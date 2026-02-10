<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirmilesnetherlands extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Language" => "en-US,en;q=0.5",
        "Accept-Encoding" => "gzip, deflate, br",
        "content-type"    => "application/json",
        "Origin"          => "https://www.airmiles.nl",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
    }

//    public function IsLoggedIn()
//    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL("https://www.airmiles.nl/#openLogin", [], 20);
//        $this->http->RetryCount = 2;
//
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//
//        return false;
//    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("https://www.airmiles.nl/#openLogin", [], 20);

        if ($this->http->Response['code'] != 200) {
//        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }

        $captcha = $this->parseCaptcha("6LdP0kkbAAAAAOEkWijeClFRjOlhqLULSFwgydpm");

        // x-xsrf-token
        if ($captcha === false) {
            return false;
        }

        $data = [
            "username"       => $this->AccountFields['Login'],
            "password"       => $this->AccountFields['Pass'],
            "clientType"     => 0,
            "reCaptchaToken" => $captcha,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.airmiles.nl/api/v1/token/login/password", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "504 Gateway Time-out")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        // Access is allowed
        if (isset($response->emailAddress)) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

//        The request could not be processed.
        // https://www.airmiles.nl/assets/i18n/nl.json
        $code = $response->code ?? null;
        $message = $response->message ?? null;

        if ($code && $message) {
            // Onjuiste gebruikersnaam of wachtwoord.
            if ($code == '403_2' && $message == 'Invalid login credentials.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Onjuiste gebruikersnaam of wachtwoord.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($code == '403_3' && $message == 'Invalid account.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Ongeldig account. Neem contact op met onze klantenservice.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($code == '403_6' && $message == 'Locked account.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Je inloggegevens zijn verouderd. Reset je wachtwoord via de optie: Ik kom mijn account niet meer in.", ACCOUNT_LOCKOUT);
            }

            if ($code == '403_1' && $message == 'Forbidden.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0);
            }

            if ($code == '403_9' && $message == 'Insufficient ReCaptcha score.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0);
            }

            if ($code == '400_1' && $message == "ReCaptcha token cannot be successfully verified. Reason(s): invalid-keys.") {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($code && $message)

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        /*
        $csrf = $this->http->getCookieByName("XSRF-TOKEN", ".airmiles.nl", "/", true);
        $this->http->PostURL("https://api.airmiles.nl/api/v1/token/xsrf", "{}", $this->headers);

        if (!$csrf) {
            $this->logger->notice("csrf not found");

            return;
        }
        */

        // Balance - Air Miles
        if (
            !$this->SetBalance($response->balance ?? null)
            && $response->balance === null// AccountID: 2426599
        ) {
            $this->SetBalance(0);
        }
        // Kaartnummer
        $this->SetProperty("Number", $response->cardNumber ?? null);
        // Name
        $this->SetProperty("Name", ($response->firstName ?? null) . " " . ($response->lastName ?? null));

        $headers = [
            //            "X-XSRF-TOKEN" => $csrf,
        ];
        $this->http->GetURL("https://api.airmiles.nl/api/v1/transactions/overview?numberOfRecentTransactions=3", $this->headers + $headers);
        $response = $this->http->JsonLog(null, 0);
        // Afgelopen maand: Gespaard - Earned last month
        $this->SetProperty("Earned", $response->pointsEarned ?? null);
        // Afgelopen maand: Ingewisseld - Spent last month
        $this->SetProperty("Spent", $response->pointsSpent ?? null);

        /*
        // Expiration date
        $data = sprintf('{"action":"retrieve_by_xpath","params":{"xpath":"//MyAirMiles.AirMiles[MyAirMiles.AirMiles_Member=\"%s\"]","schema":{"id":"fae9241c-0bf3-4663-8e4b-f1853375fb06","offset":0,"sort":[["ExpirationDatePerMonth","asc"]],"amount":12},"count":true,"aggregates":false},"context":[]}', $guid);
        $this->http->PostURL("https://www.airmiles.nl/mendix/xas/", $data, $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->mxobjects)) {
            return;
        }

        // table 'Geldig tot'
        $expNodes = count($response->mxobjects);
        $this->http->Log("Total {$expNodes} expiration dates were found");

        foreach ($response->mxobjects as $row) {
            $date = preg_replace("/.{3}$/", "", $row->attributes->ExpirationDatePerMonth->value);
            $points = $row->attributes->NumberOfAirMiles->value;

            if (!isset($exp) || $exp > $date) {
                $exp = $date;
                // Expiring balance
                $this->SetProperty("ExpiringBalance", $points);
                $this->SetExpirationDate($exp);
            }// if (!isset($exp) || $exp > $date)
        }// foreach ($response->mxobjects as $row)
        */
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
//        $key = $this->http->FindSingleNode("//form[@id = 'login-form']//div[contains(@class, 'g-recaptcha')]/@data-sitekey");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.9,
            "pageAction" => "CreatePasswordLoginToken",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "v3",
            "action"    => "CreatePasswordLoginToken",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
