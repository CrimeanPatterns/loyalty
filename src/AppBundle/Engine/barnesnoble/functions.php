<?php

class TAccountCheckerBarnesnoble extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.barnesandnoble.com/account/manage/memberships/associate-membership.jsp';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'barnesnobleStamps')) {
            return parent::FormatBalance($fields, $properties);
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setUserAgent("NinjaBot"); // "Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)" workaroud
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.barnesandnoble.com/account/login-frame-ra.jsp?tplName=login&parentUrl=https%3a%2f%2fwww.barnesandnoble.com%2faccount%2fmanage%2fmemberships%2fassociate-membership.jsp&isCheckout=&isNookLogin=&isEgift=&customerkey=&intent=&emailSub=&membershipIDLink=false');

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }

        $this->http->setCookie('_abck', "C8ECF51D2A3BA45062D30F5ECA4A97BD~0~YAAQaGQwFxsoR3KQAQAA4cU2fAzKxugi784gvQXQJIhPgCcoJru6v0OKkp8hVJ0We7t7gO8givzwFYgRWRidgXyYFi81AnHGEb7GdmqCIghNkzqcJYwLi9JoJZ9ghKlf3WwFPy5IrHgMTfqZiRkfkyjpgaqtdIM3fseH3lRmVg19XXM5mXNmxz9nTyq3LGSh1MW2oyKRVJ9md+/txn0Ksi2HPDtQY2fKs8OKivdyUF02NwuaQVbImmOor7noWlSsk5n6AGcJdqeDvleMIXKGw2YN4uJx2Krgozh96FO+LxXsGWfkhg9yv4tnawCDL7GbjK6CdJBuEUnJJrES7sp9Dd7vHQ1H15mBS7Rv1017AHHsHG5oXlE4hJ5YBBplxEwiGCRCofrwmWyXrOc/q2jP9OoS7UyASEbkE7w3qFRKI5gsHNe+uW2MaNZ8meFt~-1~||0||~-1", ".barnesandnoble.com");

        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.value.login', $this->AccountFields['Login']);
        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.value.password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.value.autoLogin', "true");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();
        $success = $response->success ?? null;
        $email = $response->data->uid ?? null;

        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[Success]: {$success}");

        if ($success === true) {
            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->http->FindSingleNode('//i[contains(text(), "Join B&N Rewards") or contains(text(), "Join Rewards")]')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $message = $response->response->items[0] ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Your email and password combination does not match our records. Please try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'An account for this email was created when you enrolled in Membership.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'inactiveUser') {
                throw new CheckException("Your password has expired due to inactivity. Please reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'userAccountLocked') {
                throw new CheckException("Your account has been locked due to several unsuccessful login attempts. Please reset your password.", ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - REWARDS BALANCE
        $balance = $this->http->FindSingleNode('//p[normalize-space()="REWARDS BALANCE"]/../div/p[contains(@class,"reward-num")][not (contains(@class,"reward-number"))] | //p[normalize-space()="REWARDS BALANCE"]/preceding-sibling::p[contains(@class, "member-validate-reward")]');
        $this->SetBalance($balance);
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//p[normalize-space()="Member"]/following-sibling::p[1]')));
        // Member Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[contains(@class, "d-lg-none")]//p[contains(normalize-space(),"Member Number")]/preceding-sibling::p'));
        // MEMBER SINCE
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//*[contains(@class,"d-lg-none")]//p[normalize-space()="Member Since"]/../p[contains(@class,"member-contents")]/b'));
        // STAMPS BALANCE
        $stamps = $this->http->FindSingleNode('//p[normalize-space()="STAMPS BALANCE"]/../div/p[contains(@class,"reward-num")] | //div[contains(@class, "d-lg-block")]//div[p[normalize-space()="STAMPS BALANCE"]]/preceding-sibling::div/p[contains(@class,"reward-num")]');

        if (!is_null($stamps)) {
            $this->AddSubAccount([
                "Code"        => "barnesnobleStamps",
                "DisplayName" => "Stamps",
                "Balance"     => $stamps,
            ]);
        }
        // Stamps till next Reward
        $this->SetProperty('StampsTillNextReward', $this->http->FindSingleNode('//p[contains(@class,"member-contents") and contains(@class, "d-lg-block")]/b[contains(text(),"stamp")] | //p[contains(@class,"stamp-message")]/b[contains(text(),"stamp")]', null, true, "/(\d+) stamps?/ims"));
        // REWARDS REDEEMED
        $RedeemedCurrency = $this->http->FindSingleNode('//*[contains(@class,"d-lg-none")]//p[contains(text(),"REWARDS REDEEMED*")]/../div/p[contains(@class,"reward-number")]');
        $RedeemedValue = $this->http->FindSingleNode('//*[contains(@class,"d-lg-none")]//p[contains(text(),"REWARDS REDEEMED*")]/../div/p[contains(@class,"reward-num")][not(contains(@class,"reward-number"))]');
        $this->SetProperty('Redeemed', $RedeemedCurrency . $RedeemedValue);

        if ($status = $this->http->FindSingleNode("//img[@class = 'image-resize']/@src")) {
            $status = basename($status);
            $this->logger->debug(">>> Status: " . $status);

            switch ($status) {
                case 'RewardsCard@2x.jpg':
                    $this->SetProperty('Status', "Rewards");

                    break;

                case 'premium-card-new.jpg':
                    $this->SetProperty('Status', "Premium");

                    break;

                default:
                    $this->sendNotification("refs #22770 - newStatus: $status // MI");
            }// switch ($status)
        }// if ($status = $this->http->FindSingleNode("//img[@class = 'image-resize']/@src"))

        // Status Expiration
        $statusExp = $this->http->FindSingleNode("//div[contains(@class,'card-details')]//p[contains(text(),'Expiration Date')]/preceding-sibling::p");

        if ($statusExp) {
            $this->SetProperty("StatusExpiration", $statusExp);
        }

        $this->giftCardCheck();
    }

    private function giftCardCheck()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.barnesandnoble.com/account/giftcard/manage/", [], 20);
        $this->http->RetryCount = 2;

        $giftCards = $this->http->XPath->query('//table[contains(@class, "manage-gift-card")]/tbody/tr[td[contains(@class, "giftcard-balance")]]');
        $this->logger->debug("Total {$giftCards->length} gift cards were found");

        foreach ($giftCards as $giftCard) {
            $displayName = $this->http->FindSingleNode('td[1]', $giftCard);
            $balance = $this->http->FindSingleNode('td[contains(@class, "giftcard-balance")]', $giftCard);

            if (!isset($balance)) {
                continue;
            }

            $this->AddSubAccount([
                "Code"        => "giftCard" . md5($displayName),
                "DisplayName" => $displayName,
                "Balance"     => $balance,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindNodes('//i[contains(text(),"Your Rewards")]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
