<?php

class TAccountCheckerAmtrakbiz extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.amtrakguestrewards.com/forbusiness/members/login");

        if (!$this->http->ParseForm(null, 1, true, "//form[@action = '/forbusiness/members/validate-login']")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('member[uid]', $this->AccountFields['Login']);
        $this->http->SetInputValue('member[memberpassword]', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // Maintenance
        if ($message = $this->http->FindNodes("//*[contains(text(), 'We are currently performing maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($message = $this->http->FindPreg("/The web site you are accessing has experienced an unexpected error\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[@class='logoutLink']")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//ul[@class='errors']", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current Balance
        $pointsText = $this->http->FindSingleNode("//div[@class='panel']/span[@class='field']/strong[@class='updateable']");

        if (isset($pointsText) && preg_match('/(\d*)(\D*)(\d*) points/ims', $pointsText, $outArr)) {
            $pointsText = $outArr[1] . $outArr[3];
        } else {
            $pointsText = null;
        }
        $this->SetBalance($pointsText);
        // Company
        $this->SetProperty("Company", $this->http->FindSingleNode("//div[@class='panel']/span[1]"));
        // Annual Spend
        $value = $this->http->FindSingleNode("//div[@class='panel']/span[3]");

        if (isset($value)) {
            $this->SetProperty("AnnualSpend", str_ireplace('Annual Spend: $', '', $value));
        }
        // Point Match
        $this->SetProperty("PointMatch", $this->http->FindSingleNode("//div[@class='panel']/span[4]/strong"));
        // Travelers: ... Active, \d Pending
        $this->SetProperty("ActiveTravel", $this->http->FindSingleNode("//div[@class='panel']/div/span", null, false, "/\w*:\s*(\d*)/"));
    }
}
