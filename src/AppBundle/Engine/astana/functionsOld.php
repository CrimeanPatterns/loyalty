<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAstana extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://airastana.com/kaz/en-us/Nomad-Club/Manage-My-Account/View-Activity-Details";

    private $isSelenium = true; //todo

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['cookies'])) {
            return false;
        }

        foreach ($this->State['cookies'] as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain']);
        }
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (!is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("Incorrect membership number or password", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://airastana.com/kaz/en-US/Home/LoginPage");
//        if ($this->incapsula(false)) {
//            $this->http->GetURL("https://airastana.com");
//            $this->http->GetURL("https://airastana.com/kaz/en-us/Nomad-Club/Nomad-Login?SourceUrl=/kaz/en-us/Nomad-Club/Manage-My-Account/View-Activity-Details");
//        }
        $this->selenium();

        return true;

//        if ($this->isSelenium === true) {
//            return true;
//        }

        if (!$this->http->ParseForm("Form")) {
            return false;
        }

//        $this->http->SetInputValue('membershipNumberUsername', $this->AccountFields['Login']);
//        $this->http->SetInputValue('passwordTbx', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('rememberMeCbx', "true");
        // 6LeWzo0nAAAAAOmI9oX3xLhkPIj4zgxC6KIZZS83

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "MembershipNumber" => $this->AccountFields['Login'],
            "Password"         => $this->AccountFields['Pass'],
            "RememberMe"       => true,
            "CaptchaToken"     => $captcha,
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://airastana.com/desktopmodules/NomadLoginDnnModule/API/LoginApi/SignIn", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        /*
        $response = $this->http->JsonLog();

        if (isset($response->Message, $response->Data) && $response->Message == 'LoginSuccess') {
            $this->http->GetURL($response->Data);

            if ($this->incapsula(false)) {
                $this->http->GetURL("https://airastana.com/kaz/en-us/Nomad-Club/NomadProfile");

                if ($this->loginSuccessful()) {
                    return true;
                }
            }
        }

        if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
            $this->DebugInfo = 'Incapsula';

            if ($this->incapsula(false)) {
                $this->http->GetURL("https://airastana.com/kaz/en-us");
                sleep(2);
                $this->http->GetURL("https://airastana.com/kaz/en-us/Nomad-Club/NomadProfile");

                if ($this->loginSuccessful()) {
                    return true;
                }
            }

            if (!$this->http->ParseForm("Form")) {
                return false;
            }

            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $data = [
                "MembershipNumber" => $this->AccountFields['Login'],
                "Password"         => $this->AccountFields['Pass'],
                "RememberMe"       => true,
                "CaptchaToken"     => $captcha,
            ];
            $headers = [
                "Accept"           => "*
        /*",
                "Content-Type"     => "application/json; charset=utf-8",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://airastana.com/desktopmodules/NomadLoginDnnModule/API/LoginApi/SignIn", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->Message, $response->Data) && $response->Message == 'LoginSuccess') {
                $this->http->GetURL($response->Data);
            }
        }// if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src"))
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'modal') and contains(@style, 'display: block')]//p | //div[@id = 'invalidCredentialsMessage']/p")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Login or password invalid')
                || strstr($message, 'Invalid login or password')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode("//div[@id = 'newPhoneNumber' and contains(@style, 'display: block')]//h4[contains(text(), 'New phone number')] | //div[@id = 'phoneVerify' and contains(@style, 'display: block')]")) {
            $this->throwProfileUpdateMessageException();
        }

        /*
        // hardcode
        // IDs: 2146856, 1394918 - no error for these accounts
        if (
            in_array($this->AccountFields['Login'], [
                100203935, // AccountID: 1052863
                104221272, // AccountID: 3749136
                103440374, // AccountID: 5176472
                104349862, // AccountID: 3978244
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // A critical error has occurred. Please check the Event Viewer for further details.
        if (strstr($this->http->currentUrl(), 'error=An%20unexpected%20error%20has%20occurred')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->DebugInfo == 'Incapsula') {
            throw new CheckRetryNeededException(3, 3);
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "modal in"]//p[contains(text(), "Captcha error")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        */

        return false;
    }

    public function Parse()
    {
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, \"kc-bonus-card\")]//div[contains(@class, 'kc-bonus-card__count')]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'kc-account-page__name')]")));
        // Membership No
        $this->SetProperty("MembershipNo", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'kc-account-page__info')]/text()[1]")));
        // Tier Level
        $this->SetProperty("TierLevel", $this->http->FindSingleNode("//div[contains(@class, 'kc-account-page__info')]/div"));
        // Level points
        $this->SetProperty("FlightPointsThisYear", $this->http->FindSingleNode('//div[contains(@class, "kc-progress-bar_total-value")]/preceding-sibling::div[contains(text(), "level point")]', null, true, "/(.+)\s+level point/"));
        // Flights
        $this->SetProperty("FlightSegmentsThisYear", $this->http->FindSingleNode('//div[contains(@class, "kc-progress-bar_total-value")]/preceding-sibling::div[contains(text(), "flight")]', null, true, "/(.+)\s+flight/"));
        // level points Required for Next Tier
        $this->SetProperty("PointsRequiredForNextTier", $this->http->FindSingleNode('//div[contains(text(), "level point")]/following-sibling::div[contains(@class, "kc-progress-bar_total-value")]'));
        // Segments Required for Next Tier
        $this->SetProperty("SegmentsRequiredForNextTier", $this->http->FindSingleNode('//div[contains(text(), "flight")]/following-sibling::div[contains(@class, "kc-progress-bar_total-value")]'));

        /*
        // Expiration date  // refs #12881
        if ($this->Balance && $this->Balance > 0) {
            $balance = $this->Balance;
            $nodes = $this->http->XPath->query("//h3[contains(text(), 'Activity Details')]/following-sibling::table//tr[td]");
            $this->http->Log("Total {$nodes->length} nodes were found");

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $flightPoints = $this->http->FindSingleNode("td[3]", $node);
                $bonusPoints = $this->http->FindSingleNode("td[4]", $node);
                $expDate = $this->http->FindSingleNode("td[6]", $node);
                $date = $this->http->FindSingleNode("td[1]", $node);

                if (isset($date) && isset($flightPoints, $bonusPoints)) {
                    $pointsEarned[$i] = [
                        'date'         => $date,
                        'expDate'      => $expDate,
                        'flightPoints' => $flightPoints,
                        'bonusPoints'  => $bonusPoints,
                    ];
                    $balance -= $pointsEarned[$i]['flightPoints'];
                    $this->http->Log("#{$i} Date {$pointsEarned[$i]['date']} / {$pointsEarned[$i]['expDate']} - " . var_export(strtotime($pointsEarned[$i]['date']), true) . " - {$pointsEarned[$i]['flightPoints']} / Balance: $balance", false);
                    $balance -= $pointsEarned[$i]['bonusPoints'];
                    $this->http->Log("#{$i} Date {$pointsEarned[$i]['date']} / {$pointsEarned[$i]['expDate']} - " . var_export(strtotime($pointsEarned[$i]['date']), true) . " - {$pointsEarned[$i]['bonusPoints']} / Balance: $balance", false);

                    if ($balance <= 0) {
                        $this->http->Log("Date " . $pointsEarned[$i]['date'], true);
                        $this->http->Log("Expiration Date " . date("Y-m-d", strtotime($pointsEarned[$i]['expDate'])) . " - "
                            . var_export(strtotime($pointsEarned[$i]['expDate']), true), true);
                        // Earning Date     // refs #4936
                        $this->SetProperty("EarningDate", $pointsEarned[$i]['date']);
                        // Expiration Date
                        $this->SetExpirationDate(strtotime($pointsEarned[$i]['expDate']));
                        // Points to Expire
                        $balance += $pointsEarned[$i]['flightPoints'];
                        $balance += $pointsEarned[$i]['bonusPoints'];

                        for ($k = $i - 1; $k >= 0; $k--) {
                            $this->http->Log("> Balance: {$balance}");

                            if (isset($pointsEarned[$k]['expDate']) && $pointsEarned[$i]['expDate'] == $pointsEarned[$k]['expDate']) {
                                $balance += $pointsEarned[$k]['flightPoints'];
                                $balance += $pointsEarned[$k]['bonusPoints'];
                            }// if (isset($pointsEarned[$k]['date']) && $pointsEarned[$i]['date'] == $pointsEarned[$k]['date'])
                        }// for ($k = $i - 1; $k >= 0; $k--)
                        $this->SetProperty("ExpiringBalance", $balance);

                        break;
                    }// if ($balance <= 0)
                }//if (isset($date) && isset($points) && $points > 0)
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if ($this->Balance && $this->Balance > 0)
        */
    }

    protected function incapsula($isRedirect = true)
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");

        if (!$formURL) {
            return false;
        }
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        if ($isRedirect) {
            $this->http->GetURL($referer);

            if ($this->http->Response['code'] == 503) {
                $this->http->GetURL($this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost());
                sleep(1);
                $this->http->GetURL($referer);
            }
        }

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey")
            ?? $this->http->FindSingleNode("//div[@id = 'login-module']//div[@class = 'g-recaptcha']/@data-sitekey")
        ;

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//div[contains(@class, 'kc-account-page__name')]")) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
            $selenium->useChromium();
            /*
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);
            */

