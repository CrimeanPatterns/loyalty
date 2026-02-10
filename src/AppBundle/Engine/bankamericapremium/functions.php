<?php

class TAccountCheckerBankamericapremium extends TAccountChecker
{
    /*public function GetRedirectParams($targetURL = null) {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://premiumrewards.baml.com/page.aspx?id=login';

        return $arg;
    }*/

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    /*public function IsLoggedIn() {
        $this->http->RetryCount = 0;
        $this->http->GetURL('siteURL', [], 20);
        $this->http->RetryCount = 2;
        if ($this->loginSuccessful())
            return true;

        return false;
    }*/

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://premiumrewards.baml.com/page.aspx?id=login');

        if (!$this->http->ParseForm('form1')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('Col_3_widget_1$UserIDTextBox', $this->AccountFields['Login']);
        $this->http->SetInputValue('Col_3_widget_1$PasswordTextBox', $this->AccountFields['Pass']);
        $this->http->SetInputValue('__EVENTTARGET', 'Col_3_widget_1$LoginLinkButton');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm("frmToken")) {
            $this->http->PostForm();
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode('//span[@id = "Col_3_widget_1_MessageBoxHeader_MessageLabel"]')) {
            $this->logger->error($message);
            // We don’t recognize this Username and/or Password. Please contact customer support for assistance.
            if (strstr($message, 'We don’t recognize this Username and/or Password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($message = $this->http->FindSingleNode('//span[@id="Col_3_widget_1_MessageBoxHeader_MessageLabel"]'))

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - you have ... points
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "home-welcome-box"]/h4', null, true, "/have\s*(.+)\s+point/"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class = "home-welcome-box"]/h1', null, true, "/Hi\s*([^<,]+)/")));

        $this->http->GetURL("https://premiumrewards.baml.com/page.aspx?id=accountsummary");
        // Earned
        $this->SetProperty('Earned', $this->http->FindSingleNode('//td[contains(text(), "Earned")]/following-sibling::td[1]'));
        // Redeemed
        $this->SetProperty('Redeemed', $this->http->FindSingleNode('//td[contains(text(), "Redeemed")]/following-sibling::td[1]'));
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(text(), "Sign Out")]')) {
            return true;
        }

        return false;
    }
}
