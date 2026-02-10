<?php

class TAccountCheckerCzechcorporate extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://okpluscorporate.csa.cz/cs/ok_plus_corporate/okc_login_no_reg/okc_login.htm");

        if (!$this->http->ParseForm("okplusCorp")) {
            return false;
        }
        $this->http->SetInputValue("login", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->Form['language'] = 'en';

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->currentUrl() == 'https://okpluscorporate.csa.cz/en/ok_plus_corporate/okc_login_no_reg/okc_login.htm') {
            if (!$this->http->ParseForm("okplusCorp")) {
                return false;
            }
            $this->http->SetInputValue("login", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->Form['language'] = 'en';
            if (!$this->http->PostForm()) {
                return false;
            }
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@class='error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/The server encountered an internal error or misconfiguration and was unable to complete your request/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Account Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "balance"]/span[2]/strong'));
        // Name
        $this->SetProperty("Name", $this->http->FindPreg('/<strong>([^<]+)<\/strong>\s*<\/span>\s*<\/div>\s*<div[^>]+class="number"/ims'));
        // Corporate Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//div[@class = "number"]/span[2]'));
    }
}
