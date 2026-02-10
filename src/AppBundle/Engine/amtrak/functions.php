<?php

// refs #2024, note#9

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAmtrak extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use OtcHelper;

    protected $parseTripCodes = false;

    private $datefilter = [',', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', '.'];

    private $jsonHeaders = [
        "Accept"           => "application/json, text/javascript, */*; q=0.01",
        "Accept-Encoding"  => "gzip, deflate, br",
        "Content-Type"     => "application/json",
        "X-B2C-Auth-Token" => '',
    ];
    private $accountNumber = null;
    private $itinsBatch = 50;
    private $itinError = null;

    private $sensorDataKey = null;
    private $access_token;

    private $history = [];
    private $currentItin = 0;

    public static function GetAccountChecker($accountInfo)
    {
        if (isset($accountInfo['State']['2fa']) && $accountInfo['State']['2fa'] == true) {
            require_once __DIR__ . "/TAccountCheckerAmtrakSelenium.php";

            return new TAccountCheckerAmtrakSelenium();
        }

        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if ($this->attempt == 0) {
            $this->http->SetProxy($this->proxyReCaptcha(), false);
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['X-B2C-Auth-Token'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful($this->State['X-B2C-Auth-Token']);
        $this->http->RetryCount = 2;

        return $result;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.amtrak.com/guestrewards/account-overview');

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $clientId = $this->http->FindPreg('/data-client-id-value="(.+?)"/');

        if (!$clientId) {
            return false;
        }

        $param = [];
        $param['client_id'] = $clientId;
        $param['scope'] = "openid offline_access email profile https://login.amtrak.com/api/profile.readwrite openid profile";
        $param['redirect_uri'] = "https://www.amtrak.com/home.html";
        $param['response_mode'] = "fragment";
        $param['response_type'] = "code";
        $param['code_challenge'] = "ReZByRfvIWapn8p98aPUh-a9bjED6gueJc5pw0a6foc";  // works in parse with code_verifier
        $param['code_challenge_method'] = "S256";

        $this->http->GetURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/b2c_1a_webapp_signin_signup/oauth2/v2.0/authorize?" . http_build_query($param));

        $stateProperties = $this->http->FindPreg('/"StateProperties=(.+?)",/');
        $csrf = $this->http->FindPreg('/"csrf":"(.+?)",/');

        if (!$stateProperties || !$csrf) {
            return $this->checkErrors();
        }

        $data = [
            "request_type" => "RESPONSE",
            "signInName"   => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $maintenance = $this->http->FindPreg('/"(​We are currently conducting system maintenance. You can complete a booking without logging in.)"/');
        $this->http->PostURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/B2C_1A_WebApp_Signin_Signup/SelfAsserted?tx=StateProperties={$stateProperties}&p=B2C_1A_WebApp_Signin_Signup", $data, $headers);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error($message);

                if (
                    $message === "Enter a valid Guest Rewards number or email address."
                    || $message === "We cannot find an account matching the email/Guest Rewards number."
                    || $message === "Your login information is not correct."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $status === "400"
                    && $message === "The username or password provided in the request are invalid."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message === "Password has invalid input.") {
                    throw new CheckException("Enter a valid password.", ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message === "Your login information is not correct. Use the Forgot Password option to unlock."
                    || $message === "Your account has been locked. Contact your support person to unlock it, then try again."
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if ($message === "This account is no longer active.") {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $status === "400"
                    && $message === "Use \"Forgot Password\" to reset your login and access your account."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $status === '400'
                    && $message === "We are unable to provide access to your account at this time. Please contact a representative for assistance at 1-800-307-5000."
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            } elseif ($this->http->Response['code'] == 500 && $maintenance) {
                throw new CheckException($maintenance, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $csrf;
        $param['p'] = "B2C_1A_WebApp_Signin_Signup";
        $this->http->GetURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/B2C_1A_WebApp_Signin_Signup/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->RetryCount = 2;

        $this->State['clientId'] = $clientId;
        $this->State['headers'] = $headers;
        $this->State['StateProperties'] = $stateProperties;

        return true;
    }

    public function getToken($code)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "content-type"    => "application/x-www-form-urlencoded;charset=utf-8",
        ];
        $data = [
            "client_id"     => $this->State['clientId'],
            "redirect_uri"  => "https://www.amtrak.com/progress.html",
            "scope"         => "openid offline_access email profile https://login.amtrak.com/api/profile.readwrite openid profile",
            "code"          => $code,
            "code_verifier" => "UyoWaPyM76Luf1kGoioGZz83lixfjYmoKCONa4UQ4ko", // works in parse with code_challenge
            "grant_type"    => "authorization_code",
        ];
        $this->http->PostURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/b2c_1a_webapp_signin_signup/oauth2/v2.0/token", $data, $headers);
    }

    public function ProcessStep($step)
    {
        $headers = $this->State['headers'];
        $stateProperties = $this->State['StateProperties'];
        $data = $this->State['data'];

        $data["VerificationCode"] = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->http->PostURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/B2C_1A_WebApp_Signin/SelfAsserted/DisplayControlAction/vbeta/emailVerificationControl/VerifyCode?tx=StateProperties={$stateProperties}&p=B2C_1A_WebApp_Signin", $data, $headers);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == 'Verification code does not match.'
                    || $message == 'Code has already been verified. Please request a new code.'
                ) {
                    $this->AskQuestion($this->Question, $message, "Question");

                    return false;
                }

                if ($message == 'You have exceeded the maximum time allowed.') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return $this->checkErrors();
        }

        $data["request_type"] = 'RESPONSE';
        $this->http->PostURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/B2C_1A_WebApp_Signin/SelfAsserted?tx=StateProperties={$stateProperties}&p=B2C_1A_WebApp_Signin", $data, $headers);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error($message);
            }

            return $this->checkErrors();
        }

        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = urldecode($this->http->getCookieByName("x-ms-cpim-csrf"));
        $param['tx'] = 'StateProperties=' . $stateProperties;
        $param['p'] = "B2C_1A_WebApp_Signin_Signup";
        $param['diags'] = '{"pageViewId":"5b010485-8643…us/ms-login.html","acST":1633601504,"acD":525},{"ac":"T019","acST":1633601504,"acD":4},{"ac":"T004","acST":1633601504,"acD":1},{"ac":"T003","acST":1633601506,"acD":1},{"ac":"T035","acST":1633601506,"acD":0},{"ac":"T030Online","acST":1633601506,"acD":0},{"ac":"T002","acST":1633601516,"acD":0}]}';
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/B2C_1A_WebApp_Signin/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->RetryCount = 2;

        $code = $this->http->FindPreg("/code=(.+?)$/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->error("code not found");

            return $this->checkErrors();
        }

        $this->getToken($code);

        $response = $this->http->JsonLog();
        $access_token = $response->access_token ?? null;

        if (
            $access_token
            && $this->loginSuccessful($access_token)
        ) {
            $this->State['X-B2C-Auth-Token'] = $access_token;

            return true;
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are currently conducting site maintenance.
        if ($message = $this->http->FindSingleNode('//h2[
                contains(text(), "We are currently conducting site maintenance.")
                or contains(text(), "Our services aren\'t available right now")
            ]')
            ?? $this->http->FindSingleNode("
                //p[contains(text(), 'The Amtrak.com reservation system is temporarily unavailable while we perform routine site maintenance.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently performing maintenance on our system
        if ($message = $this->http->FindPreg('/We are currently performing maintenance on our system, and will be up again shortly. Please come back soon./')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An unexpected error has occurred
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "It\'s unexpected, but not being ignored")]')) {
            throw new CheckException("An unexpected error has occurred. " . $message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error
        if ($message = $this->http->FindPreg('/Server\s*Error/ims')) {
            throw new CheckException('The service is unavailable.', ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, but we're having trouble signing you in.
        // We track these errors automatically, but if the problem persists feel free to contact us. In the meantime, please try again.
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Sorry, but we\'re having trouble signing you in.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Internal Server Error - Read")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $code = $this->http->FindPreg("/code=(.+?)$/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->error("code not found");

            $email = $this->http->FindPreg("/\"PRE\":\"([^\"]+)/");

            if (!$email) {
                return $this->checkErrors();
            }

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }
            $this->State['2fa'] = true;

            throw new CheckRetryNeededException(2, 3);
            $data = [
                "email" => $email,
            ];
            $this->http->PostURL("https://login.amtrak.com/amtrakb2c.onmicrosoft.com/B2C_1A_WebApp_Signin/SelfAsserted/DisplayControlAction/vbeta/emailVerificationControl/SendCode?tx=StateProperties={$this->State['StateProperties']}&p=B2C_1A_WebApp_Signin", $data, $this->State['headers']);
            $response = $this->http->JsonLog();
            $status = $response->status ?? null;

            if ($status !== "200") {
                $message = $response->message ?? null;

                if ($message) {
                    $this->logger->error($message);
                }

                return $this->checkErrors();
            }

            $this->State['data'] = $data;

            $this->Question = "A verification code has been sent to your email: $email.";
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return false;
        }

        $this->getToken($code);

        if (!$this->acceptTerms()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();
        $access_token = $response->access_token ?? null;

        if (
            $access_token
            && $this->loginSuccessful($access_token)
        ) {
            $this->State['X-B2C-Auth-Token'] = $access_token;

            return true;
        }

        $response = $this->http->JsonLog();
        // AccountID: 3116133
        if (
            isset($response->errors[0]->errorCode, $response->errors[0]->businessMessage)
            && $response->errors[0]->errorCode === '0000'
            && $response->errors[0]->businessMessage === "Something wrong with our System. Please try again"
        ) {
            throw new CheckException($response->errors[0]->businessMessage, ACCOUNT_PROVIDER_ERROR);
        }

        // We are unable to complete your request at this time
        if (
            $this->http->FindSingleNode('//td[contains(text(), "We are unable to complete your request at this time.")]')
        ) {
            $this->DebugInfo = "need to upd sensor_data (key {$this->sensorDataKey})";

            throw new CheckRetryNeededException(2, 7);
        }

        if (
            $this->http->FindPreg("/An error \(502 Bad Gateway\) has occurred in response to this request\./")
        ) {
            $this->DebugInfo = '502 Bad Gateway';

            throw new CheckRetryNeededException(3, 3);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $profileData = $this->http->JsonLog(null, 1);
        $firstName = $profileData->data->consumer->myProfile->consumerName->firstname ?? null;
        $lastName = $profileData->data->consumer->myProfile->consumerName->lastname ?? null;
        // Name
        $this->SetProperty('Name', beautifulName("{$firstName} {$lastName}"));
        // Member #
        $this->accountNumber = $profileData->data->consumer->myProfile->agrNumber ?? null;
        $this->SetProperty('Number', $this->accountNumber);
        // Tier Qualifying Points
        $this->SetProperty("TierQualifyingPoints", $profileData->data->consumer->myProfile->myAGRAccountInfo->tierQualifyingPoints ?? null);
        // Balance - Available Points
        $this->SetBalance($profileData->data->consumer->myProfile->myAGRAccountInfo->memberPointBalance ?? null);
        // Member since
        $since = $profileData->data->consumer->myProfile->agrEnrollmentDate ?? null;

        if ($since) {
            $this->SetProperty('MemberSince', str_replace('-', '/', $since));
        }
        // You need TQPs to next level
        $this->SetProperty('TQPsToNextLevel', $profileData->data->consumer->myProfile->myAGRAccountInfo->nextTierQualifyingPoints ?? null);
        // Status
        $tierName = $profileData->data->consumer->myProfile->myAGRAccountInfo->memberTierDescription ?? null;
        $this->SetProperty('Status', $tierName);
        // Tier Expiration Date
        if ($tierName && strtolower($tierName) !== 'member') {
            $this->SetProperty('TierExpDate', date("m/d/Y", strtotime($profileData->data->consumer->myProfile->myAGRAccountInfo->memberTierExpirationDate)));
        }

        $exp = $profileData->data->consumer->myProfile->myAGRAccountInfo->expirationDate ?? null;

        if ($exp && $exp != '') {
            $this->sendNotification("exp date was found");
        }

        $this->setBalanceExpiration();

        $this->parseCoupons();
    }

    public function ParseHistory($startDate = 0)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $res = [];

        $transactions = $this->history;

        if (empty($transactions)) {
            $transactions = $this->getHistoryData();
        }

        // transactions are in desc order
        foreach ($transactions as $trans) {
            $dateStr = ArrayVal($trans, 'transactionDate');
            $date = strtotime($dateStr);

            if ($date < $startDate) {
                $this->logger->notice("history break at date {$dateStr} ($date)");

                continue;
            }
            $type = ArrayVal($trans, 'transactionType');

            if ($type === 'EA') {
                $fullType = 'Adjustments';
            } elseif ($type === 'RT' || $type === 'RN') {
                $fullType = 'Redemption';
            } elseif ($type === 'EB') {
                $fullType = 'Travel Earning';
            } else {
                $fullType = 'Other Earning';
            }
            $desc = trim(ArrayVal($trans, 'title'));

            if (!$desc) {
                $desc = trim(ArrayVal($trans, 'description'));

                if (in_array($type, [
                    'EB',
                    'RT',
                    'RN',
                ])) {
                    if (isset($trans['travel']['originStation'], $trans['travel']['destinationStation'])) {
                        $desc = sprintf('%s (%s - %s)',
                            $desc, $trans['travel']['originStation'],
                            $trans['travel']['destinationStation']);
                    }
                }
            }

            $points = ArrayVal($trans, 'points', null); // Earned / Redeemed

            if ($fullType == 'Redemption') {
                $status = ArrayVal($trans, 'status');

                if ($status == 'Cancelled') {
                    $desc .= " (Cancelled)";
                    $points = 0;
                } elseif ($status == 'Credited') {
                    $desc .= " (Cancellation Credit)";
                }

                if ($points > 0) {
                    $points *= -1;
                }
            }

            $isTqps = ArrayVal($trans, 'tqp');
            $row = [
                'Transaction Date' => $date,
                'Type'             => $fullType,
                'Description'      => $desc,
                'Points'           => $points,
                'TQPs'             => $isTqps ? $points : null,
            ];
            $res[] = $row;
        } // foreach ($transactions as $trans)

        // Sort by date
        usort($res, function ($a, $b) {
            $key = 'Transaction Date';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        return $res;
    }

    public function GetHistoryColumns()
    {
        return [
            "Transaction Date" => "PostingDate",
            "Description"      => "Description",
            "Type"             => "Info",
            "TQPs"             => "Info",
            "Points"           => "Miles",
        ];
    }

    public function ParseItineraries()
    {
        $startTimer = $this->getTime();
        $itineraries = [];
        $this->http->GetURL("https://www.amtrak.com/home");

        if (!$this->http->FindPreg('/alt="(?:amtrak_logo|Amtrak)"/ims')) {
            return $itineraries;
        }

        $this->logger->debug('try the way with json. html is broken often');

        if (!empty($this->accountNumber)) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.amtrak.com/dotcom/consumers/mytrips?agrNumber={$this->accountNumber}",
                $this->jsonHeaders);
            $data = $this->http->JsonLog(null, 3, true);
            $this->http->RetryCount = 2;

            if ($this->http->Response['code'] == '422' && isset($data['errors'][0]['errorCode'])
                && $data['errors'][0]['errorCode'] === 'IS-0003'
                && $this->http->FindPreg('/^\{\s*"errors":\s*\[\s*\{\s*"errorCode": "IS-0003",\s*"errorType": "sys",\s*"sysError": "AMT-15003",\s*"sysMessage": "Non success response recieved from IdentitySvcs Process API - Response code 500 mapped as failure.",\s*"comments": "Backend Identity services error message",\s*"businessMessage": "We\'ve experienced a network connection issue. Please try again.",\s*"channelId": "DOTCOM",\s*"subChannelId": "5a414158",\s*"errorHeader": "",\s*"longBusinessMessage": ""\s*\}\s*\]\s*\}$/')
            ) {
                $this->logger->error($data['errors'][0]['sysMessage']);

                return [];
            }

            if (isset($data['data']) && isset($data['data']['totalResults'])) {
                $stations = $this->getStations();
                $this->parseItinerariesJson($data, $stations);

                return [];
            }
        }
        $this->http->GetURL("https://www.amtrak.com/guestrewards/account-overview/my-trips.html");
        $noActive = $this->http->FindPreg("/You don\'t have any active reservations made online/ims");
        $noCancelled = $this->http->FindPreg('#You don\'t have any canceled reservations available for review#i');

        if ($noActive and $noCancelled) {
            return $this->noItinerariesArr();
        }

        $canceledReservationNodes = $this->http->XPath->query('//div[@id="canceldetails"]/div');
        $this->logger->debug('Found ' . $canceledReservationNodes->length . ' canceled reservation(s)');

        if (!$noCancelled) {
            $i = 1;

            foreach ($canceledReservationNodes as $n) {
                $this->sendNotification("canceled itinerrefs #17693");
                $this->logger->debug('Parsing cancelled itinerary #' . $i . '/' . $canceledReservationNodes->length);
                $it = [
                    'Kind'         => 'T',
                    'TripCategory' => TRIP_CATEGORY_TRAIN,
                ];
                $s = $this->http->FindSingleNode('.//span[@class="tabbed_header_content"]', $n);

                if (preg_match('#^\s*(\w{6})\s*(Cancell?ed)\s*$#iu', $s, $m)) {
                    $it['RecordLocator'] = $m[1];
                    $it['Status'] = $m[2];
                    $it['Cancelled'] = true;
                }
                $routeNodes = $this->http->FindNodes('.//div[@class="route"]', $n);

                if (!$routeNodes) {
                    $this->logger->error('Failed to parse route');
                }
                $dateNodes = $this->http->FindNodes('.//div[@class="date"]', $n);

                if (!$dateNodes) {
                    $this->logger->error('Failed to parse dates');
                }

                if (count($routeNodes) == count($dateNodes)) {
                    for ($j = 0; $j < count($routeNodes); $j++) {
                        if (preg_match('#\(\s*(\w{3})\s*-\s*(\w{3})\s*\)#i', $routeNodes[$j], $m)) {
                            $it['TripSegments'][$j]['DepCode'] = $this->parseTripCodes ? $m[1] : TRIP_CODE_UNKNOWN;
                            $it['TripSegments'][$j]['ArrCode'] = $this->parseTripCodes ? $m[2] : TRIP_CODE_UNKNOWN;
                        }
                        $it['TripSegments'][$j]['DepDate'] = strtotime($dateNodes[$j]);
                    }

                    if (count($routeNodes) == 1) {
                        $s = $this->http->FindSingleNode('.//div[@class="station_name"]', $n);

                        if (preg_match('#(.*)\s*-\s*(.*)#i', $s, $m)) {
                            $it['TripSegments'][0]['DepName'] = $m[1];
                            $it['TripSegments'][0]['ArrName'] = $m[2];
                        }
                    }
                } else {
                    $this->logger->error('Count of dates do not match count of routes');
                }
                $this->http->Log('Result:');
                $this->http->Log(print_r($it, true));
                $itineraries[] = $it;
                $i++;
            }
        }

        $inputNames = $this->http->FindNodes('(//strong[contains(text(), "Reservation #")]/ancestor::div[contains(@class, "containter")])//input[@type = "submit"]/@name');
        $count = count($inputNames);
        $this->logger->debug('Total ' . $count . ' future reservation(s) were found');

        if (!$noActive) {
            if ($count > 0) {
                $i = 0;
                //$last = $this->getLastReservation($count);
                //$this->logger->info(sprintf('Parsing itineraries %s - %s', $last + 1, min($last + $this->itinsBatch, $count - 1)));

                foreach ($inputNames as $i => $name) {
                    if ($i >= $this->itinsBatch) {
                        $this->logger->debug("Save {$i} reservations");

                        break;
                    }
                    /*
                    if ($i <= $last) {
                        continue;
                    }
                    */
                    $this->logger->info(sprintf('Parsing current itinerary #%s (%s/%s)', $i, $i + 1, $count));
                    $payload = [
                        'requestor' => 'amtrak.presentation.handler.page.itinerary.AmtrakReservationsOverviewPageHandler',
                        $name       => ' VIEW/EDIT',
                    ];
                    $this->http->PostURL('https://tickets.amtrak.com/itd/amtrak', $payload);

                    if ($this->parseItinerary()) {
                    } else {
                        $this->logger->error('Current itinerary #' . $i . ' parsing failed');
                    }
                    /*
                    $this->State['LastReservation'] = $i;

                    if ($i - $last >= $this->itinsBatch) {
                        break;
                    }
                    */
                    if ($i % 5 === 0) {
                        $this->increaseTimeLimit(30);
                    }
                    sleep(2);
                }
            }
        }

        $this->getTime($startTimer);

        return $itineraries;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation Number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "Email" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.amtrak.com/home";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->removeCookies();
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->setCookie('CSRF-Token', 'undefined');
        $headers = [
            'Accept'            => 'application/json',
            'Accept-Encoding'   => 'gzip, deflate, br',
            'Content-Type'      => 'application/json',
            'Host'              => 'www.amtrak.com',
            'Origin'            => 'https://www.amtrak.com',
            'Referer'           => 'https://www.amtrak.com/home',
            'X-Amtrak-Trace-Id' => 'null',
        ];
        $payload = [
            'requestType' => 'PNR',
            'request'     => [
                [
                    'pnrNumber'       => $arFields['ConfNo'],
                    'email'           => $arFields['Email'],
                    'ecouponUpgrade'  => true,
                    'trainStatusInfo' => false,
                ],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.amtrak.com/v4/order/retrieve', json_encode($payload), $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 3, true);
        $businessMessage = $this->arrayVal($data, ['errors', 0, 'businessMessage']);

        if ($businessMessage) {
            return $businessMessage;
        }

        if (!isset($data['orders'])) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        if (count($data['orders']) > 1) {
            $this->sendNotification('orders > 1 // MI');
        }

        $reservationType = $this->arrayVal($data['orders'][0], ['reservationInfo', 'reservationType', 0]);

        if ($reservationType == 'NO_ACTIVE_SEGMENT') {
            return 'We are unable to retrieve your reservation at this time.';
        }
        $this->parseItineraryRetrieve($data['orders'][0]);

        return null;
    }

    private function loginSuccessful($access_token)
    {
        $this->logger->notice(__METHOD__);
        // new auth
        $this->jsonHeaders["X-B2C-Auth-Token"] = $access_token;

        $agrNumber = null;

        foreach (explode('.', $access_token) as $str) {
            $str = base64_decode($str);
            $this->logger->debug($str);

            if ($agrNumber = $this->http->FindPreg('/"extension_agrNumber":"(.+?)"/', false, $str)) {
                break;
            }
        }

        if ($agrNumber === null) {
            $this->logger->error("agrNumber not found");

            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.amtrak.com/dotcom/consumers/profile?agrNumber=' . $agrNumber, $this->jsonHeaders);
        $this->http->RetryCount = 2;
        $profileData = $this->http->JsonLog(null, 3, false, 'memberPointBalance');
        $accountNumber = $profileData->data->consumer->myProfile->agrNumber ?? null;

        $myEmailAddress = $profileData->data->consumer->myProfile->myEmailAddress ?? [];

        $email = null;

        foreach ($myEmailAddress as $emailAddress) {
            if ($emailAddress->primary == false) {
                continue;
            }

            $email = $emailAddress->emailId ?? null;
        }

        $this->logger->debug("[Number]: {$accountNumber}");
        $this->logger->debug("[Email]: {$email}");

        if (
            $accountNumber
            && (
                ($accountNumber == $this->AccountFields['Login'])
                || ($email && strtolower($email) == strtolower($this->AccountFields['Login']))
                || ($email && strtolower($email) == 'mpherz@icloud.com' && 'mitch.herz@gmail.com' == strtolower($this->AccountFields['Login']))// AccountID: 2982547
                || ($email && strtolower($email) == 'michellecville@gmail.com' && 'michellemcclelland22851@gmail.com' == strtolower($this->AccountFields['Login']))// AccountID: 3833480
                || ($email && strtolower($email) == '7161479303@amtrakguestrewards.com' && 'etegels@gmail.com' == strtolower($this->AccountFields['Login']))// AccountID: 3833234
            )
        ) {
            $this->accountNumber = $accountNumber;

            return true;
        }

        return false;
    }

    private function acceptTerms()
    {
        $this->logger->notice(__METHOD__);
        // By signing in to your account you acknowledge you have read and agree
        if ($this->http->getCookieByName('TCRememberLoginCookie')
            && $this->http->FindPreg('/asctev=true$/', false, $this->http->currentUrl())) {
            if (!$this->http->ParseForm('login')) {
                return false;
            }

            $this->http->SetInputValue('_cmstcversionnumber_login', '2.7.2');
            $this->http->SetInputValue('_cmsaccepttc_login', 'true');

            if (!$this->http->PostForm()) {
                return false;
            }
        }

        return true;
    }

    private function getHistoryData()
    {
        $this->logger->notice(__METHOD__);
        $fromDate = date('Y-m-d', strtotime('-2 years', strtotime('now')));
        $toDate = date('Y-m-d', strtotime('now'));
        $this->http->GetURL("https://www.amtrak.com/dotcom/member/transactions?agrNumber={$this->accountNumber}&startDate={$fromDate}T00%3A00%3A00%2B05%3A00&endDate={$toDate}T23%3A59%3A59%2B05%3A00", $this->jsonHeaders);
        $transactionData = $this->http->JsonLog(null, 0, true);

        if (!is_null($transactionData) && empty(ArrayVal($transactionData, 'transactions', []))) {
            $fromDate = date('Y-m-d', strtotime('-5 years', strtotime('now')));
            $this->http->GetURL("https://www.amtrak.com/dotcom/member/transactions?agrNumber={$this->accountNumber}&startDate={$fromDate}T00%3A00%3A00%2B05%3A00&endDate={$toDate}T23%3A59%3A59%2B05%3A00", $this->jsonHeaders);
            $transactionData = $this->http->JsonLog(null, 0, true);
        }

        if (is_null($transactionData)) {
            $this->logger->error('invalid json retry');
            sleep(2);
            $this->http->GetURL("https://www.amtrak.com/dotcom/member/transactions?agrNumber={$this->accountNumber}&startDate={$fromDate}T00%3A00%3A00%2B05%3A00&endDate={$toDate}T23%3A59%3A59%2B05%3A00", $this->jsonHeaders);
            $transactionData = $this->http->JsonLog(null, 0, true);
        }

        if (is_null($transactionData)) {
            $maintenance = $this->http->FindSingleNode("//h2[contains(text(), 'We are currently conducting site maintenance.')]");

            if (!$maintenance) {
                $this->sendNotification('check amtrak history');
            }

            return [];
        }

        return ArrayVal($transactionData, 'transactions', []);
    }

    // refs #12609
    private function setBalanceExpiration(): void
    {
        $this->logger->info("Expiration date", ['Header' => 3]);

        if ($this->Balance <= 0) {
            $this->logger->info('No exp on zero/negative balance');

            return;
        }

        $this->history = $this->getHistoryData();

        foreach ($this->history as $trans) {
            $dateStr = ArrayVal($trans, 'transactionDate');
            $expirationDate = strtotime($dateStr);

            if (!isset($exp) || $expirationDate > $exp) {
                $exp = $expirationDate;
                $this->SetProperty("LastActivity", date("m/d/Y", $exp));
            }
        }

        if (!isset($expirationDate, $exp)) {
            $this->logger->info('No exp date found');

            return;
        }

        $expDate = strtotime("+24 month", $exp);

        if ($expDate < 1631664000 /*"Sep 15, 2021"*/) {
            $this->logger->notice("extending exp date by provider rules");
            $expDate = 1631664000;
        }

        $this->SetExpirationDate($expDate);
    }

    private function parseCoupons()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Coupons", ['Header' => 3]);
        $this->http->PostURL('https://www.amtrak.com/dotcom/ecoupon/list', '{"eCouponRQ":{"partnerID":"5a414158","partnerName":"INTERNET","operation":"list","agrNumber":"' . $this->accountNumber . '"}}', $this->jsonHeaders);
        $couponData = $this->http->JsonLog(null, 0);

        if (is_null($couponData)) {
            return;
        }
        $eCoupons = $couponData->eCouponRS->eCoupons ?? [];

        $this->SetProperty("CombineSubAccounts", false);
        $this->logger->debug(sprintf("Total %s coupons were found", count($eCoupons)));

        foreach ($eCoupons as $coupon) {
            $code = $coupon->couponCode;
            $name = $coupon->couponName;
            $uses = intval($coupon->useLimit) - intval($coupon->useCount);
            $exp = $coupon->endDate;

            if ($uses === 0) {
                $this->logger->debug("skip used coupon -> {$code} / {$name}");

                continue;
            }

            if (strtotime($exp) < strtotime('now')) {
                $this->logger->debug("skip expired coupon -> {$code} / {$name}");

                continue;
            }

            $subAccount = [
                "Code"           => 'amtrakCoupon' . $code,
                "DisplayName"    => $name . ' #' . $code,
                "Balance"        => $uses,
                'Number'         => $code,
                'ExpirationDate' => strtotime($exp, false),
            ];
            $this->AddSubAccount($subAccount);
        }
    }

    private function checkItineraryError()
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindPreg('/Problem retrieving your reservation/ims')) {
            $this->logger->error($msg);
            $this->itinError = $msg;

            return true;
        }
        $errors = $this->http->FindNodes('//div[@id = "amtrak_error_id" and contains(@class, "error")]');

        if ($errors) {
            $nonEmpty = false;
            $this->itinError = '';

            foreach ($errors as $error) {
                if (trim($error)) {
                    $this->logger->error($error);
                    $this->itinError = sprintf('%s%s ', $this->itinError, $error);
                    $nonEmpty = true;
                }
            }

            if ($nonEmpty) {
                return true;
            }
        }

        if ($msg = $this->http->FindSingleNode('//font[contains(text(), "Our Monitoring System has detected an unusual number of requests coming from your location.")]')) {
            $this->logger->error($msg);
            $this->itinError = $msg;
            sleep(2);

            return true;
        }

        return false;
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);

        if ($this->checkItineraryError()) {
            return false;
        }

        $train = $this->itinerariesMaster->add()->train();
        // RecordLocator
        $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Details')]", null, true, "#\s+([A-Z\d]{5,})$#");
        $this->logger->info('Parse Itinerary #' . $conf, ['Header' => 3]);
        $train->addConfirmationNumber($conf, 'Reservation #', true);
        /** @var DOMNodeList $tsNodes */
        $tsNodes = $this->http->XPath->query('//div[contains(@class, "content1")]/ancestor::div[contains(@class, "content_area")]');

        if ($tsNodes->length === 0) {
            $this->http->ParseForm('form');
            $name = '_handler=amtrak.presentation.handler.request.itinerary.PresAmtrakModifyReservationRequestHandler/_xpath=/sessionWorkflow/itineraryWorkflow/basket/purchase/requirements';

            if (!$this->http->FindNodes(sprintf('//input[@name = "%s"]', $name))) {
                $this->itinerariesMaster->removeItinerary($train);
                $this->logger->error('Skipping: itinerary modify button not found');

                return false;
            }
            $this->http->SetInputValue($name, ' Modify Trip');
            $this->http->PostForm();
            $tsNodes = $this->http->XPath->query('//div[contains(@class, "content_area")]');
        }
        $i = 0;

        while ($i < $tsNodes->length) {
            $seg = $train->addSegment();
            $year = $this->http->FindSingleNode("div[contains(@class, 'content1')]/descendant::text()[normalize-space(.)!=''][2]", $tsNodes->item($i), true, "/\w+\s+\d+,\s+(\d{4})/");

            if (!$year) {
                $year = $this->http->FindSingleNode("div[contains(@class, 'content1')]/descendant::text()[normalize-space(.)!=''][1]", $tsNodes->item($i), true, "/\w+\s+\d+,\s+(\d{4})/");
            }
            // FlightNumber
            $seg->setNumber($this->http->FindSingleNode('.//div[contains(@class, "content1")]//div[contains(@class, "heading-4")]', $tsNodes->item($i), true, '/^(\d+)/ims'));
            // AirlineName
            $seg->setServiceName($this->http->FindSingleNode('.//div[contains(@class, "content1")]//div[contains(@class, "heading-4")]', $tsNodes->item($i), true, '/^\d+\s(.*)/ims'), false, true);
            // Duration
            $seg->setDuration($this->http->FindSingleNode('.//div[contains(@class, "content1")]//h6', $tsNodes->item($i)));
            // DepName
            $depName = $this->http->FindSingleNode("div[contains(@class, 'content2')]/descendant::text()[normalize-space(.)!=''][1]", $tsNodes->item($i));
            $depNameStart = preg_quote($depName);
            $points = $this->http->FindSingleNode("./preceding::div[1]/descendant::text()[normalize-space()!=''][1]", $tsNodes->item($i), false, "/^{$depNameStart}.+/iu");
            $this->logger->debug($points);

            if (!empty($points) && ($depNameExt = $this->http->FindPreg("/^({$depNameStart}.*?)\s+to\s+/iu", false, $points))) {
                $seg->setDepName($depNameExt);
            } else {
                $seg->setDepName($depName);
            }
            // DepDate
            $depDate = strtotime(str_replace($this->datefilter, '', $this->http->FindSingleNode("div[contains(@class, 'content2')]/descendant::text()[normalize-space(.)!=''][3]", $tsNodes->item($i))) . ' ' . $year);

            if ($depDate !== false) {
                $seg->setDepDate(strtotime($this->http->FindSingleNode("div[contains(@class, 'content2')]/descendant::text()[normalize-space(.)!=''][2]", $tsNodes->item($i)), $depDate));
            }
            // ArrName
            $arrName = $this->http->FindSingleNode("div[contains(@class, 'content3')]/descendant::text()[normalize-space(.)!=''][1]", $tsNodes->item($i));
            $arrNameStart = preg_quote($arrName);

            if (!empty($points) && ($arrNameExt = $this->http->FindPreg("/\s+to\s+({$arrNameStart}.*)/iu", false, $points))) {
                $seg->setArrName($arrNameExt);
            } else {
                $seg->setArrName($arrName);
            }
            // ArrDate
            $arrDate = strtotime(str_replace($this->datefilter, '', $this->http->FindSingleNode("div[contains(@class, 'content3')]/descendant::text()[normalize-space(.)!=''][3]", $tsNodes->item($i))) . ' ' . $year);

            if ($arrDate !== false) {
                $seg->setArrDate(strtotime($this->http->FindSingleNode("div[contains(@class, 'content3')]/descendant::text()[normalize-space(.)!=''][2]", $tsNodes->item($i)), $arrDate));
            }
            $i++;

            if ($seg->getDepName() == $seg->getArrName() && $seg->getServiceName() == 'Self Transfer') {
                $this->logger->notice("[{$seg->getNumber()}]: skip 'Self Transfer' from '{$seg->getDepName()}' to '{$seg->getArrName()}'");
                $train->removeSegment($seg);
            }
        }
        // TotalCharge and Currency
        if ($totalStr = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Purchase Total')][1]/following::text()[normalize-space(.)!=''][1]")) {
            $total = $this->http->FindPreg('/([\d.,]+)/', false, $totalStr);
            $train->price()->total(PriceHelper::cost($total));
            $train->price()->currency($this->currency($totalStr));
        }

        if ($points = $this->http->FindSingleNode("//div[contains(text(), 'Points Redeemed:')]/span")) {
            $train->price()->spentAwards($points);
        }
        // Passengers
        $names = $this->http->FindNodes("//*[@id = 'confirmation_passenger']/following-sibling::div[1]//span[contains(@class, 'itinerary__passenger-name')]");

        if (!$names) {
            $names = $this->http->FindNodes("//*[@id = 'confirmation_passenger']/following-sibling::div[1]", null, '/^([\s\w]+)/i');
        }
        $names = array_map(function ($item) {
            return trim(beautifulName($item));
        }, $names);

        if ($names) {
            $train->setTravellers($names);
        }

        $numbers = $this->http->FindNodes("//*[@id = 'confirmation_passenger']/following-sibling::div[1]//span[contains(@class, 'itinerary__passenger-reward')]", null, "/#(\d+)/");

        if ($numbers) {
            $train->setAccountNumbers($numbers, false);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);

        return true;
    }

    private function getStations(): array
    {
        $this->logger->notice(__METHOD__);
        $result = Cache::getInstance()->get('amtrak_stations_list');

        if (!empty($result)) {
            return $result;
        }
        $this->http->GetURL("https://www.amtrak.com/services/data.stations.json");
        $stations = $this->http->JsonLog(null, 0);
        $result = [];

        foreach ($stations as $station) {
            $result[$station->code] = $station->autoFillName;
        }
        Cache::getInstance()->set('amtrak_stations_list', $result, 60 * 60 * 24);

        return $result;
    }

    private function parseItinerariesJson(array $data, array $stations)
    {
        $this->logger->notice(__METHOD__);

        if (($cntRes = $this->arrayVal($data, ['data', 'totalResults'])) == 0) {
            $this->itinerariesMaster->setNoItineraries(true);

            return;
        }

        $cntSkipped = 0;

        foreach ($data['data']['trips'] as $trip) {
            if (!$this->ParsePastIts && strtotime($trip['serviceDate']) < strtotime(date("Y-m-d"))) {
                $this->logger->debug("Skip past itinerary: " . $trip['pnrNumber']);
                $cntSkipped++;

                continue;
            }

            if ($trip['reservationType'] === 'MLT') {
                $this->logger->debug("Skip Multi-Ride itinerary: " . $trip['pnrNumber']);
                $cntSkipped++;

                continue;
            }

            if (!isset($trip['segInfos']) && $trip['segCnt'] === 0) {
                $this->logger->debug("Skip no segments: " . $trip['pnrNumber']);
                $cntSkipped++;

                continue;
            }
            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $trip['pnrNumber']), ['Header' => 3]);

            $r = $this->itinerariesMaster->add()->train();
            $r->general()
                ->confirmation($trip['pnrNumber'])
                ->date(strtotime($trip['pnrCreationDate']));

            if (!empty($trip['travelerDetails'])) {
                $r->general()->traveller(beautifulName($trip['travelerDetails']), true);
            }

            if ($trip['pnrCancelStatus'] == true) {
                $r->general()
                    ->status('Cancelled')
                    ->cancelled();
            }

            if (isset($trip['segInfos'])) {
                foreach ($trip['segInfos'] as $segInfo) {
                    if ($segInfo['scheduleDepartureTime'] == $segInfo['scheduleArrivalTime']) {
                        $this->logger->debug('Skip: dates are identical');

                        continue;
                    }
                    $s = $r->addSegment();

                    if (!empty($segInfo['trainNum'])) {
                        $s->extra()
                            ->number($segInfo['trainNum']);
                    }
                    $s->extra()
                        ->service($segInfo['routeName'], true, false);

                    $s->departure()->code($segInfo['origin']);
                    $s->departure()->geoTip('US');

                    if (!empty($stations[$segInfo['origin']])) {
                        $station = trim(preg_replace('/\([A-Z]{3}\)\s*$/', '', $stations[$segInfo['origin']]));
                        $s->departure()->name($station);
                    }

                    $s->arrival()->code($segInfo['destination']);
                    $s->arrival()->geoTip('US');

                    if (!empty($stations[$segInfo['destination']])) {
                        $station = trim(preg_replace('/\([A-Z]{3}\)\s*$/', '', $stations[$segInfo['destination']]));
                        $s->arrival()->name($station);
                    }

                    $s->departure()->date(strtotime($segInfo['scheduleDepartureTime']));
                    $s->arrival()->date(strtotime($segInfo['scheduleArrivalTime']));
                }
            }// if (isset($trip['segInfos']))

            $s = $r->getSegments();

            if (empty($s)) {
                $this->logger->error('Skip: empty segment');
                $this->itinerariesMaster->removeItinerary($r);

                continue;
            }

            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
        }

        if ($cntSkipped == $cntRes && count($this->itinerariesMaster->getItineraries()) === 0) {
            $this->itinerariesMaster->setNoItineraries(true);
        }
    }

    private function getLastReservation($count)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['LastReservation'])) {
            return -1;
        }

        $last = $this->State['LastReservation'];
        $this->logger->notice("Last reservation: {$last} of {$count}");

        if ($last >= $count - 1) {
            return -1;
        }

        return $last;
    }

    private function parseItineraryRetrieve($data)
    {
        $this->logger->notice(__METHOD__);
        $train = $this->itinerariesMaster->createTrain();
        // confirmation number
        $conf = $this->arrayVal($data, ['reservationInfo', 'pnrNumber']);
        $train->addConfirmationNumber($conf, 'Reservation', true);
        $this->logger->info("Parse Train #{$conf}", ['Header' => 3]);
        // total
        $train->price()->total($this->arrayVal($data, ['fareAmounts', 'total']));
        // currency
        $pricingUnit = $this->arrayVal($data, ['fareAmounts', 'pricingUnit']);

        if ($pricingUnit === 'DOLLARS') {
            $train->price()->currency('USD');
        }
        // travellers
        $passengers = $this->arrayVal($data, 'passengers', []);

        foreach ($passengers as $passenger) {
            $firstName = $this->arrayVal($passenger, ['passengerInfo', 'name', 'firstName']);
            $lastName = $this->arrayVal($passenger, ['passengerInfo', 'name', 'lastName']);
            $train->addTraveller(trim(beautifulName("{$firstName} {$lastName}")));
            $loyaltyProgram = $this->arrayVal($passenger, 'loyaltyProgram', []);

            foreach ($loyaltyProgram as $program) {
                if ($this->arrayVal($program, 'programName') === 'Amtrak Guest Rewards') {
                    $train->addAccountNumber($this->arrayVal($program, 'programId'), false);
                }
            }
        }
        // segments
        $journeyLegs = $this->arrayVal($data, ['journeys', 'journeyLegs'], []);

        foreach ($journeyLegs as $journeyLeg) {
            $travelLegs = $this->arrayVal($journeyLeg, 'travelLegs', []);

            foreach ($travelLegs as $leg) {
                $seg = $train->addSegment();
                $seg->setDepName($this->arrayVal($leg, ['fromStation', 'stationName']));
                $seg->setDepGeoTip('US');
                $seg->setArrName($this->arrayVal($leg, ['toStation', 'stationName']));
                $seg->setArrGeoTip('US');
                $seg->setDepCode($this->arrayVal($leg, ['fromStation', 'stationCode']));
                $seg->setArrCode($this->arrayVal($leg, ['toStation', 'stationCode']));

                // dep date
                $dt1 = $this->arrayVal($leg, ['fromStation', 'scheduleTime'], '');
                $seg->setDepDate(strtotime($dt1));
                // arr date
                $dt2 = $this->arrayVal($leg, ['toStation', 'scheduleTime'], '');
                $seg->setArrDate(strtotime($dt2));
                // train type
                $seg->setTrainType($this->arrayVal($leg, ['travelService', 'trainType']));
                // number
                $seg->setNumber($this->arrayVal($leg, ['travelService', 'trainNo']));
                // cabin
                $rbdCount = $this->arrayVal($leg, ['RBD', 0, 'count']);
                $rbdType = $this->arrayVal($leg, ['RBD', 0, 'rbdType']);

                if ($rbdCount && $rbdType) {
                    $seg->setCabin("{$rbdCount} {$rbdType}");
                }
                // duration
                $dur = $this->http->FindPreg('/(\d+H\d+M)/', false, $this->arrayVal($leg, 'duration', ''));

                if ($dur) {
                    $seg->setDuration($dur);
                }
            }
        }

        $this->logger->debug('Parsed Train:');
        $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        if (!is_array($indices)) {
            $indices = [$indices];
        }
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }
}
