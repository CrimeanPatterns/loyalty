<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAeroplan extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $isSelenium = true;
    private $www = 'www';
    private $gigyaHost = 'https://login.aircanada.com';
    private $sensor_data = '';
    private $sensorDataURL = null;
    private $key = null;
    private $userAgent;
    private $userAgentAltitude;

    private $apikey = '3_zA5TRSBDlwybsx_1k8EyncAfJ2b62DJnoxPW60q4X9MqmBDJh1v_8QYaOTG8kZ8S';
    private $sdk = "js_latest";
    private $pageURL = 'https://www.aircanada.com/clogin/pages/login';

    private $collectedHistory = false;

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerAeroplanSelenium.php";

            return new TAccountCheckerAeroplanSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->KeepState = true;
        // todo: do not hide our user-agent https://redmine.awardwallet.com/issues/7217
//        $this->http->SetProxy($this->proxyDOP(), false);//todo
//        $this->http->setDefaultHeader('User-Agent','AwardWallet.com web crawler (http://awardwallet.com/contact.php)');
    }

    public function IsLoggedIn()
    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL("https://altitude.aircanada.com/mystatus/dashboard", [], 20);
//        $this->http->RetryCount = 2;
//        if ($this->loginSuccessful()) {
//            return true;
//        }

        return false;
    }

    public function LoadLoginForm()
    {
//        $this->http->GetURL('https://www.aircanada.com/ca/en/aco/home.html');
//        $this->http->GetURL('https://www.aircanada.com/clogin/pages/login');
//        $this->http->GetURL('https://www.aircanada.com/aeroplan/member/dashboard?lang=en-CA');
        $this->http->GetURL('https://www.aircanada.com/ca/en/aco/home.html');
        $this->http->setCookie('geoLocation', 'CA', '.aircanada.com');
        $this->http->setCookie('country_pref', 'CA', '.aircanada.com');
        $this->http->setCookie('aco_siteLocale', 'en_CA', '.aircanada.com');

        $this->http->setCookie("apiDomain_3_zgPFKZjPfWFU0BCP0pNeHYjpeFa3eQFDEatDGRwnxCzfE7eVH0yKh_XXSf34Tlux", "login.aircanada.com", '.aircanada.com');
        $this->http->setCookie("gig_bootstrap_{$this->apikey}", "login_ver3", '.aircanada.com');

        sleep(2);
        $this->http->GetURL('https://auth.api-gw.dbaas.aircanada.com/oauth2/authorize?redirect_uri=https%3A%2F%2Fwww.aircanada.com%2Fcontent%2Faircanada%2Fca%2Fen%2Faco%2Fhome%2Foidc-redirect.html&response_type=code&client_id=5put0po1jqtrfi4k9fm43roflg&identity_provider=Gigya&scopes=openid%2Cprofile&state=TuD2JJ2L8nJ9bKxn3iTFq7oHfJp6piJz-___early&code_challenge=Vxfc1bKCTY61AF_GTtgrxULPKRTA_TCs86jloQGagss&code_challenge_method=S256');
        //$this->http->GetURL('https://auth.api-gw.dbaas.aircanada.com/oauth2/authorize?redirect_uri=https%3A%2F%2Fwww.aircanada.com%2Faeroplan%2Fmember%2Fredirect&response_type=code&client_id=5put0po1jqtrfi4k9fm43roflg&identity_provider=Gigya&scopes=openid%2Cprofile&state=G4jt3XgllAU1q58LySiVDPaqydcGTxnQ-dashboard%3Flang%3Den-CA&code_challenge=bAeHJgz00rDTtGkn1sKwXhgEDPkLuBSgmKZOrgw3uu8&code_challenge_method=S256');

        $this->apikey = $this->http->FindPreg("/apiKey=([^\&\"\']+)/");
        $context = $this->http->FindPreg("/context=([^\&\"\']+)/", false, $this->http->currentUrl());

        if ($this->http->Response['code'] != 200 || !$this->apikey || !$context) {
            /*
            // retries
            if ($this->http->Response['code'] == 403) {
                throw new CheckRetryNeededException(3);
            }
            */
            return $this->checkErrors();
        }

        if ($sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#")) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->sensorDataURL = $sensorPostUrl;
            $this->logger->notice('sensorPostUrl -> ' . $this->sensorDataURL);
        }

        $this->AccountFields['Login'] = str_replace(" ", "", $this->AccountFields['Login']);

        $this->State['context'] = $context;

        $param = [];
        $param['apiKey'] = $this->apikey;
        $param['pageURL'] = 'https://www.aircanada.com/';
        $param['sdk'] = $this->sdk;
        $param['format'] = "json";
        // setCookie gmid, ucid +1 year
        $this->http->GetURL("{$this->gigyaHost}/accounts.webSdkBootstrap?" . http_build_query($param));
        $this->http->JsonLog();

        $this->http->Form = [];
        $this->http->FormURL = "{$this->gigyaHost}/accounts.login";
        $this->http->SetInputValue('loginID', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('sessionExpiration', "7200");
        $this->http->SetInputValue('targetEnv', "jssdk");
        $this->http->SetInputValue('include', "profile,data,emails,subscriptions,preferences,");
        $this->http->SetInputValue('includeUserInfo', "true");
        $this->http->SetInputValue('loginMode', "standard");
        $this->http->SetInputValue('lang', "en");
        $this->http->SetInputValue('APIKey', $this->apikey);
        $this->http->SetInputValue('source', "showScreenSet");
        $this->http->SetInputValue('sdk', $this->sdk);
        $this->http->SetInputValue('authMode', "cookie");
        $this->http->SetInputValue('pageURL', $this->pageURL);
        $this->http->SetInputValue('format', "json");

        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 403) {
            $retry = false;
            $this->State['sensorDataURL'] = $this->sensorDataURL;
            $key = $this->sendStaticSensorDataAltitude($this->sensorDataURL);

            if ($this->http->Response['code'] == 403) {
                $this->sendStatistic(false, $retry, $key);
            } else {
                $this->sendStatistic(true, $retry, $key);
            }

            $this->http->FormURL = $formURL;
            $this->http->Form = $form;
            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;
        }

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($message = $response->errorDetails ?? null) {
            $errorMessage = $response->errorMessage ?? null;
            $this->logger->error("[errorDetails]: {$message}");
            $this->logger->error("[errorMessage]: {$errorMessage}");
            /*
            if (
                $message == 'invalid loginID or password'
                || $message == 'Account Disabled'
                || ($message == 'Error from extension' && $errorMessage == 'Invalid parameter value')
            ) {
                throw new CheckException("Invalid Aeroplan number/email and/or password. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }
            if ($message == 'Old Password Used') {
                throw new CheckException("If you haven't logged in since September 10th 2019, you will need to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }
            if (
                $message == 'Registration was not finalized'
                || $message == 'Account Pending Verification'
                || ($message == 'Missing required fields for registration: email' && $errorMessage == 'Account Pending Registration')
            ) {
                throw new CheckException("We can't verify the credentials you entered. Please login on Aeroplan.com and follow the instructions to confirm your information.", ACCOUNT_INVALID_PASSWORD);
            }
            if ($message == 'Account temporarily locked out') {
//                throw new CheckException('Your account has been locked for security reason.', ACCOUNT_LOCKOUT);
                throw new CheckRetryNeededException();
            }

            // wrong error: If you haven't logged in since September 10th 2019, you will need to reset your password.
            if ($message == 'Invalid argument: saveResponseID' && $errorMessage == 'Invalid parameter value') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            */

            if ($errorMessage == 'Account Pending Registration') {
                $this->throwProfileUpdateMessageException();
            }

            if (
                $message == 'Pending Two-Factor Authentication'
                && $errorMessage == 'Account Pending TFA Verification'
            ) {
                if (!isset($response->regToken)) {
                    $this->logger->error("regToken not found");

                    return false;
                }

                $param = [];
                $param['regToken'] = $response->regToken;
                $param['APIKey'] = $this->apikey;
                $param['source'] = "showScreenSet";
                $param['sdk'] = $this->sdk;
                $param['pageURL'] = $this->pageURL;
                $param['format'] = "json";
                $this->http->GetURL("{$this->gigyaHost}/accounts.tfa.getProviders?" . http_build_query($param));
                $this->http->JsonLog();

                if ($this->parseQuestion($response)) {
                    return false;
                }
            }

            return false;
        }

        // TODO
        if (isset($response->profile->email) && filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false
            && strtolower($this->AccountFields['Login']) != strtolower($response->profile->email)) {
            $this->sendNotification("User data bug 4 // MI");

            return false;
        }

        if (isset($response->UID, $response->UIDSignature)) {
            return $this->authComplete($response);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "content-type"    => "application/json",
        ];
        $this->http->PostURL("https://akamai-gw.dbaas.aircanada.com/loyalty/profile/getProfileKilo?profiletype=complete", [], $headers);
        $response = $this->http->JsonLog();
    }

    public function ProcessStep($step)
    {
        $this->http->setHttp2(true);
        $param = [];
        $param['gigyaAssertion'] = $this->State['gigyaAssertion'];
        $param['phvToken'] = $this->State['phvToken'];
        $param['code'] = $this->Answers[$this->Question];
        $param['regToken'] = $this->State['regToken'];
        $param['APIKey'] = $this->apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = $this->sdk;
        $param['pageURL'] = $this->pageURL;
        $param['format'] = "json";
        $this->http->GetURL("{$this->gigyaHost}/accounts.tfa.email.completeVerification?" . http_build_query($param));
        $completeVerification = $this->http->JsonLog();
        unset($this->Answers[$this->Question]);
        // Wrong verification code
        if (isset($completeVerification->errorMessage) && $completeVerification->errorMessage == 'Invalid parameter value') {
            $this->AskQuestion($this->Question, 'Wrong verification code');

            return false;
        }
        // Maximum allowed tries exceeded
        if (isset($completeVerification->errorDetails) && $completeVerification->errorDetails == 'Maximum allowed tries exceeded') {
            throw new CheckException('Wrong verification code. Maximum allowed tries exceeded. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }

        if (!isset($completeVerification->providerAssertion)) {
            return false;
        }

        $param = [];
        $param['gigyaAssertion'] = $this->State['gigyaAssertion'];
        $param['providerAssertion'] = $completeVerification->providerAssertion;
        $param['tempDevice'] = false;
        $param['regToken'] = $this->State['regToken'];
        $param['APIKey'] = $this->apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = $this->sdk;
        $param['pageURL'] = $this->pageURL;
        $param['format'] = "json";
        $this->http->GetURL("{$this->gigyaHost}/accounts.tfa.finalizeTFA?" . http_build_query($param));
        $response = $this->http->JsonLog();

        // Wrong verification code
        if (isset($response->errorMessage) && $response->errorMessage == 'Invalid parameter value') {
            $this->AskQuestion($this->Question, 'Wrong verification code');

            return false;
        }

        sleep(1);

        $param = [];
        $param['regToken'] = $this->State['regToken'];
        $param['targetEnv'] = "jssdk";
        $param['include'] = "profile,data,emails,subscriptions,preferences,";
        $param['includeUserInfo'] = 'true';
        $param['APIKey'] = $this->apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = $this->sdk;
        $param['pageURL'] = $this->pageURL;
        $param['format'] = "json";
        $this->http->GetURL("{$this->gigyaHost}/accounts.finalizeRegistration?" . http_build_query($param));
        $finalizeRegistration = $this->http->JsonLog();

        if (!isset($finalizeRegistration->UIDSignature)) {
            $this->logger->error("UIDSignature not found");

            return false;
        }

        return $this->authComplete($finalizeRegistration);
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $authType = 1;

        if (empty($responseAuth = $this->itinerariesPost(false))) {
            if (empty($responseAuth) && in_array($this->http->Response['code'], [403, 504])) {
                if (empty($responseAuth = $this->itinerariesPost(true))) {
                    return $result;
                }
            }

            if (empty($responseAuth) && in_array($this->http->Response['code'], [403, 504])) {
                if (empty($responseAuth = $this->itinerariesPost(true, true))) {
                    return $result;
                }
            }

            if (empty($responseAuth)) {
                $authType = 2;

                if (empty($responseAuth = $this->itinerariesPostNew())) {
                    return $result;
                }
            }
        }

        $this->http->FilterHTML = false;

        if (isset($responseAuth['EXTERNAL_ID'])) {
            //$this->sendNotification("success {$authType} ProfileValidationServlet // MI");
            $this->http->GetURL('https://www.aircanada.com/ca/en/aco/home.html#/retrieve:bkgl:0');

            if ($sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")) {
                $this->http->NormalizeURL($sensorPostUrl);
                $this->logger->notice('sensorPostUrl -> ' . $sensorPostUrl);

                if (!empty($sensorPostUrl)) {
                    $this->sendStaticSensorDataAircanada($sensorPostUrl, true);
                }
            }

            $this->http->Form = [];
            $this->http->FormURL = 'https://book.aircanada.com/pl/AConline/en/FetchPNRServlet';
            $this->http->Form['BOOKING_FLOW'] = "REBOOK";
            $this->http->Form['EMBEDDED_TRANSACTION'] = "GetPNRsListServlet";
            $this->http->Form['EXTERNAL_DIRECT_LOGIN'] = "YES";
            $this->http->Form['EXTERNAL_LOGIN'] = "RETRIEVE";
            $this->http->Form['FROM_STATE'] = "";
            $this->http->Form['IS_HOME_PAGE'] = "TRUE";
            $this->http->Form['LANGUAGE'] = 'US';
            $this->http->Form['LANGUAGE_CHARSET'] = 'utf-8';
            $this->http->Form['PAYMENT_TYPE'] = 'NONE';
            $this->http->Form['COUNTRY'] = 'US';
            $this->http->Form['countryOfResidence'] = 'US';
            $this->http->Form['actionName'] = 'Override';

            foreach ($responseAuth as $key => $val) {
                $this->http->Form[$key] = $val;
            }

            $form = $this->http->Form;
            $formURL = $this->http->FormURL;

            $this->http->PostForm();

            $nodes = [];
            $pnrList = [];
            $response = $this->http->JsonLog();

            if (isset($response->DATA->List_of_PNR)) {
                foreach ($response->DATA->List_of_PNR as $item) {
                    $nodes[] = $item;
                    $pnrList[] = $item->Reference_Id;
                }
            }

            $numNodes = count($nodes);
            $this->logger->debug("Total {$numNodes} aircanada itineraries were found");
            $this->logger->info('=aircanada-itins:');
            $this->logger->info(var_export($pnrList, true), ['pre' => true]);

            if ($numNodes > 0) {
                foreach ($nodes as $node) {
                    $recordLocator = $node->Reference_Id;
                    $this->logger->info('Parse Itinerary #' . $recordLocator, ['Header' => 3]);

                    $this->http->FormURL = $formURL;
                    $this->http->Form = $form;
                    $this->http->Form['REC_LOC'] = $recordLocator;
                    $this->http->Form['EMBEDDED_TRANSACTION'] = "RetrievePNRServlet";
                    $this->http->Form['ACTION'] = "MODIFY";
                    $this->http->Form['BAGGAGE_CALL_REQUIRED'] = "true";
                    $this->increaseTimeLimit();
                    $this->http->PostForm();

                    // todo: debug
                    $response = $this->http->JsonLog(null, false);
                    $message = $response->DATA->LIST_MESSAGES[0]->TEXT ?? null;
                    // Empty segments, workaround   // refs #17646
                    if (
                        $this->http->Response['code'] == 403
                        || (stristr($message, 'We are temporarily unable to process your request. Please try again later.') && stristr($message, 'message number. (3006)'))
                    ) {
                        $result = array_merge($result,
                            $this->ParseItinerariesAeroplanViaAircanadaRetrieve([$recordLocator], $node->PRIMARY_PAX_LNAME)
                        );
                        $this->increaseTimeLimit();

                        continue;
                    }

                    // todo: debug
                    $response = $this->http->JsonLog(null, false);
                    $message = $response->DATA->LIST_MESSAGES[0]->TEXT ?? null;

                    if (
                        in_array($message, [
                            "System Exception occurred, Please try after sometime (2004 [5555])",
                            "[To be updated by AC] (117228)",
                        ])
                        || (stristr($message, 'We are temporarily unable to process your request. ') && stristr($message, 'error message number. (9102 [0])'))
                    ) {
                        $this->logger->notice("Skip itineraries");

                        $this->logger->error($message);

                        if (
                            !(stristr($message, 'We are temporarily unable to process your request. Please try again later.') && stristr($message, 'message number. (3006)'))
                            && !stristr($message, "[To be updated by AC] (117228)")
                        ) {
                            $this->sendNotification("refs #17646 - need to skip itinerary? debug // RR");
                        }

                        sleep(5);

                        $this->http->FormURL = $formURL;
                        $this->http->Form = $form;
                        $this->http->Form['REC_LOC'] = $recordLocator;
                        $this->http->Form['EMBEDDED_TRANSACTION'] = "RetrievePNRServlet";
                        $this->http->Form['ACTION'] = "MODIFY";
                        $this->http->Form['BAGGAGE_CALL_REQUIRED'] = "true";

                        $this->increaseTimeLimit();

                        $this->http->PostForm();
                    }// if ($message == "System Exception occurred, Please try after sometime (2004 [5555])")

                    $it = $this->ParseItineraryAirCanada($node);

                    if (!empty($it)) {
                        $result[] = $it;
                    }
                }// foreach ($numNodes as $recordLocator)
            }// if ($numNodes > 0)
            else {
                $this->logger->error('reservations not found');
                // no Itineraries
                if ($noItineraries = $this->http->FindPreg("/\"List_of_PNR\" : null/")) {
                    return $this->noItinerariesArr();
                }// if ($noItineraries = $this->http->FindPreg("/\"List_of_PNR\" : null/"))
            }

//            if ($this->ParsePastIts)
//                $result = array_merge($result, $this->parsePastItineraries($form));
        }// if (isset($response->result->TRANSACTION_STATUS) && $response->result->TRANSACTION_STATUS == 'SUCCESS')
        else {
            $this->sendNotification("failed {$authType} ProfileValidationServlet // MI");
            $this->logger->error('authorization failed');
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        if ($this->attempt == 0) {
            $this->setProxyMount();
        } elseif ($this->attempt == 1) {
            $this->setProxyBrightData();
        } elseif ($this->attempt == 2) {
            $this->setProxyGoProxies();
        }

        $this->getFromSelenium($arFields);
        //$this->http->SetProxy($this->proxyReCaptcha());
        $response = $this->retrievePost($arFields, false);
        $itinData = $this->http->JsonLog($response, 3, true);

        if ($this->http->Response['code'] == 403 || !$itinData) {
            $this->sendNotification("Failed to retrieve itinerary by conf # // MI");

            throw new CheckRetryNeededException(3, 5);
        }

        if ($this->http->FindPreg('/"errorCode":"8132"/')) {
            $msg = "The booking reference you entered doesn't appear to be valid. Make sure you're entering an Air Canada booking reference.";
            $this->logger->error($msg);

            return $msg;
        }

        if ($this->http->FindPreg('/"errorCode":"8104"/')
        ) {
            $msg = "The booking reference you entered doesn't appear to be valid. Please try again, or contact Air Canada representatives directly for assistance.";
            $this->logger->error($msg);

            return $msg;
        }

        if ($this->http->FindPreg('/"errorCode":"RT_PNRT_00[56]"/')) {
            $msg = "We are temporarily unable to process your request. Please try again later. (err-7)";
            $this->logger->error($msg);

            return $msg;
        }

        // This booking has been cancelled
        if ($msg = $this->http->FindPreg('/(This booking has been cancelled\.)/')) {
            $it = ['Kind' => 'T'];
            $it['RecordLocator'] = $arFields['ConfNo'];
            $it['Cancelled'] = true;

            return null;
        }

        if ($this->http->FindPreg('/"errorCode":"3"/')
            || $this->http->FindPreg('/"errorCode":"4649"/')
        ) {
            $msg = "Sorry, we're not able to display this booking online. To make changes to this booking, please contact Air Canada ReservationsOpens in a new window for assistance or talk to your travel agent.";
            $this->logger->error($msg);

            return $msg;
        }
        $it = $this->parseItineraryAircanadaBkgd($itinData);

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "DATE"        => "PostingDate",
            "DESCRIPTION" => "Description",
            "AMOUNTS"     => "Miles",
            "BONUS"       => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    private function getSensorDataTwo($secondSensor = false)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            "7a74G7m23Vrp0o5c9282471.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,401283,7661607,1920,1050,1920,1080,1920,403,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7606,0.400982713200,815458830803,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,1,0,1343,-1,0;0,-1,1,0,1371,-1,0;0,-1,1,0,1333,-1,0;0,-1,1,0,1347,-1,0;0,-1,1,0,1360,-1,0;0,-1,0,0,936,936,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;-1,2,-94,-108,-1,2,-94,-110,0,1,1643,678,858;1,1,1644,688,852;2,1,1644,696,847;3,1,1665,786,788;4,1,1666,796,782;5,1,1720,999,674;6,1,1722,1005,671;7,1,1723,1013,669;8,1,1727,1026,661;9,1,1731,1032,658;10,1,1732,1040,652;11,1,1732,1048,650;12,1,1747,1094,623;13,1,1748,1099,617;14,1,1751,1104,615;15,1,1896,1186,558;16,1,1921,1186,557;17,1,1925,1187,557;18,1,1957,1187,556;19,1,2030,1184,557;20,1,2030,1183,557;21,1,2034,1183,558;22,1,2034,1182,558;23,1,2040,1181,559;24,1,2044,1180,559;25,1,2166,1160,574;26,1,2191,1158,575;27,1,2193,1158,576;28,1,2195,1157,576;29,1,2203,1157,577;30,1,2217,1156,578;31,1,2219,1155,578;32,1,3466,736,948;33,1,3467,892,947;34,1,3473,891,947;35,1,3504,890,947;36,1,3549,890,947;37,1,3583,889,949;38,1,3591,889,950;39,1,3598,888,950;40,1,3615,888,951;41,1,8649,791,720;42,1,8651,799,717;43,1,8651,813,712;44,1,8654,818,709;45,1,8657,827,706;46,1,8658,832,703;47,1,8660,837,701;48,1,8662,843,698;49,1,8663,848,695;50,1,8665,856,693;51,1,8667,861,690;52,1,8670,866,688;53,1,8671,871,685;54,1,8673,876,683;55,1,8675,881,680;56,1,8677,886,678;57,1,8679,890,676;58,1,8681,895,673;59,1,8683,899,671;60,1,8685,904,669;61,1,8687,909,666;62,1,8689,913,664;63,1,8691,915,662;64,1,8693,920,660;65,1,8695,924,658;66,1,8697,928,655;67,1,8701,933,653;68,1,8703,937,651;69,1,8703,941,649;70,1,8705,946,647;71,1,8707,948,645;72,1,8709,952,642;73,1,8711,956,638;74,1,8713,961,638;75,1,8717,965,636;76,1,8718,969,634;77,1,8722,971,632;78,1,8729,982,626;79,1,8731,986,624;80,1,8735,992,622;81,1,8735,996,620;82,1,8736,998,618;83,1,8743,1005,614;84,1,8744,1009,612;85,1,8745,1011,612;86,1,8748,1015,610;87,1,8751,1017,608;88,1,8751,1018,608;89,1,8753,1020,606;90,1,8755,1024,606;91,1,8757,1025,605;92,1,8759,1027,603;93,1,8761,1029,603;94,1,8763,1030,601;95,1,8765,1032,601;96,1,8768,1034,600;97,1,8771,1035,600;98,1,8773,1035,598;99,1,8775,1036,598;266,3,9895,1118,553,-1;267,4,9996,1118,553,-1;268,2,9998,1118,553,-1;758,3,17944,1015,689,1884;-1,2,-94,-117,-1,2,-94,-111,0,872,-1,-1,-1;-1,2,-94,-109,0,811,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,3,9874;2,15066;3,17918;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,832148,32,872,811,0,833799,17944,0,1630917661606,14,17447,0,759,2907,3,0,17948,656092,0,9B9C8402C9A601CBFC3E5B52FD33E375~-1~YAAQhf1zPhUcAnJ7AQAAlrpEugafr7VRFx/3p5474hDo2kUxszHW9R5binkowMHBY0JkWfYKeWuakvqPHk4lK7p9YhkkgTIO8/otIgbeAEhrUXXdEoznRLImeySGvQyF4qJHMWX4BKm0yc13U6733Njjv+Q6PC8SeYY30v4QFPPcjZwPqut6IS+s/34Ei6liKJOYiOL0OZFe20NmnuxiD8TLO0pU+8ZirZXETbMkfr8t09VclXcjpCvEgOOyk1d7UQVpG8gDtcJ13hBrAFvQ/7lJqb96/wHyMU4J2GtwzqChUMX87Y3+6pxX8i7WiEtx53LbZIrZmeH7shLq1GmcO99ZFURVmLGUTn3XsHO3WfztsewrG9pItVUZpFmMt/sYy+sQ2uj++SCNU3hDkSaeEqjfPUu/n1KIyIJgSHDZ+9BfGE+jWBh4~-1~-1~-1,39862,840,741308806,30261689,PiZtE,22060,73,0,-1-1,2,-94,-106,1,3-1,2,-94,-119,20,20,20,20,40,40,20,0,0,0,0,380,360,120,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,22984761-1,2,-94,-118,264247-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;11;14;0",
        ];

        $secondSensorData = [
        ];

//        if (count($sensorData) != count($secondSensorData)) {
//            $this->logger->error("wrong sensor data values");
//
//            return null;
//        }

        if (!isset($this->key)) {
            $this->key = array_rand($sensorData);
            $this->logger->notice("set key: {$this->key}");
        }
        $this->logger->notice("key: {$this->key}");

        //$sensor_data = $secondSensor === false ? $sensorData[$this->key] : $secondSensorData[$this->key];

        return $sensorData[$this->key];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Log Out")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function sendStatistic($success, $retry, $key, $form = "")
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("aeroplan{$form} sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function authComplete($finalizeRegistration)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("success");
        $login_token = $finalizeRegistration->sessionInfo->login_token;
        $this->http->RetryCount = 0;
        $this->http->setCookie("glt_{$this->apikey}", $login_token, '.aircanada.com');
        $this->http->setCookie("gig_loginToken_3_zgPFKZjPfWFU0BCP0pNeHYjpeFa3eQFDEatDGRwnxCzfE7eVH0yKh_XXSf34Tlux", $login_token, '.aircanada.com');
        $this->http->setCookie("gig_loginToken_3_zgPFKZjPfWFU0BCP0pNeHYjpeFa3eQFDEatDGRwnxCzfE7eVH0yKh_XXSf34Tlux", $login_token, '.login.aircanada.com	');

        $this->http->GetURL("https://www.aircanada.com/clogin/pages/proxy?mode=afterLogin");

        // todo: not needed
        $param = [];
        $param['enabledProviders'] = "*";
        $param['APIKey'] = $this->apikey;
        $param['sdk'] = $this->sdk;
        $param['login_token'] = $login_token;
        $param['authMode'] = "cookie";
        $param['pageURL'] = "https://www.aircanada.com/clogin/pages/proxy?mode=afterLogin";
        $param['format'] = "json";
        $this->http->GetURL("https://login.aircanada.com/socialize.getUserInfo?" . http_build_query($param));
        $this->http->JsonLog();

        $param = [
            'context'            => $this->State['context'],
            'clientID'           => "-pwiPl__b08rgQLobNxqF1Ig",
            'scope'              => "openid+profile+ffp+country+device",
            'UID'                => $finalizeRegistration->UID,
            'UIDSignature'       => $finalizeRegistration->UIDSignature,
            'signatureTimestamp' => $finalizeRegistration->signatureTimestamp,
        ];

        $this->http->GetURL("https://www.aircanada.com/clogin/pages/consent?" . http_build_query($param));

        $this->http->PostURL("https://www.aircanada.com/clogin/consent?" . http_build_query($param), []);
        $redirectURL = $this->http->JsonLog()->url ?? null;

        if (!$redirectURL) {
            $this->logger->notice("redirect url not found");

            return false;
        }

        $param = [
            'UID'                => $finalizeRegistration->UID,
            'UIDSignature'       => $finalizeRegistration->UIDSignature,
            'signatureTimestamp' => $finalizeRegistration->signatureTimestamp,
        ];
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json; utf-8",
        ];
        $this->http->PostURL("https://www.aircanada.com/clogin/register-device", json_encode($param), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;

        $this->http->GetURL($redirectURL);

        $param = [
            'context'     => $this->State['context'],
            'login_token' => $login_token,
        ];
        $this->logger->notice("Redirecting...");
        $this->http->GetURL("https://login.aircanada.com/oidc/op/v1.0/{$this->apikey}/authorize/continue?"
            . http_build_query($param) . str_replace('https://www.aircanada.com/clogin/pages/proxy?mode=afterConsent', '', $redirectURL));

        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->notice("something went wrong, code not found");

            return false;
        }

        $codeVerifier = 'VXVR7z8WMQ7Db96DCCwTkwCoZBFCpnFtP9kDDvzQaE3G7760OnGGo9SFErCzYy40E0Njc4c2kEe70gCGVHr7SYqD7TrsHIRDYQ5aJkaO7lNPHdEmDkhfWW8bJCvxgWZd';

        if ($this->AccountFields['Login'] == 'iormark@yandex.com') {
            $script = 'var t = new Uint8Array(128);
            for (var n = 0; n < 128; n += 1)
                t[n] = Math.random() * "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~".length | 0;
            var e = t;
            for (t = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789", n = [], r = 0; r < e.byteLength; r += 1) {
                var i = e[r] % t.length;
                n.push(t[i])
            }
            
            sendResponseToPhp(n.join(""))';
            $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
            $codeVerifier = $jsExecutor->executeString($script);
            //$codeVerifier = '7KvSDG57833OI4zzLiVAKetVon4F93O9mc5fGOSOfaPGfMWPINYqNfW1ATQpnnuZ5iD6NP12b0WI7kwOJPfrzHYQf1LdB3wN4uDcWRnYw0DKl4EMUjSNfc6Zm45vAvzS';
        }

        $data = [
            'grant_type'    => "authorization_code",
            'code'          => $code,
            'client_id'     => "5put0po1jqtrfi4k9fm43roflg",
            'redirect_uri'  => "https://www.aircanada.com/content/aircanada/ca/en/aco/home/oidc-redirect.html",
            'code_verifier' => $codeVerifier,
        ];
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/x-www-form-urlencoded",
            "Origin"          => "https://www.aircanada.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.api-gw.dbaas.aircanada.com/oauth2/token", $data, $headers);
        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if (!isset($response->access_token)) {
            $this->logger->notice("access_token not found");

            return false;
        }
        $this->http->setDefaultHeader("authorization", $response->access_token);

        return true;
    }

    private function parseQuestion($response)
    {
        $this->logger->notice(__METHOD__);
        $this->State['regToken'] = $response->regToken;
        $this->State['Properties'] = $this->Properties;

        $this->http->RetryCount = 0;

        $this->logger->debug("init 2fa");
        $param = [];
        $param['provider'] = 'gigyaEmail';
        $param['mode'] = 'verify';
        $param['regToken'] = $response->regToken;
        $param['APIKey'] = $this->apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = $this->sdk;
        $param['pageURL'] = $this->pageURL;
        $param['format'] = "json";
        $this->http->GetURL("{$this->gigyaHost}/accounts.tfa.initTFA?" . http_build_query($param));
        $initTFA = $this->http->JsonLog();

        if (!isset($initTFA->gigyaAssertion)) {
            $this->logger->error("gigyaAssertion mot found");

            return false;
        }

        $this->logger->debug("get email info");
        $param = [];
        $param['gigyaAssertion'] = $initTFA->gigyaAssertion;
        $param['APIKey'] = $this->apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = $this->sdk;
        $param['pageURL'] = $this->pageURL;
        $param['format'] = "json";
        $this->http->GetURL("{$this->gigyaHost}/accounts.tfa.email.getEmails?" . http_build_query($param));
        $getEmails = $this->http->JsonLog();

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->logger->debug("send Verification Code");
        $param = [];
        $param['emailID'] = $getEmails->emails[0]->id;
        $param['gigyaAssertion'] = $initTFA->gigyaAssertion;
        $param['lang'] = 'en';
        $param['regToken'] = $response->regToken;
        $param['APIKey'] = $this->apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = $this->sdk;
        $param['pageURL'] = $this->pageURL;
        $param['format'] = "json";
        $this->http->GetURL("{$this->gigyaHost}/accounts.tfa.email.sendVerificationCode?" . http_build_query($param));
        $sendVerificationCode = $this->http->JsonLog();

        $this->State['gigyaAssertion'] = $initTFA->gigyaAssertion;
        $this->State['phvToken'] = $sendVerificationCode->phvToken;
        $this->State['regToken'] = $response->regToken;

        $this->Question = "We have sent a verification code to the email address: {$getEmails->emails[0]->obfuscated}. It will expire in 5 minutes.";
        $this->Step = "Question";
        $this->ErrorCode = ACCOUNT_QUESTION;

        return true;
    }

    private function generateGigyaContext()
    {
        // R3437646514
        /*
         if (n.disableCache || window.gigya.localInfo.isSafari || window.gigya.localInfo.isIE10 && e.indexOf("accounts.getAccountInfo") > -1)
            return "R" + (new Date).getTime() + "_" + Math.random();
         var i = window.gigya.utils.object.clone(t);
         for (var r in i)
             i.hasOwnProperty(r) && (0 !== r.indexOf("fb_") && "source" !== r && "sourceData" !== r || delete i[r]);
         return "R" + window.gigya.utils.object.getMurmurHash(Math.random().toString() + e + window.gigya.utils.object.getHash(i))
         */
        return 'R' . (time() * 1000) . '_' . ((float) rand() / (float) getrandmax());
        //return 'R4035331618';
    }

    private function itinerariesPostNew()
    {
        $this->logger->notice(__METHOD__);
        $context = $this->generateGigyaContext();
        $sdk = "js_latest";
        $apikey = '3_zA5TRSBDlwybsx_1k8EyncAfJ2b62DJnoxPW60q4X9MqmBDJh1v_8QYaOTG8kZ8S';

        $this->http->GetURL('https://www.aircanada.com/us/en/aco/home.html');

        if ($sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->sensorDataURL = $sensorPostUrl;
            $this->logger->notice('sensorPostUrl -> ' . $this->sensorDataURL);
        }

        $param = [];
        $param['APIKey'] = $apikey;
        $param['pageURL'] = 'https://www.aircanada.com/us/en/aco/home.html';
        $param['format'] = "json";
        $param['context'] = $context;
        $this->http->GetURL("https://login.aircanada.com/accounts.webSdkBootstrap?" . http_build_query($param));
        $response = $this->http->JsonLog();

        $this->http->Form = [];
        $this->http->FormURL = "https://login.aircanada.com/accounts.login?context={$context}&saveResponseID={$context}";
        $this->http->SetInputValue('loginID', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('sessionExpiration', "0");
        $this->http->SetInputValue('lang', "en");
        $this->http->SetInputValue('targetEnv', "jssdk");
        $this->http->SetInputValue('include', "identities-active, preferences, identities-all, loginIDs, emails, profile, data");
        $this->http->SetInputValue('includeUserInfo', "true");
        $this->http->SetInputValue('APIKey', $apikey);
        $this->http->SetInputValue('sdk', $sdk);
        $this->http->SetInputValue('authMode', "cookie");
        $this->http->SetInputValue('pageURL', "ttps://www.aircanada.com/us/en/aco/home.html");
        $this->http->SetInputValue('format', "json");
        $this->http->SetInputValue('context', $context);
        $this->http->SetInputValue('utf8', "✓");

        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;

        $param = [];
        $param['APIKey'] = $apikey;
        $param['saveResponseID'] = $context;
        $param['noAuth'] = 'true';
        $param['sdk'] = $sdk;
        $param['format'] = "json";
        $param['context'] = $context;
        $this->http->GetURL("https://login.aircanada.com/socialize.getSavedResponse?" . http_build_query($param));
        $response = $this->http->JsonLog();

        if (isset($response->sessionInfo->login_token)) {
            $param = [];
            $param['include'] = 'profile,data';
            $param['APIKey'] = $apikey;
            $param['login_token'] = $response->sessionInfo->login_token;
            $param['authMode'] = 'cookie';
            $param['pageURL'] = 'https://www.aircanada.com/us/en/aco/home.html';
            $param['sdk'] = $sdk;
            $param['format'] = "json";
            $param['context'] = $context;
            $this->http->GetURL("https://login.aircanada.com/accounts.getAccountInfo?" . http_build_query($param));
            $accountInfo = $this->http->JsonLog();

            if (isset($accountInfo->UIDSignature)) {
//                if (!empty($this->sensorDataURL)) {
//                    $key = $this->sendStaticSensorDataAircanada($this->sensorDataURL);
//                }

                $this->http->Inputs["password"]['maxlength'] = 10;
                sleep(rand(1, 3));
                $data = [
                    'ACID'               => '',
                    'AEROPLAN_NUMBER'    => $response->loginIDs->username,
                    'COUNTRY'            => 'CA',
                    'EMAIL_ID'           => $accountInfo->profile->email,
                    'ENCRYPT_TIME_STAMP' => 'true',
                    'FIRST_NAME'         => $accountInfo->profile->firstName,
                    'LAST_NAME'          => $accountInfo->profile->lastName,
                    'IS_HOME_PAGE'       => 'TRUE',
                    'LANGUAGE'           => 'US',
                    'LANGUAGE_CHARSET'   => 'utf-8',
                    'LOGIN_DURATION'     => '7200',
                    'LOGIN_TIME_STAMP'   => date("Y-m-d\TH:i:s.000\Z", $accountInfo->signatureTimestamp),
                    'PREFERENCE_CENTER'  => '',
                    'actionName'         => 'ACOLoginTimeStampCryptoAction',
                    'g_l'                => 'true',
                    'UID'                => $accountInfo->UID,
                    'UIDSignature'       => $accountInfo->UIDSignature,
                    'signatureTimestamp' => $accountInfo->signatureTimestamp,
                ];
                $this->http->PostURL('https://book.aircanada.com/pl/AConline/en/ProfileValidationServlet', $data, ['Accept' => 'application/json, text/plain, */*']);

                if ($this->http->Response['code'] == 403) {
                    sleep(rand(1, 3));
                    $this->http->PostURL('https://book.aircanada.com/pl/AConline/en/ProfileValidationServlet', $data, ['Accept' => 'application/json, text/plain, */*']);
                }
                $profileValidationServlet = $this->http->JsonLog();

                $data = [
                    'ACCOUNT_NUMBER'       => $response->loginIDs->username,
                    'COUNTRY'              => 'US',
                    'EXTERNAL_ID'          => 'NULL',
                    'EMBEDDED_TRANSACTION' => 'AeroplanServlet',
                    'CALL_LOGIN_MEMBER'    => 'true',
                    'FIRST_NAME'           => $accountInfo->profile->firstName,
                    'LAST_NAME'            => $accountInfo->profile->lastName,
                    'LANGUAGE'             => 'US',
                    'LANGUAGE_CHARSET'     => 'utf-8',
                    'SITE'                 => 'SAADSAAD',
                    'TRVLR_ID'             => '0',
                    'g_l'                  => 'true',
                    'UID'                  => $accountInfo->UID,
                    'actionName'           => 'Override',
                    'UIDSignature'         => $accountInfo->UIDSignature,
                    'signatureTimestamp'   => $accountInfo->signatureTimestamp,
                ];
                $this->http->PostURL('https://book.aircanada.com/pl/AConline/en/CreatePNRServlet', $data, ['Accept' => 'application/json, text/plain, */*']);
                $createPNRServlet1 = $this->http->JsonLog();

                if (empty($createPNRServlet1->DATA->aeroplan->sessionId)) {
                    return [];
                }
                //sleep(rand(1, 3));
                $data['EXTERNAL_ID'] = $createPNRServlet1->DATA->aeroplan->sessionId;
                unset($data['CALL_LOGIN_MEMBER']);
                $this->http->PostURL('https://book.aircanada.com/pl/AConline/en/CreatePNRServlet', $data, ['Accept' => 'application/json, text/plain, */*']);
                $createPNRServlet = $this->http->JsonLog();

                if (isset($response->loginIDs->username, $createPNRServlet->DATA->aeroplan->tierStatus, $createPNRServlet1->DATA->aeroplan->sessionId)) {
                    $data = [
                        'USERID'      => $response->loginIDs->username,
                        'EXTERNAL_ID' => $createPNRServlet1->DATA->aeroplan->sessionId,
                        'FNAME'       => $accountInfo->profile->firstName,
                        'LNAME'       => $accountInfo->profile->lastName,
                        'FFMILES'     => $createPNRServlet->DATA->aeroplan->miles,
                        'TIER_STATUS' => $createPNRServlet->DATA->aeroplan->tierStatus,
                        'TITLE'       => $createPNRServlet->DATA->aeroplan->title,
                    ];

                    return $data;
                }

                /*$this->http->FormURL = 'https://book.aircanada.com/pl/AConline/en/FetchPNRServlet';
                $this->http->Form = [];
                $this->http->SetInputValue("USERID", $response->loginIDs->username);
                $this->http->SetInputValue("BOOKING_FLOW", "REBOOK");
                $this->http->SetInputValue("COUNTRY", "US");
                $this->http->SetInputValue("EMBEDDED_TRANSACTION", "GetPNRsListServlet");
                $this->http->SetInputValue("EXTERNAL_DIRECT_LOGIN", "YES");
                $this->http->SetInputValue("EXTERNAL_ID", $createPNRServlet->DATA->aeroplan->sessionId);
                $this->http->SetInputValue("EXTERNAL_LOGIN", "RETRIEVE");
                $this->http->SetInputValue("FFMILES", "0");
                $this->http->SetInputValue("FNAME", $accountInfo->profile->firstName);
                $this->http->SetInputValue("LNAME", $accountInfo->profile->lastName);
                $this->http->SetInputValue("FROM_STATE", "");
                $this->http->SetInputValue("IS_HOME_PAGE", "TRUE");
                $this->http->SetInputValue("LANGUAGE", "US");
                $this->http->SetInputValue("LANGUAGE_CHARSET", "utf-8");
                $this->http->SetInputValue("PAYMENT_TYPE", "NONE");
                $this->http->SetInputValue("TIER_STATUS", "A");
                $this->http->SetInputValue("TITLE", "MR");
                $this->http->SetInputValue("actionName", "Override");
                $this->http->SetInputValue("countryOfResidence", "US");

                $this->http->RetryCount = 0;
                $this->http->PostForm(['Accept' => 'application/json, text/plain, /']);
                $this->http->RetryCount = 2;
                $fetchPNRServlet = $this->http->JsonLog();

                if (!empty($fetchPNRServlet->DATA->List_of_PNR) || !empty($fetchPNRServlet->DATA->LIST_MESSAGES)) {
                    $this->sendNotification('check new itineraries // MI');
                }*/
            }
        }

        return [];
    }

    private function itinerariesPost($seleniumSensor = false, $ff53 = false)
    {
        $this->logger->notice(__METHOD__);
        // refs #6522
        $this->logger->notice(">>> Go to www.aircanada.com");
        // $this->http->GetURL('http://www.aircanada.com/aco/manageMyBookings.do');
        $this->http->GetURL('https://www.aircanada.com/ca/en/aco/home.html');

        if (!$this->http->FindPreg('/form name="loginform"/')) {
            $this->logger->error("aircanada form not found");

            return [];
        }// if (!$this->http->ParseForm("ACOLogonForm"))

        $this->increaseTimeLimit(120);

        if ($sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->logger->notice('sensorPostUrl -> ' . $sensorPostUrl);

            if (!empty($sensorPostUrl)) {
                $this->sendStaticSensorDataAircanada($sensorPostUrl);
            }
        }
        //$this->airCanadaSensorData($seleniumSensor, $ff53);

        $this->http->Inputs["password"]['maxlength'] = 10;
        $this->http->FormURL = 'https://book.aircanada.com/pl/AConline/en/ProfileValidationServlet';
        $this->http->Form = [];
        $this->http->SetInputValue("COUNTRY", "CA");
        $this->http->SetInputValue("CUSTOMER_ID", $this->AccountFields['Login']);
        $this->http->SetInputValue("USER_PASS", $this->AccountFields['Pass']);
        $this->http->SetInputValue("EXTERNAL_ID", "GUEST");
        $this->http->SetInputValue("IS_HOME_PAGE", "TRUE");
        $this->http->SetInputValue("LANGUAGE", "US");
        $this->http->SetInputValue("LANGUAGE_CHARSET", "utf-8");
        $this->http->SetInputValue("actionName", "ACOAeroplanLoginAction");
        $this->http->RetryCount = 0;
        $this->http->PostForm(['Accept' => 'application/json, text/plain, */*']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->result->EXTERNAL_ID, $response->result->LNAME)) {
            $data = [
                'USERID'      => $this->AccountFields['Login'],
                'EXTERNAL_ID' => $response->result->EXTERNAL_ID,
                'TITLE'       => $response->result->TITLE->CODE,
                'LNAME'       => $response->result->LNAME,
                'MNAME'       => $response->result->MNAME,
                'FNAME'       => $response->result->FNAME,
                'FFMILES'     => $response->result->FFMILES,
                'TIER_STATUS' => $response->result->TIER_STATUS,
            ];

            return $data;
        }

        return [];
    }

    private function ParseItinerariesAeroplanViaAircanadaRetrieve($confs, $lastName = null)
    {
        $this->logger->notice(__METHOD__);
        $res = [];

        $this->logger->debug("[lastName]: {$lastName}");

        if (!$lastName) {
            $name = ArrayVal($this->Properties, 'Name');
            $lastName = $this->http->FindPreg('/(?:Mr|Mrs|Ms|Dr|M)[.]?\s+.+?([\w-]+)\s*$/i', false, $name);

            if (!$lastName) {
                $lastName = $this->http->FindPreg('/([\w-]+)$/i', false, $name);
            }
        }

        if (!$lastName) {
            $this->sendNotification('empty last name for retrieve');

            return [];
        }
        $this->logger->debug("[lastName]: {$lastName}");

        foreach ($confs as $conf) {
            $arFields = [
                'ConfNo'   => $conf,
                'LastName' => $lastName,
            ];
            $it = [];
            $this->logger->info(sprintf('Retrieve Parse Itinerary #%s', $conf), ['Header' => 3]);
            $this->CheckConfirmationNumberInternal($arFields, $it);

            if ($it && is_array($it)) {
                $res[] = $it;
            }
        }

        return $res;
    }

    /*private function parsePastItineraries($form) {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $result = [];

//        $this->http->GetURL("https://www.aircanada.com/ca/en/aco/home/book/manage-bookings/flight-pass-summary.html");
//        $this->http->PostURL("https://fp.aircanada.com/wallet/servlet/CTO5PnrViewServlet/ViewPNRSummary", ["fromSummaryPage" => "Y", "RL" => ""]);
        $this->http->setCookie("sessionInfo", "session:{$form['EXTERNAL_ID']}&custid:0{$this->AccountFields['Login']}&token:&lang:english&sellCountryCode:CA&balance:{$form['FFMILES']}&membershipLevel:*&province:", ".fp.aircanada.com");
        $this->http->setCookie("apUser", "{$form['TITLE']} {$form['FNAME']} {$form['MNAME']} {$form['LNAME']}", ".fp.aircanada.com");
        $this->http->setCookie("apMileage", $form['FFMILES'], ".fp.aircanada.com");
        $this->http->setCookie("cookietest", "yes", ".fp.aircanada.com");
//        $this->http->setCookie("JSESSIONID", "", "fp.aircanada.com");
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://fp.aircanada.com/wallet/servlet/CTO5PnrViewServlet/ViewPNRSummary", ["ThirdPartyID" => "0".$this->AccountFields['Login'], "fromNBM" => "true"]);
        $this->http->PostURL("https://fp.aircanada.com/wallet/servlet/CTO5PnrViewServlet/ViewPNRSummary", ["requestedStatus" => "PAST"]);
        $this->http->RetryCount = 2;
//        $pastIts = $this->http->XPath->query("//div[@class = 'past-book']/div[contains(@class, 'airport-arrival')]");
//        $this->logger->debug("Total {$pastIts->length} past reservations found");
//        if ($pastIts->length == 0)
//            $this->logger->notice(">>> ".$this->http->FindPreg("/We can't find any bookings for this account in the last 12 months\./ims"));
//        for ($i = 0; $i < $pastIts->length; $i++) {
//            $node = $pastIts->item($i);
//            $header = $this->http->FindSingleNode("h4", $node);
//            $result[] = [
//                'Kind' => 'T',
//                'RecordLocator' => $this->http->FindSingleNode(".//p[contains(@class, 'booking-value')]", $node),
//                'TripSegments' => [
//                    [
//                        'FlightNumber' => $this->http->FindPreg("/^[A-Z]+(\d+)/", false, $header),
//                        'AirlineName' => $this->http->FindPreg("/^([A-Z]+)\d+/", false, $header),
//                        'DepDate' => strtotime($this->http->FindSingleNode(".//p[contains(@class, 'departure-value')]", $node)),
//                        'DepCode' => TRIP_CODE_UNKNOWN,
//                        'DepName' => $this->http->FindPreg("/^[A-Z]+\d+\s+(.+)\s+to\s+.+/", false, $header),
//                        'ArrDate' => strtotime($this->http->FindSingleNode(".//p[contains(@class, 'arrival-value')]", $node)),
//                        'ArrCode' => TRIP_CODE_UNKNOWN,
//                        'ArrName' => $this->http->FindPreg("/^[A-Z]+\d+\s+.+\s+to\s+(.+)/", false, $header),
//                    ]
//                ]
//            ];
//        }// for ($i = 0; $i < $pastIts->length; $i++)
        $this->getTime($startTimer);

        return $result;
    }*/

    private function ParseItineraryAirCanada($itinData = null)
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];
        $response = $this->http->JsonLog(null, false, true);
