<?php

/**
 * Class TAccountCheckerFoodler
 * Display name: Foodler
 * Database ID: 1135
 * Author: AKolomiytsev
 * Created: 17.10.2014 3:11.
 */
class TAccountCheckerFoodler extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.foodler.com/");

        if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "Login-submit.do")]')) {
            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.foodler.com/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//a[@href='/reg/Logout.do']")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//table[contains(@class, 'warning')]/tr[1]/td[2]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.foodler.com/user/myAccount.do");
        // Balance - Your points balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(., 'Your points balance:')]/b"));
        // Status - Your gold status
        $status = $this->http->FindSingleNode("//div[contains(@class, 'goldStatus')]/b[1]", null, true, "/[^\!]+/ims");
        $status = preg_match("/Not yet|Almost there/", $status) ? "Member" : $status;
        $this->SetProperty("Status", $status);
        // Credit - Your Foodler credit
        $this->SetProperty("Credit", $this->http->FindSingleNode("//div[@id='foodlerBucksSection']/b[1]"));
        // PointsToGoldStatus - Earn more points to qualify.
        $pointsToGoldStatus = $this->http->FindSingleNode("//div[contains(@class, 'goldStatus')]/text()[last()]", null, true, "/Earn ([0-9]+) more points/");
        $this->SetProperty('PointsToGoldStatus', $pointsToGoldStatus);

        $this->http->GetURL("https://www.foodler.com/user/accountDetails.do");
        // Name
        $firstName = $this->http->FindSingleNode("(//input[@name='firstName'])[1]/@value");
        $lastName = $this->http->FindSingleNode("(//input[@name='lastName'])[1]/@value");
        $this->SetProperty("Name", beautifulName($firstName . " " . $lastName));
    }
}
