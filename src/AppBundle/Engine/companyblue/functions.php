<?php

class TAccountCheckerCompanyblue extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://blueinc.jetblue.com/login.html");

        if (!$this->http->ParseForm('login-form')) {
            return false;
        }
        $this->http->SetInputValue("tbnumber", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("remember", "true");
        $this->http->SetInputValue("_remember", "on");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // There is an issue connecting to the TrueBlue system, please try again later.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "There is an issue connecting to the TrueBlue system, please try again later.")]/text()[1]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindNodes("//a[contains(@href,'logout')]")) {
            return true;
        }
        // Could not log in. Please check your username and password
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Could not log in. Please check your username and password")]/text()[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - pts
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'tb-pts']", null, true, self::BALANCE_REGEXP_EXTENDED));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//span[@class = 'names']")));
        // Company
        $this->SetProperty('Company', $this->http->FindSingleNode("//span[@class = 'trueblue']"));
        // TrueBlue #
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode("//span[@class = 'tb-number']/span[@class = 'tb-number']"));
    }
}
