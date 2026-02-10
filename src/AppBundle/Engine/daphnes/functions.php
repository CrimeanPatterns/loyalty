<?php

class TAccountCheckerDaphnes extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://ecardportal.securetree.com/daphnes/Login.aspx');
        // parse form
        if (!$this->http->ParseForm('form1')) {
            return false;
        }
        // login, password
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$LoginForm1$LoginUser$UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$LoginForm1$LoginUser$Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$LoginForm1$LoginUser$LoginButton', '');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'http://www.daphnesgreekcafe.com/check-account-balance.html';

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // successful login
        if ($this->http->FindSingleNode('//a[@id="lnkLogin"]/span[contains(text(), "Logout")]')) {
            return true;
        }
        // failed to login
        else {
            $errorCode = ACCOUNT_PROVIDER_ERROR;
            $errorMsg = $this->http->FindSingleNode('//div[@class="section-inner"]/span[contains(@style, "color:Red;")]');
            // unknown error
            if (!$errorMsg) {
                // empty login/pass?
                if ($errorMsg = $this->http->FindSingleNode('//span[@id="ContentPlaceHolder1_LoginForm1_LoginUser_PasswordRequired"]') // in anyway "password required" message
                    or $errorMsg = $this->http->FindSingleNode('//span[@id="ContentPlaceHolder1_LoginForm1_LoginUser_UserNameRequired"]')) {
                    $errorCode = ACCOUNT_INVALID_PASSWORD;
                }
                // unknown error
                else {
                    return false;
                }
            }
            // wrong login/pass
            if (strpos($errorMsg, 'Your login attempt was not successful') !== false) {
                $errorCode = ACCOUNT_INVALID_PASSWORD;
            }
            // exception
            throw new CheckException($errorMsg, $errorCode);
        }
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class="welcome-message"]/p/span/a')));
        // parse&post form
        if (!$this->http->ParseForm('form1')) {
            return;
        }
        // fields
        $this->http->Form['ctl00$S'] = 'ctl00$ContentPlaceHolder1$RadAjaxManager1SU|ctl00$ContentPlaceHolder1$RadAjaxManager1';
        $this->http->Form['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$RadAjaxManager1';
        $this->http->Form['RadAJAXControlID'] = 'ctl00_ContentPlaceHolder1_RadAjaxManager1';
        $this->http->Form['__ASYNCPOST'] = 'true';
        // post form
        if (!$this->http->PostForm()) {
            return;
        }
        // Card number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('//p[@class="card-number"]', null, true, '/(\d+)/'));
        // Card Balance
        $this->SetProperty('CardBalance', $this->http->FindSingleNode('//div[@class="bonus running clearfix"]/div/div/strong'));
        // Pita points - Balance
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class,"bonus")][2]/div/div/strong'));

        // provider bug
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindPreg("/System.Object\[\] ReadResponse\(System\.Web\.Services\.Protocols\.SoapClientMessage\, System\.Net\.WebResponse,/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
    }
}
