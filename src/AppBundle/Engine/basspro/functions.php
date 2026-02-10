<?php

// refs #1821

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBasspro extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
        $this->setKeepProfile(true);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
        /*
        $this->useChromium();
//        $this->useFirefox();
//        $this->setKeepProfile(true);
        $this->disableImages();
        $this->keepCookies(false);
        */
        $this->http->saveScreenshots = true;
        $this->usePacFile(false);

        $this->http->SetProxy($this->proxyReCaptcha());
//        $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
    }

    /**
     * Not Working.
     *
     * @deprecated
     *
     * @return bool
     */
    public function LoadLoginForm2()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.basspro.com/');

        if ($this->http->Response["code"] != 200) {
            return false;
        }
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded",
            "Accept"           => "*/*",
        ];

        $data = [
            "applyIC"              => "N",
            "storeId"              => "10151",
            "catalogId"            => "10051",
            "reLogonURL"           => "https://www.basspro.com/shop/en",
            "g-recaptcha-response" => "",
            "myAcctMain"           => "",
            "errorViewName"        => "AjaxOrderItemDisplayView",
            "previousPage"         => "",
            "returnPage"           => "",
            "URL"                  => "https://www.basspro.com/shop/AjaxLogonForm?myAcctMain=1&catalogId=10051&langId=-1&storeId=10151",
            "logonId"              => strtoupper($this->AccountFields['Login']),
            "logonPassword"        => $this->AccountFields['Pass'],
            "rememberMe"           => "true",
            "widgetId"             => "Header_GlobalLogin",
            "requesttype"          => "ajax",
        ];

        $this->sendSensorData();

        $this->http->PostURL("https://www.basspro.com/shop/AjaxLogon", $data);
//
//        if (!$this->http->ParseForm("Header_GlobalLogin_GlobalLogon")) {
//            return false;
//        }
//        $this->http->FormURL = 'https://www.basspro.com/shop/AjaxLogon';
//        $this->http->SetInputValue("logonId", $this->AccountFields['Login']);
//        $this->http->SetInputValue("logonPassword", $this->AccountFields['Pass']);
//        $this->http->SetInputValue("rememberMe", "true");
        $this->http->PostForm($headers);

        return false;

//        $loginInput = $this->waitForElement(WebDriverBy::id('WC_AccountDisplay_FormInput_logonId_In_Logon_1'), 10);
//        $passwordInput = $this->waitForElement(WebDriverBy::id('WC_AccountDisplay_FormInput_logonPassword_In_Logon_1'), 0);
//        $button = $this->waitForElement(WebDriverBy::id("WC_AccountDisplay_links_2"), 0);
//        if (!$loginInput || !$passwordInput || !$button) {
//            $this->http->saveResponse();
//            return $this->checkErrors();
//        }
//        $loginInput->sendKeys($this->AccountFields['Login']);
//        $passwordInput->sendKeys($this->AccountFields['Pass']);
//        $this->driver->executeScript('setTimeout(function(){
//                delete document.$cdc_asdjflasutopfhvcZLmcfl_;
//                delete document.$cdc_asdjflasutopfhvcZLawlt_;
//            }, 500)');
//        $button->click();

        return true;
    }

    public function removePopups()
    {
        $this->logger->notice(__METHOD__);

        if ($button = $this->waitForElement(WebDriverBy::xpath('//a[@onclick="SS248Yes()"]'), 0)) {
            $button->click();
        }

        $this->logger->debug("remove popup2");
        $this->driver->executeScript("var popup2 = document.getElementsByClassName('bluecoreOverlay'); if (popup2 && popup2.length) popup2[0].remove();");
        $this->logger->debug("remove popup3");
        $this->driver->executeScript("var popup3 = document.getElementsByClassName('bluecoreMiddleCenterPopup'); if (popup3 && popup3.length) popup3[0].remove();");
        $this->logger->debug("remove popup 4");
        $this->driver->executeScript("var popup4 = document.querySelector('#attentive_overlay'); if (popup4) popup4.remove();");
        $this->saveResponse();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();

        $loginViaMainPAge = true;

        if ($loginViaMainPAge) {
            $this->http->GetURL('https://www.basspro.com/');
            $this->waitForElement(WebDriverBy::xpath('//div[a[contains(@id, "Header_GlobalLogin_signInQuickLink")]] | //h1[contains(text(), \'Access Denied\')]'), 10);
            $this->saveResponse();
            $loginLink = $this->waitForElement(WebDriverBy::xpath('//div[a[contains(@id, "Header_GlobalLogin_signInQuickLink")]]'), 0);

            if (!$loginLink) {
                return $this->checkErrors();
            }
            $this->logger->debug("remove popup");
            $this->driver->executeScript("var popup = document.getElementById('monetate_lightbox'); if (popup) popup[0].remove();");
            sleep(3);
            $this->saveResponse();
            $this->removePopups();

            $loginLink->click();
        } else {
            $this->http->GetURL('https://www.basspro.com/shop/AjaxLogonForm?langId=-1&catalogId=3074457345616676768&storeId=715838534');
        }

        $this->saveResponse();

        $this->logger->debug("remove popupBlueCore");
//        $this->driver->executeScript("var popupBlueCore = document.getElementsByClassName('bluecoreOverlay'); if (popupBlueCore) popupBlueCore[0].remove();");

        if ($loginViaMainPAge) {
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Header_GlobalLogin_WC_AccountDisplay_FormInput_logonId_In_Logon_1"]'), 10);
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Header_GlobalLogin_WC_AccountDisplay_FormInput_logonPassword_In_Logon_1"]'), 0);
            $button = $this->waitForElement(WebDriverBy::xpath('//a[@id = "Header_GlobalLogin_WC_AccountDisplay_links_2"]'), 0);
        } else {
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "WC_AccountDisplay_FormInput_logonId_In_Logon_1"]'), 10);
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "WC_AccountDisplay_FormInput_logonPassword_In_Logon_1"]'), 0);
            $button = $this->waitForElement(WebDriverBy::xpath('//a[@id = "WC_AccountDisplay_links_2"]'), 0);
        }
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }
        sleep(2);
        $this->removePopups();

