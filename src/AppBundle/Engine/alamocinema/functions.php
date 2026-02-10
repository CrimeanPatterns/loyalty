<?php

class TAccountCheckerAlamocinema extends TAccountChecker
{
    private $response = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['userSessionId'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://drafthouse.com/s/mother/v1/loyalty/member?userSessionId={$this->State['userSessionId']}", [], 20);
        $this->response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && !empty($this->response->data->loyaltyMember->cardNumber)) {
            return true;
        }
        $this->response = null;

        return false;
    }

    public function LoadLoginForm()
    {
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('There is not a user that matches this email/password.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://drafthouse.com/victory/sign-in');

        $data = [
            'email'         => $this->AccountFields['Login'],
            'password'      => $this->AccountFields['Pass'],
            'userSessionId' => md5($this->AccountFields['Login']),
        ];
        $this->State['userSessionId'] = $data['userSessionId'];
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=utf-8',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://drafthouse.com/s/mother/v1/auth/login/email-password', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $this->response = $this->http->JsonLog();

        if ($this->loginSuccessful() && isset($this->response->data->loginSuccess) && $this->response->data->loginSuccess == 'true') {
            return true;
        }

        $errorCode = $this->response->error->errorCode->code ?? null;

        if ($errorCode) {
            $this->logger->error("errorCode: {$errorCode}");

            if ($errorCode == 401) {
                throw new CheckException('There is not a user that matches this email/password.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($errorCode == 1) {
                throw new CheckException('Sorry, we could not log you in at this time.', ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - CURRENT VISITS
        $this->SetBalance($this->response->data->loyaltyMember->currentPoints ?? null);
        // LIFETIME VISITS
        $this->SetProperty('LifetimeVisits', $this->response->data->loyaltyMember->lifetimePoints ?? null);
        // Name
        $this->SetProperty('Name', $this->response->data->loyaltyMember->fullName ? beautifulName($this->response->data->loyaltyMember->fullName) : null);
        // VICTORY REWARDS
        $this->SetProperty('Rewards', $this->response->data->loyaltyMember->rewards ? count($this->response->data->loyaltyMember->rewards) : null);
        // VICTORY LEVEL
        $this->SetProperty('Level', $this->response->data->loyaltyMember->currentVictoryLevel ?? null);
        // Visits Until Next Victory Level
        $this->SetProperty('VisitsUntilNextLevel', $this->response->data->loyaltyMember->visitsUntilNextVictoryLevel ?? null);
        // Card Number
        $this->SetProperty('CardNumber', $this->response->data->loyaltyMember->cardNumber ?? null);
        // Rewards
        foreach ($this->response->data->loyaltyMember->rewards as $reward) {
            $this->AddSubAccount([
                "Code"           => "rewardAlamoCinema" . $reward->recognitionId,
                "DisplayName"    => $reward->name,
                "Balance"        => null,
                'ExpirationDate' => strtotime($reward->expiresDateTimeUtc),
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            !isset($this->response->data->loyaltyMember->email)
            || strtolower($this->response->data->loyaltyMember->email) !== strtolower($this->AccountFields['Login'])
        ) {
            $this->logger->error('Incorrect email in json!');

            return false;
        }

        return true;
    }
}
