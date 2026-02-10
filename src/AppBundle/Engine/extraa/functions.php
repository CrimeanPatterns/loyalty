<?php

class TAccountCheckerExtraa extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->debug = true;
        $this->http->removeCookies();
        $this->http->GetURL("https://www.businessextraa.com/AccountSummaryAction.do");

        if (!$this->http->ParseForm("PublicLoginForm")) {
            return false;
        }
        $this->http->Form['loginId'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];
        $this->http->Form['userAction'] = 'submitlogin';
        $this->http->Form['x'] = '55';
        $this->http->Form['y'] = '9';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $errors = $this->http->XPath->query("//td[@class = 'errorText' and text() != '']");

        if ($errors->length > 0) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = CleanXMLValue($errors->item(0)->nodeValue);

            return false;
        }

        if (preg_match("/registered Travel Manager to access this application/msi", $this->http->Response['body'], $match)) {
            $this->ErrorMessage = "You must be a registered Travel Manager to access Business ExtrAA account";
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;

            return;
        }

        if (preg_match("/We are unable to retrieve or update your personal information at this time/msi", $this->http->Response['body'], $match)) {
            $this->ErrorMessage = "We are unable to retrieve your personal information at this time.";
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;

            return;
        }

        if (preg_match("/We have locked access to your account for security purposes./msi", $this->http->Response['body'], $match)) {
            $this->ErrorMessage = "We have locked access to your account for security purposes.";
            $this->ErrorCode = ACCOUNT_LOCKOUT;

            return;
        }

        return true;
    }

    public function Parse()
    {
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Current Point Balance:')]/following::td[1]"));
        $this->SetProperty("BusinessName", $this->http->FindSingleNode("//td[contains(text(), 'Business Name')]/following::td[1]")); // Business Name
        $this->SetProperty("Number", $this->http->FindSingleNode("//td[contains(text(), 'Business ExtrAA Account #')]/following::td[1]")); // Business ExtrAA Account #
        // Expiration
        if (preg_match_all("/([\d\,]+)\s*points are scheduled to expire ([\d\-]+)/ims", $this->http->Response['body'], $match, PREG_SET_ORDER)) {
            $this->http->Log("Expiration date found");
            $exp = [];

            foreach ($match as $m) {
                $m[2] = strtotime(str_replace("-", "/", $m[2]));

                if ($m[2] !== false) {
                    $exp[$m[1]] = $m[2];
                }
            }
            asort($exp);

            foreach ($exp as $miles=>$e) {
                $this->SetExpirationDate($e);
                $this->SetProperty("MilesToExpire", $miles);

                break;
            }
        }
        $this->http->Log("Expiration date not found");
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.businessextraa.com/AccountSummaryAction.do";

        return $arg;
    }
}
