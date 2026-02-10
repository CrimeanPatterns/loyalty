<?php

class TAccountCheckerCosi extends TAccountChecker
{
    private $headers = [
        'Accept'       => '*/*',
        'Content-Type' => 'application/json',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['UserId'], $this->State['ApiToken'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $this->headers['Authorization'] = 'Bearer ' . $this->State['ApiToken'];
        $this->http->GetURL("https://craver-app.appspot.com/api/users/{$this->State['UserId']}", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->name, $response->id)) {
            return true;
        }
        unset($this->State['UserId'], $this->headers['Authorization'], $this->State['ApiToken']);

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://pickup.getcosi.com/menu');
        $src = $this->http->FindSingleNode("//script[contains(@src,'https://storage.googleapis.com/craver-app/')]/@src");

        if (!$src) {
            return $this->checkErrors();
        }
//        $this->http->GetURL($src); TODO heavy request
//        $apiKey = $this->http->FindPreg('/exports=\{general:\{apiKey:"([\w\-]+)",companyName:/');
        $this->headers['Authorization'] = "Bearer 8a0aa164-e26b-4138-b6ef-3671ee39af7f";
        $data = [
            'email'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL('https://craver-app.appspot.com/api/users/login', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
//        // Maintenance
//        if ($message = $this->http->FindSingleNode("(//img[contains(@alt, 'SITE DOWN FOR MAINTENANCE.')]/@alt)[1]")) {
//            throw new CheckException('', ACCOUNT_PROVIDER_ERROR);
//        }
        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->name, $response->id)) {
            $this->State['UserId'] = $response->id;
            $this->State['ApiToken'] = $this->http->Response['headers']['api_token'];

            return true;
        }

        if ($message = $this->http->FindPreg('/"message":\s*"(Incorrect email or password)"/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Points Balance
        /*{
            points: 32, - balance
            balance: 0,
        }*/
        $this->SetBalance($response->points);
        // Available Rewards
        $this->SetProperty('Name', $response->name);
    }
}
