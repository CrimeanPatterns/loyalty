<?php

namespace AppBundle\Controller;

use AppBundle\Document\AutoLogin;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQMessages\CheckPartnerMessage;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\ProvidersHelper;
use AppBundle\Model\Resources\AutoLoginRequest;
use AppBundle\Model\Resources\AutoLoginResponse;
use AppBundle\Model\Resources\UserData;
use AppBundle\Security\ApiUser;
use AppBundle\Service\CryptPasswordService;
use AppBundle\Service\CryptPasswordServiceException;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Exception\Exception;
use JMS\Serializer\SerializerInterface;
use Monolog\Logger;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AutoLoginController
{

    /** @var Connection */
    private $connection;
    /** @var TokenStorageInterface */
    private $tokenStorage;
    /** @var MQSender */
    private $mqSender;
    /** @var SerializerInterface */
    private $serializer;
    /** @var Logger */
    private $logger;
    /** @var DocumentManager */
    private $dm;
    /** @var ProvidersHelper */
    private $providersHelper;
    /** @var CryptPasswordService */
    private $cryptService;
    /** @var ResponseFactory */
    private $responseFactory;
    /** @var RequestFactory */
    private $requestFactory;

    private const AUTOLOGIN_PRIORITY = 10;


    public function __construct(
        Connection $connection,
        TokenStorageInterface $tokenStorage,
        MQSender $mqSender,
        SerializerInterface $serializer,
        Logger $logger,
        Loader $loader,
        DocumentManager $dm,
        CryptPasswordService $cryptService,
        ProvidersHelper $providersHelper,
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory
    ) {
        $this->connection = $connection;
        $this->tokenStorage = $tokenStorage;
        $this->mqSender = $mqSender;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->dm = $dm;
        $this->cryptService = $cryptService;
        $this->providersHelper = $providersHelper;
        $this->responseFactory = $responseFactory;
        $this->requestFactory = $requestFactory;
    }

    /**
     * @Route("/autologin", name="aw_controller_autologin", methods={"POST"})
     * @Route("/v{apiVersion}/autologin", name="aw_controller_v2_autologin", requirements={"apiVersion"="1|2"}, methods={"POST"})
     */
    public function post(Request $httpRequest, int $apiVersion = 1): Response
    {
        /** @var AutoLoginRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, AutoLoginRequest::class, $apiVersion);

        /** @var ApiUser $user */
        $user = $this->tokenStorage->getToken()->getUser();
        $partner = $user->getUsername();
        $protocols = array_intersect(['http', 'https'], $request->getSupportedProtocols());

        if (empty($protocols)) {
            throw new InvalidParametersException('Unavailable supported protocols.', []);
        }

        $sql = $this->providersHelper->AvailableProvidersQuery('check', $user);
        $fields = $this->connection->executeQuery($sql, [
            ':CODE' => $request->getProvider(),
            ':PROVIDER_ENABLED' => PROVIDER_ENABLED,
            ':PROVIDER_WSDL_ONLY' => PROVIDER_WSDL_ONLY,
        ])->fetch();

        if (!$fields) {
            throw new InvalidParametersException(
                'Unavailable provider code, please use the "/providers/list" call to get the correct provider code.',
                []
            );
        }

        $pass = $request->getPassword();
        if (isset($pass)) {
            try {
                $request->setPassword($this->cryptService->crypt($pass, $user->getUsername()));
            } catch (CryptPasswordServiceException $e) {
                throw new InvalidParametersException($e->getMessage(), []);
            }
        }

        $queue = $this->mqSender->declareTmpQueue();

        $this->logger->pushProcessor(function (array $record) use ($partner, $queue, $request) {
            $record['extra']['partner'] = $partner;
            $record['extra']['provider'] = $request->getProvider();
            $record['extra']['tmpQueue'] = $queue;
            $record['extra']['login'] = $request->getLogin();
            $record['extra']['userData'] = $request->getUserData();
            return $record;
        });

        if (!empty($queue)) {
            $document = $this->createMongoDocument($request, $queue, $user);

            $this->createRabbitMessage($document->getId(), $partner, AutoLogin::METHOD_KEY);

            $document->setQueuedate(new \DateTime());
            $this->logger->info('AutoLogin processing start');
            $response = $this->waitAutologinResult($queue);

            if ($response !== null) {
                $this->logger->popProcessor();
                return $this->responseFactory->buildResponse($response, $apiVersion);
            }
        } else {
            $this->logger->critical('Temp autologin queue creating error');
        }

        $arg = [
            'RedirectURL' => $fields['LoginURL'],
            'NoCookieURL' => true,
            'RequestMethod' => 'GET',
            'AutoLogin' => $fields['AutoLogin']
        ];

        $response = (new AutoLoginResponse())->setUserData($request->getUserData());

        $manager = new \AutologinManager(null, $arg);
        ob_start();
        $manager->drawPage();
        $response->setResponse(ob_get_clean());

        $this->logger->popProcessor();
        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    private function createMongoDocument(AutoLoginRequest $request, string $queue, ApiUser $user): AutoLogin
    {
        $document = new AutoLogin(
            $this->serializer->toArray($request), $user->getUsername(), $queue
        );

        if (in_array(ApiUser::ROLE_ADMIN, $user->getRoles())) {
            try {
                /** @var UserData $userData */
                $userData = $this->serializer->deserialize($request->getUserData(), UserData::class, 'json');
                $document->setAccountId($userData->getAccountId());
            } catch (Exception $e) {
                $this->logger->critical(
                    'Can not deserialize autologin request userData from awardwallet partner',
                    ['userData' => $request->getUserData(), 'error' => $e->getMessage()]
                );
            }
        }

        $this->dm->persist($document);
        $this->dm->flush();

        return $document;
    }

    private function createRabbitMessage(string $requestId, string $partner, string $method): void
    {
        $mqPartnerMsg = new CheckPartnerMessage(
            $requestId, $method, $partner, self::AUTOLOGIN_PRIORITY
        );

        $this->mqSender->sendCheckPartner($mqPartnerMsg);
        $this->mqSender->sendCheckNews($partner);
    }

    private function waitAutologinResult($queue, $timeout = 55): ?AutoLoginResponse
    {
        $response = null;
        $callback = function (AMQPMessage $msg) use (&$response) {
            /** @var AutoLoginResponse $result */
            $result = unserialize($msg->body);
            if (!$result instanceof AutoLoginResponse) {
                $this->logger->critical('MQ Message unserialize error', ['message' => $msg->body]);
                return null;
            }

            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            $response = $result;
        };

        try {
            $this->mqSender->waitQueueMessage($queue, $callback, $timeout);
            $this->logger->info('AutoLogin result success wait');
        } catch (AMQPTimeoutException $e) {
            $this->logger->warning('AutoLogin processing timeout');
        } finally {
            return $response;
        }
    }

}