<?php

// refs #15542
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAmericaneagle extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $localeParam = 'ctx.locale=en&ctx.shipTo=US&ctx.currency=USD';
    private $key = null;

    private $headers = [
        "Accept"        => "*/*",
        'aeLang'        => 'en_US',
        'aeSite'        => 'AEO_US',
        "content-type"  => 'application/x-www-form-urlencoded; charset=UTF-8',
        "Referer"       => 'https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD',
        'X-NewRelic-ID' => 'VwMPUFJVGwEBXVVaAwIBXw==',
        'origin'        => 'https://www.ae.com',
    ];

    private $authHeaders = [
        "Accept"        => "application/vnd.oracle.resource+json",
        "authorization" => "Basic MjBlNDI2OTAtODkzYS00ODAzLTg5ZTctODliZmI0ZWJmMmZlOjVmNDk5NDVhLTdjMTUtNDczNi05NDgxLWU4OGVkYjQwMGNkNg==",
    ];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "americaneagle")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    /*
    public function IsLoggedIn()
    {
        if (empty($this->State['headers'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            $this->headers = $this->State['headers'];

            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->removeCookies();
        $this->http->GetURL('https://www.ae.com/us/en/login?' . $this->localeParam);

        // Our site is super busy at the moment
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'Our site is super busy at the moment.')
                or contains(text(), 'So Sorry! The site is a little overcrowded right now.')
                or contains(text(), 'We’re sorry! Our site is a little busy at the moment.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->RetryCount = 0;

        $sensorPostUrl =
            $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link#")
        ;

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return false;
        }

        return true;
        $response = $this->http->JsonLog($this->selenium());

        /*
        $this->http->NormalizeURL($sensorPostUrl);
        $this->sendSensorData($sensorPostUrl);

        $headers = [
            'Accept'        => $this->authHeaders['Accept'],
            'aeLang'        => '',
            'aeSite'        => '',
            'X-NewRelic-ID' => $this->headers['X-NewRelic-ID'],
            'content-Type'  => $this->headers['content-type'],
            "authorization" => $this->authHeaders['authorization'],
            "Referer"       => $this->headers['Referer'],
        ];
        $data = [
            "grant_type" => "client_credentials"
        ];
        $this->http->PostURL('https://www.ae.com/ugp-api/auth/oauth/v3/token', $data, $headers);
        $response = $this->http->JsonLog();
        */
        $token = $response->access_token ?? null;

        if (empty($token)) {
            /*
            // Provider error
            if($this->http->Response['code'] == 503 && $this->http->FindSingleNode("//h1[contains(text(),'Service Unavailable')]"))
                throw new CheckException('Our bad! Something went wrong and we are unable to complete your request.', ACCOUNT_PROVIDER_ERROR);
            */
            return false;
        }
        $this->headers["x-access-token"] = $token;
        $data = [
            'username'   => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
            'grant_type' => 'password',
        ];
        $headers = $this->headers;
        $headers["Accept"] = $this->authHeaders['Accept'];
        $headers["authorization"] = $this->authHeaders['authorization'];

//        $this->http->GetURL('https://www.ae.com/agwa-api/i18n/init?', $headers);
//        $this->http->JsonLog();
        // apisg.guest_token.invalid workaround
        $this->http->GetURL('https://www.ae.com/ugp-api/cart/v1', $headers);
        $this->http->JsonLog();

        $this->http->RetryCount = 0;
