<?php

/*require_once __DIR__."/../klm/functions.php";

class TAccountCheckerAirfrance extends TAccountCheckerKlm {

    function LoadLoginForm(){
        $this->http->Log('function '.__METHOD__);
        return parent::LoadLoginForm();
    }

    function Login(){
        $this->http->Log('function '.__METHOD__);
        return parent::Login();
    }

    function GetExtensionFinalURL(array $fields) {
        return "https://www.airfrance.us/cgi-bin/AF/US/en/local/myaccount/myahome/myaHome.jsp#bbq.mya/my-profile-ajax=/ams/account/secure/mileageSummaryFB.htm";
    }

    function Parse(){
        $this->http->Log('function '.__METHOD__);
        return parent::Parse();
    }

    function GetRedirectParams($targetURL = NULL) {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.airfrance.us/US/en/local/core/engine/loginv2/action/LoginWebServiceAction.do?login='.urlencode($this->AccountFields['Login']).'&password='.urlencode($this->AccountFields['Pass']).'&jsCallback=AF.Login.loginCallback&_='.time().date('B');
        $arg['RequestMethod'] = 'GET';
        $arg['PreloadAsImages'] = true;
        $arg['SuccessURL'] = 'https://www.airfrance.us/US/en/local/core/engine/myaccount/DashBoardAction.do?tabDisplayed=milesTab';

        return $arg;
    }

    function ParseItineraries() {
        $this->http->Log('function '.__METHOD__);
        return parent::ParseItineraries();
    }

    function ParseItinerary(){
        $this->http->Log('function '.__METHOD__);
        return parent::ParseItinerary();
    }

    function GetConfirmationFields(){
        return parent::GetConfirmationFields();
    }

    function GetHistoryColumns() {
        return parent::GetHistoryColumns();
    }

    protected $collectedHistory = true;

    function ParseHistory($startDate = null) {
        $this->http->Log('function '.__METHOD__);
        return parent::ParseHistory($startDate);
    }

    function ParsePageHistory($startIndex, $startDate) {
        $this->http->Log('function '.__METHOD__);
        return parent::ParsePageHistory($startIndex, $startDate);
    }

}*/

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirfrance extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    use PriceTools;

    /**
     * @var CaptchaRecognizer
     */
    public $recognizer;

    private $key = null;
    private $currentItin = 0;
    private $sensorData = null;
    private $lastName = null;
    private $sha256HashReservations = '4e3f2e0b0621bc3b51fde95314745feb4fd1f9c10cf174542ab79d36c9dd0fb2';
    private $sha256HashReservation = '8ceaed40ef2387f278f78846e6b23f5861483a3277b65adacf0408f9f4a9c9a0';

//    private $headers = [
//        'authorization'   => 'Basic NHBtOXJrdG4zYTY4OWR5NjU5M3IyOXZyLGh0dHBzOi8vd3d3cy5haXJmcmFuY2UuZnIvZW5kcG9pbnQvdjEvb2F1dGgvY29udGludWUv', // 'Basic '.base64_encode("4pm9rktn3a689dy6593r29vr,https://wwws.airfrance.fr/endpoint/v1/oauth/continue/")
//        'Accept'          => 'application/json, text/plain, *',
//        'Content-Type'    => 'application/json',
//        'Referer'         => 'https://login.airfrance.com/oauthcust/login',
//        "Accept-Encoding" => "gzip, deflate, br",
//    ];
    private $headers = [
        'AFKL-Travel-Country' => 'US',
        'country'             => 'US',
        'AFKL-TRAVEL-Host'    => 'af',
        'Accept'              => 'application/json, text/plain, *',
        'Content-Type'        => 'application/json',
        'Referer'             => 'https://login.airfrance.com/login/account',
        "Accept-Encoding"     => "gzip, deflate, br",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        //if (in_array($accountInfo['Login'], ['veresch80@yahoo.com','ve30@hotmail.com'])) {
        require_once __DIR__ . '/TAccountCheckerAirfranceSelenium.php';

        return new TAccountCheckerAirfranceSelenium();
        //}

//        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (isset($this->State['2fa_hold_session'], $this->Step) && $this->Step == 'Question') {
            unset($this->State['2fa_hold_session']);
            $this->UseSelenium();
            $this->useGoogleChrome();
            $this->useCache();
            $this->http->saveScreenshots = true;

            return;
        }
        $this->KeepState = true;
//        $this->http->setHttp2(true); // causing error "Network error 92 - HTTP/2 stream 0 was not closed cleanly"
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.airfrance.us/US/en/local/core/engine/loginv2/action/LoginWebServiceAction.do?login=' . urlencode($this->AccountFields['Login']) . '&password=' . urlencode($this->AccountFields['Pass']) . '&jsCallback=AF.Login.loginCallback&_=' . time() . date('B');
        $arg['RequestMethod'] = 'GET';
        $arg['PreloadAsImages'] = true;
        $arg['SuccessURL'] = 'https://www.airfrance.us/US/en/local/core/engine/myaccount/DashBoardAction.do?tabDisplayed=milesTab';

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://wwws.airfrance.us/endpoint/v1/oauth/login?ref=/profile/flying-blue/dashboard", [], 20);

        // crocked server workaround
        if ($this->http->Response['code'] == 403) {
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL("https://wwws.airfrance.us/endpoint/v1/oauth/login?ref=/profile/flying-blue/dashboard", [], 20);
        }

        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/\"isLoggedIn\":true,/")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        /* not valid anymore
        // https://redmine.awardwallet.com/issues/12447#note-17
        if (strlen($this->AccountFields['Pass']) > 12 && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            throw new CheckException("The PIN code/Password field must contain between 4 and 12 characters.", ACCOUNT_INVALID_PASSWORD);
        }
        */

        $this->http->removeCookies();
        /*
        $this->http->GetURL("https://wwws.airfrance.us/endpoint/v1/oauth/login?ref=https://wwws.airfrance.us/");
        $response = $this->http->JsonLog()->cidLogonParams ?? null;

        $endpoint = $response->loginEndpoint ?? null;
        $pathname = $response->pathname ?? null;
        $client = $response->clientId ?? null;
        $redirect = $response->redirect_uri_suffix ?? null;

        if (!isset($endpoint, $pathname, $client, $redirect)) {
            return $this->checkErrors();
        }

        $this->http->GetURL("{$endpoint}{$pathname}?client_id=$client&source=homepage.homepage&loginPathname=$pathname&brand=af&locale=US/en-US&response_type=code&scope=login:afkl&redirect_uri=https://wwws.airfrance.us$redirect");
        */
        $this->http->GetURL("https://wwws.airfrance.us/endpoint/v1/oauth/redirect?loginPrompt=&source=homepage.homepage&locale=US/en-US");

        // provider bug fix
        if ($this->http->currentUrl() == 'https://login.airfrance.com/login/page-not-found') {
            $this->http->GetURL("https://wwws.airfrance.us/endpoint/v1/oauth/redirect?loginPrompt=&source=profile&locale=US/en-US");
        }

        if ($this->http->currentUrl() !== 'https://login.airfrance.com/login/otp') {
            return $this->checkErrors();
        }

//        if ($this->http->Response['code'] !== 200) {
//            if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Unavailable")]')) {
//                throw new CheckException("Due to technical issues, you may experience difficulties on our website. We're doing our best to resole these as soon as possible. Please note: the app is still working.", ACCOUNT_PROVIDER_ERROR);
//            }
//
//            return $this->checkErrors();
//        }

        $this->selenium();

        if (isset($this->State['2fa_hold_session'])) {
            return false;
        }

        return true;

        /*
        $this->http->setDefaultHeader($this->http->FindSingleNode("//meta[@id = 'csrf_header']/@content"), $this->http->FindSingleNode("//meta[@id = 'csrf']/@content"));

        if (!$this->http->FindSingleNode("//meta[@id = 'disableCaptcha' and @content = 'false']/@content")) {
            return $this->checkErrors();
        }
        */
