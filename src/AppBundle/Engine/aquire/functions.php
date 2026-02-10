<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAquire extends TAccountChecker
{
    use ProxyList;
//    use SeleniumCheckerHelper;

    private $headers = [
        "Accept"       => "*/*",
        "origin"       => "https://accounts.qantas.com",
        "content-type" => "application/json; charset=utf-8",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerAquireSelenium.php";

            return new TAccountCheckerAquireSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
        $this->http->SetProxy($this->proxyAustralia(), false);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.qantasbusinessrewards.com/myaccount", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
//        $this->http->GetURL("https://www.qantasbusinessrewards.com/login");
        $this->http->GetURL("https://www.qantasbusinessrewards.com/myaccount");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $this->http->Form = [];
        $this->http->FormURL = 'https://accounts.qantas.com/identity/perform_login';
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        /*
        if ($key = $this->http->FindPreg("/var recaptcha_site_key\s*=\s*'(.+?)';/")) {
            $captcha = $this->parseReCaptcha($key);
            if ($captcha === false)
                return false;
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->debug(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(., 'website is currently down for planned maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is currently undergoing maintenance.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Our site is currently undergoing maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're performing some site maintenance, to make your ride as smooth as possible
        if ($message = $this->http->FindSingleNode('//h1[contains(., "We\'re performing some site maintenance, to make your ride as smooth as possible") or contains(text(), "We’re performing some site maintenance, to make your ride as smooth as possible")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), '404 Page Not Found')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $sensorData = $this->getSensorDataFromSelenium();

        if (!empty($sensorData)) {
            $this->sendSensorData($sensorData);
        }

        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 401) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // 2fa
        $mfaRequired = $response->mfaRequired ?? null;

        if ($mfaRequired && $this->parseQuestion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        // from js
        $messagelogin = $this->http->getCookieByName("messagelogin");

        if (empty($messagelogin)) {
            $cookies = $this->http->GetCookies("www.qantasbusinessrewards.com", "/", true);
            $this->logger->debug(var_export($cookies, true), ["pre" => true]);

            if (isset($cookies['messagelogin'])) {
                $messagelogin = $cookies['messagelogin'];
            }
        }
        $this->logger->debug("Messagelogin {$messagelogin}");

        $reason = $response->reason ?? null;
        $attemptsRemaining = $response->attemptsRemaining ?? null;

        if (!empty($reason)) {
            switch ($reason) {
            case $reason == 'bad_credentials' && $attemptsRemaining > 0:
                throw new CheckException("The login details you entered don't match our records. Please note that your account will be locked after {$attemptsRemaining} failed attempts. Reset your password  or contact our Qantas Business Rewards Service Centre on 13 74 78, Mon to Sat, 7am to 7pm (AEST).", ACCOUNT_INVALID_PASSWORD);

                break;

            case $reason == 'bad_credentials' && $attemptsRemaining == 0:
            case $reason == 'disabled_account':
                throw new CheckException("Your account has been locked after several unsuccessful attempts. Please reset your password to continue or contact our Qantas Business Rewards Service Centre on 13 74 78, Mon to Sat, 7am to 7pm (AEST).", ACCOUNT_LOCKOUT);

                break;

            default:
                $this->logger->error("Unknown error: {$reason}");
        }
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://accounts.qantas.com/identity/mfa/otp", $this->headers);
        $response = $this->http->JsonLog();

        $email = $response->email ?? null;
        $phone = $response->mobile ?? null;
        $q = $response->securityQuestion ?? null;

        $questions = [
            'CL' => 'What is your favourite colour?',
            'FS' => 'What was the name of your first school?',
            'MN' => 'What is your mother’s maiden name?',
            'PN' => 'What was the name of your first pet?',
            'TB' => 'What is the name of the town you were born in?',
        ];

        if (!isset($questions[$q])) {
            $this->sendNotification("aquire. Unknown security question: {$q}");

            return false;
        }
        $question = $questions[$q];

        // prevent code spam    // refs #6042
//        if ($this->isBackgroundCheck())
//            $this->Cancel();

        $this->State['Question'] = "Please enter the code we sent to {$email}";

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function requestCode()
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "securityAnswer" => $this->Answers[$this->Question],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://accounts.qantas.com/identity/mfa/otp/email", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        // The answer you've entered is incorrect. You have 2 more tries. Please try again!
        $response = $this->http->JsonLog();
        $message = $response->errorMessage ?? null;

        if (strstr($message, 'Answer to security question didn')) {
            $attempt = $response->fieldErrorMessages[0]->errorMessage ?? null;
            $field = $response->fieldErrorMessages[0]->field ?? null;

            if ($field == 'attemptsRemaining' && is_numeric($attempt)) {
                $this->AskQuestion($this->Question, "The answer you've entered is incorrect. You have {$attempt} more tries. Please try again!");

                return false;
            }

            return false;
        }
        // Please enter the code we sent to ...
        if ($this->http->Response['code'] == 204) {
            $this->AskQuestion($this->State['Question'], null, "Code");

            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == 'Question' && $this->requestCode()) {
            return false;
        }

        $this->sendNotification("aquire. Answer was entered");
        // Please enter the code we sent to ...
        $data = [
            "code" => $this->Answers[$this->Question],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://accounts.qantas.com/identity/mfa/otp/verify", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        unset($this->Answers[$this->Question]);
        // The verification code you've entered is incorrect. You have 2 more tries. Please try again or resend code.
        $attemptsRemaining = $response->fieldErrorMessages[0]->errorMessage ?? null;
        $errorCode = $response->errorCode ?? null;

        if (!empty($errorCode)) {
            switch ($errorCode) {
                case $errorCode == 'INVALID_OTP' && $attemptsRemaining > 0:
                    $error = "The verification code you've entered is incorrect. You have {$attemptsRemaining} more tries. Please try again or resend code.";
                    $this->AskQuestion($this->Question, $error, "Code");

                    return false;

                    break;

                default:
                    $this->logger->error("Unknown error: {$errorCode}");
            }
        }

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.qantasbusinessrewards.com/api/qbr/dashboard");
        $response = $this->http->JsonLog(null, true, true);
        // Balance - Points balance
        $this->SetBalance(ArrayVal($response, 'pointBalance'));
        // Member since
        $this->SetProperty("Joined", ArrayVal($response, 'joinDate'));
        // ABN - Australian Business Number
        $this->SetProperty("ABN", ArrayVal($response, 'abn'));
        // Status
        $level = ArrayVal($response, 'level');

        if ($this->http->FindPreg("/^L\d+$/", false, $level)) {
            $level = str_replace('L', 'Level ', $level);
        }
        $this->SetProperty("Status", $level);
        // Status expiration
        $this->SetProperty("StatusExpiration", ArrayVal($response, 'levelExpiryDate'));
        // Qantas Points earned from flying this membership year
        $this->SetProperty("YTDQantasPoints", intval(ArrayVal($response, 'flyingPoints')));
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($response, 'familyName') . " " . ArrayVal($response, 'familyName')));
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindSingleNode("//iframe[contains(@src, 'recaptcha')]/@src", null, true, "/siteKey=([^<\&]+)/");
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
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $sensor = [
            "7a74G7m23Vrp0o5c9915381.28-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36,uaend,12147,20030107,ru,Gecko,5,0,0,0,377625,5506182,1920,1053,1920,1080,1920,584,1920,,cpen:0,i1:0,dm:0,cwen:1,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7609,0.313658386156,767382753090,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;1,0,0,0,883,883,0;0,-1,0,0,520,520,0;1,0,0,0,883,883,0;-1,2,-94,-102,0,-1,0,0,520,520,0;1,0,0,0,883,883,0;0,-1,0,0,520,520,0;1,0,0,0,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qantasbusinessrewards.com/login-1,2,-94,-115,0,0,0,0,0,0,0,3,0,1534765506180,-999999,16418,0,0,2736,0,0,8,0,0,9D05475E0063B87712E445EF3C6021C45C7B9BEFAA3D0000BFA97A5BA0E9DB5C~-1~a27kkC4/okmE1MLG/2dfBzNc5zGkZY3+Ii4n9hRHk7w=~-1~-1,8048,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,49555606-1,2,-94,-118,57895-1,2,-94,-121,;6;-1;0",
            "7a74G7m23Vrp0o5c9915381.28-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.75 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,377625,5568304,1920,1053,1920,1080,1920,430,1920,,cpen:0,i1:0,dm:0,cwen:1,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7566,0.340375007170,767382784151.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;1,0,0,0,883,883,0;0,-1,0,0,520,520,0;1,0,0,0,883,883,0;-1,2,-94,-102,0,-1,0,0,520,520,0;1,0,0,0,883,883,0;0,-1,0,0,520,520,0;1,0,0,0,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qantasbusinessrewards.com/login-1,2,-94,-115,0,0,0,0,0,0,0,4,0,1534765568303,-999999,16418,0,0,2736,0,0,12,0,0,4C8BCB8061941DD9322DCE39FA0DC26048F62BE86A5A0000FAA97A5BBA495E32~-1~0RV6pN4FTlimLr7Ao0RLRzHpULETrwzJ/vGuF2e4G+Q=~-1~-1,8140,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,250573637-1,2,-94,-118,58255-1,2,-94,-121,;8;-1;0",
            "7a74G7m23Vrp0o5c9915381.28-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36 OPR/46.0.2597.57,uaend,12147,20030107,en-US,Gecko,5,0,0,0,377625,5611582,1920,1053,1920,1080,1880,561,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8544,0.418851983209,767382805790.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;1,0,0,0,883,883,0;0,-1,0,0,520,520,0;1,0,0,0,883,883,0;-1,2,-94,-102,0,-1,0,0,520,520,0;1,0,0,0,883,883,0;0,-1,0,0,520,520,0;1,0,0,0,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.qantasbusinessrewards.com/login-1,2,-94,-115,0,0,0,0,0,0,0,7,0,1534765611581,-999999,16418,0,0,2736,0,0,14,0,0,E79FEC2D759D6DBB0A58AC80E4FB7BA55C7B9BEFAA3D00001EAA7A5B19648B13~-1~6WD5DdYscVN+xMVqUd14pD0fNzJ4ExESH3aOWokjiSQ=~-1~-1,8256,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,50504221-1,2,-94,-118,59389-1,2,-94,-121,;11;-1;0",
        ];

        return $sensor[array_rand($sensor)];

        /*

        $cache = \Cache::getInstance();
        $cacheKey = "sensor_data_aquire" . sha1($this->http->getDefaultHeader("User-Agent"));
        $data = $cache->get($cacheKey);
        if (!empty($data))
            return $data;

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            if (preg_match('#Chrome|Safari|WebKit#ims', $this->http->getDefaultHeader("User-Agent"))) {
                $selenium->useChromium();
            }
            $selenium->http->setDefaultHeader("User-Agent", $this->http->getDefaultHeader("User-Agent"));
            $selenium->http->userAgent = $this->http->getDefaultHeader("User-Agent");
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.qantasbusinessrewards.com/login");
//            $login = $selenium->waitForElement(WebDriverBy::id('email'), 5);
//            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 0);
//            $login = $selenium->waitForElement(WebDriverBy::id('loginbtn'), 0);

//            if ($login && $pass) {
                $this->logger->info("login form loaded");
                $selenium->driver->executeScript("(function(send) {
                    XMLHttpRequest.prototype.send = function(data) {
                      console.log('ajax');
                      console.log(data);
                      localStorage.setItem('sensor_data', data);
                    };
                })(XMLHttpRequest.prototype.send);");
//                $login->click();
                sleep(1);
                $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");
                $this->logger->info("got sensor data: " . $sensor_data);
                if (!empty($sensor_data)) {
                    $data = @json_decode($sensor_data, true);
                    if (is_array($data) && isset($data["sensor_data"])) {
                        $cache->set($cacheKey, $data["sensor_data"], 1000);
                        return $data["sensor_data"];
                    }
                }
//            }
        }
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
        */
    }

    private function sendSensorData($sensor_data)
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
        $this->http->PostURL('https://www.qantasbusinessrewards.com/_bm/_data', json_encode($data), $headers);
        $this->http->JsonLog();

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[@id = 'logoutButton']")) {
            return true;
        }

        return false;
    }
}
