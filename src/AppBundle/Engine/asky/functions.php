<?php

class TAccountCheckerAsky extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://ffkp.loyaltyplus.aero/kployalty/index.jsf");

        if (!$this->http->ParseForm("form_login")) {
            return false;
        }

        $this->http->SetInputValue("form_login:userName", $this->AccountFields['Login']);
        $this->http->SetInputValue("form_login:password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("form_login", "form_login");
        $this->http->SetInputValue("javax.faces.partial.ajax", "true");
        $this->http->SetInputValue("javax.faces.source", "form_login:button_submit");
        $this->http->SetInputValue("javax.faces.partial.execute", "@all");
        $this->http->SetInputValue("javax.faces.partial.render", "form_login");
        $this->http->SetInputValue("form_login:button_submit", "form_login:button_submit");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://ffkp.loyaltyplus.aero/kployalty/main.jsf";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($redirect = $this->http->FindPreg("/redirect url=\"([^\"]+)/")) {
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logoff')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@class, 'ui-messages-error-summary')]")) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Login Failed') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Available Miles
        $this->SetBalance($this->getValue("My Balance"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(., 'Membership Number:')]/preceding-sibling::span/label")));
        // Member ID
        $this->SetProperty("MemberID", $this->getValue("Membership Number:", '/:\s*([^<]+)/ims'));
        // Your current tier level will expire on
        $this->SetProperty("CardValidityPeriod", $this->http->FindSingleNode("//label[contains(normalize-space(.), 'Your current tier level will expire')]/following-sibling::label"));
        // Tier level
        $this->SetProperty("TierLevel", $this->getValue("My Tier"));
        // Miles to Expire ... on ...
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode('//span[contains(text(), "Miles to Expire")]/preceding-sibling::div/text()[1]'));
        $exp = $this->http->FindSingleNode('//span[contains(text(), "Miles to Expire")]/preceding-sibling::div/label', null, true, "/on\s*(.+)/");

        if ($exp) {
            $exp = $this->ModifyDateFormat($exp);

            if (!empty($exp) && strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
        }
    }

    public function getValue($name, $regexp = null)
    {
        if ($regexp) {
            return $this->http->FindSingleNode("//span[contains(text(), '" . $name . "')]", null, true, $regexp);
        } else {
            return $this->http->FindSingleNode("//span[contains(., '" . $name . "')]/preceding-sibling::div[1]");
        }
    }
}
