<?php

class TAccountCheckerFatsecret extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.fatsecret.co.uk/Default.aspx?pa=m", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.fatsecret.co.uk/Auth.aspx?pa=s");
        // parsing form on the page
        if (!$this->http->ParseForm("ctl03")) {
            return false;
        }
        $this->http->SetInputValue('ctl00$ctl12$Logincontrol1$Name', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$ctl12$Logincontrol1$Password', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('__EVENTTARGET', 'ctl00$ctl12$Logincontrol1$LoginButton');
        $this->http->SetInputValue('ctl00$ctl12$Logincontrol1$CreatePersistentCookie', 'on');

        return true;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return false;
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//script[contains(text(), 'Login failed')]")) {
            throw new CheckException("Login failed.", ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }
        // An unexpected error has occured
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'An unexpected error has occured')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'We’ve updated our privacy policy')]")) {
            $this->throwAcceptTermsMessageException();
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindPreg("/Hello\s*([^<\|]+)/ims"));
        // set Balance (Current Weight)
        $this->SetBalance($this->http->FindSingleNode("//div[@id = 'fswid_weight']//span[@class = 'subheading']", null, true, "/([\d\.]+)\s/ims"));

        $this->http->GetURL("https://www.fatsecret.co.uk/Default.aspx?pa=memn");
        //set LostSoFar
        $this->SetProperty("LostSoFar", $this->http->FindSingleNode("(//span[@class='green']/b)[1]", null, true, "/([\d\.]+)\s/ims"));
        //set StillToGo
        $this->SetProperty("StillToGo", $this->http->FindSingleNode("(//span[@class='red']/b)[1]", null, true, "/([\d\.]+)\s/ims"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(text(),'Sign Out')]")) {
            return true;
        }

        return false;
    }
}