//        if (empty($response))
//            return [];
        $data = ArrayVal($response, 'DATA');
        $air = ArrayVal($data, 'AIR');
        $pnr = ArrayVal($data, 'PNR');
        $price = ArrayVal($air, 'LIST_TRIP_PRICE');

        // ConfirmationNumber
        $result['RecordLocator'] = ArrayVal($data, 'REC_LOC');
        // TotalCharge
        if (isset($price[0])) {
            $result['TotalCharge'] = ArrayVal($price[0], 'AMOUNT');
            // BaseFare
            $result['BaseFare'] = ArrayVal($price[0], 'AMOUNT_WITHOUT_TAX');

            if ($result['TotalCharge'] == 0) {
                $this->sendNotification("zero TotalCharge // RR");
            }

            if ($result['BaseFare'] == 0) {
                $this->sendNotification("zero BaseFare // RR");
            }

            if ($result['Tax'] == 0) {
                $this->sendNotification("zero Tax // RR");
            }

            // Tax
            $result['Tax'] = ArrayVal($price[0], 'TAX');
            // Currency
            $result['Currency'] = ArrayVal(ArrayVal($price[0], 'CURRENCY'), 'CODE');
        }// if (isset($price[0]))
        else {
            $this->logger->debug("price not found");
        }
        // ReservationDate
        $reservDate = ArrayVal($pnr, 'CREATION_DATE');
        $this->logger->debug($reservDate);

        if ($reservDate = strtotime($reservDate)) {
            $result['ReservationDate'] = $reservDate;
        }

        $tripSummary = ArrayVal($data, 'tripSummary');
        $data = ArrayVal($tripSummary, 'data');
        $identities = ArrayVal($data, 'IDENTITIES', []);
        $itinerary = ArrayVal($data, 'ITINERARY', []);
        $seats = [];
        $result['AccountNumbers'] = [];

        foreach ($identities as $identity) {
            // TicketNumbers
            $ticket = ArrayVal($identity, 'TICKET_NUMBER');

            if (!empty($ticket)) {
                $result['TicketNumbers'][] = $ticket;
            }
            // Passengers
            $name = ArrayVal($identity, 'NAME');
            $passName = Html::cleanXMLValue(ArrayVal($name, 'FIRST_NAME') . ' ' . ArrayVal($name, 'MIDDLE_NAME') . ' ' . ArrayVal($name, 'LAST_NAME'));

            if (!empty($passName)) {
                $result['Passengers'][] = beautifulName($passName);
            }
            $this->sendNotification("wrong Name // RR");
            // Account Numbers
            $number = ArrayVal($identity, 'LIST_FREQUENT_FLYER');

            if (isset($number[0]['ACCOUNT_NUMBER'])) {
                $result['AccountNumbers'][] = $number[0]['ACCOUNT_NUMBER'];
            }
            // Seats
            $seatsSelection = ArrayVal($identity, 'LIST_SEAT_SELECTION', []);

            foreach ($seatsSelection as $seatSelection) {
                $segmentsDetails = ArrayVal($seatSelection, 'LIST_SEGMENT_DETAILS', []);

                foreach ($segmentsDetails as $segmentsDetail) {
                    $seat = ArrayVal($segmentsDetail, 'SEAT_SELECTION');

                    if ($seat) {
                        $seats[ArrayVal($segmentsDetail, 'CARRIER_NUMBER')][] = $seat;
                    }
                }
            }// foreach ($seatsSelection as $seatSelection)
        }// foreach ($identities as $identity);
        $result['AccountNumbers'] = array_values(array_unique($result['AccountNumbers']));

        // Air Trip Segments

        $bounds = ArrayVal($itinerary, 'LIST_BOUNDS', []);
        $this->logger->debug('Total ' . count($bounds) . ' legs were found');

        foreach ($bounds as $bound) {
            $segments = ArrayVal($bound, 'LIST_SEGMENT', []);
            $this->logger->debug('Total ' . count($segments) . ' segments were found');

            foreach ($segments as $segment) {
                $tripSeg = [];
                // FlightNumber
                $tripSeg['FlightNumber'] = ArrayVal($segment, 'NUMBER');
                // Seats
                if (isset($seats[$tripSeg['FlightNumber']])) {
                    $tripSeg['Seats'] = array_values(array_unique($seats[$tripSeg['FlightNumber']]));
                }
                // DepCode, DepName
                $tripSeg['DepName'] = $tripSeg['DepCode'] = ArrayVal(ArrayVal($segment, 'ORIGIN'), 'LOCATION_CODE');
                // DepartureTerminal
                $tripSeg['DepartureTerminal'] = ArrayVal($segment, 'B_TERMINAL');
                // DepDate
                $depDate = ArrayVal($segment, 'DEPARTURE', null);

                if ($depDate && strtotime($depDate)) {
                    $tripSeg['DepDate'] = strtotime($depDate);
                }
                // ArrCode, ArrName
                $tripSeg['ArrName'] = $tripSeg['ArrCode'] = ArrayVal(ArrayVal($segment, 'DESTINATION'), 'LOCATION_CODE');
                // ArrivalTerminal
                $tripSeg['ArrivalTerminal'] = ArrayVal($segment, 'E_TERMINAL');
                // ArrDate
                $arrDate = ArrayVal($segment, 'ARRIVAL', null);

                if ($arrDate && strtotime($arrDate)) {
                    $tripSeg['ArrDate'] = strtotime($arrDate);
                }
                // Stops
                $tripSeg['Stops'] = ArrayVal($segment, 'STOPS');
                // Cabin
                $cabin = ArrayVal($segment, 'LIST_CABIN');

                if (isset($cabin[0])) {
                    $c = ArrayVal(ArrayVal($cabin[0], 'FARE_FAMILY'), 'NAME');

                    if (in_array($c, ['Tango', 'Flex'])) {
                        $tripSeg['Cabin'] = 'Economy ' . $c;
                    } else {
                        $tripSeg['Cabin'] = $c;
                    }
                }// if (isset($cabin[0]))
                // BookingClass
                $tripSeg['BookingClass'] = ArrayVal($segment, 'RBD');
                // Aircraft
                $tripSeg['Aircraft'] = ArrayVal(ArrayVal($segment, 'AIRCRAFT'), 'NAME');
                // AirlineName
                $tripSeg['AirlineName'] = ArrayVal(ArrayVal($segment, 'AIRLINE'), 'CODE');
                // Operator
                $tripSeg['Operator'] = ArrayVal(ArrayVal($segment, 'OTHER_AIRLINE'), 'NAME');

                $result['TripSegments'][] = $tripSeg;
            }// foreach ($segments as $segment)
        }// foreach ($bounds as $bound)

        if ($itinData) {
            if (!ArrayVal($result, 'RecordLocator')
                && isset($itinData->Reference_Id)) {
                $result['RecordLocator'] = $itinData->Reference_Id;
            }

            if (!ArrayVal($result, 'Status')
                && isset($itinData->Booking_Status->Confirmed)
                && $itinData->Booking_Status->Confirmed === 'Cancelled') {
                $result['Status'] = 'Cancelled';
                $result['Cancelled'] = true;
            }
            /*
             * "We're not able to locate your booking.
             * It may be because it was cancelled or because you have completed your trip.
             * Please take note of the error message/number and contact Air Canada Reservations if you require assistance.
             */
            elseif (empty($result['TripSegments'])) {
                $response = $this->http->JsonLog(null, false);
                $message = $response->DATA->LIST_MESSAGES[0]->TEXT ?? null;
                $this->logger->error($message);

                if (strstr($message, 'This booking has been cancelled.')) {
                    $result['Status'] = 'Cancelled';
                    $result['Cancelled'] = true;
                } else {
                    if (
                        $message == "PNR retrieved contains no active itinerary (2000 [5555])"
                        || $message == "There are no new add-ons available for your booking. Your booking can't be enhanced any further. (15221)"
                        || (
                            strstr($message, "This booking contains at least one flight for which a passenger did not show up.")
                            && strstr($message, "(68200)")
                        )
                        || (
                            strstr($message, "We are temporarily unable to process your request.")
                            && strstr($message, "(9102)")
                        )
                        || $message == 'NDC Connection Error (5555)'
                        // We have noted an irregularity with your recent payment transaction.
                        || $message == 'The PNR is void or refunded. (8152)'
                    ) {
                        $this->logger->notice("Skip itineraries");

                        return [];
                    }

                    if ($this->http->Response['code'] != 403) {
                        $this->sendNotification("refs #17646 - Empty segments 2, debug // RR");
                    }
                }
            }// elseif (empty($result['TripSegments']))
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function parseItineraryAircanadaBkgd($data)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];

        $conf = $this->arrayVal($data, ['data', 'id']);
        // RecordLocator
        $res['RecordLocator'] = $conf;
        $this->logger->info("Parse Itinerary #$conf", ['Header' => 3]);
        $totalPrices = $this->arrayVal($data, ['data', 'air', 'prices', 'totalPrices', 0], []);
        // TotalCharge
        $total = $this->arrayVal($totalPrices, ['total', 'value']);

        if ($total) {
            $res['TotalCharge'] = $total / 100;
        }
        // Currency
        $res['Currency'] = $this->arrayVal($totalPrices, ['total', 'currencyCode']);
        // BaseFare
        $baseFare = $this->arrayVal($totalPrices, ['base', 'value']);

        if ($baseFare) {
            $res['BaseFare'] = $baseFare / 100;
        }
        // Tax
        $tax = $this->arrayVal($totalPrices, ['totalTaxes', 'value']);

        if ($tax) {
            $res['Tax'] = $tax / 100;
        }

        if (isset($res['TotalCharge']) && $res['TotalCharge'] == 0) {
            $this->sendNotification("zero TotalCharge // RR");
        }

        if (isset($res['BaseFare']) && $res['BaseFare'] == 0) {
            $this->sendNotification("zero BaseFare // RR");
        }
        // Tax
        if (isset($res['Tax']) && $res['Tax'] == 0) {
            $this->sendNotification("zero Tax // RR");
        }

        // Passengers
        $res['Passengers'] = [];

        foreach ($this->arrayVal($data, ['data', 'travelers'], []) as $traveler) {
            $firstName = $this->arrayVal($traveler, ['names', 0, 'firstName'], '');
            $lastName = $this->arrayVal($traveler, ['names', 0, 'lastName'], '');
            $title = $this->arrayVal($traveler, ['names', 0, 'title'], '');
            $name = trim(beautifulName("$title $firstName $lastName"));

            if ($name) {
                $res['Passengers'][] = $name;
            }
        }
        // AccountNumbers
        $res['AccountNumbers'] = [];

        foreach ($this->arrayVal($data, ['data', 'frequentFlyerCards'], []) as $card) {
            $number = $card['cardNumber'] ?? null;

            if ($number) {
                $res['AccountNumbers'][] = $number;
            }
        }
        // Seats
        $flightIdToSeats = [];

        foreach ($this->arrayVal($data, ['data', 'seats'], []) as $item) {
            $flightId = $item['flightId'] ?? null;

            if (!$flightId) {
                continue;
            }
            $flightIdToSeats[$flightId] = [];

            foreach (($item['seatSelections'] ?? []) as $sel) {
                $seatNumber = $sel['seatNumber'] ?? null;

                if ($seatNumber) {
                    $flightIdToSeats[$flightId][] = $seatNumber;
                }
            }
        }
        $flightDict = $this->arrayVal($data, ['dictionaries', 'flight'], []);
        // TripSegments
        $boundIndex = 0;

        foreach ($this->arrayVal($data, ['data', 'air', 'bounds'], []) as $bound) {
            $boundFlights = $this->arrayVal($bound, ['flights'], []);

            if (!$boundFlights) {
                continue;
            }

            foreach ($boundFlights as $boundFlight) {
                $flightId = $boundFlight['id'] ?? null;
                $flight = $flightDict[$flightId] ?? null;

                if (!$flight) {
                    $this->sendNotification('check aircanada flights // MI');

                    continue;
                }
                // FlightNumber
                $ts['FlightNumber'] = $flight['marketingFlightNumber'] ?? null;
                // AirlineName
                $ts['AirlineName'] = $flight['marketingAirlineCode'] ?? null;
                // DepCode
                $ts['DepCode'] = $flight['departure']['locationCode'] ?? null;
                // ArrCode
                $ts['ArrCode'] = $flight['arrival']['locationCode'] ?? null;
                // DepartureTerminal
                $ts['DepartureTerminal'] = $flight['departure']['terminal'] ?? null;
                // ArrivalTerminal
                $ts['ArrivalTerminal'] = $flight['arrival']['terminal'] ?? null;
                // Duration
                $dur = $flight['duration'] ?? null;

                if ($dur) {
                    $dur = $dur / 60;
                    $mins = intval(($dur % 60));
                    $hours = intval(($dur - $mins) / 60);
                    $ts['Duration'] = "{$hours}h {$mins}m";
                }
                // DepDate
                $depDate = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'tripSegmentInfo', $flightId, 'departureLocal']);
                $ts['DepDate'] = strtotime($depDate);

                if (!$ts['DepDate']) {
                    $ts['DepDate'] = ($flight['departure']['dateTime'] ?? null) / 1000;
                }
                // ArrDate
                $arrDate = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'tripSegmentInfo', $flightId, 'arrivalLocal']);
                $ts['ArrDate'] = strtotime($arrDate);

                if (!$ts['ArrDate']) {
                    $ts['ArrDate'] = ($flight['arrival']['dateTime'] ?? null) / 1000;
                }
                // Aircraft
                $ts['Aircraft'] = $flight['aircraftCode'] ?? null;

                if ($ts['Aircraft']) {
                    $ts['Aircraft'] = trim($ts['Aircraft']);
                }
                // Cabin
                $ts['Cabin'] = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'cabinCode']);
                // BookingClass
                $ts['BookingClass'] = $bound['flights'][0]['bookingClass'] ?? null;
                // Meal
                $ts['Meal'] = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'tripSegmentInfo', $flightId, 'listMeal', 1, 'mealDesc']);
                $res['TripSegments'][] = $ts;
            }
            $boundIndex++;
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
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

    private function getFromSelenium($arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $responseData = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            switch ($this->attempt) {
                case 0:
                    $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                case 1:
                     $selenium->useFirefoxPlaywright(\SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_100);
                    $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

                    break;

                default:
                    $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
                    $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

                    break;
            }
            $selenium->disableImages();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            //$selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook");
            sleep(1);
            $confNo = $selenium->waitForElement(WebDriverBy::id("bkmgMyBookings_bookingRefNumber"), 10);
            $lastName = $selenium->waitForElement(WebDriverBy::id("bkmgMyBookings_lastName"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id("bkmgMyBookings_findContent"), 0);

            if ($confNo && $lastName && $btn) {
                $confNo->sendKeys($arFields['ConfNo']);
                $lastName->sendKeys($arFields['LastName']);
                sleep(2);
                /*$this->logger->info("confirm dialog loaded");
                $script = '
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/\/ACWebOnline\/ACRetrieve\/bkgd/g.exec( url )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
                ';
                $this->logger->debug("[run script]");
                $this->logger->debug($script, ['pre' => true]);
                $selenium->driver->executeScript($script);
                $btn->click();*/
            }

            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

//            sleep(6);
//            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $responseData;
    }

    private function getSensorDataFromSelenium($arFields, $ff53)
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_aeroplan" . sha1($this->http->getDefaultHeader("User-Agent"));
        $data = $cache->get($cacheKey);

        if (!empty($data) || $ff53 === true) {
            $this->logger->info("got cached sensor data:");
            $this->logger->info($data);

            return $data;
        }

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->http->FindPreg('#Chrome|Safari|WebKit#ims', false, $this->http->getDefaultHeader("User-Agent"))) {
                if ($ff53 === true) {
                    $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_53);
                } elseif (rand(0, 1) == 1) {
                    $selenium->useGoogleChrome();
                } else {
                    $selenium->useChromium();
                }
            } else {
                $selenium->useFirefox();
            }

            $selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.aircanada.com/ca/en/aco/home.html");

            $tab = $selenium->waitForElement(WebDriverBy::xpath("(//h1[contains(text(),'My bookings')])[1]"), 7);

            if ($tab) {
                $this->logger->info("confirm dialog loaded");
                $selenium->driver->executeScript("(function(send) {
                    XMLHttpRequest.prototype.send = function(data) {
                        if (/sensor_data/g.exec( data )) {
                            console.log('ajax');
                            console.log(data);
                            localStorage.setItem('sensor_data', data);
                        }
                    };
                })(XMLHttpRequest.prototype.send);");
            }

            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            sleep(1);
            $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");

            if (!empty($sensor_data)) {
                $data = @json_decode($sensor_data, true);

                if (is_array($data) && isset($data["sensor_data"])) {
                    $this->logger->info("got new sensor data:");
                    $this->logger->info($data['sensor_data']);
                    $cache->set($cacheKey, $data["sensor_data"], 600);
                    $this->sensor_data = $data['sensor_data'];

                    return $data["sensor_data"];
                }
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }

    private function sendStaticSensorDataNew($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            "7a74G7m23Vrp0o5c9282471.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,401283,8236906,1920,1050,1920,1080,1920,403,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7606,0.726331722363,815459118452.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,1,0,1343,-1,0;0,-1,1,0,1371,-1,0;0,-1,1,0,1333,-1,0;0,-1,1,0,1347,-1,0;0,-1,1,0,1360,-1,0;0,-1,0,0,936,936,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;-1,2,-94,-108,-1,2,-94,-110,0,1,107152,599,710;1,1,107154,612,679;2,1,107155,614,675;3,1,107157,616,671;4,1,107159,618,668;5,1,107161,620,664;6,1,107163,622,660;7,1,107165,624,656;8,1,107168,624,652;9,1,107169,626,648;10,1,107171,628,646;11,1,107172,630,642;12,1,107174,632,638;13,1,107177,632,634;14,1,107179,634,632;15,1,107181,636,628;16,1,107183,636,626;17,1,107185,638,622;18,1,107187,640,618;19,1,107189,640,616;20,1,107191,642,612;21,1,107193,644,610;22,1,107195,644,606;23,1,107197,646,604;24,1,107199,646,602;25,1,107201,648,600;26,1,107202,648,597;27,1,107204,649,595;28,1,107207,649,593;29,1,107209,651,592;30,1,107211,651,590;31,1,107212,653,588;32,1,107214,653,587;33,1,107217,654,585;34,1,107218,654,584;35,1,107220,654,582;36,1,107223,656,582;37,1,107225,656,581;38,1,107226,657,580;39,1,107228,657,578;40,1,107232,658,577;41,1,107236,660,576;42,1,107240,660,574;43,1,107242,661,574;44,1,107247,662,573;45,1,107252,663,573;46,1,107253,663,572;47,1,107255,664,572;48,1,107257,664,571;49,1,107259,665,571;50,1,107263,666,571;51,1,107267,666,570;52,1,107273,668,570;53,1,107274,668,569;54,1,107277,669,569;55,1,107282,670,569;56,1,107286,670,568;57,1,107289,671,568;58,1,107314,672,568;59,1,107320,672,567;60,1,108304,672,726;61,1,108309,673,726;62,1,108313,673,726;63,1,108317,673,726;64,1,108326,674,726;65,1,108330,674,725;66,1,108333,675,725;67,1,108344,676,725;68,1,108354,677,725;69,1,108360,678,725;70,1,108364,678,726;71,1,108368,679,726;72,1,108371,679,727;73,1,108373,680,727;74,1,108381,681,728;75,1,108394,682,728;76,1,108404,682,729;77,1,108413,682,728;78,1,108418,682,727;79,1,108424,682,726;80,1,108429,682,725;81,1,108452,682,724;82,1,108769,681,724;83,1,108793,681,724;84,1,108802,680,724;85,1,108807,680,724;86,1,108814,679,724;87,1,108819,679,724;88,1,108821,679,724;89,1,108824,678,724;90,1,108830,677,724;91,1,108836,676,724;92,1,108841,675,724;93,1,108843,675,725;94,1,108845,674,725;95,1,108850,673,725;96,1,108851,672,725;97,1,108855,671,725;98,1,108857,670,725;99,1,108859,669,725;558,3,125124,1142,554,-1;-1,2,-94,-117,-1,2,-94,-111,0,762,-1,-1,-1;-1,2,-94,-109,0,762,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,3,125074;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,11039571,32,762,762,0,11041063,125124,0,1630918236905,25,17447,0,559,2907,1,0,125127,10902556,0,451A6A3E9F4679C9C47336F575512CF1~-1~YAAQhf1zPpwsAnJ7AQAAYV1NugaY85ifYzPqtvJRo6/3FJ4B9QWTY7gG7gHWvoyVjCiwMpt/bU3gztwAbapOo4OOSz460Z5r7wAZ1FLUiz07KtSmGQFCnymalu4BKySINpMzy/MeARnm6AlMWR5Xzk42xqmOqu0nHgwYC4VqGrDHLefsCIDbyP0ta+iBNtbypJxA0D1Z8NZdtm/druYbQj95QKp9Ys5w7C1W9cypeDY9Mn3Us1O/zV/Hk+igYvzxWIk+AWVbs3jgtD9OggQGMkct/kjSqrsAK0GF8lrqbWdgKTrNgie41Ez/sBIy3IUzvl/SbQTe8w+z7zTAc+uma5/p7qGdWYX3LGTJMJFIkJjjR2GGc2qT1wUbfDk2lqRxQLKsLP673TVNh0FiDfbekOSWMVclphFtx9hYu92yiQvV5MvfOD4a~-1~-1~-1,40441,892,-510431261,30261689,PiZtE,30649,72,0,-1-1,2,-94,-106,1,2-1,2,-94,-119,20,20,20,40,40,40,20,20,20,20,20,480,400,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,24710694-1,2,-94,-118,266659-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;5;20;0",
        ];

        $sensorData2 = [
            "7a74G7m23Vrp0o5c9282471.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,401283,8236906,1920,1050,1920,1080,1920,403,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7606,0.19410340997,815459118452.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,1,0,1343,-1,0;0,-1,1,0,1371,-1,0;0,-1,1,0,1333,-1,0;0,-1,1,0,1347,-1,0;0,-1,1,0,1360,-1,0;0,-1,0,0,936,936,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,-1,1,0,2611,1783,0;0,-1,1,0,2871,1884,0;-1,2,-94,-108,-1,2,-94,-110,0,1,107152,599,710;1,1,107154,612,679;2,1,107155,614,675;3,1,107157,616,671;4,1,107159,618,668;5,1,107161,620,664;6,1,107163,622,660;7,1,107165,624,656;8,1,107168,624,652;9,1,107169,626,648;10,1,107171,628,646;11,1,107172,630,642;12,1,107174,632,638;13,1,107177,632,634;14,1,107179,634,632;15,1,107181,636,628;16,1,107183,636,626;17,1,107185,638,622;18,1,107187,640,618;19,1,107189,640,616;20,1,107191,642,612;21,1,107193,644,610;22,1,107195,644,606;23,1,107197,646,604;24,1,107199,646,602;25,1,107201,648,600;26,1,107202,648,597;27,1,107204,649,595;28,1,107207,649,593;29,1,107209,651,592;30,1,107211,651,590;31,1,107212,653,588;32,1,107214,653,587;33,1,107217,654,585;34,1,107218,654,584;35,1,107220,654,582;36,1,107223,656,582;37,1,107225,656,581;38,1,107226,657,580;39,1,107228,657,578;40,1,107232,658,577;41,1,107236,660,576;42,1,107240,660,574;43,1,107242,661,574;44,1,107247,662,573;45,1,107252,663,573;46,1,107253,663,572;47,1,107255,664,572;48,1,107257,664,571;49,1,107259,665,571;50,1,107263,666,571;51,1,107267,666,570;52,1,107273,668,570;53,1,107274,668,569;54,1,107277,669,569;55,1,107282,670,569;56,1,107286,670,568;57,1,107289,671,568;58,1,107314,672,568;59,1,107320,672,567;60,1,108304,672,726;61,1,108309,673,726;62,1,108313,673,726;63,1,108317,673,726;64,1,108326,674,726;65,1,108330,674,725;66,1,108333,675,725;67,1,108344,676,725;68,1,108354,677,725;69,1,108360,678,725;70,1,108364,678,726;71,1,108368,679,726;72,1,108371,679,727;73,1,108373,680,727;74,1,108381,681,728;75,1,108394,682,728;76,1,108404,682,729;77,1,108413,682,728;78,1,108418,682,727;79,1,108424,682,726;80,1,108429,682,725;81,1,108452,682,724;82,1,108769,681,724;83,1,108793,681,724;84,1,108802,680,724;85,1,108807,680,724;86,1,108814,679,724;87,1,108819,679,724;88,1,108821,679,724;89,1,108824,678,724;90,1,108830,677,724;91,1,108836,676,724;92,1,108841,675,724;93,1,108843,675,725;94,1,108845,674,725;95,1,108850,673,725;96,1,108851,672,725;97,1,108855,671,725;98,1,108857,670,725;99,1,108859,669,725;558,3,125124,1142,554,-1;559,4,125156,1142,554,-1;560,2,125161,1142,554,-1;1004,3,261856,1464,680,1635;-1,2,-94,-117,-1,2,-94,-111,0,762,-1,-1,-1;-1,2,-94,-109,0,762,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,3,125074;2,126691;3,135318;2,135339;3,135343;2,135352;3,261843;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,11559412,32,762,762,0,11560904,261856,0,1630918236905,25,17447,0,1005,2907,3,0,261858,11414729,0,451A6A3E9F4679C9C47336F575512CF1~-1~YAAQhf1zPnMwAnJ7AQAA3kNPugb7zRTSXbNyoQ9miRhKPdNtaUc59TuL7XVHRbmIvB34zE2Erjg5LbjRttQVK+S8Yd6oqcXv7oKwvj+2I2PO7VkuqD37rsvqDP2zcJ4CS19TIQxNOFUZbuIuve229jKCDvImH38mx5lYXN3qYnD0dv9IXnaDt3PymU7W6CUHoFpkT2WlSbdRKpUNHFZKz2LiBrWDy+8gWlh9yHEjjAkU2MWTG8ABDgTpefAhqf7+ZnXlo+fYh/dQ+CfhxC++j9dWfW73SSiJXS6fIlJOC78FpvGeRPkN1cVgFvkVw4VNYIp9ZEJZuP1NYxG7N/9YP2BIlNsOrKwAVw8y19BdZSkQbQdUn6a3qLI1DNKXS4zO/7NuLsOkwyHyZ6qRJtg47OcHl8w3a91kFF3JU6F1AOyD9fCkWAMB~-1~-1~-1,39432,892,-510431261,30261689,PiZtE,70592,63,0,-1-1,2,-94,-106,1,3-1,2,-94,-119,20,20,20,40,40,40,20,20,20,20,20,480,400,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,24710694-1,2,-94,-118,274434-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;3;20;1",
        ];

        if (count($sensorData) != count($sensorData2)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData2[$key]]), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function airCanadaSensorData($arFields, $seleniumSensor = false, $ff53 = false)
    {
        $this->logger->notice(__METHOD__);

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $referer = $this->http->currentUrl();

        if ($asset = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#") ?? $this->http->FindPreg('# src="([^\"]+)"></script></body>#')) {
            $sensorPostUrl = "https://www.aircanada.com{$asset}";
            $this->http->NormalizeURL($sensorPostUrl);
            sleep(1);
            $this->http->RetryCount = 0;
            $sensorDataHeaders = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json",
            ];

            if ($seleniumSensor == true) {
                try {
                    $sensorData = [
                        'sensor_data' => stripslashes($this->getSensorDataFromSelenium($arFields, $ff53)),
                    ];
                } catch (UnknownServerException | TimeOutException | SessionNotCreatedException | WebDriverCurlException | ScriptTimeoutException $e) {
                    $this->logger->error("SensorData exception: " . $e->getMessage());
                    $this->DebugInfo = "SensorData Exception";
                    $sensorData = [
                        'sensor_data' => stripslashes($this->getSensorDataFromSelenium($arFields, $ff53)),
                    ];
                }
            } else {
                /*
                 $sensorData = [
                    'sensor_data' => $this->getSensorData(),
                ];
                $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
                sleep(1);
                $sensorData = [
                    'sensor_data' => $this->getSensorData(true),
                ];
                */
                $sensorData = [
                    'sensor_data' => $this->getSensorDataTwo(),
                ];
            }
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;
            sleep(1);
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
    }

    private function retrievePost($arFields, $seleniumSensor = false)
    {
        $this->logger->notice(__METHOD__);
        //$this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36');
        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));
