<?php

class TAccountCheckerColdstone extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 10;

    private $rewardsData;
    private $consumerData;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();

        $this->http->saveScreenshots = true;
        $this->seleniumOptions->recordRequests = true;

        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://my.spendgo.com/index.html#/storefront/coldstone', [], 20);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL('https://my.spendgo.com/index.html#/signIn/coldstone');

        $signInWithPassword = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "sign in with password")]'), self::WAIT_TIMEOUT);

        if (!$signInWithPassword) {
            $this->saveResponse();

            return $this->checkErrors();
        }

        $signInWithPassword->click();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="email_email"]'), self::WAIT_TIMEOUT);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="password_password"]'), 0);

        $this->saveResponse();

        if (!$login || !$password) {
            return $this->checkErrors();
        }

        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $this->processCaptcha();

        $submit = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "sign in") and not(@disabled)]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$submit) {
            return $this->checkErrors();
        }

        $submit->click();

        return true;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->getRecordedRequests();

        // Balance - Points
        $balance = $this->rewardsData->point_total ?? null;
        $this->SetBalance($balance);
        // Points to next reward (calculated from threshold and balance)
        $goal = $this->rewardsData->spend_threshold ?? null;

        if ($balance && $goal) {
            $pointsToNextReward = $goal - $balance;
            $this->SetProperty('PointsToNextReward', $pointsToNextReward);
        }
        // Rewards
        $rewards = $this->rewardsData->rewards_list ?? [];

        foreach ($rewards as $reward) {
            if (empty($reward->reward_title)
                || empty($reward->type)
                || $reward->type === 'progress'
            ) {
                continue;
            }
            $this->AddSubAccount([
                'Code'        => preg_filter('/\W/', '', $reward->reward_title),
                'DisplayName' => $reward->reward_title,
                'Balance'     => $reward->quantity ?? null,
            ]);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['CookieURL'] = 'http://www.premierclubrewards.org/';
        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg('/Your code could not be delivered. Please check for typos or try another way to sign in. Still need help?/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/The phone\/email and password combination does not match. Check for typos or choose another way to sign in./')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/Oops! Check your email for typos or use a different email address./')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/Oops, we can’t find your account. Check your email for typos or/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/Incorrect code. Please check for typos and enter your code./')) {
            throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/CAPTCHA validation failed./')) {
            throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "Mui-error") and text()]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The phone/email and password combination does not match. Check for typos or choose another way to sign in.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "Mui-error")]/div[text()]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Incorrect code. Please check for typos and enter your code.')
                || strstr($message, 'CAPTCHA validation failed.')
            ) {
                throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->waitForElement(WebDriverBy::xpath('//button[@id="btn-append-to-single-button" and contains(text(), "Hi")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        $this->getRecordedRequests();

        if (!isset($this->consumerData)) {
            return false;
        }

        $email = $this->consumerData->email ?? null;
        $username = $this->consumerData->username ?? null;
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[Username]: {$username}");

        if (
            ($email && strtolower($email) === strtolower($this->AccountFields['Login']))
            || ($username && $username === $this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function getRecordedRequests()
    {
        $this->logger->notice(__METHOD__);

        try {
            $requests = $this->http->driver->browserCommunicator->getRecordedRequests();
        } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
            $this->logger->error("BrowserCommunicatorException: " . $e->getMessage(), ['HtmlEncode' => true]);

            $requests = [];
        }

        foreach ($requests as $xhr) {
            if (strstr($xhr->request->getUri(), 'consumerdetails')) {
                $this->logger->debug('Catched consumerdetails request');
                $this->consumerData = $this->http->JsonLog(json_encode($xhr->response->getBody()));
            }

            if (strstr($xhr->request->getUri(), 'rewardsAndOffers')) {
                $this->logger->debug('Catched rewardsAndOffers request');
                $this->rewardsData = $this->http->JsonLog(json_encode($xhr->response->getBody()));
            }
        }
    }

    private function processCaptcha()
    {
        $captchaImage = $this->waitForElement(WebDriverBy::xpath('//img[contains(@class, "captcha-image")]'), 0);
        $file = $this->takeScreenshotOfElement($captchaImage);

        if (!isset($file)) {
            $this->logger->debug('Wrong file');

            return false;
        }

        $this->logger->debug("file: " . $file);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        if (!$captcha) {
            return false;
        }

        $captchaInput = $this->waitForElement(WebDriverBy::xpath('//input[@id="text_captcha"]'), 0);
        $this->saveResponse();
        $captchaInput->sendKeys($captcha);

        return true;
    }
}