//        $headers["tltuid"] = $this->http->getCookieByName("TLTUID", ".ae.com");
        $this->http->PostURL('https://www.ae.com/ugp-api/auth/oauth/v3/token', $data, $headers);
        $this->http->RetryCount = 2;

        $retry = false;

        if ($this->http->Response['code'] == 403) {
            $this->sendStatistic(false, $retry, $this->key);

            throw new CheckRetryNeededException();
        }

        $this->sendStatistic(true, $retry, $this->key);

        return true;
    }

    public function Login()
    {
        /*
        $response = $this->http->JsonLog(null, 5);
        */
        $response = $this->http->JsonLog($this->selenium(), 5);

        if (isset($response->access_token) && !$this->http->FindSingleNode('//*[self::li or self::div][contains(@class, "has-error") and contains(@class, "ember-view")]')) {
            $this->headers["x-access-token"] = $response->access_token;

            if ($this->parseQuestion()) {
                return false;
            }

            $this->State['headers'] = $this->headers;

            return $this->loginSuccessful();
        }

        $message = $response->error->errors[0]->key ?? null;

        switch (trim($message)) {
            case 'error.account.login.passwordInvalid':
                throw new CheckException("Please enter a password that contains 6-25 characters with at least one letter and one number.", ACCOUNT_INVALID_PASSWORD);

                break;

            case 'error.account.login.password.mismatch':
                throw new CheckException("Your user name and password are incorrect.", ACCOUNT_INVALID_PASSWORD);

                break;

            case 'error.account.login.user.lockWarning':
                throw new CheckException("The entered username or password is incorrect and you have 1 more attempt before you get locked out of your account.", ACCOUNT_INVALID_PASSWORD);

                break;

            case 'error.account.login.user.locked':
                throw new CheckException("Looks like you're having trouble with your login. We locked your account.", ACCOUNT_LOCKOUT);

                break;

            case 'error.general.error.internal.exception':
            case 'apisg.internalServer.error':
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

                break;

            default:
                $this->logger->error("[Error]: {$message}");
                $this->DebugInfo = $message;

                break;
        }// switch ($message)

        $message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]');

        if (strstr($message, 'We\'ve encountered an unexpected error on our end. Please try again later')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $message = $this->http->FindSingleNode('//*[self::li or self::div][contains(@class, "has-error") and contains(@class, "ember-view")]');

        if ($message) {
            $this->logger->error("[Error - 2]: {$message}");

            if (
                strstr($message, 'Your user name and password are incorrect.')
                || $message == 'This email address does not match any active accounts. Check the spelling and try again, or create account.'
                || strstr($message, 'Please enter a password that contains 6-25 characters with at least one letter and one number.')
                || strstr($message, 'Looks like you\'re having trouble with your login. We locked your account for')
                || strstr($message, 'This email address does not match any active accounts. Check the spelling and try again, or create an account.')
                || strstr($message, 'Please enter a password that contains letters + numbers and is 8-25 characters long.')
                || strstr($message, 'Please enter a password that contains 8-25 characters with at least one letter and one number.')
                || strstr($message, 'The entered username or password is incorrect and you have 1 more attempt before you get locked out of your account.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Please enter a valid email address.'
                && filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false
            ) {
                throw new CheckRetryNeededException();
            }

            if (
                strstr($message, 'You\'ve exceeded the maximum number of codes you can request in 30 minutes. Contact Customer Service at 1 (888) 232-4535 or Live Chat.')
                || $message == 'Real Rewards is temporarily down due to maintenance. Please try again later.'
                || $message == 'We\'ve encountered an unexpected error on our end. Please try again later.'
                || $message == 'You’ve already signed up for Real Rewards, but your account is not complete yet. Create a Password'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "So sorry! The site is a little overcrowded right now.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0, true);

        $data = ArrayVal($response, 'data', null);
        $user = ArrayVal($data, 'profile', ['firstName' => '', 'lastName' => '']);
        // Name
        $this->SetProperty('Name', beautifulName($user['firstName'] . ' ' . $user['lastName']));

        // Rewards
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.ae.com/ugp-api/users/v1/loyaltyAccount', $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $error = ArrayVal($response, 'error', null);
        $errors = ArrayVal($error, 'errors', [[]]);
        $message = ArrayVal($errors[0], 'key', null);
        /*
        if ($isMember && $message == 'Info about rewards account is unavailable') {
            $this->SetBalanceNA();
            return;
        } else
        */
        if (!empty($message)) {
            $this->logger->error($message);

            if (in_array($message, [
                'error.account.rewards.loyalty.notSignedUp',
                'error.account.loyalty.notSignedUp',
            ])
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (in_array($message, [
                'error.account.loyalty.customerHub.down',
                'error.account.loyalty.loyaltyEngine.down',
            ])
            ) {
                throw new CheckException("We could not load your rewards information.", ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        // EnrollmentDate - You connected on
        $data = ArrayVal($response, 'data', null);
        $this->SetProperty('EnrollmentDate', ArrayVal($data, 'dateEnrolled'));
        // Number - Your member #
        $this->SetProperty('Number', ArrayVal($data, 'rewardsNumber'));
        // Balance - Points
        $earning = ArrayVal($data, 'earning', []);
        $this->SetBalance(ArrayVal($earning, 'totalPoints'));
        // EliteLevel - You've got Full Access.
        $this->SetProperty('Tier', ArrayVal($earning, 'memberTier'));
        $amountForNextTier = ArrayVal($earning, 'amountForNextTier', []);
        // Spend $1 more to reach Level 2
        if (isset($amountForNextTier)) {
            $this->SetProperty('AmountForNextTier', "$" . ArrayVal($earning, 'amountForNextTier'));
        }
        // You're 760 points away from a $5 reward.
        $this->SetProperty('PointsNextReward', ArrayVal($earning, 'pointsNeededForNextReward'));
        //  Keep Level 2 by spending $66 more this year! - Spend to Maintain Level
        $spendMaintainLevel = ArrayVal($earning, 'amountToMaintainTier');

        if (isset($spendMaintainLevel)) {
            $this->SetProperty('SpendMaintainLevel', "$" . ArrayVal($earning, 'amountToMaintainTier'));
        }

        // Rewards
        $this->http->GetURL("https://www.ae.com/ugp-api/users/v1/discounts", $this->headers);
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data', null);
        $discounts = ArrayVal($data, 'discounts', []);

        foreach ($discounts as $discount) {
            $expirationDate = ArrayVal($discount, 'expirationDate', null);
            $description = ArrayVal($discount, 'description', null);
            $code = ArrayVal($discount, 'discountCode', null);

            if (isset($code) && ($exp = strtotime($expirationDate))) {
                $this->AddSubAccount([
                    'Code'        => 'americaneagle' . $code,
                    'DisplayName' => "{$description} (Code: {$code})",
                    // $15 REWARD
                    'Balance'        => $this->http->FindPreg('/(\$[\d.,]+)\s+/', false, $description),
                    'ExpirationDate' => $exp,
                    'Issued'         => ArrayVal($discount, 'issuedDate', null),
                    'BarCode'        => $code,
                    "BarCodeType"    => BAR_CODE_CODE_128,
                ]);
            }
        }// foreach ($discounts as $discount)

        // Earning History - refs #15889, https://www.ae.com/myaccount/aeoconnected/earning-history
        unset($exp);
        $this->http->GetURL("https://www.ae.com/ugp-api/users/v1/rewardsHistory?pageNumber=1", $this->headers);
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data', null);
        $histories = ArrayVal($data, 'history', []);
        $this->logger->debug("Total " . count($histories) . " exp date nodes were found");

        foreach ($histories as $history) {
            if (!$this->http->FindPreg('/Purchase/', false, ArrayVal($history, 'description'))) {
                continue;
            }
            $date = ArrayVal($history, 'date');

            if (!isset($exp) || strtotime($date, false) > $exp) {
                $exp = strtotime($date, false);
                $this->SetProperty('LastActivity', date('m/d/Y', $exp));
                $this->SetExpirationDate(strtotime('+375 day', $exp));
            }
        }

        $this->http->PostURL("https://www.ae.com/ugp-api/users/v1/logout", null, $this->headers); //todo
    }

    public function ProcessStep($step)
    {
        $headers = [
            'Accept'          => $this->authHeaders['Accept'],
            'aeLang'          => '',
            'aeSite'          => '',
            'X-NewRelic-ID'   => $this->headers['X-NewRelic-ID'],
            'content-Type'    => $this->headers['content-type'],
            "authorization"   => $this->authHeaders['authorization'],
            "Referer"         => $this->headers['Referer'],
            "oneTimePassword" => $this->Answers[$this->Question],
            "x-access-token"  => $this->State['x-access-token'],
        ];

        unset($this->Answers[$this->Question]);
        unset($this->State['x-access-token']);

        $data = [
            'username'   => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
            'grant_type' => 'password',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.ae.com/ugp-api/auth/oauth/v4/token', $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 5);
        $message = $response->error->errors[0]->key ?? null;

        if ($message) {
            if ($message == 'error.account.login.oneTimePassword.mismatch') {
                $this->AskQuestion($this->Question, "Verification code does not match. Please try again.", 'Question');
            }

            return false;
        }

        if (isset($response->access_token)) {
            $this->headers["x-access-token"] = $response->access_token;
        }

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.ae.com/ugp-api/users/v1", $this->State['headers'], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);

        $email = $response['data']['profile']['login'] ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            $email
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//*[contains(text(), "A 6-digit verification code was just sent to")]');

        if (!$question) {
            return false;
        }

        $this->State['x-access-token'] = $this->headers["x-access-token"];

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    private function getSensorData($secondSensor = false)
    {
        $sensorData = [
            null,
            // 1
            "7a74G7m23Vrp0o5c9046001.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389292,2465654,1536,880,1536,960,1536,444,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.279997283139,791091232826.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1582182465653,-999999,16925,0,0,2820,0,0,3,0,0,D1E2BFB98CCAE7904D95DE5DFF9E847F~-1~YAAQFTk3F9cEX15wAQAA6XlsYQM+26Adx8rqa7smU80iQG68HUYCXPJTng1DVSBAq872cxn/1wZqaD1MURUt2HgQxp7zL6NM6L2+cKUbV4S+c6PZKlk4ktfU9ReloRBMg+NJ9hJeR5vIGzLyqn3Oa4vkY6XufBXJJZZjvQTH/+J9klIK5UW88t49y78KLpLjw7Mat88ZzuIe+IfIh+/+bPMyE9LWTUAwFj0XRd8uDUesAIu9sEN/7amQ5skE/sLaz+tyL+F6XW7oJY1G86CnXaMyL5GJItmYmTjFd/7EOR1tSIh0kM4=~-1~-1~-1,28042,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,12328268-1,2,-94,-118,77778-1,2,-94,-121,;4;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9046001.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389292,2837917,1536,880,1536,960,1536,444,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.635787445317,791091418958.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1582182837917,-999999,16925,0,0,2820,0,0,2,0,0,3B32123C2E9FC8AA8EF011A3D627E854~-1~YAAQPjk3F3jce1hwAQAAwyVyYQO02QSApaJWXp1xhX5XobTmjyOIA9VVpaNABAzdd84fEKIOLLDlOp4pJhpV4/OIM4YDBYUDCbxCuqQnTHVjZiHkT0G5hWsniWp1eUvNrd6cr2fen9s+hXwlim8LPK+aCQ7DWsnXVvVIWU2Dw+0IiYc6duTkBPkbPV0fopcZhtUQqvZCtyHhL4JaTtm/5T0B9vj6CFekaxjq8fOEui7IoAjaXizi+MtNp1q1AdI2FDv4Vf0qvbDHEcgquQ/EMgj734BzhiypCTzReM+RG55eT7kJEmE=~-1~-1~-1,29258,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,2837909-1,2,-94,-118,79032-1,2,-94,-121,;4;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9016741.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386612,630002,1440,829,1440,900,1440,409,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8969,0.964221842482,785645315000.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1571290630001,-999999,16809,0,0,2801,0,0,4,0,0,A9E06D36EA4352FFA8474A46166D10D2~-1~YAAQbfzU2Vxiua9tAQAAO0Q42ALBi8+PcCyqtL8G6ft6t/1vvKQksKUzqOSR/wfkCPmefOg5Ob4FwzTQnlREM87D7EIuXVcGGVsXfCicG/Q8aj8kOVedlbxg7HoegBDeUF4dNC4jOHjmde24NYt7SuxKaTnAlyhaLEibp+od9E6sCMMKwdB25Y5Y+WxzfLB36/4ciibs2dKiAyH72+i08TWeGs8rKlds7wDkqcr3oU6EHIr7M0iUGN1WvJ17bywyYuoD0ebUcVGXfiYJk3SxpgQ=~-1~-1~-1,26666,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,629995-1,2,-94,-118,74806-1,2,-94,-121,;3;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9046091.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:73.0) Gecko/20100101 Firefox/73.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,389291,1923814,1536,880,1536,960,1536,490,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6004,0.221308560110,791090961907,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1582181923814,-999999,16925,0,0,2820,0,0,4,0,0,039D94F8C0098B9BD23514A850D36C6B~-1~YAAQRNxgaHo7Sl9wAQAAtDdkYQNlT4nLxQG2ebJmqTlNESGHgOXYKP9ZWUMFShiILE/NB4alUP5DT+tkOsiQMtgoJZPvQS8XYd6KyIWNvo2i+9jbbJ0IRfKQnV2tbaI/HmFA8+Mf638LyCK/5F9xaaeYJmbZCYq0u14EkgHKqrWV7o2a7bEONAemE/Ew3/Xc1JyBZTqQ4JkLiKLUfgnKvJFqdHTbSS6qOtV4fC3AJWkg5HvAl6m1mdZp1RXxbZPtOmM0P4zq8LJMRytlrkyep9tRue6dEQuAAID0eXiTb7R7zMWIuQg=~-1~-1~-1,28844,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,86571641-1,2,-94,-118,75662-1,2,-94,-121,;6;-1;0",
            // 5
            "7a74G7m23Vrp0o5c9046091.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389291,1995808,1536,880,1536,960,1536,498,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.468298112234,791090997903.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1582181995807,-999999,16925,0,0,2820,0,0,6,0,0,18EE9C7D96CE7D6ED155EAD217B4E95B~-1~YAAQDTk3F36IhkZwAQAAhUdlYQNqTqOz9TAjH1Yp7O5+4AioVWXyABN6AmShuef8jJES3JfcRCWfhM3wwVkdC6g7VRnPqI8kb+jK7jNfIJcYgChaqEFU4LvlW9lmdLCirMrquv+AcFvDIofaxjS0dB0LHsXfyCGj47P4v3yihSaFQj3qpOGaIKcB5S9FOF27coxzB6iYNkJExhL7SJNjhngaEC1Jkc9mPnKAz+TdHh+/I9cUXna//U54dChG78rEvqq7Vb3v9v53Szmvmy8CoOh+83xi5s9T21nu+orJkmia9DoML9I=~-1~-1~-1,28919,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,161660464-1,2,-94,-118,78657-1,2,-94,-121,;7;-1;0",
            // 6
            "7a74G7m23Vrp0o5c9016741.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:69.0) Gecko/20100101 Firefox/69.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,386612,1159354,1440,829,1440,900,1440,436,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6014,0.555813812277,785645579676.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1571291159353,-999999,16809,0,0,2801,0,0,3,0,0,CB1FD61B525E4AD1CB73F8987CA63A4F~-1~YAAQJ47tzKBzadNtAQAA21dA2AJHjlayQlr++WMH8nZ8LW156LE5QE/Uzp2rKg1P+taTn2UI/UnCKj7F/H7g/dHrcvcD0nLhZaCwjjmH95l4TkPoODyJa2r//Lt8OIGLdKQPaWcLir802rRoEE76GgmeBf3jLHnRkiLiTpcJALKENFUN/+NvtoK5y/ltvgnnJ1uUWDN+/XqY21kQxei63UzUzlmp0qNvL940yJ+IU85L7BdWIegLA+H/2sPN3U9PCDawgDJKO4oz8/hhQ5RJwnM=~-1~-1~-1,25785,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5796788-1,2,-94,-118,71278-1,2,-94,-121,;2;-1;0",
            // 7
            "7a74G7m23Vrp0o5c9016851.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386612,2536105,1440,829,1440,900,1440,409,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8969,0.555751602277,785646268052.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,1,0,0,0,0,0,1,0,1571292536105,-999999,16809,0,0,2801,0,0,2,0,0,66C061C6B8C9223CAB3743C47A7FBA13~-1~YAAQbfzU2e0Buq9tAQAAmVpV2AIaWKKqxabMnrSRlK3O4LPTJNVJwIS3rdLGP30Zd52y6lDf95Hl0RFsSiEwogqP4hUY28FFhThJpjy0pxFHGhEnv8ORgcyiILKLa7fO0xQZ3WVXSyMWjX/f0EH7ApWGwUdlpTjlxCqIliiiL9VO367GKEjJ4B16J9uHYfR1oj/fXpfHsAdqEUdZxX7U7JILGaglELZRe1BhEsunG1DnPdtHa81KbZISP8lRoD+L4iwaQprzIdsstQICG6F08bo=~-1~-1~-1,26509,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,2536102-1,2,-94,-118,74728-1,2,-94,-121,;2;-1;0",
        ];

        $secondSensorData = [
            null,
            // 1
            "7a74G7m23Vrp0o5c9046001.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389292,2465654,1536,880,1536,960,1536,444,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.10403660752,791091232826.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,2,0,0,1339,864,0;1,2,0,0,1340,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,32,32,0,0,0,0,1545,0,1582182465653,8,16925,0,0,2820,0,0,1546,0,0,D1E2BFB98CCAE7904D95DE5DFF9E847F~-1~YAAQFTk3F+YEX15wAQAAe4FsYQMDFxDNU7o4y5rxvwMR+D9jmGu2QS/Lcr+tq3lXTflCxDADY9meftvWl18+pXP02O5gJTptKRz656jwPLX+8k4PW0QgRiKp5PpT0YIF0ohKPWYSwsMuaNo89oO/4M8ZvbQ7FMVK2cEV1NF4B8O3RDB0Q7OfU6H+NJHRGqgjnvFucHAcdBTDJfq1VrOZBaAg49rC3aFzBm6kFsH9W+Mo5SxtGiR8Mx7yuFjxPNtLY5ZiWojX4RERmRVZwJ/M5g5M1b1a+wo0a3XfTs2zDrJWIgcAmBVcILlHNofcWJg6lo5TMgY3hkgA2hjT4Rw6+yQ=~-1~-1~-1,31381,129,175608234,30261693-1,2,-94,-106,9,1-1,2,-94,-119,39,31,30,36,40,40,26,24,5,5,6,4,9,342,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,1359025438;159734625;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5533-1,2,-94,-116,12328268-1,2,-94,-118,86110-1,2,-94,-121,;3;8;0",
            // 2
            "7a74G7m23Vrp0o5c9046001.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389292,2837917,1536,880,1536,960,1536,444,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.262622660131,791091418958.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,2,0,0,1339,864,0;1,2,0,0,1340,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,118,387,440;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,978,32,0,0,0,946,1618,0,1582182837917,14,16925,0,1,2820,0,0,1618,118,0,3B32123C2E9FC8AA8EF011A3D627E854~-1~YAAQPjk3F63ce1hwAQAAKS9yYQN6r0rzHvHTfDioD6iLBuv8BosXzBwSaDpFsc3cY6DEqnIpfPxSH2bJwc2w2s1i0a5p06uVXTc3eX8gHhICJAYSsASu9oceDLdNgkOBnQ955tYS6EWGQVhgsekjo+kaEaIxgs4bduAGi2245v+N97Pk7koK249j8WskPaKSAZK73am+b9e4E0q7tOLu76/kYyDB7Stj2sN4CY3RBLd7ZrQUNS3tCbot4e4Dv6faelvbLYGjCIGpvQ5yJVLJ7oyzKIATCit/W8lrV8nS35xpXPRQmWe82Vz/D/P/tUfXvfpJO5SJI3B8f3EgG0ihk5g=~-1~-1~-1,31603,323,1560790008,30261693-1,2,-94,-106,9,1-1,2,-94,-119,26,28,32,28,43,19,11,7,6,5,5,5,9,335,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,1359025438;159734625;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5533-1,2,-94,-116,2837909-1,2,-94,-118,87546-1,2,-94,-121,;3;7;0",
            // 3
            "7a74G7m23Vrp0o5c9016741.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386612,630002,1440,829,1440,900,1440,409,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8969,0.898169849449,785645315000.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,2,0,0,1329,864,0;1,2,0,0,1330,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,1,0,0,0,0,0,1141,0,1571290630001,18,16809,0,0,2801,0,0,1143,0,0,A9E06D36EA4352FFA8474A46166D10D2~-1~YAAQbfzU2Wtiua9tAQAATEg42AJMk7gy+m18pPcMDt8sm0EHmmQXLXvspQ4PPe67cViHl77Kb2pezOQC3J5L1r8oLoZ546nkIuSOIesOayBxYZzKCTdoeQOQZjT507Y95jRE57xeiDq1R7VsvT38GW+tOp6ODEDNjuX9vMa4lOmXqNjoOTV3CbbCAIKH4tQdEySyyCIEoEIpJsIOgUrJXDjS5A7ioJ4dcrOp5odpz9lUKWa42Jwpn7Ps9fK7qn8bsuVN2qIRZkdsiVH/ZcGkAWdjxDlcVqjoUr3ifEz2Rg==~-1~-1~-1,28496,580,1639547115,30261693-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,1852631062;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4994-1,2,-94,-116,629995-1,2,-94,-118,79045-1,2,-94,-121,;3;6;0",
            // 4
            "7a74G7m23Vrp0o5c9046091.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:73.0) Gecko/20100101 Firefox/73.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,389291,1923814,1536,880,1536,960,1536,490,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6004,0.866011163433,791090961907,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,32,32,0,0,0,0,544,0,1582181923814,9,16925,0,0,2820,0,0,544,0,0,039D94F8C0098B9BD23514A850D36C6B~-1~YAAQRNxgaHo7Sl9wAQAAtDdkYQNlT4nLxQG2ebJmqTlNESGHgOXYKP9ZWUMFShiILE/NB4alUP5DT+tkOsiQMtgoJZPvQS8XYd6KyIWNvo2i+9jbbJ0IRfKQnV2tbaI/HmFA8+Mf638LyCK/5F9xaaeYJmbZCYq0u14EkgHKqrWV7o2a7bEONAemE/Ew3/Xc1JyBZTqQ4JkLiKLUfgnKvJFqdHTbSS6qOtV4fC3AJWkg5HvAl6m1mdZp1RXxbZPtOmM0P4zq8LJMRytlrkyep9tRue6dEQuAAID0eXiTb7R7zMWIuQg=~-1~-1~-1,28844,715,-760257101,26067385-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,0,200,200,0,0,0,200,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,86571641-1,2,-94,-118,78722-1,2,-94,-121,;3;9;0",
            // 5
            "7a74G7m23Vrp0o5c9046091.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389291,1995808,1536,880,1536,960,1536,498,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.889215048444,791090997903.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,32,32,0,0,0,0,755,0,1582181995807,18,16925,0,0,2820,0,0,755,0,0,18EE9C7D96CE7D6ED155EAD217B4E95B~-1~YAAQDTk3F52IhkZwAQAAs1VlYQNEefvba/DYCF8TcDUo+nvnsiK1jYgIKLUkVS5wdIBInigr+aestBBp45hgo0RM5zqYoec7wyxbuID0qBoSD33oWH0djzXi1gGg9F4DJ/ddFCpL1tF/FkSuL9KLYnChp1M7DSzSAuo1pIi2rVvQDq9lQ+6m+TYB8Os8tyG01G+hLBoNsnYvfLltFe3E7OvlvPL61262kky4ytibi40cur6vi0DFHr3waJUle561oKKz0K7X/XJiSk2YRue+HtmYtOq8YRKlTiVRLX2gaWiQ5Q1dCx287WbMGwSwnh2nFds1MrHn3ETb93de5hfX2lE=~-1~-1~-1,31927,494,1476003279,30261693-1,2,-94,-106,9,1-1,2,-94,-119,26,28,32,27,43,18,11,7,6,5,5,5,10,339,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,1359025438;159734625;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5533-1,2,-94,-116,161660464-1,2,-94,-118,84924-1,2,-94,-121,;3;10;0",
            // 6
            "7a74G7m23Vrp0o5c9016741.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:69.0) Gecko/20100101 Firefox/69.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,386612,1159354,1440,829,1440,900,1440,436,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6014,0.248580295124,785645579676.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,0,2,0,0,1329,864,0;1,2,0,0,1330,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,1,0,0,0,0,0,1485,0,1571291159353,3,16809,0,0,2801,0,0,1486,0,0,CB1FD61B525E4AD1CB73F8987CA63A4F~-1~YAAQJ47tzM1zadNtAQAAZlxA2AKUnoAKbFBETUxZQEPP+e0zqp6L5YTiBP0RSUgq6xAr0l8YUHcW34KnUseEPEV6QQcn6EHEaYAkg+Ic+LqDV9tglZZegvq41jwj8jWqUn/u2iqLsD2R64BrVQRW0ON+mR+adxakS8whpAItt9F48aQ+acN5SYPvoJ76D7ZOVVhgMzlV0whB24P/hE1eiFipKdBNmycpFLFJ4b8OmoJLNggVV+VER9SR3og35QRcY9kHJe8P7/8bkiDIZEBEuXAP38V2rkY/z3x0IG2Orw==~-1~-1~-1,27681,606,-264119972,26067385-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,1241107008;dis;,3;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4835-1,2,-94,-116,5796788-1,2,-94,-118,75509-1,2,-94,-121,;1;6;0",
            // 7
            "7a74G7m23Vrp0o5c9016851.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386612,2536105,1440,829,1440,900,1440,409,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8969,0.787208367393,785646268052.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ae.com/us/en/login?ctx.locale=en&ctx.shipTo=US&ctx.currency=USD-1,2,-94,-115,1,1,0,0,0,0,0,131,0,1571292536105,6,16809,0,0,2801,0,0,132,0,0,66C061C6B8C9223CAB3743C47A7FBA13~-1~YAAQbfzU2e0Buq9tAQAAmVpV2AIaWKKqxabMnrSRlK3O4LPTJNVJwIS3rdLGP30Zd52y6lDf95Hl0RFsSiEwogqP4hUY28FFhThJpjy0pxFHGhEnv8ORgcyiILKLa7fO0xQZ3WVXSyMWjX/f0EH7ApWGwUdlpTjlxCqIliiiL9VO367GKEjJ4B16J9uHYfR1oj/fXpfHsAdqEUdZxX7U7JILGaglELZRe1BhEsunG1DnPdtHa81KbZISP8lRoD+L4iwaQprzIdsstQICG6F08bo=~-1~-1~-1,26509,181,657927905,30261693-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,1852631062;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4994-1,2,-94,-116,2536102-1,2,-94,-118,75064-1,2,-94,-121,;2;5;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        if (!isset($this->key)) {
            $this->key = array_rand($sensorData);
            $this->logger->notice("set key: {$this->key}");
        }
        $this->logger->notice("key: {$this->key}");

        $sensor_data = $secondSensor === false ? $sensorData[$this->key] : $secondSensorData[$this->key];

        return $sensor_data;
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $sensorDataHeaders = [
            'Accept'        => '*/*',
            'Content-type'  => 'application/json',
            'X-NewRelic-ID' => $this->headers['X-NewRelic-ID'],
        ];
        $sensorData = [
            'sensor_data' => $this->getSensorData(),
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $this->getSensorData(true),
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("americaneagle sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $auth_data = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->setProxyMount();
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->http->setUserAgent(null);
            */

//            $selenium->disableImages();;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.ae.com/us/en/");
//            $selenium->http->GetURL("https://www.ae.com/us/en/myaccount/real-rewards");
            $selenium->http->GetURL('https://www.ae.com/us/en/login?' . $this->localeParam);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@name = "login" or @name="submit"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$btn) {
                return false;
            }

            if ($acceptCookieBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"] | //button[@name = "acceptCookie"]'), 0)) {
                $this->savePageToLogs($selenium);
                $acceptCookieBtn->click();
                sleep(2);
            }

//            $selenium->driver->executeScript('$("div.modal-container").hide(); $("div.sidetray-container").hide();');
//            $loginInput->sendKeys($this->AccountFields['Login']);
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
//            $selenium->driver->executeScript('$("div.modal-container").hide(); $("div.sidetray-container").hide();');

            $this->savePageToLogs($selenium);

            $selenium->driver->executeScript("
//                let oldEval = window.eval;
//                window.eval = function(str) {
//                 // do something with the str string you got
//                 return oldEval(str);
//                }
                
//                let oldXHROpen = window.XMLHttpRequest.prototype.open;
//                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
//                    this.addEventListener('load', function() {
//                        if (/access_token/g.exec( this.responseText ) || /.error/g.exec( this.responseText )) {
//                            localStorage.setItem('responseData', this.responseText);
//                        }
//                    });
//                               
//                    return oldXHROpen.apply(this, arguments);
//                };
                
                !function(send){
                    XMLHttpRequest.prototype.send = function (data) {
                        if (/access_token/g.exec( data ) || /.error/g.exec( data )) {
                            localStorage.setItem('auth_data', data);
                          }
                        send.call(this, data);
                    }
                }(XMLHttpRequest.prototype.send);
            ");
            $selenium->driver->executeScript('document.querySelector(\'button[name="login"], button[name = "submit"]\').click();');
//            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //h2[contains(@class, "qa-user-greeting-name")]
                | //div[contains(@class, "alert-danger")]
                | //*[self::li or self::div][contains(@class, "has-error") and contains(@class, "ember-view")]
            '), 10);
            $selenium->waitForElement(WebDriverBy::xpath('//*[self::li or self::div][contains(@class, "has-error") and contains(@class, "ember-view")]'), 0, false);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

//            $auth_data = $selenium->driver->executeScript("return localStorage.getItem('responseData');");;
//            $this->logger->info("got auth data: " . $auth_data);
//            $this->logger->debug(var_export($auth_data, true), ["pre" => true]);

            if ($this->http->FindSingleNode('//div[@id = "page-banner-wormhole"]/@id')) {
                $auth_data = $selenium->driver->executeScript("return localStorage.getItem('aeotoken');");
                $this->logger->info("got auth data: " . $auth_data);
                $this->logger->debug(var_export($auth_data, true), ["pre" => true]);
            }
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $auth_data;
    }
}
