<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerExxonmobilSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://exxonandmobilrewardsplus.com/profile/details';
    private const MAX_TIME = 120;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->SetProxy($this->proxyReCaptcha());

        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->http->setUserAgent(null);
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        */
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

        $this->seleniumOptions->addAntiCaptchaExtension = true;
        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Your email is incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"] | //p[@class = "confirm-card-number" and contains(text(), "Card number")]'), 10);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $this->saveResponse();

        if ($trustBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class , "onetrust-close-btn-handler")]'), 0)) {
            $trustBtn->click();
            sleep(1);
            $this->saveResponse();
        }

        if (!$loginInput || !$passwordInput) {
            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        $mover = new \MouseMover($this->driver);
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 7);
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);
//        $loginInput->sendKeys($this->AccountFields['Login']);
//        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $btn = $this->waitForElement(WebDriverBy::xpath('//a[@name="login"]'), 0);

        if (!$btn) {
            return $this->checkErrors();
        }

        $btn->click();

        $res = $this->waitForElement(WebDriverBy::xpath('//input[@id = "input_field_verification_1"] | //h3[contains(@class, "points-total")] | //a[@id = "link_nav_logout"]'), 15);
        $this->saveResponse();

        if (!$res) {
            $this->waitCaptchaSolving();

            if ($btn = $this->waitForElement(WebDriverBy::xpath('//a[@name="login"]'), 0)) {
                $btn->click();

                $res = $this->waitForElement(WebDriverBy::xpath('//input[@id = "input_field_verification_1"] | //h3[contains(@class, "points-total")] | //a[@id = "link_nav_logout"]'), 15);
                $this->saveResponse();

                if (!$res) {
                    $this->waitCaptchaSolving();

                    if ($btn = $this->waitForElement(WebDriverBy::xpath('//a[@name="login"]'), 0)) {
                        $btn->click();
                    }
                }
            }
        }

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//input[@id = "input_field_verification_1"] | //h3[contains(@class, "points-total")] | //a[@id = "link_nav_logout"] | //p[@class = "alert-error"]'), 10);
        sleep(1);
        $this->saveResponse();

        // https://rewards.exxon.com/otp
        // Confirm your identity
        if ($this->http->FindSingleNode('//input[@id = "input_field_verification_1"]/@id')) {
            $this->holdSession();
            $this->AskQuestion("To continue, please enter the verification code from the email we sent you.", null, "Question");

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//p[@class = "alert-error"]')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Oops, looks like your email or password is invalid. Try again.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Something has gone wrong unexpectedly.')) {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = "captcha or block issue";

                throw new CheckRetryNeededException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "input_field_verification_1"]'), 0);

        if (!isset($codeInput)) {
            $this->logger->error('form elements not found');
            $this->saveResponse();

            return false;
        }

        $codeInput->clear();
        $codeInput->sendKeys($code);

        $btn = $this->waitForElement(WebDriverBy::xpath('//a[@id = "link_btn_submit" and not(contains(@class, "disable"))]'), 3);

        if (!$btn) {
            $this->logger->error('form elements not found');
            $this->saveResponse();

            return false;
        }

        $this->saveResponse();
        $this->waitCaptchaSolving();

        $btn->click();

        $error = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "One-time password invalid")]'), 5); // TODO: fake
        $this->saveResponse();

        // invalid code
        if ($error && $error->getText() == 'One-time password invalid') {
            $this->AskQuestion($this->Question, "Oops, looks like your verification code is invalid. Try again.", "Question");

            return false;
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->waitForElement(WebDriverBy::xpath('//h3[contains(@class, "points-total")]'), 0)) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        return true;
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath('//p[@class = "confirm-card-number" and normalize-space(text()) != "Card number: "] | //input[@name="userName"]'), 10);
        $this->saveResponse();
        // Name
        $name = $this->http->FindSingleNode('//p[contains(@class, "confirm-name")] | //input[@name="userName"]/@value');

        if ($name) {
            $this->SetProperty('Name', beautifulName($name));
        }
        // Card number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('//p[@class = "confirm-card-number" and contains(text(), "Card number")]', null, true, "/\:\s*([^<]+)/"));

        $this->http->GetURL("https://exxonandmobilrewardsplus.com/points/activity");
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "points-balance")] | //p[contains(text(), "Points available")]/preceding-sibling::h2'), 10);
        $this->saveResponse();
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(@class, "points-balance")] | //p[contains(text(), "Points available")]/preceding-sibling::h2'));
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Break Service. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//p[@class = "confirm-card-number" and contains(text(), "Card number")]')
            || $this->http->FindSingleNode('//a[@id = "link_nav_logout"]')
        ) {
            return true;
        }

        return false;
    }

    private function waitCaptchaSolving()
    {
        $this->logger->notice(__METHOD__);

        $i = 0;

        while ($this->http->FindPreg('/<a class="status">Solving is in process...<\/a>/') && $i < self::MAX_TIME) {
            $i++;
            $delay = 1;
            $this->logger->notice("sleep -> {$delay}");
            sleep($delay);
            $this->saveResponse();
        }
    }
}