//        $mover = new MouseMover($this->driver);
//        $mover->logger = $this->logger;
//        $mover->duration = rand(40000, 60000);
//        $mover->steps = rand(20, 40);
//
//        $mover->moveToElement($loginInput);
//        $mover->click();
//        $cps = rand(10, 20);
//        $mover->sendKeys($loginInput, $this->AccountFields['Login'], $cps);
//        $this->saveResponse();
//
//        $mover->moveToElement($passwordInput);
//        $mover->click();
//        $this->saveResponse();
//        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], $cps);
//
//        usleep(rand(100000, 500000));
//        $mover->moveToElement($button);
//        $button->click();

        /*
        $this->logger->debug('set credentials');
        $injection = "
            var emailInput = $('#Header_GlobalLogin_WC_AccountDisplay_FormInput_logonId_In_Logon_1');
            var passwordInput = $('#Header_GlobalLogin_WC_AccountDisplay_FormInput_logonPassword_In_Logon_1');
            emailInput.val(\"{$this->AccountFields['Login']}\");
            sendEvent(emailInput.get(0), 'blur');
            passwordInput.val(\"".str_replace('\\', '\\\\', $this->AccountFields['Pass'])."\");
            sendEvent(passwordInput.get(0), 'blur');

            function sendEvent (element, eventName) {
                var event;

                if (document.createEvent) {
                    event = document.createEvent(\"HTMLEvents\");
                    event.initEvent(eventName, true, true);
                } else {
                    event = document.createEventObject();
                    event.eventType = eventName;
                }

                event.eventName = eventName;

                if (document.createEvent) {
                    element.dispatchEvent(event);
                } else {
                    element.fireEvent(\"on\" + event.eventType, event);
                }
            };
            $('#Header_GlobalLogin_WC_AccountDisplay_links_2').get(0).click();
        ";
        $this->logger->debug("injection");
        $this->driver->executeScript($injection);
        */
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->logger->debug("log in");
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# BassPro.com is currently undergoing maintenance
        if ($this->http->FindSingleNode("//img[contains(@src, 'site_down.jpg')]/@src")) {
            throw new CheckException('BassPro.com is currently undergoing maintenance', ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            || ($this->http->Response['code'] == 400 && $this->http->FindSingleNode('//title[normalize-space(.)="Invalid URL"]'))
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The store has encountered a problem processing the last request. Try again later.
        // If the problem persists, contact your site administrator.
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'The store has encountered a problem processing the last request')]
                | //h1[contains(text(), 'Service Unavailable')]
                | //h3[contains(text(), 'Basspro.com is currently down for maintenance')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $sleep = 40;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

            $logout = $this->waitForElement(WebDriverBy::xpath('//div[@class = "myaccount_desc_title"] | //div[@id = "firstName_initials"]'), 0);

            if ($logout) {
                return true;
            }
            // check errors
            if ($errorMessage = $this->waitForElement(WebDriverBy::xpath("
                    //*[@id='logonErrorMessage']
                    | //div[@id = 'bp-alert-error']//div[@id = 'bp-alert-textId']
                    | //span[contains(text(), 'Either the email or the password entered is incorrect.')]
                    | //h1[contains(text(), 'Access Denied')]
                    | //h2[contains(text(), 'Your current password has expired.')]
                "), 0)
            ) {
                $message = $errorMessage->getText();
                $this->saveResponse();
                // You do not have the proper authority to sign in. Contact the store for further information.
                if (
                    strpos($message, 'You do not have the proper authority to sign in.') !== false
                    || strpos($message, 'Wait a few seconds before attempting to sign in again') !== false
                    || strpos($message, 'Your current password has expired.') !== false
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // Either the email or the password entered is incorrect.
                if (
                    strpos($message, 'Please provide a valid email address and password') !== false
                    || strpos($message, 'Either the email or the password entered is incorrect.') !== false
                    || strpos($message, 'Please provide a valid email and password.') !== false
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->http->FindPreg("/You don't have permission to access \"http:\/\/www\.basspro\.com\/shop\/ChangePassword\?\" on this server.<p>/")) {
                    $this->http->GetURL("http://www.basspro.com/shop/ChangePassword?");

                    continue;
                }

                return false;
            }// if ($errorMessage = $this->waitForElement(WebDriverBy::xpath("//*[@id='logonErrorMessage']"), 0))
            // Your account is locked due to 6 unsuccessful password attempts.
            if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //span[contains(text(), 'Your account is locked due to 6 unsuccessful password attempts.')]
                    | //h1[contains(text(), 'Account Locked')]
                "), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
            }
            sleep(1);
            $this->saveResponse();
        }// while ((time() - $startTime) < $sleep)

        /*if ($message = $this->http->FindSingleNode('//td[@id="noteBox"]'))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'or password entered is incorrect')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        ## Your account is locked
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your account is locked')]"))
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        // Either the email address or password you have entered is incorrect. Please try again.
        if ($message = $this->http->FindSingleNode("//div[@class='error-msg']", null, true, null, 0))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Please enter a valid Email Address.
        if ($message = $this->http->FindSingleNode("//p[@style = 'display: block;' and contains(text(), 'Please enter a valid Email Address.')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

        if ($this->http->FindPreg("/Something happened with your request/ims")) {
            if ($message = $this->http->FindSingleNode("//*[@id='content-area']/div/p[contains(text(),'We were unable to process your request')]"))
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }*/

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath("//a[@id = 'myAccountLink'] | //a[contains(text(), 'Outdoor Rewards') and not(contains(text(), 'FAQ'))]"), 10);
        $this->saveResponse();

        if ($myAccountLink = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'myAccountLink']"), 0)) {
            $this->logger->notice("Go to my account");
            $myAccountLink->click();

            if ($myAccountLink = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'myAccountLink_dropdown']"), 3)) {
                $this->saveResponse();
                $this->logger->notice("Go to my account");
                $myAccountLink->click();
