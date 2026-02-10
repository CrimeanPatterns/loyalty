<?php

// refs #15543

class TAccountCheckerFlroberts extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->GetURL('https://www.loyaltyretailrewards.com/flroberts/login');

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.loyaltyretailrewards.com/fisapi/login';
        $this->http->SetInputValue('UserId', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->Message) && ($response->Message === 'Invalid Credentials' || $response->Message === 'Not Allowed to Login - Call CSR')) {
            throw new CheckException('Invalid Credentials', ACCOUNT_INVALID_PASSWORD);
        }
        // Password must include at least:
        if (isset($response->Message) && ($response->Message === 'Required a Change Password' || $response->Message === 'Security Question Not Set Up')) {
            $this->throwProfileUpdateMessageException();
        }

        $this->http->GetURL('https://www.loyaltyretailrewards.com/flroberts/Home');

        if ($this->http->FindSingleNode('//a[@id="btnLogout"]')) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Name - CARDHOLDER
        $name = $this->http->FindSingleNode("//p/label[text()='Cardholder']/following-sibling::label[@class='userResults']");
        $this->SetProperty('Name', beautifulName($name));

        // Balance - RewardsPLUS Points
        $balance = $this->http->FindSingleNode("//p/label[text()='Balance']/following-sibling::label[@class='userResults']", null, false, self::BALANCE_REGEXP);
        $this->SetBalance($balance);

        // Status
        $status = $this->http->FindSingleNode("//p/label[text()='Status']/following-sibling::label[@class='userResults']");
        $this->SetProperty('Status', beautifulName($status));
    }
}
