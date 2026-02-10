<?php

class TAccountCheckerAirline extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return true;
    }

    public function Login()
    {
        $this->http->getURL('http://www.csair.com/en/index.asp');
        $this->http->PostURL("http://ec.csair.com/B2C/data/account/login.xsql?"
                                    . "userguid=" . urlencode($this->AccountFields['Login'])
                                    . "&passwd=" . urlencode($this->AccountFields['Pass'])
                                    . "&usertypes=M&logType=",
                                    []);

        if (preg_match("/<name><\/name>/ims", $this->http->Response['body'])) {
            $this->ErrorMessage = "Invalid Sky Pearl Number or PIN";
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->http->getURL('http://skypearl.csair.com/skypearl/en/memberArea.action#myInfo1');
        $this->SetProperty("Name", $this->http->FindSingleNode('//table/tr/th[contains(text(), "Member\'s Name:")]/following::td[1]'));
        $this->SetProperty("Tier", $this->http->FindSingleNode('//table/tr/th[contains(text(), "Membership Tiers:")]/following::td[1]'));
        $this->SetBalance($this->http->FindSingleNode('//table/tr/th[contains(text(), "Usable Mileage:")]/following::td[1]'));
    }
}
