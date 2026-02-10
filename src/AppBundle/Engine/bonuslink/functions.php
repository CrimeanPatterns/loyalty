<?php

class TAccountCheckerBonuslink extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.bonuslink.com.my/MemberDashBoard.aspx", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.bonuslink.com.my/EN/MemberLogin.aspx");

        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }
//        $this->http->FormURL = 'http://www.bonuslink.com.my/MemberLogin.aspx';
        $this->http->SetInputValue('ctl00$bodyContent$txtCardNo$txt2', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$bodyContent$txtPwd', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$bodyContent$btnLogin', "Login");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            // Site is currently under maintenance
            $message = $this->http->FindSingleNode("//font[contains(text(), 'site is currently under maintenance')]")
                // This website is currently under maintenance
                ?? $this->http->FindPreg("/(This website is currently under maintenance\.\s*Please try again later\.)/ims")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            //# Error 404
            $this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[@id = 'linkLogout']/@id")) {
            return true;
        }
        //# If wrong login or password: "your login is incorrect"
        /*
         * Sorry, you are not allow to login or make redemption.
         * Please call us at 03 7626 1000 for further assistance.
         */
        if ($message = $this->http->FindSingleNode('
                //td[@class="font_03"]/font[contains(text(), "incorrect")]
                | //td[contains(text(), "Incorrect PIN")]
                | //td[contains(text(), "Invalid BonusLink Card Number")]
                | //td[contains(text(), "Sorry, you are not allow to login or make redemption.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# PIN is locked
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'is locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@class = "dashboard-name"]/h1/text()[1]')));
        // Balance - Available Points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Available Points:")]/span'));
        // Card Number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode('//div[@class = "dashboard-name"]/h1/span'));
        // Total Points
        $this->SetProperty("TotalPoints", $this->http->FindSingleNode('//p[contains(text(), "Total Points:")]/following-sibling::h1'));
        // Lock Points
        $this->SetProperty("LockedPoints", $this->http->FindSingleNode('//p[contains(text(), "Lock Points:")]/span'));

        $points = $this->http->FindSingleNode('//div[contains(@class, "expiry-points")]/h1');
        $expDate = $this->http->FindSingleNode('//div[contains(@class, "expiry-points")]/p', null, true, "/Expiring on\s*:\s*([^<]+)/ims");
        $this->logger->debug("[Expiration Date]: $expDate / $points");

        if (isset($points) && $points > 0 && $expDate) {
            // Expiration Date
            $this->SetExpirationDate(strtotime($expDate));
            // Points to Expire
            $this->SetProperty("ExpiringBalance", $points);
        }

        if ($this->Balance <= 0 || isset($this->Properties['PointsToExpire'])) {
            return;
        }
        $this->http->GetURL("https://www.bonuslink.com.my/EN/MyPointsTransaction.aspx");
        $expNodes = $this->http->XPath->query('//div[@id = "bodyContent_bodyContent_pnlExpiry"]//table');
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            $points = $this->http->FindSingleNode("tr[2]/td", $expNode);
            $expDate = $this->http->FindSingleNode("tr[1]/td", $expNode);
            $this->logger->debug("[Expiration Date]: $expDate / $points");

            if (isset($points) && $points > 0) {
                // Expiration Date
                $this->SetExpirationDate(strtotime($expDate));
                // Points to Expire
                $this->SetProperty("ExpiringBalance", $points);

                break;
            }// if (isset($points) && $points > 0)
        }// foreach ($expNodes as $expNode)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[@id = 'linkLogout']/@id")) {
            return true;
        }

        return false;
    }
}
