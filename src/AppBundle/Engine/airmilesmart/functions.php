<?php

class TAccountCheckerAirmilesmart extends TAccountChecker
{
    public function _cookieClean()
    {
        /* get rid of:
         * Response cookies:
         *  : /a, domain: , path: , expires:
         */
        $cookies = $this->http->GetCookies("airmilesmart.com");
        $this->http->removeCookies();

        foreach ($cookies as $k=>$v) {
            if (!empty($k)) {
                $this->http->Log($k . " => " . $v);
                $this->http->setCookie($k, $v);
            }
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->RetryCount = 0;
//        $this->http->setMaxRedirects(0);
        $this->http->GetURL("http://airmilesmart.com/login.asp");

        if (!$this->http->ParseForm('form2')) {
            $this->CheckErrors();

            return false;
        }
        $this->_cookieClean();

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $this->http->Form['Submit'] = 'Submit';

        return true;
    }

    public function CheckErrors()
    {
        //# This domain registration has expired
        if ($message = $this->http->FindPreg("/src=\"http:\/\/sedoparking.com\/\'\s*\+\s*window.location.host\s*\+\s*'\/'\s*\+\s*reg\s*\+\s*'\/park\.js\"/ims")) {
            throw new CheckException("The domain registration has expired", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        return false;
    }

    public function Login()
    {
        $this->http->PostForm();

        if ($message = $this->http->FindSingleNode("//form[contains(@name, 'form2')]/b/font")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->_cookieClean();
        $this->http->GetURL("http://airmilesmart.com/PointHistory30.asp");

        $access = $this->http->FindSingleNode("//a[contains(@href, 'logoff.asp')]");

        if (isset($access)) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // You have _ points
        $this->SetBalance($this->http->FindPreg("/You have ([\d\.,]+) points/ims"));

        // refs #5561
        $exp = '11/15/2012';
        $this->SetExpirationDate(strtotime($exp));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'http://airmilesmart.com/PointHistory30.asp';

        return $arg;
    }
}
