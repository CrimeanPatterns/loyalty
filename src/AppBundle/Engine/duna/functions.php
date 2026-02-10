<?php

class TAccountCheckerDuna extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.malev.com/dunaclub");

        if (!$this->http->ParseForm("aspnetForm")) {
            //# Server Error in '/' Application
            if ($message = $this->http->FindPreg("/Server Error in \'\/\' Application/ims")) {
                throw new CheckException("The website is experiencing technical difficulties, please try to check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
            }/*checked*/
            $this->http->GetURL("http://www.malev.com/");

            if ($message = $this->http->FindSingleNode("//div[@class = 'WordSection1']/p/b/span[contains(text(), '2012')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $this->AccountFields['Login'] = preg_replace('/^MA/ims', '', $this->AccountFields['Login']);
        $this->http->Form['ctl00$WebPartManager1$DunaClubLogin144123657$ctl00$tbxUserName'] = $this->AccountFields['Login'];
        $this->http->Form['ctl00$WebPartManager1$DunaClubLogin144123657$ctl00$tbxPassword'] = $this->AccountFields['Pass'];
        $this->http->Form['ctl00$WebPartManager1$DunaClubLogin144123657$ctl00$btnLogin'] = 'Login';

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//span[@id = 'ctl00_WebPartManager1_DunaClubLogin144123657_ctl00_lblFailureText']", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->Response['url'] != "http://www.malev.com/dunaclub") {
            $this->http->GetURL("http://www.malev.com/dunaclub");
        }

        if ($this->http->FindSingleNode("//a[@id='ctl00_WebPartManager1_DunaClubLogin144123657_ctl00_btnLogout']")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'ctl00_WebPartManager1_DunaClubLogin144123657_ctl00_lblPoints']"));
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[@id = 'ctl00_WebPartManager1_DunaClubLogin144123657_ctl00_lblUserName']")); // Name
        $this->SetProperty("Level", $this->http->FindSingleNode("//span[@id = 'ctl00_WebPartManager1_DunaClubLogin144123657_ctl00_lblLevel']")); // Your tier level
        $this->SetProperty("AccountNumber", $this->AccountFields['Login']);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.malev.com/dunaclub";

        return $arg;
    }
}
