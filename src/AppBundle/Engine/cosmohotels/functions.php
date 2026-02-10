<?php

class TAccountCheckerCosmohotels extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.cosmopolitanlasvegas.com/login';
        //	$arg['SuccessURL'] = 'https://www.cosmopolitanlasvegas.com/membership/account-summary.aspx';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://peek.cosmopolitanlasvegas.com/identity/offers?ra=a", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.cosmopolitanlasvegas.com/login');

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/Account/Login')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("EmailOrPlayerNumber", $this->AccountFields['Login']);
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 're having trouble pulling up your data right now. Give us a call at')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("
                //h2[contains(text(), '502 - Web server received an invalid response while acting as a gateway or proxy server.')]
                | //h1[contains(text(), '502 Bad Gateway')]
        ")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        // Access allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // That combination doesn't seem to unlock the door. Please check your email address or Identity number and password and try again.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'seem to unlock the door. Please check your email address or Identity number and password and try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter a valid email address or a player number
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Please enter a valid email address or a player number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Not so fast. Please check your inbox for the confirmation email we sent you, and then you’re in.
        if ($message = $this->http->FindSingleNode("
                //li[contains(text(), 'Not so fast. Please check your inbox for the confirmation email we sent you, and then you’re in.')]
                | //li[contains(text(), 'Looks like there was an error in accessing your account. Please contact Identity Membership & Rewards')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Identity points
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(@class, 'identity-title')]", null, true, self::BALANCE_REGEXP_EXTENDED));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h3[contains(@class, 'title-user')]")));
        // Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(@class, 'title-number')]", null, true, "/#(.+)/"));
        // Tier
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[@class = 'orc-c-profile-block__small-print']/span[contains(@class, 'user-text')]", null, true, "/([^—]+)/"));
        // [Status] - through ...
        $this->SetProperty("StatusExpiration",
            $this->http->FindSingleNode("//div[@class = 'orc-c-profile-block__small-print']/span[contains(@class, 'user-text')]", null, true, "/—\s*through\s*(.+)/")
            ?? $this->http->FindSingleNode("//div[@class = 'orc-c-profile-block__small-print']/span[contains(@class, 'user-text')]/following-sibling::span[contains(@class, '__user-fine-print')]", null, true, "/points by ([\d\/]+) to stay /")
        );
        // Resort Credit
        $this->SetProperty("ResortCredit", $this->http->FindSingleNode("//div[contains(@class, 'credit-text')]/span"));
        // for Identity Play
        $this->SetProperty("ForIdentityPlay", $this->http->FindSingleNode("//div[contains(@class, 'available-text')]/span", null, true, "/\((.+)/"));
        // Tier Points
        $this->SetProperty("TierPoints", $this->http->FindSingleNode("//node()[contains(text(), 'Tier Point') and contains(@class, '__text-empty')]", null, true, self::BALANCE_REGEXP_EXTENDED));
        // Tier Points to Next Tier
        $this->SetProperty("PointsToNextLevel", $this->http->FindSingleNode("//node()[contains(text(), ' to ') and contains(@class, '__text-empty')]", null, true, self::BALANCE_REGEXP_EXTENDED));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//button[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
