<?php
/**
 * Class TAccountCheckerEastcoast
 * Display name: East Coast Rewards
 * Database ID: 896
 * Author: APuzakov
 * Created: 14.04.2015 13:52.
 */
class TAccountCheckerEastcoast extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.virgintrainseastcoast.com/sign-in/");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action,'sign-in')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.eastcoast.co.uk/";

        return $arg;
    }

    public function checkErrors()
    {
        // The server encountered an error and could not complete your request.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'The server encountered an error and could not complete your request.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'my-account') and @id='myaccount-link']/@href")
            || $this->http->FindSingleNode("//span[contains(text(), 'Welcome back ')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class,'validation-summary-errors')]/span/text()", null, true, '/([^,?|^\.?]+)[,|\.]/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Current Points available
        $this->SetBalance($this->http->FindSingleNode("//dt[contains(text(),'Current Points available')]/following-sibling::dd"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'greet')]", null, true, '/Hi\s+(.+)/ims')));
        //Pending Points
        $this->SetProperty("PendingPoints", $this->http->FindSingleNode("//dt[contains(text(),'Pending Points')]/following-sibling::dd"));
        //Points expiring in next 30 days
        $this->SetProperty("PointsExpiring", $this->http->FindSingleNode("//dt[contains(text(),'Points expiring in')]/following-sibling::dd"));
        /*
         * East Coast Rewards is now closed
         * If you still have some Points left,
         * you can redeem them as normal up to and including 30 September 2015 or convert them into Nectar points.
         */
        $this->SetExpirationDate(strtotime("30 September 2015"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckException("East Coast Rewards is now closed. If you still have some Points left, you can redeem them as normal up to and including 30 September 2015 or convert them into Nectar points.", ACCOUNT_WARNING);
        }
    }
}
