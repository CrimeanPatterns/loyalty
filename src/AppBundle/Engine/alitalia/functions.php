<?php

// refs #1704
use AwardWallet\Common\Parsing\Html;

class TAccountCheckerAlitalia extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);

        // remove leading zeros
        if (strlen($this->AccountFields['Login']) == 10 && $this->http->FindPreg("/^000/", false, $this->AccountFields['Login'])) {
            $this->logger->notice("remove leading zeros");
            $this->AccountFields['Login'] = preg_replace("/^000/", "", $this->AccountFields['Login']);
        }
    }

    public function IsLoggedIn()
    {
        // refs #17463
        return false;

        $this->http->RetryCount = 0;
        $this->http->GetUrl("https://www.alitalia.com/en_us/personal-area/your-profile.html", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        throw new CheckException("ALITALIA HAS CEASED ITS OPERATION", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.alitalia.com/en_us/personal-area/your-profile.html");
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm("form-perform-login")) {
            return $this->checkErrors();
        }
        $response = $this->checkCredentials();

        if ($response === false) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("j_username", "MM:" . $response['mmCode']);
        $this->http->SetInputValue("j_password", $response['mmPin']);
        $this->http->SetInputValue(":cq_csrf_token", "undefined");
        $this->http->SetInputValue("mm_remember_me", "1");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg['NoCookieURL'] = true;
//        $arg['SuccessURL'] = '	http://www.alitalia.com/US_EN/';
        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are updating our IT system
        if ($this->http->FindPreg("/<p class=\"intro\">We are updating <br>our IT system<\/p>/")) {
            throw new CheckException("We are updating our IT system", ACCOUNT_PROVIDER_ERROR);
        }

        // provider error
        if ($this->http->FindPreg("/File not found\.\"$/")) {
            $this->http->GetURL("https://www.alitalia.com");

            if ($this->http->FindPreg("/File not found\.\"$/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->http->FindPreg("/File not found\.\"$/"))
        // Alitalia.com is temporarily unavailable.
        if ($this->http->FindPreg("/Alitalia.com is temporarily unavailable\./")) {
            throw new CheckException("Alitalia.com is temporarily unavailable. We apologize for the inconvenient.", ACCOUNT_PROVIDER_ERROR);
        }
        // Bad Gateway
        if ($this->http->FindSingleNode("
                //h1[contains(text(), 'Bad Gateway')]
                | //p[contains(text(), 'Cannot serve request to /content/alitalia/alitalia-us/en/special-pages/millemiglia-login.html on this server')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkCredentials()
    {
        $this->logger->notice(__METHOD__);
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $headers = [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $data = [
            "SSWRedirectLink"      => "https://award.alitalia.com/SSW2010/AZBZ/webqtrip.html",
            "code"                 => $this->AccountFields['Login'],
            "millemiglia"          => $this->AccountFields['Login'],
            "pin"                  => $this->AccountFields['Pass'],
            "rememberme"           => "on",
            "recaptchaResponse"    => "",
            "g-recaptcha-response" => "",
            "_isAjax"              => "true",
            "_action"              => "validate",
        ];

        // AccountID: 4808385 - login == username
        if (!is_numeric($this->AccountFields['Login'])) {
            unset($data['millemiglia']);
        }

        if ($this->attempt > 0) {
            $captcha = $this->parseReCaptcha();

            if ($captcha !== false) {
                $data['recaptchaResponse'] = $captcha;
            }
        }
        $http2->PostURL("https://www.alitalia.com/en_us/home-page/.millemiglialoginvalidation1.json", $data, $headers);
        $response = $http2->JsonLog(null, 3, true);

        // Password does not conform with the stated criteria. Please enter again
        $fieldsPin = ArrayVal(ArrayVal($response, 'fields'), 'pin');

        if (
            $fieldsPin == "Field not in the correct format"
            && in_array($this->AccountFields['Login'], ["2519206", "4518519"])
        ) {
            throw new CheckException("Password does not conform with the stated criteria. Please enter again", ACCOUNT_INVALID_PASSWORD);
        }
        $checkCredentialsUrl = "https://www.alitalia.com/en_us/home-page/.checkmillemiglialogin.json";

        $result = ArrayVal($response, 'result', null);

        if (!$result || $result == false) {
            $captcha = $this->parseReCaptcha();

            if ($captcha !== false) {
                $data['recaptchaResponse'] = $captcha;
            }
            $http2->PostURL("https://www.alitalia.com/en_us/home-page/.millemiglialoginsavalidation.json", $data, $headers);
            $response = $http2->JsonLog(null, 3);

            $result = $response->result ?? null;

            if (!$result || $result == false) {
                return false;
            }

            $checkCredentialsUrl = 'https://www.alitalia.com/en_us/home-page/.checkCredenzialiMillemiglia.json';
        }// if (!$result || $result == false)

        $http2->PostURL($checkCredentialsUrl, [
            "_isAjax" => "true",
            "mmCode"  => $this->AccountFields['Login'],
            "mmPin"   => $this->AccountFields['Pass'],
        ], $headers);
        $response = $http2->JsonLog(null, 3, true);

        if (ArrayVal($response, 'errorCode') == 'ErrorLogin') {
            throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
        }
        // MAXIMUM NUMBER OF FAILED ATTEMPTS EXCEEDED
        if (ArrayVal($response, 'errorCode') == 'ErrorLoginCaptcha' && ArrayVal($response, 'errorDescription') == 'MAXIMUM NUMBER OF FAILED ATTEMPTS EXCEEDED; ') {
            throw new CheckException(ArrayVal($response, 'errorDescription'), ACCOUNT_INVALID_PASSWORD);
        }

        if (!isset($response['mmPin'])) {
            $response['mmPin'] = $this->AccountFields['Pass'];
        }

        return $response;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            // prevent 404
            if ($this->http->FindSingleNode('//title[contains(text(), "404 Not Found")]')) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // retries
        if (strstr($this->http->currentUrl(), 'j_reason=invalid_login&j_reason_code=invalid_login')) {
            throw new CheckRetryNeededException(2, 10, "Invalid credentials", ACCOUNT_INVALID_PASSWORD);
        }

        $this->sessionTimedOutWorkaround();

        return $this->checkErrors();
    }

    public function regExpMethod($propertyCode)
    {
        return $this->http->FindPreg('/' . $propertyCode . '\":\s*\"([^\"]+)/');
    }

    public function Parse()
    {
        if (!$this->http->FindPreg('#/personal\-area/your\-profile\.html#', false, $this->http->currentUrl())) {
            $this->http->GetURL("https://www.alitalia.com/en_us/personal-area/your-profile.html");
        }

        // provider bug fix
        if (
            !$this->http->FindSingleNode("//div[contains(text(), 'Last name')]/following-sibling::div[1]")
            && $this->http->FindSingleNode('//h1[contains(normalize-space(text()), "THE PAGE REQUESTED HAS NOT BEEN FOUND")]')
        ) {
            throw new CheckRetryNeededException(2, 3);
        }

        $firstName = $this->http->FindSingleNode("//div[contains(text(), 'First name')]/following-sibling::div[1]");
        $lastName = $this->http->FindSingleNode("//div[contains(text(), 'Last name')]/following-sibling::div[1]");
        $nameFromProfile = beautifulName(Html::cleanXMLValue("$firstName $lastName"));
        $this->logger->debug("Name from Profile: {$nameFromProfile}");

        $this->sessionTimedOutWorkaround();

        // provider has a bug: by url with old timestamp you can get the data of another user
        $attempt = 0;

        do {
            $this->http->GetURL("https://www.alitalia.com/etc/clientcontext/alitalia/content/jcr:content/stores.init.js?path=%2Fcontent%2Falitalia%2Falitalia-us%2Fen%2Fpersonal-area%2Fyour-profile&cq_ck=" . date("UB"));
            $nameFromJSON = beautifulName($this->regExpMethod('customerName') . ' ' . $this->regExpMethod('customerSurname'));
            $authorizableId = $this->regExpMethod('authorizableId');
            $this->logger->debug("Name from JSON: {$nameFromJSON}");
            $this->logger->debug("authorizableId from JSON: {$authorizableId}");

            // AccountID: 1226610, 2525101, 7601004, 2732400
            if (strstr($nameFromJSON, '?')) {
                $symbols = [
                    'í',
                    'é',
                    'ò',
                    'ü',
                    '¿',
                    'î',
                    'á',
                ];

                foreach ($symbols as $symbol) {
                    if (strstr($nameFromProfile, $symbol)) {
                        $this->logger->notice("fixed Name");
                        $nameFromJSON = str_replace('?', $symbol, $nameFromJSON);
                        $this->logger->debug("Name from JSON: {$nameFromJSON}");

                        break;
                    }
                }
            }// if (strstr($nameFromJSON, '?'))

            $wrongProfile = $nameFromProfile != $nameFromJSON;

            if ($wrongProfile) {
                $sleep = rand(1, 10);
                sleep($sleep);
                $this->logger->debug("Delay: {$sleep}");
            }// if ($wrongProfile)
            $attempt++;
        } while ($wrongProfile && $attempt < 5 && (!empty($nameFromProfile) || $authorizableId != 'MM:' . $this->AccountFields['Login']));

        if (
            ($wrongProfile && !empty($nameFromProfile))
            || (empty($nameFromProfile) && $authorizableId != 'MM:' . $this->AccountFields['Login'])
        ) {
            $this->logger->error("Something went wrong on the provider website");

            // refs #17463  https://redmine.awardwallet.com/issues/17463#note-3
            /*
             * todo: this is not needed because 404 is temporarily provider error
            if ($this->http->Response['code'] == 404) {
                $this->http->GetURL("https://www.alitalia.com/en_us/personal-area/your-profile.html");

                $nameFromProfile = $this->http->FindSingleNode('//div[@class = "millemiglia__card"]//span[@class = "name"]');
                if ($nameFromProfile != $nameFromJSON) {
                    return false;
                }

                // Name
                $this->SetProperty("Name", $nameFromProfile);
                // Account #
                $this->SetProperty("Number", $this->http->FindSingleNode('//div[@class = "millemiglia__card"]//span[@class = "number"]'));
                // Balance - Miles balance
                $this->SetBalance($this->http->FindSingleNode('//div[@class = "millemiglia__brief"]//div[contains(text(), "Miles balance")]/preceding-sibling::div'));
                // Status expiration
                $this->SetProperty("StatusExpiration", $this->http->FindSingleNode('//div[@class = "millemiglia__brief"]//div[contains(text(), "Status expiration")]/preceding-sibling::div'));
                // Miles accumulated
                $this->SetProperty("EarnedMiles", $this->http->FindSingleNode('//div[@class = "millemiglia__brief"]//div[contains(text(), "Miles accumulated")]/preceding-sibling::div'));
                // Qualifying miles
                $this->SetProperty("QualifyingMiles", $this->http->FindSingleNode('//div[@class = "millemiglia__brief"]//div[contains(text(), "Qualifying miles")]/preceding-sibling::div'));
                // Miles spent
                $this->SetProperty("MilesSpent", $this->http->FindSingleNode('//div[@class = "millemiglia__brief"]//div[contains(text(), "Miles spent")]/preceding-sibling::div'));
                // Qualifying flights
                $this->SetProperty("TotalQualifyingFlights", $this->http->FindSingleNode('//div[@class = "millemiglia__brief"]//div[contains(text(), "Qualifying flights")]/preceding-sibling::div'));
            }
            else
            */

            return;
        }// if ($wrongProfile)

        // Name
        $this->SetProperty("Name", $nameFromJSON);
        // Account #
        $this->SetProperty("Number", $this->regExpMethod('customerNumber'));
        // Balance - Miles balance
        $this->SetBalance($this->regExpMethod('milleMigliaRemainingMiles'));
        // Status expiration
        $this->SetProperty("StatusExpiration", preg_replace('/T.+/', '', $this->regExpMethod('milleMigliaExpirationDate')));
        // Miles accumulated
        $this->SetProperty("EarnedMiles", $this->regExpMethod('milleMigliaTotalMiles'));
        // Qualifying miles
        $this->SetProperty("QualifyingMiles", $this->regExpMethod('milleMigliaTotalQualifiedMiles'));
        // Miles spent
        $this->SetProperty("MilesSpent", $this->regExpMethod('milleMigliaTotalSpentMiles'));
        // Qualifying flights
        $this->SetProperty("TotalQualifyingFlights", $this->regExpMethod('milleMigliaQualifiedFlight'));

        // Elite Status
        $statusClass = $this->regExpMethod('milleMigliaTierCode') ?? $this->http->FindSingleNode('//div[contains(@class, "millemiglia__card tier")]/@class', null, true, "/(tier\d+)/");

        switch ($statusClass) {
            case 'Basic':
                $status = 'MilleMiglia Club';

                break;

            case 'Ulisse':
            case 'tier1':
                $status = 'Ulisse Club';

                break;

            case 'FrecciaAlata':
                $status = 'Freccia Alata Club';

                break;

            case 'Plus':
                $status = 'Freccia Alata Plus Club';

                break;

            default:
                if ($this->ErrorCode == ACCOUNT_CHECKED) {
                    $this->sendNotification("Unknown Status -> {$statusClass}");
                }// if ($this->ErrorCode == ACCOUNT_CHECKED)

                break;
        }

        if (isset($status)) {
            $this->SetProperty('ClubName', $status);
        }

        // Last Activity
        $this->http->GetURL("https://www.alitalia.com/en_us/personal-area/account-statement.estratto-conto-partial.html");
        $lastActivityDate = $this->http->FindNodes("(//div[@class = 'millemiglia__estrattoRow']/div[contains(@class, 'date')])[1]/span");

        if (!empty($lastActivityDate)) {
            $lastActivity = implode("/", $lastActivityDate);
            $lastActivityDate = $this->ModifyDateFormat($lastActivity);

            if ($exp = strtotime($lastActivityDate)) {
                $this->SetProperty("LastActivity", $lastActivity);
                $this->SetExpirationDate(strtotime("+2 year", $exp));
            }// if ($exp = strtotime($lastActivityDate))
        }// if (!empty($lastActivityDate))
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/invisibleRecaptchaSiteKey = "([\w\-]+)"/');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
//        if ($this->http->FindSingleNode("//div[contains(text(), 'Status expiration')]/preceding-sibling::div[1]")) {
        if ($this->http->getCookieByName("login-token")) {
            return true;
        }

        return false;
    }

    // provider bug fix
    private function sessionTimedOutWorkaround()
    {
        $this->logger->notice(__METHOD__);

        if (
            strstr($this->http->currentUrl(), 'https://www.alitalia.com/en_us/special-pages/millemiglia-login.html?resource=%2Fcontent%2Falitalia%2Falitalia-us%2Fen%2Fpersonal-area%2Faccount-statement.html&$$login$$=%24%24login%24%24&j_reason=session_timed_out&j_reason_code=invalid_login')
            || strstr($this->http->currentUrl(), 'https://www.alitalia.com/en_us/special-pages/millemiglia-login.html?resource=%2Fcontent%2Falitalia%2Falitalia-us%2Fen%2Fpersonal-area%2Fyour-profile.html&$$login$$=%24%24login%24%24&j_reason=session_timed_out&j_reason_code=invalid_login')
            || (
                $this->http->Response['code'] == 404
                && strstr($this->http->currentUrl(), 'https://www.alitalia.com/libs/granite/core/content/login.html?resource=%25252Fcontent%25252Falitalia%25252Falitalia-us%25252Fen%25252Fpersonal-area%25252Fyour-profile.html&$$login$$=%2524%2524login%2524%2524&j_reason=invalid_login&j_reason_code=invalid_login')
            )
        ) {
            throw new CheckRetryNeededException(2, 1);
        }
    }
}
