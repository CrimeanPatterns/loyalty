<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Common\CheckItemCreatorService;
use AppBundle\Controller\Common\CheckResponseService;
use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RegisterConfig;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountAutoRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AppBundle\Security\ApiUser;
use AppBundle\Service\AutoRegisterService;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class RegisterController
{

    const API_VERSION = 1;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var ResponseFactory */
    private $responseFactory;

    /** @var RequestValidatorService */
    private $requestValidator;

    /** @var DocumentManager */
    private $manager;

    /** @var CheckerFactory */
    private $checkerFactory;

    /** @var Connection */
    private $connection;

    /** @var CheckItemCreatorService */
    private $checkItemCreatorService;

    /** @var LoggerInterface */
    private $logger;

    /** @var ObjectRepository */
    private $repo;

    /** @var CheckResponseService */
    private $checkResponseService;

    /** @var AutoRegisterService */
    private $autoRegService;

    /** @var SerializerInterface */
    private $serializer;

    /** @var string */
    private $aesKey;

    public function __construct(
        LoggerInterface $logger,
        DocumentManager $manager,
        Connection $connection,
        CheckerFactory $checkerFactory,
        ObjectRepository $repo,
        CheckResponseService $checkResponseService,
        RequestFactory $requestFactory,
        RequestValidatorService $requestValidator,
        ResponseFactory $responseFactory,
        CheckItemCreatorService $checkItemCreatorService,
        SerializerInterface $serializer,
        AutoRegisterService $autoRegService,
        $aesKey,
        Loader $loader
    ) {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->connection = $connection;
        $this->checkerFactory = $checkerFactory;
        $this->requestValidator = $requestValidator;
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;

        $this->repo = $repo;
        $this->checkResponseService = $checkResponseService;
        $this->checkItemCreatorService = $checkItemCreatorService;
        $this->serializer = $serializer;
        $this->autoRegService = $autoRegService;
        $this->aesKey = $aesKey;
    }

    /**
     * Registering a reward availability program account with the given parameters.
     *
     * @Route("/ra-account/register", host="%reward_availability_host%", name="ra_post_account_register", methods={"POST"})
     * @return Response
     */
    public function postRAAccountRegisterAction(Request $httpRequest): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]); // TODO check role
        $request = $this->requestFactory->buildRequest($httpRequest, RegisterAccountRequest::class, 1, false);

        return $this->processPostRequest($request);
    }

    /**
     * Automatic registering a reward availability program account.
     *
     * @Route("/ra-account/register-auto", host="%reward_availability_host%", name="ra_post_account_register_auto", methods={"POST"})
     * @return Response
     */
    public function postRAAccountRegisterAutoAction(Request $httpRequest): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);
        $request = $this->requestFactory->buildRequest($httpRequest, RegisterAccountAutoRequest::class, 1, false);

        $email = $request->getEmail();

        if (!$email) {
            $config = $this->manager->getRepository(RegisterConfig::class)
                ->findOneBy([
                    'isActive' => true,
                    'provider' => $request->getProvider()
                ]);

            if (!$config) {
                return $this->responseFactory->buildNoSwaggerResponse([
                    "error" => "No active configuration for {$request->getProvider()} provider."
                ]);
            }
            $email = $config->getDefaultEmail();
        }

        if (!preg_match("/^.*@\w.*\.\w+/", $email)){
            return $this->responseFactory->buildNoSwaggerResponse([
                "error" => "Wrong format email. Should be email or @domain"
            ]);
        }

        $responses = [];
        for ($i = 0; $i < $request->getCount(); $i++) {
            try {
                $regAccRequest = $this->autoRegService->generateRegisterRequest(
                    $request->getProvider(),
                    $email,
                    $request->getDelay(),
                    $i
                );
            } catch (\EngineError $e) {
                return $this->responseFactory->buildNoSwaggerResponse([
                    'error' => $e->getMessage()
                ]);
            }

            if (is_null($regAccRequest)) {
                break;
            }

            $response = $this->processPostRequest($regAccRequest);

            if(strpos($response->getContent(), 'error') !== false) {
                $responses[] = [
                    'request' => $regAccRequest,
                    'error' => json_decode($response->getContent())->error
                ];
            } else {
                $responses[] = [
                    'requestId' => $this->serializer->deserialize($response->getContent(), PostCheckResponse::class, 'json')->getRequestid()
                ];
            }
        }

        return $this->responseFactory->buildNoSwaggerResponse($responses);
    }

    /**
     * Getting the result of registration request.
     *
     * @Route("/ra-account/register/{id}", host="%reward_availability_host%", name="get_ra_account_register", methods={"GET"})
     * @return Response
     */
    public function getRAAccountRegisterAction($id): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);
        $response = $this->checkResponseService->getCheckResponse($id, $this->repo, self::API_VERSION,
            RegisterAccountResponse::class);

        return $this->responseFactory->buildNoSwaggerResponse($response);
    }

    /**
     * Getting a list of providers which supported register accounts for reward availability.
     *
     * @Route("/ra-providers/register/list", host="%reward_availability_host%", name="ra_register_providers", methods={"GET"})
     * @return Response
     */
    public function getRARegisterProvidersListAction(): Response
    {
        $sql = <<<SQL
          SELECT ProviderID, Code, DisplayName, CanRegisterRewardAvailabilityAccount
          FROM Provider
          WHERE CanRegisterRewardAvailabilityAccount = 1 AND Code <> 'testprovider'
          ORDER BY DisplayName
SQL;
        $result = $this->connection->executeQuery($sql)->fetchAllAssociative();

        $data = [];
        foreach ($result as $prov) {
            $data[] = [
                'provider' => $prov['Code'],
                'name' => $prov['DisplayName']
            ];
        }
        return $this->responseFactory->buildNoSwaggerResponse(['providers_list' => $data]);
    }

    /**
     * Getting a list of parameter fields (with definitions) for register for a specific provider.
     *
     * @Route("/ra-providers/register/{provider}/fields", host="%reward_availability_host%", name="ra_get_providers_fields", methods={"GET"})
     * @return Response
     */
    public function getRARegisterProviderFieldsAction($provider): Response
    {
        $sql = <<<SQL
          SELECT ProviderID, Code, DisplayName, CanRegisterRewardAvailabilityAccount
          FROM Provider
          WHERE Code = :CODE
SQL;
        $result = $this->connection->executeQuery($sql, [':CODE' => $provider])->fetch();
        if (!$result) {
            throw new InvalidParametersException(sprintf('Unavailable provider %s', $provider), []);
        }
        if ($result['CanRegisterRewardAvailabilityAccount'] != '1') {
            throw new InvalidParametersException(sprintf('Unavailable method for provider %s', $provider), []);
        }

        $checker = $this->checkerFactory->getRewardAvailabilityRegister($provider);
        $data = ['fields' => $checker->getRegisterFields()];

        return $this->responseFactory->buildNoSwaggerResponse(['request_data' => $data]);
    }

    /**
     * Getting a list of fail register reports.
     *
     * @Route("/ra-account/report-fail-register", host="%reward_availability_host%", name="ra_get_account_report_fail_register", methods={"GET"})
     * @return Response
     */
    public function getFailReportAction(): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);

        $regAccount = $this->manager->createQueryBuilder(RegisterAccount::class)
            ->hydrate(false)
            ->select(['accountId', 'partner', 'method', 'request', 'response'])
            ->field('response.state')->notEqual(0)
            ->field('isChecked')->equals(false)
            ->getQuery()
            ->execute()
            ->toArray();

        $report = [];
        foreach ($regAccount as $key => $row) {
            $newRow = [
                'provider' => $row['request']['provider'],
                'isAuto' => $row['request']['isAuto'],
                'message' => $row['response']['message'],
                'requestId' => $row['response']['requestId'],
                'fields' => $row['request']['fields'],
                'partner' => $row['partner'],
            ];
            if(isset($row['method'])) {
                $newRow['method'] = $row['method'];
            }
            if(isset($row['accountId'])) {
                $newRow['accountId'] = $row['accountId'];
            }
            $report[] = $newRow;
        }

        $queueRegAccount = $this->manager->createQueryBuilder(RegisterAccount::class)
            ->hydrate(false)
            ->field('response.state')->equals(0)
            ->field('isChecked')->equals(false)
            ->getQuery()
            ->execute()
            ->toArray();

        $queue = [];
        foreach ($queueRegAccount as $key => $row) {
            if (!isset($queue[$row['request']['provider']])) {
                $queue[$row['request']['provider']] = 1;
            } else {
                $queue[$row['request']['provider']]++;
            }
        }

        return $this->responseFactory->buildNoSwaggerResponse([
            'fail' => $report,
            'queue' => $queue,
        ]);
    }

    /**
     * Retry failed registration request.
     *
     * @Route("/ra-account/register-request-retry/{id}", host="%reward_availability_host%", name="ra_get_account_register_retry", methods={"POST"})
     * @return Response
     */
    public function retryFailRegistrationAction($id): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);

        return $this->responseFactory->buildNoSwaggerResponse(
            $this->autoRegService->retryFailRegistration($id)
        );
    }

    /**
     * Retry failed registration request.
     *
     * @Route("/ra-account/register-request-check/{id}", host="%reward_availability_host%", name="ra_get_account_register_check", methods={"POST"})
     * @return Response
     */
    public function checkedRegistrationAction($id): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);
        $regAcc = $this->manager->getRepository(RegisterAccount::class)
            ->find($id);

        if ($regAcc) {
            $regAcc->setIsChecked(true);
            $this->manager->persist($regAcc);
            $this->manager->flush();

            return $this->responseFactory->buildNoSwaggerResponse([
                "status" => "ok",
                "requestId" => $id,
                "message" => "Successful checked.",
            ]);
        }

        return $this->responseFactory->buildNoSwaggerResponse([
            "status" => "error",
            "requestId" => $id,
            "message" => 'Register account request not found.',
        ]);
    }

    /**
     * Getting a list of registration queue records by provider.
     *
     * @Route("/ra-account/queue/{provider}/list", host="%reward_availability_host%", name="ra_get_account_queue_list", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function getQueueList($provider): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);

        $queueRegAccount = $this->manager->createQueryBuilder(RegisterAccount::class)
            ->hydrate(false)
            ->field('response.state')->equals(0)
            ->field('request.provider')->equals($provider)
            ->field('isChecked')->equals(false)
            ->getQuery()
            ->execute()
            ->toArray();

        $queue = [];
        foreach ($queueRegAccount as $key => $row) {
            $queue[$key] = [
                'provider' => $row['request']['provider'],
                'queueDate' => $row['queuedate'],
                'registerNotEarlierDate' => $row['request']['registerNotEarlierDate'],
                'fields' => $row['request']['fields'],
            ];
        }

        return $this->responseFactory->buildNoSwaggerResponse($queue);
    }

    /**
     * Clear a list of registration queue records by provider.
     *
     * @Route("/ra-account/queue/{provider}/clear", host="%reward_availability_host%", name="ra_get_account_queue_clear", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function getQueueClear($provider): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);

        $result = $this->manager->createQueryBuilder(RegisterAccount::class)
            ->remove()
            ->field('response.state')->equals(0)
            ->field('request.provider')->equals($provider)
            ->field('isChecked')->equals(false)
            ->getQuery()
            ->execute();

        if (!$result) {
            return new Response(
                json_encode([
                    'success' => false,
                    'message' => "Not found any rows."
                ]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => true,
                'message' => "Successful delete queue rows."
            ]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Delete register queue row by id.
     *
     * @Route("/ra-account/queue/{id}/delete", host="%reward_availability_host%", name="ra_register_queue_delete", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * @return Response
     */
    public function deleteQueueRowAction(string $id): Response
    {
        $result = $this->manager->createQueryBuilder(RegisterAccount::class)
            ->findAndRemove()
            ->field('_id')->equals($id)
            ->getQuery()->execute();

        if (!$result) {
            return new Response(
                json_encode([
                    'success' => false,
                    'message' => "Not found queue row."
                ]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response(
            json_encode([
                'success' => true,
                'message' => "Successful delete queue row."
            ]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Add registration data as account.
     *
     * @Route("/ra-account/register-request-account/{id}/{state}", host="%reward_availability_host%", name="ra_get_account_register_account", methods={"POST"}, requirements={"id"="[a-z\d]{24}"})
     * @return Response
     */
    public function saveRequestDataAsAccountAction($id, $state): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);
        if (!in_array($state,RaAccount::STATES_LABEL)){
            return $this->responseFactory->buildNoSwaggerResponse([
                "status" => "error",
                "requestId" => $id,
                "accountId" => null,
                "message" => 'Invalid state for saving.',
            ]);
        }
        $state = array_search($state, RaAccount::STATES_LABEL);
        $regAcc = $this->manager->getRepository(RegisterAccount::class)
            ->find($id);


        if (!$regAcc) {
            return $this->responseFactory->buildNoSwaggerResponse([
                "status" => "error",
                "requestId" => $id,
                "accountId" => null,
                "message" => 'Register account request not found.',
            ]);
        }
        /** @var RegisterAccountRequest $request */
        $request = $regAcc->getRequest();
        $provider = $request->getProvider();
        $fields = $request->getFields();
        $email = $fields['Email'];
        $password = $fields['Password'];
        $account = $this->manager->getRepository(RaAccount::class)
            ->findOneBy(['email'=>$email,'provider'=>$provider]);
        if ($account) {
            return $this->responseFactory->buildNoSwaggerResponse([
                "status" => "error",
                "requestId" => $id,
                "accountId" => $account->getId(),
                "message" => 'An account with the specified email address already exists.',
            ]);
        }
        $account = new RaAccount();
        $this->manager->persist($account);
        $account
            ->setProvider($provider)
            ->setLogin($email)
            ->setPass($this->manager->getRepository(RaAccount::class)->encodePassword($password, $this->aesKey))
            ->setEmail($email)
            ->setErrorCode(ACCOUNT_UNCHECKED)
            ->setState($state);
        $this->manager->persist($account);
        $regAcc->setAccId($account->getId());
        $this->manager->persist($regAcc);
        $this->manager->flush();

        return $this->responseFactory->buildNoSwaggerResponse([
            "status" => "ok",
            "requestId" => $id,
            "accountId" => $account->getId(),
            "message" => "Successfully added.",
        ]);

    }

    private function processPostRequest(RegisterAccountRequest $request)
    {
        // check provider availability
        $result = $this->connection
            ->executeQuery("SELECT ProviderID, CanRegisterRewardAvailabilityAccount FROM Provider WHERE Code = :CODE",
                [':CODE' => $request->getProvider()])
            ->fetchAssociative();
        if (!$result or $result['CanRegisterRewardAvailabilityAccount'] != '1') {
            throw new InvalidParametersException('Unavailable provider', []);
        }

        if (!empty($request->getFields())) {
            try {
                $this->checkParams($request);
            } catch (\Exception $e) {
                return $this->responseFactory->buildNoSwaggerResponse(["error" => $e->getMessage()]);
            }
        }

        $mongoRowId = $this->checkItemCreatorService->createCheckItem($request, self::API_VERSION, RegisterAccount::class);
        $response = $this->checkResponseService->createPostResponse($mongoRowId);

        return $this->responseFactory->buildResponse($response, self::API_VERSION);
    }

    private function checkParams(RegisterAccountRequest $request)
    {
        $checker = $this->checkerFactory->getRewardAvailabilityRegister($request->getProvider());

        $undefined = [];
        $fields = $request->getFields();
        foreach ($checker->getRegisterFields() as $key => $descr) {
            if (($descr['Required'] === false || !isset($descr['Required'])) and trim($fields[$key]) == '') {
                continue;
            }

            if (!isset($fields[$key])) {
                $undefined[] = $key;
                continue;
            }

            if (!empty($descr['Options']) and !array_key_exists($fields[$key], $descr['Options'])) {
                $undefined[] = $key;
                continue;
            }

            $errType = false;
            switch ($descr['Type']) {
                case 'integer':
                {
                    $fields[$key] += 0;
                    if (!is_int($fields[$key])) {
                        $errType = true;
                    }
                    break;
                }
                case 'string':
                {
                    $fields[$key] .= '';
                    if (!is_string($fields[$key])) {
                        $errType = true;
                    }
                    break;
                }
                case 'boolean':
                {
                    if (!is_bool($fields[$key])) {
                        $errType = true;
                    }
                    break;
                }
            }
            if ($errType) {
                $undefined[] = $key;
                continue;
            }

            if (strtolower($descr['Type']) === 'string' and trim($fields[$key]) == '') {
                $undefined[] = $key;
            }
        }
        if (!empty($undefined)) {
            throw new \Exception('Undefined fields values (' . implode(',', $undefined) . ')');
        }
    }
}