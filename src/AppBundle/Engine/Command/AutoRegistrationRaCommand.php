<?php

namespace AppBundle\Command;

use AppBundle\Controller\RegisterController;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RegisterConfig;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Security\ApiToken;
use AppBundle\Security\ApiUser;
use AppBundle\Service\AutoRegisterService;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AutoRegistrationRaCommand extends Command
{
    protected static $defaultName = 'aw:auto-registration-ra';

    private const PARTNER = 'awardwallet';

    private DocumentManager $dm;
    private Connection $connection;
    private AutoRegisterService $autoRegService;
    private TokenStorageInterface $tokenStorage;
    private SerializerInterface $serializer;
    private RegisterController $controller;
    private LoggerInterface $logger;
    private UrlGeneratorInterface $router;
    private RequestStack $requestStack;

    public function __construct(
        DocumentManager $dm,
        Connection $connection,
        AutoRegisterService $autoRegService,
        TokenStorageInterface $tokenStorage,
        SerializerInterface $serializer,
        RegisterController $controller,
        LoggerInterface $logger,
        UrlGeneratorInterface $router,
        RequestStack $requestStack
    ) {
        parent::__construct();
        $this->dm = $dm;
        $this->connection = $connection;
        $this->autoRegService = $autoRegService;
        $this->tokenStorage = $tokenStorage;
        $this->serializer = $serializer;
        $this->controller = $controller;
        $this->logger = $logger;
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Check RA accounts and create new if it needs'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->tokenStorage->setToken($this->createApiRoleUserToken());

        $providerConfigs = $this->dm->createQueryBuilder(RegisterConfig::class)
            ->hydrate(false)
            ->field('isActive')->equals(true)
            ->getQuery()
            ->execute()
            ->toArray();

        $responses = [];

        foreach ($providerConfigs as $config) {
            $this->autoRegService->enabledReservedAccountsBySchedule($config);
            $diffCount = $this->autoRegService->getDiffCount($config);

            for ($i = 0; $i < $diffCount; $i++) {
                try {
                    $request = $this->autoRegService->generateRegisterRequest(
                        $config['provider'],
                        $config['defaultEmail'],
                        $config['delay'],
                        $i,
                        true
                    );
                } catch (\EngineError $e) {
                    $this->logger->error($e->getMessage());
                    break;
                }

                if (is_null($request)) {
                    break;
                }

                $regUrl = $this->router->generate('ra_post_account_register');
                $httpRequest = Request::create($regUrl, 'POST', [], [], [], [], $this->serializer->serialize($request, 'json'));
                $this->requestStack->push($httpRequest);
                $httpResponse = $this->controller->postRAAccountRegisterAction($httpRequest);

                if(strstr($httpResponse->getContent(), 'error')) {
                    $responses[] = [
                        'identity' => $request->getFields(),
                        'error' => json_decode($httpResponse->getContent())->error
                    ];
                } else {
                    $responses[] = [
                        'requestId' => $this->serializer->deserialize($httpResponse->getContent(), PostCheckResponse::class, 'json')->getRequestid()
                    ];
                }
                $this->requestStack->pop();
            }
        }

        $this->logger->info(
            "Result of requests AutoRegistration.",
            [ "result" => print_r($responses, true),]
        );

        return 0;
    }

    private function createApiRoleUserToken(): ApiToken
    {
        $sql = "
            SELECT PartnerID, Login, Pass, CanDebug, Threads FROM Partner
            WHERE Login = :PARTNER
        ";
        $result = $this->connection->executeQuery($sql, [':PARTNER' => self::PARTNER])->fetch();
        $user = new ApiUser($result['Login'], $result['Pass'], $result['PartnerID'], $result['Threads'],
            array_merge([ApiUser::ROLE_USER], ApiUser::ALLOWED_ROLES));
        return new ApiToken(
            $user,
            $result['Login'] . ':' . $result['Pass'],
            'secured',
            $user->getRoles()
        );
    }
}