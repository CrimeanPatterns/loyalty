<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDanhotel extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://www.danhotels.com/eDan/dashboard";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.danhotels.com/";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        $access_token = $this->State['Authorization'];

        if ($this->loginSuccessful($access_token)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->unsetDefaultHeader("Authorization");
        $this->http->GetURL("https://www.danhotels.com/eDan/Login");

        if ($script = $this->http->FindSingleNode('//script[contains(@src, "react_components.bundle.js")]/@src')) {
            $this->http->NormalizeURL($script);
            $this->http->GetURL($script);
        }

        $siteKey = $this->http->FindPreg("/,sitekey:\"([^\"]+)/");

        if ($this->http->Response['code'] != 200 || !$siteKey) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha($siteKey);

        if (!$captcha) {
            return false;
        }

        $data = '{"query":"mutation{\n      login(\n        email:\"' . $this->AccountFields['Login'] . '\",\n        host: \"https://www.danhotels.com\",\n        password:\"' . $this->AccountFields['Pass'] . '\"\n      ){\n        error\n        Token\n        GuestId\n      }\n    }"}';

        $header = [
            "Accept"               => "application/json",
            "Content-Type"         => "application/json",
            "g-recaptcha-response" => $captcha,
            "Referer"              => self::REWARDS_PAGE_URL,
            "Origin"               => "https://www.danhotels.com",
        ];
        $this->http->PostURL("https://api.danhotels.com/club/graphql", $data, $header);

//        if (!$this->http->ParseForm("edanLoginFrm"))
//            return $this->checkErrors();
//        $this->http->SetInputValue("email", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Dan website is down for maintenance and will be back soon")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 6);

        if (!empty($response->data->login[0]->Token)) {
            $this->captchaReporting($this->recognizer);
            $authorization = "Bearer {$response->data->login[0]->Token}";
            $this->State['Authorization'] = $authorization;

            if (
                isset($response->data->login[0]->privacyConfirmed)
                && $response->data->login[0]->privacyConfirmed === false
            ) {
                $this->throwAcceptTermsMessageException();
            }

            return $this->loginSuccessful($authorization);
        }// if (!empty($response->data->login[0]->Token))
        // Invalid credentials
        if (!empty($response->data->login[0]->error)) {
            $error = $response->data->login[0]->error;
            $this->logger->error($error);
            // Incorrect password
            if ($error == 'incorrectPassword') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Incorrect password", ACCOUNT_INVALID_PASSWORD);
            }
            // Email doesn't exist
            if ($error == 'emailNotFound') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Email doesn't exist", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if (!empty($response->data->login[0]->error))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Member #
        if (isset($response->data->getClubMemberDetails[0]->ClubMemberNumber)) {
            $this->SetProperty("MembershipNumber", $response->data->getClubMemberDetails[0]->ClubMemberNumber);
        }
        // Status
        if (isset($response->data->getClubMemberDetails[0]->ClubCode)) {
            $this->SetProperty("Status", $response->data->getClubMemberDetails[0]->ClubCode);
        }
        // Name
        if (isset($response->data->getClubMemberDetails[0]->MemberDetails[0]->FirstNameHebrew, $response->data->getClubMemberDetails[0]->MemberDetails[0]->LastNameHebrew)) {
            $this->SetProperty("Name", beautifulName($response->data->getClubMemberDetails[0]->MemberDetails[0]->FirstNameHebrew . " " . $response->data->getClubMemberDetails[0]->MemberDetails[0]->LastNameHebrew));
        }

        $data = '{"query":"query{\n      getClubMemberPoints {\n        error\n        points {\n         GainedPoints\n          UsedPoints\n          ExpiredPoints\n          Balance\n          PointsExpiredAt\n     PointsIssueDate\n          PointSource\n        }\n      }\n    }"}';
        $this->http->PostURL("https://api.danhotels.com/club/graphql", $data);
        $response = $this->http->JsonLog(null, 5);

        if (!isset($response->data->getClubMemberPoints[0]->points)) {
            return;
        }

        foreach ($response->data->getClubMemberPoints[0]->points as $point) {
            if ($point->Balance > 0 && (!isset($exp) || $exp >= strtotime($point->PointsExpiredAt))) {
                if (isset($exp, $expiringBalance) && $exp == strtotime($point->PointsExpiredAt)) {
                    $expiringBalance += $point->Balance;
                } else {
                    $expiringBalance = $point->Balance;
                }
                $exp = strtotime($point->PointsExpiredAt);
                // Expiration date
                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $expiringBalance);
            }// if ($point->Balance > 0 && !isset($exp) || strtotime($point->PointsExpiredAt))
        }// foreach ($response->data->getClubMemberPoints[0]->points as $point)

        $data = '{"query":"query{\n        getClubMemberPoints(clubStatus:\"new\") {\n            error\n            points {\n              GainedPoints\n              UsedPoints\n              ExpiredPoints\n              Balance\n              PointsExpiredAt\n              PointsIssueDate\n              PointSource\n            }\n        }\n      }"}';
        $this->http->PostURL("https://api.danhotels.com/club/graphql", $data);
        $response = $this->http->JsonLog(null, 5);

        if (!isset($response->data->getClubMemberPoints[0]->points)) {
            return;
        }
        $balance = 0;

        foreach ($response->data->getClubMemberPoints[0]->points as $point) {
            $balance += $point->Balance;
        }
        // Balance - Points Status
        $this->SetBalance($balance);
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => self::REWARDS_PAGE_URL, //$this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($authorization)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Accept", "application/json");
        $this->http->setDefaultHeader("Content-Type", "application/json");
        $this->http->setDefaultHeader("Authorization", $authorization);
        $data = '{"query":"query{\n      getClubMemberDetails(\n        language:\"eng\") {\n        error\n        GuestId\n        ClubCode\n        ClubMemberNumber\n        MemberDetails {\n          FirstNameHebrew\n          LastNameHebrew\n          FirstNameEnglish\n          LastNameEnglish\n          Birthday\n        }\n        ContactInformation {\n          EMail\n          City\n          Country\n          CountryName\n        }\n      }\n    }"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.danhotels.com/club/graphql", $data);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 5);
        $email = $response->data->getClubMemberDetails[0]->ContactInformation[0]->EMail ?? null;

        if (strtolower($email) === strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }
}
