<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAeroflot extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $userAgents = [
        'Mozilla/5.0 (Linux; U; Android 7.0; en-us; MI 5 Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/53.0.2785.146 Mobile Safari/537.36 XiaoMi/MiuiBrowser/9.0.3',
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.2 Safari/605.1.15",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Safari/605.1.15",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15",
    ];

    private $fingerprint = 'e7945b9b7c74a34a056dc1e6b832deb9';
    private $headers = [
        "Accept"              => "application/json",
        "Content-Type"        => "application/json",
        "X-IBM-Client-Id"     => "52965ca1-f60e-46e3-834d-604e023600f2",
        "X-IBM-Client-Secret" => "rU0gE3yP1wV0dY6nJ8kY8pD6pI5dF7xP5nH5nR4cH3sC0rK2rR",
        "Origin"              => "https://www.aeroflot.ru",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        // $this->http->SetProxy($this->proxyReCaptcha()); // refs #18038
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->FilterHTML = false;
        // Language
        $this->http->setCookie("AF_preferredLanguage", "en", ".aeroflot.ru");

//        if ($this->attempt >= 1) {
//        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
        //        $this->setProxyBrightData(null, 'static', 'ru');
        $this->setProxyGoProxies();
//        $this->setProxyBrightData(null, "rotating_residential", "ru");
//        $this->http->SetProxy($this->proxyReCaptchaVultr());
        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 2) {
            $this->http->setUserAgent($this->userAgents[array_rand($this->userAgents)]);
            $agent = $this->http->userAgent;
            $this->State[$userAgentKey] = $agent;
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.aeroflot.ru/personal/profile/info", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->setCookie("AF_preferredLanguage", "en", ".aeroflot.ru");
        $this->http->setCookie("AF_preferredLocale", "ru", ".aeroflot.ru");

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.aeroflot.ru/personal/login?_preferredLanguage=en", [], 40);
        /*
        $this->selenium();
        */
        $this->http->RetryCount = 2;

        if (
            $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
            || strpos($this->http->Error, 'Network error 56 - Received HTTP codeT') !== false
            || $this->http->FindSingleNode('//p[contains(text(), "Для доступа к сайту рекомендуется обновить браузер до актуальной версии")]')
        ) {
            $this->callRetries();
        }

        if (!$this->http->ParseForm('form')) {
            $currentUrl = $this->http->currentUrl();
            $client_id = $this->http->FindPreg("/client_id=([^&]+)/", false, $this->http->currentUrl());
            $nonce = $this->http->FindPreg("/nonce=([^&]+)/", false, $this->http->currentUrl());
            $state = $this->http->FindPreg("/state=([^&]+)/", false, $this->http->currentUrl());

            /*
            $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/?(?:body|link rel=\"stylesheet\")#");

            if (!$sensorPostUrl) {
                $this->logger->error("sensor_data URL not found");

                return $this->checkErrors();
            }
            $this->sendSensorData($sensorPostUrl);
            */

            if ($client_id && $nonce && $state) {
                $this->State['client_id'] = $client_id;
                $this->State['nonce'] = $nonce;
                $this->State['state'] = $state;

                return $this->jsonAuth($currentUrl);
            }// if ($client_id && $nonce && $state)

            return $this->checkErrors();
        }
        $this->http->SetInputValue('login', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('submit0', 'Please wait...');
        $this->http->SetInputValue('return_url', 'https://www.aeroflot.ru/personal/profile/info');
        $this->http->SetInputValue('fingerprint', $this->fingerprint);

        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!isset($sensorPostUrl)) {
            $this->logger->error("sensor_data URL not found");

            return false;
        }

        $captcha = $this->parseCaptcha(null, $this->http->currentUrl());

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->sendSensorData($sensorPostUrl);

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//p[contains(text(), 'A confirmation code has been sent to your mobile phone')]");

        if (!isset($question)) {
            $response = $this->http->JsonLog();
            $code = $response->data->tfa->code ?? null;
            $action = $response->data->tfa->action ?? null;
            $fullNumber = $response->data->tfa->smsinfo->phone->fullNumber ?? null;

            if (
                $action !== "enter_pin"
                || !$fullNumber
                || !$code
            ) {
                $this->logger->notice("question not found");

                if ($code && $action === 'subscribe') {
                    $this->throwProfileUpdateMessageException();
                }

                if ($code && $action === 'update') {
                    $this->throwProfileUpdateMessageException();
                }

                return false;
            }
            $this->State['code'] = $code;
            $question = "The confirmation code has been sent to your phone number {$fullNumber}. Enter the code you received";
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question2fa";

            return true;
        }

        if (!$this->http->ParseForm(null, "//form[@id = 'form']")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == 'Question2fa') {
            $data = [
                "lang" => "en",
                "data" => [
                    "code" => $this->State['code'],
                    "pin"  => $this->Answers[$this->Question],
                ],
            ];
            unset($this->Answers[$this->Question]);

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://gw.aeroflot.ru/api/pr/AAC/2FA/Confirm/v1/get", json_encode($data), $this->headers);

            if (
                strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
                || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
                || strpos($this->http->Error, 'Network error 56 - Unexpected EOF') !== false
                || strpos($this->http->Error, 'Network error 16 -') !== false
                || strpos($this->http->Error, 'Network error 0 -') !== false
                || strpos($this->http->Error, 'Network error 56 - Received HTTP codeT') !== false
            ) {
                $this->callRetries();
            }

            $this->http->RetryCount = 1;
            $response = $this->http->JsonLog();

            // Invalid confirmation code entered
            $error = $response->errors[0]->userMessage ?? null;

            if (
                $error === 'Неопределенная ошибка'
                || strstr($error, 'Invalid session parameters.')
            ) {
                $this->AskQuestion($this->Question, 'Invalid confirmation code', $step);

                return false;
            }

            if (
                $error === 'Incorrect verification code.'
                || $error === 'Validation data error'
                || $error === 'Confirmation code has expired'
                || $error === "Invalid confirmation code."
            ) {
                $this->AskQuestion($this->Question, $error, $step);

                return false;
            }

            if (isset($response->isSuccess) && $response->isSuccess == true && $this->jsonAuth() && $this->jsonAuthGetTokens()) {
                return true;
            }

            if (
                $error === "ru:Передан невалидный токен ConfirmationToken"
                || ($error = $this->http->FindSingleNode('//h1[contains(text(), "Доступ к сайту заблокирован")]'))
                || (isset($response->moreInformation) && $response->moreInformation == 'Token is invalid')
            ) {
                $this->logger->error("[Error]: {$error}");

                $this->callRetries();
            }

            return !$this->checkErrors();
        }

        $this->http->SetInputValue("pin", $this->Answers[$this->Question]);
        $this->http->SetInputValue("submit_pin", "Please wait…");
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }

        // Invalid confirmation code entered
        if ($this->http->FindSingleNode("//p[contains(text(), 'Invalid confirmation code entered') or contains(text(), 'Invalid confirmation code')]")) {
            $this->parseQuestion();

            return false;
        }

        return !$this->checkErrors();
    }

    public function Login()
    {
        // new auth
        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->jsonAuthGetTokens()) {
            return true;
        }

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'info_important')]/ul/li", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//input[@value='Sign In']")) {
            throw new CheckException("Invalid ID or Password", ACCOUNT_INVALID_PASSWORD);
        }

        // Activate Aeroflot Bonus Web Access
        if ($this->http->FindPreg("/Activate Aeroflot Bonus Web Access/ims")) {
            throw new CheckException("Action Required. Please login to Aeroflot Bonus and respond to a message that you will see after your login.", ACCOUNT_PROVIDER_ERROR);
        }

        //# Message: 'Sabre Traverse communication error has occured. Please try again later.Return'
        if ($message = $this->http->FindSingleNode("//div[@id = 'error_report']", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // User must accept personal data collection conditions
        $r = '#All\s+Aeroflot\s+Bonus\s+account\s+services\s+will\s+be\s+activated\s+as\s+soon\s+as\s+you\s+accept\s*<[^>]+>\s*personal\s+data\s+consent\s+form#iu';

        if ($message = $this->http->FindPreg($r)) {
            throw new CheckException(strip_tags(Html::cleanXMLValue($message)), ACCOUNT_PROVIDER_ERROR);
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid authentication credentials
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Invalid authentication credentials')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect login or password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect login or password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid login name or password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Invalid login name or password') or contains(text(), 'Invalid user name or password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'Your account has been locked')]
                | //p[contains(text(), 'Your account is locked.')]
                | //h1[contains(text(), 'Вы были заблокированы!')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Personal account is now available for Aeroflot Bonus members only.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Personal account is now available for Aeroflot Bonus members only.')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The participant’s online account will be inaccessible till May 22, 2019, 12:00 p.m. due to the scheduled upgrade of the technical platform. Please accept our apologies for any inconvenience.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The participant’s online account will be inaccessible till') or contains(text(), 'личный кабинет участника будет недоступен в связи с плановой модернизацией технической платформы')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Invalid login name or password
        if ($message = $this->http->FindSingleNode("//input[contains(@placeholder, 'Type the text from the image')]/@placeholder")) {
            throw new CheckException("Invalid login name or password", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'To work with your personal account, connect to the SMS-Info service')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'Please, confirm that you are not a robot') or contains(text(), 'Пожалуйста, подтвердите, что Вы не робот')]")) {
            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0)->data ?? null;
        // Name
        $this->SetProperty("Name", beautifulName(($response->contact->firstName ?? null) . " " . ($response->contact->lastName ?? null)));
        $loyaltyInfo = $response->loyaltyInfo ?? null;
        // Level
        $this->SetProperty("Level", beautifulName($loyaltyInfo->tierLevel ?? null));

        if (!empty($loyaltyInfo->tierLevelExpirationDate)) {
            $this->SetProperty("LevelExpirationDate", date("m/d/Y", strtotime($loyaltyInfo->tierLevelExpirationDate)));
        }
        // Aeroflot Bonus Number
        $this->SetProperty("Number", $loyaltyInfo->loyaltyId ?? null);
        // Balance
        $this->SetBalance($loyaltyInfo->miles->balance ?? null);
        // Qualifying Miles
        $this->SetProperty("QualMiles", $loyaltyInfo->miles->qualifying ?? null);
        // Segments
        $this->SetProperty("FlightSegments", $loyaltyInfo->currentYearStatistics->segments ?? null);
        // Enrollment date
        if (strtotime($loyaltyInfo->regDate ?? '')) {
            $this->SetProperty("EnrollmentDate", date("m/d/Y", strtotime($loyaltyInfo->regDate)));
        }
        // Expiry date
        // Expiration Date   // refs #9808
        $exp = $loyaltyInfo->miles->expirationDate ?? null;
        $this->logger->debug("Miles activity date: {$exp}");

        if ($exp && strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    public function ParseItineraries()
    {
        $headers = [
            'Authorization' => $this->State['Authorization'],
            'Referer'       => 'https://www.aeroflot.ru/',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://gw.aeroflot.ru/api/pr/SB/UserLoyaltyPNRs/v1/get", '{"lang":"en"}', $this->headers + $headers);
        $data = $this->http->JsonLog(null, 3, true);

        if ($this->http->FindPreg('/se.auth._exc.CredentialsInvalid: LK User info is unavailable/')) {
            $this->logger->debug('Trying with selenium');
            $this->selenium();
            $this->http->PostURL("https://gw.aeroflot.ru/api/pr/SB/UserLoyaltyPNRs/v1/get", '{"lang":"en"}', $this->headers + $headers);
            $data = $this->http->JsonLog(null, 3, true);
        }

        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('/\{"data":\{"pnrs":\[\]\},"error":null,"isSuccess":true\}/')) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        $pnrs = $this->arrayVal($data, ['data', 'pnrs'], []);
        $this->logger->info(sprintf('Found %s itineraries', count($pnrs)));

        $notActiveIts = 0;

        foreach ($pnrs as $pnrData) {
            $active =
                ArrayVal($pnrData, 'is_active', null)
                ?? ArrayVal($pnrData, 'isActive')
            ;

            if ($active === false) {
                $this->logger->error("Skipping inactive flight");
                $notActiveIts++;

                continue;
            }

            $this->parseItineraryJson($pnrData);
        }

        // there is not active itineraries in general list (without legs / tickets)
        if ($notActiveIts === count($pnrs) && $notActiveIts > 0) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.aeroflot.ru/sb/pnr/app/us-en";
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Reservation code (PNR)",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last Name",
                "Size"     => 30,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $regExpBadProxy = '/(?:Operation timed out after|Received HTTP code 503 from proxy after CONNECT)/';

        if ($this->http->FindPreg($regExpBadProxy, false, $this->http->Error)
            || $this->http->FindSingleNode("//h1[contains(text(),'Доступ к сайту временно ограничен владельцем веб-ресурса.')]")) {
            // it helps sometimes
            $this->http->removeCookies();
            $this->setProxyGoProxies(true);
            $this->http->setUserAgent($this->userAgents[array_rand($this->userAgents)]);
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));

            if ($this->http->FindPreg($regExpBadProxy, false, $this->http->Error)) {
                $this->sendNotification('failed to retrieve itinerary by conf // MI');

                return null;
            }
        }
        /*
//        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/assets/.+?)'\]\);#");
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/webcontent/.+?)'\]\);#");

        if (!$sensorPostUrl) {
            $sensorPostUrl = $this->http->FindPreg('# src="([^\"]+)"></script></body>#');
        }

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");
            $this->sendNotification('failed to retrieve itinerary by conf // MI');

            return null;
        }

        $this->http->RetryCount = 2;
        $this->sendSensorDataRetrieve($sensorPostUrl);
        */

        $url = 'https://www.aeroflot.ru/se/api/app/pnr/view/v3';
        $postParams = [
            'first_name'  => '',
            'lang'        => 'en',
            'last_name'   => $arFields['LastName'],
            'pnr_locator' => $arFields['ConfNo'],
        ];
        $headers = [
            'Accept'          => 'application/json',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type'    => 'application/json',
            'X-App-Identity'  => '0',
            'Referer'         => 'https://www.aeroflot.ru/sb/pnr/app/us-en',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($url, json_encode($postParams), $headers);
        $this->http->RetryCount = 2;
        $resp = $this->http->JsonLog(null, 3, true);

        $errorCode = $this->arrayVal($resp, ['error', 'code']);

        if (in_array($errorCode, [
            '1001000211',
            '2005000543',
        ])
        ) {
            return "Book Travel {$arFields['ConfNo']} not found";
        } elseif ($errorCode === '2005000114') {
            return 'Passenger name can only contain Latin symbols, apostrophes and hyphens';
        } elseif ($errorCode) {
            $this->sendNotification('check retrieve unknown error code // MI');

            return null;
        }
        $data = ArrayVal($resp, 'data');

        if (!$data) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $it = $this->parseItineraryJson($data);

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            isset($this->State['Authorization'])
        ) {
            $this->http->PostURL("https://gw.aeroflot.ru/api/pr/LKAB/Profile/v3/get", '{"lang":"en","data":{}}', $this->headers + ['Authorization' => $this->State['Authorization']]);
            $response = $this->http->JsonLog();

            if (isset($response->data->loyaltyInfo)) {
                return true;
            }
        }

        return false;
    }

    private function jsonAuth($currentUrl = null)
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "lang" => "en",
            "data" => [
                "oidc"    => [
                    "clientId"      => "52965ca1-f60e-46e3-834d-604e023600f2",
                    "scopes"        => [
                        "openid",
                        "user-loyalty-profile",
                        "personal-cabinet",
                        "feedback",
                    ],
                    "responseTypes" => [
                        "code",
                        "id_token",
                        "token",
                    ],
                    "nonce"         => "0.14447320263406693",
                    "redirectUri"   => "https://www.aeroflot.ru/auth/app",
                ],
                "auth"    => [
                    "login"    => $this->AccountFields['Login'],
                    "password" => $this->AccountFields['Pass'],
                ],
                "tfa"     => [
                    "fingerprint"    => $this->fingerprint,
                    "fingerprintRaw" => "{\"excludes\":{\"userAgent\":true,\"language\":true,\"timezoneOffset\":true,\"timezone\":true,\"sessionStorage\":true,\"localStorage\":true,\"indexedDb\":true,\"openDatabase\":true,\"addBehavior\":true,\"plugins\":true,\"fonts\":true,\"fontsFlash\":true,\"audio\":true,\"hasLiedLanguages\":true,\"adBlock\":true,\"enumerateDevices\":true,\"wasRedirect\":false}}",
                ],
                "relogin" => false,
            ],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://gw.aeroflot.ru/api/pr/AAC/Authorization/v1/get", json_encode($data), $this->headers);

        if ($this->http->FindPreg("/\"userMessage\":\"Please, confirm that you are not a robot\",\"devMessage\":\"Please, confirm that you are not a robot\"/")) {
            $this->http->JsonLog();
            $captcha = $this->parseCaptcha("6LfPVBETAAAAAFL_KotHis1PSaeI3UV11RplpwTo", $currentUrl);

            if ($captcha === false) {
                return false;
            }

            $data['data']['auth']['captchaText'] = $captcha;
            $this->http->PostURL("https://gw.aeroflot.ru/api/pr/AAC/Authorization/v1/get", json_encode($data), $this->headers);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Доступ к сайту заблокирован")]')) {
            $this->logger->error("[Error]: {$message}");

            $this->callRetries();
        }

        // wtf?
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Для Вас успешно создан личный кабинет")]')) {
            $this->logger->error("[Error]: {$message}");
            $this->DebugInfo = $message;

            return false;
        }

        $this->http->RetryCount = 2;

        return true;
    }

    private function jsonAuthGetTokens()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        $this->State['Authorization'] = 'Bearer ' . ($response->data->tokens->accessToken ?? null);

        if (isset($response->data->tokens->accessToken)) {
            $this->http->setCookie('auth.accessToken', $response->data->tokens->accessToken, '.aeroflot.ru');
            $this->http->setCookie('auth.idToken', $response->data->tokens->idToken, '.aeroflot.ru');
        }

        /*
        if (!isset($this->State['sensorPostUrl'])) {
            $this->logger->error("sensor_data URL not found");

            return false;
        }
        $this->sendSensorData2fa($this->State['sensorPostUrl']);
        */

        if (isset($response->isSuccess) && $response->isSuccess == true) {
            /*
            $data = [
                "lang" => "en",
                "data" => [
                    "oidc"    => [
                        "clientId"      => $this->State['client_id'],
                        "scopes"        => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "responseTypes" => [
                            "code",
                        ],
                        "nonce"         => $this->State['nonce'],
                        "redirectUri"   => "https://www.aeroflot.ru/personal/auth/complete",
                    ],
                    "tfa"     => [
                        "fingerprint"    => $this->fingerprint,
                        "fingerprintRaw" => "{\"excludes\":{\"userAgent\":true,\"language\":true,\"timezoneOffset\":true,\"timezone\":true,\"sessionStorage\":true,\"localStorage\":true,\"indexedDb\":true,\"openDatabase\":true,\"addBehavior\":true,\"plugins\":true,\"fonts\":true,\"fontsFlash\":true,\"audio\":true,\"hasLiedLanguages\":true,\"adBlock\":true,\"enumerateDevices\":true}}",
                    ],
                    "relogin" => false,
                ],
            ];
            $this->headers['Referer'] = "https://www.aeroflot.ru/aac/app/ru-en/login?nonce={$this->State['nonce']}&state={$this->State['state']}&redirect_uri=https%3A%2F%2Fwww.aeroflot.ru%2Fpersonal%2Fauth%2Fcomplete&response_type=code&client_id={$this->State['client_id']}&_preferredLocale=ru&scope=openid+profile+email&_preferredLanguage=en";
            $this->headers['Origin'] = 'https://www.aeroflot.ru';
            $this->http->PostURL("https://gw.aeroflot.ru/api/pr/AAC/Authorization/v1/get", json_encode($data), $this->headers);
            $response = $this->http->JsonLog();
            $code = $response->data->tokens->code ?? null;

            if (!isset($code)) {
                $this->logger->error("code not found");

                return false;
            }

            $response = $this->http->JsonLog();
            $code = $response->data->tokens->code ?? null;
            $this->http->GetURL("https://www.aeroflot.ru/personal/auth/complete?code={$code}&state={$this->State['state']}");
            */

            if ($this->loginSuccessful()) {
                return true;
            }
        } elseif (isset($response->errors[0]->userMessage)) {
            $message = $response->errors[0]->userMessage;
            $code = $response->errors[0]->code ?? null;
            $status = $response->errors[0]->status ?? null;
            $this->logger->error("[Error]: {$message}");

            if ($code == 'AUTH.AAC.07010' && $message === 'You specified the wrong account information to access the system') {
                throw new CheckException("Incorrect username or password", ACCOUNT_INVALID_PASSWORD);
            }

            if ($status == 400 && $message === 'Incorrect login or password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($status == 400 && $message === "You specified the wrong account information to access the system") {
                throw new CheckException('Incorrect login or password.', ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    private function parseCaptcha($key, $currentUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'form']//div[@class = 'g-recaptcha']/@data-sitekey");
        }

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $currentUrl ?? $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - DNS failure')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Service unavailable/ims")) {
            throw new CheckException("Service unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        //# The service is not available. Please try again later.
        if ($message = $this->http->FindPreg("/(The service is not available\. Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# This web page is under maintenance
        if ($this->http->FindSingleNode("//p[contains(text(), 'This web page is under maintenance')]")
            || $this->http->Response['code'] == 500) {
            throw new CheckException("This web page is under maintenance. Please check back later.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function sendSensorData2fa($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $sensorData = [
            '7a74G7m23Vrp0o5c9142141.68-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,397719,3108271,1920,1050,1920,1080,1920,368,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7570,0.607103604303,808216559006.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,0,0,0,989,0,1;-1,2,-94,-108,0,1,282,17,0,4,0;1,1,389,86,0,4,0;2,2,550,86,0,4,0;3,2,551,17,0,0,0;4,1,1022,18,0,1,0;5,1,3143,17,0,4,0;6,1,3219,86,0,4,0;7,2,3312,86,0,4,0;8,2,3314,17,0,0,0;9,1,19117,-2,0,0,0;10,3,19118,-2,0,0,0;11,2,19182,-2,0,0,0;12,1,19741,-2,0,0,0;13,3,19743,-2,0,0,0;14,2,19806,-2,0,0,0;15,1,20176,-2,0,0,0;16,3,20177,-2,0,0,0;17,2,20242,-2,0,0,0;18,1,20801,-2,0,0,0;19,3,20802,-2,0,0,0;20,2,20879,-2,0,0,0;-1,2,-94,-110,0,1,36,707,235;1,4,52,707,235,0;2,2,54,707,235,0;3,1,1129,707,234;4,1,1136,707,234;5,1,1140,707,234;6,1,1148,708,233;7,1,1155,710,233;8,1,1166,712,232;9,1,1172,714,231;10,1,1180,716,230;11,1,1188,718,229;12,1,1196,721,229;13,1,1204,724,229;14,1,1212,729,229;15,1,1310,858,249;16,1,1316,873,254;17,1,1324,888,257;18,1,1333,905,262;19,1,1340,921,266;20,1,1350,937,269;21,1,2637,949,251;22,1,2638,949,251;23,1,2644,925,255;24,1,2653,902,259;25,1,2662,881,263;26,1,2669,862,267;27,1,2676,846,270;28,1,2684,831,274;29,1,2693,818,278;30,1,2700,804,282;31,1,2709,791,286;32,1,2717,781,290;33,1,2725,772,293;34,1,2734,762,299;35,1,2741,754,304;36,1,2749,745,308;37,1,2756,739,313;38,1,2764,734,316;39,1,2772,730,319;40,1,2781,727,321;41,1,2789,724,323;42,1,2797,722,325;43,1,2805,719,326;44,1,2813,718,328;45,1,2821,717,329;46,1,2829,715,330;47,1,2837,714,331;48,1,2845,713,332;49,1,2853,712,334;50,1,2861,710,335;51,1,2869,709,336;52,1,2877,708,336;53,1,2885,707,337;54,3,3080,707,337,0;55,4,3110,707,337,0;56,2,3112,707,337,0;57,1,3292,706,337;58,1,3300,706,338;59,1,3428,706,338;60,1,3436,705,338;61,1,3444,705,339;62,1,3452,704,339;63,1,3460,703,339;64,1,3469,703,340;65,1,3477,702,341;66,1,3484,702,341;67,1,3493,700,342;68,1,3501,699,342;69,1,3508,697,344;70,1,3516,695,345;71,1,3524,692,346;72,1,3532,687,348;73,1,3540,681,351;74,1,3549,674,355;75,1,3556,665,359;76,1,3564,655,364;77,1,4633,462,358;78,1,4637,467,349;79,1,4645,474,339;80,1,4653,481,328;81,1,4661,487,316;82,1,4669,492,305;83,1,4677,496,294;84,1,4685,500,284;85,1,4693,501,274;86,1,4701,503,268;87,1,4709,504,263;88,1,4717,504,260;89,1,5029,506,311;90,1,5037,507,311;91,1,5045,508,311;92,1,5053,510,311;93,1,5061,513,312;94,1,5069,515,313;95,1,5077,519,315;96,1,5084,523,317;97,1,5092,528,320;98,1,5101,534,323;99,1,5109,540,326;100,1,5117,547,329;101,1,5125,555,331;102,1,5133,564,333;103,1,5140,573,335;104,1,5149,582,338;138,3,5560,666,399,-1;139,4,5583,666,399,-1;140,2,5584,666,399,-1;178,3,15977,651,338,0;179,4,16075,651,338,0;180,2,16076,651,338,0;331,3,32207,799,341,-1;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,1128;3,3071;2,4365;3,5553;2,24055;3,32200;-1,2,-94,-112,https://www.aeroflot.ru/aac/app/ru-en/login?nonce=GRX86YsXGZAtme6iYpb0CzbgbB9rQGEgTIKHlVrqx7Yy4efCUvNHf8z2TtheyQG5&state=aaR83IgcG0PhoH1ukAVgB3mVxZq8B7s2&redirect_uri=https%3A%2F%2Fwww.aeroflot.ru%2Fpersonal%2Fauth%2Fcomplete&response_type=code&client_id=3b7f2489-be9b-4d2f-9a65-584617bad3f3&_preferredLocale=ru&scope=openid+profile+email&_preferredLanguage=en-1,2,-94,-115,256245,544511,32,0,0,0,800723,32207,0,1616433118013,16,17292,21,332,2882,8,0,32210,1076312,0,0885E5EB435E97AC8F68594AF666B2BB~-1~YAAQD05lX3/vFlp4AQAAZzrsWgWHwrWRRHF32znK1f19s7VUoOJGNlbRkqnhtGHjSZRJGZkvD7MzCNavdRc8ELhwJE8XYUyVU8oLTXjwu+lCbRR8pzwVh8CQM7hShu9gc0Z1wKps4i4Qtl72Uxl+uwROF0Fhvmc6KaLwdieDuXij++7a8ypnsXMTc8eHsqWioK6b/gq+ZufitjGkdhG03x344vQtH+F0kZcByz2zqquZpyf2dwfakxNBSR7T5rD3c/3MWXwan1kVGk2jbLaLnV8R5uaNiMnoQEvEVE2tkUXqpefpB7asI0IfdrPatK2UFGKk16bi8uBLZOuJWIvglwgZLoJjUjxJKHse89Yjwq+ISVC07WV0PsVjHh4rBAIvF5D+/Lc1glYhdoJBekkHcsPIGyy2DN2CxkRrtRHwB0yB6+gPZQ==~-1~||1-xfUrtLmRwd-2250-10-1000-2||~-1,42847,500,-1240007765,30261689,PiZtE,97790,45-1,2,-94,-106,1,5-1,2,-94,-119,7,10,9,9,16,18,13,42,7,6,6,914,886,126,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.6c79cae016cd2,0.4132c15a734d7,0.5aaa61238d65f,0.c6a3972597a93,0.1f2e60c212232,0.d9a1ae30e0d79,0.7259a03ee7423,0.26f61f3a0c683,0.beedc157c84d4,0.479ca31cddfd9;72,55,45,196,91,51,199,67,49,76;2154,1568,2205,8843,2986,873,8753,1592,2053,3177;0885E5EB435E97AC8F68594AF666B2BB,1616433118013,xfUrtLmRwd,0885E5EB435E97AC8F68594AF666B2BB1616433118013xfUrtLmRwd,2250,2250,0.6c79cae016cd2,0885E5EB435E97AC8F68594AF666B2BB1616433118013xfUrtLmRwd22500.6c79cae016cd2,10,107,108,151,186,123,42,220,213,90,168,32,89,204,148,67,78,44,175,81,143,120,220,170,95,229,33,76,194,229,168,112,467,0,1616433120223;-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,83923074-1,2,-94,-118,282028-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc.,ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 core),deccf2cc89f0263e782f462c55c4092b5905a39efd0433460e08827135d727aa,35-1,2,-94,-121,;10;8;0',
            '7a74G7m23Vrp0o5c9142141.68-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,397719,2423152,1920,1050,1920,1080,1920,368,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7570,0.218987851109,808216211575.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,151,-1,-1,-1;-1,2,-94,-109,0,133,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aeroflot.ru/personal-1,2,-94,-115,1,32,32,151,133,0,284,2937,0,1616432423151,18,17292,0,0,2882,0,0,2938,284,0,13BF2FF3E6C5D93AE48D736837C98EA9~-1~YAAQBZsXAmZZxkR4AQAA5mPhWgW3+sV8AzNGEa7NUvbWu44vfWxFntlGfs6YBLi52N+Jg1ttlt/7RpxYn6qlHeaAKbLTyCFZBHg7o0Us8I9zraeZo+N2Bp/VCT61uzvu6RmS64Pz59LwX56wrDu6lqxdjPq20JqeFdFVGvl+d7C8mBEz+VTq2YhMtjAgvtdFwbaVHVMxKs7Ir6IOOuCAZ6bBjXUBQrU7pnXYCu92CJXHCYO0BoYOaE5vvWBkE6UzX/3YehZqWOkNCz69GfzHoJPANBD4iNbKsCH+ZJUvj/9gOMNjlw4G/jv4hCd1qP8jBBGrXyK2ZY7YjQNQFzmpb+mkfeF+YBN4addZ0aRrLm+zuLvMBcG86b3JrnK2ygFR1nZp/1n/pM1/5e/UzqJmrQlBNECLFnr+rvzFDCOcX7i1O/FG+w==~-1~||1-jvrXdWgPrh-2250-10-1000-2||~-1,41819,948,1765733010,30261689,PiZtE,39977,96-1,2,-94,-106,8,2-1,2,-94,-119,32,42,35,39,62,119,46,121,14,6,6,1464,1453,211,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.5e9a32af3172e,0.18050b1521b1b,0.82b71daf34c18,0.fbd90d88bad97,0.05235d0fd044d,0.e62dee56b2434,0.2552dc1496758,0.66f9afd7ffb1e,0.d9cde4f60804d,0.ae94f9638fc1c;123,234,76,23,81,33,283,23,97,62;2872,7220,2361,650,2520,941,8467,693,3124,1872;13BF2FF3E6C5D93AE48D736837C98EA9,1616432423151,jvrXdWgPrh,13BF2FF3E6C5D93AE48D736837C98EA91616432423151jvrXdWgPrh,2250,2250,0.5e9a32af3172e,13BF2FF3E6C5D93AE48D736837C98EA91616432423151jvrXdWgPrh22500.5e9a32af3172e,94,179,65,171,113,15,89,99,254,211,69,46,93,196,111,98,117,97,92,128,15,164,115,249,146,38,167,214,155,3,214,8,464,0,1616432426088;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5386-1,2,-94,-116,7269450-1,2,-94,-118,128776-1,2,-94,-129,a14d82a29111eda6de622053c3b3d95a4b55e96e78b5f0d60be59f70ad5fa3c4,1,0,Google Inc.,ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 core),deccf2cc89f0263e782f462c55c4092b5905a39efd0433460e08827135d727aa,35-1,2,-94,-121,;4;17;0',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return $key;
    }

    private function sendSensorData($sensorPostUrl)
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);
        $this->State['sensorPostUrl'] = $sensorPostUrl;

        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "text/plain;charset=UTF-8",
        ];
        $sensorData = "7a74G7m23Vrp0o5c9131331.68-1,2,-94,-100,Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:86.0) Gecko/20100101 Firefox/86.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,397648,3549891,1853,1053,1920,1080,1853,312,1853,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:106,vib:1,bat:0,x11:0,x12:1,5585,0.13683960368,808071774945.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aeroflot.ru/aac/app/ru-en/login?nonce=3itLmKk8mQV8y68kotuIYd8F0L5t037IFwZsHHadiTCx8CbYpwe9b7XVenHshYC7&state=zqS8XVHvSC0cev1t6cl1Bq14rlWeVHF3&redirect_uri=https%3A%2F%2Fwww.aeroflot.ru%2Fpersonal%2Fauth%2Fcomplete&response_type=code&client_id=3b7f2489-be9b-4d2f-9a65-584617bad3f3&_preferredLocale=ru&scope=openid+profile+email&_preferredLanguage=en-1,2,-94,-115,1,32,32,0,0,0,0,515,0,1616143549891,5,17289,0,0,2881,0,0,516,0,0,600F27D4F061DA1C6DFF0948E813C7B4~-1~YAAQ14BtaOrbRiR4AQAAQIapSQWir5NUkeZqA9St4iFkP9O32iBi4LVqLM/n8OkLA5KuvtWYQQjiJIc5o+dOwdbyP/S3kxFf+CRzieQre3N/5Mmb5lYq8vKXOydEj0eH2Ltskd1RxGcqZQrR8goF0l0GzCKZUQaEjfHIqHAgc0N+Iod4mCCUno310z+H3PNAvMWl36f1RkCsBtg7576Lel/dCHsWpLSPp0/07sBuRL54sYSl1eHLzZ3mVjnPdgIWOeL2PLI9n44GIm08oT+GrnGMasABkslqgpMIpqqUy/UGNx3nCsji5TdTvY34QX1ZHddVjTozhzdbLpnynWCr+jcvp8rzsWVl+c6TyGpumLyEuUUdcVKzOYCvt1pv35KstEUDxuKFboAhKUmCjJi8dIftbx3y4HMuQ1rIKgbIjJ0EmYNNCDGg~-1~||1-gfVndAZaOd-2250-10-1000-2||~-1,42385,600,966451614,26067385,PiZtE,43975,108-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,400,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,-1279939100;-324940575;dis;;true;true;true;-180;true;24;24;true;false;unspecified-1,2,-94,-80,6501-1,2,-94,-116,479237055-1,2,-94,-118,118228-1,2,-94,-129,9feb9225ad5184a8162588771f441d89e1b7d80189ac0e715d0fdd97d7b0e0e4,1,95fbc1d02ed569659fca0b75b04a08528e40a595668bcffc7abeca7f2496c765,NVIDIA Corporation,GeForce 9800 GT/PCIe/SSE2,ee3ca419de9fc7e3701a8610f05cf62f2d40b6e376b977d9e29169b06f08ff50,26-1,2,-94,-121,;31;4;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]), $headers);
        $this->http->JsonLog();
        sleep(1);

        $sensorData = "7a74G7m23Vrp0o5c9131331.68-1,2,-94,-100,Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:86.0) Gecko/20100101 Firefox/86.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,397648,3549891,1853,1053,1920,1080,1853,312,1853,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:106,vib:1,bat:0,x11:0,x12:1,5585,0.481256078240,808071774945.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aeroflot.ru/aac/app/ru-en/login?nonce=3itLmKk8mQV8y68kotuIYd8F0L5t037IFwZsHHadiTCx8CbYpwe9b7XVenHshYC7&state=zqS8XVHvSC0cev1t6cl1Bq14rlWeVHF3&redirect_uri=https%3A%2F%2Fwww.aeroflot.ru%2Fpersonal%2Fauth%2Fcomplete&response_type=code&client_id=3b7f2489-be9b-4d2f-9a65-584617bad3f3&_preferredLocale=ru&scope=openid+profile+email&_preferredLanguage=en-1,2,-94,-115,1,32,32,0,0,0,0,1194,0,1616143549891,5,17289,0,0,2881,0,0,1194,0,0,600F27D4F061DA1C6DFF0948E813C7B4~-1~YAAQ14BtaPzbRiR4AQAAXoipSQUekvfzEsn3RgEqc5+84rhG/Pm2TxNu4jrzbRPavokm+BMCm7vVQeV1IWWrX0x48X/wA/uQB924ixWDR7amDreGh5CIHlQRzuhQzo88RVHrmHG9azNPPiVFMItnq2T1wxuxU3x8xR262aoWU6RyhG71Igh4UnwwzAPsM3CdTsC57ZxunTDYACdzCy1Q7Qs0yUW5qq26o1XfqDUTdbu+hYtWT82b7n5+ut5ehjEDG6AqxaAXC3a1n3X274nVjbySnsPlF8f+tlCcjYn7uP8UzTSfvv2TVJinpkzKGlEOGGvngjVXObB6YlvNbWfRjvPrWpnFfb9gRYq0QORHHUgW8lz5JKYdvf+IFy+D8cPPJCMvJYEsKWpcNxkg33DjrtkSrH9NdOtfxT5ryhCn/iUbrJJOMOc5~-1~||1-gfVndAZaOd-2250-10-1000-2||~-1,42741,600,966451614,26067385,PiZtE,18946,50-1,2,-94,-106,8,2-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,400,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.50137ddb619fc8,0.c1e4d6b279028,0.907670f61f175,0.b29e79dca38eb8,0.40bc0afa2ccee,0.180ecba9e216c8,0.8e16eafaaf7e5,0.58ff139ce1651,0.42e0645956a71,0.436baaba576378;60,24,0,43,8,20,7,11,13,6;4645,1936,9,3503,325,1499,259,911,779,161;600F27D4F061DA1C6DFF0948E813C7B4,1616143549891,gfVndAZaOd,600F27D4F061DA1C6DFF0948E813C7B41616143549891gfVndAZaOd,2250,2250,0.50137ddb619fc8,600F27D4F061DA1C6DFF0948E813C7B41616143549891gfVndAZaOd22500.50137ddb619fc8,76,82,101,239,44,126,57,16,220,84,49,178,37,225,248,145,61,71,40,204,176,57,73,161,195,106,199,115,136,101,158,180,380,0,1616143551085;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,-1279939100;-324940575;dis;;true;true;true;-180;true;24;24;true;false;unspecified-1,2,-94,-80,6501-1,2,-94,-116,479237055-1,2,-94,-118,153167-1,2,-94,-129,9feb9225ad5184a8162588771f441d89e1b7d80189ac0e715d0fdd97d7b0e0e4,1,95fbc1d02ed569659fca0b75b04a08528e40a595668bcffc7abeca7f2496c765,NVIDIA Corporation,GeForce 9800 GT/PCIe/SSE2,ee3ca419de9fc7e3701a8610f05cf62f2d40b6e376b977d9e29169b06f08ff50,26-1,2,-94,-121,;1;4;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]), $headers);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);

        return true;
    }

    private function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result["Kind"] = "T";
        // ConfirmationNumber
        $result['RecordLocator'] = $this->http->FindSingleNode("//span[@class='locator']", null, true, '/(?:Code|Booking\s+reference):\s*([A-Z\d]{6})/ims');
        $this->logger->info(sprintf('Parse Itinerary #%s', $result['RecordLocator']), ['Header' => 4]);
        // Passengers
        $passName = $this->http->FindNodes("//table[starts-with(@class, 'passengers')]//td[@class='passenger']/span/b");

        if (is_array($passName) && count($passName) > 0) {
            $result['Passengers'] = array_map(function ($item) {
                return beautifulName($item);
            }, array_unique($passName));
        }

        $accNumbers = $this->http->FindNodes("//table[starts-with(@class, 'passengers')]//td[@class='passenger']/span/small/b");

        if (is_array($accNumbers) && count($accNumbers) > 0) {
            $result['AccountNumbers'] = array_map(function ($item) {
                return preg_replace('/[^\d\,]/', '', $item);
            }, array_unique($accNumbers));
        }
        $ticketNumbers = $this->http->FindNodes("//table[starts-with(@class, 'passengers')]//td[@class='passenger']/following-sibling::td/a");

        if (is_array($ticketNumbers) && count($ticketNumbers) > 0) {
            $result['TicketNumbers'] = array_unique($ticketNumbers);
        }

        // Air Trip Segments

        $tripSeg = [];
        $segments = $this->http->XPath->query("//table[starts-with(@class, 'segments')]//tr[td[@class='segment']]");
        $this->logger->debug("Total " . $segments->length . " segments were found");
        $n = 0;

        for ($i = 0; $i < $segments->length; $i++) {
            // FlightNumber
            $tripSeg[$n]['FlightNumber'] = $this->http->FindSingleNode("td[1]/b[1]", $segments->item($i), true, "/\w{2}\s*(\d+)/ims");
            // AirlineName
            $tripSeg[$n]['AirlineName'] = $this->http->FindSingleNode("td[1]/b[1]", $segments->item($i), true, "/(\w{2})\s*\d+/ims");
            // Operating airline
            $tripSeg[$n]['Operator'] = $this->http->FindSingleNode("td[1]/b[2]", $segments->item($i));
            // DepCode
            $tripSeg[$n]['DepCode'] = $this->http->FindSingleNode("td[2]/div[@class='airport']/span", $segments->item($i));
            $tripSeg[$n]['DepName'] = $this->http->FindSingleNode("td[2]/div[@class='city']", $segments->item($i)) . " " . $this->http->FindSingleNode("td[2]/div[@class='airport']/text()[1]", $segments->item($i));
            $tripSeg[$n]['DepartureTerminal'] = $this->http->FindSingleNode("td[2]/div[@class='terminal']/b", $segments->item($i));
            // ArrCode
            $tripSeg[$n]['ArrCode'] = $this->http->FindSingleNode("td[3]/div[@class='airport']/span", $segments->item($i));
            $tripSeg[$n]['ArrName'] = $this->http->FindSingleNode("td[3]/div[@class='city']", $segments->item($i)) . " " . $this->http->FindSingleNode("td[3]/div[@class='airport']/text()[1]", $segments->item($i));
            $tripSeg[$n]['ArrivalTerminal'] = $this->http->FindSingleNode("td[3]/div[@class='terminal']/b", $segments->item($i));
            // DepDate
            $date = $this->http->FindSingleNode("td[2]/div[@class='date']", $segments->item($i));
            $depTime = $this->http->FindSingleNode("td[2]/div[@class='time']", $segments->item($i));
            $this->logger->debug("Dep time: $date $depTime");
            $depDateTime = strtotime($date . '  ' . $depTime);

            if ($depDateTime) {
                $tripSeg[$n]['DepDate'] = $depDateTime;
            }
            // ArrDate
            $date = $this->http->FindSingleNode("td[3]/div[@class='date']", $segments->item($i));
            $arrTime = $this->http->FindSingleNode("td[3]/div[@class='time']", $segments->item($i));
            $this->logger->debug("Arr time: $date $arrTime");
            $arrDateTime = strtotime($date . ' ' . $arrTime);

            if ($arrDateTime) {
                $tripSeg[$n]['ArrDate'] = $arrDateTime;
            }

            // Cabin
            $tripSeg[$n]['Cabin'] = $this->http->FindSingleNode("td[4]/text()[contains(., 'Class:')]/following-sibling::b[1]", $segments->item($i), true, '/([^\/]+)/');
            // if there isn't Cabin in details
            if (strlen(trim($tripSeg[$n]['Cabin'])) == 1) {
                unset($tripSeg[$n]['Cabin']);
            }
            // Status
            $tripSeg[$n]['Status'] = $this->http->FindSingleNode("td[4]/text()[contains(., 'Status:')]/following-sibling::b[1]", $segments->item($i));
            // Aircraft
            $tripSeg[$n]['Aircraft'] = $this->http->FindSingleNode("td[4]/text()[contains(., 'Aircraft:')]/following-sibling::b[1]", $segments->item($i));
            // Meal
            $tripSeg[$n]['Meal'] = $this->http->FindSingleNode("td[4]/text()[contains(., 'Meal:')]/following-sibling::b[1]", $segments->item($i));
            // Duration
            $tripSeg[$n]['Duration'] = $this->http->FindSingleNode("td[4]/text()[contains(., 'Flight:')]/following-sibling::b[1]", $segments->item($i));
            // BookingClass
            $tripSeg[$n]['BookingClass'] = $this->http->FindSingleNode("td[4]/text()[contains(., 'Class:')]/following-sibling::b[1]", $segments->item($i), true, '/\/\s*(.+)/');
            // if there isn't Cabin in details
            if (!isset($tripSeg[$n]['BookingClass'])) {
                $tripSeg[$n]['BookingClass'] = $this->http->FindSingleNode("td[4]/text()[contains(., 'Class:')]/following-sibling::b[1]", $segments->item($i), true, '/^\s*[A-Z]{1,2}\s*$/');
            }

            $n++;
        }
        $result['TripSegments'] = $tripSeg;

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function sendSensorDataRetrieve($sensorPostUrl)
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = "7a74G7m23Vrp0o5c9129651.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,396604,2550082,1536,824,1536,864,1536,150,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8323,0.330801192165,805951275040.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aeroflot.ru/sb/pnr/app/us-en#/search?_k=f687mz-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1611902550081,-999999,17243,0,0,2873,0,0,7,0,0,F8AB8108FC710F9F8532E1C818168257~-1~YAAQncwTAtwOdj13AQAA/fzgTAULNSsuyaggMOuVpIN8nlZWR+Hb7+wjVKHPJWY5iWsNDiPz+MsX2SBhrwauIYQsi11pxFzGnVNO97amrWptrHcBPvbPNB/sblKF7vjADgFSIUV8FSTegO9AXSwoGbWH7t0sy7gjt1BYPWU3h36XpHEsBvnePBH8q4bXSjB6K66pKQCAup9CA8BYTe+SuPkfKQOUDfvm1yRFXd6QKqeJdBEnUSt36P56Xy1s/vGxOp4oAgf98O+QDJro/hEzO2SV8vGHJAZXMh3cjXGpTcwjBGmpJczqQUYJ8Q==~-1~-1~-1,29715,-1,-1,30261693,PiZtE,56471,95-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,68852151-1,2,-94,-118,78157-1,2,-94,-129,-1,2,-94,-121,;7;-1;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);

        $sensorData = "7a74G7m23Vrp0o5c9129651.66-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,396604,2550082,1536,824,1536,864,1536,150,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8323,0.12303883261,805951275040.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,299,-1,-1,-1;-1,2,-94,-109,0,297,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aeroflot.ru/sb/pnr/app/us-en#/search?_k=f687mz-1,2,-94,-115,1,32,32,299,297,0,596,662,0,1611902550081,57,17243,0,0,2873,0,0,663,596,0,F8AB8108FC710F9F8532E1C818168257~-1~YAAQncwTAvMOdj13AQAAsADhTAUIRh3gzlvnDe+h0Vs5CeIc0l4yb5Z2/GH1MzIBrrucOqx2rM46qjP88ijR0p3EqyccckvaR+cbrG79A75421lPz4i+Mv4qN3SEl5B9v5MCjAYNHgJh2iYlpDabEp0YENMGYxQHzZA5Ru3pSRIceGR/K4XN65KMUJPVFe1y/mdoUYM2euSrAaf2k0J1jcVIiPkkuSil0AQ+nnQ1n6wo+/xBcF6ZeV+S61yAjJ44Sfm2s5Gdr7byowMRq/f/cjvxznwDIujZ/dZetmBCtQKGPkWNWSbeSa0Rd6QWq/LuC3kA18oPvZ/qxAbDYULfLktERPOkiA==~-1~||1-WHNIXMdDCB-2250-10-1000-2||~-1,34377,742,1694081546,30261693,PiZtE,108989,103-1,2,-94,-106,9,1-1,2,-94,-119,68,70,192,39,63,68,41,184,55,72,51,1868,2024,300,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,1454252292;895908107;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5540-1,2,-94,-116,68852151-1,2,-94,-118,90970-1,2,-94,-129,e7fa8e5f9331739c57ebdd0dd49e398c3662b0b364b74c7c64bf301e2c66dd3a,1.25,79d476b3ee7a1d053d47c234f8b00e881ef941614b47791e1d4610cb5e47a0ff,,,,0-1,2,-94,-121,;31;11;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);

        return true;
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

    private function parseItineraryJson($data)
    {
        $this->logger->notice(__METHOD__);
        $active =
            ArrayVal($data, 'is_active', null)
            ?? ArrayVal($data, 'isActive')
        ;

        if ($active === false) {
            $this->logger->error("Skipping inactive flight");

            return [];
        }

        // skip itineraries without segments
        $legs = ArrayVal($data, 'legs');
        $tickets = ArrayVal($data, 'tickets', []);

        if ($legs === [] && ArrayVal($data, 'tickets') === []) {
            $this->logger->error("Skipping Itinerary without segment");

            return [];
        }

        $f = $this->itinerariesMaster->add()->flight();
        // RecordLocator
        $confNo =
            ArrayVal($data, 'pnr_locator', null)
            ?? ArrayVal($data, 'pnrLocator')
        ;
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);
        $f->general()
            ->confirmation($confNo, "Booking code", true)
            ->date2(ArrayVal($data, 'pnr_date', null) ?? ArrayVal($data, 'pnrDate'));

        // Passengers
        $passengers = ArrayVal($data, 'passengers', []);

        foreach ($passengers as $pass) {
            $firstName =
                ArrayVal($pass, 'first_name', null)
                ?? ArrayVal($pass, 'firstName', '')
            ;
            $lastName =
                ArrayVal($pass, 'last_name', null)
                ?? ArrayVal($pass, 'lastName', '')
            ;
            $name = trim(beautifulName("{$firstName} {$lastName}"));

            if ($name) {
                $f->addTraveller($name);
            }

            $loyalty_id = ArrayVal($pass, 'loyalty_id', null);

            if ($loyalty_id) {
                $f->addAccountNumber($loyalty_id, false);
            }

            $ticketing_documents = $this->arrayVal($pass, ['ticketing_documents', 'tickets'], []);

            foreach ($ticketing_documents as $ticketing_document) {
                $number = ArrayVal($ticketing_document, 'number');
                $tickets[] = $number;
            }
        }
        // TicketNumbers
        if (empty($tickets)) {
            $tickets = ArrayVal($data, 'tickets', []);
        }

        $f->setTicketNumbers($tickets, false);
        // TripSegments
        $legs = ArrayVal($data, 'legs', []);
        $seats = ArrayVal($data, 'seats', []);

        foreach ($legs as $leg) {
            $segments = ArrayVal($leg, 'segments', []);

            foreach ($segments as $seg) {
                $s = $f->addSegment();

                $s->airline()
                    ->name(ArrayVal($seg, 'airline_code', null) ?? ArrayVal($seg, 'airlineCode'))
                    ->operator(ArrayVal($seg, 'operating_airline_code', null) ?? ArrayVal($seg, 'operatingAirlineCode'), true, true)
                    ->number(ArrayVal($seg, 'flight_number', null) ?? ArrayVal($seg, 'flightNumber'))
                ;
                // DepCode
                $depCode =
                    $this->arrayVal($seg, ['origin', 'airport_code'])
                    ?? $this->arrayVal($seg, ['origin', 'airportCode'])
                ;
                // DepName
                $depName =
                    $this->arrayVal($seg, ['origin', 'airport_name'])
                    ?? $this->arrayVal($seg, ['origin', 'airportName'])
                ;
                // DepartureTerminal
                $departureTerminal =
                    $this->arrayVal($seg, ['origin', 'terminal_name'])
                    ?? $this->arrayVal($seg, ['origin', 'terminalName'])
                ;
                // DepDate
                $depDate =
                    ArrayVal($seg, 'departure', null)
                    ?? ArrayVal($seg, 'departureDateTime')
                ;

                $s->departure()
                    ->code($depCode)
                    ->name($depName)
                    ->terminal($departureTerminal, true)
                    ->date2($depDate)
                ;

                // ArrCode
                $arrivalCode =
                    $this->arrayVal($seg, ['destination', 'airport_code'])
                    ?? $this->arrayVal($seg, ['destination', 'airportCode'])
                ;
                // ArrName
                $arrivalName =
                    $this->arrayVal($seg, ['destination', 'airport_name'])
                    ?? $this->arrayVal($seg, ['destination', 'airportName'])
                ;
                // ArrivalTerminal
                $arrivalTerminal =
                    $this->arrayVal($seg, ['destination', 'terminal_name'])
                    ?? $this->arrayVal($seg, ['destination', 'terminalName'])
                ;
                // ArrDate
                $arrivalDate =
                    ArrayVal($seg, 'arrival', null)
                    ?? ArrayVal($seg, 'arrivalDateTime')
                ;

                $s->arrival()
                    ->code($arrivalCode)
                    ->name($arrivalName)
                    ->terminal($arrivalTerminal, true)
                    ->date2($arrivalDate)
                ;

                // Cabin
                $cabin =
                    ArrayVal($seg, 'cabin_name', null)
                    ?? ArrayVal($seg, 'fareGroupName')
                ;
                // Aircraft
                $aircraft =
                    ArrayVal($seg, 'aircraft_type_name', null)
                    ?? ArrayVal($seg, 'aircraftTypeName', null)
                ;
                // Status
                $status =
                    ArrayVal($seg, 'status_name', null)
                    ?? ArrayVal($seg, 'statusName')
                ;
                // BookingClass
                $bookingCode =
                    ArrayVal($seg, 'booking_class', null)
                    ?? ArrayVal($seg, 'bookingClass')
                ;
                // Duration
                $duration =
                    ArrayVal($seg, 'flight_time_name', null)
                    ?? ArrayVal($seg, 'flightTimeName')
                ;
                // Meal
                $meal = ArrayVal($seg, 'meal_names', null);
                $meals = ArrayVal($seg, 'mealNames', null);

                if (!empty($meal)) {
                    $s->extra()->meal($meal);
                } elseif (!empty($meals)) {
                    $s->extra()->meals($meals);
                }

                $s->extra()
                    ->aircraft($aircraft, true, true)
                    ->cabin($cabin)
                    ->bookingCode($bookingCode)
                    ->status($status)
                    ->duration($duration)
                ;

                foreach ($seats as $seat) {
                    if (
                        ArrayVal($seat, 'segment_number') != ArrayVal($seg, 'segment_number')
                        || ArrayVal($seat, 'segmentNumber') != ArrayVal($seg, 'segmentNumber')
                    ) {
                        continue;
                    }
                    $seatsNumbers = ArrayVal($seat, 'seat_number', null) ?? ArrayVal($seat, 'seatNumber');

                    foreach ($seatsNumbers as $seatsNumber) {
                        $s->addSeat($seatsNumber);
                    }
                }
            }// foreach ($segments as $seg)
        }

        if (count($f->getTicketNumbers()) == 0 && count($f->getSegments()) == 0) {
            $urlPrint = ArrayVal($data, 'tickets_doc_print_url', null);

            if (!empty($urlPrint)) {
                $this->logger->warning('try parse html');
                $this->parseItineraryHtml($urlPrint);

                return [];
            } else {
                $this->logger->error('Skipping "No data" flight');
            }

            return [];
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseItineraryHtml($url)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->flight();

        $this->http->NormalizeURL($url);
        // $this->http->removeCookies();
        // $http = $this->http;
        $http = new HttpBrowser('none', new CurlDriver());
        $this->http->brotherBrowser($http);
        $http->RetryCount = 0;
        $newUA = $http->userAgent;
        $i = 0;

        while ($newUA === $http->userAgent && $i < 10) {
            $newUA = $this->userAgents[array_rand($this->userAgents)];
            $i++;
        }
        $http->GetURL($url);
        $http->RetryCount = 2;

        if (empty($http->Response['body'])) {
            return;
        }
        $pnrs = array_unique($http->FindNodes("//div[starts-with(normalize-space(),'Booking code')]/following-sibling::div[1]"));

        if (count($pnrs) !== 1) {
            $this->sendNotification("check booking code");

            return;
        }

        $r->general()
            ->confirmation(array_shift($pnrs), 'Booking Code')
            ->travellers($http->FindNodes("//div[normalize-space()='E-ticket itinerary receipt']/following-sibling::div[1]/descendant::text()[normalize-space()!=''][1]"));
        $r->issued()
            ->tickets($http->FindNodes("//div[normalize-space()='E-ticket number:']/following-sibling::div[1]"),
                false);
        $r->program()
            ->accounts($http->FindNodes("//div[normalize-space()='Aeroflot Bonus:']/following-sibling::div[1]"),
                false);

        $phones = $http->XPath->query("(//div[normalize-space()='Contact details:'])[1]/following-sibling::div[div[@class='text-bold']]");
        $this->logger->debug('Phones: ' . $phones->length);

        foreach ($phones as $item) {
            $phone = $http->FindSingleNode("./div[@class='text-bold']", $item);
            $text = $http->FindSingleNode("./following-sibling::div[1]", $item);
            $r->program()
                ->phone($phone, $text);
        }

        $sums = $http->FindNodes("//div[normalize-space()='Amount paid and payment method']/following-sibling::div[normalize-space() and not(contains(.,'***'))][1]");
        $total = 0.0;
        $currency = null;

        foreach ($sums as $sum) {
            $currency = $http->FindPreg("/^([A-Z]{3})\s*\d[\d.]+$/", false, $sum);
            $total += \AwardWallet\Common\Parser\Util\PriceHelper::cost($http->FindPreg("/(?:[A-Z]{3})\s*(\d[\d.]+)$/",
                false, $sum));
        }
        $r->price()
            ->total($total)
            ->currency($currency);

        $segments = $http->XPath->query("(//div[normalize-space()='Itinerary']//ancestor::div[1])[1]/div[contains(@class,'route__flight')]");

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $flight = $http->FindSingleNode(".//span[@class='route__flight-number-data']",
                $segment);
            $depDate = $http->FindSingleNode(".//div[contains(@class,'time-destination__date time-destination__date--left')]", $segment);
            $depTime = $http->FindSingleNode(".//div[contains(@class,'time-destination__from')]/descendant::text()[normalize-space()!=''][1]", $segment);
            $this->logger->debug("DepDate: $depDate, $depTime");

            if (empty($depDate) || empty($depTime)) {
                return;
            }
            $class = $http->FindSingleNode(".//span[normalize-space()='Class:']/following-sibling::span[1]",
                $segment);
            $s->airline()
                ->name($http->FindPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/", false, $flight))
                ->number($http->FindPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", false, $flight));
            $s->departure()
                ->name($http->FindSingleNode(".//div[@class='route__flight-city-from']", $segment))
                ->date(strtotime($depTime, strtotime($depDate)))
                ->code($http->FindSingleNode(".//div[contains(@class,'time-destination__from')]/descendant::text()[normalize-space()!=''][2]",
                    $segment, false, '/^[A-Z]{3}/'));
            $s->arrival()
                ->name($http->FindSingleNode(".//div[@class='route__flight-city-to']", $segment))
                ->code($http->FindSingleNode(".//div[contains(@class,'time-destination__to')]/descendant::text()[normalize-space()!=''][1]",
                    $segment, false, '/^[A-Z]{3}/'));

            $arrDate = $http->FindSingleNode(".//div[contains(@class,'time-destination__date time-destination__date--right')]", $segment);
            $arrTime = $http->FindSingleNode(".//div[contains(@class,'time-destination__to')]/descendant::text()[normalize-space()!=''][2]", $segment);
            $this->logger->debug("ArrDate: $arrDate, $arrTime");

            if (!empty($arrDate) && !empty($arrTime)) {
                $s->arrival()->date(strtotime($arrTime, strtotime($arrDate)));
            } elseif (trim($http->FindSingleNode(".//div[contains(@class,'time-destination__to')]/div[contains(@class,'time-destination__time')]", $segment)) == '') {
                $s->arrival()->noDate();
            }
            $s->extra()
                ->status($http->FindSingleNode(".//span[normalize-space()='Status:']/following-sibling::span[1]",
                    $segment))
                ->cabin($http->FindPreg("/^(.+)\s*\/\s*[A-Z]{1,2}$/", false, $class))
                ->bookingCode($http->FindPreg("/^.+\s*\/\s*([A-Z]{1,2})$/", false, $class));
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function callRetries()
    {
        $this->logger->notice(__METHOD__);

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
            return false;
        }

        $this->markProxyAsInvalid();

        throw new CheckRetryNeededException(3, 1);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $allCookies = array_merge($this->http->GetCookies(".aeroflot.ru"), $this->http->GetCookies(".aeroflot.ru", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.aeroflot.ru"), $this->http->GetCookies("www.aeroflot.ru", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".www.aeroflot.ru"), $this->http->GetCookies(".www.aeroflot.ru", "/", true));

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->setUserAgent($this->http->userAgent);
            //$selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.aeroflot.ru/lk/app/ru-en/service");
            sleep(5);
            $this->savePageToLogs($selenium);

//            $this->logger->notice("cookies");
//            $this->logger->debug(var_export($allCookies, true), ["pre" => true]);

            foreach ($allCookies as $key => $value) {
                if ($key == 'auth.idToken' || $key == 'auth.accessToken') {
                    $this->logger->notice("set '{$key}' to localStorage");
                    $selenium->driver->executeScript('
                        localStorage.setItem("' . $key . '", "' . $value . '");
                    ');
                }

                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".aeroflot.ru"]);
            }
            $selenium->http->GetURL('https://www.aeroflot.ru/lk/app/ru-en/services');

            $selenium->waitForElement(\WebDriverBy::xpath("//p[contains(text(),'My bookings')]"), 25);
            $this->savePageToLogs($selenium);

            /*$cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (preg_match('/^(ngenix_|session-cookie|CURRENCY_ICER|session_id|_ym_isad|_ym_d|_ym_uid|feedbackSystemId|AF_preferredLanguage|AF_preferredLocale|POS_COUNTRY|aac|feedback_session_id|sso_session_id|pkl_session_id)/', $cookie['name'])) {
                    $this->logger->debug("Skip: {$cookie['name']}: {$cookie['value']}");

                    continue;
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }*/
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }
    }
}
