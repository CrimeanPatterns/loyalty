<?php

class TAccountCheckerAvistar extends TAccountChecker
{
    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $result = Cache::getInstance()->get('avistar_locations');

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select a location...",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://www.avistarparking.com/customer/login/");
            $nodes = $browser->XPath->query("//select[@id = 'location_login-page-widget']/option[@value != '']");

            for ($n = 0; $n < $nodes->length; $n++) {
                $location = CleanXMLValue($nodes->item($n)->nodeValue);
                $code = CleanXMLValue($nodes->item($n)->getAttribute("value"));

                if ($location != "" && $code != "") {
                    $arFields['Login2']['Options'][$code] = $location;
                }
            }// for ($n = 0; $n < $nodes->length; $n++)

            if (count($arFields['Login2']['Options']) > 1) {
                Cache::getInstance()->set('avistar_locations', $arFields['Login2']['Options'], 3600 * 24);
            } else {
                $this->sendNotification("avistar - locations aren't found", 'all', true, $browser->Response['body']);
            }
        }
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Login is not a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->getURL('https://www.avistarparking.com/customer/login/');

        if (!$this->http->ParseForm("login_page_0")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.avistarparking.com/wp-content/plugins/netPark/ajax.php';
        $this->http->SetInputValue("login", $this->AccountFields['Login']);
        $this->http->SetInputValue("loc", $this->AccountFields['Login2']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg["NoCookieURL"] = true;
        //		$arg["PostDoneURL"] = 'http://www.avistarparking.com/fasttrack/';
        //		$arg["SuccessURL"] = 'http://www.avistarparking.com/fasttrack/';
        return $arg;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindPreg("/We are currently performing scheduled maintenance on our website/")) {
            throw new CheckException("We are currently performing scheduled maintenance on our website", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->JsonLog();
        //# Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindPreg("/rightPasswordLabel/")) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }
        //# Account and password do not match
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Account and password do not match')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

//        if (in_array($this->AccountFields['Login'], ['jameshuwsmith@gmail.com', 'dick@burkhalters.net', 'leo.weinberg@yahoo.com']))
        if ($this->http->Response['code'] == 403) {
            throw new CheckException("Account and password do not match", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->getURL('http://www.avistarparking.com/points_balance.php');
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//li[@id = 'toggle']/a[contains(@href, 'profile')][1]")));
        //# Balance - My Total Points
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'BALANCE:')]/following-sibling::td[1]", null, true, '/([\d\,\.]+)/ims'));
    }
}
