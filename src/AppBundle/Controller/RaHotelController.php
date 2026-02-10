<?php


namespace AppBundle\Controller;


use AppBundle\Controller\Common\CheckItemCreatorService;
use AppBundle\Controller\Common\CheckResponseService;
use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Document\RaHotel;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\ProvidersListItem as RaHotelProvidersListItem;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use AppBundle\Security\ApiUser;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RaHotelController
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

    private const API_VERSION = 1;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory,
        RequestValidatorService $requestValidator,
        CheckResponseService $checkResponseService,
        CheckItemCreatorService $checkItemCreatorService,
        ObjectRepository $repo,
        Connection $connection,
        Loader $oldLoader,
        LoggerInterface $logger
    ) {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->requestValidator = $requestValidator;
        $this->checkResponseService = $checkResponseService;
        $this->checkItemCreatorService = $checkItemCreatorService;
        $this->repo = $repo;
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * @Route("/v1/search", host="%reward_availability_hotel_host%", name="aw_controller_v2_reward_availability_hotel", methods={"POST"})
     */
    public function searchAction(Request $httpRequest): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY_HOTEL]);
        $request = $this->requestFactory->buildRequest($httpRequest, RaHotelRequest::class, self::API_VERSION, true);
        $this->requestValidator->validateRewardAvailabilityRequest($request);
        $this->checkParams($request);

        $mongoRowId = $this->checkItemCreatorService->createCheckItem($request, self::API_VERSION, RaHotel::class);
        $response = $this->checkResponseService->createPostResponse($mongoRowId);
        return $this->responseFactory->buildResponse($response, self::API_VERSION);
    }

    /**
     * @Route("/v1/getResults/{requestId}", host="%reward_availability_hotel_host%", name="aw_controller_reward_availability_hotel_response", methods={"GET"})
     */
    public function getResultsAction(Request $httpRequest, string $requestId): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_REWARD_AVAILABILITY_HOTEL]);
        $response = $this->checkResponseService->getCheckResponse($requestId, $this->repo, self::API_VERSION,
            RaHotelResponse::class);
        return $this->responseFactory->buildNoSwaggerResponse($response);
    }

    /**
     * @Route("/v1/providers/list", host="%reward_availability_hotel_host%", name="aw_controller_providers_reward_availability_hotel", methods={"GET"})
     * @return Response
     */
    public function providersAction(Request $request, int $apiVersion = 1)
    {
        $result = $this->connection->executeQuery(
            "SELECT * FROM Provider WHERE CanCheckRaHotel = 1 AND Code <> 'testprovider'"
        )->fetchAllAssociative();

        $result = array_map(function (array $row) {
            return new RaHotelProvidersListItem(
                $row['Code'],
                $row['DisplayName'],
                $row['ShortName']
            );
        }, $result);

        return $this->responseFactory->buildNoSwaggerResponse([
            'apiVersion' => $apiVersion,
            'providers' => $result,
        ]);
    }

    private function checkParams(RaHotelRequest $request)
    {
        if ($request->getCheckInDate()->getTimestamp() < strtotime('today')) {
            throw new InvalidParametersException('The checkin date cannot be in the past', []);
        }

        if (!preg_match("/[\w\-,: ]+/im",$request->getDestination())) {
            throw new InvalidParametersException('Destination contains invalid character', []);
        }

        if (strlen($request->getDestination()) < 3) {
            throw new InvalidParametersException('Too short destination', []);
        }

        if ($request->getCheckInDate()->getTimestamp() > $request->getCheckOutDate()->getTimestamp()) {
            throw new InvalidParametersException('checkout date earlier than check in date', []);
        }
    }
}