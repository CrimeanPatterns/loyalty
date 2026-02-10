<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSeleniumDicks extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [
        'partner_key' => 'myaccount_ui',
        'secret_key'  => 'ESETXC1V1Zim2jwUL1lw',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

        if ($this->attempt > 0) {
            $this->setProxyGoProxies();
        } else {
            $this->setProxyBrightData();
        }

        $this->useFirefoxPlaywright(SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

        $this->seleniumOptions->addAntiCaptchaExtension = true;
        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();

        if (!isset($this->State['Fingerprint']) || $this->attempt > 0) {
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;

            $fp = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fp !== null) {
                $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                $this->State['Fingerprint'] = $fp->getFingerprint();
                $this->State['UserAgent'] = $fp->getUseragent();
                $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
            }
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }

        if (isset($this->State["Resolution"])) {
            $this->setScreenResolution($this->State["Resolution"]);
        }

        if (isset($this->State['UserAgent'])) {
            $this->http->setUserAgent($this->State['UserAgent']);
        }
        */
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        try {
            try {
                $this->http->GetURL("https://www.dickssportinggoods.com/MyAccount/AccountSummary");
            } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("exception: " . $e->getMessage());
            }

            $this->waitForElement(WebDriverBy::xpath("//input[@id = 'email-input' or @id = 'email' or @id = 'username'] | //h1[contains(text(), 'Help us verify real visitors') or contains(text(), 'Due to GDPR regulations, our website is currently unavailable in your region.')] | //h1[contains(text(), 'Access Denied')] | //div[@data-em='Header_MyAccount_SignIn'] | //p[contains(text(), 're sorry to say that due to the General Data Protection Regulation,')]"), 5);
            $this->saveResponse();
            $this->hideOverlay();

            if ($signInBtn = $this->waitForElement(WebDriverBy::xpath("//div[@data-em='Header_MyAccount_SignIn']"), 0)) {
                $signInBtn->click();
            }

            if ($this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Help us verify real visitors')]"), 0)) {
                $this->logger->debug('waiting for recaptcha');

                $this->waitFor(function () {
                    return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
                }, 120);
                $this->saveResponse();

                if ($submitBtn = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Submit']"), 120)) {
                    $submitBtn->click();
                    $this->waitForElement(WebDriverBy::xpath("//input[@id = 'email-input' or @id = 'email' or @id = 'username'] | //h1[text() = 'Access Denied']"), 20);
                }
            }
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();

            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $this->markProxyAsInvalid();
                $this->callRetries();
            }
        }

        if (
            $this->http->FindPreg("/<(?:h1|span|p)[^>]*>(?:This site can’t be reached|page isn\’t working|There is something wrong with the proxy server, or the address is incorrect\.)<\/(?:h1|span|p)>/")
            || $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry to say that due to the General Data Protection Regulation, visitors from your location are unable to browse our web store.")] | //h1[contains(text(), "Access Denied") or contains(text(), "Due to GDPR regulations, our website is currently unavailable in your region.")] | //p[contains(text(), \'re sorry to say that due to the General Data Protection Regulation,\')]')
        ) {
            $this->markProxyAsInvalid();
            $this->DebugInfo = "This site can’t be reached";
            $this->callRetries();
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'email-input' or @id = 'email' or @id = 'username']"), 5);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password-input' or @id = 'signinPass' or @id= 'password']"), 0);
        $this->hideOverlay();
        $this->saveResponse();

        if (empty($login) || empty($pass)) {
            $this->logger->error('something went wrong');

            if ($this->http->FindSingleNode('//a[contains(text(), "Sign Out")]')) {
                return $this->loginSuccessful();
            }

            if ($this->http->FindPreg("/<script>\s*var varResponsive = \"true\";\s*<\/script>/")) {
                $this->callRetries();
            }

            return $this->checkErrors();
        }

        $this->logger->debug("login");
        $login->click();
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("pass");
        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->logger->debug("click");

        $btn = $this->waitForElement(WebDriverBy::xpath("//div[not(contains(@class, 'hidden')) and not(@aria-hidden = 'true')]/button[@type = 'submit']"), 0);
        $this->saveResponse();

        if (empty($btn)) {
            $this->logger->error('something went wrong');

            return $this->checkErrors();
        }

        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        sleep(3);
        $result = $this->waitForElement(WebDriverBy::xpath("//span[@data-testid='point-balance'] | //a[contains(text(), 'ScoreCard')] | //p[contains(@id, 'validation-alert')] | //span[@id = \"login-error-message-text\"] | //span[@data-error-code = 'wrong-email-credentials'] | //div[@data-error-code = 'password-breached']/p | //h1[contains(text(), 'Access Denied') or contains(text(), 'ACCESS DENIED')]"), 10);
        $this->saveResponse();

        if ($this->http->BodyContains('<div class="sec-container">', false)
            || $this->http->BodyContains('<div id="sec-container">', false)
        ) {
            $this->saveResponse();
            $result = $this->waitForElement(WebDriverBy::xpath("//span[@data-testid='point-balance'] | //a[contains(text(), 'ScoreCard')]"), 15);
            $this->saveResponse();
        }

        if (!$result && ($btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'sign-in-button']"), 0))) {
            $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')] | //p[contains(@id, 'validation-alert')] | //span[@id = \"login-error-message-text\"] | //span[@data-error-code = 'wrong-email-credentials']"), 10);
            $this->saveResponse();
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $this->http->FindSingleNode('//p[contains(@id, "validation-alert")] | //span[@id = "login-error-message-text"] | //span[@data-error-code = "wrong-email-credentials" and not(contains(@class, "screen-reader-only"))] | //div[@data-error-code="password-breached"]/p');

        if ($message) {
            $this->logger->error($message);

            if (
                $message == 'The specified logon ID or password are not correct. Verify the information provided and log in again.'
                || $message == 'Wrong email or password'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'To sign in, please enter a valid email address and password.'
            ) {
                if ($btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'sign-in-button' or @id = 'btn-login']"), 0)) {
                    try {
                        $btn->click();
                    } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                        $this->logger->error("ElementClickInterceptedException: " . $e->getMessage());
                        sleep(20);
                        $this->saveResponse();

                        try {
                            $btn->click();
                        } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                            $this->logger->error("ElementClickInterceptedException: " . $e->getMessage());
                            sleep(40);
                            $this->saveResponse();
                            $btn->click();
                        }
                    }

                    $result = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')]"), 15);
                    $this->saveResponse();

                    if ($result) {
                        $this->markProxySuccessful();

                        return true;
                    }
                }
                /*
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                */
                throw new CheckRetryNeededException(2, 3, $message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'The user account is disabled. Contact your site administrator regarding your access.'
                || $message == 'You have exceeded the number of password attempts. Please reset your password.'
                || $message == 'We have detected a potential security issue with this account. To protect your account, we have prevented this login. Please reset your password to proceed'
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            return false;
        }

        // AccountID: 5270214, 5683308
        if ($this->http->FindSingleNode('//h1[contains(text(), "TO SPEAK WITH A CUSTOMER SERVICE REPRESENTATIVE")]')) {
            throw new CheckException("ACCESS DENIED", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->markProxyAsInvalid();
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = 'Access Denied';

            throw new CheckRetryNeededException(4, 0, "Access Denied", ACCOUNT_PROVIDER_ERROR);

            throw new CheckException("Access Denied", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'We are working on the problem. ')]")) {
            $this->markProxyAsInvalid();
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = '[Block]: Please try accessing the site again after 12 hours';

            throw new CheckRetryNeededException(4, 0, "Oops, Something Went Wrong. We are working on the problem. Please try accessing the site again after 12 hours. We appreciate your patience and understanding.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->info('Profile', ['Header' => 3]);
        $goToProfileLink = $this->waitForElement(WebDriverBy::xpath('//a[@routerlinkactive="active" and contains(text(), "Personal Information")]'), 10);

        if (!$goToProfileLink) {
            $this->saveResponse();

            return;
        }

        $goToProfileLink->click();

        // ScoreCard Member
        $number = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "ScoreCard Number")]/following-sibling::p'), 10);
        $this->saveResponse();

        if (!$number) {
            return;
        }

        $this->SetProperty("ScoreCardNumber", $number->getText());
        // Name
        $this->SetProperty("Name", beautifulName($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Full Name")]/following-sibling::p'), 0)->getText()));

        // Available Rewards
        $this->logger->info('Available Rewards', ['Header' => 3]);
        $goToRewardsBtn = $this->waitForElement(WebDriverBy::xpath('//a[@routerlinkactive="active" and contains(text(), "ScoreCard")]'), 0);

        if (!$goToRewardsBtn) {
            $this->saveResponse();

            return;
        }

        $goToRewardsBtn->click();

        try {
            $b = $this->waitForElement(WebDriverBy::xpath('//*[@class = "progress-points"]'), 10);
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(2);
            $b = $this->waitForElement(WebDriverBy::xpath('//*[@class = "progress-points"]'), 10);
        }
        $this->saveResponse();

        if (!$b) {
            $goToRewardsBtn = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "ScoreCard")]'), 0);

            if (!$goToRewardsBtn) {
                return;
            }

            $goToRewardsBtn->click();
            $this->waitForElement(WebDriverBy::xpath('//*[@class = "progress-points"]'), 10);
            $this->saveResponse();
        }

        // Balance - Point Balance
        $this->SetBalance($this->http->FindSingleNode('//*[@class = "progress-points"]'));
        // Available Rewards
        $rewards = $this->http->XPath->query('//ul[contains(@class, "reward-list") and not(contains(@class, "inactive"))]/li[not(.//span[contains(text(), "REDEEMED")]) and not(.//span[contains(text(), "EXPIRED")])]');
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            // Online Code
            $code = $this->http->FindSingleNode('.//span[contains(text(), "Code:")]/span', $reward);
            $balance = $this->http->FindSingleNode('.//span[contains(text(), "Reward") or contains(text(), "REWARD")]', $reward, true, "/(.+)\s*Reward/ims");
            // Expiration Date
            $expirationDate = $this->http->FindSingleNode('.//span[contains(text(), "EXPIRES IN") or contains(text(), "Expires on") or contains(text(), "Expires in")]', $reward, true, "/EXPIRES\s*.N:?\s*(.+)/ims");
            $this->logger->debug($expirationDate);

            if (isset($code, $balance) && ($exp = strtotime($expirationDate))) {// "+{$expirationDate} -1 day"
                $this->AddSubAccount([
                    'Code'           => 'dicksAvailableRewards' . $code,
                    'DisplayName'    => "Reward {$code}",
                    'Balance'        => $balance,
                    'ExpirationDate' => $exp,
                    //                    'BarCode'        => ArrayVal($reward, 'storeCode', null),
                    //                    "BarCodeType"    => BAR_CODE_CODE_128,
                ]);
            }
        }// foreach ($rewards as $reward)

        // Expiration Date  // refs #6115
        if ($this->Balance <= 0) {
            return;
        }

        $points = $this->Balance;
        $this->logger->info('Expiration Date', ['Header' => 3]);

        // hide overlay
        $this->driver->executeScript('var overlay = document.getElementById(\'homr-modal\'); if (overlay) overlay.style.display = "none";');

        $spinner = $this->driver->executeScript('return $("#myaccount-container").find("common-progress-spinner > svg > circle").length > 0');

        if (!empty($spinner)) {
            $this->logger->debug("spinner: " . $spinner, ['pre' => true]);
            sleep(3);
        }

        $i = 0;

        while (
            ($viewMore = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "VIEW MORE")]'), 2))
            && $i < 15
        ) {
            $i++;

            try {
                $this->driver->executeScript('document.querySelector(\'div.view-more\').scrollIntoView({block: "end"});');
                $this->saveResponse();
                $viewMore->click();
                sleep(2);
                $this->saveResponse();
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage());
                sleep(2);
                $this->saveResponse();
            }
        }
        $transactions = $this->http->XPath->query('//div[contains(@class, "point-history")]');
        $this->logger->debug("Total {$transactions->length} transactions were found");

        for ($i = 0; $i < $transactions->length; $i++) {
            $historyPoints = $this->http->FindSingleNode('div[contains(@class, "points")]', $transactions->item($i), true, "/(.+)\s+point/ims");
            $historyPoints = str_replace(',', '', $historyPoints);
            $this->logger->debug("Node # $i - Balance: $points, HistoryPoints: $historyPoints");

            if ($historyPoints > 0) {
                $points -= $historyPoints;
            }
            $this->logger->debug("Node # $i - Balance: $points / round: " . round($points, 2));

            if ($points <= 0) {
                $date = $this->http->FindSingleNode('.//span[contains(@class, "effective-date")]', $transactions->item($i));

                if (isset($date)) {
                    $this->SetProperty("EarningDate", $date);
                    $this->SetExpirationDate(strtotime("+1 year", strtotime($date)));
                }
                // Expiring balance
                $this->SetProperty("ExpiringBalance", ($points + $historyPoints));

                break;
            }// if ($points <= 0)
        }// for ($i = 0; $i < $historyPoints->length; $i++)
    }

    /*
    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.dickssportinggoods.com/MyAccount/AccountSummary", [], 20);
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeOutException exception: ".$e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//span[@data-testid='point-balance'] | //a[contains(text(), 'ScoreCard') and not(contains(text(), 'Terms'))]")) {
            return true;
        }

        return false;
    }

    private function callRetries()
    {
        $this->logger->notice(__METHOD__);

        throw new CheckRetryNeededException(4, 0);
    }

    private function hideOverlay()
    {
        $this->logger->notice(__METHOD__);
//        $this->driver->executeScript("$('div[class = \"dialog-backdrop active\"]').remove()");

        $signInXpath = "//a[contains(text(), 'Sign In')][1] | //a[@title = 'Sign In']";

        if ($close = $this->waitForElement(WebDriverBy::xpath('//div[@aria-label="close"]'), 0)) {
            $close->click();
            sleep(1);
            $this->saveResponse();
            $signIn = $this->waitForElement(WebDriverBy::xpath($signInXpath), 5);
        }

        $this->saveResponse();
        $this->driver->executeScript("var overlay1 = document.getElementsByClassName('dialog-backdrop active')[0]; if (overlay1) overlay1.hidden = true;");
        $this->driver->executeScript("var overlay2 = document.getElementsByClassName('dialog-backdrop active')[1]; if (overlay2) overlay2.hidden = true;");
        $this->driver->executeScript("var closeBtn = document.querySelector('div[aria-label=\"close\"]'); if (closeBtn) closeBtn.click();");
        sleep(1);
        $this->saveResponse();
    }
}
