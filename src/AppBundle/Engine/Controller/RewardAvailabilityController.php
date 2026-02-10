<?php


namespace AppBundle\Controller;


use AppBundle\Controller\Common\CheckItemCreatorService;
use AppBundle\Controller\Common\CheckResponseService;
use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\ProvidersListItem as RewardAvailabilityProvidersListItem;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Security\ApiUser;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RewardAvailabilityController
{

    /** @var RequestFactory */
    private $requestFactory;

    /** @var ResponseFactory */
    private $responseFactory;

    /** @var RequestValidatorService */
    private $requestValidator;

    /** @var CheckResponseService */
    private $checkResponseService;

    /** @var CheckItemCreatorService */
    private $checkItemCreatorService;

    /** @var ObjectRepository */
    private $repo;

    /** @var Connection */
    private $connection;

    /** @var CheckerFactory */
    private $checkerFactory;

    /** @var CurrencyConverter */
    private $currencyConverter;

    private const API_VERSION = 1;
    /**
     * @var LoggerInterface
     */
    private $logger;

    private TokenStorageInterface $tokenStorage;

    public function __construct(
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory,
        CheckerFactory $checkerFactory,
        RequestValidatorService $requestValidator,
        CheckResponseService $checkResponseService,
        CheckItemCreatorService $checkItemCreatorService,
        ObjectRepository $repo,
        Connection $connection,
        Loader $oldLoader,
        CurrencyConverter $currencyConverter,
        LoggerInterface $logger,
        TokenStorageInterface $tokenStorage
    )
    {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->requestValidator = $requestValidator;
        $this->checkResponseService = $checkResponseService;
        $this->checkItemCreatorService = $checkItemCreatorService;
        $this->repo = $repo;
        $this->connection = $connection;
        $this->checkerFactory = $checkerFactory;
        $this->currencyConverter = $currencyConverter;
        $this->logger = $logger;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @Route("/v1/search", host="%reward_availability_host%", name="aw_controller_v2_reward_availability", methods={"POST"})
     */
    public function searchAction(Request $httpRequest): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);
        $request = $this->requestFactory->buildRequest($httpRequest, RewardAvailabilityRequest::class, self::API_VERSION, true);
        $this->requestValidator->validateRewardAvailabilityRequest($request);
        $this->checkParams($request);

        $mongoRowId = $this->checkItemCreatorService->createCheckItem($request, self::API_VERSION, RewardAvailability::class);
        $response = $this->checkResponseService->createPostResponse($mongoRowId);
        return $this->responseFactory->buildResponse($response, self::API_VERSION);
    }

    /**
     * @Route("/v1/getResults/{requestId}", host="%reward_availability_host%", name="aw_controller_reward_availability_response", methods={"GET"})
     */
    public function getResultsAction(Request $httpRequest, string $requestId): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY]);
        $response = $this->checkResponseService->getCheckResponse($requestId, $this->repo, self::API_VERSION, RewardAvailabilityResponse::class);
        return $this->responseFactory->buildNoSwaggerResponse($response);
    }

    /**
     * @Route("/v1/providers/list", host="%reward_availability_host%", name="aw_controller_providers_reward_availability", methods={"GET"})
     * @return Response
     */
    public function providersAction(Request $request, int $apiVersion = 1)
    {
        $userName = $this->tokenStorage->getToken()->getUser()->getUsername();
        if ($userName === 'awardwallet') {
            $sqlQuery = "SELECT * FROM Provider WHERE CanCheckRewardAvailability <> 0 AND Code <> 'testprovider'";
        } else {
            $sqlQuery = "SELECT * FROM Provider WHERE CanCheckRewardAvailability = 1 AND Code <> 'testprovider'";
        }
        $result = $this->connection->executeQuery($sqlQuery)->fetchAll();

        $result = array_map(function (array $row) {
            $settings = $this->checkerFactory
                ->getRewardAvailabilityChecker($row['Code'])
                ->getRewardAvailabilitySettings();

            return new RewardAvailabilityProvidersListItem(
                $row['Code'],
                $row['DisplayName'],
                $row['ShortName'],
                $settings['supportedCurrencies'],
                (int)($settings['supportedDateFlexibility'] ?? 0)
            );
        }, $result);

        return $this->responseFactory->buildNoSwaggerResponse([
            'apiVersion' => $apiVersion,
            'providers' => $result,
        ]);
    }

    private function checkParams(RewardAvailabilityRequest $request)
    {
        $settings = $this->checkerFactory
            ->getRewardAvailabilityChecker($request->getProvider())
            ->getRewardAvailabilitySettings();

        if (!in_array(strtoupper($request->getCurrency()), $settings['supportedCurrencies'])) {
            $defaultCurrency = $settings['defaultCurrency'] ?? null;
            if (null === $defaultCurrency) {
                $defaultCurrency = in_array('USD', $settings['supportedCurrencies']) ?
                    'USD' : $settings['supportedCurrencies'][0];
            }
            if (null === $this->currencyConverter->getExchangeRate($request->getCurrency(), $defaultCurrency)) {
                $this->logger->notice("currencyConverter failed",
                    ['from' => $request->getCurrency(), 'to' => $defaultCurrency]);
                throw new InvalidParametersException(
                    sprintf('The currency you requested (%s) is not supported', $request->getCurrency()),
                    []
                );
            }
        }

        if ((int)$request->getDeparture()->getFlexibility() > (int)$settings['supportedDateFlexibility']) {
            throw new InvalidParametersException(
                sprintf(
                    'This provider only supports flexibility for +-%d days, the value you requested (%s) is not supported by this provider',
                    (int)$settings['supportedDateFlexibility'],
                    (int)$request->getDeparture()->getFlexibility()
                ),
                []
            );
        }

        $depCode = trim(strtoupper($request->getDeparture()->getAirportCode()));
        $arrCode = trim(strtoupper($request->getArrival()));
        if (!preg_match("/^[A-Z]{3}$/", $depCode)) {
            throw new InvalidParametersException('Departure airport should be only a iata code', []);
        }
        if (!preg_match("/^[A-Z]{3}$/", $arrCode)) {
            throw new InvalidParametersException('Arrival should be only a iata code', []);
        }
        if ($depCode === $arrCode) {
            throw new InvalidParametersException('Airport codes can’t be the same', []);
        }

        if ($request->getDeparture()->getDate()->getTimestamp() < strtotime('today')) {
            throw new InvalidParametersException('The departure date cannot be in the past', []);
        }

    }
}