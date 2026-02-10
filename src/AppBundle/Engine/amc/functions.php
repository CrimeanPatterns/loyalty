<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerAmc extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'amcRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.amctheatres.com/amcstubs/dashboard', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // INVALID EMAIL ADDRESS
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("INVALID EMAIL ADDRESS", ACCOUNT_INVALID_PASSWORD);
        }
        // INVALID PASSWORD
        if (strlen($this->AccountFields['Pass']) < 8) {
            throw new CheckException("INVALID PASSWORD", ACCOUNT_INVALID_PASSWORD);
        }

        try {
            $this->http->removeCookies();

            $this->driver->manage()->window()->maximize();
            $this->http->GetURL("https://www.amctheatres.com/amcstubs/dashboard");

            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Email"]'), 5);
            $this->saveResponse();

            if (!$loginInput) {
                $loginFormBtn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign In to AMC Stubs Account"]'), 0);

                if (!$loginFormBtn) {
                    return $this->checkErrors();
                }

                $loginFormBtn->click();
            }

            $this->saveResponse();

            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Email"]'), 5);
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Password"]'), 0);

            if (!$loginInput || !$passwordInput) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();

            $loginBtn = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "Login--Submission-buttons")]//button[not(@disabled) and contains(text(), "Sign In")]'), 5);

            if (!$loginBtn) {
                return $this->checkErrors();
            }

            $loginBtn->click();

            sleep(3); // handling incorrect click

            $loginBtn = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "Login--Submission-buttons")]//button[not(@disabled) and contains(text(), "Sign In")]'), 5);

            $this->saveResponse();

            if ($loginBtn) {
                $this->logger->debug('retry click');
                $loginBtn->click();
            }

            return true;
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3);
        }
    }

    public function checkConnectionErrors()
    {
        $this->logger->notice(__METHOD__);
        // retries
        if (
            strstr($this->http->Error, 'Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to graph.amctheatres.com:443')
            || $this->http->Response['code'] == 406
        ) {
            // $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(4, 7);
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // provider error
        if (
            $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")
            || $this->http->FindPreg("/The service is unavailable\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message =
            $this->http->FindSingleNode("//span[contains(text(), 'Thank you for your patience as we perform some planned maintenance on our website and app.')]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindSingleNode("//body[contains(text(), 'The requested URL was rejected. Please consult with your administrator.')]")) {
            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "Form--error-message")]/text() | //div[@role="alert" and contains(@class, "ErrorMessageAlert")]/p/text()')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "The information you entered doesn't match what we have on file. Please check the information you entered or create a new AMC Stubs Account.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            if ($message === 'Invalid form submission.') {
                throw new CheckRetryNeededException(3);
            }

            return false;
        }

        if ($message = $this->http->FindSingleNode('//h4[contains(text(), "Account Locked")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        try {
            $this->http->GetURL("https://www.amctheatres.com/amcstubs/account");
            $this->saveResponse();

            $firstName = $this->http->FindSingleNode('//input[@name="firstName"]/@value');
            $lastName = $this->http->FindSingleNode('//input[@name="lastName"]/@value');
            // Name
            $this->SetProperty('Name', beautifulName("$firstName $lastName"));

            $this->http->GetURL("https://www.amctheatres.com/amcstubs/dashboard");
            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"member-info")]/img'), 10);
            $this->saveResponse();

            $statusText = $this->http->FindSingleNode('//div[contains(@class,"member-info")]/img/@alt');
            $status = $this->http->FindPreg('/Stubs\s([A-z]+)/', false, $statusText);

            if (!$status) {
                $status = $this->http->FindPreg('/A M C (.*) Member/', false, $statusText);
            }

            // Status
            $this->SetProperty('Status', $status);

            $memberSinceRaw = $this->http->FindSingleNode('//div[contains(@class,"member-info__dates")]//h4[@class="member-info__info-callout"]/text()');

            if (strtolower($status) == 'insider' && isset($memberSinceRaw)) {
                // If user in status "insider" then date is MemberSince else date is StatusExpiration
                $memberSince = DateTime::createFromFormat('F d, Y', $memberSinceRaw);
                // Member since
                $this->SetProperty('MemberSince', $memberSince->getTimestamp());
            }

            $this->http->GetURL("https://www.amctheatres.com/amcstubs/wallet");
            $this->waitForElement(WebDriverBy::xpath('//div[@class="StubsCard-Info"]/span[contains(@class, "italic")]'), 10);
            $this->saveResponse();

            if (strtolower($status) != 'insider') {
                // Expiration date
                $this->SetExpirationDateNever();
                $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');

                $statusExpirationRaw = $this->http->FindSingleNode('//div[@class="StubsCard-Info"]/span[contains(@class, "italic")]/text()', null, false, '/\w+\s\d+,\s\d+/');

                if (isset($statusExpirationRaw)) {
                    // If user in status "insider" then date is MemberSince else date is StatusExpiration
                    $statusExpiration = DateTime::createFromFormat('F d, Y', $statusExpirationRaw);
                    // Status expiration
                    $this->SetProperty('StatusExpiration', $statusExpiration->getTimestamp());
                }
            }

            // Number
            $this->SetProperty('Number', $this->http->FindSingleNode('//div[@class="StubsCard-Info"]/span[@class="Headline--eyebrow txt--gray--light"]/text()'));

            // Balance - points
            $this->SetBalance($this->http->FindSingleNode('//span[text()="Points Available"]/../span[@class="Headline--h4"]/text()'));

            // Points till next reward
            $this->SetProperty('UntilNextReward', $this->http->FindSingleNode('//span[text()="Points to Next $5 Reward"]/../span[@class="Headline--h4"]/text()'));

            $this->http->GetURL('https://www.amctheatres.com/amcstubs/rewards');
            $this->waitForElement(WebDriverBy::xpath('//div[@class="MyAMCDashboard-extra-info"]//span[contains(@aria-label, "USD")]'), 10);
            $this->saveResponse();

            // total rewards available
            $rewardBalance = $this->http->FindSingleNode('//div[@class="MyAMCDashboard-extra-info"]//span[contains(@aria-label, "USD")]', null, false, '/[\d.]+/');
            $amcRewards = [
                'Code'        => 'amcRewards',
                'DisplayName' => "Rewards",
                'Balance'     => $rewardBalance,
            ];

            $this->AddSubAccount($amcRewards);

            $pendingPoints = $this->http->FindSingleNode('//span[text()="points pending"]/../h3/text()');

            if ($pendingPoints) {
                $this->SetProperty('PendingPoints', $pendingPoints);
            }
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        try {
            $logoutItemXpath = '//button[text()="Sign Out"]';
            $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), 10);
            $this->saveResponse();

            if ($this->http->FindSingleNode($logoutItemXpath)) {
                return true;
            }

            return false;
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3);
        }
    }
}
