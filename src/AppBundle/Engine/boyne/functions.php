<?php

class TAccountCheckerBoyne extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.boynerewards.com/account';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'RewardCardBalance')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL('https://www.boynerewards.com/api/auth/csrf');
        $response = $this->http->JsonLog();

        if (!isset($response->csrfToken)) {
            return $this->checkErrors();
        }

        $csrf = $response->csrfToken;

        $headers = [
            'accept'       => '*/*',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $data = [
            'callbackUrl' => "/account",
            'csrfToken'   => $csrf,
            'json'        => "true",
        ];

        $this->http->PostURL('https://www.boynerewards.com/api/auth/signin/boyne-id', $data, $headers);

        $response = $this->http->JsonLog();

        if (!isset($response->url)) {
            return $this->checkErrors();
        }

        $this->http->GetURL($response->url);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $state = $this->http->FindPreg("/state=([^&]+)/", false, urldecode(urldecode($this->http->currentUrl())));
        $codeChallenge = $this->http->FindPreg("/code_challenge=([^&]+)/", false, urldecode(urldecode($this->http->currentUrl())));

        if (!$state || !$codeChallenge) {
            return $this->checkErrors();
        }

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $data = [
            'ReCaptchaToken' => 'null',
            'ReturnUrl'      => "/connect/authorize/callback?client_id=BoyneRewards&scope=openid%20profile&response_type=code&redirect_uri=https%3A%2F%2Fwww.boynerewards.com%2Fapi%2Fauth%2Fcallback%2Fboyne-id&claims=given_name%20family_name%20email%20picture&claims_locales=en&state={$state}&code_challenge={$codeChallenge}&code_challenge_method=S256",
            'Username'       => $this->AccountFields['Login'],
        ];

        $this->http->PostURL("https://id.boyneresorts.com/api/users", json_encode($data), $headers);

        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status != 'UserAccountExists') {
            if ($status == 'UserAccountExistsWithNoEmail') {
                throw new CheckException("We've encountered a problem locating your account. Please contact customer service at (406) 995-5749. Error: no email profile.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($status == 'NotExists') {
                throw new CheckException("Email, Username or BoyneRewards Number is invalid. Please re-enter your information or create a new account.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($status == 'MissingInfo') {
                $this->throwProfileUpdateMessageException();
            }

            return $this->checkErrors();
        }

        $isPasswordExpired = $response->isPasswordExpired ?? null;

        if ($isPasswordExpired) {
            $this->throwProfileUpdateMessageException();
        }

        $data = [
            "Username"           => $this->AccountFields['Login'],
            "Password"           => $this->AccountFields['Pass'],
            "RememberLogin"      => true,
            'ReturnUrl'          => "/connect/authorize/callback?client_id=BoyneRewards&scope=openid%20profile&response_type=code&redirect_uri=https%3A%2F%2Fwww.boynerewards.com%2Fapi%2Fauth%2Fcallback%2Fboyne-id&claims=given_name%20family_name%20email%20picture&claims_locales=en&state={$state}&code_challenge={$codeChallenge}&code_challenge_method=S256",
            "ConsentModels"      => [],
            "AccountHelpMessage" => "",
            "ReCaptchaToken"     => "null",
        ];

        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Referer"      => "https://id.boyneresorts.com/",
            "Content-Type" => "application/json",
        ];

        $this->http->PostURL("https://id.boyneresorts.com/api/login?button=login", json_encode($data), $headers);

        $response = $this->http->JsonLog();

        if ($response->showLockOutMessage ?? false) {
            throw new CheckException("Your account or password is incorrect. If you don't remember your password, reset it now.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://id.boyneresorts.com/connect/authorize/callback?client_id=BoyneRewards&scope=openid profile&response_type=code&redirect_uri=https://www.boynerewards.com/api/auth/callback/boyne-id&claims=given_name family_name email picture&claims_locales=en&state={$state}&code_challenge={$codeChallenge}&code_challenge_method=S256");

        if (strstr($this->http->currentUrl(), 'error')) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $nameRaw = $this->http->FindNodes('//div[contains(text(), "Member Since")]/../div[1]/text()');
        $this->SetProperty('Name', beautifulName(implode(' ', $nameRaw)));
        // Number
        $number = $this->http->FindPreg('/.\"LoyaltyNumber.\"\s*:\s*.\"(\d+).\"/');
        $this->SetProperty('Number', $number);
        // Balance - points
        $balance = $this->http->FindPreg('/.\"PointsBalance.\"\s*:\s*(\d+)/');
        $this->SetBalance($balance);
        // MemberSince
        $memberSince = $this->http->FindPreg('/.\"EnrollmentDate.\"\s*:\s*.\"\$D([^\"]+).\"/');
        $this->SetProperty('MemberSince', strtotime($memberSince));
        // TransactionsPastMonth
        $transactionsPastMoth = $this->http->FindSingleNode('//p[contains(text(), "Transactions Past Month")]/../p[1]/text()');
        $this->SetProperty('TransactionsPastMonth', $transactionsPastMoth);

        $this->http->GetURL('https://www.boynerewards.com/account/history');

        $lastActivityRawMonthDay = $this->http->FindSingleNode('//article[1]/p[@aria-label="Transaction Date"]/span[1]/text()');
        $lastActivityRawYear = $this->http->FindSingleNode('//article[1]/p[@aria-label="Transaction Date"]/span[2]/text()');

        $this->logger->debug('lastActivityRawMonthDay: ' . $lastActivityRawMonthDay);
        $this->logger->debug('lastActivityRawYear: ' . $lastActivityRawYear);

        if ($lastActivityRawMonthDay && $lastActivityRawYear) {
            // Last Activity
            $lastActivity = DateTime::createFromFormat('M d Y', "$lastActivityRawMonthDay $lastActivityRawYear");
            $this->SetProperty('LastActivity', $lastActivity->format("Y-m-d"));
            // Expiration date
            $expirationDate = $lastActivity->add(new DateInterval('P2Y'));
            $this->SetExpirationDate(strtotime($expirationDate->format("Y-m-d")));
        }

        $this->http->GetURL('https://www.boynerewards.com/account/wallet');
        $rewardCardBalance = $this->http->FindSingleNode('//article/div/p/text()[2]');

        if (isset($rewardCardBalance)) {
            $rewardCardExpirationDateRaw = $this->http->FindSingleNode('//article/div[2]/text()[2]');
            $rewardCardExpirationDate = DateTime::createFromFormat('M d, Y', $rewardCardExpirationDateRaw);

            $this->AddSubAccount([
                "Code"           => "RewardCardBalance",
                "DisplayName"    => "Reward Card",
                "Balance"        => $rewardCardBalance,
                "ExpirationDate" => strtotime($rewardCardExpirationDate->format("Y-m-d")),
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Account Overview")]')) {
            return true;
        }

        return false;
    }

    /*
    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL('https://www.boynerewards.com/api/auth/session');

        $response = $this->http->JsonLog();

        if (!isset($response->user->email)) {
            return false;
        }

        $email = $response->user->email;
        $this->logger->debug("[Email]: {$email}");

        if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }
    */
}
