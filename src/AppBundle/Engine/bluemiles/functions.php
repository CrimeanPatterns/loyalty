<?php

class TAccountCheckerBluemiles extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->ParseForms = false;
        $this->http->removeCookies();
        $this->http->GetURL("https://www.tuifly.com/en/mein-TUIfly/");
        $this->http->Form = [];
        $this->http->Form['ControlGroupGlobalLoginView%24GlobalLoginViewExMemberLogin%24TextBoxUserID'] = $this->AccountFields['Login'];
        $this->http->Form['ControlGroupGlobalLoginView%24GlobalLoginViewExMemberLogin%24PasswordFieldPassword'] = $this->AccountFields['Pass'];
        $this->http->Form['returnURL'] = 'https://www.tuifly.com/en/mein-TUIfly/';
        $this->http->Form['culture'] = 'en-GB';
        $this->http->FormURL = 'https://www.tuifly.com/GlobalLogin.aspx';

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = "https://www.tuifly.com/en/mein-TUIfly/";

        return $arg;
    }

    public function Login()
    {
        $this->http->PostForm();

        if (preg_match("/<div id=\"errorSectionMainContent\" class=\"errorSectionMainContent\">\s*<div class=\"formRow\">([^<]+)</ims", $this->http->Response["body"], $arMatches)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $arMatches[1];

            return false;
        }
        $this->http->GetURL('https://www.tuifly.com/NewskiesEndpointMemberInformation.aspx');
        $t = json_decode($this->http->Response["body"]);
        $this->http->Log(var_export($t, true));

        if (isset($t->Success) && $t->Success) {
            if (isset($t->Data->Name) && is_object($t->Data->Name)) {
                $this->SetProperty("Name", implode(" ", (array) $t->Data->Name));
            }

            return true;
        }

        if (isset($t->Message)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $t->Message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $this->SetBalanceNA();

        return;
        $this->http->GetURL('https://www.tuifly.com/en/mein-TUIfly/ueberblick_meilen_sammeln.html');

        if (preg_match("/<dt>Your bonus miles<\/dt>\s*<dd>([^<]+)<\/dd>/ims", $this->http->Response["body"], $match)) {
            $this->SetBalance($match[1]);
        }

        if (preg_match("/<dt>Your status miles<\/dt>\s*<dd>([^<]+)<\/dd>/ims", $this->http->Response["body"], $arMatches)) {
            $this->SetProperty("StatusMiles", $arMatches[1]);
        }

        if (preg_match("/<dt>Your bonus miles<\/dt>\s*<dd>([^<]+)<\/dd>/ims", $this->http->Response["body"], $arMatches)) {
            $this->SetProperty("BonusMiles", $arMatches[1]);
        }

        if (preg_match("/<dt>bluemiles card number:<\/dt>\s*<dd>([^<]+)<\/dd>/ims", $this->http->Response["body"], $arMatches)) {
            $this->SetProperty("Number", $arMatches[1]);
        }

        if (preg_match("/<dt>Card type:<\/dt>\s*<dd>([^<]+)<\/dd>/ims", $this->http->Response["body"], $arMatches)) {
            $this->SetProperty("CardType", $arMatches[1]);
        }

        if (preg_match("/<dt>Name:<\/dt>\s*<dd>([^<]+)<\/dd>/ims", $this->http->Response["body"], $arMatches)) {
            $this->SetProperty("Name", $arMatches[1]);
        }
    }
}