//                $this->driver->executeScript("var popup = document.getElementById('monetate_lightbox'); if (popup) popup[0].remove();");
            }
        }// if ($myAccountLink = $this->http->FindSingleNode("//a[@id = 'myAccountLink']/@href"))

        $rewardsLink = $this->waitForElement(WebDriverBy::xpath("//div[@id='section_list_rewards']//a[contains(text(), 'Outdoor Rewards') and not(contains(text(), 'FAQ'))]"), 10);
        $this->saveResponse();

        // CLUB Account -> Rewards Available
        $rewards = $this->http->FindSingleNode("//div[@id = 'clubWalletClubPoints1']/span");

        if (isset($rewards)) {
            $this->AddSubAccount([
                "Code"           => 'bassproClubRewards',
                "DisplayName"    => "Club Rewards",
                "Balance"        => $rewards,
            ], true);
        }

        if ($rewardsLink) {
            $this->logger->notice("Go to rewards page");

            if (!$this->waitForElement(WebDriverBy::xpath("//div[@id='section_list_rewards']//a[contains(text(), 'Outdoor Rewards') and not(contains(text(), 'FAQ'))]"), 0)) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                return;
            }

            try {
                $rewardsLink->click();

                $this->waitForElement(WebDriverBy::xpath("
                    //*[@id='rewardsBalanceAmount']
                    | //div[@class = 'outdoorRewards_accountInfo' and contains(., 'I would like to link my online account to my Outdoor Rewards account')] | //a[@id = 'submitLinkRewardsAcctBtn' and contains(text(), 'Connect Outdoor Rewards')]
                "), 35);
            } catch (
                TimeOutException
                | Facebook\WebDriver\Exception\TimeoutException
                $e
            ) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }

            $this->saveResponse();
        }// if ($logout = $this->http->FindSingleNode("//a[@id = 'myAccountLink']/@href"))
        elseif ($this->waitForElement(WebDriverBy::xpath("//div[@class = 'myaccount_desc_title' and (contains(text(),'Welcome, ') or contains(text(),'Hi '))]"), 0)) {
            if (isset($rewards)) {
                //# Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id = 'welcome_header_firstName']") . " " . $this->http->FindSingleNode('//div[@id = "lastName_initials"]')));
                $this->SetBalanceNA();

                return;
            }

            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        //		$this->http->GetURL('https://www.basspro.com/shop/LinkOutdoorRewards');
        if ($this->http->FindPreg('/allow 24 hours for your account number to be generated before logging in/ims')) {
            throw new CheckException('Welcome to Bass Pro Shops Outdoor Rewards! Outdoorsmen know the best rewards come with a little patience. Please allow 24 hours for your account number to be generated before logging in. If you have any questions, please contact Customer Service at 1-800-227-7776 or contact us by email or chat.', ACCOUNT_PROVIDER_ERROR);
        }

        $name = $this->http->FindSingleNode("//*[@id='odr_welcome']/text()[contains(., 'Welcome,')]", null, false, '/,\s*(.+)/');
        $number = $this->http->FindSingleNode("//*[@id='or-welcome']/text()[contains(., 'Member ID:')]/following-sibling::span[1]");
        $balance = $this->http->FindSingleNode("//*[@id='rewardsBalanceAmount']");

        if ($this->waitForElement(WebDriverBy::xpath("//div[@class = 'outdoorRewards_accountInfo' and contains(., 'I would like to link my online account to my Outdoor Rewards account')] | //a[@id = 'submitLinkRewardsAcctBtn' and contains(text(), 'Connect Outdoor Rewards')]"), 0)) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        //# Name
        $this->SetProperty("Name", beautifulName($name . " " . $this->http->FindSingleNode('//div[@id = "lastName_initials"]')));
        //# Member ID
        $this->SetProperty("Number", $number);
        //# Balance - The value of your current point balance is
        $this->SetBalance($balance);
        //# My Points
        $this->SetProperty("MyPoints", $this->http->FindSingleNode("//span[@id='rewardsPointBalance']"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name']) && $balance === '') {
            $this->SetBalanceNA();
        }

        if ($logout = $this->http->FindSingleNode("//a[@id = 'signInOutQuickLink']/@href")) {
            $this->logger->notice("Logout...");
            $this->http->NormalizeURL($logout);
            $this->http->GetURL($logout);
        }// if ($logout = $this->http->FindSingleNode("//a[@id = 'signInOutQuickLink']/@href"))
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");
            $sensorPostUrl = "https://www.basspro.com/G93M9W/l/d/Xw8mj4xcyw/YQ5tJ85zha/dDY_FQE/K2A/Rdk0CG1U";
        }

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9275261.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400644,576696,1536,871,1536,960,1536,440,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8968,0.19754011698,814160288348,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1138,-1,0;-1,-1,0,0,1621,-1,1;-1,-1,0,0,3201,-1,1;0,0,0,1,2721,1038,0;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;0,-1,0,1,693,693,0;0,-1,0,1,7284,716,0;1,0,0,1,7818,1394,0;1,0,0,1,8447,2023,0;-1,2,-94,-102,0,-1,0,0,1138,-1,0;-1,-1,0,0,1621,-1,1;-1,-1,0,0,3201,-1,1;0,0,0,1,2721,1038,0;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;0,-1,0,1,693,693,0;0,-1,0,1,7284,716,0;1,0,0,1,7818,1394,0;1,0,0,1,8447,2023,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.basspro.com/shop/en-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1628320576696,-999999,17419,0,0,2903,0,0,3,0,0,298681104AA6E474A9E6A66175D350C9~-1~YAAQDPOPqFm+6Mt6AQAA0zR4HwZVdJwSwa0D6Fn3usq02ZcTEfa7rEjkzEiAu4dEXCOqR3sO6rafETc+ZpY8P3bp3Nivxa9TnNJVJxBLuUruJzIMuYLSWJy+BmIT6PwqdqtTqLNB2Nr74ZHQY1VMgJCtF7RK78eMCQt2pLRQjTm9jC3n52BvbL+UhQw3ksLHn8QL2BfWC+5yW28YKCS7jMHjZdxo1ng6FrLVumssdBUt+Div7H2kRXLKc9iIyU58zw7bw4ysvB8gfhGcy596BOTHTDokTSQpB1fpAZLS97S89i+ePAPYt4GyGxZ6gBqbnvp0HHdiSSKq0jHCwzJZMXD0PpQ6SnFhJrG1GFDgHeWpaHBwh9iOac9BJz7w5+HfwxHvbNJMf0vAA0o=~-1~-1~1628324075,36915,-1,-1,30261693,PiZtE,69836,101-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1730031-1,2,-94,-118,106523-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9275261.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400644,576696,1536,871,1536,960,1536,440,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8968,0.02614108213,814160288348,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1138,-1,0;-1,-1,0,0,1621,-1,1;-1,-1,0,0,3201,-1,1;0,0,0,1,2721,1038,0;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;0,-1,0,1,693,693,0;0,-1,0,1,7284,716,0;1,0,0,1,7818,1394,0;1,0,0,1,8447,2023,0;-1,2,-94,-102,0,-1,0,0,1138,-1,0;-1,-1,0,0,1621,-1,1;-1,-1,0,0,3201,-1,1;0,0,0,1,2721,1038,0;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;-1,-1,0,0,2170,-1,1;-1,-1,0,0,2030,-1,1;0,-1,0,1,693,693,0;0,-1,0,1,7284,716,0;1,0,0,1,7818,1394,0;1,0,0,1,8447,2023,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.basspro.com/shop/en-1,2,-94,-115,1,32,32,0,0,0,0,535,0,1628320576696,27,17419,0,0,2903,0,0,536,0,0,298681104AA6E474A9E6A66175D350C9~-1~YAAQDPOPqFm+6Mt6AQAA0zR4HwZVdJwSwa0D6Fn3usq02ZcTEfa7rEjkzEiAu4dEXCOqR3sO6rafETc+ZpY8P3bp3Nivxa9TnNJVJxBLuUruJzIMuYLSWJy+BmIT6PwqdqtTqLNB2Nr74ZHQY1VMgJCtF7RK78eMCQt2pLRQjTm9jC3n52BvbL+UhQw3ksLHn8QL2BfWC+5yW28YKCS7jMHjZdxo1ng6FrLVumssdBUt+Div7H2kRXLKc9iIyU58zw7bw4ysvB8gfhGcy596BOTHTDokTSQpB1fpAZLS97S89i+ePAPYt4GyGxZ6gBqbnvp0HHdiSSKq0jHCwzJZMXD0PpQ6SnFhJrG1GFDgHeWpaHBwh9iOac9BJz7w5+HfwxHvbNJMf0vAA0o=~-1~-1~1628324075,36915,824,-1231051886,30261693,PiZtE,45937,63-1,2,-94,-106,9,1-1,2,-94,-119,40,40,60,40,100,80,60,40,40,0,20,0,20,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,01321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,1730031-1,2,-94,-118,109791-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;68;7;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }
}
