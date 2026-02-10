<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerColumbia extends TAccountChecker
{
    use ProxyList;
    private const REWARDS_PAGE_URL = 'https://www.columbia.com/on/demandware.store/Sites-Columbia_US-Site/en_US/Loyalty-Dashboard';

    public function InitBrowser()
    {
        parent::InitBrowser();

        if ($this->attempt > 0) {
            $this->http->SetProxy($this->proxyDOP());
            $this->sendNotification('trying DOP to avoid captcha // BS');
        }
        $this->KeepState = true;
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
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.columbia.com/on/demandware.store/Sites-Columbia_US-Site/en_US/Login-Show');

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('loginEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('loginPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('loginRememberMe', "true");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $data = $this->http->JsonLog(null, 3, true);

        if ($data === null && $this->http->FindSingleNode('//div[@id = "px-captcha"]')) {
            $this->DebugInfo = 'Captcha Press and Hold';

            throw new CheckRetryNeededException();
        }

        if (isset($data['redirectUrl'])) {
            $url = $data['redirectUrl'];
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // login or password incorrect
        if (isset($data['error'][0]) && strstr($data['error'][0], 'Invalid login or password') !== false) {
            $error = strip_tags($data['error'][0]);
            $this->logger->error("[Error]: {$error}");

            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - Rewards Balance
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "rewards__tracker-balance")] | //p[contains(text(), "Rewards Balance:")]/following-sibling::p'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg("/\"firstName\":\s*\"([^\"]+)/") . " " . $this->http->FindPreg("/\"lastName\":\s*\"([^\"]+)/")));
        // Spend .... to earn your next $5 reward.
        $this->SetProperty("SpendToNextTier", $this->http->FindSingleNode('//div[contains(@class, "rewards__tracker-disclaimer")]/span'));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/\"emailId\":\s*\"([^\"]+)/")) {
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
