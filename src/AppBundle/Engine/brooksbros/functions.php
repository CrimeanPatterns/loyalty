<?php

class TAccountCheckerBrooksbros extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const ACCOUNT_OVERVIEW_LINK = "https://www.brooksbrothers.com/on/demandware.store/Sites-brooksbrothers-Site/default/Account-Show";

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "brooksbrosCertificate")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public static function GetAccountChecker($accountInfo)
    {
        if ($accountInfo['Login2'] != 'ShoppingCard') {
            require_once __DIR__ . "/TAccountCheckerBrooksbrosSelenium.php";

            return new TAccountCheckerBrooksbrosSelenium();
        }// if ($accountInfo['Login2'] != 'ShoppingCard')
        else {
            return new TAccountCheckerBrooksbros();
        }
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields, $values);
        $fields['Login2']['Options'] = [
            ""             => "Select your card type",
            'CreditCard'   => 'Credit card',
            'ShoppingCard' => 'Shopping card',
        ];
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'ShoppingCard') {
            $redirectURL = self::ACCOUNT_OVERVIEW_LINK;
        } else {
            $redirectURL = 'https://citiretailservices.citibankonline.com/RSnextgen/svc/launch/index.action?siteId=PLCN_BROOKSBROTHERS';
        }

        $arg["RedirectURL"] = $redirectURL;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
//        $this->useFirefox();
//        $this->setKeepProfile(true);

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        return false;

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::ACCOUNT_OVERVIEW_LINK, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::ACCOUNT_OVERVIEW_LINK);

        /*
        $this->http->SetInputValue("loginEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("loginPassword", $this->AccountFields['Pass']);
        $this->http->SetInputValue("loginRememberMe", "true");
        */

        $this->waitForElement(WebDriverBy::xpath('//input[@name = \'loginEmail\'] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe'), 10);

        if ($this->cloudFlareWorkaround()) {
            $this->saveResponse();
        }

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'loginEmail']"), 7);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'loginPassword']"), 3);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);

        $this->driver->executeScript("try { $('#attentive_overlay').hide(); } catch(e) {}");
        $this->driver->executeScript("try { $('#closeIconContainer').click(); } catch(e) {}");

        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        // remember me
        $this->driver->executeScript("document.querySelector('input[name = \"loginRememberMe\"]').checked = true;");

        $this->logger->debug("set login");
        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set pass");
        $pass->click();
        $pass->clear();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->logger->debug("click btn");

        sleep(rand(5, 30));
        $this->saveResponse();
        $this->logger->debug("Click btn");
        $this->driver->executeScript("$('button:contains(\"Login\")').click();");
//        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "Logout")] | //*[contains(@class, "invalid-feedback set--visible")]'), 10);
        $this->saveResponse();
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();
        $redirectUrl = $response->redirectUrl ?? null;

        if ($redirectUrl) {
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        $message =
            $this->http->FindSingleNode('//*[contains(@class, "invalid-feedback set--visible")]')
            ?? $response->error[0]
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "Invalid login or password. Remember that password is case-sensitive. Please try again."
                || $message == "Please enter an email address."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h3[contains(text(), "First Name")]/following-sibling::div') . " " . $this->http->FindSingleNode('//h3[contains(text(), "Last Name")]/following-sibling::div')));

        $this->http->GetURL("https://www.brooksbrothers.com/on/demandware.store/Sites-brooksbrothers-Site/default/Rewards-Show");
        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "Points Balance")]/following-sibling::div/div[1]'));
        // Member
        $this->SetProperty("Number", $this->http->FindSingleNode('//div[contains(@class, "member-id")]', null, true, "/\#\s*(\d+)/"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode('//div[contains(@class, "rewards-total__info")]//div/div[contains(@class, "tier")]'));
        // Your Available Rewards
        $this->SetProperty("BrooksRewards", $this->http->FindSingleNode('//div[contains(text(), "Your Available Rewards")]/following-sibling::div'));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//div[contains(@class, "since")]', null, true, "/since\s*([^<]+)/"));
        // ... Points until your next $10 reward
        $this->SetProperty("NeededToNextReward", $this->http->FindSingleNode('//div[contains(@class, "rewards-total__next-reward")]', null, true, "/([\d]+)\s*Points? until/ims"));

        // Rewards
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "account-rewards__pagination") and normalize-space() = "Showing 0 of 0"]')) {
            $this->logger->notice("Rewards: {$message}");
        }
        $certificates = $this->http->XPath->query("//div[contains(@class, 'account-rewards__cert')]/div[contains(@class, 'account-rewards__cert-inner')]");
        $this->logger->debug("Total {$certificates->length} certificates were found");

        foreach ($certificates as $certificate) {
            // Certificate Number
            $number = $this->http->FindSingleNode('.//div[strong[contains(text(), "Certificate:")]]', $certificate, null, "/\:\s*#([^ ]+)/");
            $exp = $this->http->FindSingleNode('.//div[strong[contains(text(), "Expires:")]]', $certificate, null, "/\:\s*([^<]+)/");
            $balance = $this->http->FindSingleNode('.//div[contains(@class, "account-rewards__amount")]', $certificate);
            $this->AddSubAccount([
                'Code'           => 'brooksbrosCertificate' . $number,
                'DisplayName'    => "Certificate #{$number}",
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($exp, false),
            ]);
        }// foreach ($certificates as $certificate)

        // not a member
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->FindSingleNode('//div[@id = "primary"]//a[@id = "btnEnrollNow" and contains(text(), "Enroll Now")]')) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(@href, "Logout")]')) {
            return true;
        }

        return false;
    }

    /* @deprecated */
    private function cloudFlareWorkaround()
    {
        $this->logger->notice(__METHOD__);

        $res = false;

        if ($verify = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human']"), 0)) {
            $verify->click();
            $res = true;
        }

        if ($iframe = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'turnstile-wrapper']//iframe"), 5)) {
            $this->driver->switchTo()->frame($iframe);
            $this->saveResponse();

            if ($captcha = $this->waitForElement(WebDriverBy::xpath("//label[@class = 'ctp-checkbox-label']/map/img | //label[@class = 'ctp-checkbox-label' and input[@type = 'checkbox']]"), 10)) {
                $this->saveResponse();
                $captcha->click();
                $this->logger->debug("delay -> 15 sec");
                $this->saveResponse();
                sleep(15);

                $this->driver->switchTo()->defaultContent();
                $this->saveResponse();
                $res = true;
            }
        }

        return $res;
    }
}
