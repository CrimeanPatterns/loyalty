<?php

use AwardWallet\Common\Parsing\Html;
use Facebook\WebDriver\Exception\InvalidSessionIdException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\UnknownErrorException;

class TAccountCheckerAmazongiftSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->http->RetryCount = 0;
            $this->http->GetURL($this->getLoginUrl(), [], 20);
            $this->http->RetryCount = 2;

            if ($this->loginSuccessful()) {
                return true;
            }

            $this->processCaptcha();

            if ($this->loginSuccessful()) {
                return true;
            }

            return false;
        } catch (WebDriverCurlException | UnknownErrorException | InvalidSessionIdException $e) { // unknown error: session deleted because of page crash
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
            $this->http->GetURL($this->getLoginUrl());

            $this->processCaptcha();

            if ($this->loginSuccessful()) {
                return true;
            }

            $loginFormUrl = $this->http->FindPreg("/\'nav-signin-tooltip\'><a\s*href=\'([^']+)/");

            if (!$loginFormUrl) {
                $origin = $this->driver->executeScript('return document.location.origin');
                $homepageUrl = $this->http->FindPreg('/nav-bb-right\">\s*<a\shref=\"([^"]+)/');
                $this->http->GetURL("{$origin}{$homepageUrl}");
                $loginFormUrl = $this->http->FindSingleNode('//a[contains(@href,"balance") and contains(@class, "link")]/@href | //a[contains(@href,"balance") and contains(@class, "b-card")]/@href');
            }

            $this->http->GetURL($loginFormUrl);

            if ($this->processLoginForm()) {
                return true;
            }

            $this->checkProviderErrors();

            return $this->checkErrors();
        } catch (WebDriverCurlException | UnknownErrorException | InvalidSessionIdException $e) { // unknown error: session deleted because of page crash
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function Login()
    {
        try {
            $this->processCaptcha();

            $this->checkProviderErrors(); // prevent "keep hackers out" form

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->parseQuestion()) {
                return false;
            }

            $this->checkProviderErrors();

            return $this->checkErrors();
        } catch (WebDriverCurlException | UnknownErrorException | InvalidSessionIdException $e) { // unknown error: session deleted because of page crash
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function Parse()
    {
        try {
            $this->checkProviderErrors(); // prevent "keep hackers out" form

            if ($this->parseQuestion()) {
                return;
            }

            $this->logger->info("Closing cookies popup", ['Header' => 1]);
            $popupClose = $this->waitForElement(WebDriverBy::xpath('//input[@id="sp-cc-accept"]'), 5);

            if ($popupClose) {
                $popupClose->click();
            }

            $this->http->SaveResponse();

            $this->logger->info("Searching account data url", ['Header' => 1]);
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hallo,') or contains(text(), 'Hello,')]", null, true, "/llo,\s*([^<]+)/")));

            $accountDataLink = $this->http->FindSingleNode('//a[contains(@href, "homepage") and contains(@href, "youraccount_btn")]/@href');
            $this->http->GetURL($accountDataLink);

            $this->saveResponse();

            $balanceUrl = $this->http->FindSingleNode('//a[contains(@href,"balance") and contains(@class, "link")]/@href | //a[contains(@href,"balance") and contains(@class, "b-card")]/@href');
            $addressesUrl = $this->http->FindSingleNode('//a[contains(@href,"addresses") and contains(@class, "link")]/@href | //a[contains(@href,"addresses") and contains(@class, "b-card")]/@href');

            $this->http->GetURL($balanceUrl);

            $this->saveResponse();

            if ($this->parseQuestion()) {
                return;
            }

            $this->logger->info("Parsing balance data", ['Header' => 1]);
            //# Balance - Available Gift Certificate Balance
            $balance = $this->http->FindSingleNode("//span[@id = 'gc-ui-balance-gc-balance-value']");
            $this->SetBalance($balance);

            // Full Name

            $this->logger->info("Parsing addresses data", ['Header' => 1]);
            $this->http->GetURL($addressesUrl);

            $this->saveResponse();

            if ($this->parseQuestion()) {
                return;
            }

            $name = $this->http->FindSingleNode('//span[@id="address-ui-widgets-FullName"]/text()[1]');

            $this->SetProperty("Name", beautifulName($name));

            if ($this->AccountFields['Login2'] == 'USA') {
                $this->logger->info("Parsing subaccounts", ['Header' => 1]);

                if (!$this->parseAffiliateProgram()) { // preparing 2fa
                    return;
                }

                if (!$this->parseAmazonPayments()) { // preparing 2fa
                    return;
                }

                if (!$this->parseNoRushRewards()) { // preparing 2fa
                    return;
                }

                if (!$this->parseAmazonTurk()) { // preparing 2fa
                    return;
                }
            }
        } catch (WebDriverCurlException | UnknownErrorException | InvalidSessionIdException $e) { // unknown error: session deleted because of page crash
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $this->processSelectDeviceForm();

        $parseQuestionResult = $this->parseTwoFactorQuestion();

        $this->logger->debug('parseTwoFactorQuestion returns ' . json_encode($parseQuestionResult));

        if ($parseQuestionResult) {
            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        try {
            $this->logger->notice(__METHOD__);
            $this->logger->debug("Current URL: " . $this->http->currentUrl());

            if ($this->isNewSession()) {
                return $this->LoadLoginForm() && $this->Login();
            }

            if ($step == "twoFactorQuestion") {
                return $this->parseTwoFactorQuestion();
            }

            return false;
        } catch (WebDriverCurlException | UnknownErrorException | InvalidSessionIdException $e) { // unknown error: session deleted because of page crash
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
    }

    protected function processCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $captchaIsPresent = $this->waitForElement(WebDriverBy::xpath('//iframe[@id="aa-challenge-whole-page-iframe"] | // iframe[@id="cvf-aamation-challenge-iframe"] | //input[@id="captchacharacters"] | //input[@name="cvf_captcha_input"] | //input[@id="aa_captcha_input"]'), 5);
        $this->saveResponse();

        if (!$captchaIsPresent) {
            $this->logger->debug('Captcha is not present');

            return false;
        }

        $captchaIframe = $this->findElement(WebDriverBy::xpath('//iframe[@id="aa-challenge-whole-page-iframe"]'));

        if ($captchaIframe) {
            $this->logger->debug("switch to captcha iframe");
            $this->driver->switchTo()->frame($captchaIframe);
            $this->saveResponse();
        }

        $unknownCaptchaIframe = $this->findElement(WebDriverBy::xpath('// iframe[@id="cvf-aamation-challenge-iframe"]'));

        if ($unknownCaptchaIframe) {
            $this->logger->debug("unknown captcha detected");
            $this->sendNotification('refs #23399 unknown captcha detected // IZ');
            $this->driver->switchTo()->frame($unknownCaptchaIframe);
            $this->saveResponse();

            throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
        }

        $captchaInput = $this->findElement(WebDriverBy::xpath('//input[@id="captchacharacters"] | //input[@name="cvf_captcha_input"] | //input[@id="aa_captcha_input"]'));
        $captchaSubmit = $this->findElement(WebDriverBy::xpath('//button[contains(text(), "Continue shopping")]| //*[@value="verifyCaptcha"] | //button[@type="submit"]'));

        if (!$captchaInput || !$captchaSubmit) {
            $this->logger->debug('Captcha input not found');

            return false;
        }

        if ($this->http->FindSingleNode('//div[contains(@id, "captcha")]/img/@src')) {
            $link = '//div[contains(@id, "captcha")]/img';
        } elseif ($this->http->FindSingleNode('//div[contains(@class, "cvf-captcha-img")]/img/@src')) {
            $link = '//div[contains(@class, "cvf-captcha-img")]/img';
        } elseif ($this->http->FindSingleNode('//img[contains(@src, "captcha")]/@src')) {
            $link = '//img[contains(@src, "captcha")]';
        } elseif ($this->http->FindSingleNode('//img[contains(@alt, "captcha")]')) {
            $link = '//img[contains(@alt, "captcha")]';
        } elseif ($this->http->FindSingleNode('//img/@src')) {
            // This is a very nasty captcha. No selector except this one catches it. There's some error catching done above, so everything should work fine. Keep that in mind if you decide to delete this.
            $link = '//img';
        } else {
            $this->logger->debug('Captcha element not found');

            return false;
        }

        $this->printCaptchaSrc();

        $this->logger->debug("Taking screenshot of captcha: '{$link}'");
        $linkElement = $this->findElement(WebDriverBy::xpath($link));
        $file = $this->takeScreenshotOfElement($linkElement);

        if (!isset($file)) {
            $this->logger->debug('Wrong file');

            return false;
        }

        $this->logger->debug("file: " . $file);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        if ($captcha === false) {
            $this->logger->debug("Captcha is false");

            return false;
        }

        $captchaInput->clear();
        $captchaInput->sendKeys($captcha);
        $this->saveResponse();

        $captchaInput->sendKeys(\WebDriverKeys::ENTER);
        $this->saveResponse();

        $captchaSubmit = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue shopping")]| //*[@value="verifyCaptcha"] | //button[@type="submit"]'), 0); // prevent StaleElementReferenceException

        if ($captchaSubmit) {
            $captchaSubmit->click();
            $this->saveResponse();
        }

        /*
        for ($i = 0; $i < 3; $i++) { // debug
            try {
                $captchaSubmit = $this->findElement(WebDriverBy::xpath('//button[contains(text(), "Continue shopping")]| //*[@value="verifyCaptcha"] | //button[@type="submit"]'));
            } catch (StaleElementReferenceException $e) {
                $this->logger->debug("error: {$e->getMessage()}");
                $captchaSubmit = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue shopping")]| //*[@value="verifyCaptcha"] | //button[@type="submit"]'), 0);
            } finally {
                if (!$captchaSubmit) {
                    break;
                }
                $captchaSubmit->click();
                $this->saveResponse();
            }
        }
        */

        $error = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cvf-captcha-error")] | //div[contains(@class, "aacb-widget-alert-message")] | //input[@id="captchacharacters"] | //input[@name="cvf_captcha_input"]'), 5);

        $this->saveResponse();

        $this->printCaptchaSrc();

        /*
        if ($this->findElement(WebDriverBy::xpath('//div[contains(@class, "aacb-widget-alert-message")]'))) {
            $this->sendNotification('refs #23399 need to check the contents of the element "aacb-widget-alert-message" // IZ');
        }
        */

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "aacb-widget-alert-message")]/text()')) {
            if (strstr($message, "Enter the characters as they are given in the challenge")) {
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
            $this->sendNotification('refs #23399 aacb-widget-alert-message - new captcha message // IZ');
        }

        if ($error && $this->findElement(WebDriverBy::xpath('//div[contains(@class, "cvf-captcha-error")]'))) {
            $this->logger->debug('Captcha is incorrect');

            $this->captchaReporting($recognizer, false);

            throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
        }

        if ($error && $this->findElement(WebDriverBy::xpath('//input[@id="captchacharacters"] | //input[@name="cvf_captcha_input"]'))) {
            $this->logger->debug('Seems that captcha is incorrect or error on submit captcha form');

            throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
        }

        $this->captchaReporting($recognizer);

        return true;
    }

    private function printCaptchaSrc()
    { // only for debug purposes
        $this->logger->notice(__METHOD__);

        if ($src = $this->http->FindSingleNode('//div[contains(@id, "captcha")]/img/@src')) {
            $this->logger->debug("CAPTCHA SRC: {$src}");
        } elseif ($src = $this->http->FindSingleNode('//div[contains(@class, "cvf-captcha-img")]/img/@src')) {
            $this->logger->debug("CAPTCHA SRC: {$src}");
        } elseif ($src = $this->http->FindSingleNode('//img[contains(@src, "captcha")]/@src')) {
            $this->logger->debug("CAPTCHA SRC: {$src}");
        } elseif ($src = $this->http->FindSingleNode('//img[contains(@alt, "captcha")]')) {
            $this->logger->debug("CAPTCHA SRC: {$src}");
        } elseif ($src = $this->http->FindSingleNode('//img/@src')) {
            $this->logger->debug("CAPTCHA SRC: {$src}");
        } else {
            $this->logger->debug('Captcha element not found');
        }
    }

    private function copySeleniumCookies($selenium, $curl)
    {
        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function parseAffiliateProgram()
    {
        $this->logger->info("Amazon (Affiliate Program) Curl", ['Header' => 3]);

        $dataUrl = 'https://affiliate-program.amazon.com/gp/associates/network/main.html';

        $drive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($drive);

        $this->copySeleniumCookies($this, $drive);

        $drive->GetURL($dataUrl);

        if ($drive->ParseForm("signIn") || $drive->FindSingleNode('//form[@id="auth-mfa-form"] | //form[@id="verification-code-form"]')) {
            $this->logger->debug('Auth flow elements detected. Curl parsing failed, initializing parsing on selenium');
            // $this->sendNotification('refs #23399 parsing subaccount "AffiliateProgram" with selenium // IZ');
            $this->logger->info("Amazon (Affiliate Program) Selenium", ['Header' => 3]);

            $this->http->GetURL($dataUrl);
            $this->processCaptcha();
            $this->processLoginForm();
            $this->processCaptcha();

            if ($this->parseQuestion()) {
                return false;
            }
            $this->http->GetURL($dataUrl);
            $this->saveResponse();
            $drive = $this->http;
        }
        $balance = $drive->FindPreg("/Total Earnings:.*<[^>]+>\s*<[^>]+>[\$]+([^<]+)/ims");

        if (isset($balance) && $balance > 0) {
            // $this->sendNotification("refs #23399 need to check affiliate program // IZ");

            $this->AddSubAccount([
                'Code'              => 'AmazonAffiliateProgram',
                'DisplayName'       => 'Affiliate Program',
                'Balance'           => $balance,
                'TotalItemsShipped' => Html::cleanXMLValue($drive->FindPreg("/Total Items Shipped:\s*<[^<]+>\s*<[^<]+>([^<]+)/ims")),
                // 'ReferralRate'      => $curl->http->FindPreg("/Referral Rate<[^<]+>\s*<[^<]+>([^<]+)/ims"),
                'OrderedItems'      => Html::cleanXMLValue($drive->FindPreg("/Total Ordered Items:\s*<[^<]+>\s*<[^<]+>([^<]+)/ims")),
                'Clicks'            => Html::cleanXMLValue($drive->FindPreg("/Clicks\s*<[^<]+>\s*<[^<]+>([^<]+)/ims")),
                'Conversion'        => Html::cleanXMLValue($drive->FindPreg("/Conversion:\s*<[^<]+>\s*<[^<]+>([^<]+)/ims")),
            ]);
        }// if (isset($balance))
        elseif ($message = $drive->FindPreg('/(?:The e-mail address and password you are using are not connected to an Associates account\.|The e-mail address \/ mobile number and password you are using are not connected to an Associates account\.|The e-mail\/mobile number you are using is not connected to an Associates account\.)/ims')) {
            $this->logger->notice("Amazon (Affiliate Program): " . $message);
        }

        return true;
    }

    private function parseAmazonPayments()
    {
        $this->logger->info("Amazon Payments Curl", ['Header' => 3]);

        $dataUrl = 'https://payments.amazon.com/sdui/sdui/overview';

        $drive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($drive);

        $this->copySeleniumCookies($this, $drive);

        $drive->GetURL($dataUrl);

        if ($drive->ParseForm("signIn") || $drive->FindSingleNode('//form[@id="auth-mfa-form"] | //form[@id="verification-code-form"]')) {
            $this->logger->debug('Auth flow elements detected. Curl parsing failed, initializing parsing on selenium');
            // $this->sendNotification('refs #23399 parsing subaccount "AmazonPayments" with selenium // IZ');
            $this->logger->info("Amazon Payments Selenium", ['Header' => 3]);

            $this->http->GetURL($dataUrl);
            $this->processCaptcha();
            $this->processLoginForm();
            $this->processCaptcha();

            if ($this->parseQuestion()) {
                return false;
            }
            $this->http->GetURL($dataUrl);
            $this->saveResponse();
            $drive = $this->http;
        }

        $balance = $drive->FindSingleNode("//div[@id = 'balanceValue']");

        if (isset($balance)) {
            // $this->sendNotification('refs #23399 balance of subaccount "AmazonPayments" is present // IZ');
            $this->AddSubAccount([
                'Code'        => 'AmazonAmazonPayments',
                'DisplayName' => 'Amazon Payments',
                'Balance'     => $balance,
                //# Auto-Deposit
                'AutoDeposit' => $drive->FindSingleNode("//div[@id = 'autoDepValue']/span/text()[1]"),
            ]);
        }// if (isset($balance))
        elseif (($message = $drive->FindSingleNode("//div[@id = 'message_error']"))
                || ($message = $drive->FindSingleNode("//p[contains(text(), 'Please provide current and accurate information to obtain an Amazon Payments account')]"))
                || ($message = $drive->FindPreg("/(An error occurred when we tried to process your request)/ims"))) {
            $this->logger->error("Amazon Payments: " . $message);
        }

        return true;
    }

    private function parseNoRushRewards()
    {
        $this->logger->info("No-Rush Rewards Curl", ['Header' => 3]);

        $dataUrl = 'https://www.amazon.com/norushcredits';

        $drive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($drive);

        $this->copySeleniumCookies($this, $drive);

        //# No-Rush Rewards  // refs #17532
        $drive->GetURL($dataUrl);

        if ($drive->ParseForm("signIn") || $drive->FindSingleNode('//form[@id="auth-mfa-form"] | //form[@id="verification-code-form"]')) {
            $this->logger->debug('Auth flow elements detected. Curl parsing failed, initializing parsing on selenium');
            // $this->sendNotification('refs #23399 parsing subaccount "NoRushRewards" with selenium // IZ');
            $this->logger->info("No-Rush Rewards Selenium", ['Header' => 3]);

            $this->http->GetURL($dataUrl);
            $this->processCaptcha();
            $this->processLoginForm();
            $this->processCaptcha();

            if ($this->parseQuestion()) {
                return false;
            }
            $this->http->GetURL($dataUrl);
            $this->saveResponse();
            $drive = $this->http;
        }

        $balanceNoRushRewards = $drive->FindSingleNode("//div[h1[contains(text(), 'Your No-Rush Reward Balance') or contains(text(), 'Your No-Rush and Amazon Day Reward Balance')]]/following-sibling::div[1]/h1", null, true, "/\:\s*([^<]+)/");

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", $drive->FindSingleNode("//span[@class='nav-shortened-name']"));
        }

        $this->SetProperty("NoRushRewards", $balanceNoRushRewards);

        $rushRewards = $drive->XPath->query("//div[contains(@class, 'a-spacing-none')]/div[contains(@class, 'a-row')]");
        $this->logger->debug("Total {$rushRewards->length} No-Rush Rewards were found");

        foreach ($rushRewards as $rushReward) {
            $displayName = $drive->FindSingleNode(".//h6", $rushReward);
            $rushRewardBalance = $drive->FindSingleNode(".//h3", $rushReward);
            $exp =
                $drive->FindSingleNode(".//span[contains(text(), 'expires in')]", $rushReward, true, "/ on ([^<.]+)/")
                ?? $drive->FindSingleNode(".//span[contains(text(), 'expires today on')]", $rushReward, true, "/ on ([^<.]+)/")
                ?? $drive->FindSingleNode("(.//span[contains(text(), 'expires on')])[1]", $rushReward, true, "/expires on([^<.]+)/");

            $expiringBalance =
                $drive->FindSingleNode(".//span[contains(text(), 'expires in')]", $rushReward, true, "/^(.+) expires in \d+/")
                ?? $drive->FindSingleNode(".//span[contains(text(), 'expires today on')]", $rushReward, true, "/([^<]+)\s+expires today on/")
                ?? $drive->FindSingleNode("(.//span[contains(text(), 'expires on')])[1]", $rushReward, true, "/([^<]+)\s+expires on/")
            ;

            if (!$expiringBalance) {
                $this->sendNotification("refs #23399 need to check No-Rush Rewards exp date // IZ");
            }

            if (isset($displayName, $rushRewardBalance)) {
                $this->AddSubAccount([
                    'Code'            => 'amazonNoRushRewards' . str_replace(' ', '', $displayName) . strtotime($exp),
                    'DisplayName'     => $displayName,
                    'Balance'         => $rushRewardBalance,
                    'ExpirationDate'  => strtotime($exp, false),
                    'ExpiringBalance' => $expiringBalance,
                ]);
            }// if (isset($displayName, $rushRewardBalance))
        }// foreach ($rushRewards as $rushReward)

        return true;
    }

    private function parseAmazonTurk()
    {
        $this->logger->info("Amazon Turk Curl", ['Header' => 3]);

        $dataUrl = 'https://worker.mturk.com/dashboard';

        $drive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($drive);

        $this->copySeleniumCookies($this, $drive);

        $drive->GetURL($dataUrl);

        if ($drive->ParseForm("signIn") || $drive->FindSingleNode('//form[@id="auth-mfa-form"] | //form[@id="verification-code-form"]')) {
            $this->logger->debug('Auth flow elements detected. Curl parsing failed, initializing parsing on selenium');
            // $this->sendNotification('refs #23399 parsing subaccount "AmazonTurk" with selenium // IZ');
            $this->logger->info("Amazon Turk Selenium", ['Header' => 3]);

            $this->http->GetURL($dataUrl);
            $this->processCaptcha();
            $this->processLoginForm();
            $this->processCaptcha();

            if ($this->parseQuestion()) {
                return false;
            }
            $this->http->GetURL($dataUrl);
            $this->saveResponse();
            $drive = $this->http;
        }

        if ($drive->FindSingleNode('//form[@action="/register"]')) {
            $this->logger->debug('This account don\'t have access to amazon turk');

            return true;
        }

        if ($drive->FindSingleNode('//div[@class="error-page"]')) {
            $msgHeader = $drive->FindSingleNode('//div[@class="error-page"]//h1/text()');
            $msgBody = $drive->FindSingleNode('//div[@class="error-page"]//p/text()');
            $this->logger->debug($msgHeader);
            $this->logger->debug($msgBody);
            $this->logger->debug('This account has problems with Amazon Turk');

            return true;
        }

        // Current Earnings
        // Available for Transfer
        $balance = $drive->FindSingleNode("//div[strong[contains(text(), 'Current Earnings') or contains(text(), 'Available for Transfer')]]/following-sibling::div");

        if (isset($balance)) {
            // $this->sendNotification('refs #23399. Need to check Amazon Turk // IZ');

            $this->AddSubAccount([
                'Code'        => 'AmazonMechanicalTurk',
                'DisplayName' => 'Mechanical Turk',
                'Balance'     => $balance,
            ]);
        }// if (isset($balance))
        elseif (($message = $drive->FindSingleNode("//div[@id = 'message_error']"))
                || ($message = $drive->FindSingleNode("//div[contains(text(), 'User Registration')]"))) {
            $this->logger->notice("Mechanical Turk: " . $message);
        }

        return true;
    }

    private function getLoginUrl()
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                return 'https://www.amazon.co.uk/ref=ap_frn_logo';

            case 'France':
                return 'https://www.amazon.fr/ref=ap_frn_logo';

            case 'Canada':
                return 'https://www.amazon.ca/ref=nav_logo';

            case 'Germany':
                return 'https://www.amazon.de/ref=ap_frn_logo';

            case 'Japan':
                return 'https://www.amazon.co.jp/';

            case 'USA': default:
                return 'https://www.amazon.com';
        }
    }

    private function processSelectDeviceForm()
    {
        $this->logger->notice(__METHOD__);

        $selectDeviceFormIsPresent = $this->waitForElement(WebDriverBy::xpath('//form[@id="auth-select-device-form"] | //div[contains(@class, "auth-SMS")]//input'), 5);
        $this->saveResponse();

        if (!$selectDeviceFormIsPresent) {
            $this->logger->debug('Select device form not found');

            return false;
        }

        // Text me at my number ending in ...
        $smsOption = $this->http->FindSingleNode("(//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'SMS')]/@value)[1]")
            ?? $this->http->FindSingleNode("//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'TOTP')]/@value")
        ;

        if (!$smsOption) {
            $this->logger->debug('Sms option not found');

            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $opt = $this->findElement(WebDriverBy::xpath('//div[contains(@class, "auth-SMS")]//input'));

        if (!$opt) {
            $this->logger->debug('Sms option not found');

            return false;
        }

        $opt->click();

        $submit = $this->waitForElement(WebDriverBy::xpath('//input[@id="auth-send-code"]'), 5);
        $this->saveResponse();

        if (!$submit) {
            $this->logger->debug('Submit button not found');

            return false;
        }

        $submit->click();

        return true;
    }

    private function parseTwoFactorQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->loginSuccessful()) {
            return false;
        }

        $twoFactorIsPresent = $this->waitForElement(WebDriverBy::xpath('//form[@id="auth-mfa-form"] | //form[@id="verification-code-form"] | //input[@id="input-box-otp"] | //input[@id="auth-mfa-otpcode"] | //span[@id="cvf-submit-otp-button-announce"]/../input | //input[@id="auth-signin-button"]'), 5);
        $this->saveResponse();

        if (!$twoFactorIsPresent) {
            $this->logger->debug('Two factor is not present');

            return false;
        }

        $form = $this->findElement(WebDriverBy::xpath('//form[@id="auth-mfa-form"] | //form[@id="verification-code-form"]'));
        $input = $this->findElement(WebDriverBy::xpath('//input[@id="input-box-otp"] | //input[@id="auth-mfa-otpcode"]'));
        $submit = $this->findElement(WebDriverBy::xpath('//span[@id="cvf-submit-otp-button-announce"]/../input | //input[@id="auth-signin-button"]'));

        $question = $this->http->FindSingleNode('//div[@id="channelDetailsForOtp"]//span/text() | //form[@id="auth-mfa-form"]//p/text()');

        if (!$form || !$input || !$submit || !$question) {
            $this->logger->debug('Form fields not found');

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "twoFactorQuestion");

            return true;
        }

        $input->clear();
        $input->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        $rememberDevice = $this->findElement(WebDriverBy::xpath('//div[@data-a-input-name="rememberDevice"]'));

        if ($rememberDevice) {
            $rememberDevice->click();
        }

        $this->logger->debug("Submit question");
        $submit->click();

        sleep(5);

        $this->saveResponse();

        $error = $this->http->FindSingleNode('//div[@id="auth-error-message-box"]//span/text() | //div[@id="invalid-otp-code-message"]/text()');

        if ($error) {
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "twoFactorQuestion");
            $this->DebugInfo = $error;
        }

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logoutItemXpath = '//a[@id="nav-item-signout" and contains(@href, "signout")] | //a[@id="nav-item-signout" and contains(@href, "sign-out")]'; // sign-out for amazon business
        $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), 5);
        $this->saveResponse();

        if ($this->http->FindSingleNode($logoutItemXpath, null, true)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function processLoginForm()
    {
        $this->logger->notice(__METHOD__);

        $loginFormIsPresent = $this->waitForElement(WebDriverBy::xpath('//input[@id="ap_email"] | //input[@id="ap_password"] | //input[@id="signInSubmit"] | //input[@id="continue"] | //a[@id="ap_switch_account_link"]'), 10);
        $this->saveResponse();

        if (!$loginFormIsPresent) {
            $this->logger->debug('Login form not found');

            return false;
        }

        $login = $this->findElement(WebDriverBy::xpath('//input[@id="ap_email"]'));
        $pass = $this->findElement(WebDriverBy::xpath('//input[@id="ap_password"]'));

        $sbm = $this->findElement(WebDriverBy::xpath('//input[@id="signInSubmit"]'));
        $continue = $this->findElement(WebDriverBy::xpath('//input[@id="continue"]'));

        $switch = $this->findElement(WebDriverBy::xpath('//a[@id="ap_switch_account_link"]'));

        if ($switch) {
            return $this->existingAccountForm();
        }

        if ($login && !$pass && $continue) {
            return $this->serialForm();
        } elseif ($login && $pass && $sbm) {
            return $this->standardForm();
        }

        $this->logger->debug('Failed to recognize login form type');

        return false;
    }

    private function serialForm()
    {
        $this->logger->notice(__METHOD__);

        $email = $this->findElement(WebDriverBy::xpath('//input[@id="ap_email"]'));
        $continue = $this->findElement(WebDriverBy::xpath('//input[@id="continue"]'));

        if (!$email || !$continue) {
            $this->logger->debug('Form fields not found');

            return false;
        }

        $email->clear();
        $email->sendKeys($this->AccountFields['Login']);
        $continue->click();

        $this->processCaptcha();

        $formFieldsIsPresent = $this->waitForElement(WebDriverBy::xpath('//input[@id="ap_password"] | //input[@id="signInSubmit"]'), 5);

        if (!$formFieldsIsPresent) {
            $this->logger->debug('form fields not found');

            return false;
        }

        $password = $this->findElement(WebDriverBy::xpath('//input[@id="ap_password"]'));
        $submit = $this->findElement(WebDriverBy::xpath('//input[@id="signInSubmit"]'));

        if (!$password || !$continue) {
            $this->logger->debug('form fields not found');

            return false;
        }

        $rememberMe = $this->findElement(WebDriverBy::xpath('//input[@name="rememberMe"]'));

        if ($rememberMe) {
            $rememberMe->click();
        }

        $password->clear();
        $password->sendKeys($this->AccountFields['Pass']);
        $submit->click();

        $this->saveResponse();

        $this->processCaptcha();

        return true;
    }

    private function standardForm()
    {
        $this->logger->notice(__METHOD__);

        $login = $this->findElement(WebDriverBy::xpath('//input[@id="ap_email"]'));
        $pass = $this->findElement(WebDriverBy::xpath('//input[@id="ap_password"]'));
        $sbm = $this->findElement(WebDriverBy::xpath('//input[@id="signInSubmit"]'));
        $rememberMe = $this->findElement(WebDriverBy::xpath('//input[@name="rememberMe"]'));

        $this->saveResponse();

        if (!$login || !$pass || !$sbm) {
            $this->logger->debug('Form fields not found');

            return false;
        }

        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $pass->clear();
        $pass->sendKeys($this->AccountFields['Pass']);

        if ($rememberMe) {
            $rememberMe->click();
        }
        $this->saveResponse();
        $sbm->click();

        return true;
    }

    private function existingAccountForm()
    {
        $this->logger->notice(__METHOD__);
        $email = $this->http->FindSingleNode('//div[@class="a-row a-size-base a-color-tertiary auth-text-truncate"]/text()');

        if (strtolower($email) !== strtolower($this->AccountFields['Login'])) {
            $switch = $this->findElement(WebDriverBy::xpath('//a[@id="ap_switch_account_link"]'));

            if (!$switch) {
                $this->logger->debug('Switch button not found');

                return false;
            }

            $switch->click();

            $signOut = $this->waitForElement(WebDriverBy::xpath('//a[@data-name="sign_out_request"]'), 5);
            $this->saveResponse();

            if (!$signOut) {
                $this->logger->debug('SignOut button not found');

                return false;
            }

            $signOut->click();

            return $this->standardForm();
        }

        $pass = $this->findElement(WebDriverBy::xpath('//input[@id="ap_password"]'));
        $sbm = $this->findElement(WebDriverBy::xpath('//input[@id="signInSubmit"]'));
        $rememberMe = $this->findElement(WebDriverBy::xpath('//input[@name="rememberMe"]'));

        if ($rememberMe) {
            $rememberMe->click();
        }

        $pass->clear();
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->saveResponse();

        $sbm->click();

        return true;
    }

    private function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($skipLink = $this->http->FindSingleNode('//a[contains(text(), "Not now") or contains(text(), "Pas maintenant")]/@href')) { // prevent "keep hackers out" form
            $this->logger->notice("skip profile update");
            $this->http->GetURL($skipLink);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(normalize-space(text()), "Sorry, you\'ve made too many failed attempts. We blocked your Sign-In to protect it against unauthorized access.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# Invalid credentials
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There was an error with your E-Mail/Password combination')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Your e-mail or password was incorrect. Please try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We cannot find an account with that e-mail address
        if ($message = $this->http->FindSingleNode("//*[
                contains(text(), 'We cannot find an account with that e-mail address')
                or contains(text(), 'We cannot find an account with that mobile number')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your password is incorrect
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your password is incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Enter a valid e-mail or mobile number
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Enter a valid e-mail or mobile number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid e-mail address or mobile phone number
        if ($message = $this->http->FindSingleNode("//div[@id = 'auth-error-message-box']//span[contains(text(), 'Invalid e-mail address or mobile phone number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# We've blocked your sign-in to protect it against unauthorised access.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'ve blocked your sign-in to protect it against unauthorised access.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your account has been locked for security purposes.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Your account has been locked for security purposes.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Password assistance
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Password assistance')]") && $this->http->ParseForm("forgotPassword")) {
            throw new CheckException("Account has been locked", ACCOUNT_LOCKOUT);
        }
        // For your security, we need you to reset the password on your account.
        if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'For your security, we need you to reset the password on your account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // For your security, we need you to reset the password on your account.
        if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'Zu Ihrer eigenen Sicherheit müssen wir das Passwort für Ihr Konto zurücksetzen. Hierfür senden wir Ihnen einen Code zu.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Ein Problem ist aufgetreten:')]", null, true, "/([^\:]+)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//div[@id="auth-error-message-box"]//span/text()')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'We cannot find an account with that e-mail address') || strstr($message, 'Your password is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Please wait at least one minute before requesting another OTP')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // We cannot find an account with that e-mail address
        if ($message = $this->http->FindSingleNode("//*[
                contains(text(), 'We cannot find an account with that e-mail address')
                or contains(text(), 'We cannot find an account with that mobile number')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Votre mot de passe est incorrect
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Votre mot de passe est incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Pour votre sécurité, nous vous demandons de réinitialiser le mot de passe de votre compte.
        if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'Pour votre sécurité, nous vous demandons de réinitialiser le mot de passe de votre compte.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Impossible de trouver un compte correspondant à cette adresse e-mail
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Impossible de trouver un compte correspondant à cette adresse e-mail')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        /*
        // hard code (AccountID: 3270884)
        if ($this->AccountFields['Login'] == 'stephanie_lautier@yahoo.fr') {
            throw new CheckException("Votre mot de passe est incorrect", ACCOUNT_INVALID_PASSWORD);
        }
        */

        // We cannot find an account with that e-mail address
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'We cannot find an account with that e-mail address')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // For your security, we need you to reset the password on your account.
        if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'For your security, we need you to reset the password on your account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Invalid login or password
        if ($this->http->FindSingleNode("//font[contains(text(), 'The e-mail address and password you entered do not match any accounts on record')]")) {
            throw new CheckException("The e-mail address and password you entered do not match any accounts on record", ACCOUNT_INVALID_PASSWORD);
        }
        // There was an error with your E-Mail/Password combination. Please try again.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'There was an error with your E-Mail/Password combination')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'There was an error with your E-Mail/ Password combination.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Internal Error. Please try again later.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Internal Error. Please try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There was a problem with your request
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'There was a problem with your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There was an error with your Phone/Password combination. Please try again
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There was an error with your Phone/Password combination. Please ')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The phone number you entered cannot be used to sign in.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'The phone number you entered cannot be used to sign in.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# E-mail Address Already in Use
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'E-mail Address Already in Use')]/following::div[@id = 'ap_email_verify_lockout_warn_box']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your email or password was incorrect. Please try again
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Your email or password was incorrect. Please try again')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your password is incorrect
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your password is incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We can not find an account with that email address
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We can not find an account with that email address')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We cannot find an account with that mobile number
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We cannot find an account with that mobile number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid email address or mobile phone number
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid email address or mobile phone number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We cannot find an account with that email address
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We cannot find an account with that email address')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked for security purposes.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your account has been locked for security purposes.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // For your security, we need you to reset the password on your account.
        if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'For your security, we need you to reset the password on your account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
            * There is another Amazon account with the e-mail ... but with a different password.
            * The e-mail address has already been verified by this other account and only one account can be active
            * for an e-mail address. The password you signed in with is associated with an unverified account.
            */
        if (($message = $this->http->FindSingleNode("//p[contains(text(), 'There is another Amazon account with the e-mail')]"))
            || ($message = $this->http->FindSingleNode("//div[@id = 'ap_email_verify_lockout_warn_box']/p"))) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The information you supplied was reviewed by Amazon but we cannot remove the hold on your account at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Document submission required
        if ($this->http->FindSingleNode("//h4[
                contains(text(), 'Document submission required')
                or contains(text(), 'Account on hold temporarily')
            ]")
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Amazon account deactivated')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // Password assistance
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Password assistance')]") && $this->http->ParseForm("forgotPassword")) {
            throw new CheckException("Account has been locked", ACCOUNT_LOCKOUT);
        }
        // Your Amazon account is locked and order(s) are on hold.
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Your Amazon account is locked and order(s) are on hold.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        $message = $this->http->FindSingleNode('//p[@class = "a-spacing-none" and 
                contains(., "Please set a new password for your account that you have not used elsewhere. We\'ll send a One Time Password (OTP) to authenticate this change.")
                or contains(., "Please set a new password for your account that you have not used elsewhere. We\'ll email you a One Time Password (OTP) to authenticate this change.")
                or contains(., "Please set a new password for your account that you have not used elsewhere. We\'ll send a One Time Password (OTP) to your mobile number to authenticate this change.")
            ]');
        // 3938344, 4860140
        $message2 = $this->http->FindSingleNode("//h1[contains(.,'Password assistance') or contains(.,'Passworthilfe')]
            /following-sibling::p[contains(.,'Enter the email address or mobile phone number associated with your Amazon account.')  
            or contains(.,'Geben Sie die E-Mail-Adresse oder Mobiltelefonnummer ein, die mit Ihrem Amazon-Konto verbunden ist.')]");

        if ($message || $message2) {
            throw new CheckException("Password reset required", ACCOUNT_INVALID_PASSWORD);
        }

        // Enter a valid email or mobile number
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Enter a valid email or mobile number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // This site can’t be reached
        if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")) {
            $this->DebugInfo = "This site can’t be reached";

            throw new CheckRetryNeededException(5, 10);
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]"))

        return false;
    }

    private function findElement(WebDriverBy $by)
    {
        try {
            return $this->driver->findElement($by);
        } catch (WebDriverCurlException | NoSuchElementException | WebDriverException $e) {
            $this->logger->debug("error: {$e->getMessage()}");

            return false;
        }
    }
}
