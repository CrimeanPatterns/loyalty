<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Common\CheckItemCreatorService;
use AppBundle\Controller\Common\CheckResponseService;
use AppBundle\Controller\Common\RequestValidatorService;
use AppBundle\Document\ChangePassword;
use AppBundle\Model\Resources\ChangePasswordRequest;
use AppBundle\Security\ApiUser;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChangePasswordController
{
    protected $RepoKey = 'change-password';

    /** @var RequestFactory */
    private $requestFactory;

    /** @var ResponseFactory */
    private $responseFactory;

    /** @var ObjectRepository */
    private $repo;

    /** @var Connection */
    private $connection;

    /** @var CheckResponseService */
    private $checkResponseService;

    /** @var RequestValidatorService */
    private $requestValidator;
    /**
     * @var CheckItemCreatorService
     */
    private $checkItemCreatorService;

    public function __construct(
        CheckItemCreatorService $checkItemCreatorService,
        RequestFactory $requestFactory,
        ResponseFactory $responseFactory,
        ObjectRepository $repo,
        Connection $connection,
        CheckResponseService $checkResponseService,
        RequestValidatorService $requestValidator
    ) {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->repo = $repo;
        $this->connection = $connection;
        $this->checkResponseService = $checkResponseService;
        $this->requestValidator = $requestValidator;
        $this->checkItemCreatorService = $checkItemCreatorService;
    }

    /**
     * @Route("/v2/account/password/set", name="aw_controller_v2_change_password", methods={"POST"})
     */
    public function changePassword(Request $httpRequest): Response
    {
        $apiVersion = 2;
        $this->requestValidator->checkAccess([ApiUser::ROLE_CHANGE_PASSWORD]);

        /** @var ChangePasswordRequest $request */
        $request = $this->requestFactory->buildRequest($httpRequest, ChangePasswordRequest::class, $apiVersion);

        $this->validateRequest($request);

        $request->setPassword(CryptPassword($request->getPassword()))
                ->setNewPassword(CryptPassword($request->getNewPassword()));

        //create mongo and rabbitMQ row
        $mongoRowId = $this->checkItemCreatorService->createCheckItem($request, $apiVersion, ChangePassword::class);

        $response = $this->checkResponseService->createPostResponse($mongoRowId);
        return $this->responseFactory->buildResponse($response, $apiVersion);
    }


    private function validateRequest(ChangePasswordRequest $request)
    {
        /* миграции для БД
         ALTER TABLE `Provider`
         ADD `CanChangePasswordClient` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Возможность смены паролей аккаунтов через extension',
         ADD `CanChangePasswordServer` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Возможность смены паролей аккаунтов через Loyalty';

         ALTER TABLE `Partner`
         ADD `CanChangePassword` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Возможность смены паролей аккаунтов';
         */
        $this->requestValidator->validateRequest($request);
        if (trim($request->getNewPassword()) === '') {
            throw new InvalidParametersException(
                'The specified parameter was rejected: the "newPassword" property is required for "' . $request->getProvider() . '" provider',
                []
            );
        }

        // Check provider ability
        $providerId = $this->connection->executeQuery(
            'SELECT ProviderID FROM Provider WHERE Code = :CODE AND CanChangePasswordServer = 1',
            [':CODE' => $request->getProvider()]
        )->fetch();
        if (!$providerId) {
            throw new InvalidParametersException(
                'Changing passwords for this provider from AwardWallet servers is not supported at this time.',
                [],
                405
            );
        }
    }
}