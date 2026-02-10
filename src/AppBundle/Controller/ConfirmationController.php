<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Common\CheckItemCreatorService;
use AppBundle\Controller\Common\CheckResponseService;
use AppBundle\Controller\Common\QueueInfoService;
use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Document\CheckConfirmation;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Model\Resources\CheckConfirmationRequest;
use AppBundle\Model\Resources\CheckConfirmationResponse;
use AppBundle\Model\Resources\InputField;
use AppBundle\Security\ApiUser;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Doctrine\Common\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConfirmationController
{

    /** @var LoggerInterface */
    private $logger;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var ResponseFactory */
    private $responseFactory;

    /** @var ObjectRepository */
    private $repo;

    /** @var CheckerFactory */
    private $factory;

    /** @var DocumentManager */
    private $manager;

    /** @var CheckResponseService */
    private $checkResponseService;

    /** @var QueueInfoService */
    private $queueInfoService;

    /** @var RequestValidatorService */
    private $requestValidator;

    /** @var CheckItemCreatorService */
    private $checkItemCreatorService;

    /** @var SerializerInterface */
    protected $serializer;

    public function __construct(
        LoggerInterface $logger,
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory,
        CheckItemCreatorService $checkItemCreatorService,
        ObjectRepository $repo,
        DocumentManager $manager,
        CheckerFactory $factory,
        CheckResponseService $checkResponseService,
        QueueInfoService $queueInfoService,
        RequestValidatorService $requestValidator,
        Loader $loader,
        SerializerInterface $serializer
    ) {
        $this->logger = $logger;
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->repo = $repo;
        $this->factory = $factory;
        $this->manager = $manager;
        $this->checkResponseService = $checkResponseService;
        $this->queueInfoService = $queueInfoService;
        $this->requestValidator = $requestValidator;
        $this->checkItemCreatorService = $checkItemCreatorService;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/confirmation/check", name="aw_controller_check_confirmation", methods={"POST"})
     * @Route("/v{apiVersion}/confirmation/check", name="aw_controller_v2_check_confirmation", requirements={"apiVersion"="1|2"}, methods={"POST"})
     */
    public function checkConfirmation(Request $httpRequest, int $apiVersion = 1): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_RESERVATIONS_CONF_NO, ApiUser::ROLE_RESERVATIONS_INFO]);

        /** @var CheckConfirmationRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, CheckConfirmationRequest::class, $apiVersion);

        //-post--
        $this->validateRequest($request);

        $mongoRowId = $this->checkItemCreatorService->createCheckItem($request, $apiVersion, CheckConfirmation::class);
        $this->fillConfNo($mongoRowId, $request);

        $response = $this->checkResponseService->createPostResponse($mongoRowId);
        //-end post--

        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    /**
     * @Route("/confirmation/check/{requestId}", name="aw_controller_check_confirmation_response", methods={"GET"})
     * @Route("/v{apiVersion}/confirmation/check/{requestId}", name="aw_controller_v2_check_confirmation_response", requirements={"apiVersion"="1|2"}, methods={"GET"})
     */
    public function checkConfirmationResponse(Request $httpRequest, string $requestId, int $apiVersion = 1): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_RESERVATIONS_CONF_NO, ApiUser::ROLE_RESERVATIONS_INFO]);

        $response = $this->checkResponseService->getCheckResponse($requestId, $this->repo, $apiVersion, $this->getResponseClass($apiVersion));

        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    /**
     * @Route("/confirmation/queue", name="aw_controller_check_confirmation_queue", methods={"GET"})
     * @Route("/v{apiVersion}/confirmation/queue", name="aw_controller_v2_check_confirmation_queue", requirements={"apiVersion"="1|2"}, methods={"GET"})
     */
    public function checkAccountQueue(Request $httpRequest, int $apiVersion = 1): Response
    {
        $this->requestValidator->checkAccess([ApiUser::ROLE_RESERVATIONS_CONF_NO, ApiUser::ROLE_RESERVATIONS_INFO]);
        $response = $this->queueInfoService->queueInfo(CheckConfirmation::class);
        return $this->responseFactory->buildResponse($response, $apiVersion);
    }

    private function validateRequest(CheckConfirmationRequest $request): void
    {
        $this->requestValidator->validateRequest($request);
        // Fields
        $checker = $this->factory->getAccountChecker($request->getProvider());
        $providerConfirmationFields = $checker->GetConfirmationFields();

        $fieldValues = [];
        /** @var InputField $field */
        foreach ($request->getFields() as $field) {
            $fieldValues[$field->getCode()] = $field->getValue();
        }

        foreach ($providerConfirmationFields as $key => $desc) {
            if ($desc['Required'] === true) {
                if (!isset($fieldValues[$key]) || trim($fieldValues[$key]) == '') {
                    throw new InvalidParametersException("Field {$key} is required.", []);
                }
            }

            $errType = false;
            switch ($desc['Type']) {
                case 'integer':
                {
                    $fieldValues[$key] += 0;
                    if (!is_int($fieldValues[$key])) {
                        $errType = true;
                    }
                    break;
                }
                case 'string':
                {
                    $fieldValues[$key] .= '';
                    if (!is_string($fieldValues[$key])) {
                        $errType = true;
                    }
                    break;
                }
                case 'boolean':
                {
                    if (!is_bool($fieldValues[$key])) {
                        $errType = true;
                    }
                    break;
                }
            }
            if ($errType) {
                throw new InvalidParametersException("Field {$key} needs to be {$desc['Type']} type.", []);
            }

            if (isset($desc['Size']) && iconv_strlen($fieldValues[$key]) > $desc['Size']) {
                throw new InvalidParametersException("Field {$key} unavailable size. Limit: {$desc['Size']} characters",
                    []);
            }
        }
    }

    private function fillConfNo(string $id, CheckConfirmationRequest $request): void
    {
        /** @var CheckConfirmation $row */
        $row = $this->repo->find($id);
        if (!$row) {
            return;
        }

        $apiVersion = $row->getApiVersion() ?? 1;
        $response = $this->serializer->deserialize(json_encode($row->getResponse()), $this->getResponseClass($apiVersion), 'json');
        $checker = $this->factory->getAccountChecker($request->getProvider(), null, null, $response->getRequestdate()->getTimestamp());
        $providerConfirmationFields = $checker->GetConfirmationFields();
        if (!isset($providerConfirmationFields['ConfNo'])) {
            return;
        }

        $fields = $request->getFields();
        $confNo = null;

        /** @var InputField $field */
        foreach ($fields as $field) {
            if ($field->getCode() === 'ConfNo') {
                $confNo = $field->getValue();
                break;
            }
        }
        if (empty($confNo)) {
            return;
        }

        $row->setAccountId($confNo);
        $this->manager->persist($row);
        $this->manager->flush();
    }

    private function getResponseClass(int $apiVersion): string
    {
        if (2 === $apiVersion) {
            return \AppBundle\Model\Resources\V2\CheckConfirmationResponse::class;
        }

        return CheckConfirmationResponse::class;
    }
}