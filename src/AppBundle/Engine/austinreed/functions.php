<?php

class TAccountCheckerAustinreed extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('http://www.austinreed.com/AjaxLogonPopup?catalogId=10052&myAcctMain=1&langId=-1&storeId=10152&URL=');

        if (!$this->http->ParseForm('LogonPopUp')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("logonId", $this->AccountFields["Login"]);
        $this->http->SetInputValue("logonPassword", $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        $this->http->GetURL("http://www.austinreed.com/");
        /*
         * We are currently not accepting new orders through the website. If you wish to purchase goods, please visit one of our stores.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently not accepting new orders through the website.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // In light of the recent sale of certain Austin Reed group assets, the website is no longer processing orders.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'In light of the recent sale of certain Austin Reed group assets, the website is no longer processing orders.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@id='accountPanelContent']/p[contains(text(), 'unable to match your details')]/text()[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->http->FindPreg("/Sign Out/")) {
            return true;
        }
        // Either the Logon ID or password entered is incorrect. Enter the information again.
        if ($message = $this->http->FindPreg("/displayErrorMessage\(\"(Either the Logon ID or password entered is incorrect\.[^\"])/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The password or login you have used is incorrect. Please try again.
        if ($message = $this->http->FindPreg("/displayErrorMessage\(\"(The password or login you have used is incorrect[^\"])/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Due to ... unsuccessful password attempt(s), you will be unable to logon.
        // Contact a store representative to unlock your account.
        if ($message = $this->http->FindPreg("/displayErrorMessage\(\"(Due to \d* unsuccessful password attempts?, you will be unable to logon\.\s*Contact a store representative to unlock your account\.)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://www.austinreed.com/MyAccountAccountInfoView?editRegistration=Y&catalogId=10052&myAcctMain=1&langId=-1&storeId=10152');
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@name = 'firstName']/@value")
            . " " . $this->http->FindSingleNode("//input[@name = 'lastName']/@value")));
        // Loyalty Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//input[contains(@name, 'LoyaltyCard_')]/@value"));

        if (isset($this->Properties["Number"])) {
            $this->SetBalanceNA();
        }
    }
}