//        $abcks = [
//            '17BFEC1767A6564576AA8B8D1BEFC0F3~-1~YAAQjTxRaP79K2WEAQAAcCWRegji/noT4q9D3bMNW7snrGTuwilwIp1gxthd7XkWecaMlWRyE1W5yorVwmh5UdLHNSnHvN4zYUu7ouU19eLJuG+OcTG2ISEjf84x/RzgvHsYW7SfvM4Yxh+yVtRuABYswP3a4YR7usPwbEbn9FlHXmvZ9AUJlc43R8LMnn5H8FzKqcvCS9YlhOmjlbGkMkDv2WoPghaNS/aetIGzdi6eer+T96KXGJ//UjG/5tpD9hluW9D0GtMblVRwl+Vrxc2oXkd7A5pklKlT/Oc3VV+4jYYptxukq2rwJfD4ult/wKqDVl7IolLuKcuyAVj45yZgSFQnaMhzzymQk0lfmUIjbAD/rEpEX4GMhg6VcLbUddkQPrU2kui7ABr83kwrnO5mvUx24Gd+AzLTaMxbVZYI3BpPslCt~-1~-1~-1'
//        ];
//        $abck = $abcks[array_rand($abcks)];
//        $this->http->setCookie('_abck', $abck, '.aircanada.com');
//
//        if ($asset = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#") ?? $this->http->FindPreg('# src="([^\"]+)"></script></body>#')) {
//            $sensorPostUrl = "https://www.aircanada.com{$asset}";
//            $this->http->NormalizeURL($sensorPostUrl);
//            //$this->sendStaticSensorDataNew($sensorPostUrl);
//            $this->sendStaticSensorDataNewOne($sensorPostUrl);
//        } else {
//            return $this->notifications();
//        }
        $response = null;

        //$this->airCanadaSensorData($arFields, $seleniumSensor);
        //$response = $this->getFromSelenium($arFields);

        if (empty($response)) {
//            $this->http->OptionsURL('https://book.aircanada.com/ACWebOnline/ACRetrieve/bkgd', [
//                'Accept'                         => '*/*',
//                'Content-Type'                   => null,
//                'Access-Control-Request-Headers' => 'content-type',
//                'Access-Control-Request-Method'  => 'POST',
//                'Origin'                         => 'https://www.aircanada.com',
//                'Referer'                        => 'https://www.aircanada.com/',
//                'Pragma'                         => 'no-cache',
//                'Cache-Control'                  => 'no-cache',
//                'Accept-Encoding'                => 'gzip, deflate, br',
//            ]);

            $payload = [
                'bookingRefNumber'   => $arFields['ConfNo'],
                'lastName'           => $arFields['LastName'],
                'iataNumber'         => '',
                'agencyId'           => '',
                'agentId'            => '',
                'SITE'               => 'SAADSAAD',
                'COUNTRY'            => 'CA',
                'LANGUAGE'           => 'US',
                'countryOfResidence' => 'CA',
                'LANGUAGE_CHARSET'   => 'utf-8',
                'mainbundlev'        => '',
            ];
            $headers = [
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.9,ru;q=0.8',
                'Content-Type'    => 'application/json',
                'Origin'          => 'https://www.aircanada.com',
                'Referer'         => 'https://www.aircanada.com/',
            ];
            $this->http->RetryCount = 0;
            $this->increaseTimeLimit(120);
            $this->http->PostURL('https://book.aircanada.com/ACWebOnline/ACRetrieve/bkgd', json_encode($payload),
                $headers);
            $this->http->RetryCount = 2;
        }

        return $response;
    }

    private function sendStaticSensorDataNewOne($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            '2;4469554;3748401;12,31,0,2,6,29;u3iQIKi!Ha$<KKP@,3FXX]Q!cx$~z}O3Pd+6q5`o|0.?mhsdNnxaFEN_bxn7aflLn8{ap MkA_/Dwy1!l+H>v0%K@7ncdm]R1RBC@][s|>#H~3:+uzz0NZ_ 3[`x.3)~BkXTff>t]!l/j B6)U{pdx~JZ9<ez3!hHkj@_A|T8XS|R5X[%gW&{<tH-5sx_x3e=mzwD`4<Z1zQYI>zvP$.YI,bRTBQXTJw+5ct@mAskQC|ZwB1div{(Il7P<c<S{Jn:{Ua,aE%p^[<{nt$sKb`nGZ<gTp2SnGJ3W_Sz@m,N`Qu204AcM9HHe~WiFug,NVZaLlwZ2.lbc`?JvYkG~ez}f3wS2~X7QI8Fl.@b:]E^HV8mx_Il2}>Z>hP+%aNORp<4(xiM3/$<74,8x_K@CBhx^#*lfBXhT{.0*UtjVRSpbrH%3Gu|.sH1wLy6hxiG4g2)%T!zgya&4B;SK@m;53l3%b.GxHF%q4qzwA&XBu~BMkeumGww~1i)unsgh)V#MAEI,lryp+_N_%(B@FOS`E&NE]j^LGP5/0B}nt*Y&x)$?6k_/lnXh~,@1)gf|x[ {|dxZL4>?fr4b*=rkqYgaxa7C8NYin$J#`T[|rVqf8.^@{<9:ugYw7yfrs5/N^npv,Pz< y0:v$719(LeKF[%QC28<Pr/GZdoiWnCE7D31S*bfn>Uc^b1aE.@P&;Yk:FbMwcF@<SiP{W4m7w4[LT}GPNRm5M,>eD+S3X;4:QvP!|N.cBj/-fm~Lo`nd<!0iy<bb6thw{oY<h}*aT{SIsOY71IaLF H )bTOM7^e:jF-,OOO<mCG7|hso:OAi?!QmjI%7rcjxt:KM*`q5Vb~ju0sxKC$#8AMMJ-nUsXjw*q8TTZd/cuZqaVb}!GR/$}DwIVu[Tr7zVI,9#6&|X;]{ney<s:/hbG1Y-~q>Wr@*hnaeXjhn?$Syrx4O}dV::|e6xAneY&;?#|x,:8>=,pCh`~ouu0aZT:CdY OHGx8?|*6AKX3{/OqRUZ2P9u!gdZX2~O)|4j:@$14S^vG3+~Q(;=mm/s{n~lf~HtCYdB#tw&/[g +A<J)OYoS(u6dispy_HfHf4wObRmspY#=Tu_@y.>~ZMMZLkjmqoH~>U;~(,Pc7Wm3QPbA2Gc>|/!95W^we7=/FkLFZT)@w+`tIKbPFavYCPA`l$@bT]<4Tr]WZ():oBwQ<x{?(M@U?7LvVEDg&hjm1AAH;Z.]FcIsB[/AZ7hAFroHX(9`8Uqb2jVUp3m=~E2d@P:lsGlci|*ShGN~6FD:tDC<h-gJ-[DoZw7?C%Q|GezW{=`0+M;u;++.Ms_6 Wo.p?a`rZ&31]tKnImDY~%3oVco2[ [zaU1u:y_;wXnh5-)e=7Kl/CH^s}echWg_$JUPOS`KZD$5l%i4+]4Y.=M>(EL<`SCA54nbG /X7/QX:P2sa4pC*/^<)GcM~yhm4AZJ,o;Im*P=smc`-iY,aPl/{QG%13%9(:&4l[%A;3QB+~kZ^7$)4]$!r9dR;84PO_!Xm5`3v1QkLa+AlMK&b=VZd;OC>KN^Tyi ee5hreBVI/5t~^[a2^:2<sK0S94Ol*-5%!V,D9askNQ`&C>6-kDn9$OM8v)L`t 852vCx6uKX-b`mkhaV Xywky{9Wn>Nz!Du}%_wj<D<-DCJU^NO0x!6w5l<(A:)]z00F-s{9.haSjy.2>BE&q1A2v27UqBk8Q%1Bpp:L:BvD=Bfg-z1mR!A=~=8t:j1a!ZmteTuM>*.]IW$$v#E*1!T9hV ZqS?SKrFm7]MhlS`d@X@Snh#92]f(6,6yF4]>4Da.s|MyOA(*apGi*>QLO~VRPh5tM*-GV]FF?$**YNo!MxNl<?dbdCn#)ZtQ7*Z%170[kdH#.$J]=[A];Xtos0E U)R6lmGEsd6aozgBJ_pB|< O*Oto(dSnmQ!sHE6/[Y#_y.qObq?tFu_,q>ms3m}r0q:czKXW*@IYc#~o+Bl|pF:sXxOi{i_/@yYIF;?@/(PHZei_lSwHMB}~At?D]fC:A$z`XW3<g{[Kd{!$Fel`Q^=7VQn,UoXrIIo;yB!RE,+HCGI`20BHIK1  @dC5Elpdzh,.v+;s]*Lwe#WH!%)KRcO9WtS9tmRH.=iMT|.#dV!RJ/yrxP=5:unLKgUJ#eY(-D/N:}vOl^jm#ae+C(&_xkff#sSaS>5Q<|s|Lg8*/3av%(PgG)Z[+u.pMw!^:snOZzp3$t9v#LN8a}4#ZJO|n_8gpY1K/X:F,.2v,m%G$*HJ=.[TZv#C2QPJ5.F<b`|H}GN5gNLI!em2GX=#S+PKAQ5D01 t7narbTGau%@7SCt6$v`pSbYPWH!Rq]EH[dYPqy<dZ;fGq_;5#T/cmdeJ^1[Tp9#BTt-IFVPjy7k}>`qD!;QOj:*()F4-%a:#y83Y#:.l:Kr${VaF!T>AOm8J%:@BP[L?_lw@qQ.=?!~NBv3Hf;~/=at[zk0o6[[_?$GV&WC}|hY6.c;O^@07E{8|B6hGe<I`3|9M6Y7AF31wk=yfAB&@PtO7m`(|Bk!7,y@T1cbNbDN?Fw]xC~v-N1W3#xS.a<R|j22r/crWlim_^4{Nkf]R6{HDuyYJ._T=?9g(qP;&hZvjmiJj;/RZyp<2{uWX5Urc_e;_aGF@^Xvm|bX-;e8&S<WNgjQc<*J##{]8!r#ygLlyphi@vz)T$qWXNZ<F2 C#,7~OT2Hf}8Vcvd.F#34kA>&e(3Y=|{z+<CSaa3Q7Id,7NQ2a!/{SPUlR|9,s&hf}0N}9p>>Cm%`?/c<+<V@S3_Ucd|x<6<qgy6{jz ?_/?(1gkzaAs+t`Wgu|:sj&]B2T}mSkk@[7`>S%,K=5EkePL.p NXzu(pf.TB;V_6X?lGr4McX]wG=;M?] Kc,+;(JY*(qUO L/[<cAZ(#w;wD6s([gG^+5G:6HEw5^~a1uL9fOe,*:d.1(Q_.GWtu$M>IlKRVeQfXL.xL8V/-6}EJPya;|c}R 7;8{Yom;DXdzaQ/#Ev+9wH]a7JPH)}v%Z7Ul]a$AplUF48eQ,jp]hMl;EUtJOS6c]B!drn}Y-Hk`KI4/afIfRyWsx<OT@CTTXE_y5x_ER|g>)6Hu%k)F= =TTbO/kQ9tVixuj-L:jzhzhoi`<`%oj^zBtJF*$U sh}F94<&=VT,k3tenSAt/8/Q#f;}fl_N-_7]TLWDL&<ANFe[oVUzMGZtC=noM%Fjyd7druIfoXca&*LQV~;~Ife?`G.</-$!g`6`[pL($|70Q].(Cd@wOQgK|lz[u_YgEIxOhR.kh-+2fa^?b[Uj3WiI,[CSH*&:1&XD8E2d:e~=yv ~NP]C#AQmwKl}z9>XCH)u0IX#)<J x4k&o{+qe L1jkbz0OMo]qErAPH6j)Lq4fGd&6L4.`whDRd{.=^vu&ceb!I2Ui>Qf];i=@UWSuB4~4WdyKD&$5*zBhh[|Bi4~E/T6P^]h-iM&nA4Hd!<v.uwQ_&N@[:pR.tP+@_leMvtLb@jdaLK`)$xY$,h1y_]+hZ{f)^lmEw~#Pb.T6P3F`hiKG4Bt9HaU8$dKuGRU5}(0KlPxUq]$=JV8l.3v<#gcQO!r7s*=4lvUHu^-m5*=r%-xTq3k81rqce@1pIkt:HhxByPIuW@[6EcQ?_mN5/nhZe[N)j}I;0EO0^H/qXsu$>.rd0#2@6c:*F-59[Odl*%lsD`|yKAx)l8.}PD;KO>+ybOZv4X+MFebDvC.X8<xUlA@4p7$ozu4)B8O>*d+G,V{iJu;7,~2Ht` !s.=^N=c`#$Z#f](f~lXiRJ5}x3U9^FL|#bAJWc.3TRHb=<qR29?%x}q)t}m=BE|.n|~M[v<Wv{33sMUe*`?3M !ymE99uX]xaDQMirEP*?f[gK1MhP)}!1,NIdJQ+7q+ka&0`qJ^4.Pu%u5VD$_fp+/VMpd]tu5c{qV|?f0R6Q)8?9}~n(>F rGDJbj}fFy>4,5M+I#?.J}.KG-n9/`!v}ZW;Tk|kS,JJFe{Wya*o?~ IBIl?w,rFt4b#ASmeh$]YZ(Bz9:hTq]t>qxwHB5YnDd2Is&NJL~^VAc^-VV)*X>U 1:nHRG^+^$LT[=.~SoVXT9Z;swJ>|]4kgmXmp;c&e.o3{3UQdN9CMYH<~}fQ3!x0F_8L,kdvb9zHbIr ?$d!=h?7t{ofE3qJcXn~avKr_/BM4hBHPAo lK*y8o/h@C%f&t%v:Q[[{q`xC56Hyobx^#Xc>Py4A=!r3Lt=p}W7z_*o+P<N$zf8UH35|`/:movl^bYWx5(_Y39t$_?c(jgbCI~6B&JRPj->r;3;{yhKN<@/![e)|<L(JLZlq,(w@bKCzNRd4IL*X#(ws`F#OUcFmeWy&BI0q6)w57iEwSI.?/<g3$|M(2J,Xfa~!1?V;GAT0r 8qC97<9~?I=DwJi{1IJUgvH39k7;kKfN7 ).R,dJ}-QT3JgKe[+8^ule7Nnz/uTP5#ddfG#gK_X3}eEl4Nht4!bM KltfpBv7,pG:WBfpH+:)l1fG?X-7eF-:fX@d{HrKYzC<Y77Y|{K8n@fW+~9afz@!gG/S}vEgggv(wZ8H!+9%&X{*/VqC1X0%GbB2fbHwne!:8SRm>/DhaJ))&Ft%^vH`tY}AKN)pdj}ixfh1hLrCE!_nl7`J/ QP,v4;!,h8O0M3e1~C|7dauU@C#0]&`!c$96Z&Ab7v&j:,fl#r#D+~8o`q/fE0iRh7B.dKhuXKegRsV{iM,P#3K65H_kniY[TJ77mPq%zqcuq>4=2(vP%A[P<_xdE?41Y/$-oPo21|9$0t_ABsc*x(ZABn1 S]^ff@bsPuhc+~QtQ!Nf^G?{Y~pNJu06{{tL2EK>6,W*$+Opa8>~&vsP:d5).f12|((KGkc{mXVjv}<wZNS*>4)rb*crpV{w 99EbTS8&*c@IS.bU=?30x&4%S<KV*pl[}7^Y)2TWFTp|=j?=4B@KIRJ>@Qg[3DHGb}Gd#k-CnQ0@Q;w!q z5;[EK.gGA){zzBy,T*6[cpcHb{m?HIZZ*&?FDi!fN=+6H(:m5f|TgnULu43h}o2>fiY)<%nGY[C5ooY1`Nh0tYSOps^v(7Xh0Ag;$vs)XNLa%X~Vbp9f#KJBiD.&pti)Tc:DgRJtMHDi%c:sN8j:f+{/S-|N{Zr5o#Tj&#1OW8Bgua=+bM#k#Sb_?pz!_$XIs7y0D{G-dwP%jK5)>g^x;g!#pGHZ =p@yD{GZlvY9DG(NA|w=/eb.{CG2&$*U#]$h9yCgq*G4t#.;S_`OL.~^/F^G:UV*Q1kT.l,lrW o95}YZ@N+Da]+o(`v/9nHn||};3kxQhaaS4 ZA4Z+F16!_#H/?c7?so?G51a>[z)n9_L/105<=46}c9_`U 5oOC-!Ccmv4mih*/G(:;TEdXjDu|r>Ac*t]S<<!&eQv>dQ(U<T>HeRuv,*wDy}W/&g%bxQ;fyyFkI*%5)|.YTa2 G..hAQd3nWJmUfY?~A;{p*x$A5a|q:gy~w?R:GgE{p]bx,Cwpu7)X^t`~kJ~TgbI)6-kp]I!$vI&ISevleR;l+lJTw;_]A}p6@X7,KUH$+7AG]`YO+',
        ];
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function getSensorData($secondSensor = false)
    {
        $sensorData = [
            null,
            // chrome
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397753,1501234,1536,824,1536,864,2304,630,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8291,0.703938704351,808285750617,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,19,0,1616571501234,-999999,17293,0,0,2882,0,0,31,0,0,F2F0E737FF86DB0D2B20D0DE2B39B96D~-1~YAAQbb56XHgsqTx4AQAAFIYrYwWUHcyElE9xp8G4ib+MRr91+iNf2cGLQxlDQqygF9I67FL57Eo+HsKDMCB1flNLDrJOVSM8UnttqoT4tRZE1BgeaSeIXOUt7+OBg9aHUgA45Q5Pg9diY8bn3XrddO8kudWuhycWvPlsOp9s+Y2D5qIRzoH4L6jGYKy2ymzKC1dTW1YXpM1KjnvGg8ATzpDBQsERQiLPAhxu6J/w5aqnFd29X6S4j13uZstaUNm8w+oo/7ta/mg48a5DY0eDDtUAJ1BMQr7RdUSnOag3GMeXbD1tfX50+9cMugV9v5GhH7sxFIfHINoYfRybnzZxmKUIvgcP0kdnCtWJanYvcLKK56dmfmocqM9GHk/GE4u3hIOmu0PxzmA//9eOQQ==~-1~-1~-1,36956,-1,-1,30261693,PiZtE,64284,87-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,22518495-1,2,-94,-118,207893-1,2,-94,-129,-1,2,-94,-121,;19;-1;0",
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397753,1606836,1536,824,1536,864,2304,630,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8291,0.265115078132,808285803417.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,33,0,1616571606835,-999999,17293,0,0,2882,0,0,57,0,0,F2F0E737FF86DB0D2B20D0DE2B39B96D~-1~YAAQbb56XHosqTx4AQAAjIsrYwWMIY/QSK6Jt+m/XIu9Sm9VHGs0Gn1Rits+jfY+JYzDjgiQ4e6Th7s1NB6zuM3F1ed4+7H84bqvePJdTP1Gz15KL3ckk2DqvN4XZJ2o6kiLm982WEGWXseGf7/223Ecw7HHh+EI8JLSvNnuhxRxNtrJRAz51q5Aa8h5z2PUF9CWznaFQdhN6Cj3dozarNXLGfBYtRh+JwG9y8ltT80+Kck4sg4tN7umX2qCmAnwTrHrahmANbeVuy7pqOw6u3NtxJKyM1UdVEPLognuq2qniXTISt/5Ps5R+FpIZH1CVrEAM+/6Rrq/CPNMN/Ibh5KvKTaGwORPz7e1chvxcgtAWgR0wuZh/RPBAcROhjOVzBqbIvyjQLdwaxcX9JvwtT9OGte7YhKXRVbLPW6r6ucuVRwF3GPCuw==~-1~-1~-1,40250,-1,-1,30261693,PiZtE,72148,63-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,43384254-1,2,-94,-118,211295-1,2,-94,-129,-1,2,-94,-121,;34;-1;0",
            "7a74G7m23Vrp0o5c9142511.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397752,6452340,1536,824,1536,864,2304,516,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8291,0.417698020208,808283226170,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,21,0,1616566452340,-999999,17293,0,0,2882,0,0,36,0,0,E86A4F6B133B07DFA45913F80C649343~-1~YAAQH/AVAk8SnkR4AQAAnGzeYgXfb1SVAJGOphsl3X6U+1MpzM842xbWk84C+G7KGmLhlQbGhIIfygP15/C3QIXbZ89m3dqLvNcJHsN/4FjotmyDaUuNhYAh7QxOWpoE6UuEZyr3sq5U4Er3VfzbT0/Q1lXhcR1Jkj5M2vf5zDlfLhY1/KoYPzR4gzAedbj1fJMmYOBaFsA7jGrpyzQ1jNvBbuftGFNX+awLiUSoiuEKQ38j16frKId64loGY7zhhYKkka0+rfDiXDPR1Rb1iyWpqdUA+H4zKTW0KJ8KmqqSAjGEXy2tJcC2ZfjVu3K/lc+eYqjuPL+shedo5BZ3z1M6gwweMokxU32D+s02nG8SGmvto7XMbY7lj14LV75W8ZPlSoobYxLisJ1B7rLDsd6TkuMrB56iMxlvsTsdEzyuKcdGwmIAzg==~-1~-1~-1,40396,-1,-1,30261693,PiZtE,28134,94-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,96785097-1,2,-94,-118,211304-1,2,-94,-129,-1,2,-94,-121,;23;-1;0",
            // firefox
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,397753,1779888,1536,824,1536,864,1536,350,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.207335343103,808285889943.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,33,0,1616571779887,-999999,17293,0,0,2882,0,0,40,0,0,76AF9ECBBB0EAC1D6F3FD500F33A5C1A~-1~YAAQbb56XAMtqTx4AQAA7qovYwXHvKeMqCrGX2d+f7Xe3raJKSk4k85XJcdAoY95m/tb59myBc+/uKGmq63ztI7Wp6eCnYiYy3Rd2OD2P4EEHJXk6ostRPqKNMc9/+RxiSOMtTTroUuK4oIerTlGm44FC1nmgSnmgoRE+EfVVin7VNRwbMvD4+4ROfOMKgTXtoNATnEAAdr8urnNaN+skIC7FRnZWohfh3lDJ4HkEJIWS2l6h2IdLPfprQEQgDkixSDst7Po+u0b7tMNyEUhQVPpZkQa7mpJm4/qopu1h0SdaSsAFwvzVdqihzag6A0go944rv57o9c6VEEyZyPHpE2yn/Dh28qd/U4awQdL1eUPnoopgZHTRbgK91E8QFrTvihKphCbUlDqF5PaFyzXBy2bYAL9GQvs985S2Q75JzN9i/+uIN3zKfE=~0~-1~-1,40297,-1,-1,26067385,PiZtE,72802,57-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,3892615062-1,2,-94,-118,209559-1,2,-94,-129,-1,2,-94,-121,;11;-1;0",
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,397753,1877371,1536,824,1536,864,1536,618,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.09692578348,808285938684.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,45,0,1616571877369,-999999,17293,0,0,2882,0,0,58,0,0,E3E7C4AC53C9F10105533A3618507B39~-1~YAAQbb56XC8tqTx4AQAArUIxYwWnd6QRdm+MeEarUN4sScEmwmLRqK19W8aM3Ojau9VZbX9BFCljdVAinaao2TyjbJvJ4ohIcVBSFLBqMwUY5ljDu91tMYJfE/b3KKTGSQAnWSGlYRZ+rEek/ei+qXweDr8HW/7AWjB5YQLdC09KQnuQdBtIFYL0wpMcTGlxfN6GhfBI8/e2Lnt4eStQJXC5vr+OnxgaBjByr8hxpzqh48cOH1K1ynT9e+MjmcSQUBg8VbEykrd7uoZ9DBwPdZ0YI8q7VmzD4FobhwGdISL52WkxTY8v57TTWtDqndxyrZI/rNDZxKCgGUyZ1/1TsA+Z08MUxsq5trIqOnjtJ6jhtt7YSD0kxs5k5tFdyU6ec34vlskcp7HIbPHNr6Q=~-1~-1~-1,37448,-1,-1,25543097,PiZtE,86273,59-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5632035-1,2,-94,-118,206712-1,2,-94,-129,-1,2,-94,-121,;18;-1;0",
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,397753,1975375,1536,824,1536,864,1536,618,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.301002996150,808285987687.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,37,0,1616571975375,-999999,17293,0,0,2882,0,0,44,0,0,E3E7C4AC53C9F10105533A3618507B39~-1~YAAQbb56XDEtqTx4AQAA/kgxYwX4E5S2zgLODUHCCrBnvjjYM8+j9fpca3tYLxM6hrPQji0WOwoy/eEEz/ltkccIj52YXxY2ED9Vr10ps3hfMILzhrEZKMJYWsWdC4f9w3e0skiTknXMpvSUlzKprBhOyBSs81WFBtvJszrEQ+EYEgBW2A1/xKAN1gEAyluRYWtbpOpkOv5zUEeiZC0lmS5R0gTS6R/iObuT+ft2qPw6MsNjYg8Wuie4E9ysOpalaYKycZ4NOZjHUyOA4eKROjbzZRoyH0n998bFBY7nVoppEfhw2ttZaf1zo7kZW8lRg1SRnQ+k/yxrm9ic66N5rcQcmKU6OSoayUpnSRCl2GVBCrBw1+p/q6PNoxnVaHNo+ci9GgzhronktL2K7yClzlezoAt7B71aHd2dd6u3aomnKNhOr319BCc=~-1~-1~-1,41005,-1,-1,25543097,PiZtE,76413,72-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5926101-1,2,-94,-118,210268-1,2,-94,-129,-1,2,-94,-121,;13;-1;0",
        ];

        $secondSensorData = [
            null,
            // chrome
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397753,1501234,1536,824,1536,864,2304,630,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8291,0.987507912493,808285750617,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,463;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,885,0,1616571501234,63,17293,0,0,2882,0,0,887,0,0,F2F0E737FF86DB0D2B20D0DE2B39B96D~-1~YAAQbb56XHksqTx4AQAANogrYwVCtSmHfVndENHPI+0iGaCvoWWRsi3mqTK2yREsJPxeSddKPG71wJenU3EwZmCwkXQ0RK8O0uNgtd1fzB9cNgsoAf2RyTirELr2Lq+fp5HAxXhrIKNbFhOLTie+zRRLO0+KmWp4XYrJ7n/vKU7udrxrCLLzOfHVldgoq+6+0Sm/a4VntGUCNTJeWMj4NYLfI1AV8V1H6oQc2DRlkxdZVzGyTGvyT3WlVLA1yDFLBCLPVdSkjYCzPrS/QHZQKl6fHVL4gJZnyxaEVNnwuoZiOdHX1PGn5sc5xc/othhyxD3VbHBI4ye8gdOMI0frxD//TCO80q90UyD+uyvZvAoUow1IWviJAZ0jeoDKtmkuk0XM5mn7mOkJcF/0yucejMupnjT2JUuH/8M=~-1~-1~-1,39103,983,381771464,30261693,PiZtE,11036,123-1,2,-94,-106,9,1-1,2,-94,-119,64,98,77,61,82,54,40,40,68,42,34,797,488,315,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,1454252292;895908107;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5540-1,2,-94,-116,22518495-1,2,-94,-118,212860-1,2,-94,-129,977fda0e11adc284b06f48b4e34d71100831f59c86e2d390b6006b7193281ce7,0.8333333730697632,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;35;47;0",
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397753,1606836,1536,824,1536,864,2304,630,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8291,0.12288932861,808285803417.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,991,0,1616571606835,91,17293,0,0,2882,0,0,993,0,0,F2F0E737FF86DB0D2B20D0DE2B39B96D~-1~YAAQbb56XLYsqTx4AQAACCQtYwW3ultVZxemj6B7A/VJU1sV4yuBj8+9navthiaf6042TySPhRGJCjzp2Iz0ybRYvB4E2TAEjQTF+VH/iM6BMihox8YjdiNcNJ2hjPVjOclhUOdiNsISk/EMr8eU1nDD+TpW7soRfBXJ7IdFcy1ZxB139hfOQwdgsupdcpN2kMvlpUL4w0+nz3pIsL6182llySxLZEkkMlU4R7IwK4KI/gezI1TBXyvKvUtmuX3sXM1AiPUvz369hhpssO09ztZaP9IiJIYmJiVO6y+NNGzEb+xKLgKtq51Bu2KoEuwUhvAyh52hpGzZ3GV9bjzyQqjIMCBAC1opTCQIhJ8sScOtmyQPMF08ezmxbsxoJkef4tlAMIRpyagar+XgrWEIFKqhu5Tl1rzP3nuxYJ6JgkKbVJG36JE8PA==~-1~-1~-1,40822,77,1079789831,30261693,PiZtE,40975,100-1,2,-94,-106,9,1-1,2,-94,-119,32,86,906,39,88,66,37,34,31,12,6,829,449,354,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,1454252292;895908107;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5540-1,2,-94,-116,43384254-1,2,-94,-118,215289-1,2,-94,-129,977fda0e11adc284b06f48b4e34d71100831f59c86e2d390b6006b7193281ce7,0.8333333730697632,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;42;74;0",
            "7a74G7m23Vrp0o5c9142511.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397752,6452340,1536,824,1536,864,2304,516,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8291,0.15201571476,808283226170,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,1066,0,1616566452340,64,17293,0,0,2882,0,0,1069,0,0,E86A4F6B133B07DFA45913F80C649343~-1~YAAQH/AVAmcSnkR4AQAAU33eYgVuYbAam9DiuxS1xWHgZoR1FXT5mGnlxsaETieiJ4jCFKLUbEs4cQ3x+i88Y3cS90AgTG7H4FSPN8GuuRR2a/R7PpP/RS1MtTKfa3Kcehdgmu5xW1YNY/wkiqAe91M/DiauBJnjThzLObfmM2qnxKNw19qFrS8pXAbfU1yKx60Ji06xvdeOIaVHltZvryQiinmbjsPhPQjzazjkX9tRYBBeF/G4NM7FSC7y4nlgnGNZuQEyLxehswm1PrgqdIENhF8dX1q8wM7211JPMp+uQ/pjh59NJxoWZt25gYffvnd+5jZ5fBaRBedYse2UFz0/6tMNiIq1/w12dHM4clPUHghFKC8YRQBKl3cHxU+m0GDk0Xi8ddNKwZFzMkhPS9Iio+03TRoG0lAoYtwXtOWAeZMMcNx2KQ==~-1~-1~-1,40172,366,125269488,30261693,PiZtE,15191,40-1,2,-94,-106,9,1-1,2,-94,-119,29,32,32,31,52,55,11,8,7,5,5,399,277,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,1454252292;895908107;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5540-1,2,-94,-116,96785097-1,2,-94,-118,213334-1,2,-94,-129,977fda0e11adc284b06f48b4e34d71100831f59c86e2d390b6006b7193281ce7,0.8333333730697632,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),95f5b71fe531f867faa814bdd4050dd8057206d53ecec1163523560525884870,33-1,2,-94,-121,;40;52;0",
            // firefox
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,397753,1779888,1536,824,1536,864,1536,350,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.18589980092,808285889966,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,930,0,1616571779932,17,17293,0,0,2882,0,0,932,0,0,76AF9ECBBB0EAC1D6F3FD500F33A5C1A~-1~YAAQbb56XAMtqTx4AQAA7qovYwXHvKeMqCrGX2d+f7Xe3raJKSk4k85XJcdAoY95m/tb59myBc+/uKGmq63ztI7Wp6eCnYiYy3Rd2OD2P4EEHJXk6ostRPqKNMc9/+RxiSOMtTTroUuK4oIerTlGm44FC1nmgSnmgoRE+EfVVin7VNRwbMvD4+4ROfOMKgTXtoNATnEAAdr8urnNaN+skIC7FRnZWohfh3lDJ4HkEJIWS2l6h2IdLPfprQEQgDkixSDst7Po+u0b7tMNyEUhQVPpZkQa7mpJm4/qopu1h0SdaSsAFwvzVdqihzag6A0go944rv57o9c6VEEyZyPHpE2yn/Dh28qd/U4awQdL1eUPnoopgZHTRbgK91E8QFrTvihKphCbUlDqF5PaFyzXBy2bYAL9GQvs985S2Q75JzN9i/+uIN3zKfE=~0~-1~-1,40297,714,1717389443,26067385,PiZtE,10134,45-1,2,-94,-106,9,1-1,2,-94,-119,200,200,0,200,200,200,200,0,0,200,200,1000,600,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;unspecified-1,2,-94,-80,6461-1,2,-94,-116,3892615062-1,2,-94,-118,213049-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),240adf671f4531cf490c21464a7bf43c343023e1a98b7ce49f23a892965b0942,24-1,2,-94,-121,;15;50;0",
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,397753,1877371,1536,824,1536,864,1536,319,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.209214036104,808285938684.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,914,0,1616571877369,18,17293,0,0,2882,0,0,916,0,0,E3E7C4AC53C9F10105533A3618507B39~-1~YAAQbb56XDAtqTx4AQAAZ0UxYwWvHr0JFBxMPZU6J5oDFgZUJ4h2KwnMfMya45hUYGFG/AJFkyBmB3knmQ+2wKK+KR+HrwK7gmdsPVvQl8MJuA5q62Z5P5X1EJ+8Vz9OuTkwRIvPV5A+PbWQamae/5SKYsB/Lxvs0e0iNXyMVaRW5BG37S4gjGaQoyonpL4Xk/Dk1S4teK/L7Bl7QbYZU2JHRZjS8aop0VoqE6QjWX3cY9ZeZX9x7guv+zeeGsDJN74fruO7qDM5bfB2B7wLcvuykp2AcXWxlbe55qb/ezdgptrvKf/8ZT7Awtc0HuqlZsJfLHhRFIaGR4IeAtpdggcWbxSnBIqB1v/b8yhfovos2ho1GFRBn4lcMcCXuz9TOZ+kaiK2w8qCqu2gvvPNAAvhR4f8fe2fRASY~-1~-1~-1,38493,302,-312004420,25543097,PiZtE,98214,87-1,2,-94,-106,9,1-1,2,-94,-119,400,200,0,0,0,200,200,200,0,400,0,1400,400,400,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;1-1,2,-94,-80,5343-1,2,-94,-116,5632035-1,2,-94,-118,211119-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),240adf671f4531cf490c21464a7bf43c343023e1a98b7ce49f23a892965b0942,24-1,2,-94,-121,;16;69;0",
            "7a74G7m23Vrp0o5c9142521.68-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,397753,1975375,1536,824,1536,864,1536,618,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5553,0.747968625373,808285987687.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,0,1,639,1313,615;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,2600,32,0,0,0,2568,849,0,1616571975375,13,17293,0,1,2882,0,0,853,639,0,E3E7C4AC53C9F10105533A3618507B39~-1~YAAQbb56XNAtqTx4AQAAkMMyYwW+MvQMVrTQzIRM6Sn5c3h5RvYiwBWoJOmRTwbwCo9AFxwVNblnnkZY6YwrTRtmDpYgZPSY+sS174Jii71iygeL2IpF4oI9YTCrFgl4TNbNKI4mK71xTMTOMZsGPeTOgFiahui1+KT/54sZoFYK6vUDq3/t8ucfXuA/TcEspx+1rmb7GmMkmkK3TIkg7wbd5RZSDsT2Mn6IJdMxu7Z9WSC3gWytHqDMeq7iYIp2o9pBFUKRHJA5VZ2hinrB2/NPMFA90nSuz7B56CYRxcHHtxAd3ZKZoAZoQb+kRyC7dEcnVFgRUp7yYpbAIhjNWX06GFVvUjfiHluW6DT5xE8+jNrUMrjJTioxitP6vwyNO0KlcuBUk78yd+nNH3D1UwDcsoh8WA0w0hA9bWIUMJvEOpcSkR2IqrE=~-1~-1~-1,40342,379,2061509280,25543097,PiZtE,75733,96-1,2,-94,-106,9,1-1,2,-94,-119,400,0,0,200,200,0,200,0,0,0,0,600,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;1-1,2,-94,-80,5343-1,2,-94,-116,5926101-1,2,-94,-118,214046-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),240adf671f4531cf490c21464a7bf43c343023e1a98b7ce49f23a892965b0942,24-1,2,-94,-121,;14;54;0",
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

    private function sendStaticSensorDataAltitude($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            "7a74G7m23Vrp0o5c9168201.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,391170,7547305,1920,1050,1920,1080,1920,579,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.833875101416,794908773652.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-102,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://altitude.aircanada.com/status/login#/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1589817547305,-999999,17007,0,0,2834,0,0,4,0,0,756E98F0682A5809CDD4656142AAB028~-1~YAAQXDa50EROX9BxAQAAb5WCKAMUwbBVhMxpd8xJ/OAgCJZXZ4rlMmzMFBXSojnVvxn08XILGv6L2Zrvrs0PGS5GcW9cp/VUyMu9pHzUS5NFhw2OhHmh8Pxwo54Y+D/3JoZFD0KftPs1xkCZkuc9m2HyVxORO/DeerbBhxGn4jLznd2Ntpfsvs4AmdzcdcQLNst9hiMDDv87aLHqCEq4aocO0PvAH1ut4H47QN5u4dLPJyqMaeDvCPduRnHHa8IZhkjtxatdLEmCmPT00tynrjgl4bdk2G2ai5gD9JgxBnQdSK2bAvBnJnyLNfzD~-1~-1~-1,30130,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,37736495-1,2,-94,-118,82650-1,2,-94,-121,;4;-1;0",
            "7a74G7m23Vrp0o5c9168211.54-1,2,-94,-100,Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:74.0) Gecko/20100101 Firefox/74.0,uaend,11059,20100101,ru-RU,Gecko,1,0,0,0,391170,7638342,1920,1050,1920,1080,1920,499,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:128,vib:1,bat:0,x11:0,x12:1,5579,0.728127713364,794908819171,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-102,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://altitude.aircanada.com/status/login#/-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1589817638342,-999999,17007,0,0,2834,0,0,5,0,0,FDAD8408D22663F58C1364D541DC5BEA~-1~YAAQqZ7IF+Lx+M1xAQAAV/mDKAMoGGhZjsKWrw75+jDNRj0M5JLF6vfngi33p1v/yv1E5C+55uiR+r4NZeydPjwPuiDALgx5B9Us2sVIHhu3+KvsqsTPD1XEfn9cyNHhb+FESylj8boTDEWqT9oC3gi2H8573S0IACe3TZhu4rHMjuerG99+9r5Bf2pFuosNRbuOU8E+u5XpzK/A7v9U5cqAJhurXDFZarWnQ/rHaR8c9F5xuGt58ygEZ9tDHUZlmCVj9LqhQkH8htpuREYwXIgDoHBX9o2S8lyo/1zhU1RZdhb4dYPkGm3WDFRG~-1~-1~-1,29263,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,343725215-1,2,-94,-118,80093-1,2,-94,-121,;3;-1;0",
        ];

        $sensorData2 = [
            "7a74G7m23Vrp0o5c9168201.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,391170,7547305,1920,1050,1920,1080,1920,579,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.374064410187,794908773652.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-102,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,110,-1,-1,-1;-1,2,-94,-109,0,110,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://altitude.aircanada.com/status/login#/-1,2,-94,-115,1,32,32,110,110,0,220,529,0,1589817547305,16,17007,0,0,2834,0,0,530,220,0,756E98F0682A5809CDD4656142AAB028~-1~YAAQXDa50KNOX9BxAQAAyJuCKAPGsRNToWtz7v/Q2RzPb5tu+Qf4LLvx0vePvDKDIVrlFK1iMIK3/1p+/7aDgHi+6CuhmAbX+F/UACQQuQuhi9wDkj2q0H6pz2e18r+6lCSCc1v17t8nrJuDkbH/OY1KlKEnYph2kgo/fafXTA9F6/q+F6Y/nINnKT5Gg4BhopY0pX67Gk2QjME2PBaNqEv5V9Gfoz3nehe8TvHPqmBgf+FA/1chOynBhSWVHd9koAfXDLmhCtssRx2fIKiseG/OlbKdA0WJeMMyTb3bd3kNpQ/gfxRGp+AkLiI1Z4xLkv++nVJGyjMJHrA64g9SY8TfngtfgCoU~-1~-1~-1,32134,443,-1014205048,30261689-1,2,-94,-106,9,1-1,2,-94,-119,116,70,74,72,108,98,69,57,63,56,54,50,64,326,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5585-1,2,-94,-116,37736495-1,2,-94,-118,90899-1,2,-94,-121,;3;12;0",
            "7a74G7m23Vrp0o5c9168211.54-1,2,-94,-100,Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:74.0) Gecko/20100101 Firefox/74.0,uaend,11059,20100101,ru-RU,Gecko,1,0,0,0,391170,7638342,1920,1050,1920,1080,1920,499,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:128,vib:1,bat:0,x11:0,x12:1,5579,0.358965109179,794908819171,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-102,0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;0,0,0,0,1467,447,0;1,0,0,0,-1,327,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://altitude.aircanada.com/status/login#/-1,2,-94,-115,1,32,32,0,0,0,0,519,0,1589817638342,7,17007,0,0,2834,0,0,520,0,0,FDAD8408D22663F58C1364D541DC5BEA~-1~YAAQqZ7IF/jx+M1xAQAALf+DKANW7m8gCszdjFqSz/dNDA6NuKKheQrtopG3ckJXRrliXb7wFvNPmi9vb7iXnh0ApclJ6A+SFKH3dncdCLswwfISbihpsVkVOHqPDDR+IEJOy/bqGvaZzCKOCjI8+7NoEnKE1lbTw6Jk6h6tapWNZV9uwLcGGR7wtsmw9WT/fduWD3mQT7dVCgm4easLSTfO+oodUk8xCmIFnpJKgOE6QAEE+dynyUpPr94gAk9iUZP0X2uAPmXoEo8PEdoiUnOy18PRbspJ1rhT4qjkAUcj28rRMOBgY+l9iXdy5ZRcjrNcGC6HLmgIHYHpv0o5XBnm5igkDD62~-1~-1~-1,33107,171,-1902146740,26067385-1,2,-94,-106,9,1-1,2,-94,-119,400,0,0,0,200,200,200,0,0,200,200,0,400,600,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,-1279939100;-324940575;dis;,3;true;true;true;-180;true;24;24;true;false;unspecified-1,2,-94,-80,6596-1,2,-94,-116,343725215-1,2,-94,-118,87299-1,2,-94,-121,;2;11;0",
        ];

        if (count($sensorData) != count($sensorData2)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData2[$key]]), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    // ======================
    // Aircanada
    // ======================
    private function sendStaticSensorDataAircanada($sensorPostUrl, $isOne = false)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            //null,
            //"",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,390503,7324784,1920,1050,1920,1080,1920,461,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.223107074111,793553662392,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1587107324784,-999999,16978,0,0,2829,0,0,5,0,0,F2C6180A864FFA4B1BE2458AC276031C~-1~YAAQ0kU8F4zvAYBxAQAAltv3hgP9LoC3qEc3XmpXzdMHfx0FsIYAfQWo9mChLfGUVRcVULY8lVNWsMyMsj3kpU7tsFc/djdY1zevhnYyEyOiuIMLajyItm3A/ROAFLWkVRWmOuX5e6KEaxBC0FwbrCs76vLsKqQ8G99L/8vHjkow/ms84ACV1KcLP01lqT04rV0TGyyCytvamLiHTFYEPKQiFruGHyzaLbZkrUDlC+c2WmZWFnUtTyiP3vFzFxF/m6+jwCmx2/+qZ5vQshC+02AZGFB+KVSytQV2mJW/0q2SEfWIz+4pYbsvhX5s~-1~-1~-1,29946,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1648076357-1,2,-94,-118,177057-1,2,-94,-121,;13;-1;0",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,390503,7591222,1920,1050,1920,1080,1920,461,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.330359249165,793553795610.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1587107591221,-999999,16978,0,0,2829,0,0,5,0,0,F5FE748D65380C56240568DC6AE8B547~-1~YAAQ0kU8F4gTAoBxAQAA0ev7hgPT8LkeJyXSKp9BHCofXAEuOH3aKvIn8qaaVZpcgAQ3Dzrj+eU7BjIDglMtiiLW8r6eB3IFnADPjBb9MYn0bvTk8ww/1+/lRTawZB+P2OZGGtHBxMO8YRURJy8e/YIlkIVtl/5y6oD6pN25LGVuPr40nc1ZZY6OrqxR9T92M6ldJeVDnw3HK1k/1lYelsyQPxTd5OG8giAZPWyYJ8cBPJ/+xLa6OOLcaeSfnA1U1R7D7GeAtaizccSgnaBYi39+s9pTgJIkpouBu8iIen60jTaoaYCJXqtpFvlB~-1~-1~-1,29409,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,7591225-1,2,-94,-118,176667-1,2,-94,-121,;6;-1;0",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,390503,7683896,1920,1050,1920,1080,1920,461,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.761109742380,793553841946,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,1,0,1343,-1,0;0,-1,1,0,1371,-1,0;0,-1,1,0,1333,-1,0;0,-1,1,0,1347,-1,0;0,-1,1,0,1360,-1,0;0,-1,0,0,936,936,0;0,0,1,1,1879,864,0;1,0,1,1,1898,883,0;-1,2,-94,-108,0,1,12035,18,0,1,1471;1,2,15519,66,0,4,1471;2,2,15591,17,0,0,1471;3,1,16526,17,0,4,864;4,1,16583,86,0,4,864;5,2,16732,86,0,4,864;6,2,16740,17,0,0,864;7,1,17014,18,0,1,864;8,2,18456,67,0,4,864;9,2,18470,17,0,0,864;10,1,19430,17,0,4,883;11,1,19530,86,0,4,883;12,2,19623,86,0,4,883;13,2,19631,17,0,0,883;-1,2,-94,-110,0,1,291,1355,115;1,1,540,1403,97;2,1,939,1405,96;3,1,944,1407,95;4,1,972,1413,93;5,1,1047,1420,87;6,1,1048,1420,87;7,1,1089,1423,83;8,1,1108,1424,82;9,1,1254,1443,83;10,1,1259,1446,84;11,1,1271,1450,84;12,1,1272,1453,85;13,1,1282,1456,86;14,1,1288,1460,86;15,1,1307,1468,87;16,1,1312,1471,87;17,1,1320,1474,87;18,1,1328,1478,87;19,1,1336,1481,87;20,1,1344,1484,87;21,1,1352,1486,87;22,1,1360,1488,87;23,1,1368,1490,87;24,1,1426,1491,87;25,1,1434,1492,88;26,1,1441,1492,89;27,1,1470,1495,93;28,1,1473,1496,95;29,1,1483,1497,97;30,1,1494,1499,99;31,1,1496,1501,100;32,1,1503,1502,101;33,1,1512,1503,102;34,1,1520,1504,103;35,1,1528,1505,104;36,1,1594,1505,105;37,1,1600,1506,106;38,1,1616,1507,106;39,3,2759,1507,106,-1;40,4,2796,1507,106,-1;41,2,2798,1507,106,-1;42,1,2822,1503,106;43,1,2826,1502,106;44,1,2860,1499,106;45,1,2868,1498,105;46,1,2877,1496,105;47,1,2881,1494,105;48,1,2891,1492,105;49,1,2905,1487,104;50,1,2915,1484,103;51,1,2922,1481,102;52,1,2959,1459,100;53,1,2968,1452,99;54,1,2968,1444,98;55,1,2982,1435,97;56,1,2988,1425,96;57,1,2996,1414,95;58,1,3000,1399,94;59,1,3009,1383,94;60,1,3055,1248,94;61,1,3058,1202,94;62,1,3067,1158,96;63,1,3076,1114,96;64,1,3087,1072,98;65,1,3088,1030,100;66,1,3134,862,117;67,1,3136,839,119;68,1,3150,818,121;69,1,3155,803,123;70,1,3161,791,125;71,1,3171,785,127;72,1,4521,784,127;73,1,4529,785,127;74,1,4536,790,128;75,1,4545,804,128;76,1,4553,824,129;77,1,4561,850,131;78,1,4568,876,135;79,1,4581,901,139;80,1,4584,926,143;81,1,4593,949,145;82,1,4602,972,146;83,1,4611,989,148;84,1,4617,1005,148;85,1,4625,1016,148;86,1,4633,1028,148;87,1,4641,1039,148;88,1,4649,1050,148;89,1,4657,1065,148;90,1,4665,1080,148;91,1,4673,1097,148;92,1,4708,1175,148;93,1,4712,1195,148;94,1,4720,1215,148;95,1,4729,1232,148;96,1,4737,1248,147;97,1,4746,1262,145;98,1,4752,1278,141;99,1,4760,1298,138;100,1,4770,1318,132;101,1,4779,1341,128;102,1,4784,1368,124;258,3,7220,1523,105,-1;259,4,7249,1523,105,-1;260,2,7250,1523,105,-1;261,3,7832,1523,105,-1;262,4,7888,1523,105,-1;263,2,7890,1523,105,-1;264,3,8376,1523,105,-1;265,4,8443,1523,105,-1;266,2,8444,1523,105,-1;317,3,9655,1417,164,-1;318,4,9699,1417,164,-1;319,2,9700,1417,164,-1;463,3,16393,980,318,864;464,4,16457,980,318,864;465,2,16459,980,318,864;539,3,19152,905,392,883;540,4,19234,905,392,883;541,2,19234,905,392,883;622,3,25914,946,284,883;623,4,25946,946,284,883;624,2,25947,946,284,883;673,3,27251,949,215,864;-1,2,-94,-117,-1,2,-94,-111,0,779,-1,-1,-1;-1,2,-94,-109,0,778,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,2,13295;3,15415;2,17278;3,18407;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,256636,794632,32,779,778,0,1052792,27251,0,1587107683892,15,16978,14,674,2829,17,0,27252,847760,0,2ADABB6F06C4B5A72C7BCBD7558BEAB6~-1~YAAQ0kU8F/gfAoBxAQAAvMP9hgMyLWIIY6llXQdCNVoxon+6Fu0uwsbSQyQlYT6GSC7r0X2DT0cySPwYL/8YrhW0WB8yvdZ1bufDJ5daexhRNvokA43mwsCzbq59Ok658jmLdXjnGCV5+zFqW8PCDmg/Phn9/vnAJ96q0YEkY5JT4y5YcM+MFLP2stMKR0YrSymxZLCel8P8mynJP5vvDMTkWHAtgXh1tK885fIXeuYcaLy7gN80PbluJ861GYqPq7xEM08bNhgaHAi5CpWxoWZRFLPC72dCXJXe1tqX5+576UcvBHhgd4HB7qwuzw/jG6EVW2FuIwbBY7W2ASiwIlRid5gUtUM=~-1~-1~-1,32581,211,1758642904,30261689-1,2,-94,-106,1,10-1,2,-94,-119,192,164,171,171,248,260,201,155,161,148,147,1190,1251,662,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5585-1,2,-94,-116,622395783-1,2,-94,-118,287378-1,2,-94,-121,;4;31;0",

            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/80.0.3987.87 Chrome/80.0.3987.87 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,390503,7795427,1920,1050,1920,1080,1920,598,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9789,0.10652902453,793553897713.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1587107795427,-999999,16978,0,0,2829,0,0,4,0,0,3A4198F0666881175D4F5DFBA7F91024~-1~YAAQRb56XA1gtAxxAQAAQfmAFgOpTdXiOh81fXDDZcPXO6rypkNrw8n+iWTUCzisrIAscuDvoXcL1/zdgYfvCnr2wmSWAd9RqYdSDVWfD4yQSwfurRTYF729vGryY5qLI5KkMxyAhaY3uOR5U8PWD+hEW7MX77fbMLNpQ4/C7P9xYYcR0I6Nk/C2Wt6IaYorcVHwKIUSPnjBXVbVhioc022BCf+LveTFLBolJe4fqAYiDtSmqxExwGMrcOfk5Kn1UnMKR+vs26fM87H4/bJPMAmE51vHTjbA1YiBt7daoOlQLgr6DmiLXztYrBWqfodAJXv3MYtfY2CVpOuVoPYu5J77IKIbEFI=~-1~-1~-1,32872,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,38977096-1,2,-94,-118,182437-1,2,-94,-121,;8;-1;0",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/80.0.3987.87 Chrome/80.0.3987.87 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,390503,7795427,1920,1050,1920,1080,1920,527,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9789,0.759099287379,793553897713.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,1,0,1343,-1,0;0,-1,1,0,1371,-1,0;0,-1,1,0,1333,-1,0;0,-1,1,0,1347,-1,0;0,-1,1,0,1360,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,14710,471,11;1,1,14714,479,40;2,1,14718,483,58;3,1,14729,486,74;4,1,14735,491,88;5,1,14744,495,104;6,1,14749,498,118;7,1,14762,502,130;8,1,14766,503,141;9,1,14776,505,152;10,1,14788,507,160;11,1,14790,509,167;12,1,14798,510,172;13,1,14807,512,175;14,1,14814,512,178;15,1,15126,513,180;16,1,15135,515,179;17,1,15142,519,177;18,1,15150,524,173;19,1,15161,531,171;20,1,15166,539,167;21,1,15174,547,165;22,1,15182,558,162;23,1,15216,611,150;24,1,15223,627,146;25,1,15231,645,142;26,1,15237,662,139;27,1,15246,684,135;28,1,15254,702,132;29,1,15262,724,130;30,1,15269,746,128;31,1,15278,769,126;32,1,15285,792,126;33,1,15293,818,124;34,1,15302,843,124;35,1,15310,866,124;36,1,15318,889,124;37,1,15329,910,124;38,1,15333,931,124;39,1,15342,950,124;40,1,15350,969,123;41,1,15360,985,123;42,1,15365,1000,121;43,1,15373,1015,119;44,1,15381,1032,117;45,1,15391,1052,115;46,1,15401,1069,113;47,1,15408,1090,113;48,1,15415,1109,111;49,1,15423,1128,111;50,1,15430,1145,111;51,1,15437,1163,111;52,1,15445,1181,111;53,1,15455,1199,111;54,1,15463,1219,113;55,1,15471,1238,115;56,1,15477,1256,117;57,1,15486,1273,118;58,1,15494,1287,120;59,1,15501,1298,122;60,1,15512,1309,122;61,1,15517,1318,122;62,1,15527,1323,122;63,1,15533,1326,122;64,1,15678,1329,122;65,1,15686,1330,123;66,1,15694,1331,123;67,1,15702,1332,123;68,1,15709,1333,123;69,1,15717,1334,123;70,1,15725,1335,123;71,1,15734,1336,123;72,1,15758,1337,123;73,1,15765,1338,123;74,1,15776,1339,123;75,1,15782,1341,123;76,1,15790,1344,123;77,1,15797,1347,124;78,1,15806,1352,124;79,1,15814,1358,125;80,1,15822,1364,125;81,1,15830,1369,125;82,1,15837,1374,125;83,1,15846,1379,125;84,1,15853,1384,125;85,1,15861,1389,124;86,1,15869,1393,123;87,1,15877,1397,122;88,1,15885,1402,122;89,1,15893,1405,122;90,1,15901,1408,121;91,1,15910,1412,121;92,1,15918,1414,120;93,1,15926,1416,120;94,1,15934,1419,120;95,1,15944,1422,120;96,1,15950,1424,119;97,1,15958,1427,119;98,1,15966,1430,119;99,1,15974,1432,119;255,3,95906,1311,175,-1;-1,2,-94,-117,-1,2,-94,-111,0,14481,-1,-1,-1;-1,2,-94,-109,0,14481,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,3,14338;1,14345;2,18452;3,95864;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,1760510,32,14481,14481,0,1789440,95906,0,1587107795427,28,16978,0,256,2829,1,0,95908,1667734,0,3A4198F0666881175D4F5DFBA7F91024~-1~YAAQ0kU8F9UqAoBxAQAAchX/hgNovHnTJNS46JDVSHyxSDUl4CCXk0ShjAYgsud2nZVyv8Tmd8l1CF0DWc3rNIDMwVSaGVngwy7rJ2KdbF7+Wwp1DerP/PDxmXfkux+cPGMnbI6dxytSVq/v24802OcflAMsf5iFuRL1Fn6jTml6cAdy/gKO3NRNkFDggsIYMaqQ7OAebs/zE4mn7PwUNRmef12PGDP+4msEgzJ5zmT7Dsw17CIPTn0UmAfjv60oyMs89Oc8rxzwo71g15D+4DQzTuERCYxiIEH6sXO0/rYmd16JbjR3AEZnxCTObk3wpPOVowEzFx3NEPNCCUyMTegPYKqbZ08=~-1~-1~-1,32609,529,1295229928,30261689-1,2,-94,-106,1,2-1,2,-94,-119,39,41,49,42,57,60,18,9,8,7,7,313,307,168,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,38977096-1,2,-94,-118,249508-1,2,-94,-121,;20;15;0",
        ];
        $sensorData2 = [
            //null,
            //"",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,390503,7324784,1920,1050,1920,1080,1920,461,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.09475415447,793553662392,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,0,1,249,90,233;1,1,376,67,383;2,1,376,67,392;3,1,403,67,418;4,1,411,67,426;5,1,417,67,435;6,1,425,67,442;7,1,435,67,449;8,1,471,67,454;-1,2,-94,-117,-1,2,-94,-111,0,590,-1,-1,-1;-1,2,-94,-109,0,589,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,7898,32,590,589,0,9045,617,0,1587107324784,19,16978,0,9,2829,0,0,619,4742,0,F2C6180A864FFA4B1BE2458AC276031C~-1~YAAQ0kU8F+XvAYBxAQAAYeT3hgPhucVOFNHDPoXq6vgbfuHSpUuP4cfzN1OhCoQCq9zFIO/9N6qJMc5N3T1WkQhfd7IdlLseLZ+z1i7BQ1jBazz5IrTTZ07z/TnCIAUx8/vPvIkciTxl/LmRd9f1osDF0Vnd90KdhZQUEsNFEPYs3Pom5z6f3AMZ32dFoZIa3i5LWMmTxAACU8mlLMcJ2NuhqWqW1pWEezm6nP0+njdkEcorVTyFXH8hCG3PiHqM42B/UvT5AQiarOWPH9p6Hm/bMA3oYMEoIcpQ3BIK2/PulTDSzBfPFDjXmhfR5l7RQI9CaxGc0tpxycxVgJw86AqohCF8DjmI~-1~-1~-1,32490,319,-1914797372,30261689-1,2,-94,-106,9,1-1,2,-94,-119,533,41,41,41,66,66,47,37,9,7,7,715,749,269,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5585-1,2,-94,-116,1648076357-1,2,-94,-118,192907-1,2,-94,-121,;8;28;0",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,390503,7591222,1920,1050,1920,1080,1920,461,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.825674531412,793553795610.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,430,-1,-1,-1;-1,2,-94,-109,0,430,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,430,430,0,860,584,0,1587107591221,41,16978,0,0,2829,0,0,586,860,0,F5FE748D65380C56240568DC6AE8B547~-1~YAAQ0kU8F7ETAoBxAQAAEPX7hgMfdwaLr+dAGWQn2b/44fu8o23tRo7+GyHBVVxypAh2HlNw5HlvOKZn1RR2xvVDIfCzh8NzOUoRX75rx4G2TVF5FgLXA6IWYC3RYXfwbPtr9lRtPswcZnI9ZQzOW/V+C6uvBUeyGGJHvkSMQInq4OLkGBRLqPmHHD0v8uleczolh56eN9JNqUB2IxmfIQnw2UtytxgZLIqkFeaki4d4abdQkUaI1YwfV8ZXkdO9Y29h7O1XvUTJB10QJfVp1rpPWw8qU51dX58Pri4jhIItWBkdu5PuQuSFcWXs40SLtnfVfJwerccuIeofBViKrvCgCxDmwV7F~-1~-1~-1,33110,533,2089808439,30261689-1,2,-94,-106,9,1-1,2,-94,-119,40,43,42,43,62,63,48,9,8,7,7,620,634,163,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5585-1,2,-94,-116,7591225-1,2,-94,-118,186369-1,2,-94,-121,;7;16;0",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,390503,7683896,1920,1050,1920,1080,1920,461,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7618,0.761109742380,793553841946,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,1,0,1343,-1,0;0,-1,1,0,1371,-1,0;0,-1,1,0,1333,-1,0;0,-1,1,0,1347,-1,0;0,-1,1,0,1360,-1,0;0,-1,0,0,936,936,0;0,0,1,1,1879,864,0;1,0,1,1,1898,883,0;-1,2,-94,-108,0,1,12035,18,0,1,1471;1,2,15519,66,0,4,1471;2,2,15591,17,0,0,1471;3,1,16526,17,0,4,864;4,1,16583,86,0,4,864;5,2,16732,86,0,4,864;6,2,16740,17,0,0,864;7,1,17014,18,0,1,864;8,2,18456,67,0,4,864;9,2,18470,17,0,0,864;10,1,19430,17,0,4,883;11,1,19530,86,0,4,883;12,2,19623,86,0,4,883;13,2,19631,17,0,0,883;-1,2,-94,-110,0,1,291,1355,115;1,1,540,1403,97;2,1,939,1405,96;3,1,944,1407,95;4,1,972,1413,93;5,1,1047,1420,87;6,1,1048,1420,87;7,1,1089,1423,83;8,1,1108,1424,82;9,1,1254,1443,83;10,1,1259,1446,84;11,1,1271,1450,84;12,1,1272,1453,85;13,1,1282,1456,86;14,1,1288,1460,86;15,1,1307,1468,87;16,1,1312,1471,87;17,1,1320,1474,87;18,1,1328,1478,87;19,1,1336,1481,87;20,1,1344,1484,87;21,1,1352,1486,87;22,1,1360,1488,87;23,1,1368,1490,87;24,1,1426,1491,87;25,1,1434,1492,88;26,1,1441,1492,89;27,1,1470,1495,93;28,1,1473,1496,95;29,1,1483,1497,97;30,1,1494,1499,99;31,1,1496,1501,100;32,1,1503,1502,101;33,1,1512,1503,102;34,1,1520,1504,103;35,1,1528,1505,104;36,1,1594,1505,105;37,1,1600,1506,106;38,1,1616,1507,106;39,3,2759,1507,106,-1;40,4,2796,1507,106,-1;41,2,2798,1507,106,-1;42,1,2822,1503,106;43,1,2826,1502,106;44,1,2860,1499,106;45,1,2868,1498,105;46,1,2877,1496,105;47,1,2881,1494,105;48,1,2891,1492,105;49,1,2905,1487,104;50,1,2915,1484,103;51,1,2922,1481,102;52,1,2959,1459,100;53,1,2968,1452,99;54,1,2968,1444,98;55,1,2982,1435,97;56,1,2988,1425,96;57,1,2996,1414,95;58,1,3000,1399,94;59,1,3009,1383,94;60,1,3055,1248,94;61,1,3058,1202,94;62,1,3067,1158,96;63,1,3076,1114,96;64,1,3087,1072,98;65,1,3088,1030,100;66,1,3134,862,117;67,1,3136,839,119;68,1,3150,818,121;69,1,3155,803,123;70,1,3161,791,125;71,1,3171,785,127;72,1,4521,784,127;73,1,4529,785,127;74,1,4536,790,128;75,1,4545,804,128;76,1,4553,824,129;77,1,4561,850,131;78,1,4568,876,135;79,1,4581,901,139;80,1,4584,926,143;81,1,4593,949,145;82,1,4602,972,146;83,1,4611,989,148;84,1,4617,1005,148;85,1,4625,1016,148;86,1,4633,1028,148;87,1,4641,1039,148;88,1,4649,1050,148;89,1,4657,1065,148;90,1,4665,1080,148;91,1,4673,1097,148;92,1,4708,1175,148;93,1,4712,1195,148;94,1,4720,1215,148;95,1,4729,1232,148;96,1,4737,1248,147;97,1,4746,1262,145;98,1,4752,1278,141;99,1,4760,1298,138;100,1,4770,1318,132;101,1,4779,1341,128;102,1,4784,1368,124;258,3,7220,1523,105,-1;259,4,7249,1523,105,-1;260,2,7250,1523,105,-1;261,3,7832,1523,105,-1;262,4,7888,1523,105,-1;263,2,7890,1523,105,-1;264,3,8376,1523,105,-1;265,4,8443,1523,105,-1;266,2,8444,1523,105,-1;317,3,9655,1417,164,-1;318,4,9699,1417,164,-1;319,2,9700,1417,164,-1;463,3,16393,980,318,864;464,4,16457,980,318,864;465,2,16459,980,318,864;539,3,19152,905,392,883;540,4,19234,905,392,883;541,2,19234,905,392,883;622,3,25914,946,284,883;623,4,25946,946,284,883;624,2,25947,946,284,883;673,3,27251,949,215,864;-1,2,-94,-117,-1,2,-94,-111,0,779,-1,-1,-1;-1,2,-94,-109,0,778,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,2,13295;3,15415;2,17278;3,18407;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,256636,794632,32,779,778,0,1052792,27251,0,1587107683892,15,16978,14,674,2829,17,0,27252,847760,0,2ADABB6F06C4B5A72C7BCBD7558BEAB6~-1~YAAQ0kU8F/gfAoBxAQAAvMP9hgMyLWIIY6llXQdCNVoxon+6Fu0uwsbSQyQlYT6GSC7r0X2DT0cySPwYL/8YrhW0WB8yvdZ1bufDJ5daexhRNvokA43mwsCzbq59Ok658jmLdXjnGCV5+zFqW8PCDmg/Phn9/vnAJ96q0YEkY5JT4y5YcM+MFLP2stMKR0YrSymxZLCel8P8mynJP5vvDMTkWHAtgXh1tK885fIXeuYcaLy7gN80PbluJ861GYqPq7xEM08bNhgaHAi5CpWxoWZRFLPC72dCXJXe1tqX5+576UcvBHhgd4HB7qwuzw/jG6EVW2FuIwbBY7W2ASiwIlRid5gUtUM=~-1~-1~-1,32581,211,1758642904,30261689-1,2,-94,-106,1,10-1,2,-94,-119,192,164,171,171,248,260,201,155,161,148,147,1190,1251,662,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5585-1,2,-94,-116,622395783-1,2,-94,-118,287378-1,2,-94,-121,;4;31;0",

            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/80.0.3987.87 Chrome/80.0.3987.87 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,390503,7795427,1920,1050,1920,1080,1920,598,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9789,0.689815502344,793553897713.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,831,0,1587107795427,28,16978,0,0,2829,0,0,833,0,0,3A4198F0666881175D4F5DFBA7F91024~-1~YAAQ0kU8F68qAoBxAQAAOBL/hgMcHcAci/bSYIyiYZFTRBFx0W1MdXaKXvKXXSRZiACBIxt+Gfb1TbxrV615em0IjfEURrCXyOeUwZYlf79qc70/gydK71DnSkWbjpKMdjhBoxisn1tK0TMbD7ON1cogJ0ciwaVNGqbBitWMiKYA87D4eUX+bMUt8aeqkbN5jauqXXQ+LgAD1/l1M9tq6zUxnD7L+2G9Rg3cBX23cRV5XGUW68HCtoIefPWMdL7+vF5R+EGEWqH0IeDa59mRmHqSV69bBKx5/jQHg3bx1ssPUGw8tpOIat8x6TxpRg6bdpCaWorZhe58tJUQAu7l4EK5yxO0OQY=~-1~-1~-1,32230,529,1295229928,30261689-1,2,-94,-106,9,1-1,2,-94,-119,44,41,42,42,59,62,49,9,11,6,6,546,673,140,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,38977096-1,2,-94,-118,185281-1,2,-94,-121,;5;15;0",
            "7a74G7m23Vrp0o5c9150781.54-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/80.0.3987.87 Chrome/80.0.3987.87 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,390503,7795427,1920,1050,1920,1080,1920,527,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9789,0.984384771492,793553897713.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,1,0,1343,-1,0;0,-1,1,0,1371,-1,0;0,-1,1,0,1333,-1,0;0,-1,1,0,1347,-1,0;0,-1,1,0,1360,-1,0;0,-1,0,0,936,936,0;0,0,0,1,1879,864,0;1,0,0,1,1898,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,14710,471,11;1,1,14714,479,40;2,1,14718,483,58;3,1,14729,486,74;4,1,14735,491,88;5,1,14744,495,104;6,1,14749,498,118;7,1,14762,502,130;8,1,14766,503,141;9,1,14776,505,152;10,1,14788,507,160;11,1,14790,509,167;12,1,14798,510,172;13,1,14807,512,175;14,1,14814,512,178;15,1,15126,513,180;16,1,15135,515,179;17,1,15142,519,177;18,1,15150,524,173;19,1,15161,531,171;20,1,15166,539,167;21,1,15174,547,165;22,1,15182,558,162;23,1,15216,611,150;24,1,15223,627,146;25,1,15231,645,142;26,1,15237,662,139;27,1,15246,684,135;28,1,15254,702,132;29,1,15262,724,130;30,1,15269,746,128;31,1,15278,769,126;32,1,15285,792,126;33,1,15293,818,124;34,1,15302,843,124;35,1,15310,866,124;36,1,15318,889,124;37,1,15329,910,124;38,1,15333,931,124;39,1,15342,950,124;40,1,15350,969,123;41,1,15360,985,123;42,1,15365,1000,121;43,1,15373,1015,119;44,1,15381,1032,117;45,1,15391,1052,115;46,1,15401,1069,113;47,1,15408,1090,113;48,1,15415,1109,111;49,1,15423,1128,111;50,1,15430,1145,111;51,1,15437,1163,111;52,1,15445,1181,111;53,1,15455,1199,111;54,1,15463,1219,113;55,1,15471,1238,115;56,1,15477,1256,117;57,1,15486,1273,118;58,1,15494,1287,120;59,1,15501,1298,122;60,1,15512,1309,122;61,1,15517,1318,122;62,1,15527,1323,122;63,1,15533,1326,122;64,1,15678,1329,122;65,1,15686,1330,123;66,1,15694,1331,123;67,1,15702,1332,123;68,1,15709,1333,123;69,1,15717,1334,123;70,1,15725,1335,123;71,1,15734,1336,123;72,1,15758,1337,123;73,1,15765,1338,123;74,1,15776,1339,123;75,1,15782,1341,123;76,1,15790,1344,123;77,1,15797,1347,124;78,1,15806,1352,124;79,1,15814,1358,125;80,1,15822,1364,125;81,1,15830,1369,125;82,1,15837,1374,125;83,1,15846,1379,125;84,1,15853,1384,125;85,1,15861,1389,124;86,1,15869,1393,123;87,1,15877,1397,122;88,1,15885,1402,122;89,1,15893,1405,122;90,1,15901,1408,121;91,1,15910,1412,121;92,1,15918,1414,120;93,1,15926,1416,120;94,1,15934,1419,120;95,1,15944,1422,120;96,1,15950,1424,119;97,1,15958,1427,119;98,1,15966,1430,119;99,1,15974,1432,119;255,3,95906,1311,175,-1;256,4,95962,1311,175,-1;257,2,95968,1311,175,-1;379,3,118441,847,415,864;-1,2,-94,-117,-1,2,-94,-111,0,14481,-1,-1,-1;-1,2,-94,-109,0,14481,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,3,14338;1,14345;2,18452;3,95864;2,98535;3,118428;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,2076016,32,14481,14481,0,2104946,118441,0,1587107795427,28,16978,0,380,2829,3,0,118454,1978105,0,3A4198F0666881175D4F5DFBA7F91024~-1~YAAQ0kU8F3M6AoBxAQAAxYoAhwPAHfJSXNM1oWeVfRzcqe+CD8Le7PBtp962A8jsvfSUUBm/3M74IQ5V8MbLzJ4littKROgXJwvdY1SF4WY/W6wSMPVmqVrxZBaQt9mnl6pFXd6EJUY37sTsHLjnnxxxzP0nRpt0Vu61npaAJL6UgW6VPRhmLTvTrxAf4RFGANOM5bbwF7oBSms3+iCyr/E4hTfoNYzqUYW8z42rBSAp4gw5+aJVBut89NgXZYp2+wE6eep3S9AiMJ1E0XZsEZKLjZHd2SzGYe9Z7rFijcq81pjlIO1ZgNSqKxVdiwOjVv6aKulQVVlyvUx6AW8UnuxEDT++9Sk=~-1~-1~-1,32723,529,1295229928,30261689-1,2,-94,-106,1,3-1,2,-94,-119,39,41,49,42,57,60,18,9,8,7,7,313,307,168,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,38977096-1,2,-94,-118,256175-1,2,-94,-121,;21;15;0",
        ];

        if (count($sensorData) != count($sensorData2)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);

        if (!$isOne && $sensorData2[$key] !== "") {
            $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData2[$key]]), $sensorDataHeaders);
        }
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }
}