//        $this->http->SetInputValue("username", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
//        $this->http->SetInputValue("g-recaptcha-response", $captcha);
//        $data = [
//            "username"          => $this->AccountFields['Login'],
//            "password"          => $this->AccountFields['Pass'],
//            "type"              => "normal",
//            "recaptchaResponse" => $captcha,
//        ];

        $data = [
            "operationName" => "login",
            "variables"     => [
                "loginParams" => [
                    "loginId"           => $this->AccountFields['Login'],
                    "password"          => $this->AccountFields['Pass'],
                    "persistent"        => false,
                    "recaptchaResponse" => $captcha,
                    "type"              => is_numeric($this->AccountFields['Login']) ? "FLYINGBLUE" : "EMAIL",
                ],
            ],
            "query"         => "query login(\$loginParams: LoginParams) {\n  login(input: \$loginParams) {\n    code\n    redirectUri\n    errors {\n      code\n      description\n      __typename\n    }\n    __typename\n  }\n}\n",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.airfrance.com/login/gql/gql-login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, false, 'description');

        if ($response || $this->http->FindNodes('//div[contains(@class, "bwc-form-errors")]/span | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]')) {
            if (isset($response->data->login->redirectUri)) {
                $this->captchaReporting($this->recognizer);
                $this->http->GetURL($response->data->login->redirectUri);

//                $this->http->GetURL("https://account.bluebiz.com/ctms/auth/asfc/callback?code={$response->data->login->code}");

//                return $this->loginSuccessful();//todo
                return true;
            }

            $message =
                $response->data->login->errors[0]->description
                ?? $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
                ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]')
                ?? null
            ;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'Incorrect username and/or password. Please check and try again.')
                    || strstr($message, 'These login details appear to be incorrect. Please verify the information and try again')
                    || strstr($message, 't find the e-mail address or Flying Blue number you entered')
                    || strstr($message, 't find the e-mail address or Flying Blue number entered')
                    || strstr($message, 'The password you entered is not valid.')
                    || strstr($message, 'More than 1 passenger is registered with this e-mail address. Please log in with your Flying Blue number instead, so we can uniquely identify you.')
                    || $message == 'Please enter a valid password.'
                    || $message == 'Please enter a valid e-mail address.'
                    || $message == 'Your temporary password has expired.'
                    || strstr($message, 'Sorry, we can\'t recognise your password due to a technical error.')
                    || strstr($message, 'Sorry, we cannot log you in right now. Contact us via the')
                    || strstr($message, 'Oops, the login details you entered are incorrect.')
                    || strstr($message, 'Your e-mail address seems to be invalid.')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'Unfortunately, your account is blocked.')
                    || strstr($message, 'Your account is blocked. Please wait 24 hours before clicking "Forgot password?" to reset your password.')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if (
                    strstr($message, 'Authentication failed: recaptchaResponse')
                    || strstr($message, 'Access denied: Ineligible captcha score')
                    || $message == 'Invalid Captcha'
                ) {
                    $this->captchaReporting($this->recognizer, false);
                    $this->DebugInfo = $message;

                    throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                }

                if (
                    strstr($message, 'Due to a technical error, it is not possible to log you in right now.')
                    || strstr($message, 'Due to a technical error, we cannot log you in right now.')
                ) {
                    $this->markProxyAsInvalid();

                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $this->DebugInfo = "block, technical error";

                    throw new CheckRetryNeededException(2, 0);
                }

                if (
                    $message == 'Retrieved unexpected result from mashery.'
                    || $message == 'Forbidden'
                    || $message == 'Sorry, an unexpected technical error occurred. Please try again or contact the Air France customer service team.'
                ) {
                    $this->captchaReporting($this->recognizer);
                    $this->DebugInfo = $message;

                    throw new CheckException("Sorry, an unexpected technical error occurred. Please try again or contact the Air France customer service team.", ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $message == 'Sorry, our system fell asleep. Please restart your login.'
                    || strstr($message, 'Communication email is invalid')
                    || strstr($message, 'Sorry, we cannot verify your password due to a technical issue')
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return $this->checkErrors();
        }

        if (
            $this->http->FindPreg("/<BODY>\s*An error occurred while processing your request\.<p>/")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Sorry, an unexpected technical error occurred. Please try again or contact the Air France customer service team.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//span[contains(@class, "bwc-logo-header__user-name")]')
            || $this->http->FindSingleNode('//div[contains(@class, "bw-profile-recognition-box__info")]/h1')
            || $this->http->FindSingleNode('//h1[contains(text(), "Dashboard")]')
        ) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($this->http->FindSingleNode('//div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if (!$otpInput = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5)) {
            $this->saveResponse();

            return false;
        }

        $this->logger->debug("entering code...");
        $elements = $this->driver->findElements(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'));

        foreach ($elements as $key => $element) {
            $this->logger->debug("#{$key}: {$code[$key]}");
            $element->click();
            $element->sendKeys($code[$key]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)

        if (!$this->solveCaptchaImg()) {
            return false;
        }

        if (!$button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 1)) {
            return false;
        }
        $button->click();
        $this->waitForElement(WebDriverBy::xpath('
            //span[contains(@class, "bwc-logo-header__user-name")]
            | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
            | //div[contains(@class, "bwc-form-errors")]/span
        '), 15);
        $captchaAttempt = 0;

        while ($this->waitForElement(WebDriverBy::xpath('//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)] | //div[contains(@class, "bwc-form-errors")]/span'), 0)) {
            $this->saveResponse();
            $error = $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
                ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]');
            $this->logger->error("[Error]: $error");

            if (strstr($error, 'Invalid Captcha') && ++$captchaAttempt < 3) {
                if (!$this->solveCaptchaImg()
                    || !$button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3)
                ) {
                    return false;
                }
                $button->click();
                sleep(3);

                continue;
            }

            if (
                strstr($error, 'This is not the right PIN code. Please try again.')
                || strstr($error, 'You have entered an incorrect PIN code. Please try again.')
                || strstr($error, 'Your one-time PIN code has expired')
            ) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }

            if (strstr($error, 'Sorry, an unexpected technical error occurred')) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;

            return false;
        }

        $this->switchToCurl();

        return true;
    }

    public function Parse()
    {
        $data = [
            "operationName" => "ProfileAccountInfoQuery",
            "variables"     => new stdClass(),
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "fe2cd257408d9cf35e82132e33e562531aef7f39f96fabfb903380f68990069f",
                ],
            ],
        ];
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'X-Aviato-Host' => 'wwws.airfrance.us',
        ];
        $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($data), $headers);
        $accountInfo = $this->http->JsonLog();

        // provider bug fix
        if ($this->http->FindPreg('/\{"data":\{"account":null\}\}$/')) {
            $try = 0;

            do {
                sleep(5);
                $this->logger->error("provider bug fix, try one more time");
                $this->http->setCookie('XSRF-TOKEN', null);
                $this->http->RetryCount = 0;
                $retry = 0;

                do {
                    $this->http->GetURL('https://wwws.airfrance.us/profile', [], 30);
                    $xsrf = $this->http->getCookieByName('XSRF-TOKEN');
                    $retry++;
                } while (!$xsrf && $retry < 5);
                $this->http->RetryCount = 2;

                $this->http->setDefaultHeader('x-xsrf-token', $xsrf);

                $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($data), $headers);
                $accountInfo = $this->http->JsonLog();
                $try++;
            } while ($this->http->FindPreg('/\{"data":\{"account":null\}\}$/') && $try < 5);
        }

        // AccountId: 1367843
        if ($this->http->FindPreg('/"message":"An unknown error occurred","name":"UnknownError"/')
            || ($this->http->FindPreg('/"message":"An unknown error occurred",/') && $this->http->FindPreg('/"name":"AviatoError","data":/'))
            // AccountId: 650916
            || $this->http->FindPreg('/"message":"Unauthorized","name":"UnauthorizedError"/')) {
            $this->parseMainPage();

            return;
        }

        if (isset($accountInfo->data->account->givenNames, $accountInfo->data->account->familyName)) {
            $this->lastName = $accountInfo->data->account->familyName;
            $this->SetProperty("Name", beautifulName($accountInfo->data->account->givenNames . ' ' . $accountInfo->data->account->familyName));
        }

        if (!isset($accountInfo->data->account->flyingBlueNumber)) {
            $this->logger->error("something went wrong");
            // not a member
            if (isset($accountInfo->data->account->isFlyingBlue) && $accountInfo->data->account->isFlyingBlue === false
                && !empty($this->Properties['Name'])) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }

            return;
        }// if (!isset($accountInfo->data->account->flyingBlueNumber))

        $this->SetProperty("Number", $accountInfo->data->account->flyingBlueNumber);

        $data = [
            "operationName" => "ProfileFlyingBlueDashboardQuery",
            "variables"     => [
                "fbNumber" => $this->Properties['Number'],
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => '75235467716d05abac908894c4ec567c7eb6f2c24fe7bb144f8618eaa00e4db7',
                ],
            ],
        ];
        $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (!$response || !isset($response->data)) {
            return;
        }

        $dashboard = $response->data->flyingBlueDashboardV2;
        // Balance
        $this->SetBalance($dashboard->miles->amount ?? null);
        // Experience Points
        $this->SetProperty("ExperiencePoints", $dashboard->detailStatus->currentTierLevel->currentTierLevelXP->xpAmount ?? null);
        // Status
        if (isset($dashboard->fbLevel)) {
            $this->SetProperty("Status", beautifulName($dashboard->fbLevel));
            // refs
            if ($dashboard->fbLevel == 'Explorer' && !isset($this->Properties['ExperiencePoints'])
                && $this->http->FindPreg("/\{\"tierLevel\":\"Explorer\",\"tierLevelCode\":\"A\",\"currentTierLevelXP\":null,/")) {
                $this->SetProperty("ExperiencePoints", 0);
            }
        }

        // AccountId: 4138182
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) && !empty($this->Properties['Number'])) {
                $this->SetBalanceNA();
            }

            return;
        }

        // refs #16824
