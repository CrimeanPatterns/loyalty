<?php

class TAccountCheckerChangi extends TAccountChecker
{
    private string $apikey = '4_tMrLlH1Jt0XJ-c54fMOOSA';
    private string $sdk = "js_latest";
    private string $context = 'R2649647561';
    private string $clientId = '7ZXSSYrsoi_QJ_QN-HgW6ihN';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://rewards.changiairport.com/en/dashboard.html');

        /*
        if (!$this->http->ParseForm(null, '//form[@id = "gigya-login-form"]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember_me', "on");
        */

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

//        $context = "au1_tk1.N6_gAvYW8SuDQ8_myQZTWpBA8l7hOJUloQBlKk_R2zI." . date("U"); //todo
//        $this->http->GetURL("https://auth.changiairport.com/proxy?context={$context}&client_id={$client_id}&mode=login&scope=openid+profile+email+phone+UID&gig_skipConsent=true");

       /* $this->http->GetURL("https://auth1.changiairport.com/accounts.getScreenSets?screenSetIDs=CIAM-RegistrationLogin&include=html%2Ccss%2Cjavascript%2Ctranslations%2C&lang=en&APIKey={$this->apikey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Fauth.changiairport.com%2Flogin%3Flang%3Den&sdkBuild=13549&format=json&httpStatusCodes=true");

        if (!$this->http->FindPreg("/form class=.\"gigya-login-form/")) {
            $response = $this->http->JsonLog();
            $message = $response->errorDetails ?? null;
            $this->logger->error($message);

            return $this->checkErrors();
        }*/

        // for cookies
        $this->http->GetURL("https://auth1.changiairport.com/accounts.webSdkBootstrap?apiKey={$this->apikey}&pageURL=https%3A%2F%2Fauth.changiairport.com%2Flogin%3Flang%3Den&sdk=js_latest&sdkBuild=13549&format=json");

