<?php

class TAccountCheckerCashback extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();

        // Bing cashback program was discontinued. Last day to access purchase history or redeem cashback was on July 30, 2011.
        // http://www.bing.com/shopping/pages/faq.aspx
        $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
        $this->ErrorMessage = 'Bing cashback program was discontinued. Last day to access purchase history or redeem cashback was on July 30, 2011.'; /*checked*/

        return false;

        if (!$this->http->GetURL("https://cashbackaccount.bing.com/cashback/signup.aspx")) {
            return false;
        }
        $this->http->FormURL = $this->http->FindPreg("/var srf_uPost='([^']+)'/ims");

        if (!isset($this->http->FormURL)) {
            $this->http->Log("failed to find from link");

            return false;
        }
        $PPFT = $this->http->FindPreg("/<input[^>]+name=\"PPFT\"[^>]+value=\"([^\"]+)\"/ims");

        if (!isset($PPFT)) {
            $this->http->Log("failed to find PPFT");

            return false;
        }
        $this->http->Form = [
            "login"        => $this->AccountFields['Login'],
            "passwd"       => $this->AccountFields['Pass'],
            "type"         => "11",
            "LoginOptions" => "2",
            "NewUser"      => "1",
            "MEST"         => "",
            "PPSX"         => "PassportR",
            "PPFT"         => $PPFT,
            "idsbho"       => "1",
            "PwdPad"       => "IfYouAreReadingThisYouHaveTooMuch",
            "sso"          => "",
            "CS"           => "",
            "FedState"     => "",
            "remMe"        => "1",
            "i1"           => "",
            "i2"           => "1",
            "i3"           => "194879",
            "i4"           => "",
            "i12"          => "1",
        ];

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindPreg("/var srf_sErr='([^']+)'/ims");

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            return false;
        }

        if ($this->http->ParseForm("fmHF")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm("fmHF")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm("idForm")) {
            $this->http->PostForm();
        }
        $url = $this->http->FindPreg("/Still waiting\? <a href=\"([^\"]+)\"/ims");

        if (isset($url)) {
            $this->http->GetURL($url);
        }
        $error = $this->http->FindSingleNode('//div[@id="signupTitleSection"]');

        if (isset($error)) {
            // We need a little more information about you ...
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            return false;
        }
        $error = $this->http->FindSingleNode('//div[@class="errorMessage"]');

        if (isset($error)) {
            // Enter the Windows Live ID that you used when you signed up for your cashback account.
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            return false;
        }
        $error = $this->http->FindSingleNode('//h2[contains(text(),"Make sure you agree")]/..');

        if (isset($error)) {
            // Make sure you agree Review the updated terms and conditions in the Microsoft service agreement ...
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = substr($error, 0, 80) . "...";

            return false;
        }
        $error = $this->http->FindPreg("/Your account has been temporarily blocked/ims");

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_LOCKOUT;
            $this->ErrorMessage = $error;

            return false;
        }
        $error = $this->http->FindSingleNode("//*[@class='sc_error]");

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = $error;

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->SetBalance($this->http->FindPreg("/<span id=\"ctl00_ContentPlaceHolder1_totalTable_availableBalanceLabel\">\s*\\$([^<]+)</ims"));
        $this->SetProperty("Pending", $this->http->FindPreg("/<span id=\"ctl00_ContentPlaceHolder1_totalTable_pendingBalanceLabel\">([^<]+)<\/span>/ims"));
        $this->SetProperty("Available", $this->http->FindPreg("/<span id=\"ctl00_ContentPlaceHolder1_totalTable_availableBalanceLabel\">([^<]+)<\/span>/ims"));
        $this->SetProperty("InProcess", $this->http->FindPreg("/<span id=\"ctl00_ContentPlaceHolder1_totalTable_processingBalanceLabel\">([^<]+)<\/span>/ims"));
        $this->SetProperty("Rewarded", $this->http->FindPreg("/<span id=\"ctl00_ContentPlaceHolder1_totalTable_paidBalanceLabel\">([^<]+)<\/span>/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://cashbackaccount.bing.com/cashback/signup.aspx";
        $arg["SuccessURL"] = "https://cashbackaccount.bing.com/cashback/signup.aspx";

        return $arg;
    }
}
