<?php

class TAccountCheckerFlowers extends TAccountChecker
{
    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login'] == 'test@sasilevi.com"><img src=') {
            throw new CheckException("Email ID is Invalid.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->http->GetURL('https://www.1800flowers.com/');

        if ($logonURL = $this->http->FindSingleNode("//input[@name = 'LogonURL']/@value")) {
            $this->http->GetURL($logonURL . "&origStoreId=20051&URL=AjaxLogonForm%3FWcUseHttps%3Dtrue%26MyAccount%3DY%26catalogId%3D13302%26langId%3D-1%26langId%3D-1%26origStoreId%3D20051%26storeId%3D20051%26storeId%3D20051%26remember%3Dtrue&krypto=e7LYCxekxd9ToFUpd9Fa2lX%2FRIa56SWdepUZCEszHVDfS%2B3Y%2Bz6yueOWBJz%2BW7z2&ddkey=https:AjaxLogonForm");
        } elseif ($logonURL = $this->http->FindPreg("/MainJS.HeaderUrlJSON.LogonURL\s*=\s*'([^\']+)/")) {
            $this->http->GetURL($logonURL);
        }

        if (!$this->http->ParseForm("Logon")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('logonId', $this->AccountFields['Login']);
        $this->http->SetInputValue('logonPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue("x", "1");
        $this->http->SetInputValue("y", "1");

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            // The page you requested is unavailable. Try using the search box at the top of the page. (AccountID: 4098392)
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "The page you requested is unavailable. Try using the search box at the top of the page.")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if ($message = $this->http->FindSingleNode("//span[@class='medium-error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The e-mail address and password you entered do not match any accounts on record.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'The e-mail address and password you entered do not match any accounts on record.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The email address and password you entered do not match any accounts on record.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'The email address and password you entered do not match any accounts on record.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter valid E-mail Address.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Please enter valid E-mail Address.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Email ID is Invalid.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Email ID is Invalid')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//input[@id = 'RewardslogonId']/@value")) {
            return true;
        }
        // Your registration is pending approval. You are not authorized to log on at this time.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Your registration is pending approval.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $uid = $this->http->FindSingleNode("//input[@id = 'RewardsAPIKey']/@value");
        $authToken = $this->http->FindSingleNode("//input[@id = 'RewardsAuthToken']/@value");
        $email = $this->http->FindSingleNode("//input[@id = 'RewardslogonId']/@value");

        if (isset($uid, $authToken, $email)) {
            $this->http->FilterHTML = false;
            $this->http->GetURL("https://loyalty.500friends.com/customers/auth?uuid={$uid}&&auth_token={$authToken}&&email={$email}&&auto_resize=true&");
            $fullName = $this->http->FindSingleNode("(//div[contains(text(), 'Status:')]/preceding-sibling::div[1])[1]");
            $this->http->Log("Full Name: $fullName");

            if (isset($fullName) && (empty($name) || strstr($fullName, $name))) {
                $this->SetProperty("Name", beautifulName($fullName));
            }
            // Tier Level
            $this->SetProperty("TierLevel", $this->http->FindSingleNode("(//div[contains(text(), 'Status:')])[1]", null, true, "/Status\s*:\s*([^<]+)/ims"));
            // Tier expires
            $this->SetProperty("TierExpires", $this->http->FindSingleNode("(//div[contains(text(), 'Tier expires:')])[1]", null, true, "/expires\s*:\s*([^<]+)/ims"));
            // Lifetime Balance
            $this->SetProperty("LifetimeBalance", $this->http->FindSingleNode("(//div[contains(text(), 'Lifetime Balance:')]/strong)[1]"));
            // Balance - Points Balance
            $this->SetBalance($this->http->FindSingleNode("(//div[contains(text(), 'Points Balance')]/following-sibling::div[1])[1]", null, true, "/[\d\,\.\-\s]+/ims"));
            // Errors on the website
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                // An error has occurred.
                if ($this->http->FindSingleNode("//p[contains(text(), 'Invalid auth token. Please reload the page.')]")) {
                    throw new CheckException("You have not yet signed up for your FREE Fresh Rewards account.", ACCOUNT_PROVIDER_ERROR);
                }
                // Membership cancelled
                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Membership cancelled')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Join Fresh Rewards® Today!
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Join Fresh Rewards® Today!')]")
                    || $this->http->FindSingleNode("//h1[contains(text(), 'Join Celebrations Rewards℠ Today!')]")) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                // An error has occurred.
                if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'An error has occurred.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        }// if (isset($uid, $authToken, $email))
    }
}
