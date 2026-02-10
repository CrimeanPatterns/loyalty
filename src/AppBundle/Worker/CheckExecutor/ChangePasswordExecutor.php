<?php

namespace AppBundle\Worker\CheckExecutor;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\ChangePassword;
use AppBundle\Model\Resources\ChangePasswordRequest;
use AppBundle\Model\Resources\ChangePasswordResponse;
use AppBundle\Model\Resources\CheckAccountRequest;

class ChangePasswordExecutor extends CheckAccountExecutor implements ExecutorInterface
{

    protected $RepoKey = ChangePassword::METHOD_KEY;

    /**
     * @param \TAccountChecker $checker
     * @param ChangePasswordRequest $request
     * @param ChangePasswordResponse $response
     * @param integer $apiVersion
     */
    protected function prepareResponse(\TAccountChecker $checker, $request, &$response, $apiVersion, string $partner)
    {
        $browserState = $checker->GetState();
        $response->setState($checker->ErrorCode)
                 ->setMessage($checker->ErrorCode == ACCOUNT_CHECKED ? '' : $checker->ErrorMessage)
                 ->setCheckdate(new \DateTime())
                 ->setBrowserstate(isset($browserState) ? $this->encodeBrowserState($browserState, $request) : null)
                 ->addDebuginfo($checker->DebugInfo)
                 ->setErrorreason($checker->ErrorReason)

                 ->setUserdata($request->getUserdata());
    }

    protected function buildChecker($request, BaseDocument $row): \TAccountChecker
    {
        // skip parent:: , because it has links to CheckAccountRequest
        $checker = BaseExecutor::buildChecker($request, $row);

        $checker->onLoggedIn = function() use($checker, $request) {
            $checker->ChangePassword($request->getNewPassword());
        };

        return $checker;
    }

    public function getMethodKey(): string
    {
        return ChangePassword::METHOD_KEY;
    }

    protected function getRequestClass(int $apiVersion): string
    {
        return ChangePasswordRequest::class;
    }

    protected function getResponseClass(int $apiVersion): string
    {
        return ChangePasswordResponse::class;
    }
}