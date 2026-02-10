<?php

class TAccountCheckerEpinions extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('http://www.epinions.com/login/');

        if (!$this->http->ParseForm()) {
            return $this->checkErrors();
        }
        $this->SetFormParam("submitted_form", "login_form");
        $this->http->SetInputValue("login_ID", $this->AccountFields["Login"]);
        $this->http->SetInputValue("login_Password", $this->AccountFields["Pass"]);
        $this->SetFormParam("login_form.x", "32");
        $this->SetFormParam("login_form.y", "13");
        $this->SetFormParam("login_form_hidden", "jj8p8DUFc0ArfiwQAzT5Zw6hfKiPKTwkC8PGcNogLJ5Kio5u5X6OyQEH5lOG3Wm/riyAByaxhE6UbW12GI/p65l1pcTxUIG7/Nev/ZMvQXclbGEHbLDxyOF6cagvk1fC");
        $this->SetFormParam("login_page_target", "http://www.epinions.com/");
        $this->http->FormURL = 'http://www0.epinions.com/login/';

        return true;
    }

    public function checkErrors()
    {
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Epinions offline in order to make some changes to the site')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $error = $this->http->FindNodes('//span[@class="rrr"]');

        if (isset($error[0])) {
            throw new CheckException($error[0], ACCOUNT_INVALID_PASSWORD);
        }
        //# Successful access
        if ($this->http->FindSingleNode('//a[@href="/logout/"]/../a[1]/@href', null, true, null, 0)) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($link = $this->http->FindSingleNode("//a[contains(text(), 'Account')]/@href")) {
            $this->http->Log("GET Account URL");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[span[contains(text(), 'Member:')]]/following-sibling::td[1]")));
        //# Reviews Written
        $this->SetProperty("ReviewsWritten", $this->http->FindSingleNode('//span[@class="rkr"][contains(text(),"Reviews Written")]/b'));
        //# Member Visits
        $this->SetProperty("MemberVisits", $this->http->FindSingleNode('//span[@class="rkr"][contains(text(),"Member Visits")]/b'));
        //# Total Visits
        $this->SetProperty("TotalVisits", $this->http->FindSingleNode('//span[@class="rkr"][contains(text(),"Total Visits")]/b'));
        //# Total Earnings
        $this->SetProperty("TotalEarnings", $this->http->FindSingleNode('//span[@class="rkr"][contains(text(),"Total Earnings")]/following::td[1]'));
        //# Redemption / Other
        $this->SetProperty("RedemptionOrOther", $this->http->FindSingleNode('//span[@class="rkr"][contains(text(),"Redemption / Other:")]/following::td[1]'));
        //# Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@class="rkr"][contains(text(),"Balance:")]/following::td[1]'));
        //# Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//span[@class="rkr"][contains(text(),"Member Since:")]/following::td[1]'));

        if ($link = $this->http->FindSingleNode("//a[contains(@href, 'show_~Edit_Profile')]/@href")) {
            $this->http->Log("GET Profile URL");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }
        $name = CleanXMLValue($this->http->FindSingleNode("//input[contains(@name, 'first_name')]/@value")
            . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'last_name')]/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function SetFormParam($a, $b)
    {
        if (isset($this->http->Form[$a])) {
            if ($this->http->Form[$a] == $b) {
                $this->http->Log("Param[$a]:same[$b]");
            } else {
                $this->http->Log("Param[$a]:[{$this->http->Form[$a]}]=>[$b]");
            }
        }
        $this->http->Form[$a] = $b;
    }
}
