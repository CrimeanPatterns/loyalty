<?php

class TAccountCheckerAmbest extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://register.am-best.com/default.asp");

        if (!$this->http->ParseForm(null, '//form[@action = "noframes.asp"]')) {
            return false;
        }

        $this->http->SetInputValue("cardnumber", $this->AccountFields['Login']);
        $this->http->SetInputValue("pin", $this->AccountFields['Pass']);
        $this->http->SetInputValue("f", "balance.asp");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//a[contains(text(), 'Update Your Account')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//font[contains(normalize-space(), "Card Number, ' . $this->AccountFields['Login'] . ', was not found.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
        if ($this->http->currentUrl() == 'http://register.am-best.com/noframes.asp?t=m') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//b[contains(text(), 'Cardholder Information for')]/parent::td/following::td[1]")));
        // Registration Status
        $this->SetProperty("Registration", $this->http->FindSingleNode("//b[contains(text(), 'Registration Status')]/parent::td/following::td[1]"));
        // Total Purchases
        $this->SetProperty("Purchases", $this->http->FindSingleNode("//b[contains(text(), 'Total Purchases')]/parent::td/following::td[1]"));
        // Total Redemptions
        $this->SetProperty("Redemptions", $this->http->FindSingleNode("//b[contains(text(), 'Total Redemptions')]/parent::td/following::td[1]"));
        // Current Rewards Card Point Balance
        $this->SetBalance($this->http->FindSingleNode("//b[contains(text(), 'Current Rewards Card Point Balance')]/parent::td/following::td[1]"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://register.am-best.com/default.asp';

        return $arg;
    }
}
