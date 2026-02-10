<?php

class TAccountCheckerCashbaq extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->ParseForms = false;

        return true;
    }

    public function Login()
    {
        $res = null;
        $i = 0;

        while ($res == null && $i < 10) {
            $res = $this->CashbaqLogin();
            $i++;
        }

        return $res;
    }

    public function CashbaqLogin()
    {
        $this->http->GetURL("https://www.cashbaq.com/loginajax.php?email=" . urlencode($this->AccountFields['Login']) .
                                        "&password=" . urlencode($this->AccountFields['Pass']) .
                                        "&isFromFB=0&remeber=undefined");

        if ($this->http->Response['body'] == 'INVALID') {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = "Could not log you in. Please check your email and password";

            return false;
        }

        if ($this->http->FindPreg('/(SQL\/DB Error)/ims')) {
            return null;
        }

        return $this->http->Response['body'] == 'YES';
    }

    public function Parse()
    {
        $this->http->getURL('https://www.cashbaq.com');
        $this->SetProperty("Name", $this->http->FindPreg("/([^\>']*)\'s account/ims"));
        $this->SetProperty("Total", $this->http->FindSingleNode('//a[@href="https://www.cashbaq.com/accountsummary.php"][2]'));
        $this->SetBalance($this->http->FindSingleNode('//a[@href="https://www.cashbaq.com/accountsummary.php"][4]'));
    }

    public function GetRedirectParams($targetURL = null)
    {
        //$arg = parent::GetRedirectParams($targetURL);
        //$arg["CookieURL"] = "https://www.cashbaq.com/loginajax.php?email=".urlencode($this->AccountFields['Login']).
        //								"&password=".urlencode($this->AccountFields['Pass']).
        //								"&isFromFB=0&remeber=undefined";
        $arg["SuccessURL"] = "https://www.cashbaq.com";
        $arg["RequestMethod"] = "GET";
        $arg["URL"] = "https://www.cashbaq.com/loginajax.php?email=" . urlencode($this->AccountFields['Login']) .
                                        "&password=" . urlencode($this->AccountFields['Pass']) .
                                        "&isFromFB=0&remeber=undefined";
        $arg["RedirectURL"] = "https://www.cashbaq.com/loginajax.php?email=" . urlencode($this->AccountFields['Login']) .
                                        "&password=" . urlencode($this->AccountFields['Pass']) .
                                        "&isFromFB=0&remeber=undefined";

        return $arg;
    }
}
