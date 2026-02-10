<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCopaair extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "X-Requested-With" => "XMLHttpRequest",
        "Accept"           => "application/json, text/plain, */*",
        "Content-Type"     => "application/json",
        "currentLang"      => "en",
    ];

//    public static function GetAccountChecker($accountInfo)
//    {
//        require_once __DIR__ . "/TAccountCheckerCopaairSelenium.php";
//
//        return new TAccountCheckerCopaairSelenium();
//    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setUserAgent(HttpBrowser::PROXY_USER_AGENT);
        $this->setProxyBrightData();
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.copaair.com/api/auth/login?lng=en&useComarch=true', ['Referer' => null]);

        if (!$this->http->ParseForm(null, "//form[//input[@name = 'username']]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('js-available', "true");
        $this->http->SetInputValue('webauthn-available', "true");
        $this->http->SetInputValue('is-brave', "false");
        $this->http->SetInputValue('webauthn-platform-available', "false");
        $this->http->SetInputValue('action', "default");

        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('captcha', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//*[self::span or self::p][contains(text(), "Estamos modernizando nuestra plataforma de ConnectMiles")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm(null, "//form[contains(@class, '_form-login-password')]")) {
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('action', "default");

            $captcha = $this->parseReCaptcha();

            if ($captcha !== false) {
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
                $this->http->SetInputValue('captcha', $captcha);
            }

            if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
                return $this->checkErrors();
            }// if (!$this->http->PostForm() && $this->http->Response['code'] != 400)
        }// if ($this->http->ParseForm(null, "//form[contains(@class, '_form-login-password')]"))

        if ($this->http->ParseForm(null, "//form[contains(@class, '_form-detect-browser-capabilities')]")) {
            $this->http->SetInputValue('js-available', "true");
            $this->http->SetInputValue('webauthn-available', "true");
            $this->http->SetInputValue('is-brave', "false");
            $this->http->SetInputValue('webauthn-platform-available', "false");
            $this->http->PostForm();

            if ($this->http->ParseForm(null, "//form[contains(@class, 'ulp-action-form-refuse-add-device')]")) {
                $this->http->SetInputValue('action', "snooze-enrollment");
                $this->http->PostForm();
            }// if ($this->http->ParseForm(null, "//form[contains(@class, 'ulp-action-form-refuse-add-device')]"))
        }// if ($this->http->ParseForm(null, "//form[contains(@class, '_form-detect-browser-capabilities')]"))

        $this->http->RetryCount = 2;

        if (
            $this->http->currentUrl() == 'https://www.copaair.com/en-gs/after-login/'
        ) {
            $this->http->GetURL("https://www.copaair.com/api/auth/me/");
        }

        $response = $this->http->JsonLog();

        $this->botDetectionWorkaround();

        if (isset($response->sid)) {
            $responseAuth = $this->http->JsonLog(null, 0, true);
            $this->captchaReporting($this->recognizer);

            $this->http->GetURL('https://members.copaair.com/en/dashboard?_gl=1*1b250q0*_gcl_au*NjM3NjM3NTMzLjE3MjIyNTY5MTQ.*_ga*MTg5ODk3ODM2OS4xNzIyMjU2OTE0*_ga_SEJ8DB2YNH*MTcyMjI1NjkxMy4xLjEuMTcyMjI1OTMxNy42MC4wLjA.');

            if ($this->http->ParseForm(null, "//form[contains(@class, '_form-detect-browser-capabilities')]")) {
                $this->http->SetInputValue('js-available', "true");
                $this->http->SetInputValue('webauthn-available', "true");
                $this->http->SetInputValue('is-brave', "false");
                $this->http->SetInputValue('webauthn-platform-available', "false");
                $this->http->PostForm();

                if ($this->http->ParseForm(null, "//form[contains(@class, 'ulp-action-form-refuse-add-device')]")) {
                    $this->http->SetInputValue('action', "snooze-enrollment");
                    $this->http->PostForm();
                }
            }

            if ($this->loginSuccessful()) {
                return true;
            }

            // ---------------- It WORKS -----------------
            $response = $this->http->JsonLog(null, 0);

            if ($this->http->Response['body'] == 'nil') {
                throw new CheckRetryNeededException(3, 0);
            }

            if (isset($response->message) && $response->message == 'Internal server error') {
                sleep(5);

                if ($this->loginSuccessful()) {
                    return true;
                }
            }
            // -------------------------------------

            // AccountID: 4598772
            if ($responseAuth) {
                // Name
                $this->SetProperty("Name", beautifulName(ArrayVal($responseAuth, 'given_name') . " " . ArrayVal($responseAuth, 'family_name')));
                // Balance - Mileage Balance
                $this->SetBalance(ArrayVal($responseAuth, 'lp_miles'));
                // Status
                $this->SetProperty("Status", ArrayVal($responseAuth, 'lp_level') == null ? "Member" : ArrayVal($responseAuth, 'lp_level'));
                // Status Expires
                $this->SetProperty("StatusExpires", ArrayVal($responseAuth, 'ExpireCardDate'));
                // ConnectMiles #
                $this->SetProperty("Number", ArrayVal($responseAuth, 'lp_ffn'));
                // Expiration date
                $exp = ArrayVal($responseAuth, 'ExpireMilesDate');

                if (strtotime($exp)) {
                    $this->SetExpirationDate(strtotime($exp));
                }

                $this->http->setCookie("lp_ffn", ArrayVal($responseAuth, 'lp_ffn'), ".copaair.com");
                $this->http->setCookie("lp_firstname", ArrayVal($responseAuth, 'given_name'), ".copaair.com");
                $this->http->setCookie("lp_lastname", urlencode(ArrayVal($responseAuth, 'family_name')), ".copaair.com");
                $this->http->setCookie("lp_level", ArrayVal($responseAuth, 'lp_level') == null ? "Member" : ArrayVal($responseAuth, 'lp_level'), ".copaair.com");
                $this->http->setCookie("lp_miles", ArrayVal($responseAuth, 'lp_miles'), ".copaair.com");

                return true;
            }// if ($responseAuth)

            return $this->botDetectionWorkaround();
        }// if (isset($response->sid))

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "ulp-input-error-message")] | //div[@id = "prompt-alert"]/p')) {
            $this->logger->error("[Error]: {$message}");

            switch ($message) {
                case 'Something went wrong, please try again later':
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

                case strstr($message, 'Wrong username or password'):
                case strstr($message, 'Wrong email or password'):
                case 'User ID data format invalid':
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                case 'Your account has been blocked after multiple consecutive login attempts':
                case 'Account Locked':
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_LOCKOUT);

                default:
                    $this->DebugInfo = $message;

                    break;
            }

            return false;
        }

        if (strstr($this->http->currentUrl(), 'error=access_denied&error_description=Service%20Unavailable')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0, true);
        $loyalty = ArrayVal($response, 'loyalty');

        if ($loyalty) {
            // Name
            $name = ArrayVal($response, 'name');
            $this->SetProperty("Name", beautifulName(ArrayVal($name, 'givenName') . " " . ArrayVal($name, 'surname')));
            // Balance - Mileage Balance
            //        $this->SetBalance(ArrayVal($loyalty, 'balance'));// refs #18570
            // Status
            $this->SetProperty("Status", ArrayVal($loyalty, 'loyalLevel'));
            // Status Expires
            $this->SetProperty("StatusExpires", ArrayVal($loyalty, 'periodEndOnCard'));
            // ConnectMiles #
            $this->SetProperty("Number", ArrayVal($loyalty, 'membershipID'));
            // Balance - Mileage Balance
            $this->SetBalance(ArrayVal($loyalty, 'balance'));
            // Expiration date
            $exp = ArrayVal($loyalty, 'milesExpirationDate');
            // Balance - Mileage Balance
            if (strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
            // Miles to next level
            $this->SetProperty("MilesToNextLevel", ArrayVal($loyalty, 'totalCategoryMiles', 0) - ArrayVal($loyalty, 'qualifyingMiles', 0));
            // Segments to next level
            $this->SetProperty("SegmentsToNextLevel", ArrayVal($loyalty, 'totalCategorySectors', 0) - ArrayVal($loyalty, 'qualifyingSectors', 0));

            $this->http->setCookie("lp_ffn", ArrayVal($loyalty, 'membershipID'), ".copaair.com");
            $this->http->setCookie("lp_firstname", ArrayVal($name, 'givenName'), ".copaair.com");
            $this->http->setCookie("lp_lastname", urlencode(ArrayVal($name, 'surname')), ".copaair.com");
            $this->http->setCookie("lp_level", ArrayVal($loyalty, 'loyalLevel'), ".copaair.com");
            $this->http->setCookie("lp_miles", ArrayVal($loyalty, 'balance'), ".copaair.com");
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"         => "PostingDate",
            "Description"  => "Description",
            "Awards Miles" => "Miles",
            "Bonus"        => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 0;
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://members.copaair.com/api/miles-transactions", $this->headers);
        $this->http->RetryCount = 1;
        $page++;
        $this->logger->debug("[Page: {$page}]");

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function unicodeString($str, $encoding = null)
    {
        if (is_null($encoding)) {
            $encoding = ini_get('mbstring.internal_encoding');
        }

        return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/u', function ($match) use ($encoding) {
            return mb_convert_encoding(pack('H*', $match[1]), $encoding, 'UTF-16BE');
        }, $str);
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog();
        $activities = $response->transactions ?? [];
        $total = count($activities);
        $this->logger->debug("Total {$total} history items were found");

        foreach ($activities as $activity) {
            $dateStr = $activity->date;
            $postDate = strtotime($dateStr);

            if ((isset($startDate) && $postDate < $startDate) || $dateStr == '') {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $activity->typeName;
            $result[$startIndex]['Description'] = $this->unicodeString($result[$startIndex]['Description']);

            if ($this->http->FindPreg('/Bonus/i', false, $result[$startIndex]['Description'])) {
                $result[$startIndex]['Bonus'] = $activity->points;
            } else {
                $result[$startIndex]['Awards Miles'] = $activity->points;
            }

            $startIndex++;
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindSingleNode("//div[@data-captcha-sitekey]/@data-captcha-sitekey");

        if (!$key) {
            //$key = '6Le__CslAAAAAFkzgH7A25GrN17zUomoWjv_eL6i';
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->disableOriginHeader();
        $this->http->setDefaultHeader("CorrelationId", "866c5062-127b-4723-b6ae-0583c33d1673");
        $this->http->GetURL("https://members.copaair.com/api/user", [
            'Accept' => '*/*',
        ], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->name->givenName)) {
            return true;
        }

        return false;
    }

    private function botDetectionWorkaround()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);

        if (
            !$response
            && $this->http->FindSingleNode('//b[
                    contains(text(), "Please solve this CAPTCHA in helping us understand your behavior to grant access")
                    or contains(text(), "Please solve this CAPTCHA to request unblock to the website")
                ]
                | //p[contains(text(), "We’ve detected unusual and/or suspicious activity. Please solve the following Captcha to continue.")]
            ')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }
}
