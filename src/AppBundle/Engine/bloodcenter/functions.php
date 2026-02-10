<?php

class TAccountCheckerBloodcenter extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://sbcdonor.org/donor/account/view';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm('login_form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "error")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'We were unable to log you in based on the information provided.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//li[contains(text(), "Points -")]', null, true, "/\-\s*(\d+)/"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//label[@class="value" and @for="first_name"]')." ".$this->http->FindSingleNode('//label[@class="value" and @for="last_name"]')));
        // Donor ID
        $this->SetProperty('Number', $this->http->FindSingleNode('//li[contains(text(), "Donor ID -")]', null, true, "/\-\s*(\d+)/"));
        // Blood Type
        $this->SetProperty('BloodType', $this->http->FindSingleNode('//li[contains(text(), "Blood Type -")]', null, true, "/\-\s*(.+)/"));
        // YTD Donations
        $this->SetProperty('Donations', $this->http->FindSingleNode('//li[contains(text(), "Donations -")]', null, true, "/\-\s*(\d+)/"));
        // Lifetime donations
        $this->SetProperty('LifetimeDonations', $this->http->FindSingleNode('//li[contains(text(), "Lifetime donations -")]', null, true, "/\-\s*(\d+)/"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(@href, "signout")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
