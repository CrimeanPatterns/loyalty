<?php

class TAccountCheckerFlyking extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // TODO: remove it in the future
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.kingclub.me/CRANE_IT_WEB/index.jsp");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('memberID', $this->AccountFields['Login']);
        $this->http->SetInputValue('pinCode', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        //# Website is not available
        if ($message = $this->http->FindPreg("/is not available\./ims")) {
            throw new CheckException('Fly Kingfisher website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //# Error - HTTP Status...
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status')]")
            || $this->http->FindPreg("/(HTTP Status 404)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // TODO: Fly Kingfisher website had a hiccup (it need remove in the future)
        if ($this->http->FindSingleNode("//span[contains(text(), 'Your session has timed out. Redirecting the page to My Account')]")) {
            $this->http->GetURL("https://www.kingclub.me/CRANE_IT_WEB/account.jsp");

            if ($this->http->FindSingleNode("//span[contains(text(), 'Your session has timed out. Redirecting the page to My Account')]")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Invalid login or password
        if ($message = $this->http->FindSingleNode("//font[@color = 'red']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.kingclub.me/CRANE_IT_WEB/ViewAccount.jsp");

        $this->checkErrors();

        return true;
    }

    public function Parse()
    {
        //# Balance - King Miles Balance
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'King Miles Balance')]/following::td[1]"));
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[contains(text(), 'Membership Number')]/preceding-sibling::td")));
        //# Membership Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//td[contains(text(), 'Membership Number')]", null, true, "/Membership\s*Number\s*:\s*([^<]+)/ims"));
        //# King Miles Redeemed Till Date
        $this->SetProperty("MilesRedeemed", $this->http->FindSingleNode("//td[contains(text(), 'King Miles Redeemed Till Date')]/following::td[1]"));
        //# King Miles Transferred
        $this->SetProperty("MilesTransferred", $this->http->FindSingleNode("//td[contains(text(), 'King Miles Transferred')]/following::td[1]"));
        //# King Miles Purchased
        $this->SetProperty("MilesPurchased", $this->http->FindSingleNode("//td[contains(text(), 'King Miles Purchased')]/following::td[1]"));

        if ($this->http->currentUrl() != 'https://www.kingclub.me/CRANE_IT_WEB/ViewAccount.jsp') {
            $this->http->FilterHTML = false;
            $this->http->GetURL('https://www.kingclub.me/CRANE_IT_WEB/ViewAccount.jsp');
        }
        //# Sector points
        $this->SetProperty('SectorPoints12Month', $this->http->FindSingleNode('//tr[td[contains(text(), "Sector Points")]]/following::td[3]', null, true, null, 0));
        //# Status miles
        $this->SetProperty('StatusMiles12Month', $this->http->FindSingleNode('//tr[td[contains(text(), "Status Miles")]]/following::td[3]', null, true, null, 0));

        // refs #4057
        $nodes = $this->http->XPath->query("//tr[td[contains(text(), 'Recent Activity')]]/following-sibling::tr");

        for ($i = $nodes->length; $i > 0; $i--) {
            $node = $nodes->item($i);
            //# Expiration Date
            $expiration = $this->http->FindSingleNode('td[1]', $node);
            //# Miles to expire
            $milesToExpire = $this->http->FindSingleNode('td[5]', $node);

            if (($milesToExpire > 0) && ($expiration = strtotime($expiration))) {
                if (!isset($expdate)) {
                    $expdate = $expiration;
                    $totalMilesToExpire = str_replace(",", ".", $milesToExpire);
                } elseif ($expdate == $expiration) {
                    $totalMilesToExpire += str_replace(",", ".", $milesToExpire);
                }
                $this->http->Log("Activity Date - " . var_export(date("d.m.Y", $expdate), true), true);
                $this->SetProperty("MilesToExpire", str_replace(".", ",", $totalMilesToExpire));
                $this->SetExpirationDate(strtotime("+36 month", $expdate));
                $this->http->Log("Expiration Date - " . var_export(date("d.m.Y", strtotime("+36 month", $expdate)), true), true);
            }
        }

        //# Status
        $this->http->GetURL("https://www.kingclub.me/CRANE_IT_WEB/MainContainer.jsp");
        $status = $this->http->FindPreg('/<img src=\"Design_tier\/([A-Z]+)\/images/ims');
        $status = beautifulName($status);
        $this->SetProperty("Status", $status);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.kingclub.me/CRANE_IT_WEB/account.jsp';

        return $arg;
    }
}