//            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
//            $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
//            $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;

//            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://airastana.com/kaz/en-US/Home/LoginPage");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                $this->saveToLogs($selenium);
            }
            sleep(2);

            if ($selenium->waitForElement(WebDriverBy::xpath('//button[@id = "accept-cookies"]'), 5)) {
                $this->saveToLogs($selenium);
                $selenium->driver->executeScript("
                    document.querySelector('#accept-cookies').click();
                ");
                sleep(2);
                $this->saveToLogs($selenium);
            }

            $login = $selenium->waitForElement(WebDriverBy::id('membershipNumberUsername'), 5);
            $pass = $selenium->waitForElement(WebDriverBy::id('passwordTbx'), 0);
            $rememberMe = $selenium->waitForElement(WebDriverBy::id('rememberMeCbx'), 0);
            $button = $selenium->waitForElement(WebDriverBy::id('submitLoginFormBtn'), 0);
            $this->saveToLogs($selenium);

            if (!$login || !$pass || !$button || !$rememberMe) {
                $this->logger->error("something went wrong");

                if ($this->http->FindPreg('/(Request unsuccessful. Incapsula incident ID|HTTP Error 503. The service is unavailable\.)/')) {
                    $retry = true;
                }

                return false;
            }

            $rememberMe->click();
            $login->click();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->click();
            $password = $this->AccountFields['Pass'];
            $pass->sendKeys($password);
            $this->saveToLogs($selenium);

            if ($this->isSelenium === true) {
                $captcha = $this->parseReCaptcha();

                if ($captcha === false) {
                    return false;
                }

                $selenium->driver->executeScript("
                    function SubmitLoginFormCustom() {
                        $(\"#invalidCredentialsMessage\").addClass(\"displayNone\");
                        DisableInputs();
                        DisableSignInButton();
                        
                        let formData = {
                            MembershipNumber: $(\"#membershipNumberUsername\").val(),
                            Password: $(\"#passwordTbx\").val(),
                            RememberMe: $(\"#rememberMeCbx\").prop(\"checked\"),
                            CaptchaToken: '{$captcha}'
                        };
                        $.ajax({
                            url: \"/desktopmodules/NomadLoginDnnModule/API/LoginApi/SignIn\",
                            cache: false,
                            type: \"POST\",
                            contentType: \"application/json; charset=utf-8\",
                            data: JSON.stringify(formData),
                            success: function (response) {
                                if (response != null) {
                                    if (response.Message === \"VerifyPhoneNumber\") {
                                        $(\"#phoneNumberText\").text(\"+7 (XXX) XXX \" + response.Data.slice(-4));
                                        $(\"#phoneNumberTbx\").val(response.Data);
                                        $(\"#phoneVerify\").modal({ backdrop: \"static\", keyboard: false, show: true });
                                        // disable button for 2 minutes
                                        SetTimerForResendCodeButton();
                                        EnableInputs();
                                        EnableSignInButton();
                                        ResetReCaptcha();
                                    } else if (response.Message === \"LoginSuccess\") {
                                        DisableInputs();
                                        DisableSignInButton();
                                        if (response.Data != null && response.Data !== \"\") {
                                            window.location.replace(response.Data);
                                        } else {
                                            window.location.replace(window.location.href);
                                        }
                                    }
                                }
                            },
                            error: function (xhr) {
                                if (xhr.responseJSON != null) {
                                    if (xhr.responseJSON.Message === \"InvalidCaptcha\") {
                                        $(\"#reCaptchaInvalidErrorMessage\").css(\"display\", \"block\");
                                    }
                                    else if (xhr.responseJSON.Message === \"DuplicateExists\") {
                                        DuplicateExists();
                                    } else if (xhr.responseJSON.Message === \"ThrottlingError\"){
                                        $(\"#smsCodeAlreadySentModal\").modal(\"show\");
                                    } else if (xhr.responseJSON.Message === \"SmsCodeNotSent\") {
                                        $(\"#phoneNumberText\").text(\"\");
                                        $(\"#newPhoneNumber\").modal({ backdrop: \"static\", keyboard: false, show: true });
                                    }
                                } else if (xhr.status === 401) {
                                    $(\"#invalidCredentialsMessage\").removeClass(\"displayNone\");
                                }
                                EnableInputs();
                                EnableSignInButton();
                                ResetReCaptcha();
                            }
                        });
                    }
                    
                    SubmitLoginFormCustom();
                ");
//                $button->click();
                $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'kc-account-page__name')] | //div[@id = 'newPhoneNumber' and contains(@style, 'display: block')] | //div[@id = 'phoneVerify' and contains(@style, 'display: block')] | //div[@id = 'invalidCredentialsMessage']/p"), 15);
            }
            // save page to logs
            $this->saveToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();
            $this->State['cookies'] = $cookies;

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $result = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $result;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
