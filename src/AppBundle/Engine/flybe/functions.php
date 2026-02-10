<?php

// refs #1705

class TAccountCheckerFlybe extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://accounts.flybe.com/o3r-app-server/flybe/login');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://accounts.flybe.com/go/gateway-config.json");
        $response = $this->http->JsonLog();

        if (!isset($response->gatewayClientId) || !isset($response->gatewayClientSecret) || !isset($response->baseUrl)) {
            return $this->checkErrors();
        }
        $data = [
            "client_id"     => $response->gatewayClientId,
            "client_secret" => $response->gatewayClientSecret,
            "grant_type"    => "client_credentials",
        ];
        $this->http->PostURL("{$response->baseUrl}/v1/security/oauth2/token", $data);
        $response = $this->http->JsonLog();

        $headers = [
            "Accept"         => "*/*",
            "Referer"        => "https://accounts.flybe.com/o3r-app-server/flybe/login",
            "Content-Type"   => "application/json",
            "o3r-session-id" => "4130dca7-1072-4159-b569-8ebdbff2a8cf",
            "Authorization"  => "Bearer {$response->access_token}",
            "Origin"         => "https://accounts.flybe.com",
        ];

        foreach ($headers as $header => $value) {
            $this->http->setDefaultHeader($header, $value);
        }
        $data = [
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL("https://api-ap.flybe.com/api/v1/security/access-tokens?username=" . urlencode($this->AccountFields['Login']), json_encode($data));
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['NoCookieURL'] = true;

        return $arg;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        $accessTokenId = $response->data->accessTokenId ?? null;

        if ($accessTokenId) {
            $this->http->setDefaultHeader("X-Bearer-Token", $accessTokenId);

            return true;
        }

        $errorCode = $response->errors[0]->code ?? null;

        switch ($errorCode) {
            case '26322':
                throw new CheckException("Incorrect email or password, please try again (CLP/401/26322)", ACCOUNT_INVALID_PASSWORD);

                break;

            default:
                $this->logger->error("Unknown error code: {$errorCode}");

                break;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api-ap.flybe.com/v1/cem/profiles/digital");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->errors[0]->title) && $response->errors[0]->title == 'This service is not active for this airline') {
            throw new CheckException("Sorry, you are not logged in. If the problem persists please contact support for more information. (CEM/403/403)", ACCOUNT_PROVIDER_ERROR);
        }

        // Name
        if (isset($response->data->individual->identity->names[0]->universal->firstName, $response->data->individual->identity->names[0]->universal->lastName)) {
            $this->SetProperty("Name", beautifulName($response->data->individual->identity->names[0]->universal->firstName . " " . $response->data->individual->identity->names[0]->universal->lastName));
        }
        // Avios number
        $memberships = $response->data->individual->memberships ?? [];

        foreach ($memberships as $membership) {
            if ($membership->membershipType == 'INDIVIDUAL') {
                $this->SetProperty("Number", $membership->memberId ?? null);

                break;
            }
        }
        // Balance - Points available
//        $this->SetBalance($this->http->FindSingleNode("//thead[tr[th[contains(text(), 'Points available')]]]/following-sibling::tbody/tr[1]/td[3]"));

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }
}