//        $this->http->GetURL("https://wwws.airfrance.us/en/profile/flying-blue/benefits");
        $data = [
            "operationName" => "ProfileFlyingBlueBenefitsQuery",
            "variables"     => [
                "fbNumber" => $this->Properties['Number'],
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "ee0498f9ac6236f86f09013c8621ab2894e36e17dd0d0d8fb80b856514b23379",
                ],
            ],
        ];
        $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (isset($response->data->flyingBlueBenefits->currentBenefits)) {
            foreach ($response->data->flyingBlueBenefits->currentBenefits as $benefit) {
                if ($benefit->label == "Flying Blue Petroleum") {
                    $this->SetProperty("PetroleumMembership", 'Yes');

                    break;
                }
            }
        }// foreach ($response->data->flyingBlueBenefits->currentBenefits as $benefit)

        if (!$this->Balance || $this->Balance < 0) {
            return;
        }
        $this->logger->info('Expiration Date', ['Header' => 3]);

        if (isset($this->Properties['Status']) && $this->Properties['Status'] == 'Explorer') {
            // Expiration Date
            $response = $this->getHistory(10);

            if (isset($response->data->flyingBlueTransactionHistory->milesValidities[0]->validityDate)) {
                $result = [];

                foreach ($response->data->flyingBlueTransactionHistory->milesValidities as $row) {
                    $result[$row->validityDate]['validityDate'] = $row->validityDate;

                    if (isset($result[$row->validityDate]['milesAmount'])) {
                        $result[$row->validityDate]['milesAmount'] += $row->milesAmount;
                    } else {
                        $result[$row->validityDate]['milesAmount'] = $row->milesAmount;
                    }
                }
                $result = array_values($result);

                usort($result, function ($a, $b) {
                    $a2 = strtotime($a['validityDate']);
                    $b2 = strtotime($b['validityDate']);

                    if ($a2 < $b2) {
                        return -1;
                    }

                    if ($a2 > $b2) {
                        return 1;
                    }

                    return 0;
                });
                $first = current($result);

                $this->logger->debug("Exp Date: {$first['validityDate']}");

                if ($exp = strtotime($first['validityDate'], false)) {
                    $this->SetExpirationDate($exp);
                    $this->SetProperty('ExpiringBalance', $first['milesAmount']);
                }
            }
        } elseif (isset($this->Properties['Status']) && in_array($this->Properties['Status'],
                ['Silver', 'Gold', 'Platinum', 'Ultimate', 'Platinum For Life', 'Ultimate Club 2000'])) {
            $this->SetExpirationDateNever();
            $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');
            $this->ClearExpirationDate();
        } elseif (isset($this->Properties['Status'])) {
            $this->sendNotification("new status {$this->Properties['Status']} // MI");
        }
    }

    public function ParseItineraries(): array
    {
        $trips = $this->getReservationsJson();

        if (is_array($trips)) {
            if (empty($trips)) {
                $this->itinerariesMaster->setNoItineraries(true);

                return [];
            }
            $cntSkipped = 0;

            foreach ($trips as $trip) {
                $scheduledReturn = !empty($trip->scheduledReturn) ? $trip->scheduledReturn : $trip->scheduledDeparture;
                $this->logger->debug("[scheduledReturn]: '{$scheduledReturn}'");
                $isPast = strtotime($scheduledReturn) < strtotime(date("Y-m-d"));

                if (!$this->ParsePastIts && $scheduledReturn != '' && $isPast) {
                    $cntSkipped++;
                    $this->logger->notice("Skipping booking {$trip->bookingCode}: past itinerary");

                    continue;
                }

                if ($trip->historical === true || $isPast) {
                    $this->parseReservationJson($trip->bookingCode, $trip, true);
                } else {
                    $reservation = $this->getReservationJson($trip->bookingCode, $trip->lastName);

                    if ($reservation === null) {
                        $this->logger->error("Skipping reservation: {$reservation}");

                        continue;
                    }

                    if (is_string($reservation)) {
                        $this->logger->error("Skipping reservation 2: {$reservation}");

                        continue;
                    }
                    $this->parseReservationJson($trip->bookingCode, $reservation);
                }
            }

            if (count($trips) === $cntSkipped && count($this->itinerariesMaster->getItineraries()) === 0) {
                $this->itinerariesMaster->setNoItineraries(true);
            }
        }

        return [];
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
        return "https://wwws.airfrance.us/trip";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        if (!$this->seleniumRetrieve($this->ConfirmationNumberURL($arFields))) {
            return null;
        }

        if ($this->http->currentUrl() !== 'https://wwws.airfrance.us/trip') {
            $memBody = $this->http->Response['body'];
            $this->http->GetURL('https://wwws.airfrance.us/trip');
        }

        $reservation = $this->getReservationJson($arFields['ConfNo'], $arFields['LastName']);

        if ($reservation === null) {
            return null;
        }

        if (is_string($reservation)) {
            return $reservation;
        }

        if (!$reservation) {
            if (isset($memBody)) {
                $this->sendNotification('check memBody // MI');
                $this->http->SetBody($memBody);
                $it = $this->ParseItinerary($arFields['LastName']);
            }

            return null;
        }

        return $this->parseReservationJson($arFields['ConfNo'], $reservation);
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"              => "PostingDate",
            "Transaction"       => "Description",
            "Travel Date"       => "Info.Date",
            "Award Miles"       => "Miles",
            "Bonus Miles"       => "Bonus",
            "Experience Points" => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (empty($this->Properties['Number'])) {
            return $result;
        }

        $page = 0;
        $this->getHistory();
        $page++;
        $this->logger->info("[History page: {$page}]", ['Header' => 3]);

        if ($this->http->FindPreg("/^\[\"serviceUnavailable\"\]$/") || $this->http->Response['code'] == 204) {
            $this->sendNotification('airfrance: Service Unavailable');
            $this->logger->warning("service unavailable");

            return $result;
        }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function seleniumRetrieve($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL($url);

            // login
            $bookingCode = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(),'Log in with my booking reference')]"), 3);

