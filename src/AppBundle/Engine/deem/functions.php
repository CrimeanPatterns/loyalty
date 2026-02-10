<?php

class TAccountCheckerDeem extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://login.deem.com/login/apps/init.do?_appid=Login');

        if (!$this->http->ParseForm("LogonForm")) {
            return false;
        }
        $this->http->SetFormText("_action=hankAuthenticate&_flowName=main&pageID=LogonPage&_appid=Login&_formId=LogonForm&appType=generic&relayState=https%3A%2F%2Freardensmb-crnpi.deem.com%2Frc%2Flogin%2Fmain.do&origRememberMe=&smb=true", '&');
        $this->http->SetInputValue('emailAddress', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($mess = $this->http->FindSingleNode("//*[@id='errors']/center//div[2]")) {
            throw new CheckException($mess, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//a[contains(., 'Sign Out')]")) {
            return true;
        }

        if ($mess = $this->http->FindPreg("/(The Email Address and Password combination that you entered is not recognized\.)/ims")) {
            throw new CheckException($mess, ACCOUNT_INVALID_PASSWORD);
        }
        // Change Password
        if ($this->http->FindSingleNode("//span[contains(text(), 'Change Password')]")) {
            throw new CheckException("Rearden Commerce (Deem) website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // set Company Name
        $this->SetProperty('CompanyName', $this->http->FindSingleNode("//div[contains(@class, 'companyName')]"));

        if (isset($this->Properties['CompanyName'])) {
            $this->SetBalanceNA();
        }

        if (!$this->http->FindPreg("/No upcoming activity/ims")) {
            $this->sendNotification("Rearden Commerce (Deem). New trips found!");
        }

        $this->http->GetURL("https://reardensmb-crnpi.deem.com/rc/settings/employeeInfo.do");
        // set Name property
        $this->SetProperty('Name', beautifulName(CleanXMLValue(
            $this->http->FindSingleNode("//input[@name='personalInfo.firstName']/@value") . ' ' .
            $this->http->FindSingleNode("//input[@name='personalInfo.middleInitials']/@value") . ' ' .
            $this->http->FindSingleNode("//input[@name='personalInfo.lastName']/@value"))));
    }
}
