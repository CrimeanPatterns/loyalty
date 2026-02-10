<?php

class TAccountCheckerEliterewards extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->GetURL('https://www.eliterewards.com/ip-er/app/Account');

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        if (!$tokenName = $this->http->FindPreg('/var tokenName\s?=\s?[\'"]([\w]+)[\'"];/s')) {
            return false;
        }

        if (!$tokenValue = $this->http->FindPreg('/var tokenValue\s?=\s?[\'"]([\w\-0-9]+)[\'"];/s')) {
            return false;
        }
        $this->http->Form[trim($tokenName)] = trim($tokenValue);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = "https://www.eliterewards.com/ip-er/app/Login";

        return $arg;
    }

    public function checkErrors()
    {
        //# We're sorry, the page you are looking for is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 're sorry, the page you are looking for is temporarily unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Elite Rewards program terminated as of 11/1/2015
        if ($message = $this->http->FindPreg("/The Elite Rewards program terminated as of 11\/1\/2015/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//span[normalize-space(@class)='errorheader']/ul/li")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'Logout')]/@href)[1]")) {
            return true;
        }
        //# The username or password you entered is not valid. Please try again.
        if ($message = $this->http->FindPreg("/(The username or password you entered is not valid\.\s*Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The username and password you entered do not match our records
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The username and password you entered do not match our records.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // There is a problem with your account. Please contact customer service at 1-877-528-9008.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'There is a problem with your account.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your account is locked.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Your account is locked\.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//b[contains(text(), 'Member:')]", null, true, '/Member:\s*([^<]+)/ims')));
        //# Your Goal
        $this->SetProperty("Goal", $this->http->FindSingleNode("//input[@id = 'userGoal']/@value"));
        //# Balance - Your Points
        $this->SetBalance($this->http->FindSingleNode("//input[@id = 'pts']/@value"));
        //# Lifetime points earned
        $this->SetProperty("LifetimePointsEarned", $this->http->FindSingleNode("//span[@class = 'number_points']"));
        //# Lifetime points redeemed
        $this->SetProperty("LifetimePointsRedeemed", $this->http->FindSingleNode("//span[@class = 'number_point_other']"));
        //# Points Earned this year
        $this->SetProperty("EarnedThisYear", $this->http->FindSingleNode("(//input[@name = 'userPoint']/@value)[1]"));
        //# Points Earned last year
        $this->SetProperty("EarnedLastYear", $this->http->FindSingleNode("(//input[@name = 'userPoint']/@value)[2]"));
        //# enrolled online as of ...
        $this->SetProperty("MemberSince", $this->http->FindPreg("/enrolled online as of\s*([^<\)]+)/ims"));
        //# Expiration Date
        $nodes = $this->http->XPath->query("//div[@class = 'expiring_points']/div[div[2]]");
        $this->http->Log("Total nodes found: " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $exp = $this->http->FindSingleNode('div[1]', $nodes->item($i), true, '/EXPIRING\s*ON\s*([^<\:]+)/ims');
                $pointsToExpire = $this->http->FindSingleNode('div[2]', $nodes->item($i));

                if (strtotime($exp) && $pointsToExpire > 0) {
                    $this->SetExpirationDate(strtotime($exp));
                    $this->SetProperty("PointsToExpire", $pointsToExpire);

                    break;
                }// if (strtotime($exp) && $pointsToExpire > 0)
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if ($nodes->length > 0)
    }
}
