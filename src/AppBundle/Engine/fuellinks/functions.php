<?php

class TAccountCheckerFuellinks extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FormURL = 'https://www.fuellinks.com/Login.php';
        $this->http->Form = [
            'member_number' => $this->AccountFields['Login'],
            'pin_number'    => $this->AccountFields['Pass'],
            'x'             => 45,
            'y'             => 24,
            'loginBox'      => 1,
        ];

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.fuellinks.com/Statements.php';

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        // Successful login
        if ($this->http->FindSingleNode('//a[@href="/Logout.php"]')) {
            return true;
        }
        // Failed to login
        else {
            // unknown error
            if (!($errorMsg = $this->http->FindSingleNode('//div[@class="messageBox"]/div[@id="message"]'))) {
                return false;
            }

            // wrong login/password
            if (strpos($errorMsg, 'Please enter a valid member number') !== false
                || strpos($errorMsg, 'Your Member Number and/or PIN could not be verified') !== false) {
                throw new CheckException($errorMsg, ACCOUNT_INVALID_PASSWORD);
            } else {
                $this->http->Log($errorMsg);
            }
        }

        return false;
    }

    public function Parse()
    {
        // Page to parse
        $this->http->GetURL('https://www.fuellinks.com/myAccount.php');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@id="topControls"]')));
        // Table to parse
        $tbx = '//div[@id="info-table"]/table';

        if (!$this->http->FindSingleNode($tbx)) {
            return false;
        }
        // Member Number
        $this->SetProperty('MemberNumber', $this->http->FindSingleNode($tbx . '/tr[1]/td[2]'));
        // Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode($tbx . '/tr[2]/td[2]'));
        // Cash for Gas
        $this->SetProperty('CashForGas', $this->http->FindSingleNode($tbx . '/tr[1]/td[4]'));
        // Cents per Gallon
        $this->SetProperty('CentsPerGallon', $this->http->FindSingleNode($tbx . '/tr[2]/td[4]'));

        // Statements page to parse
        $this->http->GetURL('https://www.fuellinks.com/Statements.php');
        // Table to parse
        $tbx = '//table[@class="balances"]';

        if (!$this->http->FindSingleNode($tbx)) {
            return false;
        }
        // Cents Off Balance
        $this->SetProperty('CentsOffBalance', $this->http->FindSingleNode($tbx . '/tr[2]/td[2]'));
        // Cash Reward Balance - Main balance
        $cashRewardBalance = CleanXMLValue($this->http->FindSingleNode($tbx . '/tr[3]/td[2]'));
        $cashRewardBalance == 'Unavailable' ? $this->SetBalanceNA() : $this->SetBalance($cashRewardBalance);
        // Stored Value Balance
        $this->SetProperty('StoredValueBalance', $this->http->FindSingleNode($tbx . '/tr[4]/td[2]'));

        // Notification
        if (!$this->http->FindSingleNode('//td[@class="issueRow"][contains(text(), "You have no Cents Per Gallon Reward Earnings in the last 31 days.")]')) {
            $this->sendNotification("fuelLinks - rewards issued");
        }
    }
}
