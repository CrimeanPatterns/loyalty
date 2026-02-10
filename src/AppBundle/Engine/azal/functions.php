<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAzal extends TAccountChecker
{
    use ProxyList;
    public const REWARDS_PAGE = 'https://ffp.azal.travel/profile';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE, [], 20);
        $this->http->RetryCount = 2;

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        // Check your password or card
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException("Check your password or card", ACCOUNT_INVALID_PASSWORD);
        }

        if (empty($this->AccountFields['Pass'])) {
            throw new CheckException("To update this Azerbaijan Airlines (Azal Miles) account you need to update your credentials. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->http->removeCookies();
        $this->http->GetURL("https://ffp.azal.travel/login?lang=en");

        if (!$this->http->FindSingleNode('//form[@action = "/login"]')) {
            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://ffp.azal.travel/login';
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember-me', 'on');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $this->http->FindSingleNode('//form/div/div[@role="alert"]');

        if (isset($message)) {
            $this->logger->error($message);

            if (str_contains($message, 'Your username and password is invalid')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Travel points
        $this->SetBalance($this->http->FindSingleNode('//b[text() = "Travel points"]/following-sibling::a'));
        $this->SetProperty('Name', $this->http->FindSingleNode('//h3[contains(@class, "profile-username")][2]'));
        $this->SetProperty('Account', $this->AccountFields['Login']);
        // Active/Classic or Active/Gold or Active/Platinum
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(@class, "box-profile")]/p[starts-with(@class, "text-muted")]'));
        // Status validity
        $this->SetProperty('CardValidityPeriod', $this->http->FindSingleNode('//b[text() = "Status validity"]/following-sibling::a'));
        // Status points
        $this->SetProperty('PointsNextTier', $this->http->FindSingleNode('//b[text() = "Status points"]/following-sibling::a'));
        // Used points
        $this->SetProperty('MilesRedeemed', $this->http->FindSingleNode('//b[text() = "Used points"]/following-sibling::a'));
        // Expired points
        $this->SetProperty('MilesExpired', $this->http->FindSingleNode('//b[text() = "Expired points"]/following-sibling::a'));
        // Enroll date
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//b[text() = "Enroll date"]/following-sibling::a'));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        return $this->http->currentUrl() == self::REWARDS_PAGE
            && $this->http->FindSingleNode('//h3[contains(@class, "profile-username")][1]');
    }
}
