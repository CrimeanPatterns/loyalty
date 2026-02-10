<?php

class TAccountCheckerCoasthotels extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['X-TC-User-Auth']) || !isset($this->State['token'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.coasthotels.com/guest-portal/sign-in");

        if (!$this->http->FindSingleNode('//div[@id = "gms-form-login"]/@id')
            || !$key = $this->http->FindPreg('/proxy_key: "([^"]+)/')
        ) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue("email", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $this->http->GetURL('https://tc.galaxy.tf/token/oauth2/gms', [
            'Origin'       => 'https://www.coasthotels.com/',
            'Referer'      => 'https://www.coasthotels.com/',
            'X-Galaxy-Key' => $key,
        ]);
        $response = $this->http->JsonLog();

        if (!isset($response->access_token, $response->token_type)) {
            return $this->checkErrors();
        }

        $data = [
            "credentials" => [
                "loginID"  => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
            ],
        ];
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"          => "*/*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Referer"         => "https://www.coasthotels.com/",
            "Authorization"   => "{$response->token_type} {$response->access_token}",
            "Content-Type"    => "text/plain;charset=UTF-8",
            "Origin"          => "https://www.coasthotels.com",
        ];

        foreach ($headers as $header => $value) {
            $this->http->setDefaultHeader($header, $value);
        }
        $this->http->PostURL("https://api.travelclick.com/loyalty/v2/WXC/auth", json_encode($data));
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg('/<p>(Due to essential technical maintenance, our Coast Rewards Membership Portal is currently offline.[^<]+)/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->profile->id, $response->token)) {
            $this->State['X-TC-User-Auth'] = $response->token;
            $this->State['token'] = $response->profile->id;

            return $this->loginSuccessful();
        }

        if (isset($response->errors[0]->code, $response->errors[0]->message)) {
            if ($response->errors[0]->code == "LTY100" && $response->errors[0]->message == "Authorization Failure") {
                throw new CheckException("Wrong credentials", ACCOUNT_INVALID_PASSWORD);
            }

            if ($response->errors[0]->code == "LTY900" && $response->errors[0]->message == "Internal Error") {
                throw new CheckException("We are experiencing a technical issue, please try again later", ACCOUNT_PROVIDER_ERROR);
            }

            if ($response->errors[0]->code == "LTY999" && $response->errors[0]->message == "Bad Request") {
                throw new CheckException("Bad Request", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Coast Rewards Points
        $this->SetBalance($response->loyaltyInfo->level->points->numberOfPoints ?? null);
        // Member Number
        $this->SetProperty("Number", $response->memberInfo->memberNumber ?? null);
        // Name
        $this->SetProperty("Name", $response->memberInfo->name->firstName . " " . $response->memberInfo->name->lastName);
        // Level
        $this->SetProperty("Level", $response->loyaltyInfo->level->levelName ?? null);
        // Nights Accrued
        $this->SetProperty("NightsSpentThisYear", $response->loyaltyInfo->level->numberOfNights ?? null);
        // Nights needed for next level
        $this->SetProperty("NightsNeededForNextLevel", $response->loyaltyInfo->nextLevel->numberOfNights ?? null);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("X-TC-User-Auth", $this->State['X-TC-User-Auth']);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.travelclick.com/loyalty/v2/WXC/account/{$this->State['token']}", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            (isset($response->memberInfo->memberID) && strtolower($response->memberInfo->memberID) == strtolower($this->AccountFields['Login']))
            || (isset($response->memberInfo->memberNumber) && $response->memberInfo->memberNumber == $this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }
}
