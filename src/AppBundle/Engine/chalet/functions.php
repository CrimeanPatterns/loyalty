<?php

class TAccountCheckerChalet extends TAccountChecker
{
    public function LoadLoginForm()
    {
        throw new CheckException("On April 16th, 2016 Sport Chalet began the process of closing all of our stores and stopped selling merchandise online. While our online store is no longer available, all Sport Chalet  stores will remain open for several weeks, offering customers the opportunity to use their remaining rewards and gift cards, and to take advantage of great sales.", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->GetURL("https://www.sportchalet.com/actionpass");

        if (!$this->http->ParseForm("dwfrm_login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue($this->http->FindSingleNode("//form[@id = 'dwfrm_login']//input[contains(@name, 'dwfrm_login_username_')]/@name"), $this->AccountFields['Login']);
        $this->http->SetInputValue('dwfrm_login_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('dwfrm_login_login', "Log in");

        return true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'chaletRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return parent::FormatBalance($fields, $properties);
        }
    }

    public function checkErrors()
    {
        // This site is no longer active!
//        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'This site is no longer active!')]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, the information you provided does not match our records. Check your spelling and try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = CleanXMLValue($this->http->FindSingleNode("//span[contains(@class, 'account-customer-name')]/text()[1]"));

        if (empty($name)) {
            $name = CleanXMLValue(implode(' ', $this->http->FindNodes("//span[contains(@class, 'account-customer-name')]/span")));
        }

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Points Balance:')]/span"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member?
            if ($this->http->FindSingleNode("//span[contains(text(), 'ACTION PASS POINT/REWARD LOOKUP IS TEMPORARILY UNAVAILABLE.')]")) {
                $this->SetBalanceNA();
            }
            // not a member
            if ($this->http->FindSingleNode("//span[contains(text(), 'Join Now')]")
                && $this->http->FindSingleNode("//a[contains(@href, 'rewards-terms-conditions')]/@href")
                && !empty($this->Properties['Name'])) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_WARNING);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // SubAccounts - Rewards     //refs #7428
        $nodes = $this->http->XPath->query("//div[@id = 'rewardsTable' and contains(@class, 'desktop')]/div[@class = 'rewardRow']");
        $this->http->Log("Total rewards found: " . $nodes->length);

        for ($i = 0; $i < $nodes->length; $i++) {
            $code = $this->http->FindSingleNode('div[@class = "rewardCol"]', $nodes->item($i));
            $exp = $this->http->FindSingleNode('div[@class = "dateCol"]', $nodes->item($i));
            $status = $this->http->FindSingleNode('div[@class = "statusCol"]', $nodes->item($i));
            $balance = $this->http->FindSingleNode('div[@class = "amountCol"]', $nodes->item($i), true, "/[\d\.\,]+/ims");

            if (strtolower($status) == 'available' && strtotime($exp) && strtotime($exp) > time()) {
                $subAccounts[] = [
                    'Code'           => 'chaletRewards' . $code,
                    'DisplayName'    => "Reward Cert # " . $code,
                    'Balance'        => $balance,
                    'ExpirationDate' => strtotime($exp),
                ];
            }
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($subAccounts)) {
            //# Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            $this->http->Log("Total subAccounts: " . count($subAccounts));
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))

        if ($this->ErrorCode != ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.sportchalet.com/account");
            // Action Pass™ Number
            $this->SetProperty("ActionPassNumber", $this->http->FindSingleNode("//div[@class = 'ap-member-number']/div[2]"));
        }// if ($this->ErrorCode != ACCOUNT_ENGINE_ERROR)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.sportchalet.com/actionpass';

        return $arg;
    }
}
