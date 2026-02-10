<?php

class TAccountCheckerChilis extends TAccountChecker
{
    private $parseUrl = 'https://www.chilis.com/account/rewards';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->parseUrl, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && $this->http->Response['code'] === 200) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.chilis.com/login');

        if (!$this->http->FindSingleNode('//input[@data-testid="username"]')) {
            return false;
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', 'true');
        $this->http->SetInputValue('_rememberMe', "on");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('
                //span[
                    contains(text(), "Incorrect My Chili\'s Rewards phone or password")
                    or contains(text(), "It looks like you registered on Ziosk. We have emailed you instructions to create an online Rewards password.")
                    or contains(text(), "It looks like you\'ve recently registered. We have emailed you instructions to create an online Rewards password.")
                ]
            ')
            ?? $this->http->FindPreg("/errorMessages=\[\{\"message\":\"(Incorrect My Chili's Rewards.u00AE phone or password, please try again)\"/")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message =
            $this->http->FindSingleNode('//span[contains(text(), "Unable to log in to My Chili\'s Rewards at this time")]')
            ?? $this->http->FindPreg("/errorMessages=\[\{\"message\":\"(Unable to log in to My Chili's Rewards.u00AE at this time\. Please try again later\.)\"/")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != $this->parseUrl) {
            $this->http->GetURL($this->parseUrl);
        }
        // Balance - YOU HAVE ... REWARDS
        $this->SetBalance($this->http->FindSingleNode('//a[@id = "rewards-logged-in-summary-rewards"]'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class = "profile-name"]')));
        // REWARDS MEMBER ID
        $this->SetProperty('Number', str_replace(['(', ')', ' ', '-'], '', $this->http->FindSingleNode('//div[@class = "rewards-number"]')));
        // SubAccounts
        $rewards = $this->http->XPath->query('//div[@class = "rewards-active-item"]');
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            $time = $this->http->FindSingleNode('.//div[contains(@class, "rewards-active-expiration")]/b', $reward);
            $date = DateTime::createFromFormat('M. d, Y', $time);
            $displayName = $this->http->FindSingleNode('.//div[contains(@class, "rewards-active-title")]', $reward);

            $this->AddSubAccount([
                'Code'           => 'chilis' . md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $date ? intval($date->format('U')) : false,
            ]);
        }
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//form[contains(@action, "logout")]/@action')
            || $this->http->FindSingleNode('//div[contains(text(), "Welcome, ")]')
        ) {
            return true;
        }

        return false;
    }
}
