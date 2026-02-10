<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Common\CheckItemCreatorService;
use AppBundle\Controller\Common\CheckResponseService;
use AppBundle\Controller\Common\QueueInfoService;
use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Document\CheckAccount;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\AutologinWithExtensionRequest;
use AppBundle\Model\Resources\BaseCheckRequest;
use AppBundle\Model\Resources\CheckAccountPackageRequest;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\CheckExtensionSupportPackageRequest;
use AppBundle\Model\Resources\CheckExtensionSupportPackageResponse;
use AppBundle\Model\Resources\CheckExtensionSupportRequest;
use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\PostCheckErrorResponse;
use AppBundle\Model\Resources\PostCheckPackageResponse;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Model\Resources\RequestItemHistory;
use AppBundle\Security\ApiUser;
use AppBundle\Service\CryptPasswordService;
use AppBundle\Service\CryptPasswordServiceException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseAllowedInterface;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\SessionManager;
use Doctrine\Common\Persistence\ObjectRepository;
use AppBundle\Service\InvalidParametersException;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class AccountController
{

    /** @var LoggerInterface */
    private $logger;

    /** @var CryptPasswordService */
    private $cryptService;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var ResponseFactory */
    private $responseFactory;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var ObjectRepository */
    private $repo;

    /** @var CheckResponseService */
    private $checkResponseService;

    /** @var QueueInfoService */
    private $queueInfoService;

    /** @var RequestValidatorService */
    private $requestValidator;
    /**
     * @var CheckItemCreatorService
     */
    private $checkItemCreatorService;
    private SessionManager $sessionManager;
    private Connection $connection;
    private ParserFactory $parserFactory;

    public function __construct(
        LoggerInterface $logger,
        CryptPasswordService $cryptService,
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory,
        CheckItemCreatorService $checkItemCreatorService,
        TokenStorageInterface $tokenStorage,
        ObjectRepository $repo,
        CheckResponseService $checkResponseService,
        QueueInfoService $queueInfoService,
        RequestValidatorService $requestValidator,
        Loader $loader,
        SessionManager $sessionManager,
        Connection $connection,
        ParserFactory $parserFactory
    ) {
        $this->logger = $logger;
        $this->cryptService = $cryptService;
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->tokenStorage = $tokenStorage;
        $this->repo = $repo;
        $this->queueInfoService = $queueInfoService;
        $this->checkResponseService = $checkResponseService;
        $this->requestValidator = $requestValidator;
        $this->checkItemCreatorService = $checkItemCreatorService;
        $this->sessionManager = $sessionManager;
        $this->connection = $connection;
        $this->parserFactory = $parserFactory;
    }

    /**
     * @Route("/account/check", name="aw_controller_check_account", methods={"POST"})
     * @Route("/v{apiVersion}/account/check", name="aw_controller_v2_check_account", requirements={"apiVersion"="1|2"}, methods={"POST"})
     */
    public function checkAccount(Request $httpRequest, int $apiVersion = 1): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_ACCOUNT_INFO]);

        /** @var CheckAccountRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, $this->getRequestClass(), $apiVersion);
        $response = $this->post($request, $apiVersion, true);

        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    /**
     * @Route("/account/check/{requestId}", name="aw_controller_check_account_response", methods={"GET"})
     * @Route("/v{apiVersion}/account/check/{requestId}", name="aw_controller_v2_check_account_response", requirements={"apiVersion"="1|2"}, methods={"GET"})
     */
    public function checkAccountResponse(Request $httpRequest, string $requestId, int $apiVersion = 1): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_ACCOUNT_INFO]);
        $response = $this->checkResponseService->getCheckResponse($requestId, $this->repo, $apiVersion, $this->getResponseClass($apiVersion));
        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    /**
     * @Route("/account/queue", name="aw_controller_check_account_queue", methods={"GET"})
     * @Route("/v{apiVersion}/account/queue", name="aw_controller_v2_check_account_queue", requirements={"apiVersion"="1|2"}, methods={"GET"})
     */
    public function checkAccountQueue(Request $httpRequest, int $apiVersion = 1): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_ACCOUNT_INFO]);
        $response = $this->queueInfoService->queueInfo(CheckAccount::class);
        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    /**
     * @Route("/account/check/package", name="aw_controller_check_account_package", methods={"POST"})
     * @Route("/v{apiVersion}/account/check/package", name="aw_controller_v2_check_account_package", requirements={"apiVersion"="1|2"}, methods={"POST"})
     */
    public function checkAccountPackage(Request $httpRequest, int $apiVersion = 1, bool $sendToQueue = true): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_ACCOUNT_INFO]);

        /** @var CheckAccountPackageRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, CheckAccountPackageRequest::class, $apiVersion);

        $result = [];
        $errors = [];
        $startTime = time();
        /** @var CheckAccountRequest $requestItem */
        foreach ($request->getPackage() as $requestItem) {
            try {
                $result[] = $this->post($requestItem, $apiVersion, $sendToQueue);
            } catch (InvalidParametersException $e) {
                $errors[] = new PostCheckErrorResponse($e->getMessage(), $requestItem->getUserData());
            } catch (\Exception $e) {
                $this->logger->critical('Error post account package', [
                        'errorMessage' => $e->getMessage(),
                        'provider' => $requestItem->getProvider(),
                        'partner' => $user = $this->tokenStorage->getToken()->getUser()->getUsername(),
                        'userData' => $requestItem->getUserData(),
                    ]
                );
            }
        }

        $this->logger->info('Check package controller info', [
            'PackageProcessedTime' => time() - $startTime,
            'PackageSize' => count($request->getPackage()),
        ]);
        $response = (new PostCheckPackageResponse())->setPackage($result)->setErrors($errors);

        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    private function post(CheckAccountRequest $request, int $apiVersion, bool $sendToQueue): PostCheckResponse
    {
        $this->checkPermissions($request);
        $this->validateRequest($request);

        $user = $this->tokenStorage->getToken()->getUser();

        $pass = $request->getPassword();
        if (isset($pass)) {
            try {
                $request->setPassword($this->cryptService->crypt($pass, $user->getUsername()));
            } catch (CryptPasswordServiceException $e) {
                throw new InvalidParametersException($e->getMessage(), []);
            }
        }

        $browserExtensionSession = null;
        if (
            $request->isBrowserExtensionAllowed()
            &&
            $this->checkWithExtension(
                $request->getProvider(),
                new AccountOptions($request->getLogin(), $request->getLogin2(), $request->getLogin3(), $request->isBrowserExtensionIsMobile() === true)
            )
        ) {
            $browserExtensionSession = $this->sessionManager->create();
            $request->setBrowserExtensionSessionId($browserExtensionSession->getSessionId());
        }

        //create mongo and rabbitMQ row
        $mongoRowId = $this->checkItemCreatorService->createCheckItem($request, $apiVersion, CheckAccount::class, $sendToQueue);

        // temporary log partner requests
        if ($request->getParseitineraries() || $request->getParsePastIineraries()) {
            if (!in_array(ApiUser::ROLE_RESERVATIONS_INFO, $user->getRoles())) {
                $this->logger->notice('Partner has no permissions to parse itineraries', [
                    'partner' => $user->getUsername(),
                    'requestId' => $mongoRowId,
                    'provider' => $request->getProvider()
                ]);
            }
        }

        $result = $this->checkResponseService->createPostResponse($mongoRowId);

        if ($browserExtensionSession) {
            $result->setBrowserExtensionConnectionToken($browserExtensionSession->getCentrifugoJwtToken());
            $result->setBrowserExtensionSessionId($browserExtensionSession->getSessionId());
        }

        return $result;
    }

    /**
     * @Route("/v{apiVersion}/account/check-extension-support/package", name="aw_controller_v2_check_extension_support_package", requirements={"apiVersion"="1|2"}, methods={"POST"})
     */
    public function checkExtensionSupportPackage(Request $httpRequest, int $apiVersion = 1): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_ACCOUNT_INFO]);
        /** @var CheckExtensionSupportPackageRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, CheckExtensionSupportPackageRequest::class, $apiVersion);
        $supportMap = [];
        /** @var CheckExtensionSupportRequest $checkExtensionSupportRequest */
        foreach ($request->getPackage() as $checkExtensionSupportRequest) {
            $accountOptions = new AccountOptions(
                $checkExtensionSupportRequest->getLogin(),
                $checkExtensionSupportRequest->getLogin2(),
                $checkExtensionSupportRequest->getLogin3(),
                $checkExtensionSupportRequest->isMobile(),
            );
            $supportMap[$checkExtensionSupportRequest->getId()] = $this->checkWithExtension(
                $checkExtensionSupportRequest->getProvider(),
                $accountOptions
            );
        }

        $response = (new CheckExtensionSupportPackageResponse())->setPackage($supportMap);

        return $this->responseFactory->buildResponse($response, $apiVersion);

    }


    private function validateRequest(CheckAccountRequest $request)
    {
        $this->requestValidator->validateRequest($request);

        $history = $request->getHistory();
        if ($history instanceof RequestItemHistory && $history->getRange() === History::HISTORY_INCREMENTAL && empty($history->getState())) {
            throw new InvalidParametersException('The History `state` field can not be empty if the `range` field is set to `incremental`', []);
        }
    }

    private function checkPermissions(BaseCheckRequest $request): void
    {
        $permissions = [];
        /** @var CheckAccountRequest $request */
        if ($request->getParseitineraries() || $request->getParsePastIineraries()) {
            $permissions[] = ApiUser::ROLE_RESERVATIONS_INFO;
        }

        if ($request->getHistory() !== null) {
            $permissions[] = ApiUser::ROLE_ACCOUNT_HISTORY;
        }

        $this->requestValidator->checkAccess($permissions);
    }

    private function getRequestClass(): string
    {
        return CheckAccountRequest::class;
    }

    private function getResponseClass(int $apiVersion): string
    {
        if (2 === $apiVersion) {
            return \AppBundle\Model\Resources\V2\CheckAccountResponse::class;
        }
        return CheckAccountResponse::class;
    }

    private function checkWithExtension(string $provider, AccountOptions $options) : bool
    {
        return
            $this->connection->fetchOne("select IsExtensionV3ParserEnabled from Provider where Code = ?", [$provider])
            && $this->extensionParserAllowCheck($provider, $options);
    }

    private function extensionParserAllowCheck(string $provider, AccountOptions $options) : bool
    {
        $parser = $this->parserFactory->getParserOptions($provider);
        if ($parser instanceof ParseAllowedInterface) {
            return $parser->isParseAllowed($options);
        }

        return true;
    }

}