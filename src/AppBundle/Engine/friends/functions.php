<?php

// Feature #4092
class TAccountCheckerFriends extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://friends.freshandeasy.com/microsite/Signin.aspx');

        if (!$this->http->ParseForm("joinform")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        $this->http->Form['chkRememberMe'] = 'on';
        $this->http->Form['LoginButton'] = 'Sign in';

        return true;
    }

    public function checkErrors()
    {
        //# Oops! Your request wasn't processed.
        if ($error = $this->http->FindPreg('#(Oops!\s*Your request wasn\'t processed\.)#ims')) {
            throw new CheckException("Oops! Your request wasn't processed. Please try again.", ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize for the inconvenience as we update the Friends site
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We apologize for the inconvenience as we update the Friends site')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Error 503. The service is unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Invalid credentials
        if ($error = $this->http->FindPreg('#(Invalid email address or password)#ims')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/log-out.png/ims")) {
            return true;
        }
        // Please reset your password
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Please reset your password')]")) {
            throw new CheckException("Fresh and Easy (Friends) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
        }
        // For security reasons, your account has been locked.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'For security reasons, your account has been locked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // hard code
        $accountIDs = ArrayVal($this->AccountFields, 'RequestAccountID', $this->AccountFields['AccountID']);

        if ($this->http->FindSingleNode('//input[@name = "UserName"]/@value') == $this->AccountFields["Login"]
            && $this->http->ParseForm('joinform')
            && in_array($accountIDs, [1618042, 1471549])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // current points
        $this->SetBalance($this->http->FindSingleNode('//span[@id="ctl00_MenuContent_mnucontrol_lbltotalpoints"]'));
        // bonus coupons on card
        $this->SetProperty("BonusCouponsOnCard", $this->http->FindSingleNode('//span[@id="ctl00_MenuContent_mnucontrol_lblAvailablecoupons"]'));
        $this->http->GetURL('https://friends.freshandeasy.com/Microsite/MyRewards.aspx');
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@id="ctl00_MenuContent_mnucontrol_lblName"]')));
        // points will expire on ...
        if ($exp = strtotime($this->http->FindSingleNode('//span[@id="ctl00_MainContent_lblPointsExpiry"]', null, true, '#points will expire on (.*?)$#ims'))) {
            $this->SetExpirationDate($exp);
        }

        // Friends rewards balance on my card
        $FRB = $this->http->FindSingleNode('//span[@id="ctl00_MainContent_lblRewards"]');
        $exp = $this->http->FindSingleNode('//span[@id="ctl00_MainContent_lblRewardsExpiry"]', null, true, "/expire on\s*([^<]+)/ims");
        $expBalance = $this->http->FindSingleNode('//span[@id="ctl00_MainContent_lblRewardsExpiry"]', null, true, "/([\$\d\.\,]+)/ims");

        if (isset($FRB/*, $exp, $expBalance) && strtotime($exp*/)) {
            $subAccounts = [
                [
                    'Code'            => 'FriendsFriendsRewards',
                    'DisplayName'     => "Friends rewards balance on my card",
                    'Balance'         => $FRB,
                    //                    'ExpiringBalance' => $expBalance,
                    //                    'ExpirationDate'  => strtotime($exp)
                ],
            ];
            $this->SetProperty("CombineSubAccounts", false);
            $this->SetProperty("SubAccounts", $subAccounts);
        } elseif ($FRB > 0) {
            $this->sendNotification("Fresh and Easy (Friends). New Valid Account");
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'FriendsFriendsRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
        }
    }
}