//            if (!$bookingCode) {
//                return false;
//            }
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // TODO
        }

        return true;
    }

    public function parseCaptcha($selenium)
    {
        $this->logger->debug("parseCaptcha");
        $selenium->driver->executeScript("var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = document.querySelector('.asfc-svg-captcha svg');
            function drawInlineSVG(svgElement, ctx, callback) {
                var svgURL = new XMLSerializer().serializeToString(svgElement);
                var img = new Image();
                img.onload = function () {
                    canvas.height = img.height;
                    canvas.width = img.width;
                    ctx.drawImage(this, 0, 0);
                    callback();
                }
                img.src = 'data:image/svg+xml; charset=utf8, ' + encodeURIComponent(svgURL);
            }
            drawInlineSVG(img, ctx, function () {
                var dataURL = canvas.toDataURL('image/png');
                localeStorage.put('dataURL', dataURL);
            });");

        sleep(1);
        $dataUrl = $selenium->driver->executeScript("localeStorage.get('dataURL');");
        $this->logger->debug("dataURL: $dataUrl");

        if (strpos($dataUrl, 'data:image/') !== 0) {
            $this->logger->error("no marker");

            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeCaptcha($this->recognizer, $dataUrl);
    }

    protected function parseCaptchaImg()
    {
        $this->logger->notice(__METHOD__);
        $img = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'asfc-svg-captcha']"), 0);

        if (!$img) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $parameters = [
            "regsense" => 1,
        ];
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, $parameters);
        unlink($pathToScreenshot);

        return $captcha;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode("//meta[@id = 'recaptchaSiteKey']/@content")
            ?? $this->http->FindPreg("/recaptchaSiteKey\":\"([^\"]+)/")
        ;

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        if ($this->http->FindPreg("/\"reCaptchaV2\":false,\"reCaptchaV3\":true,/")) {
            $parameters += [
                "version"   => "v3",
                "action"    => "customer_login",
                "min_score" => 0.9,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The server encountered an internal error or misconfiguration and was unable to complete your request.
        if ($this->http->FindSingleNode("
            //h1[
                contains(text(), 'Internal Server Error - Read')
                or contains(text(), 'Service Unavailable - Zero size object')
            ]
            | //bwc-error-page-subtitle[contains(text(), 'The server was acting as a gateway or proxy and received an invalid response from the upstream server.')]
            | //*[contains(text(), 'The server was acting as a gateway or proxy and received an invalid response from the upstream server.')]
            ")
        ) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        // retry
        if (
            $this->http->Response['code'] == 403
            && empty($this->http->Response['body'])
        ) {
            throw new CheckRetryNeededException(2, 10);
        }

        return false;
    }

    // AccountId: 1367843
    private function parseMainPage()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.airfrance.us/");

        // Balance - Miles
        $this->SetBalance($this->http->FindPreg("/fb_solde = '(.+?)';/"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/fb_user = '(.+?)';/")));
        // Number
        $this->SetProperty("Number", $this->http->FindPreg("/fb_number = '(.+?)';/"));
        // Status
        $this->SetProperty("Status", beautifulName($this->http->FindPreg("/fb_short_label = '(.+?)';/")));
    }

    private function getHistory($size = 100)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $data = [
            "operationName" => "ProfileFlyingBlueTransactionHistoryQuery",
            "variables"     => [
                "size"     => $size,
                "offset"   => 1,
                "fbNumber" => $this->Properties['Number'],
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "a4da5deea24960ece439deda2d3eac6c755e88ecfe1dfc15711615a87943fba7",
                ],
            ],
        ];
        $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($data), $headers);

        return $this->http->JsonLog();
    }

    private function getSensorData($secondSensor = false)
    {
        $sensorData = [
            null,
            // linux chrome
            "7a74G7m23Vrp0o5c9037931.45-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,388488,8139507,1920,1056,1920,1080,890,762,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7623,0.620088187310,789459069753.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-102,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.airfrance.us/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1578918139507,-999999,16890,0,0,2815,0,0,4,0,0,82DCE2F9B41A93B96955FDA51D5BC71B~-1~YAAQFIQUAnT2V0RvAQAAEdPangNxv+7D8axGs7cwxTJtxWtrUK0n8rqu8zYcXzSDx0+V3mleEf1/r4MkuO0Jfu6jWDKwvU91efPX5ekHXiQqzWNO9uJbR047at4BzSInO3iVv/iYSmMw+UP/iWaSYUt5TIaTlROK8KI+08TDV0HjIFgXKdQOdHs3Su0l92wArkQZS1qYCurQLDeJu78lIJ7BwqaqPmhVfZefnuMCIyROYCP7l+Q7koa9o9GXC/nYltNvVG4Qj2eXgcSQMGw+AMJLiec782XOj4xY+ZpntwNMaC719S6B5YNHcbk=~-1~-1~-1,29578,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,8139511-1,2,-94,-118,77868-1,2,-94,-121,;3;-1;0",
            // mac chrome
            "7a74G7m23Vrp0o5c9037931.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,388489,9245316,1280,777,1280,800,659,605,1280,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8965,0.03957464519,789459622657,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-102,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.airfrance.us/-1,2,-94,-115,1,1,0,0,0,0,0,5,0,1578919245314,-999999,16890,0,0,2815,0,0,12,0,0,2EC08572C8B1FDC528C470A61785FCB7~-1~YAAQZR8WAksVy5FvAQAAW67rngM20N7YRpbI7fR7ylsJoCbR20yq150645s9nJ4hQ0v5URC68vCRS2e5BU+gYpCxcUDST1rbuiormK7OO1VrCkMYSEUxCI3QH5lObcn8VmesHARhFQnLVy7afkn070Ju9JOhzL86VdrKMNSvAL+AT5xN3N8yNjodnyfECq4r5VW1xxfjQT17Ru7WcxSOgfYfR24nM0LUyvfe5TDAAiq8/wS7G9DLoBTMjiVdHHzzpNTVYUzjU6bgEHa/j2XlZqpD+pl+jKyqeJ24fEPq/Pw6d7pQdAe3cnDtR58=~-1~-1~-1,29313,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,46226565-1,2,-94,-118,78748-1,2,-94,-121,;9;-1;0",
            // mac firefox
            "7a74G7m23Vrp0o5c9037931.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:68.0) Gecko/20100101 Firefox/68.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,388489,9393565,1280,777,1280,800,780,677,1280,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:97,vib:1,bat:0,x11:0,x12:1,6010,0.572089512286,789459696782.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-102,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.airfrance.us/-1,2,-94,-115,1,1,0,0,0,0,0,3,0,1578919393565,-999999,16890,0,0,2815,0,0,9,0,0,F6BF343F7FB0724D1EFB576F9095B6FD~-1~YAAQZR8WAkQZy5FvAQAA4fPtngOC9fEksrwPHw0JaPQ8dy0xE3+q8S5j1P9e7Z/vhMvLZnwHOoyEEwH0+j25pmhn0gUHBg3QAZcnHJUDa6jxvHXbT9uUahCeb64nLdRcTxqHGUz2J9Ac5sIl1KsrgkVvy0A3wzTjA9iHeHYILspdefrLefAHTP8IieQpRS7Bq++ynvVmcA0ZBbeL6MtqxvPqK9vwTOMgSfNDBo3LjDKdjasZ9q2+6fGxASePawWtRRHWkDDvAttvr2X5vID3crIa9Iku0AjOTjBE9yKyvKAEwB8Kt43uqjy/qIk=~-1~-1~-1,30116,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,9393576-1,2,-94,-118,76879-1,2,-94,-121,;8;-1;0",
        ];

        $secondSensorData = [
            null,
            // linux chrome
            "7a74G7m23Vrp0o5c9037931.45-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,388488,8139507,1920,1056,1920,1080,890,762,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7623,0.840873386420,789459069753.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-102,0,0,0,0,1614,-1,0;0,0,1,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,695;1,712;-1,2,-94,-112,https://www.airfrance.us/-1,2,-94,-115,1,1,0,0,0,0,0,817,0,1578918139507,69,16890,0,0,2815,0,0,819,0,0,82DCE2F9B41A93B96955FDA51D5BC71B~-1~YAAQFIQUAqL2V0RvAQAA79bangNfEnk6u6iWji5Fy0OuOWT1GcJQK274moDGSpq8HDleMeJK5T4mqbcWMjFMsX/RlQ56uMHIeORsqglD5W6c8XPUe0qLZtV2yI7Y0x3Ly1mPKCLLUYCOlZrBTUvxNCnZhCR+ModjwCtLzLdJA7av85X5PXZf2yVC+EszjqWxqoWC5C/WXJ9UQ9udKnuO8upcC+VjOjjyWhHRMWPFX9LKny1JWTtK2cu/X8+YCMRxwvxDpOtSCgVXXfKLGmaacDd0c25Fq85PRoNLbX/06rpY7qu7ofUIOjl1mB4uHX36MxQMA9sNsorEqw==~-1~-1~-1,31276,391,-1261358811,30261689-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-705080415;dis;,7,8;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4942-1,2,-94,-116,8139511-1,2,-94,-118,80626-1,2,-94,-121,;3;6;0",
            // mac chrome
            "7a74G7m23Vrp0o5c9037931.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,388489,9245316,1280,777,1280,800,659,605,1280,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8965,0.19788306298,789459622657,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-102,0,0,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.airfrance.us/-1,2,-94,-115,1,1,0,0,0,0,0,586,0,1578919245314,20,16890,0,0,2815,0,0,589,0,0,2EC08572C8B1FDC528C470A61785FCB7~-1~YAAQZR8WAl8Vy5FvAQAAs7brngOnKG9rKYcts0XW+uT5ugSQEwVQ0NLmh2mK2JmRwXGz54LHIT0/9GhNNcNLuubxj9CFgTj+7ZzsBnDuaPmkqG8TV+xbyK9br1+DAlWwbpu/uBuUD5rBZzMsOxSpbLQ03atZu51YSLBonwh6YubWAhcvjx5znmZdq73rw/4+8Yyy0U0KA75qPJDAVj1Aj4LvCncGuuhcvRsde6/kKu2AhLfkuXT405PadDc6icVIi3Cj+aOjxzu/ReO1Ax7Y0h5QHNPeIPFFhEDm+BzXsRtFM9KFmtMLrTPLwmnndj3kJm61WQyORq3Djg==~-1~-1~-1,31591,879,468769117,30261693-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,1008762800;dis;,7,8;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4947-1,2,-94,-116,46226565-1,2,-94,-118,81348-1,2,-94,-121,;4;15;0",
            // mac firefox
            "7a74G7m23Vrp0o5c9037931.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:68.0) Gecko/20100101 Firefox/68.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,388489,9393565,1280,777,1280,800,780,677,1280,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:97,vib:1,bat:0,x11:0,x12:1,6010,0.613630108306,789459696782.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-102,0,0,0,0,1614,-1,0;0,0,0,0,1463,-1,0;0,0,0,0,1334,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.airfrance.us/-1,2,-94,-115,1,1,0,0,0,0,0,511,0,1578919393565,8,16890,0,0,2815,0,0,513,0,0,F6BF343F7FB0724D1EFB576F9095B6FD~-1~YAAQZR8WAkkZy5FvAQAAkfntngPZbkZh7477SljGBVNLrnbbgFqhuoJ2DPv6x88/F6p426OTd1TcunnIflTbDlmkxi5UTBc4eewJB/ZhxJzvBB3VGeHeOHYuF8LGLFMAI/71ZrHMRQ4YGZxFS4B0A3MVG/Fk8JTzgyBtodW3TPR1hOkxUXcsDz0dBFU3WCvfF+GwGCAjjbColWZvqVZCayj/82n6fL/Y7arsdyxnwFZkudtcDJsVDeAQNLzs296JAZcNL0uq9puCRqsJfC/t59fyfy1rV0nZ+u5AZ2rqQKroAl3Py+sS5K/XqLEhwegBI/rcj0JPzhXJJQ==~-1~-1~-1,31501,2,468147653,26067385-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,1836555955;dis;;true;true;true;-300;true;24;24;true;false;unspecified-1,2,-94,-80,5886-1,2,-94,-116,9393576-1,2,-94,-118,78412-1,2,-94,-121,;2;12;0",
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

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_airfrance";
        $sensorData = $cache->get($cacheKey);

        if ($sensorData) {
            $this->logger->info("got cached sensor data");

            return $sensorData;
        }

        /** @var TAccountCheckerAirfrance $selenium */
        $selenium = clone $this;
//        $this->http->brotherBrowser($selenium->http);
        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);
            $choice = rand(1, 2);

            if ($choice == 1) {
                $selenium->useGoogleChrome();
            } else {
                $selenium->useFirefox();
            }
            $selenium->disableImages();
            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            $selenium->http->GetURL($this->http->currentUrl());
            $selenium->waitForElement(WebDriverBy::cssSelector('div[class = "bookingReference--value"]'), 5);
//            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            /*
            $agree = $selenium->waitForElement(WebDriverBy::cssSelector('div.gdpr-agree'), 10);
            if (!$agree) {
                return null;
            }
            $this->logger->info("agree dialog loaded");

            $selenium->http->removeCookies();
            $selenium->driver->manage()->deleteAllCookies();
            $selenium->driver->executeScript("
                (function(send) {
                    XMLHttpRequest.prototype.send = function(data) {
                        console.log('ajax');
                        console.log(data);
                        localStorage.setItem('sensor_data', data);
                    };
                })(XMLHttpRequest.prototype.send);
            ");
            $agree->click();
            $confInput = $selenium->waitForElement(WebDriverBy::cssSelector('input[name = "recordLocator"]'), 5);
            $nameInput = $selenium->waitForElement(WebDriverBy::cssSelector('input[name = "lastName"]'), 5);
            if (!$confInput || !$nameInput) {
                $this->logger->error('conf input or name input not found');
                return null;
            }
            $confInput->sendKeys(substr(str_shuffle(md5(microtime())), 0, 6));
            $nameInput->sendKeys(substr(str_shuffle(md5(microtime())), 0, 6));
            $button = $selenium->waitForElement(WebDriverBy::cssSelector('button#validate_resa_search_button'), 5);
            if (!$button) {
                $this->logger->error('submit button not found');
                return null;
            }
            $button->click();
            sleep(2);
            $button = $selenium->waitForElement(WebDriverBy::cssSelector('button#validate_resa_search_button'), 5);
            if (!$button) {
                $this->logger->error('submit button not found');
                return null;
            }
            $button->click();
            sleep(5);

            $sensorData = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");
            $this->logger->info("[sensor_data]: {$sensorData}");
            if (!empty($sensorData)) {
                $data = @json_decode($sensorData, true);
                if (is_array($data) && isset($data["sensor_data"])) {
                    $this->logger->info("got new sensor data");
                    $cache->set($cacheKey, $data["sensor_data"], 600);
                    $this->sensorData = $data['sensor_data'];
                    return $data["sensor_data"];
                }
            } else {
                $cache->set($cacheKey, 'null', 600);
            }
            */
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            $selenium->http->cleanup();
        }

        return null;
    }

    private function sendSensorData($null = true, $selenium = false)
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl =
            $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if (!$sensorPostUrl) {
            $this->logger->error("sensor data url not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        /** @var HttpBrowser $http2 */
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $http2->RetryCount = 0;

        if ($selenium === true) {
            $this->getSensorDataFromSelenium();
//            $http2->PostURL($sensorPostUrl, json_encode(['sensor_data' => $this->getSensorDataFromSelenium()]));
        } else {
            if ($null === true) {
                $this->key = 0;
            }
            $sensorDataHeaders = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json",
            ];
            $sensorData = [
                'sensor_data' => $this->getSensorData(),
            ];
            $http2->NormalizeURL($sensorPostUrl);
            $http2->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            sleep(1);
            $sensorData = [
                'sensor_data' => $this->getSensorData(true),
            ];
            $http2->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $http2->JsonLog();
            sleep(1);
        }
        sleep(1);
        $http2->RetryCount = 2;

        foreach ($http2->GetCookies('.airfrance.us') as $k => $v) {
            $this->http->setCookie($k, $v);
        }

        return true;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("airfrance sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function ParseItinerary(?string $lastName): array
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];
        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode('//div[@class = "bookingReference--value"]');
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$result['RecordLocator']}", ['Header' => 3]);
        $this->currentItin++;

        $paymentData = $this->parsePaymentData($result['RecordLocator'], $lastName);

        if (isset($paymentData['seats'])) {
            $seats = $paymentData['seats'];
            unset($paymentData['seats']);
        }

        if ($paymentData) {
            $result = array_merge($result, $paymentData);
        }

        $earned = array_filter($this->http->FindNodes(
            "//text()[starts-with(normalize-space(),'Miles earned on this trip')]/ancestor::div[1]/span",
            null,
            "/^\d+$/"
        ));

        if (!empty($earned)) {
            $result['EarnedAwards'] = array_sum($earned) . ' Miles';
        }
        // Passengers
        $passengers = $this->http->FindNodes("//article[@id = 'passengersInfos']//div[contains(@class, 'paxId')]/div[contains(@class, 'paxName')]");

        foreach ($passengers as $passenger) {
            $result['Passengers'][] = beautifulName($passenger);
        }
        // AccountNumbers
        $accountNodes = $this->http->FindNodes("//article[@id = 'passengersInfos']//ul[contains(@class, 'paxNumbers')]//li[contains(@id, 'paxFfCard')]/div[2]", null, '/(\d+)/');
        $accounts = [];

        foreach ($accountNodes as $account) {
            if (!empty($account)) {
                $accounts[] = $account;
            }
        }
        $accounts = array_values(array_unique($accounts));

        if (!empty($accounts)) {
            $result['AccountNumbers'] = $accounts;
        }
        // TicketNumbers
        $ticketNodes = $this->http->FindNodes("//article[@id = 'passengersInfos']//ul[contains(@class, 'paxNumbers')]//li[contains(@id, 'paxTicketNumber')]/div[2]", null, '/(\d[\d\s\-]+)/');
        $tickets = [];

        foreach ($ticketNodes as $ticket) {
            if (!empty($ticket)) {
                $tickets = array_merge($tickets, array_filter(explode(' - ', $ticket)));
            }
        }

        if (!empty($tickets)) {
            $tickets = array_values(array_unique(array_filter($tickets)));
            $result['TicketNumbers'] = $tickets;
        }

        // Segments
        $segments = $this->http->XPath->query("//div[@class = 'flightContent']//div[contains(@class, 'contentBlocFlight') and div[contains(@class, 'flightNumber')]]");
        $this->logger->info("Total {$segments->length} segments were found");
        $airSegments = [];
        $trainSegments = [];

        for ($i = 0; $i < $segments->length; $i++) {
            $segment = $segments->item($i);
            $singleSeg = [];
            // FlightNumber
            $singleSeg['FlightNumber'] = $this->http->FindSingleNode('.//div[@class= "flightNumber"]', $segment, true, '/[A-Z]+\s*(\d+)/');
            // AirlineName
            $singleSeg['AirlineName'] = $this->http->FindSingleNode('.//div[@class= "flightNumber"]', $segment, true, '/([A-Z]+)\s*\d+/');

            if (isset($seats) && isset($seats[$singleSeg['AirlineName'] . $singleSeg['FlightNumber']])) {
                $singleSeg['Seats'] = $seats[$singleSeg['AirlineName'] . $singleSeg['FlightNumber']];
            }
            // DepCode
            $singleSeg['DepCode'] = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[1]//span[contains(@class, 'flightText')]/text()[1]", $segment, true, '/\(([A-Z]{3})\)/');
            // DepName
            $singleSeg['DepName'] = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[1]//span[contains(@class, 'flightText')]/text()[1]", $segment, true, '/([^\(]+)/');
            // DepartureTerminal
            $singleSeg['DepartureTerminal'] = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[1]//span[contains(@class, 'flightText')]/span[@class = 'terminal--Label']", $segment, true, '/TERMINAL\s*([^<]+)/ims');
            // DepDate
            $date = $this->http->FindSingleNode(".//div[contains(@class, 'date')]", $segment, true, "/\,\s*([^<]+)/");
            $time = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[1]//span[contains(@class, 'time')]", $segment, true, '/([\d\:]+ .M)/');
            $this->logger->info("DepDate $date $time / " . strtotime($date . ' ' . $time) . " / ");

            if (strtotime($date . ' ' . $time) !== false) {
                $singleSeg['DepDate'] = strtotime($date . ' ' . $time);
            }
            // ArrCode
            $singleSeg['ArrCode'] = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[3]//span[contains(@class, 'flightText')]/text()[1]", $segment, true, '/\(([A-Z]{3})\)/');
            // ArrName
            $singleSeg['ArrName'] = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[3]//span[contains(@class, 'flightText')]/text()[1]", $segment, true, '/([^\(]+)/');
            // ArrivalTerminal
            $singleSeg['ArrivalTerminal'] = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[3]//span[contains(@class, 'flightText')]/span[@class = 'terminal--Label']", $segment, true, '/TERMINAL\s*([^<]+)/ims');
            // ArrDate
            $time_2 = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[3]/div[1]/span[contains(@class, 'time')]", $segment);
            $nextDay = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/div[3]/div[2]/span[contains(@class, 'time')]", $segment, true, "/\+(\d)\s*Day/ims");
            $this->logger->info("ArrDate $date $time_2 / " . strtotime($date . ' ' . $time_2) . " / next day: +{$nextDay}");

            if (strtotime($date . ' ' . $time_2) !== false) {
                $singleSeg['ArrDate'] = strtotime($date . ' ' . $time_2);

                if ($nextDay) {
                    $singleSeg['ArrDate'] = strtotime("+$nextDay day", $singleSeg['ArrDate']);
                }
            }
            // Status
            $singleSeg['Status'] = $this->http->FindSingleNode(".//div[@class = 'flightStopOver']/following-sibling::ul/li[1]", $segment);

            if ($this->http->FindPreg("/cancell?ed$/i", false, $singleSeg['Status'])) {
                $singleSeg['Cancelled'] = true;
            }
            // Operator
            $singleSeg['Operator'] = $this->http->FindSingleNode(".//span[contains(text(), 'Provided by')]", $segment, true, '/Provided by\s*(.*)/');
            // Aircraft
            $singleSeg['Aircraft'] = $this->http->FindSingleNode('.//span[contains(text(), "Aircraft")]/span', $segment);
            // Cabin
            $singleSeg['Cabin'] = $this->http->FindSingleNode('.//span[contains(text(), "Class")]', $segment, true, '/Class\s*:\s*(.*)/');

            if ($this->http->FindPreg('/train/i', false, ArrayVal($singleSeg, 'Aircraft'))
                || stripos($singleSeg['ArrName'], 'Railway Station') !== false
                || stripos($singleSeg['DepName'], 'Railway Station') !== false
            ) {
                unset($singleSeg['Aircraft']);
                unset($singleSeg['Operator']);
                $trainSegments[] = $singleSeg;
            } else {
                $airSegments[] = $singleSeg;
            }
        }

        if (!empty($trainSegments)) {
            $air = $result;
            $train = $result;
            $train['TripCategory'] = TRIP_CATEGORY_TRAIN;
            $air['TripSegments'] = $airSegments;
            $train['TripSegments'] = $trainSegments;
            $result = [$air, $train];
        } else {
            $result['TripSegments'] = $airSegments;
            $result = [$result];
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    // refs #18794, 20255
    private function parsePaymentData(?string $conf, ?string $lastName): array
    {
        $this->logger->notice(__METHOD__);

        if (!$conf || !$lastName) {
            return [];
        }

        $this->logger->info("Payment Data for Itinerary #{$conf}", ['Header' => 4]);

        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $http2->GetURL('https://iran.airfrance.com/trip');

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $payload = [
            "operationName" => "TripReservationQuery",
            "variables"     => [
                "bookingCode" => $conf,
                "lastName"    => $lastName,
            ],
            "extensions" => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256HashReservation,
                ],
            ],
        ];
        $http2->RetryCount = 0;
        $http2->PostURL("https://iran.airfrance.com/gql/v1?bookingFlow=LEISURE", json_encode($payload), $headers);
        $http2->RetryCount = 2;
        $data = $http2->JsonLog(null, 0, true);

        if (empty($data)) {
            // todo: need to check request from profile, also need to check sha256Hash
            $this->sendNotification('check itinerary price // MI');

            return [];
        }

        $seats = [];

        foreach ($data['data']['reservation']['itinerary']['connections'] ?? [] as $connection) {
            foreach ($connection['segments'] ?? [] as $segment) {
                $flight = $segment['flight']['carrierCode'] . $segment['flight']['flightNumber'];

                if (isset($segment['ancillaries']['seats'], $segment['ancillaries']['seats'][0]['seatNumbers'])) {
                    $seats[$flight] = array_unique($segment['ancillaries']['seats'][0]['seatNumbers']);
                }
            }
        }
        $res = ['seats' => $seats];
        // SpentAwards
        if (isset($data['data']['reservation']['ticketInfo']['totalPrice'])) {
            $totalPrice = $data['data']['reservation']['ticketInfo']['totalPrice'];
        } else {
            return $res;
        }
        // TotalCharge and Currency
        foreach ($totalPrice as $item) {
            $currencyCode = ArrayVal($item, 'currencyCode');
            $currency = $this->currency($currencyCode);

            if (!$currency) {
                if (!in_array($currencyCode, ['MLS', 'MRU'])) {
                    $this->sendNotification('check currency // MI');

                    break;
                }

                continue;
            }
            $res['TotalCharge'] = ArrayVal($item, 'amount');
            $res['Currency'] = $currency;

            break;
        }

        foreach ($totalPrice as $item) {
            $currencyCode = ArrayVal($item, 'currencyCode');

            if ($currencyCode === 'MLS') {
                $res['SpentAwards'] = ArrayVal($item, 'amount');

                break;
            }
        }

        return $res;
    }

    private function getReservationsJson(): ?array
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Content-Type'    => 'application/json',
            'Accept-Language' => 'en',
            'Country'         => 'us',
            'Language'        => 'en',
            'Referer'         => 'https://wwws.airfrance.us/trip/overview',
        ];

        if ($token = $this->http->getCookieByName('XSRF-TOKEN')) {
            $headers['x-xsrf-token'] = $token;
        }
        $variantsSha256Hash = [
            '735f6b8977d354e6539965bd8eb4e131bde690dddc8fea86605cbacd6ec20d1c',
        ];
        $current = Cache::getInstance()->get('airfrance_reservations_sha256Hash');

        if (empty($current)) {
            $this->logger->debug('[getting new value]');
            $last = Cache::getInstance()->get('airfrance_reservations_sha256Hash_last');

            if (empty($last)) {
                $this->logger->debug('get first value');
                $current = $variantsSha256Hash[0];
            } else {
                $this->logger->debug('get next value');
                $previousNum = array_search($last, $variantsSha256Hash, true);

                if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                    $current = $variantsSha256Hash[0];
                } else {
                    $current = $variantsSha256Hash[$previousNum + 1];
                }
            }
        }
        $curNum = array_search($current, $variantsSha256Hash, true);
        $this->logger->debug('request with key: ' . $curNum);

        $payload = [
            'operationName' => 'TripReservationsQuery',
            'variables'     => [
                'daysBack' => 180,
            ],
            'extensions' => [
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => $this->sha256HashReservations,
                ],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($payload),
            $headers);

        if ($this->http->FindPreg('/Operation timed out/', false, $this->http->Error)
            || $this->http->FindPreg('/\{"errors":\[\{"extensions":\{"code":"400"\}\}\],"data":\{"reservations":null\}\}/')
        ) {
            // retry helps
            $this->increaseTimeLimit();
            $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($payload),
            $headers);
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $i = 0;

        while (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
            && $i < count($variantsSha256Hash)
        ) {
            $previousNum = array_search($current, $variantsSha256Hash, true);

            if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                if ($current === $variantsSha256Hash[0]) {
                    $this->logger->error('something wrong with getting new value sha256Hash');
                    $this->sendNotification('check sha256Hash for reservations');

                    return null;
                }
                $current = $variantsSha256Hash[0];
            } else {
                $current = $variantsSha256Hash[$previousNum + 1];
            }
            $curNum = array_search($current, $variantsSha256Hash, true);
            $this->logger->debug('request with key: ' . $curNum);
            // it works: вечером на одном sha256Hash не работает, а утром опять заработло
            $payload['extensions']['persistedQuery']['sha256Hash'] = $current;
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($payload),
                $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (is_null($response)) {
                return null;
            }
            $i++;
        }

        if (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
        ) {
            $this->sendNotification('update sha256Hash (reservations)');

            return null;
        }
        Cache::getInstance()->set('airfrance_reservations_sha256Hash', $current, 60 * 60 * 3);
        Cache::getInstance()->set('airfrance_reservations_sha256Hash_last', $current, 60 * 60 * 24);

        if (is_null($response) || !isset($response->data)) {
            return null;
        }

        if (
            isset($response->errors)
            && isset($response->errors[0]->message)
            && isset($response->data)
        ) {
            $this->logger->error($error = $response->errors[0]->message);
        }

        if (isset($response->data) && (!isset($response->data->reservations) || !isset($response->data->reservations->trips))) {
            if (!isset($error)
                && isset($response->errors[0], $response->errors[0]->extensions, $response->errors[0]->extensions->code)
                && $response->errors[0]->extensions->code == 400
            ) {
                $this->logger->error('something went wrong');

                return null;
            }

            if (!isset($error) || $error !== 'Sorry, something went wrong. Please contact the KLM Customer Contact Centre.') {
                $this->sendNotification("check getting its, json // ZM");
            }

            return null;
        }

        return $response->data->reservations->trips;
    }

    private function getReservationJson(string $conf, string $lastName)
    {
        $this->logger->notice(__METHOD__);

        if (!$conf || !$lastName) {
            return null;
        }

        /*
        $variantsSha256Hash = [
            '5862217c780db7597694b8736e2846f235c5deedcc0322e5c09b6f6ca4c8006d',
        ];
        $current = Cache::getInstance()->get('airfrance_sha256Hash');

        if (empty($current)) {
            $this->logger->debug('[getting new value]');
            $last = Cache::getInstance()->get('airfrance_sha256Hash_last');

            if (empty($last)) {
                $this->logger->debug('get first value');
                $current = $variantsSha256Hash[0];
            } else {
                $this->logger->debug('get next value');
                $previousNum = array_search($last, $variantsSha256Hash, true);

                if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                    $current = $variantsSha256Hash[0];
                } else {
                    $current = $variantsSha256Hash[$previousNum + 1];
                }
            }
        }
        $curNum = array_search($current, $variantsSha256Hash, true);
        $this->logger->debug('request with key: ' . $curNum);
        */

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/json',
            'x-dtpc'               => '4$248262466_516h6vSVHCKULNKHQRIJPUBWKUNFSPCCTHWNHP-0e0',
            'country'              => 'US',
            'language'             => 'en',
            'afkl-travel-country'  => 'US',
            'afkl-travel-host'     => 'AF',
            'afkl-travel-language' => 'en',
        ];
        $payload = [
            'operationName' => 'TripReservationQuery',
            'variables'     => [
                'bookingCode' => $conf,
                'lastName'    => $lastName,
            ],
            'extensions' => [
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => $this->sha256HashReservation,
                ],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($payload),
            $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (is_null($response)) {
            return null;
        }
        /*
        $i = 0;

        while (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
            && $i < count($variantsSha256Hash) - 1
        ) {
            $previousNum = array_search($current, $variantsSha256Hash, true);

            if (!is_int($previousNum) || $previousNum >= count($variantsSha256Hash) - 1) {
                if ($current === $variantsSha256Hash[0]) {
                    $this->logger->error('something wrong with getting new value sha256Hash');
                    $this->sendNotification('update sha256Hash for reservation // MI');

                    return null;
                }
                $current = $variantsSha256Hash[0];
            } else {
                $current = $variantsSha256Hash[$previousNum + 1];
            }
            $curNum = array_search($current, $variantsSha256Hash, true);
            $this->logger->debug('request with key: ' . $curNum);
            // it works: вечером на одном sha256Hash не работает, а утром опять заработло
            $payload['extensions']['persistedQuery']['sha256Hash'] = $current;
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($payload),
                $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (is_null($response)) {
                return null;
            }
            $i++;
        }
        */

        if (isset($response->errors)
            && isset($response->errors[0]->message) && $response->errors[0]->message === 'PersistedQueryNotFound'
        ) {
            $this->sendNotification('update sha256Hash for reservation // MI');

            return null;
        }
        /*Cache::getInstance()->set('airfrance_sha256Hash', $current, 60 * 60 * 3);
        Cache::getInstance()->set('airfrance_sha256Hash_last', $current, 60 * 60 * 24);*/
        $error = $this->checkJsonError($response);

        if (is_string($error)) {
            return $error;
        }

        if (!isset($response->data, $response->data->reservation)) {
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($payload),
                    $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (is_null($response)) {
                return null;
            }
            $error = $this->checkJsonError($response);

            if (is_string($error)) {
                return $error;
            }

            if (!isset($response->data, $response->data->reservation)) {
                $this->sendNotification("check retry // MI");
            }

            return null;
        }
        $message = $response->data->reservation->messages[0]->name ?? null;

        if (
            $message === 'A travel voucher has been requested for this reservation.'
            || $message === 'A travel voucher has been issued for this reservation.'
            || $message === 'Multiple travel vouchers have been issued for this reservation'
            || $message === 'Refund Eligibility'
        ) {
            return $message;
        }

        return $response->data->reservation;
    }

    private function checkJsonError($response)
    {
        if (
            isset($response->errors)
            && isset($response->errors[0]->message)
            && isset($response->data)
            && property_exists($response->data, 'reservation')
            && $response->data->reservation === null
        ) {
            $this->logger->error($response->errors[0]->message);

            return $response->errors[0]->message;
        }

        if ($this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"Internal server error\",\"extensions\":\{\"code\":\"INTERNAL_SERVER_ERROR\"\\}}/")
        ) {
            $this->logger->error($response->errors[0]->message);

            return $response->errors[0]->message;
        }

        if ($this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"Sorry, we couldn't find your booking details. Please/")
            && $this->http->FindPreg("/\"extensions\":\{\"code\":\"RESERVATION_NOT_FOUND\"/")
        ) {
            $this->logger->error($response->errors[0]->message);

            return $response->errors[0]->message;
        }

        return null;
    }

    private function parseReservationJson(string $conf, $reservation, ?bool $fromTrip = false): ?string
    {
        $this->logger->notice(__METHOD__);

        // $reservation->thirdPartyOrderedProducts [hotelProduct|carProduct] not showing on the pages - skip it info

        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $r = $this->itinerariesMaster->add()->flight();
        $r->general()->confirmation($conf);
        $totalMiles = 0;
        $accounts = [];
        $passengers = [];
        $infants = [];

        foreach ($reservation->passengers ?? [] as $passenger) {
            if ($passenger->type == 'INFANT') {
                $infants[] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
            } else {
                $passengers[] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
            }

            if (!$fromTrip) {
                foreach ($passenger->ticketNumber as $ticketNumber) {
                    if (!in_array($ticketNumber, array_column($r->getTicketNumbers(), 0))) {
                        $r->issued()->ticket($ticketNumber, false);
                    }
                }
                $memberships = $passenger->memberships ?? [];

                foreach ($memberships as $membership) {
                    $accounts[] = $membership->number;
                }
                $totalMiles += $passenger->earnQuote->totalMiles ?? 0;
            }
        }

        if (!empty($infants)) {
            $r->general()->infants($infants, true);
        }

        if (!empty($passengers)) {
            $r->general()->travellers($passengers, true);
        }

        if (!empty($totalMiles)) {
            $r->program()->earnedAwards($totalMiles);
        }
        $accounts = array_values(array_unique($accounts));

        if (!empty($accounts)) {
            $r->program()->accounts($accounts, false);
        }

        // SpentAwards
        // TotalCharge and Currency
        $totalPrice = $reservation->ticketInfo->totalPrice ?? [];

        foreach ($totalPrice as $item) {
            $currencyCode = $item->currencyCode;
//             $currency = $this->currency($currencyCode);

            if ($currencyCode == 'MLS') {
                $r->price()->spentAwards($item->amount);

                continue;
            }

            if (empty($r->getPrice()) || empty($r->getPrice()->getTotal()) || $item->amount > $r->getPrice()->getTotal()) {
                $r->price()->total($item->amount);
                $r->price()->currency($currencyCode);
            }
        }

        $connections = $reservation->itinerary->connections ?? [];

        if (count($connections) === 0) {
            if (isset($reservation->messages[0], $reservation->messages[0]->code)
                && $reservation->messages[0]->code === 'EMD_CDET_REFUNDED_SINGLE_VOUCHER'
            ) {
                $this->logger->error($msg = ($reservation->messages[0]->description ?? ''));
                $this->itinerariesMaster->removeItinerary($r);

                if (!empty($msg)) {
                    return $msg;
                }

                return null;
            }
            $this->sendNotification('check connections // MI');
        }

        foreach ($connections as $connection) {
            foreach ($connection->segments as $segment) {
                if ((isset($segment->flight->equipment->code) && $segment->flight->equipment->code === 'TRN')
                    || stripos($segment->destination->airportName, 'Railway Station') !== false
                    || stripos($segment->origin->airportName, 'Railway Station') !== false
                ) {
                    // train segment
                    if (!isset($train)) {
                        $train = $this->itinerariesMaster->add()->train();
                        $train->general()->confirmation($conf);

                        if (!empty($passengers)) {
                            $train->general()->travellers($passengers, true);
                        }
                    }
                    $s = $train->addSegment();
                    $s->extra()->service($segment->flight->carrierName);

                    if (!empty($segment->flight->flightNumber)) {
                        $s->extra()->number($segment->flight->flightNumber);
                    } else {
                        $s->extra()->noNumber();
                    }
                } else {
                    // flight segment
                    $s = $r->addSegment();
                    $s->airline()
                        ->name($segment->flight->carrierCode);

                    if (!empty($segment->flight->flightNumber)) {
                        $s->airline()->number($segment->flight->flightNumber);
                    } else {
                        $s->airline()->noNumber();
                    }
                    $s->extra()->aircraft($segment->flight->equipment->name ?? null, false, true);
                }

                if ($segment->isCancelled) {
                    $s->setCancelled(true);
                }
                $s->departure()
                    ->code($segment->origin->airportCode)
                    ->name($segment->origin->airportName)
                ;

                if (!empty($segment->flight->newDepartureDate)) {
                    $s->departure()->date2($segment->flight->newDepartureDate);
                } else {
                    $s->departure()->date2($segment->flight->departureDate);
                }

                $s->arrival()
                    ->code($segment->destination->airportCode)
                    ->name($segment->destination->airportName);

                if (!empty($segment->flight->newArrivalDate)) {
                    $s->arrival()->date2($segment->flight->newArrivalDate);
                } elseif (!empty($segment->flight->arrivalDate)) {
                    $s->arrival()->date2($segment->flight->arrivalDate);
                } else {
                    $s->arrival()->noDate();
                }
                $duration = round($segment->flight->duration / 60) . 'h' . round($segment->flight->duration % 60) . 'm';
                $s->extra()
                    ->duration($duration)
                    ->cabin($segment->flight->cabinClass, false, true);
//                $segment->flight->newArrivalDate, $segment->flight->newDepartureDate - not print on page(site)
                if (!empty($segment->ancillaries->meals) && is_array($segment->ancillaries->meals)) {
                    foreach ($segment->ancillaries->meals as $meal) {
                        if (isset($meal->name)) {
                            $s->extra()->meal($meal->name);
                        }
                    }
                }

                if (isset($segment->ancillaries->seats)) {
                    $seatNumbers = [];

                    foreach ($segment->ancillaries->seats as $seat) {
                        if (isset($seat->seatNumbers)) {
                            foreach ($seat->seatNumbers as $seatNumber) {
                                if ($this->http->FindPreg('#^[A-Z\d\-/]{1,7}$#i', false, $seatNumber)) {
                                    $seatNumbers[] = $seatNumber;
                                }
                            }
                        }
                    }
                    $seatNumbers = array_unique($seatNumbers);

                    if (!empty($seatNumbers)) {
                        $s->extra()->seats($seatNumbers);
                    }
                }
            }
        }

        if (count($r->getSegments()) > 0) {
            $allCanceled = true;

            foreach ($r->getSegments() as $s) {
                if (!$s->getCancelled()) {
                    $allCanceled = false;

                    break;
                }
            }

            if ($allCanceled) {
                $r->general()->cancelled();
            }
        }

        if (isset($train)) {
            if (count($r->getSegments()) === 0) {
                $this->logger->debug("no flight-segments --> delete flight");
                $this->itinerariesMaster->removeItinerary($r);

                if (!empty($totalMiles)) {
                    $train->program()->earnedAwards($totalMiles);
                }

                if (!empty($accounts)) {
                    $train->program()->accounts($accounts, false);
                }
            } else {
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
            }
            $allCanceled = true;

            foreach ($train->getSegments() as $s) {
                if (!$s->getCancelled()) {
                    $allCanceled = false;

                    break;
                }
            }

            if ($allCanceled) {
                $train->general()->cancelled();
            }
            $this->logger->debug('Parsed Itinerary (Train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);

            return null;
        }

        // only for nearby reservations
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $payload = [
            "operationName" => "TripReservationTicketPriceBreakdownQuery",
            "variables"     => ["id" => $reservation->id],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => '2645ba4eec72a02650ae63c2bd78d14a3f0025dddfca698f570b96a630667fe0',
                ],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", json_encode($payload), $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 2);

        if (!empty($data->data->reservation)) {
            $taxes = [];

            foreach ($data->data->reservation->ticketInformation->passengersTicketInformation as $information) {
                if (isset($information->taxes->totalPrice->amount)) {
                    $taxes[] = $information->taxes->totalPrice->amount;
                }
            }

            if (!empty($taxes)) {
                $r->price()->tax(array_sum($taxes));
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return null;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response['data']['flyingBlueTransactionHistory']['transactions']['transactionsList'])
            && is_array($response['data']['flyingBlueTransactionHistory']['transactions']['transactionsList'])) {
            foreach ($response['data']['flyingBlueTransactionHistory']['transactions']['transactionsList'] as $row) {
                $dateStr = ArrayVal($row, 'transactionDate');
                $postDate = strtotime($dateStr, false);

                if (!$postDate) {
                    $this->logger->notice("skip {$dateStr}");

                    continue;
                }

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }// if (isset($startDate) && $postDate < $startDate)
                $result[$startIndex]['Date'] = $postDate;

                $description = Html::cleanXMLValue(ArrayVal($row, 'description'));
                // 'description' => 'My Trip to {#/transactions/transactionsList[46]/finalDestination}',
                $finalDestination = ArrayVal($row, 'finalDestination', null);
                // 'description' => 'Car & Taxi - {#/transactions/transactionsList[35]/complementaryDescriptionData[0]}',
                $complementaryDescriptionData = ArrayVal($row, 'complementaryDescriptionData', null);

                if (isset($finalDestination)) {
                    $transaction = preg_replace('/\{.+?finalDestination\}/i', $finalDestination, $description);
                } elseif (isset($complementaryDescriptionData)) {
                    for ($i = 0; $i < count($complementaryDescriptionData); $i++) {
                        $transaction = preg_replace("/\{.+?complementaryDescriptionData\[{$i}\]\}/i", trim($complementaryDescriptionData[$i]), $description);
                    }
                } else {
                    $transaction = preg_replace('/\{.+?\}/i', '', $description);
                }
                $this->logger->debug("[$transaction]");

                $details = ArrayVal($row, 'details', []);

                foreach ($details as $detail) {
                    $complementaryDescription = ArrayVal($detail, 'description', null);
                    $complementaryDetailDescriptionData = ArrayVal($detail, 'complementaryDetailDescriptionData', []);
                    $ancillaryLabelCategory = ArrayVal($detail, 'ancillaryLabelCategory', null);

                    if (
                        $complementaryDescription
                        && (
                            !empty($complementaryDetailDescriptionData)
                            || !empty($ancillaryLabelCategory)
                        )
                    ) {
                        for ($i = 0; $i < count($complementaryDetailDescriptionData); $i++) {
                            $complementaryDescription = preg_replace("/\{.+?complementaryDetailDescriptionData\[{$i}\]\}/i", trim($complementaryDetailDescriptionData[$i]), $complementaryDescription);
                        }

                        // https://redmine.awardwallet.com/issues/18358#note-8
                        if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0) {
                            $result[$startIndex]['Date'] = $postDate;
                            $result[$startIndex]['Transaction'] = $transaction . "; " . $complementaryDescription;

                            $result[$startIndex]['Travel Date'] = strtotime(ArrayVal($detail, 'activityDate'), false);

                            if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Transaction'])) {
                                $result[$startIndex]['Bonus Miles'] = ArrayVal($detail, 'milesAmount');
                            } else {
                                $result[$startIndex]['Award Miles'] = ArrayVal($detail, 'milesAmount');
                            }

                            $xpAmount = ArrayVal($detail, 'xpAmount', null);

                            if (isset($xpAmount)) {
                                $result[$startIndex]['Experience Points'] = $xpAmount;
                            }

                            $startIndex++;

                            continue;
                        }// if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0)

                        $transaction .= "; " . $complementaryDescription;
                    }// if ($complementaryDescription && !empty($complementaryDetailDescriptionData))
                    elseif (
                        $transaction === ''
                        && !empty($complementaryDescription)
                        && empty($complementaryDetailDescriptionData)
                    ) {
                        $transaction = $complementaryDescription;
                    }
                }

                if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0) {
                    $this->logger->notice("skip {$finalDestination} / {$transaction}");

                    continue;
                }

                $result[$startIndex]['Transaction'] = $transaction;

                if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Transaction'])) {
                    $result[$startIndex]['Bonus Miles'] = ArrayVal($row, 'milesAmount');
                } else {
                    $result[$startIndex]['Award Miles'] = ArrayVal($row, 'milesAmount');
                }

                $xpAmount = ArrayVal($row, 'xpAmount', null);

                if (isset($xpAmount)) {
                    $result[$startIndex]['Experience Points'] = $xpAmount;
                }

                $startIndex++;
            }// foreach ($response as $row)
        } else {
            $this->logger->notice('transactionsList empty');
        }

        return $result;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->setProxyGoProxies();

            if ($this->attempt > 0) {
                $wrappedProxy = $this->services->get(WrappedProxyClient::class);
                $proxy = $wrappedProxy->createPort($selenium->http->getProxyParams());
                $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
                $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            }

            $request = FingerprintRequest::firefox();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://wwws.airfrance.us/profile");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeoutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                $this->savePageToLogs($selenium);
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException: {$e->getMessage()}");

                throw new CheckRetryNeededException(3);
            }

            $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"] | //span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "Our security system has detected that your IP address has a bad reputation and has blocked further access to our website")] | //div[contains(text(), "The page you\'re looking for cannot be found")]'), 20);
            $this->savePageToLogs($selenium);

            // provider bug fix
            if ($this->http->FindSingleNode('//div[contains(text(), "The page you\'re looking for cannot be found")]')) {
                $selenium->http->GetURL("https://wwws.airfrance.us/endpoint/v1/oauth/redirect?loginPrompt=&source=profile&locale=US/en-US");
                $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"] | //span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "Our security system has detected that your IP address has a bad reputation and has blocked further access to our website")] | //div[contains(text(), "The page you\'re looking for cannot be found")]'), 20);
                $this->savePageToLogs($selenium);
            }

            $loginWithPass = $selenium->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"]'), 0);

            if (!$loginWithPass) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "Our security system has detected that your IP address has a bad reputation and has blocked further access to our website")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            $this->acceptCookies($selenium);
            $loginWithPass->click();

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password"]'), 10);
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="loginId"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $retry = true;
                }

                return false;
            }

            $this->acceptCookies($selenium);

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(10, 20);

            $this->logger->debug("set login");
            $this->savePageToLogs($selenium);
