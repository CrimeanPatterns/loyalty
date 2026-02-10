<?php

// refs #3135
class TAccountCheckerDuane extends TAccountChecker
{
    private $Logins = 0;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        throw new CheckException('The Duane Reade Flax Rewards program has been discontinued.
You can join the new rewards program here <a href="http://www.duanereade.com" target="_blank">http://www.duanereade.com</a>.
If you do not join 6 months from your last purchase on your FlexRewards Card, your FlexRewards Points will expire.', ACCOUNT_PROVIDER_ERROR);

        $this->http->GetURL('https://secure.duanereade.com/Rewards.aspx');

        if (!$this->http->ParseForm("aspnetForm")) {
            return false;
        }
        //# Card number
        $this->http->Form['ctl00$ContentPlaceHolder2$txtCardNumber'] = $this->AccountFields['Login'];
        //# Zip
        $this->http->Form['ctl00$ContentPlaceHolder2$txtZip'] = $this->AccountFields['Login2'];
        //# Last name
        $this->http->Form['ctl00$ContentPlaceHolder2$txtLastName'] = $this->AccountFields['Pass'];
        $this->http->Form['ctl00$ContentPlaceHolder2$btnGo.x'] = "43";
        $this->http->Form['ctl00$ContentPlaceHolder2$btnGo.y'] = "18";

        $this->http->setCookie(
           "ASP.NET_SessionId",
            "hhvznk3ncraok345nypjlo55",
           "secure.duanereade.com"
          );

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        //# Update registration
        if ($message = $this->http->FindSingleNode("//img[contains(@src, 'update_registration')]/@src")) {
            throw new CheckException("Please update your registration", ACCOUNT_PROVIDER_ERROR);
        }
        //# Access is allowed /*checked*/
        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]")) {
            return true;
        }

        //# Invalid email address or password
        if ($message = $this->http->FindSingleNode("//div[contains(@id, 'signinform_message')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        //# Retry login
        if (!isset($this->Properties['Number']) && $this->Logins < 3) {
            $this->http->Log("Retry login (function Login)- " . var_export($this->Logins, true), true);
            sleep(5);
            $this->Logins++;
            $this->LoadLoginForm();
            $this->Login();
            $this->Parse();
        }

        return false;
    }

    public function Parse()
    {
        //# Balance - Total Points
        $this->SetBalance($this->http->FindPreg("/Total Points:\s*<strong>([^<]+)/ims"));
        //# Current Points
        $this->SetProperty("CurrentPoints", $this->http->FindPreg("/Current Points:\s*<strong>([^<]+)/ims"));
        //# Status
        $this->SetProperty("Status", $this->http->FindPreg("/SuperSaver Status:\s*([^<]+)/ims"));
        //# SuperSaver Points
        $this->SetProperty("SuperSaverPoints", $this->http->FindPreg("/SuperSaver Points:\s*<strong>([^<]+)/ims"));
        //# Card #
        $this->SetProperty("Number", $this->http->FindSingleNode("//strong[contains(text(), 'Card #')]/parent::div", null, true, '/Card #: (.*)/ims'));

        //# Retry login
        if (!isset($this->Properties['Number']) && $this->Logins < 3) {
            $this->http->Log("Retry login (function Parse) - " . var_export($this->Logins, true), true);
            sleep(5);
            $this->Logins++;
            $this->LoadLoginForm();
            $this->Login();
            $this->Parse();
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://secure.duanereade.com/Rewards.aspx';

        return $arg;
    }
}
