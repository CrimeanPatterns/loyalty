<?php

class TAccountCheckerFreebees extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->disableOriginHeader();
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
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
        $this->http->unsetDefaultHeader('Authorization');
        $this->http->GetURL("https://www.freebees.nl/");

        if ($this->http->Response['code'] != 200) {
            return false;
        }
        $data = [
            'cardNumber' => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL('https://prod.freebees.nl/api/auth', json_encode($data));

        return true;
    }

    public function Login()
    {
//        $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
//        $this->http->PostForm();
        $response = $this->http->JsonLog();
        $tkl = $response->tkl ?? null;

        if ($tkl) {
            $this->State['Authorization'] = "bearer {$tkl}";

            if ($this->loginSuccessful()) {
                return true;
            }

            return false;
        }
        $message = $response->error ?? null;

        if ($message == 'Invalid password specified') {
            throw new CheckException("Ongeldig wachtwoord", ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid Card Number
        if ($message == 'Invalid Card Number') {
            throw new CheckException("Ongeldig FreeBees-kaartnummer", ACCOUNT_INVALID_PASSWORD);
        }
        // Wrong card number
        if ($message = $this->http->FindPreg("/(Verkeerd kaartnummer)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/^(\{\"code\":99,\"error\":\"Internal error\"\})$/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        //# Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Kaartnummer
        $this->SetProperty("CardNumber", $response->cardNumber ?? null);

        // Balance - totaal aantal FreeBees
        $this->http->GetURL("https://prod.freebees.nl/api/balance");
        $response = $this->http->JsonLog();
        $this->SetBalance($response->balance->Freebees ?? null);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.freebees.nl/website/mybees/website';

        return $arg;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Authorization", $this->State['Authorization']);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://prod.freebees.nl/api/user");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->email)) {
            return true;
        }

        return false;
    }
}
