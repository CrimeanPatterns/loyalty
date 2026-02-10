<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirarabia extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""      => "Select your country",
        "ae-rk" => "Sharjah and Ras Al Khaimah",
        "ma"    => "Morocco",
        "eg"    => "Egypt",
    ];

    protected $data;

    /*public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }*/

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyGoProxies(null, 'es');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        //$this->getCookiesFromSelenium();
        $this->http->GetURL("https://reservations.airarabia.com/service-app/ibe/reservation.html?#/signIn/en/AED/AE");

        if (!$this->http->ParseForm("captcha_form") && !$this->http->FindSingleNode("//title[contains(text(), 'IBE Flight Booking')]")) {
            return $this->checkErrors();
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        if ($this->http->ParseForm("captcha_form")) {
            $captcha = $this->parseReCaptcha();

            if (!$captcha) {
                return false;
            }
            $this->http->FormURL = "https://reservations.airarabia.com/.well-known/proxy/captcha_callback";
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('grecaptcharesp', $captcha);

            $this->http->PostForm();
            // 1724281926-556f8c7429e97a4ebab0110dbf6ca60e3ffd110143be5e41f0b10aecf811bf43
            if ($solved = $this->http->FindPreg('/^\d+-\w+$/')) {
                $this->http->setCookie('solved_captcha', $solved);
            }
        } else {
            return false;
        }

        $this->http->GetURL("https://reservations.airarabia.com/service-app/ibe/reservation.html?#/signIn/en/AED/AE");

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://reservations.airarabia.com/service-app/controller/customer/login", json_encode([
            'customerID'    => $this->AccountFields['Login'],
            'password'      => $this->AccountFields['Pass'],
            'transactionId' => null,
        ]), [
            'Accept'          => 'application/json, text/plain, */*',
            'Content-Type'    => 'application/json',
            'Accept-Encoding' => 'gzip, deflate, br',
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->success, $response->otpRequired) && $response->success === true && $response->otpRequired === true) {
            $this->AskQuestion('Please enter the One-Time Password (OTP) sent to your registered email address', null, 'Question');

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://reservations.airarabia.com/service-app/controller/customer/validateOTP", json_encode([
            'emailId'       => $this->AccountFields['Login'],
            'otp'           => $answer,
            'password'      => $this->AccountFields['Pass'],
            'transactionId' => null,
        ]), [
            'Accept'          => 'application/json, text/plain, */*',
            'Content-Type'    => 'application/json',
            'Accept-Encoding' => 'gzip, deflate, br',
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->success, $response->otpRequired) && $response->success === false && $response->otpRequired === true) {
            $this->AskQuestion($this->Question, "Invalid OTP. Please re-enter OTP",
                "Question");

            return false;
        }

        return $response->success;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Unavailable.
        if ($this->http->Response['code'] == 503 && $this->http->FindSingleNode("//body[contains(., 'Service Unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->success, $response->loggedInLmsDetails->availablePoints) && $response->success === true) {
            return true;
        }

        if (isset($response->success, $response->messages[0]) && $response->success === false) {
            throw new CheckException($response->messages[0], ACCOUNT_INVALID_PASSWORD);
        }
        // retry
        //if ($this->http->FindSingleNode("//font[contains(text(), 'Your session has expired. Please start again')]"))
        //    throw new CheckRetryNeededException(3, 7);

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Air Rewards Points
        if (isset($response->loggedInLmsDetails->availablePoints)) {
            $this->SetBalance($response->loggedInLmsDetails->availablePoints);
        }
        // Name
        if (isset($response->loggedInCustomerDetails->firstName)) {
            $this->SetProperty("Name", beautifulName(sprintf('%s %s', $response->loggedInCustomerDetails->firstName,
                $response->loggedInCustomerDetails->lastName)));
        }
        // AED 0.00 credits
        if (isset($response->totalCustomerCredit)) {
            $this->SetProperty('CurrentReservationCredit', $response->totalCustomerCredit);
        }
    }

    private function getCookiesFromSelenium(): void
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $solvingStatus = null;

        try {
            $selenium->UseSelenium();
            $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);
            $selenium->http->saveScreenshots = true;
            /*$wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($selenium->http->getProxyParams());
            $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
            $selenium->seleniumOptions->addAntiCaptchaExtension = true;*/
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://reservations{$this->AccountFields['Login2']}.airarabia.com/ibe/public/loginApi.action");
            $selenium->waitForElement(WebDriverBy::id('txtUID'), 10);
            $this->savePageToLogs($selenium);

            /*$selenium->waitFor(function () use ($selenium) {
                $this->logger->warning("Solving is in process...");
                sleep(3);
                $this->savePageToLogs($selenium);

                return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
            }, 180);*/

            /*$solvingStatus =
                $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
                ?? $this->http->FindSingleNode('//a[@class = "status"]')
            ;*/

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();
        }

        if ($solvingStatus) {
            $this->logger->error("[AntiCaptcha]: {$solvingStatus}");

            if (
                strstr($solvingStatus, 'Proxy response is too slow,')
                || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection refused')
                || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection timeout')
                || strstr($solvingStatus, 'Captcha could not be solved by 5 different workers')
                || strstr($solvingStatus, 'Solving is in process...')
                || strstr($solvingStatus, 'Proxy IP is banned by target service')
                || strstr($solvingStatus, 'Recaptcha server reported that site key is invalid')
            ) {
                $selenium->markProxyAsInvalid();

                throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                //throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
            }
        }
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class='g-recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://reservations.airarabia.com/ibe/public/loginApi.action',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
