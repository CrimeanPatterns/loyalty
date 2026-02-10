<?php

class TAccountCheckerAerosvit extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FormURL = "http://62.80.163.28/CraneWeb/Login.jsp";
        $this->ParseForms = false;
        $this->http->Form['txtUser'] = $this->AccountFields['Login'];
        $this->http->Form['txtPass'] = $this->AccountFields['Pass'];
        $this->http->Form['clickedButton'] = "Login";

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(text(), 'Logout')]")) {
            return true;
        }

        //# Invalid Login
        if (($message = $this->http->FindPreg("/innerHTML = '([^']+)/ims")) && strstr($message, 'ACCOUNT_LOCKED')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        //# Invalid Login
        if ($message = $this->http->FindPreg("/innerHTML = '([^']+)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[@class = \"errorMessage\"]")) {
            throw new CheckException("Could not log you in. Please check your email and password", ACCOUNT_INVALID_PASSWORD);
        }
        //# 500 Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 500')]")) {
            throw new CheckException("HTTP Status 500", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        //# Balance - Award Points
        if (!$this->SetBalance($this->http->FindPreg("/Award\s*Points\s*:\s*(\d*)[^\d]{1}/ims"))) {
            //# Balance - Award Miles
            $this->SetBalance($this->http->FindPreg("/Award\s*Miles\s*:\s*(\d*)[^\d]{1}/ims"));
        }
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'LoginName']", null, false, "/mr?s?\.(.*)/ims")));
        //# Tier
        $tier = $this->http->FindSingleNode("//div[@class = 'LoginDetails']", null, false, "/tier_?\s*(.*)/ims");

        if (strtolower($tier) == 'bas') {
            $tier = 'Base';
        } elseif (strtolower($tier) == 'blu') {
            $tier = 'Classic';
        } elseif (strtolower($tier) == 'gol') {
            $tier = 'Gold';
        }
        $this->SetProperty("Tier", $tier);
        //# Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[@class = 'LoginDetails']", null, false, "/Number ([\d]+)/ims"));

        $this->http->GetURL("http://62.80.163.28/CraneWeb/StatusMilesToExpire.jsp?activeLanguage=EN&source=VV");
        //# Expiration Date
        $exp = $this->http->FindSingleNode("//div[@id = 'formContent']/table/tr[2]/td[1]");
        $this->http->Log("Expiration Date - " . var_export($exp, true), true);
        $exp = str_replace('/', '.', $exp);

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }
        //# Points due to expire
        $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//div[@id = 'formContent']/table/tr[2]/td[2]"));
        //# Total Tier Points
        $this->SetProperty("TotalTierPoints", $this->http->FindSingleNode("//div[contains(text(), 'Total Tier Points')]/following::div[1]"));
        //# Tier Sectors
        $this->SetProperty("TierSectors", $this->http->FindSingleNode("//div[contains(text(), 'Tier Sectors')]/following::div[1]"));
        //# Total Points Since Enrollment
        $this->SetProperty("TotalPointsSince", $this->http->FindSingleNode("//div[contains(text(), 'Total Points Since Enrollment')]/following::div[1]"));
    }
}
