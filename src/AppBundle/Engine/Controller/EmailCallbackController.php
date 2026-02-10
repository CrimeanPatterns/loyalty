<?php


namespace AppBundle\Controller;

use AppBundle\Email\CallbackProcessor;
use AwardWallet\Common\API\Email\V2\ParseEmailResponse;
use AwardWallet\Common\Strings;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/callback")
 */
class EmailCallbackController
{

    /** @var LoggerInterface  */
    private $logger;
    /** @var Serializer  */
    private $jms;
    /** @var CallbackProcessor  */
    private $processor;
    private $callbackPassword;

    public function __construct(LoggerInterface $logger, Serializer $jms, CallbackProcessor $processor, string $callbackPass)
    {
        $this->logger = $logger;
        $this->jms = $jms;
        $this->processor = $processor;
        $this->callbackPassword = $callbackPass;
    }

    /**
     * @Route("/email", name="aw_email_callback", methods={"POST"})
     */
    public function callbackAction(Request $request): JsonResponse
    {

        if ($request->getUser() != 'awardwallet' || empty($request->getPassword()) || $request->getPassword() != $this->callbackPassword) {
            $this->logger->info("access denied for " . $request->getUser());
            return new JsonResponse(['error' => 'access denied'], 403);
        }
        try {
            $response = $this->jms->deserialize($request->getContent(), ParseEmailResponse::class, 'json');
            $success = $this->processor->processResponse($response);
            return new JsonResponse(["success" => $success]);
        }
        catch(RuntimeException $e) {
            return new JsonResponse(['error' => 'invalid content'], 400);
        }
    }

}