//            $loginInput->sendKeys($this->AccountFields['Login']);
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 7);
            $mover->click();
            $this->logger->debug("set pass");
            $passwordInput->click();
//            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);
            $mover->click();
            $this->logger->debug("click by 'remember me'");

            // remember me
            $selenium->driver->executeScript('
                var rememberme = document.querySelector(\'[id = "mat-slide-toggle-1-input"]\');
                if (rememberme)
                    rememberme.click();
            ');

            $captchaField = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0);
            $this->savePageToLogs($selenium);

            if ($captchaField) {
                $captcha = $selenium->parseCaptchaImg();

                if ($captcha === false) {
                    return false;
                }

                $captchaField->sendKeys($captcha);
                $this->savePageToLogs($selenium);

                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
                $this->savePageToLogs($selenium);

                if (!$button) {
                    return false;
                }

                $button->click();
            }

            $this->logger->debug("click by btn");
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
            $this->savePageToLogs($selenium);

            if (!$button) {
                return false;
            }
            $button->click();

            $captchaField = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 10);
            $this->savePageToLogs($selenium);

            if ($captchaField || $selenium->waitForElement(WebDriverBy::xpath("//div[@formcontrolname='recaptchaResponse']"), 0, false)) {
                $captcha = $selenium->parseCaptchaImg();

                if ($captcha !== false) {
                    $captchaField->sendKeys($captcha);
                } else {
                    $selenium->waitFor(function () use ($selenium) {
                        $this->logger->warning("Solving is in process...");
                        sleep(3);
                        $this->savePageToLogs($selenium);

                        return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                    }, 180);

                    if ($this->attempt == 0 && $this->http->FindSingleNode('//div[@formcontrolname="recaptchaResponse"]//iframe/@title')) {
                        $retry = true;
                    }
                }

                $this->savePageToLogs($selenium);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
                $this->savePageToLogs($selenium);

                if ($button) {
                    $this->logger->debug("click by btn");

                    try {
                        $button->click();
                    } catch (
                        Facebook\WebDriver\Exception\StaleElementReferenceException
                        | Facebook\WebDriver\Exception\ElementClickInterceptedException
                        $e
                    ) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    }
                }
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@class, "bwc-logo-header__user-name")]
                | //div[contains(@class, "bw-profile-recognition-box__info")]/h1
                | //span[contains(text(), "Invalid Captcha")]
                | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]
                | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Get your one-time PIN code")]
                | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
                | //div[contains(@class, "bwc-form-errors")]/span
            '), 15);
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Get your one-time PIN code")]'), 0)) {
                $this->logger->notice('started 2fa');

                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                if ($captchaField = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0)) {
                    $captcha = $selenium->parseCaptchaImg();

                    if ($captcha === false) {
                        return false;
                    }

                    $captchaField->sendKeys($captcha);
                    $this->savePageToLogs($selenium);
                }

                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
                $this->savePageToLogs($selenium);

                if (!$button) {
                    if (isset($captcha) && $captcha === '') {
                        $this->captchaReporting($selenium->recognizer, false);

                        throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                    }

                    return false;
                }

                $button->click();
                $result = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);

                try {
                    if (!$result && $button->isDisplayed()) {
                        $selenium->waitFor(function () use ($selenium) {
                            $this->logger->warning("Solving is in process...");
                            sleep(3);
                            $this->savePageToLogs($selenium);

                            return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                        }, 180);

                        $result = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
                    }

                    if (!$result && $button->isDisplayed()) {
                        $this->logger->info('repeat button click');
                        $button->click();
                        $result = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
                    }

                    $this->savePageToLogs($selenium);
                } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                    $this->logger->error($this->DebugInfo = 'error with repeating button click');
                }

                if (!$result) {
                    $this->savePageToLogs($selenium);
                    $this->DebugInfo = 'otp input not found';
                    $this->logger->error($this->DebugInfo);

                    return false;
                }

                $this->http = $selenium->http;
                $this->State['2fa_hold_session'] = true;
                $this->holdSession();
                $this->AskQuestion('We’ve sent the PIN code to your e-mail address', null, 'Question');

                return false;
            }

            $this->acceptCookies($selenium);
            $this->savePageToLogs($selenium);

            $solvingStatus =
                $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
                ?? $this->http->FindSingleNode('//a[@class = "status"]')
            ;

            if ($solvingStatus) {
                $this->logger->error("[AntiCaptcha]: {$solvingStatus}");

                if (
                    strstr($solvingStatus, 'Proxy response is too slow,')
                    || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection refused')
                    || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection timeout')
                    || strstr($solvingStatus, 'Captcha could not be solved by 5 different workers')
                    || strstr($solvingStatus, 'Solving is in process...')
                    || strstr($solvingStatus, 'Proxy IP is banned by target service')
                    || strstr($solvingStatus, 'Recaptcha server reported that site key is invalid')
                ) {
                    $selenium->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
                }

                $this->DebugInfo = $solvingStatus;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $retry = true;
        } catch (UnexpectedJavascriptException | ErrorException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            if (empty($this->State['2fa_hold_session'])) {
                $selenium->http->cleanup();
            }

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return true;
    }

    private function acceptCookies($selenium)
    {
        $this->logger->notice(__METHOD__);

        $selenium->driver->executeScript('
            try {
                BWCookieBanner.acceptAllCookies();
            } catch (e) {}         
            try {
                document.querySelector(\'#cookiebarModal\').remove();
            } catch (e) {}
        ');
    }

    private function switchToCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $curl = new HttpBrowser("none", new CurlDriver());
//        $curl->setHttp2(true); // causing error "Network error 92 - HTTP/2 stream 0 was not closed cleanly"
        $curl->SetBody($this->http->Response['body']);
        $this->http->cleanup();
        $state = $this->http->driver->getState();
        $cookies = $state['BrowserCookies'] ?? $state['Cookies'] ?? [];
        $this->http->brotherBrowser($curl);

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $this->http = $curl;
    }

    private function solveCaptchaImg()
    {
        $this->logger->notice(__METHOD__);

        if ($captchaField = $this->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0)) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha === false) {
                $this->saveResponse();

                return false;
            }

            $captchaField->sendKeys($captcha);
        }

        return true;
    }
}
