<?php

class TAccountCheckerEversave extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.eversave.com/save-login.jsp?postAuthentPage=");

        if (!$this->http->ParseForm()) {
            return false;
        }

        $this->http->SetInputValue("emailaddress", $this->AccountFields['Login']);
        $this->http->SetInputValue("savepassword", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        //splash "region select" after login
        if ($this->http->FindSingleNode("//body[contains(@class, 'splashPageBackgroundColor')]")) {
            return true;
        }

        $access = $this->http->FindSingleNode("//a[contains(@id, 'qa_signoutLink')]");

        if (isset($access)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@id, 'qa_loginErrorMsg')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("http://www.eversave.com/buffalo/"); // skip splash

        // Name
        $this->SetProperty("Name", $this->http->FindPreg("/<div id=\"qa_greetingfName\">Hi\s+([^\-]+)\s+-\s+<a/ims"));

        // You have $0 in Save Rewards to spend
        $this->SetBalance($this->http->FindPreg("/<div id=\"qa_SaveRewardsLeftToSpend\"[^\>]*>You have \\\$([0-9\.]+) in Save Rewards to spend<\/div>/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.eversave.com/save-login.jsp?postAuthentPage=';
        $arg['SuccessURL'] = 'http://www.eversave.com/buffalo/champion-me-books';

        return $arg;
    }
}
