<?php

class TAccountCheckerCat extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Your credentials are invalid", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.cityairporttrain.com/en/login");

        $csrf = $this->http->FindSingleNode("//meta[@name = 'csrf-token']/@content");

        if (!$csrf) {
            return false;
        }

        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "_token"   => [],
        ];
        $headers = [
            "Accept"           => "application",
            "Content-Type"     => "application/json",
            "x-csrf-token"     => $csrf,
            "x-requested-with" => "XMLHttpRequest",
            "x-xsrf-token"     => $this->http->getCookieByName("XSRF-TOKEN"),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.cityairporttrain.com/en/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $this->http->JsonLog();

        // provider workaround
        if ($this->http->FindPreg("/^\/bonusclub\/my-credits$/")) {
            $this->http->GetURL("https://www.cityairporttrain.com/en/bonusclub/my-credits");
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout') and contains(text(), 'Logout')]")) {
            return true;
        }
        // Your credentials are invalid
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Your credentials are invalid')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(@class, 'welcome name')]", null, true, "/([^<\!]+)/ims")));
        // Balance - Current Status
        $this->SetBalance($this->http->FindSingleNode("//h1[contains(text(), 'Current Status:')]//span", null, true, self::BALANCE_REGEXP));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg['CookieURL'] = 'https://www.cityairporttrain.com/SpecialPages/Login.aspx?ReturnUrl=%2fSpecialPages%2fC-Club-Portal%2fBenefits%2fHotel---Kulinarik.aspx';
//        $arg['SuccessURL'] = 'https://www.cityairporttrain.com/SpecialPages/C-Club-Portal/Benefits/Hotel---Kulinarik.aspx';
        return $arg;
    }
}
