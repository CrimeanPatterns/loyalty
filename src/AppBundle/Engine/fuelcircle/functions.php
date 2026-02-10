<?php
/**
 * Class TAccountCheckerFuelcircle
 * Display name: FuelCircle
 * Database ID: 1239
 * Author: MTomilov
 * Created: 31.05.2016 13:17.
 */
class TAccountCheckerFuelcircle extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://fuelcircle.com/");

        if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "fuelcircle")]')) {
            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("login", '');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://fuelcircle.com/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindPreg('/Something has gone wrong and we could not log you in[.]/i')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        sleep(5);
        $this->http->GetURL('http://fuelcircle.com/portal/');

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode('//*[contains(text(), "Points Balance")]/preceding::div[1]'));
        // Points Needed Until Next Reward
        $this->SetProperty('PointsNeededUntilNextReward', $this->http->FindSingleNode('//*[contains(text(), "Points Needed Until Next Reward")]/preceding::div[1]'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//*[contains(@class, 'name')]")));
    }
}
