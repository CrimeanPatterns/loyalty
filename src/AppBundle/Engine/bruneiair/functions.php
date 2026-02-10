<?php

//  ProviderID: 1218

require_once __DIR__ . '/../pia/functions.php';

class TAccountCheckerBruneiair extends TAccountCheckerPia
{
    public $code = 'royalbrunei';

    /*
    public function getStatus($tier)
    {
        $this->logger->debug("Tier: {$tier}");

        switch ($tier) {
            case 'BASE':
                $status = 'Base';

                break;

            case 'SLVR':
                $status = 'Silver';

                break;

            case 'GOLD':
                $status = 'Gold';

                break;

            default:
                $status = '';
                $this->sendNotification("{$this->AccountFields['ProviderCode']}, New status was found: {$tier}");
        }

        return $status;
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://book-{$this->code}.crane.aero/ibe/loyalty");

        if (!$this->http->ParseForm(null, '//form[contains(@action, "/ibe/loyalty")]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('isRemember', 'on');

        return true;
    }

    public function Login()
    {
        $this->http->setMaxRedirects(0);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->setMaxRedirects(5);

        $redirect = $this->http->Response['headers']['location'] ?? null;
        $message = $this->http->Response['headers']['x-error-message'] ?? null;
        $this->logger->debug("Redirect -> '{$redirect}'");

        if ($redirect) {
            $this->http->GetURL($redirect);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $message ?? $this->http->FindSingleNode("//p[@id = 'errorModalText']")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Please check your credentials and try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "Your account is locked after multiple attempts. Account will unlock after an hour.") {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }
}
