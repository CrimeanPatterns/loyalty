<?php

class TAccountCheckerBobs extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;
        $this->useFirefox();
        $this->setKeepProfile(true);
        $this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.bobstores.com/signin");

//        if ($this->waitForElement(WebDriverBy::xpath('//h6[contains(text(), "Personal Data")]'), 0)) {
//            $this->http->GetURL("https://www.bobstores.com/logout");
//            sleep(1);
//            $this->http->GetURL("https://www.bobstores.com/account");
//        }

        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password' and not(contains(@class, 'disabled')) and not(@disabled)]"), 25);
        $this->saveResponse();
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email' and not(contains(@class, 'disabled')) and not(@disabled)]"), 5);
        $submitButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$submitButton) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $this->saveResponse();
        $login->click();
        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $pass->click();
        $pass->clear();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $submitButton->click();

        /*
        $login = $this->http->FindSingleNode("//form[@id = 'dwfrm_login']//input[contains(@name, 'dwfrm_login_username')]/@name");
        if (!$this->http->ParseForm("dwfrm_login") || !isset($login))
            return $this->checkErrors();
        // enter the login and password
        $this->http->SetInputValue($login, $this->AccountFields["Login"]);
        $this->http->SetInputValue("dwfrm_login_password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("dwfrm_login_login", 'Login');
        */

        return true;
    }

    public function checkErrors()
    {
        // Our new and improved website will be up very shortly.
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'Our new and improved website will be up very shortly.')
                or contains(text(), 'Pardon our mess while we make some planned updates to our website.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(2);
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 30;

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            // look for logout link
            $menu = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "My Account")]'), 0);

            if (!$menu) {
                return true;
            }
            $menu->click();
            $logout = $this->waitForElement(WebDriverBy::xpath('//li[.//span[contains(normalize-space(), "Log Out")]]'), 0);

            if ($logout) {
                return true;
            }

            // Invalid credentials
            if ($error = $this->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "alert-danger")]/p
                    | //div[contains(text(), "Your email or password didn\'t match our records.")]
                '), 0)
            ) {
                $message = $error->getText();

                if (
                    strstr($message, 'User not found')
                    || strstr($message, 'Your email or password didn\'t match our records.')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'Due to a system upgrade, you must reset your password before you can access your account.')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            $this->saveResponse();
            $time = time() - $startTime;
        }// while ($time < $sleep)

        /*
        if (!$this->http->PostForm())
            return $this->checkErrors();
        // Sorry, the information you provided does not match our records.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, the information you provided does not match our records.')]"))
        	throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Due to a system upgrade, you will need to reset your password.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Due to a system upgrade, you will need to reset your password.')]"))
        	throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // login successful
        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href"))
            return true;
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.bobstores.com/account/rewards");
        $this->waitForElement(WebDriverBy::xpath('//p[contains(., "Enter your phone number or zip code so we can link your new online account with your existing rewards membership.")] | //p[contains(text(), "Your member ID is")]'), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//p[contains(., "Enter your phone number or zip code so we can link your new online account with your existing rewards membership.")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Number
        $this->SetProperty("Number", beautifulName($this->http->FindSingleNode('//p[contains(text(), "Your member ID is")]', null, true, "/Your member ID is\s*(\d+)/")));

        if (
            isset($this->Properties['Number'])
            && $this->http->FindSingleNode('//h6[contains(text(), "You’re enrolled in the Bob’s Stores reward program!")]')
        ) {
            $this->SetBalanceNA();
        }

        $this->http->GetURL("https://www.bobstores.com/rewards-account");
        sleep(2);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[normalize-space()='Name']/following-sibling::p")));

        // To Next Certificate
        $this->SetProperty("ToNextCertificate", $this->http->FindSingleNode("//p[@class = 'points-away']", null, true, "/You\'re\s*just\s*([\d]+)\s*points\s*away\s*from\s*earning/ims"));
        // Next Reward
        $this->SetProperty("NextCertificate", $this->http->FindSingleNode("//p[@class = 'points-away']", null, true, "/earning\s*your\s*next\s*([^\!]+)/ims"));
        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Points Balance:')]/span"));

        // SubAccounts - Rewards

        $nodes = $this->http->XPath->query("//div[@id = 'rewardsTable' and @class = 'desktop']/div[not(contains(@class, 'header'))]");
        $this->http->Log("Total nodes found: " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                // Reward #
                $code = $this->http->FindSingleNode('div[1]', $nodes->item($i));
                // Amount
                $balance = $this->http->FindSingleNode('div[2]', $nodes->item($i), true, "/[\d\.\,]+/ims");
                // Status
                $status = $this->http->FindSingleNode('div[4]', $nodes->item($i));
                // Expires
                $exp = $this->http->FindSingleNode('div[3]', $nodes->item($i));

                $subAccounts[] = [
                    'Code'           => 'bobsRewards' . $code,
                    'DisplayName'    => "Reward # " . $code,
                    'Balance'        => $balance,
                    'ExpirationDate' => strtotime($exp),
                    'RewardStatus'   => $status,
                ];
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }// if ($nodes->length > 0)
    }
}
