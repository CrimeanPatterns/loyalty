<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerChoice extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected $statements = [];
    protected $lastName = null;
    protected $guestId = null;

    private $endHistory = false;

    private $loyaltyProgramId = 'GP';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        // https://redmine.awardwallet.com/issues/16927#note-5
        $this->setProxyBrightData();

        $userAgentKey = "User-Agent-Safari";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 0) {
            if ($userAgentKey == "User-Agent-Safari") {
                $safariAgents = [
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/534.59.10 (KHTML, like Gecko) Version/5.1.9 Safari/534.59.10',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_5) AppleWebKit/600.8.9 (KHTML, like Gecko) Version/6.2.8 Safari/537.85.17',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/537.86.3',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/601.4.4 (KHTML, like Gecko) Version/9.0.3 Safari/601.4.4',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/600.8.9 (KHTML, like Gecko) Version/8.0.8 Safari/600.8.9',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/601.7.7 (KHTML, like Gecko) Version/9.1.2 Safari/601.7.7',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.2 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12) AppleWebKit/602.1.50 (KHTML, like Gecko) Version/10.0 Safari/602.1.50',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_4) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.1 Safari/603.1.30',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/603.2.4 (KHTML, like Gecko) Version/10.1.1 Safari/603.2.4',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/603.3.8 (KHTML, like Gecko) Version/10.1.2 Safari/603.3.8',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.2 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.2 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.2 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.2 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.3 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.2 Safari/605.1.15',
                ];
                $this->http->setUserAgent($safariAgents[array_rand($safariAgents)]);
            } else {
                $this->http->setRandomUserAgent(20);
            }
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
    }

    public function IsLoggedIn()
    {
        $headers = [
            'Accept' => 'application/json',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.choicehotels.com/webapi/user-account?include=year_to_date_nights%2Cloyalty_account_forfeiture_date%2Cppc_status%2Cdgc_status&preferredLocaleCode=en-us&siteName=us", $headers, 15);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.choicehotels.com/choice-privileges/account');
        $this->http->RetryCount = 2;

        // retries
        if (
            ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && (
                    strstr($this->http->Response['errorMessage'], 'Connection timed out after')
                    || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
                    || strstr($this->http->Error, 'Network error 56 - Recv failure: Connection reset by peer')
                    || strstr($this->http->Error, 'Network error 56 - Unexpected EOF')
                    || strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')
                    || $this->http->Response['code'] == 404
                    || empty($this->http->Response['body'])
                )
        ) {
            throw new CheckRetryNeededException(3);
        }

        //$this->http->GetURL('https://www.choicehotels.com/choice-privileges/account');
//        if (!$this->http->ParseForm("chUserLoginForm")) {
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

//        $this->logger->debug(var_export($this->http->Form, true), ['pre' => true]);
//        if ($this->http->Form == ['username' => '', 'password' => '']) {
//            $this->logger->debug(var_export($this->http->Response['body'], true), ['pre' => true]);
//        }
        if (
            $this->http->Form == ['username' => '', 'password' => '']
            && $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]") && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
        ) {
            $this->DebugInfo = 'Access Denied';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(3);
        }

        $this->http->FormURL = 'https://www.choicehotels.com/webapi/user-account/login';
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('include', "year_to_date_nights,ppc_status");
        $this->http->SetInputValue('preferredLocaleCode', "en-us");
        $this->http->SetInputValue('siteName', "us");

        return true;
    }

    public function Login()
    {
        $retry = false;
        $key = 0;

        if ($sensorDataPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")) {
            $this->http->NormalizeURL($sensorDataPostUrl);
            $sensorData = $this->getSensorDataFromSelenium();

            if ($sensorData !== true) {
                if (!empty($sensorData)) {
                    $this->sendSensorData($sensorData, $sensorDataPostUrl);
                } else {
                    $key = $this->sendStaticSensorData($sensorDataPostUrl);
                }
            }
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        $this->http->RetryCount = 0;
        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $headers = [
            "Accept" => "application/json, text/plain, */*",
            "ADRUM"  => "isAjax:true",
        ];

        if ($sensorData !== true) {
            if (!$this->http->PostForm($headers) && !in_array($this->http->Response['code'], [401, 500])) {
                if ($this->http->FindPreg("/\"outputErrors\":\{\"UNAVAILABLE_GET_PROFILE\":\"We’re currently experiencing a technical issue. Please try signing in later.\"/ims")) {
                    throw new CheckRetryNeededException(3, 2, "We’re currently experiencing a technical issue. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
                }

                return $this->checkErrors();
            }
        }

        // 2fa
        $response = $this->http->JsonLog();

        if (isset($response->status, $response->factors) && $response->status == 'ERROR') {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            foreach ($response->factors as $factor) {
                if ($factor->status == 'ACTIVE' && $factor->type == 'EMAIL') {
                    $headers = [
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ];
                    $this->http->PostURL('https://www.choicehotels.com/webapi/user-account/challenge-mfa', ['factorType' => 'EMAIL'], $headers);
                    $mfa = $this->http->JsonLog();

                    if (isset($mfa->status) && $mfa->status == 'OK') {
                        $this->AskQuestion("We’ve sent a one-time verification code to {$factor->email}. The code expires in 10 minutes.", null, $factor->type);

                        return false;
                    }

                    break;
                }
            }
        }

        if (($this->http->Response['code'] == 403 || $sensorData === true) && $sensorDataPostUrl) {
            if ($this->http->FindPreg("/\"INVALID_LOYALTY_MEMBER_CREDENTIALS_PERMANENT_LOCKOUT\":\"The supplied loyalty account has been permanently locked, please call the support center.\"/ims")) {
                throw new CheckException("Sign in failed", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindPreg("/\"INVALID_LOYALTY_MEMBER_CREDENTIALS_TEMPORARY_LOCKOUT\":\"The supplied loyalty account has been temporarily locked.\"/ims")) {
                throw new CheckException("The username or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindPreg("/\"INVALID_LOYALTY_O2A_MEMBER_CREDENTIALS\":\"Please enter a valid loyalty account\.\"/ims")) {
                throw new CheckException("The username or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindPreg("/outputErrors\":\{\"UNEXPECTED_TECHNICAL_FAILURE\":\"We’re currently experiencing a technical issue. Please try signing in later.\"/ims")) {
                throw new CheckException("We’re currently experiencing a technical issue. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/outputErrors\":\{\"(?:UNEXPECTED_TECHNICAL_FAILURE|UNEXPECTED_O2A_TECHNICAL_FAILURE)\":\"We're sorry, an unexpected error occurred. Please try signing in later.\s*\"/ims")) {
                throw new CheckException("We're sorry, an unexpected error occurred. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/\{\"status\":\"ERROR\",\"outputInfo\":\{\"NONEXISTENT_PPC_DATA_FOR_LOYALTY_PROGRAM\":\"Points plus cash is not available for the selected loyalty program.\"\},\"isEmailTaken\":false,\"isMFAVerifiedInSession\":false,/ims")) {
                throw new CheckException("We’re currently experiencing a technical issue. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        // We're having some trouble connecting to your Choice Privileges account.
        /*
        if ($this->http->FindPreg('/\{"ENROLLMENT_SOURCE":\["INTERNET"\]\}\}\],"pointsPlusCashAccountStatus":\{"statusCode":"INELIGIBLE_REWARD_PROGRAM"\}/')) {
            throw new CheckException("We're having some trouble connecting to your Choice Privileges account.", ACCOUNT_PROVIDER_ERROR);
        }
        */

        // broken account
        if (in_array($this->http->Response['code'], [403, 500]) && in_array($this->AccountFields['Login'], [
            'ChoiceJSH',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 403 && $sensorDataPostUrl) {
            sleep(5);
            $this->sendStatistic(false, $retry, $key);
            $retry = true;

            $key = $this->sendStaticSensorData($sensorDataPostUrl);
            $this->DebugInfo = "key: {$key}";

            $this->http->FormURL = $formUrl;
            $this->http->Form = $form;
            $this->http->RetryCount = 0;

            if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [401, 500])) {
                return $this->checkErrors();
            }

            $this->http->RetryCount = 2;
            $this->http->JsonLog();

            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo = 'need to upd sensor_data (' . (strstr($this->DebugInfo, 'key') ? $this->DebugInfo : "key: {$this->DebugInfo}") . ')';
                $this->sendStatistic(false, $retry, $key);

                throw new CheckRetryNeededException(2);
            }
        }// if ($this->http->Response['code'] == 403 && $sensorDataPostUrl)
        $this->sendStatistic(true, $retry, $key);

        $this->http->RetryCount = 2;

        // Access is allowed
        if ($this->loginSuccessful()) {
            $headers = [
                'Accept' => 'application/json',
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.choicehotels.com/webapi/user-account?include=year_to_date_nights%2Cloyalty_account_forfeiture_date%2Cppc_status&preferredLocaleCode=en-us&siteName=us", $headers, 15);
            $this->http->RetryCount = 2;

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]") && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->DebugInfo = 'Access Denied';
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                throw new CheckRetryNeededException(3);
            }

            return true;
        }
        // Sign in failed
        if ($this->http->FindPreg("/\"(?:INVALID_LOYALTY_MEMBER_CREDENTIALS|INVALID_LOYALTY_O2A_MEMBER_CREDENTIALS)\":\"Please enter a valid loyalty account.\"/ims")) {
            throw new CheckException("The username or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/\"status\":\"ERROR\",\"outputErrors\":\{\"INVALID_LOYALTY_MEMBER_CREDENTIALS_PERMANENT_LOCKOUT\":\"The supplied loyalty account has been permanently locked, please call the support center.\"},/ims")) {
            throw new CheckException("Your account is locked. To protect your account, it's been locked after too many sign-in attempts. Call 1-888-770-6800 to unlock your account.", ACCOUNT_LOCKOUT);
        }

        // Sign in failed - it looks like invalid credentials, but on valid accounts
        if (
            $this->http->FindPreg("/\"UNEXPECTED_FAILURE_GET_PROFILE\":\"We're sorry, an unexpected error occurred\.\"/ims")
            || $this->http->FindPreg("/\"UNEXPECTED_FAILURE\":\"We're sorry, an unexpected error occurred\.\"/ims")
        ) {
            throw new CheckException("Sign in failed", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]") && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->DebugInfo = 'Access Denied';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(3);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "factorType"       => $step,
            "verificationCode" => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $this->http->PostURL('https://www.choicehotels.com/webapi/user-account/verify-mfa', $data, $headers);
        $mfa = $this->http->JsonLog();

        if (isset($mfa->outputErrors->UNEXPECTED_FAILURE_VERIFY_MFA)) {
            $this->AskQuestion($this->Question, $mfa->outputErrors->UNEXPECTED_FAILURE_VERIFY_MFA, $step);
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            $headers = [
                'Accept' => 'application/json',
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.choicehotels.com/webapi/user-account?include=year_to_date_nights%2Cloyalty_account_forfeiture_date%2Cppc_status&preferredLocaleCode=en-us&siteName=us", $headers, 15);
            $this->http->RetryCount = 2;

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]") && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->DebugInfo = 'Access Denied';
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                throw new CheckRetryNeededException(3);
            }

            return true;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, true);
        $guestProfile = ArrayVal($response, 'guestProfile');
        $this->loyaltyProgramId = ArrayVal($guestProfile, 'loyaltyProgramId', 'GP');
        // Member Name
        $this->SetProperty("Name", beautifulName(Html::cleanXMLValue(ArrayVal($guestProfile, 'firstName') . " " . ArrayVal($guestProfile, 'middleName') . " " . ArrayVal($guestProfile, 'lastName'))));
        // for history
        $this->lastName = ArrayVal($guestProfile, 'lastName', null);
        $this->guestId = ArrayVal($guestProfile, 'guestId', null);
        // Member Number
        $this->SetProperty("Number", ArrayVal($guestProfile, 'choicePrivilegeAccountNumber'));
        $loyaltyAccounts = ArrayVal($response, 'loyaltyAccounts', []);
        $accountBalanceUnits = 0;

        foreach ($loyaltyAccounts as $loyaltyAcc) {
            $accountBalanceUnit = ArrayVal($loyaltyAcc, 'accountBalanceUnits');

            if (
                $accountBalanceUnit == 'POINTS'
                && !in_array(ArrayVal($loyaltyAcc, 'loyaltyProgramId'), ['AT', 'VB'])
            ) {
                $loyaltyAccount = $loyaltyAcc;

                break;
            } elseif ($accountBalanceUnit == 'MILES') {
                $accountBalanceUnits++;
            }
        }// foreach ($loyaltyAccounts as $loyaltyAcc)

        if (!isset($loyaltyAccount)) {
            // AccountID: 413254
            // We're sorry, an unexpected error has occurred.
            if (ArrayVal(ArrayVal($response, 'outputInfo'), 'UNAVAILABLE_LOYALTY_ACCOUNT') == 'No loyalty account associated to this guest profile.') {
                throw new CheckException("We're sorry, an unexpected error has occurred.", ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 1334959 / 4441805 / 612540
            elseif ($accountBalanceUnits >= 1) {
                $this->SetBalanceNA();
            }
            // AccountID: 3714455
            elseif (count($loyaltyAccounts) == 1 && ArrayVal($loyaltyAcc, 'loyaltyProgramId') == 'AT') {
                $this->SetBalanceNA();
            }
            // AccountID: 4543263, 3646209
            elseif (
                !empty($this->Properties['Name'])
                && !empty($this->Properties['Number'])
                && empty($loyaltyAccounts)
//                && ArrayVal($guestProfile, 'yourExtrasPreference') == "CP Points"
            ) {
                $this->SetBalanceNA();
            // We're having some trouble connecting to your Choice Privileges account.  // AccountID: 2091506, 4054311
            } elseif (ArrayVal(ArrayVal($response, 'inputErrors'), 'userAccountGetProfileFormBean') == 'Please login.  [EnglisH]') {
                throw new CheckException("We're having some trouble connecting to your Choice Privileges account.", ACCOUNT_PROVIDER_ERROR);
            } elseif (ArrayVal(ArrayVal($response, 'outputInfo'), 'NONEXISTENT_PPC_DATA_FOR_LOYALTY_PROGRAM') == 'Points plus cash is not available for the selected loyalty program.') {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }// if (!isset($loyaltyAccount))
        // Balance - Choice Privileges Points
        $this->SetBalance(ArrayVal($loyaltyAccount, 'accountBalance'));
        // Member Since
        $memberSince = ArrayVal($loyaltyAccount, 'memberSince', null);

        if ($memberSince) {
            $this->SetProperty("MemberSince", date("M d, Y", strtotime($memberSince)));
        }
        // Exp date // refs #12872
        // Keep your points active by completing one of many
        $exp = ArrayVal($response, 'loyaltyAccountForfeitureDate', null);
        $expirationDate = null;

        if ($exp && ($exp = strtotime($exp))) {
            $expirationDate = $exp;
        }
        $this->logger->notice("Exp date from Profile -> {$expirationDate} ");
        // Nights to next status
        $yearToDateEliteNights = ArrayVal($response, 'yearToDateEliteNights', null);
        $this->logger->debug("YTD Elite Nights: {$yearToDateEliteNights}");

        if ($yearToDateEliteNights == 0) {
            $status = "None";
            $nightsNeeded = 10;
        } else {
            switch ($yearToDateEliteNights) {
            case $yearToDateEliteNights < 10:
                $status = "None";
                $nightsNeeded = 10;

                break;

            case $yearToDateEliteNights >= 10 && $yearToDateEliteNights < 20:
                $status = "Gold";
                $nightsNeeded = 20;

                break;

            case $yearToDateEliteNights >= 20 && $yearToDateEliteNights < 40:
                $status = "Platinum";
                $nightsNeeded = 40;

                break;

            case $yearToDateEliteNights >= 40:
                $status = "Diamond";
                $nightsNeeded = 0;

                break;

            default:
                $this->sendNotification("choice - refs #16927. Something went wrong with status");
                $status = '';
                $nightsNeeded = 0;

                break;
        }
        }// switch ($yearToDateEliteNights)
        // Elite Status
        $this->SetProperty("ChoicePrivileges", ArrayVal($loyaltyAccount, 'eliteLevel', null) ?? $status);
        // Nights to next status
        if ($nightsNeeded > 0) {
            $this->SetProperty("Eligible", $nightsNeeded - intval($yearToDateEliteNights));
        }

        if (empty($this->Properties['Number'])) {
            return;
        }
        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.choicehotels.com/webapi/user-account/loyalty-statement-summaries?loyaltyAccountNumber={$this->Properties['Number']}&loyaltyProgramId={$this->loyaltyProgramId}&preferredLocaleCode=en-us");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0, true);
        $statements = ArrayVal($response, 'statements', []);
        $this->statements = $statements;

        if (!empty($statements[0])) {
            $statement = $statements[0];
            $expirations = ArrayVal($statement, 'expirations', []);
            // Points expiring
            if (!empty($expirations)) {
                foreach ($expirations as $expiration => $pointsExpiring) {
                    $this->logger->debug("[{$expiration} / " . strtotime($expiration) . "]: expire {$pointsExpiring}");
                    // Points expiring
                    $this->SetProperty("PointsExpiring", $pointsExpiring);

                    if (strtotime($expiration) < $expirationDate || !isset($expirationDate)) {
                        $this->logger->notice("Set new Expiration Date: {$expiration}");
                        $expirationDate = strtotime($expiration);
                    }// if ($eDate < $expirationDate || !isset($expirationDate))
                }// foreach ($expirations as $expiration => $pointsExpiring)
            }// foreach ($expirations as $expiration => $pointsExpiring)
            // Beginning Balance
            $this->SetProperty("BeginningBalance", ArrayVal($statement, 'beginningBalance'));
            // Points Earned
            $this->SetProperty("PointsEarned", ArrayVal($statement, 'earned'));
            // Points Redeemed
            $this->SetProperty("PointsRedeemed", ArrayVal($statement, 'redeemed'));
            // Points Adjusted
            $this->SetProperty("PointsAdjusted", ArrayVal($statement, 'adjusted'));
        }// if (!empty($statements[0]))

        if ($expirationDate) {
            $this->SetExpirationDate($expirationDate);
        }
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName"      => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ParseItineraries()
    {
        $result = [];

        if (empty($this->guestId) || empty($this->lastName) || empty($this->Properties['Number'])) {
            $this->logger->error("something went wrong");

            return [];
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-2 year"));

        $this->http->GetURL('https://www.choicehotels.com/webapi/reservation/summaries?deviceType=DESKTOP&endDate=' . $endDate . '&guestId=' . $this->guestId . '&include=current_reservations&loyaltyAccountNumber=' . $this->Properties['Number'] . '&loyaltyProgramId=' . $this->loyaltyProgramId . '&preferredLocaleCode=en-us&reservationLookupStatusList=RESERVED%2CCANCELLED&startDate=' . $startDate . '&siteName=us');
        $response = $this->http->JsonLog(null, 0, true);
        $currentReservations = ArrayVal($response, 'currentReservations', []);
        $this->logger->debug("Total " . count($currentReservations) . " itineraries were found");

        foreach ($currentReservations as $currentReservation) {
            $result[] = $this->ParseReservation($currentReservation);
        }

        if (empty($currentReservations) && $this->http->FindPreg("/\{\"status\":\"OK\",\"outputInfo\":\{\"NONEXISTENT_RESERVATION_INFO\":\"No reservations exist for the supplied criteria. Check that the criteria is correct.\"\}\}$/")) {
            if ($this->ParsePastIts) {
                $this->http->RetryCount = 1;
                $pastItineraries = $this->parsePastReservations($endDate, $startDate);
                $this->http->RetryCount = 2;

                if (!empty($pastItineraries)) {
                    return $pastItineraries;
                }
            }// if ($this->ParsePastIts)

            return $this->noItinerariesArr();
        }
        // parse past reservations  // refs #16862
        if ($this->ParsePastIts) {
            $result = array_merge($result, $this->parsePastReservations($endDate, $startDate));
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.choicehotels.com/reservations";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->attempt == 1) {
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'de');
        } elseif ($this->attempt == 2) {
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'es');
        }

        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(3, 0);
        }

        /* -------------------------------------------------------- */
        // may be not exist
        $assetFingerprint = $this->http->FindPreg("/'assetFingerprint':\s*'([^']+)/");

        if ($assetFingerprint) {
            $this->http->GetURL("https://www.choicehotels.com/{$assetFingerprint}/app/account/reservations/reservations.html");
        }
        /* -------------------------------------------------------- */

        if (!$this->http->ParseForm("reservationsConfirmationForm")) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $result = $this->reservationForm($arFields["ConfNo"], $arFields["LastName"], []);

        if (!empty($result) && is_string($result)) {
            return $result;
        }

        $it = $result;

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Dates" => "PostingDate",
            "Description"    => "Description",
            "Points"         => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (empty($this->statements) || empty($this->lastName) || empty($this->Properties['Number'])) {
            return [];
        }

        $page = 0;

        foreach ($this->statements as $statement) {
            $this->logger->debug("[Page: {$page}]");
            $statementPeriodStartDate = ArrayVal($statement, 'startDate');
            $params = [
                'loyaltyAccountLastName'   => $this->lastName,
                'loyaltyAccountNumber'     => $this->Properties['Number'],
                'loyaltyProgramId'         => $this->loyaltyProgramId,
                'preferredLocaleCode'      => 'en-us',
                'statementPeriodStartDate' => $statementPeriodStartDate,
                'siteName'                 => 'us',
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.choicehotels.com/webapi/user-account/loyalty-statement', $params);
            $this->http->RetryCount = 2;

            if ($this->http->Response['code'] != 200) {
                break;
            }

            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));
            $page++;

            if ($this->endHistory) {
                break;
            }
        }// foreach ($this->statements as $statement)

        usort($result, function ($a, $b) {
            $key = 'Activity Dates';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        $this->getTime($startTimer);

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/\{\"status\":\"OK\"/")) {
            return true;
        }

        if ($this->http->FindPreg("/\{\"status\":\"PARTIAL\"/")) {
            return true;
        }

        if ($this->http->Response['code'] == 200 && $this->http->getCookieByName("UserHasLoggedIn") == true) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        // retries
        if (
            ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && $this->http->Response['code'] == 403
            && $this->http->FindPreg("/(?:<html>Banned: Detecting too many failed attempts from your IP\. Access is denied until the ban expires\.<\/html>|<H1>Access Denied<\/H1>)/")
        ) {
            throw new CheckRetryNeededException(3);
        }
        // It's not you - it's us! choicehotels.com is temporarily unavailable.
        if ($this->http->FindSingleNode('//p[contains(text(), "It\'s not you - it\'s us! choicehotels.com is temporarily unavailable.")]')) {
            throw new CheckException("It's not you - it's us! choicehotels.com is temporarily unavailable. Please try again in a few minutes. Thank you, and our apologies for the delay.", ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable - Zero size object
        if ($this->http->FindSingleNode("
                //h1[contains(text(), 'Service Unavailable - Zero size object')]
                | //h1[contains(text(), 'Internal Server Error - Read')]
                | //h1[contains(text(), '504 Gateway Time-out')]
                | //title[contains(text(), '502 Bad Gateway')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function reservationForm($confNo, $lastName, $res)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->FormURL = "https://www.choicehotels.com/webapi/reservation";
        $this->http->SetInputValue("deviceType", 'DESKTOP');
        $this->http->SetInputValue("preferredLocaleCode", 'en-us');
        $this->http->SetInputValue("siteName", 'us');
        $this->http->SetInputValue("confirmOrCancelId", $confNo);
        $this->http->SetInputValue("lastName", $lastName);
        $this->http->SetInputValue("guestDataSource", 'RESERVATION');
        $this->http->SetInputValue("searchType", 'CONFIRMATION');
        $this->http->unsetInputValue("include");
        $this->http->unsetInputValue("username");
        $this->http->unsetInputValue("password");
        $this->http->PostForm();
        $this->http->RetryCount = 2;
        // We were unable to locate this reservation. Please try again.
        if ($message = $this->http->FindPreg("/\"INVALID_RESERVATION_ID\":\"([^\"]+)/ims")) {
            return $message;
        }

        if ($message = $this->http->FindPreg("/\"UNEXPECTED_FAILURE_RESERVATION_INFO\":\"([^\"]+)/ims")) {
            return "We're sorry, an unexpected error occurred";
        }

        if ($message = $this->http->FindPreg("/\"INVALID_RESERVATION_ID_AND_LAST_NAME\":\"([^\"]+)/ims")) {
            return "Confirmation/Last Name not found";
        }

        if ($this->http->FindPreg("/\"status\":\"OK\"/")) {
            $res = $this->ParseConfirmationJSON($res);
        }

        return $res;
    }

    private function parsePastReservations($endDate, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->http->GetURL('https://www.choicehotels.com/webapi/reservation/summaries?deviceType=DESKTOP&endDate=' . $endDate . '&guestId=' . $this->guestId . '&include=past_stays&loyaltyAccountNumber=' . $this->Properties['Number'] . '&loyaltyProgramId=' . $this->loyaltyProgramId . '&preferredLocaleCode=en-us&startDate=' . $startDate);
        $this->logger->info("Past Reservations", ['Header' => 3]);
        $response = $this->http->JsonLog(null, 0, true);
        $pastIts = ArrayVal($response, 'pastReservations', []);
        $this->logger->debug("Total " . count($pastIts) . " past itineraries were found");

        foreach ($pastIts as $pastIt) {
            $res['Kind'] = "R";
            $res['ConfirmationNumber'] = ArrayVal($pastIt, 'hotelName');
            $this->logger->info('Parsing itinerary #' . $res['ConfirmationNumber'], ['Header' => 3]);

            // Status
            $res['Status'] = ArrayVal($pastIt, 'reservationStatus');

            // Cancelled reservation
            if (stristr($res['Status'], "Cancelled")) {
                $res['Cancelled'] = true;
                $this->logger->debug('Parsed itinerary:');
                $this->logger->debug(var_export($res, true), ['pre' => true]);

                $result[] = $res;

                continue;
            }// if (stristr($res['Status'], "Cancelled"))

            // HotelName
            $res['HotelName'] = ArrayVal($pastIt, 'hotelName');
            // EarnedAwards
            $res['EarnedAwards'] = ArrayVal($pastIt, 'pointsEarned');
            // CheckInDate
            $res['CheckInDate'] = strtotime(ArrayVal($pastIt, 'checkInDate'));
            // CheckOutDate
            $res['CheckOutDate'] = strtotime(ArrayVal($pastIt, 'checkOutDate'));
            // Address
            $res['Address'] = strtotime(ArrayVal($pastIt, 'hotelLocation'));
            $address = ArrayVal($pastIt, 'address');
            $result["DetailedAddress"] = [
                [
                    "AddressLine" => trim(ArrayVal($address, 'line1') . ' ' . ArrayVal($address, 'line2')),
                    "CityName"    => ArrayVal($address, 'city'),
                    "PostalCode"  => ArrayVal($address, 'postalCode'),
                    "StateProv"   => ArrayVal($address, 'subdivision'),
                    "Country"     => ArrayVal($address, 'country'),
                ],
            ];

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($res, true), ['pre' => true]);

            $result[] = $res;
        }

        return $result;
    }

    private function ParseConfirmationJSON($result = [])
    {
        $response = $this->http->JsonLog();
        $result['Kind'] = "R";
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $response->reservation->confirmationId ?? null;
        // GuestNames
        $result['GuestNames'] = beautifulName(isset($response->reservation->guest->firstName, $response->reservation->guest->lastName) ? $response->reservation->guest->firstName . " " . $response->reservation->guest->lastName : '');
        // AccountNumber
        $result['AccountNumbers'] = $response->reservation->guest->loyaltyAccountNumber ?? null;
        // HotelName
        $result['HotelName'] = $response->hotel->name ?? null;
        // CheckInDate
        $date = $response->reservation->checkInDate ?? '';
        $time = $response->hotel->checkIn ?? '';

        if (isset($date)) {
            $result['CheckInDate'] = strtotime("$date $time");
        }
        // CheckOutDate
        $date = $response->reservation->checkOutDate ?? '';
        $time = $response->hotel->checkOut ?? '';

        if (isset($date)) {
            $result['CheckOutDate'] = strtotime("$date $time");
        }
        // DetailedAddress
        $result["DetailedAddress"] = [
            [
                "AddressLine" => $response->hotel->address->line1 ?? '',
                "CityName"    => $response->hotel->address->city ?? '',
                "PostalCode"  => $response->hotel->address->postalCode ?? '',
                "StateProv"   => $response->hotel->address->subdivision ?? '',
                "Country"     => $response->hotel->address->country ?? '',
            ],
        ];
        // Address
        if (isset($result["DetailedAddress"][0])) {
            $result['Address'] = Html::cleanXMLValue(str_replace(', ,', ', ', implode(", ", $result["DetailedAddress"][0])));
        }
        // Phone
        $result['Phone'] = $response->hotel->phone ?? null;
        // Fax
        $result['Fax'] = $response->hotel->fax ?? null;
        // CancellationPolicy
        $result['CancellationPolicy'] = $response->reservation->cancellationPolicyText ?? null;
//        $result['CancellationPolicy'] = $response->reservation->cancellationDeadline ?? null;
        // RateType
        $result['RateType'] = $response->reservation->ratePlanDetail->name ?? null;
        // Rate, RoomType, RoomTypeDescription, Guests, Kids
        if (isset($response->reservation->rooms)) {
            // Rooms
            $result['Rooms'] = count($response->reservation->rooms);

            foreach ($response->reservation->rooms as $room) {
                $nights = $room->nights ?? '';
                $price = isset($room->avgNightlyPoints) ? ' : ' . $room->avgNightlyPoints : '';
                isset($room->description) ? $roomDescAr[] = $room->description : null;
                isset($room->thumbCaption) ? $roomTypeAr[] = $room->thumbCaption : null;
                isset($room->adults) ? $roomAdultAr[] = $room->adults : null;
//            isset($room->kids) ? $roomKidsAr[$i] = $room->kids : null;
                $arRate[] = $nights . $price;
            }

            if (isset($arRate[0])) {
                $result['Rate'] = implode(' | ', $arRate);
            }

            if (isset($roomTypeAr[0])) {
                $result['RoomType'] = implode(' | ', $roomTypeAr);
            }

            if (isset($roomDescAr[0])) {
                $result['RoomTypeDescription'] = implode(' | ', $roomDescAr);
            }

            if (isset($roomAdultAr[0])) {
                $result['Guests'] = array_sum($roomAdultAr);
            }
//            if (isset($roomKidsAr[0]))
//                $result['Kids'] = array_sum($roomKidsAr);
        }// if (isset($response->reservation->rooms))

        // Cost
        $result['Cost'] = $response->reservation->totalBeforeTax ?? null;
        // Total
        $result['Total'] = $response->reservation->totalAfterTax ?? null;
        // Taxes
        if (!empty($result['Cost']) && !empty($result['Total'])) {
            $result['Taxes'] = PriceHelper::cost($result['Total'] - $result['Cost']);
        }
        // Currency
        $result['Currency'] = $response->reservation->currencyCode ?? null;

        return $result;
    }

    private function ParseReservation($reservation)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['Kind'] = "R";
        $result['ConfirmationNumber'] = ArrayVal($reservation, 'confirmationId');
        $this->logger->info('Parsing itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);
        // Status
        $result['Status'] = ArrayVal($reservation, 'reservationStatus');

        // Cancelled reservation
        if (stristr($result['Status'], "Cancelled")) {
            $result['Cancelled'] = true;
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($result, true), ['pre' => true]);

            return $result;
        }// if (stristr($res['Status'], "Cancelled"))

        // HotelName
        $result['HotelName'] = ArrayVal($reservation, 'hotelName');
        // Rooms
        $result['Rooms'] = ArrayVal($reservation, 'numberOfRooms');
        // CheckInDate
        $result['CheckInDate'] = strtotime(ArrayVal($reservation, 'checkInDate'));
        // CheckOutDate
        $result['CheckOutDate'] = strtotime(ArrayVal($reservation, 'checkOutDate'));
        // Address
        $result['Address'] = ArrayVal($reservation, 'hotelLocation');
        $address = ArrayVal($reservation, 'address');
        $result["DetailedAddress"] = [
            [
                "AddressLine" => trim(ArrayVal($address, 'line1') . ' ' . ArrayVal($address, 'line2')),
                "CityName"    => ArrayVal($address, 'city'),
                "PostalCode"  => ArrayVal($address, 'postalCode'),
                "StateProv"   => ArrayVal($address, 'subdivision'),
                "Country"     => ArrayVal($address, 'country'),
            ],
        ];

        $res = $this->reservationForm($result['ConfirmationNumber'], ArrayVal($reservation, 'lastName'), $result);

        if (!empty($res) && is_array($res)) {
            $result = $res;
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0, true);
        $earned = ArrayVal($response, 'earned', []);
        $this->logger->debug("Total " . count($earned) . " earned transactions were found");
        $redeemed = ArrayVal($response, 'redeemed', []);
        $this->logger->debug("Total " . count($redeemed) . " redeemed transactions were found");
        $adjusted = ArrayVal($response, 'adjusted', []);
        $this->logger->debug("Total " . count($adjusted) . " adjusted transactions were found");

        $hotels = ArrayVal($response, 'hotels', []);
        // POINTS EARNED
        foreach ($earned as $e) {
            $dateStr = ArrayVal($e, 'startDate', null);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $hotelId = ArrayVal($e, 'hotelId', null);

            if ($hotelId) {
                $this->logger->debug("hotelId: {$hotelId}");
                $activity = $hotelId;
                $hotelIdInfo = ArrayVal($hotels, $hotelId, null);

                if ($hotelIdInfo) {
                    $name = ArrayVal($hotelIdInfo, 'name', null);

                    if ($name) {
                        $activity .= '; ' . $name;
                    }
                    $address = ArrayVal($hotelIdInfo, 'address', null);
                    $city = ArrayVal($address, 'city', null);

                    if ($city) {
                        $activity .= '; ' . $city;
                    }
                    $subdivision = ArrayVal($address, 'subdivision', null);

                    if ($subdivision) {
                        $activity .= ', ' . $subdivision;
                    }
                }// if ($hotelIdInfo)
            }// if ($hotelId)
            else {
                $activity = ArrayVal($e, 'description');
            }

            $result[$startIndex]['Activity Dates'] = $postDate;
            $result[$startIndex]['Description'] = $activity;
            $result[$startIndex]['Points'] = ArrayVal($e, 'points');
            $startIndex++;
        }
        // POINTS REDEEMED
        foreach ($redeemed as $r) {
            $dateStr = ArrayVal($r, 'startDate', null);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $hotelId = ArrayVal($r, 'hotelId', null);

            if ($hotelId) {
                $this->logger->debug("hotelId: {$hotelId}");
                $activity = $hotelId;
                $hotelIdInfo = ArrayVal($hotels, $hotelId, null);

                if ($hotelIdInfo) {
                    $name = ArrayVal($hotelIdInfo, 'name', null);

                    if ($name) {
                        $activity .= '; ' . $name;
                    }
                    $address = ArrayVal($hotelIdInfo, 'address', null);
                    $city = ArrayVal($address, 'city', null);

                    if ($city) {
                        $activity .= '; ' . $city;
                    }
                    $subdivision = ArrayVal($address, 'subdivision', null);

                    if ($subdivision) {
                        $activity .= ', ' . $subdivision;
                    }
                }// if ($hotelIdInfo)

                $cancellation = ArrayVal($r, 'cancellation', null);

                if ($cancellation) {
                    $activity .= ' (cancelled)';
                }
            }// if ($hotelId)
            else {
                $activity = ArrayVal($r, 'description');
            }

            $result[$startIndex]['Activity Dates'] = $postDate;
            $result[$startIndex]['Description'] = $activity;
            $result[$startIndex]['Points'] = ArrayVal($r, 'points');
            $startIndex++;
        }
        // POINTS ADJUSTED
        foreach ($adjusted as $adj) {
            $dateStr = ArrayVal($adj, 'startDate', null);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $result[$startIndex]['Activity Dates'] = $postDate;
            $result[$startIndex]['Description'] = ArrayVal($adj, 'description');
            $result[$startIndex]['Points'] = ArrayVal($adj, 'points');
            $startIndex++;
        }

        return $result;
    }

    private function sendSensorData($sensor_data, $sensorDataPostUrl)
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $data = [
            "sensor_data" => $sensor_data,
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        sleep(1);
        $this->http->PostURL($sensorDataPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);
    }

    private function getSensorDataFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_choice" . sha1($this->http->getDefaultHeader("User-Agent"));
        $data = $cache->get($cacheKey);

        if (!empty($data) && $this->attempt <= 1 && $retry === false) {
            return $data;
        }

        $configs = [0, 1, 2, 3];
        $config = $configs[array_rand($configs)];

        /*
        if ($this->attempt == 1) {
            $config = 3;
        }
        */

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $request = FingerprintRequest::chrome();

            switch ($config) {
                case 0:
                    $selenium->useGoogleChrome();
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 3;

                    break;

                case 1:
                    $selenium->useChromium();
                    $request->platform = 'Linux x86_64';

                    break;

                case 2:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
                    $request->platform = 'Win32';

                    break;

                case 3:
                    $selenium->useFirefox();

                    $request = FingerprintRequest::firefox();
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
                    $request->platform = 'Linux x86_64';

                    break;
            }

            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.choicehotels.com/choice-privileges/account");
            } catch (TimeOutException | ScriptTimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }
            sleep(3);
            $login = $selenium->waitForElement(WebDriverBy::id('cpSignInUsername'), 5);
            $pass = $selenium->waitForElement(WebDriverBy::id('cpSignInPassword'), 0);
            $this->savePageToLogs($selenium);

            if ($login && $pass) {
                $this->logger->info("login form loaded");
//                $selenium->driver->executeScript("(function(send) {
//                    XMLHttpRequest.prototype.send = function(data) {
//                      console.log('ajax');
//                      console.log(data);
//                      if (!localStorage.getItem('sensor_data'))
//                      localStorage.setItem('sensor_data', data);
//                    };
//                })(XMLHttpRequest.prototype.send);");
                //$login->click();
//                sleep(1);
//                $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");
//                $this->logger->info("got sensor data: " . $sensor_data);

                $this->savePageToLogs($selenium);

                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->duration = rand(50000, 90000);
                $mover->steps = rand(50, 70);

                try {
                    $mover->moveToElement($login);
                    $mover->click();
                } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                    $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage());
                }
                $login->click();
                $mover->sendKeys($login, $this->AccountFields['Login'], 5);

                try {
                    $mover->moveToElement($pass);
                    $mover->click();
                } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                    $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage());
                }
                $pass->click();
                $mover->sendKeys($pass, $this->AccountFields['Pass'], 5);

//                $login->sendKeys($this->AccountFields['Login']);
//                $pass->sendKeys($this->AccountFields['Pass']);

                $selenium->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener("load", function() {
                            if (/webapi\/user-account\/login/g.exec(url)) {
                                localStorage.setItem("responseData", this.responseText);
                            }
                        });
                        return oldXHROpen.apply(this, arguments);
                    };
                ');

                $selenium->driver->executeScript('
                    const constantMock = window.fetch;
                    window.fetch = function() {
                        console.log(arguments);
                        return new Promise((resolve, reject) => {
                            constantMock.apply(this, arguments)
                                .then((response) => {                
                                    if(response.url.indexOf("/webapi/user-account/login") > -1) {
                                        response
                                         .clone()
                                         .json()
                                         .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                                    }
                                    resolve(response);
                                })
                                .catch((error) => {
                                    reject(response);
                                })
                        });
                    }
                ');

                if ($btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "btn-login")]'), 0)) {
                    $btn->click();
//                    $selenium->driver->executeScript('document.querySelector(\'button.submit-button\').click()');
                    $selenium->saveResponse();
                    $res = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cp-user-points")]'), 10);
                    $selenium->saveResponse();
                    $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                    $this->logger->info("[Form responseData]: " . $responseData);

                    if (!empty($responseData)) {
                        $this->http->SetBody($responseData, false);

                        $cookies = $selenium->driver->manage()->getCookies();

                        foreach ($cookies as $cookie) {
                            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                        }

                        return true;
                    }

                    if (empty($responseData) && $res) {
                        $cookies = $selenium->driver->manage()->getCookies();

                        foreach ($cookies as $cookie) {
                            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                        }

                        return true;
                    }
                }

                if (!empty($sensor_data)) {
                    $data = @json_decode($sensor_data, true);

                    if (is_array($data) && isset($data["sensor_data"])) {
                        $cache->set($cacheKey, $data["sensor_data"], 1000);

                        return $data["sensor_data"];
                    }
                } else {
                    $cookies = $selenium->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }
                }
            }
        } catch (
            /*
            TimeOutException
            |
            */
            SessionNotCreatedException
            | UnexpectedAlertOpenException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            // retries
            $retry = true;
        }// catch (TimeOutException $e)
        finally {
            StatLogger::getInstance()->info("choice login attempt", [
                "success"      => isset($res) || !empty($responseData),
                "browser"      => $selenium->seleniumRequest->getBrowser() . ":" . $selenium->seleniumRequest->getVersion(),
                "userAgentStr" => $selenium->http->userAgent,
                "proxy"        => "lumunati-us:" . $selenium->http->getProxyAddress(),
                //                "resolution"     => $selenium->seleniumOptions->resolution[0] . "x" . $selenium->seleniumOptions->resolution[1],
                "attempt"      => $this->attempt,
                "isWindows"    => stripos($this->http->userAgent, 'windows') !== false,
                "config"       => $config,
            ]);

            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("choice sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function sendStaticSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        // https://www.choicehotels.com/choice-privileges/account

        $sensorData = [
            // 0
            '2;3749188;4602164;10,0,0,0,2,0;>K{UW^%g%WX$wK*b}J5eFDjguS|_}Hv634tOid6^?-|PN!x3mz(kKDR(=w7%I?Eexzw?Id?c[-.tgLCmEZcN@>Su3#K;Zic5w/t2>g0f42>*=iY)t+? s+Cb3$OExW>`peky};SGI/=&-rrQ1ErKIe|Z!jRD{a8h$J2.LzVJ6#0|5| mIo)#oLRDs_:7ig1}?O]M ;*uidLm|u#aH~@AKj:iB<%xbYB bee5u7,<D1^Z1mm`EWzXXrA-`iHLF?&o=akrB+8-C@ %UJcQGuDUR/JA1% uo*XQXJa_Hmjm!AKJmw7*.d<I`,YRAP?R7M?8`3Z`CHnfDBl!OEi_nP~M]5fSFlSsYmhsF,BJ)t$iz:orty1PM,+VP!gVB6N& K+2u||1)T9]75CF%Fge5~_hC7Km40U)}Yt<*>qUOZU2]&:Ehv,XvzU^99A>;i_w%,gO#*s{0$$a`/63$YFlJ?Y&rpj+/,NVE:CzNU[eNRkIadBAv`Ki-+Lnk$MOdut<tP~glJ-Ly0ZfR^ta?sg_(7 ^Oa:cC2E#.V:]#BlbG$q$*J_p,y.GLccLLP4y>_}a#C]_Xq6Ae)S`V{`M1E,X?Nms1)vQ67hd~B6CZcHb:MPW`1@{V7t$_@Z/xY-XwP`C4sKv/J0NX(sid},*g9sX[@5ZC)+x_%7`Y!c5G>~}Ni`sbqjJU>4]im-p4 USVAuMV=qB5=OPD]phfPzLW+j3sz*N>`{*taO@qXfzF*Ts-?0!9F_1Ob-gKIY;b#~u3U3n:`+^c|n_ lF<8]u<)UJ:B)AuAKCCwUWc/X-j{AXeL?qf/j8ABR)yc;pl~VgfOSPP`>%x=ov,v42gAwCgaWEUA(]_L HA<XYl[aGOp[dfQ~$pB_q-!QxuuQ4IN8d_PUukgOa]F%;J4k/y*Iv1Xk75A%Y/7s/O9K[tpz,$UwW~_KFYx>e1Sl93N.wLaGd((8LVa9!0FTiD$u#%^u[sKi,VVNoZ~oQb]>#Gcru3qq*G%,Y_*O.n9]a4_D_l)(h #Eu-9Bd|ky*Q[Lo{Ft[/&2kW ys{>&>?KI%ZkEM``R(SDYlGh,3X@N?p326Y=917U,D!(^~8A9r3ANtP}I7f[+y0ggyN)v)Md9^3i/AH`jE;;6B.0SMalQBf<?X1rx{MTNX@u<e%#6xbtqU{sTG.~+Iz[]mh>y[.T2|8lPr^QVQ/K0)f[59x~mW2]$9?t.k`*xJ;dRyIhp[Fb1_=,~%qk0&~*7J:4P^<iT0kT>tMWxza%H|:j[YQjEU|v@TW<Y$bQ}T[2@V2e /m*MI!3x+v<4nxc>`7Wv]_*Ml-DqG)NpfMB]@z+h);L~B=&V[=1+H3)oSM:8wj&^Xb#YUBZMrt@+Ff,V619a0!plWVPfBNZ#GDy1BHQ]Pyw)6bY%zw{0zrpt[lfgfR{;jyb4>f<f!06P{j|G3BZ2hbWtboE|rhAn-*h= u$Vu$7Y -3}^-)ZC?8FEblysiN%FpQ:=Dp.Uq=@jSA~f/B6ppp|+9SzizW;o|e-_x<Y(L:H5{,)pOSH44TS[A=P4bL5T)rr#7Pz~e5lnlvJC|3YGUWq,og5jIG2Xdo%1Ba:*NU^1Th)`/qWQDl0A7;_Jwvt]nts-Q%O8x^/c*JEQx_HbkmFc3YyGTD.xNP_*J0]R(k6/Pz r](|i5A<~{:t3j]%/yx{N8#VN  MCj=8n^DPVuY8gIlMm$59rHo,R&j>L }HHT4DvO8MS/?-K<c?m>:I?v8@yU>&!gzTRzZS)yls{~Pd5}V/>nSVBBSsJqs[lWfGTnOr:zo&NtLfc^8:D#KZPC] dd?}:}3t!gQ+2~^z AgK4&n[$r%U=7b+)JLVTUpPpH85{yn>I<%msS)+fJn-51y!pamd|r,~)rp7nhg.RBzradNc(ztccgKqeSl)& M=[@8j4TT#D6( /n[Koh>2NO&8Q[S[W&FKa_X;jz)]5tlozwhPM^~aitb+H-?ew+1WzloF*JR1j]VKZi]p^c8e(7!ci[u.[lKPzo{i?_rt5-u;0KXd_-,k2G1yEj6Br5]Dk_wMH#^<%63?Sh=9](iRnGXft61ZYR[|R|qbtp&ryUGnk};U?pU_0QG/U[~DOlUyC.LMB&&}v{{?O/3Z_igM|{93Kb5UMvm!Lr%&wE{wki{SiYQksP0#0#{!GhDtSz*AulR][%XnOm_PeDa0@FL`/g`a<HS[K;tJ2E2z&69H8W]Ar=z<Hxk<V*<>s9)LH&|O?M(INBclhNZ155Y:Riw(`F``;bOE,~xC8h@VdG|4Ah7q7#A%Bn-5zSw8kC5j#RZp#l>qhJ8Qb)n4a^*t%cy2}n,<3TTv::8rnHj%O2+er@^K!*0%^.CVUCk}iFi1E6dNV.-4LN7XhN,wqE/uY*Smd_+E7smXmJ`DKCL#B;sXv_.+_?XJQcHQEDn82yHz[aJ1I$}Fxr~:cO/aCrW%/)LD|%KDHWlp(/KD[QrsOTXPL9zLElveJzT)BDy^HCIi{!QEbZT1PpxR |WoZejbK{rKUoQr*x3<ax3L`YaA{$G!LM>B6EIQB,wO`%Z]J=wv;; 0XRK:#CT0mhftt3FA-6iTv!jG',
            // 1
            '2;3158071;3618099;12,0,0,0,2,0;?,;ZksSTW0)2,L&xw>k)b9B-MZp)!/X#~W3|h|_n:[fOQ2UaW F~t6qS5)#KP]+Xf_u+bESI~)(u%R$,5X(jD1dX<n|ir{K-52[9IqPo^ixd=O=+C[,|t!OM7{5oz1zNOZ+RKF8[@o,*JO8OiR y[U=.xd2GYeU<i,atd|n@zNzt5$m)3 B42-iw]Q<^z3{v$)t6ub;fI&`w%na*B}1<s+;C1(eS(!mg%;$@>fXrh^>EgV]x85acVdd-MV5LmGf`t)S%K%v[fzlvGT7m)}Te$asvw4gL}EI`]gs2>%)ifJ(BQxTEgXI]Yn}7/:wpDvvw@wr6>&&6s,@P,==uR?*CB_w/(90&i/:H2JEl!ipstW#x:QePvyhw+HPy-V1:aOWsWgcjc^X^`Ymn]cnS9:4Q[bFBg:B8yIisc&**6%H;HM<gKd*zC_MQ+EpwX~w}mFb0mDONX[g1hkT:!8 oP[X}kMgBj31K-QT5N8,)U5`t@jy6M;vZziypf+K<7]lw_S|,6)T]|&FY$n /%Jo`8dhBiCcGfKwpaXfa87,S_-a7;<Z@wY)Jg,Ti^wuY(fy1|D;V,OGpm@4!]l oi:&coeF(=lp7|gwJpN(W]bywZxK)2Unu1@**+iwL5?_Wue@ZhdFuz:F`(L(Z3Jkm.N4;Ky@Rcb:A.QyFSlS5K:fb;;i2pf_FkovvDJ]B*!t*NIfCjnD9EO-D3x=kP$iFha7XUMrd^P3P*{^+H^HEYX7^X(a7+A~2g8<NdD|/abkKDAu63KZhxsgu>>G:xjjyF ~hpf-:!YL#1 bNov:@PM^Lt>F!tZTCwO# REl+^,U=AZ )=z{}dC-0QrA.B^)*rS4+,H0|O+WVT3? nh%KdRSQD{.AS)9WO^I{uAOf>,FKX_!u2AkQmtO D1K^;?S`N3Y7[2L2ps9JJ5G77`ikxIdTQMUWCq27l];~#p*l]#8G &.A~_@P_3?9&&%xy;(3IQl~ya!-semaZ|f0a7?vFz/$2ue<ImYOOD{27`_o:x4lP`+>)1Wy8q7$dN!yG7OWwSF!EH?0hEK!Li%kfuD`5x6B^n{&ho;~NWqt-$-N~VCB1b-%TDm;sQ]{PhFp}X.l2,I*?H<Nh{&mK@J//Y[FXx?wu=)BTCW;bM&%163,7|D[!!=q`GE&;}&<r_zK|[8WycumkgdtZsR8{ryHmA.^x1YWrBs?9w^+4R,(/eNBw5F5Z?sJOP3Uck$O-M8g>b1AgvY|,Y&(ZNN;jjIl&3U]WsvLI:&%pH`z%woq0T(iwPRXAScSPxdNqpBqo2)O4~8M`1=EfM;!m-lfUS751]H.Ym?jp7.aP$T!+upJWJ~YyZm]w]K8Z %h#?d2-hqu2]GrI:OEmY:8^tc%s}UFvC(W1N5#ityaZ5zB^^]2TQ]9}cCmOuAN!LX{*M6v:^hFC=!9gFd{!n]s(,CY^gQi0Yjk?k_EW:A!01j>hx*a:. BFgu3<SED*xfb%2p^l[W8>dM!i)#j.@7Lbz=d~942tB8!>z3wx57)}-oXBRuR7zJNgCV~9Y[cXP>eB >x,3LRq[V@7?Y7g;}ctOgQR.zr!U|I3Uzb)>cawmx{Kx y^~}UuHyMgmE9Ip`dl``C)|Qe~`?hl/aUFeb<_AbChJRtmS/PG,wQT!Nh/2=+9M+kh+}|%%^]fLKztk>qr^vbWE+!ze>;5U=;#StN7_q3>5Q|qwG !s4D}=o8SxD*:c>IFq6kF<j].vI`Ka7fn=M`(L/bg/YNI7n*0R}ng6rnk-M65;$,lz?Z2_:g=pO+6sfz/jFA{sVJ3}K)6NJ~-1j%j3|Z3Rz&N*u%v{39 7olLLqZ#+gPP1>xh4(lGlJno_Ik}]D5t oz*YxwvT?1J`IeQJ0W-_4x3wpAY9x{m)lrfZ@2QuidL3(XQ,}OJ-X{8<4ewwcPPQ[ca,B7c/N&LaLi PVEA$f#n<K/H;s){?ES[XGBk+:I_-mHQ5;)*|K#APT&zDBMO/j;)AA*O37F7:1,oneW]A<^:[J7]RMclL7]_{r,I0,}w(:y8 >D(~yD~+IuZ{MU+)O(%GWH~j90mD:$KSBHM@D!~m&CQ?IFl-I<Oaa XFQwQ9I%37VUopjC_DMJ/TCiwUvT$u(I!l7P1v~tE@Kg0y*X|uoxdu(khGgCY)IO@;[npRZdv.jRxy!{Z%TD:k]&T4Y185%WQZjB/Q.{NgRDmn(p`7_?^[)=+M8)C#[PQp SHoZG!Bo(cfvR:iLba(Q!U||D}idv{4QP+PQ{Lf@b/6iPUFqoo@WV{_5(aIe28>v_WRy+jBa-`xCA6{QEl)eU|(tL,kF0i&[GiGXCR9r;(Yivp`OUb5]$3f`a>@+0PUIO<Dk5td%S=gI6N4|:JsMEzdt[WK4c{.WkU)a?eG V?f?G>W$_t`%,7xvlhP3+7?BMF^5_T-<U$w3a wb-@0d_O`WaPQ=74]SM/+s84neS;aRf7Gz{m>,3,?=M}QstDjlg4e eZKZ|y.CGx2%P3gIdvvt !D|m5ohu',
            // 2
            '2;4273207;3748409;10,0,0,0,2,0;^I*%[sNtJ^&k[U,?6ZE@py-V<jZst%c(XNfweqw.`gJhPzj[*Xxa;3rwD0X3F83m2JUlLmS e.+YbZbSHt$$m!gaZfqG^nULNd|`6kHse+ef$gUt%f?*uxDc>BjLq-rfy(g1lJAvP Zh-uezKFjOMX4S,BpUf6,y4K|dh+oH=oLD$qm<0D -5)m+zygFd+&! _4keP?%xW/T%Au.i9B>+>ASM!%c4-xvNjN+!br W!t^gZR(QM<&~<izj!u<1Xdo8]F/fy-*x,Eib#)q&FkTPty*97M8T*-{Z9!Hkcg_HVGJmz@c?.%8=g!|HWTz:gwytB-{yu,6*%yL,;H6gqc3*RHz{o`cXWNT>Vy]ne}#u# fbQ]P14H1w;d7%kawWQduR;AvJASd){yYGW4rRYb;K|?%RgY#,Qm7G[_9X]GSif@_?`))o$~PnV|Jq@NmQ>s[4ax}DW~uFjaC<T,As/7v9{4{K~+P>Crn%j-3Wf7Ft~8yvJvse6(%F+e,vAf-e}N*l$6@X~[O8;odW0^[U%<%Z3kP~k)]Al8_[l-=n(QyIY,~ W><>uf|@>l<}X Q~ p!PDl#h{e}ZZGGH!i1%NoHP.nZD}r:?J27(y<ldpJz[sCCFt[qxn^uOXCX*?6 1Cs/:N*=}N7`Ffb(B$~&`E{uPYDD$V%8G]`<dgL]M#~RBW<:zW>S{JG;#&syxoD!LiYNk|N(O*#-v8x9TO=X~hF?=H;QH;!loVICa)o{;pp=Lw^t</0^~_SIik^gm4dIp;B6Y)hO; |rfK~Jfw^QyeJR[px|$4^!CQ^NXwU3IP8stc>O,)0^cK631Yucq&rf<|1@Z2,.d[qwyad4pkzH%4U;8ms>:^EYUfu{O#,pzc$s*nl>t*;z^__ fN]$7TCh?!)HDXPKH_hbrkeD(CC:l,FLU @T-<?V:,=5>nze3-@E`cX8w:PF7AGm hAXf.-Y0zCg?A6,Ic< ;4C%o*Y?,AQ)(y:)&z 4yr77P+?I^tD}reweOtF86r^/Y]d9oEXa_B3XA4KIxNVX$1@65di2 FM/b<uJ!Xm2*.i@108Jp0`tBZ^kk>nki.[5nNsz8%>dXZ<rog(0a1a7Hf&nsZ*]MvT)fm[1YFCt`xxF[Ot~A7j5fHQTOvk9@u&6ewE7p8];B?B :%*0g5zFp^_-N)wxs9TZ:b|i*KMN:}iWS-{|u@:yFMOqV`Sr%19o=Z2Tv,0Ic]efN_ncC5f*1|Dzl^X-/dt@fq~_TF`WlXLT|YW=[EFTf)uP5*ZF;ZG0E71.A-:.GQn|I(LqDaD=8F8e~]X#}HK/wIE<[%:@.j3*zYE~j-Q9N$h(Ge=)<zp!J@MWF|a.fE@6$qbhaDm;*m#)yyl3Tu%Y(+93R,EN~#F%Gg>Q*Za=]zt9Fk$MQ=~i_h@@6bCq#~++o!s2si.uOd|h8-$>GcT_ Pgs,s#rfphSIvu<xPfrs2_<kok..*Q4c~AB9)cPW9zZHv`}k<A`Yu-s%djVsyC<n)DYSv /=(Gb7UmrBMy~uXMHb)Z7:uw#PXy@n0QmTI4OD`=[(K9a/ed~^9h5Ufu7]LhEo?Ryem,c`8JUo@_i8@X.Ei7426xCb<R94ZoKrd>q/]}J;I^mEVl;]+fY[/J~E9r.3l/FjS2Wn(}.dw[mEJjufd(//9Ho9{bV-q.YldIy 4%a0^B>?Koi~]EGW#P^wVCez^1OsvT5CIJ5j.!x#3lu!_DKgHA`+,|{[: pir]=Td1D2_=iYwIZiT7Z^FuqX`CA&(;KNcQH=x4ZF}p82I}/Y@,BzO|ybfZZ91Yx+B65Hc8gN0+aB Pu1#i;DN!{ctyTrjIE@(oj=&6`Y$Gxi/x~iONlALljie))%$oZ}PPrs/ ON-/pzi;E|p)Is^X^bqs$?yoL#LDn@bO|%3UAM%|<vg=``xqtpOzVY$=;DJ&0a_-h=_`Nk$uBil|d{31h{8Lft*njrCcb=MO5@4A5Q9+UEA^>t%l23vwEAgfcD3}V2kb[eoyz|qz.3B^>$1&BXyMz!FEOq$BV/uBmr%DDTKC2w,J;v#5YsI^:Ulx)sb#D/Euf+!f^wjndFm&)f&5WjQp[pGG_G;e!vXzk27Xo)41&|JibO]tLdKv`!^e-lh*fFQ_]#5&s7DwP5c@i1Hib`;s` Ut+574*{w+?J*Pk5Ei_W&ZLK$0!h4WxY.RI=9rU#H[bw:s2(1Yb<4_kp:`;ijA2e4$=>>2+e]3I#?$<r.rOLA7-+])MJ5zs~=O-9X*pjzG`8pGrOk9CR/r 8g,~__@Dzy<q&3{jx%XlYotCu7xA:kg{nj87i*[bA7nPb~fNqC_m^8z])t<JhSIjve08|fmz[g|z9Zq:LfJZd.;Ye%fo3+(IfJ)%%$x #;WAd5c},N)n]0.{.>v8`5Qk4.Kjclz!yvqZ|D7Kk%nLa,!_]50<nmz7dPm*L-1LrNjW*Yia.VM,%3j2!=#AKwU$* tHvm;g2wi^hmy&PD4~#JN@;(j<ISWap=-Hqul?d[NMwvo#T)qyymkuuoa2i~qy?@Bee6DvyAyPxSg~ek1w(nF6K. L hvRLtKfI 7hvfjn8@NnSV5tAaTRik;E ?RR9ACgxSvU^)-<+Y8ICw/#<5cT3$(bSst$f17l#[tRdQSfA=j)Xj',
            // 3
            '2;3422277;4535622;11,0,0,0,2,0;N_/}Ryq|1J]:{u7i32C [G! <J4]Nmbc*=w,q6`~>r|&TdnI$JwHW]~U3qnm&1;67~f$L;AYpy1Zq)&f ku|ffK9d5C}NS,:Z}ylf1:xW(l~x4qtRn!zYp*%Q#(R:k4K6cB ]7|/mBTP4I>dgU1}gm%u&5Og`U+wLsWv(~VsZvGweg?,[6[sUW@L{ghGC-]oQ%qE<~hB<M_MEv2zjdR`b.wkU@_#&v?Stz-^I3,Pyg>&%QshXN-n|~kwhbGcPJyM,d_J AMnL!LHUvYaF?;QdR*&-q%yuA1`a(TrO,=xk*P|x5aX:Rg%iGei^04sRM{_N0}VoO,pV0Ev$[4vr*i&*4^*qsK(GQ3l!MYUX^ziO0}&-lI.b.&CGrtichY:w`?y{o{$KL<54sahcu__uEUm2t#?!QU$5gZ9&Y>@N,RkA{D8|sJr0EM2M{OiK)|ZfTB~13fXdv:GR^%AbNTe4JRE|TPkIoW7d)}pvSHQq/POh^p|=89oi1/Cl<sR+#iI@$kOCvQYum^}&jg6Be_~=!J/[EsGo9>$abpU!2wwz0Um:3|T&-7VsT${LKu~p8BF}rjLQ/x+V!htz&]a@J!|K,AQ.!aIhAMX5azrA[2Q3^2Rsu4!YRr9)Z.Wk}E),28@0ow7Z9Zzi.e;_^WdY`mIvtC?*n|*9t/R=o+-j3k>)`?h3ElR6;cC+mX<OR6mlijvfa~)W:|uOqg::!0-}A^:Uycb=tpX3rphnKfrf7E]Kg!o9CAbbN=UhnWsd|l8Pm7+#$?bIqiZP/%ep4+|QHuf!SMm7QoyYU*HKD!r-e`Al4#_!B:!J,I-&D@@Xk,!YZCwasAjGjyP3xm_M<TC`64dEeb)6Nam(f)s##Z}S_mOhf+N1sVb[ApXp@=MdAITXQ e!1oB9&}^4EJ*UeB=jBx//5NzGij,BGpWG-dBF@oBPb$mVD8KE/Q-DNd6a1)`g1U4Xk5{wvWUnpj)OvWu;!lzzLfDW=N]/X5vucM#i6kcsaMFLt#]x^B:rLfsj_h/Tc}Ob@17-{MSN5ORAOMiC3AN]2XB{@lNet){BzSL_k0<h2Yi+h`6E]UfPwKDcN6%z4Z$[av6CY :-DTb*wJyMES#xVe`Iy`?T?dEw$j}J;Wob+-&Pq=8L55?6jx;Hol,[h,9CA!_PFIXf8 ^M!~zOBC`4H1xIB*Sj{6m;rb!yPKLQ3K?I@@](5MPja0Kf79.l73pG8$M.wo4*1/IrGtMpPaGyWg>;=UT/IyuP{[PQW(HpV{k0r~jE}vo>2vX+D{J|76TPy<O3iBzQ@O{CdGv4P| @~Z>-WEAf=7<CR]l#0Ib[?| fV~;RV+/{U^./dmx jwI:=rE.,}kJw?+A8SB&@DTfMQs_a/]GpGe(JfBQWXgBs9Vk=TmI?QoILw<n5jDRTi$fwM4&/zN}OX=.p1kGp~}Fx[II4y{Q(+mxeL;nXtF.l01lG/)wIW`]^#p _FTh1[Wj[C.p)51QSAKcKf&{bh}l|@we6U^YM*sG.dhqo9Z=hEn;S}ZA?o?Wk}PSAVk%<vzDQ&8Z8E9#48CfJ%-hYSYig@gBN_7B%V5dD,`LsNRf.SRP&8|.~aeOx<wM7^!IslFYOE,LBwEpa>G|L@|QG3;wYn>:7L#m_{[%^_Rop!? 0i<EC&[2L{OjQqg<6m5h4I*jMbIhrAkYp3Owfp>q_5Y|kD/]lx!HH6N:8JMs{bCi8e)?IK-o.G+ddN:))dZ>iV20WalI_%Z;yXkA>T4%?Ko0kQZXf?7d5J83h2`#FhdXrfDs[!xjk+d;-uyfuYs1Vu4,q~G%j5]Z<(3k#55v?]}|w`Hd,bQ;-MT,F{sFUsfDszAI>JWN2:rISN[G2OwA!~jrV}D|gST6`@5z67d[~O;C4u59qLC/)p&nsRr;15q_H=ZkSiTiB1]6EBbD}.,tJC7*qIrf?I2/^*lIURvi2hX0c{2)&^kP23apRK2zO`[jB%v]-p>F,f]Pr-TBM{V13l_qk#}6]^=zumdzJh%<qal^l-M5::/VbAvmR5a?bhc$:>v}R;`)k2G?mzT]pP3|vt`;0i%MZd`s#TC5qYuB o57LjTs/PyRo5x.0I*1`PMhV<KNyz9QH5S`.K.O-Oz,w4)GUgKRl;FT?,PC- Jn=Z14Z,mU{.g$|@:Xq]E,.NcFL:)4cxTsnp2I&T8[!#to,8Dw+$+CET8%#ISWCJqf4Fz<{gKCY5o/Iz|W5+-J_5TzmFd)5)0nn AoK{x_n$Y)W-m:*3XVhuaIZLiD)2hFVj4<KG&_f6PN|3Z0fm|Ha=jUxb$nNNy/PI~ST-}qgq;_Cd{PDOP`Q3cV-36P_Tmpr{p:Y{jnprPs9{/ni!,n9}WD}c&f#4!-KC<t2Z_$[E@Oj};t1bb9HbL/^`0,~9<Faq0C:%w:E-6zi%HN[x{_1)oaWD9av gf<Pj`qilp*D|bX-|UBK3&T%:B|~Ubp+3*d$Zn}i(<NraFG!N5Dm=07l',
            // 4
            '2;3223604;3356212;13,0,0,0,2,0;%M}$wVmSfmD|eQ>$J$O|J|khCj,-JNW>:p:Gxm<fvy[cLZ>_g1(FtON2@Rp~AuLDB|U>aqO/!9D#LK/&^2EHjJp[J-KV~2ED:U:{zb7$&)r/!;2&M0B_<%&upZ}*ei.pr|^Q)m?>8B0?kqne>)tR{[=)WeGX)Nhox_Mm/eI]Jahak@=w^t,L>%4kiZ)`am<CPE._~bK,Nmq7F_dk[G=,]M62fdf*hk?xwqm7cN=JCp#@@I1.yf3_WUq#c+fkuwq2?/Ai;*p;Ajx}W(;P?*Z2(6ZmPDT0v?&E]8;gaSZizYc15[@LOLxX-NSL#T+Zr/y;emBA]&)@V#K/Jw2/1`,IT%GsT-zXP}l;^C{d8:Fe+T$i-mN>zX2q&5a1QR&<1^J(VdV=@N0[>rV=ZE+*->Wq(4n4uU{V]:+_DPi[?Og6P|HX|r=`q`hHV@n.)b.yr@A=q_T=&z`0UO!cFjMk}pLRf$`IG9506j_k=<a(h4J,^C!yV>tRq2)g}gO+1/<)yC=u`0SJ~y7nH=v#N`4g-TNKu97y|z0.I:E.SDuUWEZ4c(jF4[,P<hcL|LD2aRtft}8`g3bgk:f_194Sv*fpmJY_+c+Xqm1<YSn_kP_lzZ<(j+*/(,z^m|)+k7~uSU*1ScPhW(2BLl7dB[U[XkJ+l7|0c(OXnOMc|h?0?E|7V;gp7eo)%9d%cG[wWIIW$+cxHi.)u+n!ybskfwvGtPYT9)pRJ1AOt=q2bzDVlU9JetT[Uo{BUsr%:Ou@1#79Qlq0n(do)s?<eHsWOh}GY(N1LtEX/Vm W|Z,l8i[;A+R3&rc:bi4Jx->,%Z#1PD5lf?x^,p}(*Kl{I?Zf:I*wdy31%hFDCE(Dv*W $[vNIU+gdesoj3b}Qg;%(iIERwL0]`7wu)Wqy-^gzs;G!uc.j*[Yg?N?pqEueh~*_oc@ygmfjOEpvpLhhf4/9qmpkhBH?1}vWSsN*?:w%kJmL*D:lxJnn o8B*[Z/8&4 oK&;UC<Od:mE( KCe;XhW^y$sQB:u&2! j@j)uz8H6^:w{UIx(o6S*A~Yn5}Sj~3vp;/NPJm#PQ/VO1gT:Sdy/Fj:WO$3 P9Ats<`e<~U7djUKD:na)WVO|!}99>ZRz )s6+NIuBsUA>$6RxiXzT%Y,kcbhi8%RSe]S5gq(&|X2bViPXK7cyN[0T<A]#1/N=H:={^tOL+}fz| Q4 eP$8Mf_Kz)1KRQSqt`4IMJ&,(G.#7@G:@x^F7x[<j66v2`|98JnY5L=x~w#(KGhgcD*OpQ@Qa$=K.r M[;Xvg%k,b^z#MWxqx^Kz6}q-?r(m&e+_m(nf/W?>CEcn:Oyv}xm3X$>_S~lIyZ(`q#$|M{i]i{JP{G^k`Ip4@va|G$I+9?A{j$ ,[,+I8;ik5}F)R>9%qs_s_=]7d*N-[x!/[5;3_kF^(R|B`Uj>ftzJua[t8.1k>ies(*:@VxUsZ~Yw92;!jrvP~Wv?yf u[^eYv 14[Nf/c?w=dou1Rgk|}j=:-0la2cs=zWWdWoUoK|k@8p%pg3A>59y*n6vzM#?bV6YP];)TT*:m=WlXOd-jGC`;*8z$9Ffuyk&GsG6zpF4nP^,Fn3V9! lQ 6{|hPn@()HdV-k<76(_Ed:| %$hAVQ]dsdxNKk>H;]YsW?rmxrV:`.a+:2,ctw)?KDbL9ip:Ew9:Ji^aON%}Er#4Z45!/kF/VUw. OnnHwK= ~v/spk[1nwr,35}6Ggt<T_QAw!x=m;=#5-;AD5(b9xR<%.yA}q(ac(!,lRazX;6$?/JR)I2Xku<%V?q<IV8hG9vN;x=U8#H{U[NGheiC1LcODR[Qoq33vDT2UAdQBFXPs ;I_i9`Id(Fo#$-SSOWw=$njPPL/s)1I],*=2oURg=51A11L3Z>gAIhB33NR4N]{6YjJCUULxMC6fwXu]+a~ 21]d~]bTfI;a7Dt~H/|nEOGdmB<)bOugS9GS%Z9I|+[|[nb6WVdM49@d2rBEslG5HO.<g USv>eAsOp$YKnT+}Dq8&XQD,bHuSViM7JJ)zk2cq1M4Z/[+^K 4#oFS:/tC|e<||fiV3%s/2/ W3{+(Z_H^AA#TS1g|]VsHXSaca)W4LE|vcIP|9?{^A,aa)S:InbvF]Fp*Y^@=D/:D[96vC7m]?&FB5y%-We^u,iDd}dYH^iUW!,l@O9F+Jwmw)Tb{1CN3VMC*@^zDRe;fh&c]l6(By]EKTn3/<qH,SyBhuKKI/#5y<VVYJ+P)(5StaZ+*E,eamhsS~{0|plT0E5/R3%tX<iZf*$TKxHCm)U;}00z(a+Nk9D{E K^@}70lH!7*k1PL?Z<|`6R?$[.l<(=GAENj[%qI]+3Re`_X G{GCB:~mubx4| }Z;ukJN+t9GaU#PIwv,C-Wd7a?oI(w0;Gp.TCH@WD|3V FSI!$A8>H=P3E6.%}hf`vo8d& xG^ FU{xQ?,$ju?d&F)y~]`2)sy<V[hGXF`0T;CcT91H)kwP;_MUYqOX0Wfg F#X.^e2u;ZcY5ymVrLo2n)Y}42]Lv0dw{3cM#&T8^j*([cYk-B~tp>|+SO]|c9;Qu!R&<0',
            // 5
            '2;4536368;3687224;18,0,0,1,1,0;k=mc_L#3$&hU1Uhz#x~h37,Y%Ql72[CI2anYFzsS:C>[:9JZ<vzy7@[k<EZ[aoq&9&KM>nJYQf.O03U9mY5]z,TxBPmGX0 eOb0jY:?+b@=:![>/}&Hsk!SpFe)1iJk.}6im;.`JSqhBSM5Q>WlwCd_k3T}+9.)krU8 vpm6G%ikdO1A+!6h%2kAQxyu>/4K]l]lv1Ai$EpqMBg9?-Ie;h?U/R?b.%M^ zqX,]!|K#,PGAi3IF]!Owr7~^3JxvoQ%qthHsNjXOaFeWPb2el*l= ]VXs,!9rz:nD}alq?bDTS4kFv{WT}b:-:=GzKOb6k5@?3Lu*h,5Coy,mre]<lY7+wiA@hlU]C~<TL0vC510a!=B1CcDvSHH-lm&}yL@[BepyXti%{K5b8JdG4cHN uku]jyv$a$pd 8KU}:hqIdN1vC(#u+lYVfR5cigIC MCgEqJCrDJJ@M%Js7Ls8(hvczJZ}vh+EO7@3He;%{i_O!_.`S^)L1./,AATRZI/e08.b0HHJ75}}PMtj;Hv|54E]UWYFW[K=n#!LfC%eGB<9$mEEV.Y4?`4Lh<?+P;Eo]c99neQ#$gM^H^iDMlB~|k):_j<Aea_<u+BzRQ?IAaNWKJ(bt>MPTS~/Wo=JnGc7|5xI$ygx7{-:8$jUy4qal*#E}_$Qm=(fYm.x[WUg}BvH^`?17Z]l(]!DqHs68D|ff+dAu#|?Qk*BlE:QwoYXfxHqIsXJDT{d^{JBh8>i#;M{ -yfBjV[}7f0xxxF%1PORHxI*}7%5a|HLpQ5k!79y4wC&bJA`n2^_g:9<(T+J[7iq;58mDUIv(sReu^3s7inrU&.}QR,J@a&BhXcF{7-Y0yU:*p,}Ps[K2u w5bOU`WPbe39$Yl<WtQx7J??{VO%ranl8P1L81~NtWjuA*Pi%M,*sE$&VF!OL@h+AF%y_zR#NAG,l%A4^NDRrF_PmJQ9w&0>0/2v3X7xMdBYSFs,`jZ%WX{@eJ7{2HyfRRwwIxs[VmI`cA|@zi}k]*4-%S8gC>_L)Cft|K>BEQi_]4Hn=puh]$tp?8~6PsQyxEInu:!#+l&0i#C!Kb)i{%WMI;]wD3rH`/(!M$%ugl`e2zz_~Men{-pW-?G,nH@-YNXp[4lA[K%h:Fg`_G-(WI5_sh]2X]TPiLT<SMLtosbv<B{t]#)u-*0cB` TK[#JO%94DH].C^[w;8W+UWL}i=G#zVg_F3Viy{~$$3|OZ?u8aRi#LAQO+J=:jgt@~1Z7Z`6-+O1vz;hJW:=|w18UOmJIUNMjVz3MQxxH^SS.JT]:l4^4%dEhRo}@g#N,LJjFFjW4Hm6[c(z5Jecr4y$PGKWyb(:NEBMBDgA<Z5yh$Oo1sJSRz7d,r}j-z9`kz$~###L-Lbv/8PRR<x_)Qyk8(lb:{I4UW[`Wc$j!2wL+Ie7C:HBJ%TmJ}XZ3+B/@g2d*12s_~*5j1:A+eJiCP{ueIHAyxI~;H3&-J~8 P;rc{W^{oLr28@5n|s%bY7}SQ?0x(<WPZBI3[6YgZSn=$YV@D>N?BwOT~9Gg`I=7I5~ngt7TCBe9zY`Pa[bfjoAU{zx8]CjrRL>.`Mn0,&YwkUaMgn-t&H75Re4WT,nH1Z96{b&`[,7WVPuSoj//j_-S*dom2,46SOJO+{,[[?l0B0l7nxk1E8CeRa7.cVPALRgU4}f.>S=;j[GUKWw&JC;,`@3`>aS) D1i:(fm)f=&+&M]+r@%3ANMxv;}]GJO[ot3I%KC~=]3GLr`M|u2!+:wh1JIwmky,CMZ^hm6Il&n[[2Kt?l5Qp(7ixB+m$p7G4t&/H&k2iMa^]>W/V<M*9LE<k* vN1EMr-U6dT}II0-IU4TtI&N4c/xIUgwfIzJR24fz,w|Htsx?M+dRJV&d|AKRwKE=Qp3T%=w{=?<eK&~{.xPc{S4i7[`n@vr|EG$@|At+QNr:i{{myI*#VS/9=cs~-5MD.1Uze*`(KIRBv6BC4)n+D5xh0]e)eJWh.Q%_Cq i@EZ?(I,%UG3.;tSC$_9+j_=NVfRaLG$X%[U,%?{8gdK~IB5(U9ZocTt.2{l@?jf#$$)hFDK7{rSlXkPS=P+Zxpbo]/tXP)P$5D_`c#>,lscLH+rz`tr4#0OhUUSaQygsWj.9;:~zXc]pLW).we1Y`/^S/t|,YOGEr*-E3gmN9OIna+#3{rL&d!`UNY[@O7H xLNi|V/&t1)Ja$D=&#,fID)ME5=c3)2xo2XbsC:d!+<i/V}aba?uNc;!la[Yscf2/kJlrwcily2bsH&HqtSw*jO-7R.5ozWHQ+E|>}fwv?P#JpKPJ[{dXhT,7)KUtz.(,gQy8|`k?2Nr3vx!0/*e-zn[it25f]Y4k}q~GPiCT?)f`xVFL0PT@K&m6g7?ef7<5<<vN>7VkGU;-uAk$!}#GnpXp$A[-ve|-E!:ksspD4bsdT[4}@;FF6C;WY+C!>fvB7jW>',
            // 6
            '2;3752262;3160374;10,0,0,0,2,0;UDO+t3~:_bOXb+o*!.U;NrWFFTr|S{HV{/5yd|@~h^]so/BDRQ-w/2X$5Hp6o+XgxReA=Tx]0hYsu>XbkeSIj/#k0?d(^2k+_d%kf-W@68FfvBLou&xd4ZVDh#PuX$k,a[J+|20vjv~Yj6Mo}5)-Q&~iIW3NA}a0b#IG8tEG.Pg-Dyf=Bho*5qxq; b.bA!m#Pzu!T-=BJe<#&Q#[yM[jB@Fy;!SL5#OBcndP)Sfn3AW5aR$=ICIg@I|[1J{jHL6yBv%6xPkwAb7]Ora#ASQA}FS(SF/ ]iYOmR]}_NVFQh<Xy.t1MXyR`>yN),0$5[NL$O;M@>M`8]Kz[oa1l*FNGh#dKH#9*5,!U@f$vc+<!ICooQ9I]c|um?bF`cUc..!7gVs U3Xrb.&`cH>T8MJ<+a)xYed}z y4t_&7kT$M1i]TK2*lVvAMH#<4hI6NXkr#e>CedMJZQi=z(xq^Wf~(/9QYD``d6EBcUX}bmp?x_]_SMUR@2Rk|#iB_QK,@K!-C=4p[^k@(xgsp{ &vh7~1C!ckZ*?ft6GojrM6Ui>vxqtt33P68p^f-oP]Z^NkvOe58?|S:@*wja}-HB[Sa>$IU/HDCr7/g*eZMaQ4d~dG6irr4MkikM?r2}s~5`~i!A.V^yfc#CD/;5ai9Bbp%g_&VM7VkDjNqOuP]#pt<feTbp<Gs-fgvlh,jed{*sC~P/jA[+s[cyrr(@]&Sy>Fucj@mgm,Y7kM)]Q)@tu~X$+&=3AU#JK7QYV:kn{o8~]x&F{<YS^:Mb`j+HHB(mJ=68A RjpHL341HpB//?zYCz)b` +T:(j1TZi0GQ_{rTAr8{dc&jw/nsAS*TS,rJ3O~,F0y+F#RQGK.0$z<!D^K4.8sbEuhV/a2HbNApy^?C5;-#ooH?Pxzm~HxIAtvD>t=r0oaCI:v{xx+()seDt $&<(]*7e]PgpD%ZS>i.lcTU0>89]`+C cW/W0bZBb+p 3&/;DgO:09SdCc7n$a eR~Bu=M~axaze+}Cm1hBl}cS%^5F,Y `lqt0cT0dShKPZ/=}8Kw3N6gaD.bX3u(t3,BeOlD_TN%^M4qy;uMP7]6CGdqZYB?~pxF?wp]@h~VmyC?/*-3^p))(z&{?Y&!g)<om9<n9Pl$-3V^_{nkq-N$E):H/M!}hm3rXFn#v6JgH_^^Y{RKxJ6u3r[OQYc*YR^:PU5~PQEX,o9k]+133E3_ufU}FVI=^H@1g_h.eJBC|M)&uE|c#1/iG+rXy]z.kZ!!124[>&9y~uxMPn$rTL3?p)7K %Se`{^22ved!03E @GE7H8KzF-o/kLAUF5B#G-Q$K+:<b^*v7s1fZoc>6AK+l@:w+qohw%?l*&~JA&ql7*#C{U?!qy=l]8@K/_~b4Z_+i)%dps=oB~|@#h=J!UD.5&RwdY}oT@Y)7(<HEp<-R*3ezEWE>UuG{Ll HuEh5yJV6c4q8`-kQ%@b-Xh,%^bURQkUN5$Lq?n 6wYR/,ch8u=hJVuABz m, <.D&|?faw+X6ys`Q7H|e(C&cyMFrC8P+~p|cTh+lh8]K+4!vaA^8`qHCrCC0NAcn1:H<2_GNX~UR43%7)*sf0bhn0=4*KPs,?Qo]g;u.HsO=lH0D8E71]AJ9Wu(/9Hl%Lp/~*eNjrM&rF^c8?DH;{_s.yM`k*?e+Uh%5D n%L/_[nop5;EMP/h;N6 oMhq9#BW;6I*cQdL.BBRO{(GHQ:y}7.}6g:`5]3(4&A85N|YJX;80Sc&&D#|KubLr#Q.c2z4j%;80Q{qhr!AA<BdlZ~g6%AE^|Mh%S2g,U4DJ}[VEmhem_H|y>@mW(JyL7KB5l/+ue`VGEJGKW]>`$aDm1)7g:ODk]4vg@kI6W(.:Y[QY&2n=qCt$ISPr# /p%zFN1yPIuLbpM#=6k!o.t=|xwinEU`eLpc7x6yY-)jm}v`cvF@^~ek0<dr0(b+5rw,WfzrZV)a.B2o;;qA0eM.mr#{3`25J:niLaPdLDyefV5!,i?kX8 fI+h|?(1OpV.4$=0<#.r^$? @^j]({ah93VOc-0u1q_UNBSRh_^(R`3tAVwAK8vqc[<%Oqwu7j^N]Wml1/2{xIy(%Kqo>G/1/}bK$GTN0bV@]Y%!9B[{)s{xc=nTF>*:4v1mB~2/m:N5LYz',
            // 7
            '2;4470839;3552053;9,0,0,1,1,0;Sk$A3wm0q(ZZtTZAu:q`?-Wf>+f`f)S9SxwA)`9jQ#XSplga$[MD@>#2si@z4S>-UY.]@|7I3s5#dLd@[Xz6Q<%3{kG|hqJF[ZDvb(4 e:Z>^{g)k:zklpmnzwe?p-&~ESK}dJE*$[4Suuw=gY:C<VV_cdb8Y212]3[X7.-ofB*U}y+H}dVwj#!E8P7?V69>bqF.A@VC:0n?mxme!+{&%FPin)*sN*11#:,w{fvdd]SsLRrJ~}r+@Xc,,OJ^0il+a0/mv+)%3.pH&:N0o%m+Cq6IR|t eAPhI.-f0{E7O=gQ/M-vJZY=1i+]`3:: s4D#piZwx;9^BY/xDl>3?Np/_-Rj0GQD+eqe@O?mp05bS^C?M*ddx~EvS)@f1RZOOv${gs~>Iy{QHcTHZ,(<5I|7xGCZ5@jPF~FD!TIr$W[07IBGg;,%zIvD1|e3s2hERrHX8Ob:OE4o~.!$b8B6W|Y+pLEq`l8>^sEFBtwVqgbPRmt!A.*:_)^L6mg>nGs6tg@C>!x[+ow0G~FO{xp+S-_JKAu]B#;@`G4!<Rzi$b8r0f^$[UL|9tbN4=zf^0T~#vVx|Hfi{uG+=}$4xu.;}MS06q+[n:#9X1&@W(j/p_/ R#8LH*GlL*+r5fqYZ@~?aSQg*g4q]J kM ;]S<i1yRM#5ygII>%BhrM)>@HA~:ZZ:$$ZMRtL=7sm(*C.Bym8fXu+Z9 _RP@,nXvI?h_.oisiOX``^RzU&5<p:-a)Y7IH~lIQen[fb(e7cea(Mf<[(_/e4yYdPNUrfc6mh&e$&Abo+=C@r[d7$*;zD5!60D:E0&U:OS=wq1=Ns,PbustkDcyT!Xc^_>7DN3uYy%jRUes8byLh $4)a)6xSiaol84JHK s1F5z6PW/z>#=x5DI{:Z@+86Kbs&HHA2uOWJ;^c/P7M=H&%C.=BteOP`q3W#{|;z|=fdQstzN(ez)KJ8/u=_HiopAU)6`sxzm;ajXz@v8|M?lH[,$q^qvg!GmzU[:SZfe5cec:@*WATN+]W^_su9P-ylpBg`O)5K1vfYX1X19(5;T>P<z.XqV`/u(w0,6V[hXMG~d:?N2k?]=cyR(sy`=n: W=nnrUIRQ5nl-@orvqVP|-P+FWSa8(IPv5IRUPr/cH{.;4zT@+nH-u)NLNp4gl$_15r !P|^r;_p/LMnTjp9*[j8*GD`dvVd)TsL.6xTA0c&q6KU-L=f!Vz)Gwl3,m-^B$1>`UG?-@H}YEugNw_>outl98R$8cqq@yA~)a%B0j=D)CJrL-;(AEi=zhw`~DGhB@G.Xb4pnR6Q63|/<BvIa$?{Q0antX#N]3}mY?*YC=V6`_^/._b0&@xED CEPZQogG&7p-[Gj`pzSD)gCzBW>@yjE*R&&|?GaLm.)J2m,l) pz4w|;2~2C>h=|K?)-!_ky~RhMN/#fXI%EY_!Gbm:KV,*ZuMgc9oC3-^cF})2p{lRk:%d1!x#3V1o`o89f(%8P|0aYxK~>A1s/U(a~_2[/EMUJ-49 6;h|;N!.>4;M3)z;S,W6rO2Up5=&Hic]j9!e.T~H]x9|GG83P+iE kAyAQ!<mIaC-Ay8h*ZeI-5sDDL~>(QR0qF2Q--ynLbYx1n+^eGORJOIt(1]zpE@,5GGf>FV@u(-UmZtAg<0M`]P3>K7^I$5n%@DsFWkZSAf(H(hS8N/U^rXH iM}ybcZS6-mITmhThoQ|g-z[Hn7dsjXz~qPY+3)2w[3m_waZs4czc+[,}$3G?^}~:|YbH[!Y~&> Bde`r<rFGwx-z&cWE@D9,yh5:gx&r `Y~OySr;}$I/9MG>2W;$+^-p[bc4zB7G0-[>{,^xjVTS0&!d^(mNN4gdGtd*$RA-.H9xOi: V?EH4;/v`Avx%l9X(DLi,A>j`T5!}1gA+s&x9&)/yQ%AB8k~^}5Yn6NhrXX HN2]{Nkc#u$s~V1HnB%:<Hb8U:0L_rf&R`F~4 =4OXkvgD$IeF(y|_fZ#a45AV9,OfbFa?Ua0MGNNHsP^RO`a2V(N4ExzB4?@v`ZRPj%=`|z&ha&mD@at7eGqAu;-Lpb0cTSKUw2cqIHU>GuZ1/WhLjJzh1i!Q=;f;A,AN!;h/N^}za@q)lBcOci$wG.8rl%6u#wTCi0h1[..08k9GPx8vSKsyL2Dp*,r=MUq}=*o{]m:=f my4Ef[',
            // 8
            '2;4407617;4604473;10,0,0,0,2,0;A8OTO%m0B1r11IrD`AV|x0#^-~0t|x8/T^uf#gPr3$pz1X~J|Rd#x53zf& eqo+7^s2*As}B]ZWl!Hs=~]uTe m`>]GSUS(@Wx3GAL:Y98+LW2eMnT$Y|;9k[./w6@fzLkxPaMN2M^QZoSE)d<tT+<5J$KQ;lrF_7a(=e=`0kjwB_G}qL3hOgD0V*WZKd}K)1qGl7LNGRcj7RhB6~fL2`3B=U8RE$>?jJ}DjC4(t>(+b< aR0vYN8 +?(bu MoP.mA|Kkx7{^&,VA.pp_aA8wKeyVgy!N!YBlOwVx6r[>]eb5/($w~c4m.9YA?^zKyZGqab5a!7zSseKz3w0#{J^-v?%$t2_4cV;7OZ6~3ULP H,mwj#v4!.`VcSG@e1w1@#+ni;%3~kiCqaHIJejur-mc5a_j;4>@U7SlS8+C+%vU.*r{|?Cbco_S-goyC kN,:E76)uBmy4p2:.NHe BPAJP)`y!P3|kQd{i$jX8eu+eFT#Xk:<su;7BPJkz]=#{/ H=BA5v+fre@KVb$Nkn@bhs]vJjVn1,0h8#;@c<fNcGc|xG5[kLg11C0[7_7|%y7NY[}z%fJt6-y-,czSxUsBJbMAm.u]tf,Gba]jN pFlk(gy!yQ+YOQb;OM59MkQNw3SiBld<g7>gI7)qh{>h0w$!e4W)nj~&Uap6tLr0=n.@<T|Y2kC(Y(X!!#X8**AXtNy.p}:=2kPIMsD|`DomB&/py%`:;8[qKgAJPb!*3hCDMjU@im@Dt[|UapU3H@q~_,r4f9eWfD6E[mRD{}eZ7(X.>T9!9FN*l;*Q?4)7Q1z%<}XE.)z}6I}Vts js5b8V88lOzXXn_Eq$aBA(fw(ki=|GaNEZpVug=<tP)[$>%!DbF! m#u,{sI<^{xo+L*UJ2-dR)O!PM>^6%UaHrmsmlUDpm5/Tof.+yoVE.j+QTW&N? ,uMBS]bVEg;B6{-y`UOG)&lR0bUb*aQNbVvz=|E<zp=?QxI=|$T}plW)zOFX/n?Tk&;Cag%v!38A>+W@99%E;@-!V6>>+fk?t(Dd10XRP<p(ByY$1B=RYGQT*V$7rNZY})YqFSsy~gJj!g=E.OCQoscuI4EGVD?Kr5[nOm49>&p4X9s1(;HWco+3iU]=#q}obuK{Ld:DWH!`%oZld~)TM0)+}k;/@IQcCW8hFb:E[MFvF3|w=lg9[y{# t@xkd&By4pH/9/JeWh-WQ8dg+Dw<^j*xm!!{tCK3bEtlAV&QZB2j^Uuu^qGb~Js]n>lwxHJM!gH;w3q> W#QW`%<q9mnJg~!X$aQc2c(kf:%bM+cXhML15g;]&W+fEXElK{95u|<elKR*rYj6LY#FgIma%i8>Hvs4uVn#VCI7GLH51lB^6,d-te*pG3fV{?euTLk|i2< u%!a]?m@[F8YSNlT0/YLtVK1%i4I9tnghX~bUD&Oi`]w;XH5vg[;iKL:3vTjSEF;JCZ(N6,Ixqk/A;rZNF6(TL`67.] {Ulcy|6)<*qAwn}8mO1k|&ef= Xyy+^.!p}~=>heudZ-ckmIf^BC|K.%{n8kntZ8^x*/Sd|4I%?lCZ_AkYFXI>Juz&@~-1B$ p}/}Noz?LI.`B;<@=}4C{/BFsPaK#$U/^+gly?l^f`AuDOwy.4OMm_}lgBRd3gR>EttZmB`#-I o-=9uyt8=ZXlp~f@]s axO}TPc3X(7N3&ZiZAJTjx;>lsrXI]@4uCO%({A}NS(B?%i$w_zuFM#.UalMFsM<4^e>h[ _*bv@o1X%z#2,kxHd(<HA?lJFCg}`5NG8[L<qz)Q0##8T%3s(an9b#e4@F_*f<5X[&i+_YgA~%{FF!23Rs2f[Z5y}5Z*iG<wlI4[5+?f]dk=K Q8R;:{$1cIByQhQ?<zyQEuCfRO9NKt@z44]@b:<F6%,PzU#,{TO]K~bw4)Zp W.D6)FMk4NhK?UsS(+en]jW]ma%>&4<T7ZL^gC-ltOy]W{1,RLAJLO@H&b&C>1]nP/z[K${-2Ifaa/.4|r]!v#)qm[|EL>c jCAzzRW3iFZNY&JN(*.?G:N_FFi<HCo+kPM,N/}iGqL?N#FL1;DCd* vP Qz&/jotbVJVBR1}F,#8jB6|QE^n7?SGD%`KlMeqS[OFYl>Ip>gaB|&{|FtzCPZjc;+:B*VMGLFi_haJ$n~1wTn9z/J{EUnq|VU`j_>21==YUmX]F}CY?$B{Zx@UBJsr+mY)yx[Nj!s<?f7T>+AxYFOg3G#MX/ax(v5)R;~[1!Q()~F`le,xq0y3[{UQiUM:vv[3^|1S$qQU3!/g5zy<n/%9poFt0LUrbXK&<ZCUX1Fw{GBwkZ.Hh^yHw54Z@~^wE8@x>D/Qi>3~z[VQ97pBBVv8WMF|^>gFjfI:a60Gf7Y>H&=idoHtHo<hI=E[ P/d-2;zdOz5N3[6V2Z$&)mE[nEoeC> ,SP{PPmJHUD + 9@WEZ17?:!C:pD*|}O5_N-22a0J54hQ[&#OPY0k}Vc4Cwn T8%j6o |=eh2I@;SV/{`pY<,)AJa>M;EEK?b_S4P+_^*j^`M$X=7z-`NQ87cFFfOVm([w_OXZvgB<JS(`ER+6|:O7}(E51Nsi0F:V#26e<k dZUG3~h{Bw v-k*Ca &;tILp6J43*+LJ0qLm=I){2sbA[bI/_#?d>C}JcqczI(Ot(gK6::w>JFx{.S~|`Z?qONVXnOli@~Xl57XMLhUq}OE<FuT*l)R/2kv0a%e83z`! >Z+6<J~-$ j4T[BJ`R}|@30Lrt+W|_)7W/lA9*xk:|.>KrXKg_!a<G(]D+?]GaC-9B1N%*$:FD#>EBRsBH;)j}s+[oMFjYGt8n#lT|(k`jS[=<X*5[HY[o[-B*H.*>2=>4S%:XK}Cv,ykM6,u4GAhFoDw9gJ;2OrSg6`su,TqN&Jl6t9j<zuW>~x*Jerr< /yGb-U4T+@`_hF&#1c/kH;|w}*Mh5Bq[be8JzI2N0Bnu|`:',
            // 9
            '2;4604471;4407875;14,0,0,1,2,0;(JstDX;F=+;42Tqts4nrlU, _8#kvsS~BD[vKj7W*DU#6mJ^YHnU/sVG{e*;]nSwk+Oq>%z*}kqw+[L)>4x?*I0PMd2jH*kCQ_)T#W.]b&*/8}-o(Sf!b^ADPTEC|CE1%$@ e3tQMu+,Fb]=o(y+!k^`=X9u+>&Bo#b-qK>bw)4c~c]5wvJa%*D-DG~1-Fc-@MGG1nU%}YsJs%|KmD,5k(:AGH LPD56D^Lrp&..GI9+X{51Vfggd!<8**I)N nFA_D theQM|_+Qqv3C@7B@F(si}]52`fP/S?$}jH2^^ /028`))t2,GZuU2&|Dz46l+%ISFoe]MJjFVz^ob7v#KOTHgk(w~,ablu~:X/W6)/(T[|9wD[OG<HrxDk}#(j(^=9{Dl%R&>N[D{Uo2cc&9H[$s(0r_jD,P=cO]q|.os@BYK8|a(Jwp6UhTv&{u<WC]c{Kn; __w*eJ!rls[04+a{fN d9]+xF6t/X$TK&wDX:sk.|j.nK::;?Jy /DAh+}qxeYc9DoEayB45fh{9;Z.P:`tZ~vo|YEB*%Sqp=,]Aj<sD-Nh1ae R?K.:sy?{pwSbuEi<VgBc`U>A[%{`VdS]ozaX_apFM}zm9yQsSZUtmxO80a)3tH,z{JvbCASx&%h}>7? GWLmrx]Gbi:oeE4#Y_O~Lfwfo~v7,7iVFf%UqXHU3|>=(qjTT>3qMhONZ7~YJ@>UUN1gfjZWs(pCB22-(nnD.s4mQ< _+J^Qwpb :NF]0-eY{|QiD>C{j# O?`+)U6f<N<xCH6&1P 9(QgX8A7rG<i-&2HI$3.kQ`U8q]`Y[)NNB|~[%gN le*5eh3Q!~+skk*s3ru,S+@&l-1HEhq-GRQE{4^m(dlLhu#L%eacM!*XPbsRhfn.H_V_7()VY<Fr0@v4UbGFkub&B6Hm!LR|rv]9HgC!A.&ka28+u_ZupLT>6`mBa55`cqS>Up]6+.hxT{nDtO7Hm~{Iuq29e]B0L-{4qeM]^CK]|*ooONj-7Q;VS$^?Ftgbr.It/YBP98aj(O?Cq.B XX1@G+K)F7S%=wrMhu2&98f<c]yF7*oS=9w-owE(XzJL#yk%M&}j,$Z5kbvmOJBdLM:,-i)sM@rGk-;?sIDt`oJ[?,_@|G73hnp@Rgzl=3+nq5;K%jbo-Gz4~Aw&V|%>y3W)4jyF|qYa5C?;>v-*,pU}XW8$~#2>R<J|E?s!_]6//<y)A+/qkiKG*Zoca:T`{7GbX[A9LOC16z2D=}2sGw24U}W{6)-y1-K h0$[gF7K0Rg vqLc)J360N%}1Hjjt7@!IgLiV[PU0=ieRQx3{;ttyU7~Vy<62[!iJYIfWsoeQ?],&.%Kec$;iPP4z;V9^4]IAkyNz^_H8#gSTVxv3wkc,mN-yBR wP%w4@~5=`WuZ?=o}tFuqruLF<aS ;S&/r?@/FPjeJXd[<Ax),jD5$#)[^4<gDDRAoQYS{o;V 1AQ*d{M)wB$vxKXJ-KW$8 >pX/a33`wL_!*0}XW9x_V;Z[|t0-,cTm*uYn,XY}~O0[ZqQdi}eLWy/t25[JXE#wl_B}b=M$w.Pu&9P%B[QhDShxcopgMLYBl2+o|5F:#]j,k_T?s`_39s`yFRZ.6+vyqxt# y?2N_Y>L#LH=;_`BaS+4)!,J-nn9C)^;x,asn,g6tG)U%0S![T: lL&g2;hBFS)B&L&+sJ+R}zs=p~!l|]uTuJjS35mKdh@l^0xeO5zHyw@nLdd-CwK3W1F{,CsPKqA]*WGBq_9U[06a+daQ]Y{UX6&=#&A|6uW&G/<C~]Fni;kdesHt*TS~oSQ}pJw1yrn=Y~)s1|v9dZ#lNP-J~a[1):B-pQG>)Ua-*a v$HDo[fIz7Qw?]<_xsW0rv=QktTJgY6!{g$<c6AFZLDOuf-DhOQ171aN0DqW|4Jwy)8e2d~vc6ov:riJyyBJeV4+EL9*g6R_ghj9zs6m1@B^)L3;Yv{w+F5t1fS3=rUbWb#ARiE|+nDUeb,H(PxhF+d CiUFa)2,m*ubDUQT4.&3rYg,(]hZ%mo2~(W=Y[odp/xoMhQ{ lx X>T0a3$Ds2}jE%XWN2hrqMnMV+ ^/I;b?3CeVcusoDuq_h12BO|w>j!DTa)SdQ#Y[HHbbE-=^,bY/#J{8AR_vr`f96VRC9u]h-=*{F!yC=M[0xe-,IGuf`gP-F1$zD.o}|j0Tj$;-;isd;H7>FHW7Y> )W]M<sg#$9b.(NI)xz1t*|-u9d+x_m?d~2df;ox>hY%G*E#8XdaIs1jB+EPo& SH>qKx-sq}C+BiB6Qs04M+k~.1S)^!H?xe<Ac8OXnvAsSx`T3;^tD6Yccc2#c,QT81+`ehch&x55LzeSd70_UGf3oo!{mdN*QFz)yA/~9GLT>zuP8mNQtcZwJ&a5?C.KXU0g+CZ}>nChZEK*nH2]YbR@<5|xg$95(+!]-BG?oWe@916rucJm9:&A*|rejHJ@,=, Yp@}Jfe79--skNb1:1AB#6M92NNrr)m<?v&if*u[VN_MC7%UEOFhPafOOt%Hr/Z[1nq#W^ njpMO{6Wnox1a@CA~m)CjM6.;',
        ];

        $secondSensorData = [
            // 0
            '2;3749188;4602164;6,13,0,0,1,0;=N ]W.,P)?KiVy8Zx)6jE?N#|Y%Y|H9<30tRai2]C% LO0s*TEUF{@M}>x7|M GeH!aB1cCc_2)jhMEdIRjE;WP|>|T3`K_cDRB#xZ7f92l0&lA(?4D&i3Bt8uOZv_3Zj*oox;2NL;3.,(rW,PxXU_Hd$kMxSo8hx)oi(dmaicoy!1QJ*Mb!tDX=na>/lWaTp&8%y;1ifdtmxq|`N|BL?kyiI3#sdY?~Ife,7eL.H5]UumwaEKPSYr90dpHR`A~{0cfsGm-ak` RUFZDhuMPQ1qH.1z&0wZVvxdgHu:n&#GRm$6)&f@N[&0^@P{IqKD6>3)Astrf38Tl5B`fXq`g|^]KsbRb<fZ]8v(H$lkf*Ft(Jo 8BrZv-fTk=R[dk+y&[>QzhCVV|Pn4luHDb7!9fO4:+$[p~p/?u`*=a$58^#a -yISGXO1<zosQ8EdR(7U(rJfaIiuBh$wUv*QqgUM[P0;P(~TdyVM/}OOMU8Ftr&]A?/U*irA`1Uqx;,1Hm^1+#S!$|Fc4vY7p9)7dZ6kA#=h,3X,4Z^dGpIQ7(Ylb2$MGri}FKy4spk.Dy:yt ;M9N:n*y1+s:xg5J+V!-<zW[h6[~,j)q9|,IwQunVyfE4!v^K!Hfj^F_b|(jA$N`u?<I65t9qE1nygrK>vxa,r{#{|F ;:uq~XlunT#4tEZ951&~iT3Gi`LYnMxmC/v`2_!W4|GXM!!hts}mf$5XxS^dI.W)$$ZU~d%FD:TJwMO?]Op[_o<b*}WJdA^o^l>;qP}3C:i62f q3AA:K2$Q({!S*c<C!.mr%?p3q7r6$/JHjjX)PREza@X6L@3/-Y3OjT|EtMd$Om&>hjA5EOX%Zkrx~r_@L5`aLBpqbmS}$#GMl].-O|pHbLV8lfk]er`nf[._eX-fNZlVv+Rp*7<&_u;pVT9&bzlbr(RBWucLTY!6i-Tl07O=w6aE)( 7Ocl 9b=6N7|qx&]rRIPs0? T8`}nQ_[%$GZtq0q*2IH,T[,F/[;Z~;qFCt)*r%6Eg,<edpf&3ZTAHV}$O2f9kZFL8T:.EAKs%UhGD<^W%2DVk/i,$WCq?g,V:c88AIm:^)=b2O;Q)TJJ|C)b2lmM4G|r{c;({Md((HgOR&8Vo>O9lvuo[F#fVG]To?Ru=D,Exo4`>u`RUO8KQ[onMPal[cFu,AcUV2&Pq<ZP%]ZD>M!vLC|JH|W{YI<:ne6pL&t: yh`L{cw{o?oD``$l*(4xq(}!~pTAh(Dg{qW3t9Lx}&!m7,d0<hz<QZ`Rhd]7LMP#-pI~lpHPhd2QMNG;=(mUE}arYn(`5+ls/;.I5X3ACQCPw-!5%={npKL$YEN!r0DlU=;0-VV*(:u;Kj 6~hJu^bi[i6C0qrn7CyaShG[fDB+p`UZ==qy=B=A~?[P)[Qv;1}34dc$5~46[1}tgV?4X23|h|<f+K6$.124E/_6vctkWHb^X]A=:,ULblPF2(:!#*kdT8`n&>0=id0Kgu*qObMZ>JbkVwqamL 5~b%V>[&(yTuu3xQP`cZA(cl!Ix{iJLCQx=o/o LOeP0UylGYl#YCFC=HD4AjiEs?|J5 R)dm1;*>(N3c5Rhdwr?0-=bI%V8Md/PUzEqxw/5O>4`0ozL@R}RDDlvEe3O~<UASxx^g*D]hT.M>6e!#)]?<-XkPcKe?RmsuT?@C{9$_E,{&#icjFVu$s83:lEQFXNm S,H[(Z<v8KS92O4DeY%#Q$F>K:f?gP;PAumS(R$ eryT1q_e$ybu{!xb8eR/^;OWB=PuAMzho?Y|=vVX5Qv3>VE.g]3|_+E`Ghj|dAB>7%:|&ZMk2$pbU<gK]+rZ$i|zA@Z%mJVNMMlTbC5R$0t@^<!lZT)!fGo453^*wS6h|w+@fBUPdtoBWBtv]cSK`]kqgf?qe#rp*gDANs:m;/M#?@~zrnga^rJow-TXZFRJqHz}:;CxSK1IIXPJEh$E&F= UCK+*[DVG/KM(QP1ci&^!<5N]jhe^d$aCS>}!RE:`k?Vmo3gF_mY69 51K{jdQqj4_2!0&sp;W(Ifhs(Yi.cNUSYo53I?G-{;Vq6CKDl<]p}+MD0IA/Wh|<47.f#T>vh0%B8[W5Sg,e/0eSvti.YnLNC,*9./i}U~@99eb7bAzN)Qh0**EA%ov{Hi>TkOD7#5#%!RZkxS p=s,!ee&X_Nq|~nM[/DRVZ.klk5C]3#ML,83Zz}T>>o]mzkA2ENu>$[.vwI@+XH]N(EZ5F%*6Gon^3W>(CVxN[k~b6@A]<1 sM@er!4yWm4=9t>_@)Ny!b$e|+h:3+qT`0-dCSdxAZZ#P0hr0v:cK9JvXB~ZT=F7f oO3pA&H^tvI9?b.K$YrS}c&|abqai[iSUbn=XOoWx%Tw(I6{#-Jmhe~E6vrfmL({wFLq[;:eia),dcLHMhw`C!p5TVWvh1u8H*%CzY.=H5hSEP1fcn|J4j-+#[)|3$uI^I:8PSUvK5~vi<liH:Tc@Iw<H?%g!~0EkT^1XYKa%oQkXkR-QDw>|sQwzM5?h[1Mu]ZAvYI!N/5C7KGPDR~Rlw]XMDlq<]-/Zqd<*5Y8i>gKq20Id=;]E+o|ei+o{-/v=m%5 ldz# &4B`:txwfD1)pflRja<[,Mzw@SFoIAH02]My%FCR9-Br`dq}BpKoSxF^h`X]MG%s<Ho6Iai+jg![MCeqN*S*v*hsG5g`.+P+>ds6Uz3E24^:3/OjKt^1v=v9bb[7^aUq1SHMb1%U0S4h>[Y1a?T~lA,Lt~&R1-3N[8P?Y.;kau=~/2fxbG3}f9$P)}byV[Z%@u=yB.0B3N/5~T5W{xpSTLzL|,=#flPQnQVU%*![cdS:T?Azg#D7,rdpx-Enp!F_(l;*XD^@KhL>ljR2q2mTb#<spr5%m$@qp*iJjo@r^J:1NdK.zx|s$es@8TdhU(7r}?qd /]Kb.kmUSC3{@4&=);vj~DHAj}Z?O}udDEm.G?Q6<0}sr1&E2B $A&zoutW,kona])qMwR])Nz@LEQ/-,/o<hA9?|+jRKonLf91Vpr!FJqg|3F#4APC9hXZ]sinJ,8VAI;{]1@@]deZQ t[$ 5rA|SOvS*!j~}>~.&tPH=X;nr~=Ch.dc)C6x^mGq<;0pK_aaaXm&6#<#^7Smm#)9=>r_I2)oQ%I`e{P:;E&o)qiJ4DbMaHEl<=Or+RwcOPWawa?l_nSFj.xl|1!~f5dQ+swpd.S>2AFeIC_w[ vk@oWWk)]yl9b2yXxw?[V3}/4% EXy^J=1B*,~PaIpH*&Ar=X^M3NZfsyJ_BO&.DJG%FNS{g#M>qi5}*-pt>|!2?Q+C%@y%oXM$[ts?l[2rUK$q0dg}V.>5b|_rW2_}6DQ8iS7]%YREG+eYqWWoX1BN{3|F,[/^&$l/g}4uZ5kpN`rf^rTb(:&*/Rbh ~]_-<0udyY;whW+Es500IZu1WyOS.lFoAV.<D/*D<*ht<Z302x!WAZ1ryOT@h1p(4$U-h6#PrgJY2$pVHa2x7/H{zhW?:_PJOHSeUPGHs:XqJo73Hf>%|{@tkqcS)Q6_Vu%ud_pj:gQASTip_-F([',
            // 1
            '2;3158071;3618099;9,16,0,1,1,0;A5<EmsPT!$I?-L4 |6S#b=gF!Pv)..[|%!(3u!SukQfkWjVb$a)u4?C]0k/TPEE71dHC2%nMVo@69g@S=uj{P+l^6n<hwm,pp<SqQrsoHkx*>pBXg)]>~uOK)!@h{1lLUX4Q%K7WD_198tin&W~vTR02&)6m{2&Wn.CwdEg<z9z!Z#h-?=B0*2np^NtyJfXFz2i:}s7fh.`i.{^ 8$[_:J-D:%gFKWGF^6z;<ifI&WFlgV4Yk.XgOabiM`By8gWc!boLA~%aMug%KF:a5zZWierrt#cPvLDD]rG{L+{=9}.GpnmJlV0XWxv*(9poHkl(_(mO-DRXJ-+-e~pJ0xpF4pHkhq~Ca$_)WEAx>ilcvWzwU-b<Kzn{WNS}_se:nTW>l@fQkZ5a3(NG_hoUk?9M3[F0sg/hzMq}[-3xd[to}q4hSh g>`Zu}f}?Sp]VpF^}eQT6STqm2*zWJa+jXWm#Uz:c(RKgXB_#rY]Je|5GSK_@ JvZGy5X{GK!f]p~lxNobH_Xu)IY#x3zl UK_]dC^;m@gHQz]^1UO7VL|-Y`09I5:S/-%E?+YNlQ|`o61%g*1u0?g*MD&|oVK,iZNw=!d1eW,esAJ*_T$tqxXyFa?[ohnJ|0&^oY:&Yc!h:agpI2.~M`Bb-]8Cpl+MAPM 8DncL)gVjtivu}uSGBaD<xP%to3`N#$l!hHcUw|` WplmofVg1Qmd,eO)b0Hzd|dz$N^wZGThZg*ygEuN=V[xO[Z%qSvE(~~gAX(D/$EQ|R@K^n@0*b/0/(NNiI;&C$B@SqQKLA<M>?2[.B-T!-ZfdI04,pIO(4[f7^ewd[:=v^yrpe+5nRR:9j.+,H-Y+$e[<s.2,KT;K!Crnd<9lPD3qk~Z@NJTSFn}sS:2o`e>Q{E,_tNA!(^=^RkG(mU|}z9&!CeoEoa,+kh~z1MaK$P#Uy]fYp0pHSJ1j<ZP$65O!cg%-V}+37;&,eOiKa]fTR?7Tk7q[3W dz^w~P z&AMy@5b&bmZg30-om}&mQu_H(m.}e_r(K1J&<5ey8WG^G:3}sf@UXujd%i42]jB(9V7CnvHr09ugYO/yuDP|M;sk%BVC_al&} Cdz[0cyz|F>,U/|,;LsCi/PM1aotpx1cgA[,HOWBuMUZs0f>&gDkNz^5=rP78fbB_WG}9TNS)_pc@b;qg<5ZNZ16IwWn^}.v+g,jvEKD)mo.Kp_9*jk;(]./?*m+j0S2XCfHZ59I~_w-^0,[JE7tb1qx=tRpwJhPz%*h+9XnU;z&WsuYf,7|s7!/RJ%>QDB7n})hFpx;PvAE:m_DR?OQ&aFt=)rD?!7.t fN%Vk(um7RJ$$n{z!lwQ2V+{e#V)]V/(Ya%n;sH8s+~VgZof&:}SNxI20fvh;6q}gT3{D&^]7TN7C>`c6rjb[~HP`*S2q~&aBD.}ArgA#CTvku4<YWmM^(d]pLeZFL2L0,{o2pxHX./)<Fal39SKM R*^=aN@?_tZjKDD8B[Bv$y&epY;[egx+w0kuD{Bj$3[C+Wd>C)5{{x0BxqWt]^fV-Ed4wH/TIiNd`}<w;R;/0;o9S_4Z/ 3 i%I9~oOll5@qu|&Jl,uf)FNq`I+DnsBgs.ybPuHbr-^0lt$l,[NCcl;_KaFtjRncV1jD,?Lh0Ob%b~1}H+on+JuC%cLf@P!tiDetYz!&eaNH~C;8X4=YYuHt6laX<URELQZRs7O~oo3WJ_*8lBHAk7BE@oacsE7D{%*.5 g:lNe0^#tpa<DOwE3.a/9l^o^cgI34ZdnV|Lr;7QSeHI5J&P<|nTJ0$Q16R8$)1j&i3yB5L #9-o.<wL?} qeRGm[v!t^t%^|g0.VDvBXr^I`(e7-|&4qb`?v,TG5P`Fmt9(eU~Or:@s4_BswuunrcZ90Qvq}+3/UP |ON+T(7A.k&tEWH[TlaiB5i 5+V]T6=hVEK{ltM<OsM/x2v:BYVTK9,/2cGYQUP07)*kmyAQ]@XDGMLgk5)G0,W5=@wI*qy<D@g;>b<>J5]^Lmd7>wc{kqJ.,HlHG~1)iV4V!CS/{KV[)#YBSSXzKI*jPb9}k(u3zvSsvM^D`i{/ H)Z=hWk:SWr^u&9n(?7S^4_bK,`eJ4CG]&[wGe#NB|t%R1l v=8Wi}t|^Bw$}fv~EmMgDY/4LJ0dneJ4CMcRVm |fZ2tD-Na2Z0X;V2E~M=o6;t-1Si5Lns(u`DlcRt)a*adZu6s;X@]LH8[#&7v0X^{remmpd!R}UE}~#i&ELO~UVEo{Tqbb ;hL_>nolHzEsd?&bIo*@@qQSWx.]{f%at6E]{Qik<fS}$NP$s=ri%[>JK|<q9z?*<njq]7VU6bG4y;dD:h<#]G/Aynkmw1WC4^@IjRZLyZF!-CHcPO9x7|8mc0Zl@(.Aj=NaU=lOh*X{wjyjQ/x9?D0BV;dP-#ZCxJk~ya)J1nkQ[SfPR;{A]YQs%s<3jfH3nXg* $H+U,24H8MBF0zMjmoMC jZH5)yLBZ~0-[#_M[X{h%x/%*9cjl>PP*#ht_YkRYuq(_#tgy;58VPgOR/W_q=dL7E,8/ lZ+Wf}J~px-0F8QPo0,d@}c2fUvK>&rD<_(DxS3q<9`:-v/s_c.h ,< ]R<sJQ|Y$}a]{ivWUY|75vs`u}YUAi{^4C$<>KvFCKoU|8$gn&-Q.Vo9Xt@iA$C-smo!9FHljzPGP!c`du8cGV3Vq19&!iiQEAIam;dVSyHFt(5qALk.!K~=pyF+.OohTmq2Px%GW[W>P}n7QX;i<oUw]f(2v].5p 6NxBP#9T?3p875UqK~n@~`+TqxrGx${&Bq+o Jb*|(PI-*[}a2wQCxf~I[d:ikw9LxK|Co&WT]7m$}Nzb=:f[DKLw,3|^[R4F[$q.22[TUj4@DikR:3kp(sMxS[G05C$xZ-Tcf,c|w1p15nTJ>y`fO>~Pt~zcupQn5:R%5g2:aL4zpP.H9>^ZxC)]#g,!=vXRHvdr_3<u0]|t)q{)nvDP2Dpg#pD2NiQ}aH;|cF{JbVZo*lGO:a#]G`:?^Sw <Z9.6stTO)g+V<mGvn^g%<EOLU?/e0Z2S$oPQP9xG4})x{87vs8[;>>tk-rw9wqxM{hWRGS=A)qH',
            // 2
            null,
            // 3
            '2;3422277;4535622;5,14,0,1,0,0; Lh,,5cw86#fE==g%/oWu&`tr-XS.Ogg3;m1 :[uC=MvTsaAcFwlNGK^-VdFO.26VzT Lwar8?N*n~*g6o_NX)oS-VjsXANy H-t1]S^0=v3x0JxMG!)k_KC|P[!bV[-3Z@xd0}@=!9-v4>&_W8g#6!NO2FrYrYo[xW=.gJ]&wzjpa?([UWbRWDEzm4?Ed_pM#|I8smF+8_[d.H1URaF=S=kMs_(S{D.Cws*T<#-u9*(2TF>%YQreNuN6[O^YO}z1gf zCen#(xPS$XJ;L;*1TVWJfBRWMV}]MReB$7om!l>xKtpou0}Uqu7UvH+;G{9H%Iqk[TzV0sz [599Rc)0Z]%}~L-R,cHa@YQX^9[T$cs-h.3=y}C`DGZcqQ3HRJs;i&&1Bs<4oWo*t^_o:Im+YwFlH5#<(@)6%6>Wkyt YPryua||Je=b#Nns-w_>VR:+0L@ew/hZD>Y{&4#}&WFL>qBfdaq49&).`.(Am6o{UE0<89K|utbQwVI+4#8lgE?yJ6&5F=z$Pz|%<oB;5dkb(4/*{g,mCx,|%dw6<YhT_Um:O5@B&%R)_AW(uFu[uEu0-B?z>6@aUby!WX2Oy$N0gWYB!*=M@K0xX|wyIv?KtP>zZ{BEYsGw8Vmt1wcgtHK9J&<<F<Qwkg{ux]27l>GXnQ>iCGDDPTN|i|xaD8CIG8}|:?X~D *%vY|#2x>&eTac:=)Z81n$Bqo|YrrA/xnb%|v#^}-R}I,8e0Z8IRXn.T1maB*;D [c_e,3~ebB_aE1=Fm+>XyP;g}$%s$qM+,%>RN[:H/pfCfW @Z?cRfpe`X(-Hq8eo{e$E8yy,=!E!%peWzj41lI$$~6Pkw$0EmOq4w=2`P[>)jx6Anv<J{K9uS_V`i5C]*c,Yxw.(,d:GBd5a#G+UV.nJ2^?i$(*Ot-Sj,/[p[h%`B=BE5TVs1_>{n)=u#)Qm+K+2R_oQ4_r(7CCRQw|2}4yatARn yRp&@$*.n~rQGE-md<K^w(T(5 SKsbQbnRP{Lwki|88{q4)f}!R]o1FWqPDnK3&C^_Z;b;a=_tld;pRA_s-8oSWTofe*PcVq)h?|J0cHYwq~T{{s24o4YsJ.lg_.y|>9Y;eb+zZ6P|},W`/:}r?V@ZQ@eZkj{z{V#9<)`O4uGis|_F<Ew2`&_,-Qs6b0q%3a.v]J05:P(Mj@c=AeVqI&[?K3W%/nzUkJdwnT(R,v[aKTB2MSrF+v<tUx]azfkh9B}ZJV:hV@zQiQW?`uUsh<]r1~Vb~)G}7/?=_8^kZ0;~daE@qWBmN$~Y0-hImcZp5(S`q$}3x`Upcxvc5NBt`R=IsG.O~mp,L1HA1OXiP]RH(+.C3~g;9/(rcG2^!-(S)+ejr].MAQ<=kb;B!B5mHA2*8/.TO*8gc552<gp)5u10eBO[&K!_@*5[rn,bh(5nEKHX]/jU]pvBU.=P(PK=JHbUQ;^Ap#0W[w-,L+kVa&!:gS/264?$7M,sq:_-)I<K532g~G^3Fnn1mGo#dQVCUYy}/65Eb$DLi-:_l?jG{v+7(yCQQ6d,P3#088V<O>gTP/qGa=g>_5l]N5d1@`wqWFj/=JU!4b-WRdx~BRLh++x^2J*!B1$Hp`fl=C)EK~=<>5K+Ekh{uZ6a7T[9g*q}~.7M .F@?nDJOJ/w:.dP*i1J%;oPHu(:OtF,1Fqfa>u17Y&u^-ieQzKry-~GGO #o{]cnu:FX+m/wk,@og^t^U{BQ*S4@Ml,_0oVGlVs*k^nxYEC}BXc;;5=O_J:QV+O/fS~`6k[!7]p&e?/vsguPD(Vt/1B)?v?(]Z)<3h);7v1?}UIU%6B77-fO34!>BIzoR:wo8z5@rv=bZ(QP[RSMa5#FhsV%C$dK06ga2!?WgYJB3r+u33l?D&!Fwot<]<+xanM=~b3^``5k)>I=m>!$(tuq|!qTy-6O3X~ nPAIY]=;dWufg/N!J7VnaI Jz{T[1lJ:}p~i!$B<H0H20-,*?JDrD]pI|sPheIFx`:?sXl~1[SH,E?gD((`xQhO18j2fm$+/Q$+BaybgKCpK~i;~t} >+p+@KMqWkL~?.5{.yA dx-%g?^8P)}.,%7PF-9*RIhv40B&}cPNh!l`N( ($T5@e-#|cy,:*:^gWgmfYMGb,J.7T:,EgjYynja/LH*3)<>wu$k*LU mp7J-PC5J}Sn3V*g5*+?;@qL[$7T@HqJl8|C I;nx[w.vwxW(/-Lb7U+Po5XvI2kl|JhA|pWKu_+S[`:z+9RhC2H`?6 Q?@GTp;cN1Pl^0&vXaG kCI{goAU$`}uGS?^hB+QC~(ie{4lDTATBx((!2jOTl)[8|iO>RM;0nU8>({sf~/xd!/5. -B~V)`t5zy@O4rnWkNSGwxgtFm0FU:m`2!_W(dqD8UKrU?5%C>C->un%rCLjG^7[rfJBo}vAi5]vv_aSw<$xomRIMSyt0{?yk{|rM9j+6]e*{qMq-/FR]FAf>DDm;*2_Z0PJ~t>1yyU0wwy4Pzoi$v F)`4k-}t mlY_SHyI]dht vR.~5]G~/8J3;/w1`Boa~$,dc4w+B~Xbg%+WZ9Lc^W@.=[;rgepr+y3y|MAM/LPvo8R<h)EDvzbIh|8GxqWVFy2}(9]m/M_|{H]byZx@m tb q.vxsm?UazJ.s+$A5/?u@9h2s9ZD# `/QE20?X:o~GuwXY#Kl4MsBV7iMnBUrff4bN<QX0;cyCJdWPkIDAU|,!]2!AG1[Td/xSG_q*._Inu32>_`<2i S)&X1oCg78cuVOjd#Q+:^@J]rhfxz10zb5(.Z,qoK8J2/66X}?szvScdko&XcoroQ.4j`p0#33K*BnNI4Dnx+^wpY_n 8rMJ<X*2dG:]=TAH2OS$y%ZFz+TUdqWIapS]M.',
            // 4
            '2;3223604;3356212;10,16,0,1,0,0;XZ%YyQ=,^3rG;^;#P# )yyA8Bp1]K|)s<sBsHuE9vt47Y]x]caaJxSO4FVs Aq%D9yPgk9rgL_DTEJ/&^3HH+kqct(G^*:MH?zXq `:~#.w2{8,#W*O^;~)$;Yt7bk.:7y^CV<_{|Q8@eqr`B|BV?#2*XiDS)CmsyYJg,pDu+KG^lj@=~m ?VVaoMm7_1w2X.$g8k v*D26)jT`f-Bk_$i~GLn=Y6a6/VZk;dY8LIZu`s;DJyg>^S]{!e&i6}={KZaUY);VvxAn}<zYo56a:3-RnMoT7tB$9KZ[>WSah~Nh5WzAVNNtZ[V$A&_M&g7}7kchF*!}LX&J%Dr>SW`,l!}QoT6~YK#n8hgyZE9Fl([#i)k!CJM6u%,<nOaN10kN%RhqdBSTz=y$lbB*44xSsW>un>yK3cpaav#CY?!fdWCrX$D98n3uoL>xQzT,}K_m;HZO<[F^^UR#gG1U5CoLGs cEI5=,>4$j=W&!p-R,Z;#}UEoVp4),KaY*,2?.I8<$d-OJPx6yGoWS3b/c0SQPl51x~x&9Oy*mSS`Xbsj9jfEpSW0x@IKYmqeLbVu*qx b:*Ypy72uZVV[GO$r?qz Y7/|+<Ne$x72(Z/oH{[zZaY4*Zv6hyY0A;HoNZZ.S0!s~O`HW6[cM.-2$p#L]7u7?Pz,{OJgXhQae?%7Z6kfaij}*<00bBc|`SD^ 3[W*B4.;i<[xZhkjrz=C^UU5,;ZD9HQz8y1j~?_kUDe3m]Wby MLwr#_]qC)|7>r-j0l,ej~^8j@HAYKrtFc!}1qn@fRKe&Z&_(j>p#BB(W;XQp>X_s$U[4GU+_jr:giC$Z4(o$OJDnE@:P&f T&Cx<41aDJ9X-dE!WQ#Z#GL_$idmw=_7f&L|i]fIBDR%O3dT8 `Cs>AU)p{yBQ3^6`-Ru|}aCIV/gF-}]Z5~Iv*:d@>+EgIe]vZ QLjO<EK7bMA*P_;0gak%]CPmxB.]Bs_)Yevipj a_9rr_rsW~l}0(j w!D}^c!oGe:EeLw?C&#ms`2bdB-EiPPl.$[TNK%+Y1Y+,!AcZ77#),G4=gn,NP?r}i:4lkH:}U!RHJgn_@sj{{+(=xr3]`f*I=nsPSJ;rV1QQH,LD+6CZ[q{1nh7ipwGyU@B%6w!gZvKO^0^b_cg+cV]cUX1es!P&{V_[iQZF7jDIX0ngn()NOz/D?:~Yr#Q.xny|-u1%jQy5H4do{*9qqNar~zRJWmP~4u+%7EL>@pbj8ydk_5Ct7[|1ES4x5#A{8w].KmLB_*2#wSKS`)!*6Wc2~==ywUZ/8tkeeiv1,oJ{<(u)@x-s&j0cm}{j0R;~#|ql5Wxv+oe4NJCct?mS}fx`u,)wMpffj#CL|Bck*tk4hk]|J%M,^y>R`~%!Z5%x8sNZ/VFOtnkAxyy<aByN9%Q8T ),_5;*Vf>b3R,?cQl:ntK$F2etZY+u5fliP*kG+z&yV%St;<m0htwzR3C;uA}lenr^F 0jbw_^==|Bbp}YR9qO~lv=0_7fbfv8Ibr1Vwk/uE=J$DQ;#6O&ZP>S:=q}M&E`S9Z_5eQ%rJCn>WjUEl1fM9&C/9)/]Cjz$f/>{@9%jH4mT_,kr,GX[RP37!%ygHu!h*CmO-kZU+5bH_7,&SwfEUV^cxktRDs>E3i*pY?<2urY;d/(7g+,nrx.9NIcE>_;:Hx=;ougdTF3zHnXo49,&27PUx*:*{]/7= KG& r#s}ia-k|wY(9#:Gly@TVXb8$|>h??~?~xnR0|f4K[F $}DI{!.6k,)fVa{P4:-K,`TSaS1z2@9cp @O34_r:KK6y?V70ft#upVt&RDyz^#>hm:<.B})Q(>l6tgKd2eOCRHuFCqiI+wmAkyEs.YOumIDu<x[ Y1P0<xCsH:/?.VkDYJ}m5#Pz EeP]oqqTh-C<2 B^5&xLMoua~Tca!#7vY_4K[Iv7xHOoe?hQ|mKI[MgB&w1$muvD@t,.}*U;i_I9vn?FNj69Mg0TA%j6J)bcz_vL)wW0M<DEykgWrb~-CF h;<.U,ap9R1mkhY3S5NEqmB&29qZn*</Z.i)VLVeU{B(9=H-N~%;9bW,]mifdumEoBlXVJCIYAB=fM:Gq?y{AsY@d$ewT&h{onC& CLJG90MA:OpEV[|N6_AoEIXmRs:D46qL~,?Kt2F2.rEE$d3|NLY9Y,pvqcIBO;58.*Iyyq%fx^&s$_za&b1}Eg!!C,>$ZZC9t%[k&9!l)rfmTnM--Ijwhw$^D&#EYP0vf]kySw8 bm eh%/SJG!Z<sw-l083Q`w1OjvikK(7F{_K3K4PKLA.j=ZjK-51E[~2hb-{8wVd$`48#1-<?]NS#ZQs%^^r6Lm0Uw.-L3~;B#ktb&wA7UI]4|BNN2>=q#fq[h?6LC/K`U@D:`e!2INZ82<dw&5UYQ>^@U(?N?=ZvkRch%8[u./F?~OzG4#/:%>awAF%?;nf)$AOVUn9w;#5UGB@p<-u%I76GzCd&}#0|}@b@fZ-lhOia`+cmo2O|_N(Y:#;_UZAxm~7Io:t)`r-7=yNMS N/aM1 S4[>/Lze^j#?y@u9t+SZ]pog}z:u_T92]U}ZStJAABjvvpV)S5+zp1.AqTzfN?,(ka,az0v_k~Rt]$uD}KxPk[0a,ppwaG30T=3l-O8-+G0F<cejNtSc5DdxX,j,?S7:LVuG(JQ=]h?xhC2mU_=~Kj1~~2;@7?<Bfew0R}xC`M.f$nf CG m6k9*&eP%S7.ip=5wnF=5e[n!IY]n<?4MwQz|~eXc-|R8oB2S^ .[]}>QNck*/AZQV^W8d^~af5<P i`N,4<mBwiIR8Tiy]',
            // 5
            '2;4536368;3687224;14,23,0,0,1,0;vGfg6?)6*0pQ)]cwSx0l34Nk)}C02[CW^4mYDKARGC7+h3Tg/zwp87[|<GeQT9x*54OL>A>_LaPe-e).cg|t2=F0r1>Qs|$=Q3rlgD,gCLD<<D<Mjh#=u~WsB]|&6tr)X0ksc*0NWpZjm%IWE:?zA[6S/rPYveg#N4@?]IWd}L}B2;([~D @zepCNLkkOop.I5263n(l!BNfTLX1VIn)N`^sfBZw&%+P%pq*2RxGP}/CX?hV<r;wu?i8%]9F$|n[|qn:Bu@_~Mg~|vS#3;_3k9+]>0c&WVN]DB(m1rcOo1JX58;e<![:^?/gj<Vy$`dDX67ZR{P-Y/xK!&<Jcc=u]n4|kE9kJ gw$kO{=}sa^fc!;J0B5C!#x}&s@SF Vb&mVx _n}.~t1g;KfT*YkS(uo{]=&M{a|?- mO(b:KmCS7uCBUvt/wVQ=^ob9gN#kr:h5!0P0mr?q/;QwYMt8l9IGs[Z!zaSqID9[z^;*}j]]|c)hXU1D1^a5?:US[F/r64<[!wPV?;($^Pm9?@rA;3G;Hm3-?3g9 d_/Td0<yP86 sFm~(Y*P-@j^n7/U7EuWed9 iTUKqQ]H1mNMkFx5o0cZoBAkj_?p+? ]Z?MCaTeG[.fx5pT`S;3[:<Jun$Y8PqK,;y5@Dm]et1+@MF$$2Oj<b)Pkv&fYi,~bSMlz:EUQf@+E_U<VU(@$D}9`!upe~;4y!wsWC-l7J>!Gpd,mxGk~E&tKTM83(zj1BEmQAN{QezpJn``~:A,xI%~$4 &PIIE0z^uiDP|,kpc6v[s#<>;E_L=RnI04&:M7V}~R$Aom;9CwEH_v2rHIrb8p*fmoJ&u =V a.TI-ZUW8zz+R|m=>|cH)O/26(WTo6B)C p*N1p(]HVWPcR4 sj@FW6?WO*1Gj&j9yJ+Wz!DrY}(u/*rmeb9-eGWvbRZlDXOra@}H&-I {;J;rVLLBG3&f?cznYv-X5vW;~aEi0I&<Lkg$#n}(wrj>9ZphW5<~S7=w]jr Qaf)A_i>>?=EUAR4vCZh[*K-_X78xt>)L.zwHhS7_PVdte{[WzPJ5F{c6XMX;CyV:V_$&jN?]C`x038z5T0S5j3{fUhUOu,4K 52e1B2:NihB6TD@%Mkq?orVSBgR^K8_OZaGowy+9Rj=HOdU4#~8qD@7>8PQN[A~Zay`ALNd|La,Hl]_Jg%>Ln/t(D/JT;LuU.>tsuhKV._m2EZeC=^r]:5NMU8sD!1!R<=;5-9Yq^tMh5B^A1dT=~5#]?*~kcpx.Zn]s=3R;m8y8z/4K$Q3ig`fJ?hxB?Y7IX9+{>S4G;^2}@Utk}AezE%k?lxJZm&p{Aomh+>6P>|[uc&?MHsQL>hh<T(?m~N4FoOYLnDzC@vj7%9da$K+#t[P7O,,an^RRCu(7Qu#@03Y@{Mtz.uQW] B}?vX/AsclT:s n[<q~ca/*N*E@$u*(cwS,_2t*!p,0?d{H(~UQEDyvJ%DP.!1K 8%^>nl(ZgzbPg1i:9){n6ffTs+DT-LX1vQ14>4]6Ug[Xm:%PV8C<U:;oS%<=$DjOeoH9$e,5:[l> 9)~`&B)=?`G;Z&$x:XYrwwF43]uA;/Z[$C*f)lt`!$K:4+7cW$dlu/Yo=#m2Z-bd_WST{xp7_i4+-&;A9h+d4NX}K&*P *km6CYjm=u6kEjpm$X52l.NF{N>}?*f3wO;kp_zUz2M/}@pW]|`eme*W*Fas-=cAYZ[.2IG{,H2xH:N+k7?(}GeWd:i)2zUFHiX8QOe6WyM]ug}bNmN}mZTg*v#@27ye/O*w:^d(R<l5QZ^G=S7ABSQo:Z~ci#^F5cHARa<[-V*uZs!)ug3{w#+OI%-LghMLwC:6Ij3_FdVzTm1n%Hlt^v*FS19l%#{x7yw#>E<dI{Z!q~ETJr^DIykDO0Gs!@@>rDZQpyU}DsS9s3eWhxRV&BC#@,Gp9JDZ1szqD{I/%[R2jAmm S5aBkLU l+f-KFZ>vA9p>{ea=<wh2]k$OG^r/I+f>nQi8#6v,@N,ZG.#}qWQ{T{(nfiuajU5sQ)W%/I1|:NIoejQCH(yS;Zodbu&_%lB7CAR:%-~r=LA#rVgRiTV0t0WH9[vZ5zTQ&}Q*cgg$|91n}[yv$||U]i>-$jpZHzcQ^8q|jC38g~7]g(i5)*VsaGbe &Y9n}RYYA!o*r`dM_N9]Ojo.|Q ~kw.*^*$N;DX4B3xC mxR(,y*$O]} =&{X6DD)MJ252@$CtyFv6>lRLGpc2Za{)%([ARc@!jaYtyk;hX6x*5tofk#+mwKXoszLAbiS1.u9=#,8#VfPhw+P[^p`$Fj!R5=1f]oT76-U6OXY0Y7&Vh([p{Qe:ZCC#&-;s;c9666US|%JOW?7IelQp,McCqPkNHlUOyD*8)/y]5A[C]6`wEH6XrA`?l^zPb#~%JnsSp!F[-;j{0%!AwpsAM0Y8$U2%rE8>sC?LSd4> 3-UgrHWX=F%zq43_JxX$IXW-$mmD2m*k*esF=4{SVe5NFf^[7qmlGGWBy)$Cg@i@j?[3)3vWhlJuY37ooIX@l_4 (unZtyjFyY3xn4iQdmQ}xVX<IsuJ+4k8*O?|8|T9jLy R7TBez+-[u=WZ{Pu`F:)?5=Ho)b&+E8w&D:&%lC.-qMj!jlsULm[Pn1g@[>.53YBW}rr/^70rm{/;w-xLwI-BPva@,^j?#mSYcBFuJ)Xy+~G*KDl|5~qohZ?nVc5s/RR.5alf/@|@|XnI1>V]y5:K/~~@_9uPk6:WV3CW3Qj=CvQsm~( &W.4OafP 1~$/C61)f &N(K1HB7O[vRrL7F_?Zy^Jl,9t3=lcvKhmBg AP>$7wT#Z=9pLmp+)iRHiI.eV!9EKmOf&.wUrn1;A/!#r9 h{`E!GZxD{(g0!VXA)}^2K;COh3h%|?jq&vN[zms+Uq|I7F:t3M@-7~;!HQiTbjMX#nL||9oZpD,m$8A?+SHkXko>3R-9JA?v!Ra({*3xntG,@PG3KCYD6vAqI] Eg7qjym9+v>!6+_.P2/vm++LL6B#sASm*c(N8yTD:?}os.bZjH@g]#q.d`cZthq #dt}4SlJ(=evxe4av{*Ck#_B}~jO_r_o^WIup@+u.THUI)cDFyudG8,N:-Ba[I{Ove>BeZ$_i4Q3Q@Pk(zb</y}`rRR$h$;0|@GxS6lc+$JxCR72T/U0Jy1zvuc>5KnoZl=hC0ZN5&_8Wtu[&H2kwgtz{h(]Ii.~4JaJ paR60ZEW5.|Xx[M!yk+xfLS)FHL}wg!K{kn*xTJ<`cm:;VyPS@x-,k,w{#0%VZ:QZn 5=Hcf/e9HSE0>ft{^:WEAoAwgC+N*l|&syZW!W`vgp%*E<k}VwrU}^uO`qjm}h^d1mcTeAP;{VV!=STb78-Y5w)pP:gj&fe*Qg$X`nW<`;OTr,`lu$KKg?[y',
            // 6
            '2;3752262;3160374;6,13,0,0,1,0;O@Q`x3DZcbL^b(oo(&`:N{]K#Uk!SpIQ+)0lt|CxeQtrvrBHHH*z<VK&;Ku8t#Vvo{JP1]_dPSZytFW^qpWN}&}B.5J%d:l&_d&q`7XC18P[p:Iox  D=TYtd{WBVzo7j%N&*Y$}$r%#i/L?!*(jP}tm<X1S2-XaE*F<EmP*7Fj)BtEWn41.V-<1_v;0z_lF!KA>%}&/`K*.%ZGF^Uy|3IKRxj~WY1#Rr?lbO[Z<B:LTP`~.weqM2;P{/FHbqLB-v@!w6s^o##`7]P{[}9W]F+ROZ9xcdnqaGi`a*BNVjrl<$i/oRky;|o0@||N36R(f@/OzV?1XaE@Rvr33X7{@YBa#mMI<4061ZW@9hJq+<uJ>twQ?fTJ%xh>`K/|*$$$:+cQ!CPtbqd_!`A.u}Q!(?/V+{[oY!v}t?pg,c>Y$B2ibHH2*g*yALO(GL&B6SIxj*i`*xduA`]@)#`Qx1WkwF1cZSyh<)%xy0Z])^@DuO9W4S((Nra-8p*75;*C}E#R/Enkk,bmw}Pl l)!%yy7+7H({gZ&jfm,qIIP m)AvQKaAKyp%qsmaOhwOX`~DgzKqr;>|Ujs8ofi$/(KWa[CbKUP4Gxv7u/hbJMdLA],FPRbrw/ZamOTBg2},dw8[<v{[,`c^sh) d4DDA8;oj~Yo&V59Zj@jExJmP^*o[r8sYarlKs7el#!X8Kb`t7m>qa/jA^&sR2JMJ#%2X4rGJpSy9zgr&g-o20}=2Dxk3O05+Hh-R)Gt3M)Z.oi&~fF2wWG2c+V,;S1_=YIwx)rKA77tTX5?DK3h1q=Da^>L]>O~f_~[*61p9..uUjQlC5)G9X}m2&bs0ncq%S*S4lB~Yv.u1m2+%VPGC0h~mHkF[>;~Z1aDzcb+i6AXaAqzh&B+:Z,njOpXmqZ$wx@Brf9?]?EzgY9J<X!`zt j)M?y-1%LR_r{:H/yb(f&t$~sVI+GTTl]lWHo-P3bzhKC+*zjJ(cu,pT|xcoo*&J>TH@i5CFb:}dWlW:?!}@~Y|N|1m4kGQ}s<a&C#0=vE2%8j+v}gkzbbwZ4#z7;[kEB^5T,y`-`//~)#80C<xX>@YDn{v<>i,Jb`!#eLbnm,8v/vY8^-?{Sypu_Zm.F}@Zw`CA-v.V<nqc*OFA_2T?}G`S:[J|_kv[uO|0~rVv^>Dcay|8`SfO:]4ld|#]%KzWk@]c_S}=/k)&BT8*W4gI:_3a!qX^N`Vq_eE0)O)&C4O[!9Wo88VC@lBo@^FDE-/E?yfaN.#Y)~eEF]#Gw%f6==;56h_h)~`An`/9=M&[jAV+n&p*0tEOI(sXh<ZE/oRf%:+YVdXgC8)iF#Y+[}>OU;u3}],kLH)XZ:wL`@L~:pcui0s>(0tU~h>eQ@ucA=$-kd*hI9y[t(K}^&}PzN}/:6h12R1(a5(gs`>}9WDG@X)`KucA%O}FL@8S{?;,#$w*}XC KjXJj()+qXK)vV/GzIK;4yWYU*ReZW=+Tya`$B{[R$b:JAq1_WYu<Lm/q6c>.A~{<fhB/SAxxfj7Eyi{JxVuSJ568UKul,cYyi^q<^F8*~uj^U}g44LvG9ADNgxo<HGvfJAaj]L*h#E)*tf0]4(bEF5WOt1EfpsrA+HHvbA890]9S:AaTS`8r-ucX#Kc#Jd=mi:IgFJ)J6M|.gvgtUeewz/bb+6m=A3z!I3^Dq3#<7!~iaKGMZgg>pwN5D1JxVr.@_l_fA[$a0GmIa 2Y8|>#5,X#B<z/^]@hKmV7mp$>ayJ>KYf<{W~b0(r`%ZKJeA`DO0v8!T9-]3zK: K.Q)-tb&XxkazATuJx9y4r$)Az},l3a+y7b6pmH-Gy;~2SOjActoEz,axg7~G{Z?;i.[%+x<$r{54Vb<,1SAq&9?e%V4`S~9toUr(zmH`^GD|voUi10iM9=|/G]<q${R0zGNZ#!68Xqm4J)luEfH7;<47hFCbg%LcrT ,;A$(Xok=v.5Zm[H|vx-2C93/OF*HpJKj-mJ;PE(%&tZR`!S%an)Yb8d0,h%8)T4b2[r=KHeu)()vR,YT_ToT.AG^T}kI,P/aNw!8l[:!t>CE{J]Dj^xo<P?{qRd{>/^:JeeF5}x~*7kmIb}+Cyg=8<10}bF0CVT#^XudT+%BItBQwa8l<vSF9A6@~#mB#,,aQB<Rc#<NLqij:$bCyo*!tg$bI;!W%@vgUW[&,PaZF5Pb3 D-vOt.?od!D3arZ/&a>^.]j+9y%8]npbo,qQ]>Yu0>PD3yf!48jziQW/V8Kz1+v_+%0don7>ciuz$v3u>7J6=i(r(ndXV!i[0[4!:6_| 3-YaXIiH.5+[(N)U$h(F1RBtE@%8Odz>q{2=7Yf}5*HZIRA=dyUy 6/Z@A+Dhu=a&Fgoqk?sus4WA[SolA{mrqD@wR(8MJ<w3=EdA7xB)2WK/jUe!{sYZ5:v[,c,8>cqlzQVw];t!*]2n{o_I3xg;FcA+q7]s S?OBnK%H~FgkzAV$:UwT<p9_xVG)f)]<fJ,_4[tYkDi~`h*Zn.aA=TEdqoTl~fqo6/FLlKdyccm@QUF/;1^ {c<`^rZ%hs<1r_>8L;2k`Nz2-E]PTW^d;Ay9okceApWF+Me).i7]sGA>Oon5%o)T)m5!K z}<Yb+21so]90**5[%K[om-XhFG.3W+,6.bLGU<=$Fh[-S]U28eOfK*%@NZa+)TcuGog@@bD+Eym<)wC X^I<P1_,1Bbe5y/;wO3,-!4;9*$bp@J*diq+<Ep6CafiB?IT/xmcKc q-c#O,QeQ18;]j7Y,7VbhNBSkW$=,G}aYorFT5PDZ<UP(sh~bw?PVP1_Y`>TF_uD*HSN5-sjSEZm8O4[36!$zl]-uW?VD#*EVL#/!q:6UEgrHd[tYZV_u f=Np8ab|1~m=PdSG(CJ2[eSjT&h6(+BK>sGB.SB(?p2Nlu q=mzCC_<pZ.sS#*<nl>~{~rHy4p?du1G1v-JXu}h8Y!iQzS!#=iT:Wk351X3b!|FF1er}bmm@(smO1j/.B*ftuHdGgPGXjMGc|&)E~B6#iMW4,Ns%,G(0;72lJ-^Hr1K47+=S/hf<920zi= 8X69EN!b8sU8xJ~#P<~Et@ yhbbWLG}lyRp%:d9XC<u_&wJ^7|}tb@!-NX_X#^:+8/_[&&9fX59{G~hzs_R+D-|kYm`<dd^NfqYW#DpeV6B&#UI_,+>^1Nfl[m]#pEJ^,mh(V4G71to,/p_~TNX,`Un(/Rg|?bNErVaX0-M7C]3)*?g(ov~V?03)=hg?nePb3<v(#p^iWkN|+xl>o:c2R/3$[r[]{]1(L5RXv0W>SFf;`]P3q&BuoNq/Dm/^s<=Pvd*7y`ocs+%3D6?+T~?%^2 YcH47<vm,J[{=$chv&+|^onEaEriv`t,YtI,OlnPVlx4m7_{q;#jk47G4&BwlQd:V&G)])`s2+yPso/pa6Aws[T`MNt?MA[vR X+r;k;IT)$BD8!B|`4}]q)v+?&Yz>Q/@8lY-15iLL&nFKs A`9_A:ij *n >d:aH6fiD#?H|L[g?BisSyMFOe1$YF}Z`=+|Ru/#~|?wP*qM$-*J F-R,RK+?wa;rIPBLv.#i%+W7b9jCZAFzxY%80`|ys6nzY4xjmTLJ3gH_wm+nuGnR@|Wzg',
            // 7
            null,
            // 8
            '2;4407617;4604473;10,13,0,0,1,0;C;HUOzt2I,s61ODUj9&Q#4zZ$A6mW*8*RTzs)ZWr7*k~.]%ET`n8D_RLf[2hif+:ofk?G+PNgRVm UoIzZ!L]{Gu@Xcimt9*O+w*9y1^1>)He1`5O./_u _a@-f^z&K+Oj{2EO}b/8+QhT ]F7q(`x8(_#+}iZwB)3jv;f<`EE`{/2AM+?bQ#w#0^1@jk&O}RxIh-9R61Q^-]P yqC^:[1BGkv!nytFaGrMh=AE5t|+j?!Z2ZM~Bmb-@!Xw$ThW.mC|-@/.w=42]?/ty_&vt,&Ok:6H>o+Cu/O@tDUqW5a#a,%(&{v><7I]Y}OcmK{d>.M9_k!7m,GVJz6w-){g!_HZ& u>Q4c2zaxZk*9PCL~F|dr_$o/$#99S,LD^8oOMy*ohH ?zit;pl.2.;rWZn<nxICA=OE}zuS;?i6P 1}X8mQgWN}19T5c-gijK rAF}#jJ}q9t{9e37-T@[!EN[AX(frWf6x=#nwC^lX3kqhsLG5aq@8F->3@pNpu0vVNm(2AIE1`Mmx_FHOo{*s5@mfs]3@ uAE<%Q097>X_V&4!rq(qzmQ+Dc|e1gO>z|Z-GALd|!|dP}.)w)3ZlTwak-NdX9@?z]cj9E^f/6M+wFqeMlwQn)$XlUZM|q1WDqYPl Vd52U<<^4$3Je)c:zWpb[/}8L2ym}-fxOWz(?1:_bHMzl!s?Cack9#)<iV7?E~I.eyO{S=TPdv{;]@:YZUA .-8>(Z]P[lgl[wl[JFmQmQ&ARZpjYWJ@KZ$rBgVW0N:RLkDFzRPMU$d2!RGgfvT^|H&whS_u.k})fdwx19hZ2[aw=[SmX,oiE@*n,ycqw.)bETY:}+_5s+W?Qx0kGeQaoCDIh)?BTtzjSsOe!K7?Y~~W}|Ujzrb.)hrX+Q1eaUOB-gTS(=hj$5uX7IK-/_pSoaMHTlh!|F.wRx]N6<=})kTfCLjmU+qr)).>#E-v~`95V_`|0>aUA-{Vm5>RVOE&{Wi>}o>lAgiP0tWz-/1[)a;;jM%Z#hR0c95X]%X1_Ls=H<Ejo.xiAh*@ CflyrM$<.?Ev A,{SLM+qE}SJF=78n*k:;fxh(ir^3kw,1^Ye1T!Q_|wjtK-)yKo=3b8kI5Uj=LW5r.t9pC`G3<):XXYf$-yVq@tM_.EW;@Z(oRgS~0[c/$/%g8PuyXpn^C<whkOc*Qv m$ehj@j*z{X(LFuib}nqnpP/`dKkf4^dUo`h0tO:Y=+#B8kz{FFdf{zA:v2X^d09kRx!(I!e=CE6ACAF|NkIOmF;D(vD@T#Y[fY=~Axkd&onOXfk{ykN6)ZW.sRx~-nW`V,>#F)Z$I DdIMPPEp_,:pnU?tDXjL*KQt>{J4jIi<AN@!>N+_OnB oRS*is$*e.5e&mH4fE Lcq`yj! =u#$CeGX~|jeN;^_M!1B,/.:>2)XXM4n;901i^sgM LmdcxhxN8KH&nsM>2`MTB_qFH|ER|T9[O}}ldM9{aPLfY/yhf6c6)$2q5U#l7<`J9br CiO8(t+e=N2KT15U!+o}w>Jeau`e%|PUzD;wB=A)yo%i?MQAnbV8}l>ge( S?zH}bX)5Y@.O]jA^4`@%[X:ci{V!@dioF+=;x{omsnY]rUU)vVf,+f^Z8<VR]R/YlMBP1EL:N{;5Q<x}8 DKltuuQ)pw4T_I+?cO6KRxkC[2oG&h@d0wexfMpPc3G#:T3}SjYA?yIV v@KG8~L*UP$5U(`7+/P0:G6f~VlxqBR!0KTiRLsI78ZiK`/3d*fw791[&&#.-hyH[,4:B>w>y>uz[-oL9c?dqQ2W+a1=M{p&2YIA)#<DC>>7k58^R,i/b`lK%(!LO*Q&Ix6_d_5l%2Z =#m!h<jj4+x3ZZExza)4^<S|yhP~<{LhVA=~qRJrKXxT5OGpEzM(JHd:5F*|1O%HI14yvEN(hn4~Z}x_%cC$`Ac9F6QB1pWX0kw5n]Sd=VF:3<-j0J}tEcif+t6W!<(&VBLz!Kx&5,xC0bpT1$ZyZPz_yfad I9yscwr#,-g]#NT@Z-o?BvlZV:o0_U]!PJ1/&xO_NMIFl<}Sr!vMM?A6$ePw6CU&AP.?MX[~v7~|MY55]GO78(R?M4+>#}p D2*D8ft=;ML=*Yql)2oJgP`ZcBAwI^jBo/~?:p%@Raa_@/<Q [MJMPiZlWO#fz(xWlSz6esIUyn|jHgoZ?.,4=[YedaE&6Ox^tqP.u5t,pdBD>_wSZic&u9=]SX,N|G-/.e<G+Bz9`v)zh~L9|)/PS%%MF]92V|qXv,PU_%[~IfzoX3UP-IOe}KY$/15vLjkey/gAo= M(GZPP(ZN1^Z3Ar!Hekm`.k[K#G~;dlJrIvBC8Woxq$e(U(Bu+P>=lHRTy8 N8%c>,:aE Z*)1Mj22KR~zvih7|4Ac)oBE~sR4`..7 dkn2Z3Oog<RZff{IVs@sf:6rtLCi:XWI5AFf(n80Q6T*950n/AiC5Z0ee@AlMauW<crEe]%eG+E}1C2mL]<e^O_FAkGhG^*pjpdx7 IM |0T;I7Y}Gnbk+kR+x{S8_{rm*@1I>&v[AvXt/<2XodAH<Q@RL??}Ikge1L9iSF3u U@LU.*>qdTRFnkt$v,2Q>dY_W;ltMPC7ql3SRP?``;N+zOc`B30d]c@;B0m=_b/$6[WV,c?Kvn}(?$AybUHWdiAuPAU3^`31G9sG[qs*zTC9mz:P`ggF};Kql6u$%2:|+tvVm@Q.2 j(4<.<a[aS6QeB~^#%yDBOM>(f(9&rqWRX Qr1_PvX@ani>Ix^1vw;;n~:* 7 bAm*,&VJGB,z8`[s/Y_|%O[Erype{s92HPZy*M+Hx*J7b0Ri8}t2Jh(JEcGqz91 6O-W<eCmAdzwt>viUisGShJqs#x}HuR}AN%[',
            // 9
            null,
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = $key;

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);

        if ($secondSensorData[$key]) {
            $sensorData = [
                'sensor_data' => $secondSensorData[$key],
            ];
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $this->http->JsonLog();
            sleep(1);
        }

        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);

        return $key;
    }
}
