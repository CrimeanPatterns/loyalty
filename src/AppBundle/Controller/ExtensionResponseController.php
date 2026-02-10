<?php

namespace AppBundle\Controller;

use AwardWallet\ExtensionWorker\ExtensionResponse;
use AwardWallet\ExtensionWorker\ResponseReceiver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExtensionResponseController
{

    /**
     * @Route("/v2/extension/response", name="extension_response", methods={"POST"})
     */
    public function extensionResponseAction(Request $request, ResponseReceiver $responseReceiver) : Response
    {
        $data = json_decode($request->getContent(), true);
        $responseReceiver->receive(new ExtensionResponse($data['sessionId'], $data['result'] ?? null, $data['requestId']));

        return new JsonResponse("ok");
    }


}