<?php

// this file is unused, we parsed mobile version some time ago

class TAccountCheckerCitybankMobile extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->setDefaultHeader("User-Agent", "android");
        $this->http->GetURL("https://creditcards.citi.com/");

        if (!$this->http->ParseForm("frmAdvancedLoginCB")) {
            return false;
        }
        $this->http->Form['txtbxUserIdCc'] = $this->AccountFields['Login'];
        $this->http->Form['txtbxPassCc'] = $this->AccountFields['Pass'];
        $this->http->Form['RemIdcheckboxgroupCc'] = '0';
        $this->http->Form['button40700582337999event_'] = 'Sign on';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode("//div[contains(@class, 'lblRed')]/label", null, false, null, 0);

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//label[@style = 'color:#CC0000']/b", null, false, null, 0);
        }

        if (isset($error)) {
            $this->ErrorMessage = $error;
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;

            return false;
        }
        $error = $this->http->FindSingleNode("//div[@class = 'warning']");

        if (isset($error)) {
            $this->ErrorMessage = $error;
            $this->ErrorCode = ACCOUNT_LOCKOUT;

            return false;
        }

        return $this->http->FindSingleNode('//input[contains(@value, "Sign Off")]') !== null;
    }

    public function Parse()
    {
        $this->SetProperty("Name", $this->http->FindSingleNode('//label[contains(text(), "Welcome")]', null, false, '/Welcome\s+(.*)/ims'));
        $text = StripTags($this->http->Response['body']);

        if (preg_match_all('/\*\s+([^:]+):\s*(\d{4})\s*Rewards\s*Earned[\s\$]*(\d+)/ims', $text, $matches, PREG_SET_ORDER)) {
            $this->SetBalanceNA();
            $subAccounts = [];

            foreach ($matches as $match) {
                $subAccounts[] = [
                    "Code"        => $match[2],
                    "DisplayName" => $match[1] . " " . $match[2],
                    "Balance"     => $match[3],
                ];
            }
            $this->SetProperty("SubAccounts", $subAccounts);
        }

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://creditcards.citi.com/";

        return $arg;
    }
}
