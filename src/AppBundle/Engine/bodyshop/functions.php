<?php

class TAccountCheckerBodyshop extends TAccountChecker
{
    public $regions = [
        ''    => 'Select your region',
        'CA'  => 'Canada',
        'UK'  => 'United Kingdom',
        //        'USA' => 'United States',
    ];
    protected $reCaptcha = false;
    protected $lang = 'en_us';
    protected $id = 'us';
    protected $curr = 'USD';

    // newAuth
    private $access_token;
    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Encoding" => "gzip, deflate, br",
        "Referer"         => "https://www.thebodyshop.com/",
        'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = $this->regions;
    }

    public static function FormatBalance($fields, $properties)
    {
        switch ($fields['Login2']) {
            case 'UK':
                if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'bodyshopVoucher')) {
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
                }

                break;

            default:
                if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'bodyshopVoucher')) {
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
                }

                break;
        }// switch ($fields['Login2'])

        return parent::FormatBalance($fields, $properties);
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case "UK":
                $arg['RedirectURL'] = "https://www.thebodyshop.com/en-gb/login?loginAccount";

                break;

            case "CA":
                $arg['RedirectURL'] = "https://www.thebodyshop.com/en-ca/login?loginAccount";

                break;

            default:// USA
                $arg['RedirectURL'] = "https://www.thebodyshop.com/en-us/login?loginAccount";

                break;
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        if (empty($this->AccountFields['Login2']) || !array_key_exists($this->AccountFields['Login2'], $this->regions)) {
            $this->AccountFields['Login2'] = 'USA';
        }
        $this->logger->debug("Region {$this->AccountFields['Login2']}");

        if ($this->AccountFields['Login2'] == 'UK') {
            $this->lang = "en_gb";
            $this->id = 'uk';
            $this->curr = "GBP";
        }

        if ($this->AccountFields['Login2'] == 'CA') {
            $this->lang = "en_ca";
            $this->id = 'ca';
            $this->curr = "CAD";
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['access_token'])) {
            return false;
        }
        /*
        if ($this->AccountFields['Login2'] == 'UK' && isset($this->State['access_token'])) {
        */
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.thebodyshop.com/rest/v2/thebodyshop-{$this->id}/users/current/my-account/summary?lang={$this->lang}&curr=GBP", $this->headers + ["Authorization" => "bearer {$this->State['access_token']}"], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            $this->access_token = $this->State['access_token'];

            return true;
        }

        return false;
        /*
        }
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->AccountFields['Login2'] == 'USA') {
            throw new CheckException("The Body Shop has shut down its United States operations as it has filed for bankruptcy.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://www.thebodyshop.com/" . str_replace('_', '-', $this->lang) . "/login?loginAccount");

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re currently undergoing planned maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->newAuth();

        if ($this->AccountFields['Login2'] == 'UK') {
            if (!$this->newAuth()) {
                return $this->checkErrors();
            }

            return true;
        }

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('submit-login', '');

        return true;
    }

    public function Login()
    {
        /*
        if ($this->AccountFields['Login2'] == 'UK') {
        */
        if ($this->loginSuccessful()) {
            $this->State['access_token'] = $this->access_token;

            return true;
        }

        if ($this->http->FindPreg("/\{\s*\"errors\"\s*:\s*\[\s*\{\s*\"type\"\s*:\s*\"NullPointerError\"\s*\}\s*\]\s*}/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
        /*
        }
        */
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //if ($this->http->FindSingleNode('//div[contains(@class, "sign-out")]/a[contains(@href, "logout")]'))
        //    return true;
        if ($message = $this->http->FindSingleNode('//div[@id="globalMessages"]/div[contains(@class, "error")]/p[
                contains(text(), "Incorrect username")
                or contains(text(), "Your username or password was incorrect.")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // As it is your first time logging in to our new site please change your password.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "As it is your first time logging in to our new site please change your password.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Problem with captcha verification, please try again.
        if ($this->http->FindSingleNode('//div[@class = "message-container error"]/p[contains(text(), "Problem with captcha verification, please try again.")]') && !$this->reCaptcha) {
            $this->logger->notice("ReCaptcha verification");
            $this->reCaptcha = true;

            if (!$this->http->ParseForm('loginForm')) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
            $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);

            return $this->Login();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*
        if ($this->AccountFields['Login2'] == 'UK') {
        */
        $this->parseJson();

        return;
        /*
        }
        */

        $firstname = $this->http->FindSingleNode("//input[@id='register-firstname']/@data-ng-init", null, true, "/register\.firstname='(.+?)';/");
        $lastname = $this->http->FindSingleNode("//input[@id='register-lastname']/@data-ng-init", null, true, "/register\.lastname='(.+?)';/");
        // Name
        $this->SetProperty('Name', beautifulName($firstname . " " . $lastname));

        $this->http->GetURL("https://www.thebodyshop.com/{$this->lang}/my-account/home");
        // Points
        $points = $this->http->FindSingleNode('//div[contains(@class, "love-your-body")]/div[@class="content"]/div[1]', null, true, '/(\d+)/i');
        $this->SetBalance($points);
        // Rewards
        $this->SetProperty('Rewards', $this->http->FindSingleNode('//div[contains(@class, "love-your-body")]/div[@class="content"]/div[2]/text()[1]'));
        // Status (Example: You're a Love Your Body™ Club Member)
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(@class, "desktop-account-header")]//p[contains(@class, "card-no")]', null, true, "/You're\s*a\s*([^<]+)/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ($this->http->FindSingleNode("//a[contains(@aria-label, 'Click to learn more about Love Your body Club') and normalize-space(text()) = 'SIGN UP'] | //a[(contains(@aria-label, 'Click to learn more about Love Your Body™ Club') or contains(@aria-label, 'Click to learn more about Love Your body Club')) and normalize-space(text()) = 'LEARN MORE']")) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }

            return;
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->http->GetURL("https://www.thebodyshop.com/{$this->lang}/my-account/points");
        // Next Reward
        $nextReward = $this->http->FindSingleNode('//div[contains(text(), "Next Reward")]', null, true, "/Next\s*Reward\s*([^<]+)/");

        if (isset($nextReward) && isset($points)) {
            $this->SetProperty('NextReward', $nextReward - $points);
        }
        $this->http->GetURL("https://www.thebodyshop.com/{$this->lang}/my-account/vouchers/json");
        $response = $this->http->JsonLog(null, true, true);
        // Active Rewards
        $activeRewards = ArrayVal($response, 'active', []);
        $this->SetProperty('ActiveRewards', count($activeRewards));
        // Used Rewards
        $this->SetProperty('UsedRewards', count(ArrayVal($response, 'used', [])));
        // Expired Rewards
        $this->SetProperty('ExpiredRewards', count(ArrayVal($response, 'expired', [])));

        $this->SetProperty("CombineSubAccounts", false);

        foreach ($activeRewards as $activeReward) {
            $expires = $activeReward['expires'];
            $voucherValue = $activeReward['voucherValue'];
            $type = $activeReward['type'];
            $voucherNo = $activeReward['voucherNo'];
            $subAccount = [
                "Code"        => "bodyshopVoucher{$voucherNo}",
                "DisplayName" => "Voucher #{$voucherNo}",
                "Balance"     => $voucherValue,
                "Type"        => $type,
            ];

            if ($expires && strtotime($expires)) {
                $subAccount['ExpirationDate'] = strtotime($expires);
            }
            $this->AddSubAccount($subAccount);
        }// foreach ($activeRewards as  $activeReward)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'loginForm']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        /*
        if ($this->AccountFields['Login2'] == 'UK') {
        */
        $response = $this->http->JsonLog();
        $email = $response->customer->email ?? null;
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[Login]: {$this->AccountFields['Login']}");

        if (
            $email
            && (
                stristr($email, $this->AccountFields['Login'])
                || ($email == 'jasonruffo@earthlink.net' && $this->AccountFields['Login'] == 'jasonruffoorders@earthlink.net')
            )
        ) {
            return true;
        }
        /*
        }
        */

        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.thebodyshop.com/{$this->lang}/my-account/profile");
        $this->http->RetryCount = 2;
        $email = $this->http->FindSingleNode("//input[@id='profile-email']/@data-ng-init",null,true,"/profile\.email = '(.+?)'/");
        $this->logger->debug("[Email]: {$email}");
        if ($email && strtolower($email) === strtolower($this->AccountFields['Login'])) {
            return true;
        }
        */

        return false;
    }

    private function newAuth()
    {
        $context = "R2479260576";
        $sdk = "js_latest";
        $callback = "gigya.callback";
        $format = "jsonp";
        $domain = 'us1';

        switch ($this->AccountFields['Login2']) {
            case 'UK':
                $apiKey = "3__KY3DZ97uDFCNLmBAylDgvNjbOGyGyw1nO5uD1pKlDybCHyRL1tvrv_xi4-EF9E3";
                $domain = 'eu1';

                break;

            case 'CA':
                $apiKey = "3_PFSc8MJbtXyqyXbkF2s7f0O2S4lQ4N9fDKUBE-seV7ZxNO9BOx7odIuEIIIS5F1e";

                break;

            default:
                $apiKey = "3_BjEfUjkRPSy6B7L2EJsyEepPdy1aDJ3rmLkhHuFH11FaSLNMta8a-foqI3bssAKN";
        }
        $pageURL = "https://www.thebodyshop.com/";

        // for cookies
        $param = [];
        $param['apiKey'] = $apiKey;
        $param['pageURL'] = $pageURL;
        $param['format'] = $format;
        $param['callback'] = $callback;
        $param['context'] = $context;
        $this->http->GetURL("https://accounts.{$domain}.gigya.com/accounts.webSdkBootstrap?" . http_build_query($param));

        // accounts.login
        $param = [];
        $param['loginID'] = $this->AccountFields['Login'];
        $param['password'] = $this->AccountFields['Pass'];
        $param['sessionExpiration'] = "0";
        $param['targetEnv'] = "jssdk";
        $param['include'] = "profile,data,emails,subscriptions,preferences,";
        $param['includeUserInfo'] = true;
        $param['loginMode'] = "standard";
        $param['lang'] = "en";
        $param['APIKey'] = $apiKey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = $sdk;
        $param['authMode'] = "cookie";
        $param['pageURL'] = "https://www.thebodyshop.com/{$this->lang}/login?loginAccount=";
        $param['format'] = $format;
        $param['callback'] = $callback;
        $param['context'] = $context;
        $param['utf8'] = "&#x2713;";
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://accounts.{$domain}.gigya.com/accounts.login?context={$context}&=&saveResponseID={$context}", http_build_query($param));
        $this->http->RetryCount = 2;

        // access_token
        $param = [];
        $param['client_id'] = "mobile_android";
        $param['client_secret'] = "secret";
        $param['grant_type'] = "client_credentials";
        $this->http->PostURL("https://api.thebodyshop.com/authorizationserver/oauth/token", http_build_query($param));
        $response = $this->http->JsonLog();
        $access_token = $response->access_token ?? null;

        if (!$access_token) {
            return false;
        }

        // callback
        $param = [];
        $param['APIKey'] = $apiKey;
        $param['saveResponseID'] = $context;
        $param['pageURL'] = $pageURL;
        $param['noAuth'] = true;
        $param['sdk'] = $sdk;
        $param['format'] = $format;
        $param['callback'] = $callback;
        $param['context'] = $context;

        $this->http->GetURL("https://accounts.{$domain}.gigya.com/socialize.getSavedResponse?" . http_build_query($param));
        $pregResponse = $this->http->JsonLog($this->http->FindPreg("/{$callback}\((.+?)\);/s"));
        // Error
        $errorDetails = $pregResponse->errorDetails ?? null;

        if ($errorDetails) {
            $this->logger->error("[Error]: {$errorDetails}");

            if ($errorDetails == "invalid loginID or password") {
                throw new CheckException("Invalid login or password", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        // oauthToken
        $profile = $pregResponse->profile ?? null;
        $data = $pregResponse->data ?? null;
        $uIDSignature = $pregResponse->userInfo->UIDSignature ?? null;
        $signatureTimestamp = $pregResponse->userInfo->signatureTimestamp ?? null;
        $uID = $pregResponse->userInfo->UID ?? null;

        if (!$pregResponse || !$profile || !$uIDSignature || !$signatureTimestamp || !$uID) {
            return false;
        }
        $headers = [
            "Authorization" => "bearer {$access_token}",
            "Content-Type"  => "application/json",
        ];
        $data = [
            "eventName"          => "login",
            "remember"           => true,
            "provider"           => "",
            "loginMode"          => "standard",
            "newUser"            => false,
            "UIDSignature"       => $uIDSignature,
            "signatureTimestamp" => $signatureTimestamp,
            "UID"                => $uID,
            "profile"            => $profile,
            "data"               => $data,
            "isGlobal"           => true,
            "fullEventName"      => "accounts.login",
            "source"             => "showScreenSet",
            "referer"            => "/{$this->lang}/login",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.thebodyshop.com/rest/v2/thebodyshop-{$this->id}/gigya-raas/login?lang={$this->lang}&curr={$this->curr}", json_encode($data), $this->headers + $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $oauthToken = $response->oauthToken ?? null;

        if (!$oauthToken) {
            $this->logger->error("oauthToken not found");

            $message = $response->message ?? null;

            if ($message == "Error logging in user. Please contact support.") {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $access_token = $this->http->FindPreg('/"access_token":"(.+?)",/', false, $oauthToken);

        if (!$access_token) {
            return false;
        }

        $this->http->GetURL("https://api.thebodyshop.com/rest/v2/thebodyshop-{$this->id}/users/current/my-account/summary?lang={$this->lang}&curr={$this->curr}", $this->headers + ["Authorization" => "bearer {$access_token}"]);
        $this->access_token = $access_token;

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re upgrading our site with new exciting features and content.")]
                | //h4[contains(text(), "OUR SITE IS TEMPORARILY DOWN")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        /*
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                break;
            case 'USA':
            default:
                break;
        }// switch ($this->AccountFields['Login2'])
        */

        return false;
    }

    private function parseJson()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($response->customer->name ?? null));
        // Member since:
        $memberSince = $response->customer->memberSince ?? null;

        if ($memberSince && strtotime($memberSince)) {
            $this->SetProperty("MemberSince", date("M d, Y", strtotime($memberSince)));
        }
        // Rewards
        $this->SetProperty('Rewards', $response->lybc->rewards ?? null);
        // You have 0 POINTS
        $this->SetBalance($response->lybc->points ?? null);

        $this->http->GetURL("https://api.thebodyshop.com/rest/v2/thebodyshop-{$this->id}/users/current/loyalty?lang={$this->lang}&curr={$this->curr}", $this->headers + ["Authorization" => "bearer {$this->access_token}"]);
        $response = $this->http->JsonLog();
        // Collect a further 0 POINTS to receive your next reward
        $this->SetProperty('NextReward', $response->estimatedPointsToNextVoucher ?? null);

        $this->http->GetURL("https://api.thebodyshop.com/rest/v2/thebodyshop-{$this->id}/users/current/loyalty/history/rewards?lang={$this->lang}&curr={$this->curr}", $this->headers + ["Authorization" => "bearer {$this->access_token}"]);
        $response = $this->http->JsonLog();

        $active = 0;
        $used = 0;
        $expired = 0;

        $vouchers = $response->rewardsHistory ?? [];

        foreach ($vouchers as $voucher) {
            $status = $voucher->status ?? null;

            if ($status === "EXPIRED") {
                $expired++;

                continue;
            }

            if ($status === "CAPTURED") {
                $used++;

                continue;
            }

            if ($status !== "ACTIVE") {
                continue;
            }

            $active++;
            $expiryDate = $voucher->expiryDate ?? null;
            $type = $voucher->type ?? null;
            $voucherId = $voucher->voucherId ?? null;
            $value = $voucher->value->value ?? null;

            $subAccount = [
                "Code"        => "bodyshopVoucher{$this->AccountFields['Login2']}{$voucherId}",
                "DisplayName" => "Voucher #{$voucherId}",
                "Balance"     => $value,
                "Type"        => $type,
            ];

            if ($expiryDate && strtotime($expiryDate)) {
                $subAccount['ExpirationDate'] = strtotime($expiryDate);
            }
            $this->AddSubAccount($subAccount);
        } // foreach ($vouchers as $voucher)

        // Active Rewards
        $this->SetProperty('ActiveRewards', $active);
        // Used Rewards
        $this->SetProperty('UsedRewards', $used);
        // Expired Rewards
        $this->SetProperty('ExpiredRewards', $expired);
    }
}