        $this->http->Form = [];
        $this->http->FormURL = "https://auth1.changiairport.com/accounts.login";
        $this->http->SetInputValue('loginID', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('sessionExpiration', "-1"); // 0
        $this->http->SetInputValue('targetEnv', "jssdk");
        $this->http->SetInputValue('include', "profile,data,emails,subscriptions,preferences,");
        $this->http->SetInputValue('includeUserInfo', "true");
        $this->http->SetInputValue('loginMode', "standard");
        $this->http->SetInputValue('lang', "en");
        $this->http->SetInputValue('riskContext', '{"b0":346443,"b1":[750,619,357,536],"b2":10,"b3":[],"b4":3,"b5":1,"b6":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36","b7":[{"name":"PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Chrome PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Chromium PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Microsoft Edge PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"WebKit built-in PDF","filename":"internal-pdf-viewer","length":2}],"b8":"13:09:14","b9":-180,"b10":{"state":"prompt"},"b11":false,"b12":{"charging":true,"chargingTime":0,"dischargingTime":null,"level":1},"b13":[null,"1920|1080|24",false,true]}');
        $this->http->SetInputValue('APIKey', $this->apikey);
        $this->http->SetInputValue('source', "showScreenSet");
        $this->http->SetInputValue('sdk', $this->sdk);
        $this->http->SetInputValue('authMode', "cookie");
        $this->http->SetInputValue('pageURL', "https://auth.changiairport.com/login?lang=en");
        $this->http->SetInputValue('sdkBuild', "13826");
        $this->http->SetInputValue('format', "json");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//h4[contains(text(), "Please note that the OneChangi ID service is not available on")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog($this->http->FindPreg("/\(([\w\W]+)\);\s*$/ims"), 1);

        if (isset($response->sessionInfo->login_token)) {
            //$this->http->setCookie("glt_gig_loginToken_{$this->apikey}", $response->sessionInfo->login_token, ".auth1.changiairport.com");
            $this->http->setCookie("glt_{$this->apikey}", $response->sessionInfo->login_token, ".changiairport.com");

            $this->http->GetURL("https://auth1.changiairport.com/oidc/op/v1.0/{$this->apikey}/authorize?client_id={$this->clientId}&redirect_uri=https%3A%2F%2Frewards.changiairport.com%2Fetc%2Fclientcontext%2Frewards%2Flogin%2Fcallback.html&scope=openid+phone+UID+email+CRID+profile&response_type=code");
            $context = $this->http->FindPreg("/context=([^&]+)/", false, $this->http->currentUrl());

            if (!$context) {
                return false;
            }

            $this->http->GetURL("https://auth1.changiairport.com/oidc/op/v1.0/{$this->apikey}/authorize/continue?context={$context}&login_token={$response->sessionInfo->login_token}");
        }

        if ($this->http->FindSingleNode('//strong[contains(text(), "Please complete your profile by filling up the following fields.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $response->errorDetails;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "invalid loginID or password") {
                throw new CheckException("Your email and password do not match. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://rewards.changiairport.com/en/dashboard.cardenquiry.data');
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $cardInfo = $response->CardInfo ?? null;
        $errorCode = $response->errorCode ?? null;

        // AccountID: 4704123
        if (!$cardInfo && $errorCode == 'No active session') {
            $this->http->GetURL('https://rewards.changiairport.com/en/dashboard.memberenquiry.data');
            $response = $this->http->JsonLog();
            $cardInfo = $response->cardInfo ?? null;
            $this->SetBalance($cardInfo->PointsBAL);
        }

        if (!$cardInfo && $errorCode != 'No active session') {
            if ($this->http->Response['code'] == 500) {
                $this->http->GetURL('https://rewards.changiairport.com/etc/clientcontext/cag/ocid/status.json');
                $response = $this->http->JsonLog();

                if (
                    isset($response->user_profile->changi_rewards)
                    && $response->user_profile->changi_rewards === ""
                ) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->AccountFields['Login'] == 'mattjk+onechangi@gmail.com') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return;
        }
        // Name
        $this->SetProperty("Name", beautifulName($cardInfo->PrintedName) ?? null);
        // Card Number
        $this->SetProperty("CardNumber", preg_replace('/06001$/', '', $cardInfo->CardNo));
        // My Tier
        $this->SetProperty("MembershipTier", $cardInfo->TierCode ?? null);
        // Tier Expiry
        if (
            isset($cardInfo->ExpiryDate)
            && strtotime($cardInfo->ExpiryDate) > time()
            && strtotime($cardInfo->ExpiryDate) < strtotime("+5 year")
        ) {
            $this->SetProperty("TierExpiry", date("d M Y", strtotime($cardInfo->ExpiryDate)));
        }
        // Spend to the next tier
        $this->SetProperty("SpendToTheNextTier", '$' . $cardInfo->NettToNextTier ?? null);
        // Current spend
        $this->SetProperty("CumulativeNettSpend", '$' . $cardInfo->CurrentTierNett ?? null);
        // Year Points
        //$this->SetProperty("YearPoints", $cardInfo->CurrentNetSpent ?? null);
        if (($cardInfo->CurrentNetSpent ?? 0) > 0) {
            $this->sendNotification('Year Points > 0 refs#23854 // MI');
        }

        // Balance - points
        //$this->SetBalance($cardInfo->RewardCycleLists->RewardCycleInfo->Value ?? null);
        if (isset($cardInfo->RewardCycleLists->RewardCycleInfo) && is_array($cardInfo->RewardCycleLists->RewardCycleInfo) && count($cardInfo->RewardCycleLists->RewardCycleInfo) == 2) {
            $balance = 0;
            $minDate = strtotime('01/01/3018');

            foreach ($cardInfo->RewardCycleLists->RewardCycleInfo as $list) {
                if (in_array($list->Type, ['Current', 'Grace'])) {
                    if (isset($list->ExpiringDate, $list->Value)) {
                        $balance += $list->Value;

                        if ($list->Value > 0) {
                            $list->ExpiringDate = $this->http->FindPreg('/(\d{4}\-\d+\-\d+)T/', false, $list->ExpiringDate);
                            $this->logger->debug("Expiring Date: {$list->ExpiringDate}");
                            $expDate = strtotime($list->ExpiringDate, false);

                            if ($expDate && $expDate < $minDate) {
                                $maxDate = $expDate;
                                $this->SetExpirationDate($maxDate);
                                $this->SetProperty("ExpiringBalance", $list->Value);
                            }
                        }
                    }
                }
            }

            if (isset($cardInfo->TotalPointsBAL) && $cardInfo->TotalPointsBAL == $balance) {
                $this->SetBalance($cardInfo->TotalPointsBAL);
            }
        }
        // AccountID: 4704190
        elseif (
            isset($cardInfo->RewardCycleLists->RewardCycleInfo)
            && is_object($cardInfo->RewardCycleLists->RewardCycleInfo)
            && $cardInfo->RewardCycleLists->RewardCycleInfo->Type == 'Current'
        ) {
            $balance = 0;
            $minDate = strtotime('01/01/3018');
            $list = $cardInfo->RewardCycleLists->RewardCycleInfo;

            if ($list->Type == 'Current') {
                if (isset($list->ExpiringDate, $list->Value)) {
                    $balance = $list->Value;

                    if ($list->Value > 0) {
                        $list->ExpiringDate = $this->http->FindPreg('/(\d{4}\-\d+\-\d+)T/', false, $list->ExpiringDate);
                        $this->logger->debug("Expiring Date: {$list->ExpiringDate}");
                        $expDate = strtotime($list->ExpiringDate, false);

                        if ($expDate && $expDate < $minDate) {
                            $maxDate = $expDate;
                            $this->SetExpirationDate($maxDate);
                            $this->SetProperty("ExpiringBalance", $list->Value);
                        }
                    }
                }
            }

            if (isset($cardInfo->TotalPointsBAL) && $cardInfo->TotalPointsBAL == $balance) {
                $this->SetBalance($cardInfo->TotalPointsBAL);
            }
        }

        // SubAccounts
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://rewards.changiairport.com/en/dashboard/myrewards.allmyrewards.data');
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $totalRewards = $response->totalResults ?? null;
        $rewards = $response->results ?? [];
        $this->logger->debug("Total {$totalRewards} rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            // DisplayName
            $displayName = $reward->name;
            // Points Balance
            $balance = $reward->count;
            // Points Expiry
            $exp = $reward->validTo ?? null;

            if (!$displayName) {
                continue;
            }
            $this->AddSubAccount([
                'Code'           => 'changi' . md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($exp, false),
            ], true);
        }// foreach ($rewards as $reward)
    }

//    function IsLoggedIn()//todo
//    {
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//
//        return false;
//    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(text(), "LOGOUT")]')) {
            return true;
        }

        return false;
    }
}
