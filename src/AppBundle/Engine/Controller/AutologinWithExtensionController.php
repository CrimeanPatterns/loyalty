<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Common\CheckItemCreatorService;
use AppBundle\Document\AutoLoginWithExtension;
use AppBundle\Model\Resources\AutologinWithExtensionRequest;
use AppBundle\Model\Resources\PostCheckResponse;
use AppBundle\Security\ApiUser;
use AppBundle\Service\AccessChecker;
use AppBundle\Service\CryptPasswordService;
use AppBundle\Service\CryptPasswordServiceException;
use AppBundle\Service\InvalidParametersException;
use AppBundle\Service\RequestFactory;
use AppBundle\Service\ResponseFactory;
use AwardWallet\ExtensionWorker\SessionManager;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AutologinWithExtensionController extends AbstractController
{

    private Connection $connection;
    private CryptPasswordService $cryptService;
    private SessionManager $sessionManager;
    private CheckItemCreatorService $checkItemCreatorService;
    private ResponseFactory $responseFactory;

    public function __construct(
        Connection $connection,
        CryptPasswordService $cryptService,
        SessionManager $sessionManager,
        CheckItemCreatorService $checkItemCreatorService,
        ResponseFactory $responseFactory
    )
    {
        $this->connection = $connection;
        $this->cryptService = $cryptService;
        $this->sessionManager = $sessionManager;
        $this->checkItemCreatorService = $checkItemCreatorService;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @Route("/v{apiVersion}/account/autologin-with-extension", name="aw_controller_v2_account_autologin_with_extension", requirements={"apiVersion"="2"}, methods={"POST"})
     */
    public function autologinWithExtension(
        Request $httpRequest,
        int $apiVersion,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        RequestFactory $requestFactory
    ): Response
    {
        // awardwallet only for now
        if (!$authorizationChecker->isGranted(ApiUser::ROLE_DEBUG)) {
            throw new InvalidParametersException(AccessChecker::getAccessDeniedMessage(ApiUser::ROLE_DEBUG), [], 403);
        }

        /** @var AutologinWithExtensionRequest $request */
        $request = $requestFactory->buildRequest($httpRequest, AutologinWithExtensionRequest::class, $apiVersion);
        if (!$this->autologinAllowedToProvider($request->getProvider())) {
            throw new InvalidParametersException('Autologin with extension is not allowed to this provider', [], 400);
        }

        $pass = $request->getPassword();
        if (isset($pass)) {
            try {
                $request->setPassword($this->cryptService->crypt($pass, $tokenStorage->getToken()->getUsername()));
            } catch (CryptPasswordServiceException $e) {
                throw new InvalidParametersException($e->getMessage(), []);
            }
        }

        $browserExtensionSession = $this->sessionManager->create();
        $request->setBrowserExtensionSessionId($browserExtensionSession->getSessionId());
        $request->setPriority(9); // for compatibility with other methods, actually there is no priority for this method

        $mongoRowId = $this->checkItemCreatorService->createCheckItem($request, $apiVersion, AutologinWithExtension::class, true, 30000);

        $result = (new PostCheckResponse())->setRequestid($mongoRowId);
        $result->setBrowserExtensionConnectionToken($browserExtensionSession->getCentrifugoJwtToken());
        $result->setBrowserExtensionSessionId($browserExtensionSession->getSessionId());

        return $this->responseFactory->buildResponse($result, $apiVersion);
    }

    private function autologinAllowedToProvider(string $provider) : bool
    {
        return $this->connection->fetchOne("select AutologinV3 from Provider where Code = ?", [$provider]) == 1;
    }

}