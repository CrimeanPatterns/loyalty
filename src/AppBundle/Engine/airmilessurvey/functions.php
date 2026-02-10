<?php

class TAccountCheckerAirmilessurvey extends TAccountChecker
{
    /* parser like as airmilessurvey, perspectives, valuedopinions, opinionmiles */

    private const REWARDS_PAGE_URL = 'https://flare.rewardingyouropinions.ca/api/1/respondent?_cache=';

    private $headers = [
        'Accept'        => 'application/json; charset=utf-8',
        'Content-Type'  => 'text/plain',
        'panelDomainId' => '432',
        'Origin'        => 'https://www.rewardingyouropinions.ca',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL . date('UB'), $this->headers, 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.rewardingyouropinions.ca/login");

        $panelId = $this->http->FindPreg('/panelId:\s*([^,]+)/ims');
        $panelDomainId = $this->http->FindPreg('/panelDomainId:\s*([^,]+)/ims');
        $brandId = $this->http->FindPreg("/brandId:\s*(\d+),/");
        $passwordClientId = $this->http->FindPreg("/passwordClientId:\s*\"([^\"]+)/");

        if ($this->http->Response['code'] !== 200 || !$panelDomainId || !$panelId || !$brandId || !$passwordClientId) {
            return false;
        }

        $data = [
            "AuthFlow"       => "USER_PASSWORD_AUTH",
            "ClientId"       => $passwordClientId,
            "AuthParameters" => [
                "USERNAME" => $this->AccountFields['Login'],
                "PASSWORD" => $this->AccountFields['Pass'],
            ],
            "ClientMetadata" => [
                "brand_id" => $brandId,
                "panel_id" => $panelId,
            ],
        ];
        $headers = [
            "Accept"                => "*/*",
            "Accept-Language"       => "en-US,en;q=0.5",
            "Accept-Encoding"       => "gzip, deflate, br",
            "Referer"               => "https://www.rewardingyouropinions.ca/",
            "amz-sdk-request"       => "attempt=1; max=3",
            "content-type"          => "application/x-amz-json-1.1",
            "x-amz-target"          => "AWSCognitoIdentityProviderService.InitiateAuth",
            "x-amz-user-agent"      => "aws-sdk-js/3.388.0 ua/2.0 os/macOS#10.15 lang/js md/browser#Firefox_116.0 api/cognito-identity-provider#3.388.0",
            "Origin"                => "https://www.rewardingyouropinions.ca",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://cognito-idp.us-east-1.amazonaws.com/", json_encode($data), $headers);
        $this->http->RetryCount = 1;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Internal Server Error
        if ($this->http->FindSingleNode('
                //title[contains(text(), "502 Bad Gateway")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        $this->http->GetURL("https://www.rewardingyouropinions.ca");

        if ($message = $this->http->FindPreg("/Air Miles Opinions is temporarily unavailable due to maintenance. Please visit us later\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, true);
        $jsResponse = ArrayVal($response, 'AuthenticationResult');
        $IdToken = ArrayVal($jsResponse, 'IdToken');
        $str = base64_decode(explode('.', $IdToken)[1] ?? null);
        $this->logger->debug($str);
        $sessionId = $this->http->FindPreg('/"corona_session":"(.+?)"/', false, $str);

        if ($sessionId) {
            $this->http->setCookie("corona_session", $sessionId, ".rewardingyouropinions.ca");
            $this->http->GetURL(self::REWARDS_PAGE_URL . date('UB'), $this->headers, 20);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = ArrayVal($response, 'message');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Password reset required for user due to security reasons'
                || $message == 'Password reset required for the user'
            ) {
                throw new CheckException('Air Miles (Rewarding Your Opinions) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'Incorrect username or password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        if (isset($response->response->firstName, $response->response->lastName)) {
            $this->SetProperty("Name", beautifulName($response->response->firstName . " " . $response->response->lastName));
        }

        // Balance - total AIR MILES Reward Miles collected to date!
        $this->http->GetURL("https://flare.rewardingyouropinions.ca/api/1/respondent/balance?_cache=" . time() . date("B"));
        $response = $this->http->JsonLog();

        if (isset($response->response->credits)) {
            $this->SetBalance($response->response->credits);
        }

        $this->http->GetURL("https://www.rewardingyouropinions.ca/auth/account");
        // AIR MILES Collector Number
        $this->SetProperty("AccountNumber", $this->http->FindPreg("/Invalid Account number\",\"rule\":\"Not Valid\",\"partner_assigned_id_already_exists\":\"\"},\"defaultValue\":\"([^\"]+)/"));

        $this->http->GetURL("https://flare.rewardingyouropinions.ca/api/1/badge/respondent?_cache=" . time() . date("B"));
        $response = $this->http->JsonLog();

        if (isset($response->response)) {
            foreach ($response->response as $row) {
                if (!isset($row->parentId, $row->priority) && isset($row->granted, $row->name) && $row->granted
                    && (!isset($priority) || $row->priority < $priority)) {
                    $priority = $row->priority;
                    // Level
                    $this->SetProperty("Level", $row->name);
                }
            }// foreach ($response->response as $row)

            if (!isset($this->Properties['Level'])) {
                $this->SetProperty("Level", "Bronze");
            }
        }// if (isset($response->response))
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if ($response->response->emailAddress ?? null) {
            return true;
        }

        return false;
    }
}
