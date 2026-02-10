<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerDisneyvacation extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected $loginResp = [];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    /*public static function GetAccountChecker($accountInfo)
    {
        if (in_array($accountInfo['Login'], ['gulfire@gmail.com'])) {
            require_once __DIR__ . '/TAccountCheckerDisneyvacationSelenium.php';

            return new TAccountCheckerDisneyvacationSelenium();
        }

        return new static();
    }*/

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        //$this->http->SetProxy($this->proxyDOP());
        $this->setProxyGoProxies();
        $this->http->setRandomUserAgent();
        $this->http->setHttp2(true);
        $this->http->disableOriginHeader();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://disneyvacationclub.disney.go.com/', [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(text(),'Welcome, ')]")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->http->currentUrl() != 'https://disneyvacationclub.disney.go.com/') {
            $this->http->GetURL('https://disneyvacationclub.disney.go.com/');
        }

        if (
            $this->http->Response['code'] != 200
        ) {
            // provider problems
            if ($this->http->Response['code'] == 500 && empty($this->http->Response['body'])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        return $this->seleniumAuth();

        $this->http->GetURL('https://disneyvacationclub.disney.go.com/sign-in/?appRedirect=http%3A%2F%2Fdisneyvacationclub.disney.go.com%2F');
        $pageurl = $this->http->currentUrl();
        $this->http->GetURL('https://cdn.registerdisney.go.com/v2/TPR-DVC.WEB-PROD/en-US?include=config&config=PROD&countryCode=US');
        $this->http->JsonLog(null, 3, false, "reCaptchaSiteKey");
        $captcha = $this->parseReCaptcha($pageurl);

        if ($captcha === false) {
            return false;
        }

        $this->http->PostURL('https://registerdisney.go.com/jgc/v5/client/TPR-DVC.WEB-PROD/api-key?langPref=en-US', []);
        $apiHeader = ArrayVal($this->http->Response['headers'], 'api-key');

        if (!$apiHeader) {
            $this->logger->error('api header not found');
        }
        $this->http->setDefaultHeader('authorization', 'APIKEY ' . $apiHeader);

        $params = [
            'loginValue' => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
        ];
        $headers = [
            'Accept'            => '*/*',
            'Content-Type'      => 'application/json',
            'g-recaptcha-token' => $captcha,
            'Referer'           => $pageurl,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://registerdisney.go.com/jgc/v8/client/TPR-DVC.WEB-PROD/guest/login?langPref=en-US&feature=no-password-reuse', json_encode($params), $headers);
        $this->http->RetryCount = 2;

        $this->loginResp = $this->http->JsonLog(null, 5, true);
        $form = $this->jsonToForm($this->loginResp);

        if (empty($form)) {
            return false;
        }

        $this->http->FormURL = 'https://disneyvacationclub.disney.go.com/disid-sign-in/';
        $this->http->Form = $form;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($message = $this->http->FindPreg("/(In order to serve you better\, The Disney Vacation Club Members site is undergoing maintenance\.\s*Please check back later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'Site Down for Maintenance')]
                | //strong[contains(text(), 'Scheduled Website Maintenance on')]
                | //div[contains(text(), 'All password-protected areas of this website are temporarily unavailable.')]
                | //div[contains(text(), 'In order to accommodate updates, Member sign-in will be temporarily unavailable')]
                | //strong[contains(text(), 'Scheduled Website Maintenance beginning ')]
                | //p[contains(text(), 'All password protected areas of this website are temporarily unavailable.')]
        ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($this->http->currentUrl() == 'https://dvc.disney.go.com/members/down-for-maintenance') {
            throw new CheckException("We're sorry. Our systems are unavailable at this time as we are performing maintenance. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // System error
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'Sorry, we are not able to save your registration information at this time')
                or contains(text(), 'All password-protected areas of this website are temporarily unavailable.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The page you are trying to access is currently not available
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The page you are trying to access is currently not available.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're unable to put this page together right now, so please try again later.
        if ($message = $this->http->FindPreg("/We're unable to put this page together right now, so please try again later\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Rest assured, we'll be back soon, so please try again later.
        if ($message = $this->http->FindPreg("/Rest assured, we\'ll be back soon, so please try again later\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // We're sorry. Our systems are unavailable at this time as we are performing maintenance.
        if ($message = $this->http->FindPreg("/\{\"error\":true,\"isDfm\":true\}/ims")) {
            throw new CheckException("We're sorry. Our systems are unavailable at this time as we are performing maintenance. ", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->State['2faData']["passcode"] = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/TPR-DVC.WEB-PROD/otp/redeem?langPref=en-US", json_encode($this->State['2faData']), $this->State['2faHeaders']);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $code = $response->error->errors[0]->code ?? null;

        // The code you entered is incorrect or expired.
        if ($code === 'DEVICE_LINK_INVALID_OTP_CODE') {
            $this->AskQuestion($this->Question, "The code you entered is incorrect or expired.", "Question");

            return false;
        }

        if (!empty($response->data->access_token) /*&& $this->http->Response['code'] == 200*/) {
            $this->captchaReporting($this->recognizer);
            $headers = [
                "Authorization" => $response->data->access_token,
                //                "ClientId"      => "TPR-DVC.WEB",
                //                "swid"          => $response->data->accountRecoveryProfiles[0]->swid,
            ];

            if (!$this->loginSuccessful($headers)) {
                return false;
            }
            $this->State['Authorization'] = $response->data->access_token;
            $this->State['SWID'] = $response->data->accountRecoveryProfiles[0]->swid;

            return true;
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm(['Accept-Encoding' => 'gzip, deflate, br']) && $this->http->Response['code'] != 500)
            return $this->checkErrors();
        */
        // part of login process
        if ($this->http->FindPreg('/(?:"success":true|profile":\{"swid":")/ims')) {
            $this->http->RetryCount = 0;
            $this->http->GetURL('https://disneyvacationclub.disney.go.com/');
            $this->http->RetryCount = 2;
        }

        //# Invalid login or password
        /*
        if ($message = $this->http->FindPreg("/Attempt/ims")) {
            $this->captchaReporting();
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }
        */
        if ($message = $this->http->FindPreg("/Your input contains invalid characters/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Your username or password contains invalid characters", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/This Disney Vacation Club Membership has already been linked with your Disney account\. Please sign in using your Disney account username and password\./ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * This Disney Vacation Club Membership has already been linked with your Disney account
         * or is not currently associated with a Disney Vacation Club Membership.
         */
        if ($message = $this->http->FindPreg("/This Disney Vacation Club Membership has already been linked with your Disney account or is not currently associated with a Disney Vacation Club Membership\./ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/This Disney Vacation Club Membership has already been linked with your Disney account or is not currently associated with a Disney Vacation Club Membership\./ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // not a member
        if ($message = $this->http->FindPreg("/registration\?type=linkaccountauth/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Activate Your Member Account
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Activate Your Member Account')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Disney Vacation Club website is asking you to activate your Member Account, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our systems are unavailable at this time as we are performing maintenance')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Access is allowed
        if (
            $this->http->FindNodes("//a[contains(@href, 'sign-out')]/@href")
            || ($this->AccountFields['Login'] == 'fischerwdf' && $this->http->getCookieByName("SESSIONTOKEN"))
            || ($this->loginResp['data']['profile']['swid'] ?? false)
        ) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = Html::cleanXMLValue(sprintf('%s %s %s ',
            $this->loginResp['data']['profile']['firstName'] ?? null,
            $this->loginResp['data']['profile']['middleName'] ?? null,
            $this->loginResp['data']['profile']['lastName'] ?? null
        ));

        if (empty($name)) {
            $name = $this->http->FindSingleNode("(//a[contains(text(),'Welcome, ')])[1]", null, false, '/Welcome,\s+(.+)/');
        }

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }

        // refs#19615
        // {"P":"Purchaser","R":"Co-Purchaser","O":"Officer","A":"Associate","C":"Corporate"}
        $token = $this->loginResp['data']['token']['access_token'] ?? $this->State['Authorization'];
        $this->http->setDefaultHeader("Authorization", "Bearer {$token}");
        $swid = $this->loginResp['data']['profile']['swid'] ?? $this->State['SWID'];
        $this->http->setDefaultHeader("swid", $swid);
        $this->http->GetURL("https://dvc-lightcycle-api.wdprapps.disney.com/api/v1/profile/{$swid}");
        $response = $this->http->JsonLog();

        if (isset($response->isDvcMember) && $response->isDvcMember === false) {
            throw new CheckException("Disney Vacation Club website is asking you to verify your Membership, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            isset($response->error->type)
            && $response->error->type === 'ERR_AUTHZ_SWID'
            && $this->seleniumAuth()
            && $this->Login()
        ) {
            $token = $this->loginResp['data']['token']['access_token'] ?? $this->State['Authorization'];
            $this->http->setDefaultHeader("Authorization", "Bearer {$token}");
            $swid = $this->loginResp['data']['profile']['swid'] ?? $this->State['SWID'];
            $this->http->setDefaultHeader("swid", $swid);
            $this->http->GetURL("https://dvc-lightcycle-api.wdprapps.disney.com/api/v1/profile/{$swid}");
            $response = $this->http->JsonLog();
        }

        foreach ($response->memberData as $memberData) {
            $clubIds[] = $memberData->clubId;
        }

        $noPurchasers = $coPurchasers = $purchasers = [];
        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        foreach ($clubIds as $clubId) {
            $data = [
                'clubId' => $clubId,
            ];
            $this->http->PostURL('https://dvc-lightcycle-api.wdprapps.disney.com/api/v1/profile/' . $swid . '/membership-info', $data, $headers);
            $response = $this->http->JsonLog();

            foreach ($response->profiles[0]->memberships as $membership) {
                $this->http->GetURL("https://dvc-lightcycle-api.wdprapps.disney.com/api/v1/memberships/details/{$membership->membershipId}/{$clubId}");
                $details = $this->http->JsonLog();
                $isOneSubAccounts = count($response->profiles[0]->memberships) == 1;

                if ($isOneSubAccounts) {
                    // Vacation Points Currently Available
                    $this->SetBalance($details->vacationPointsAvailableToBook);
                } else {
                    $this->SetBalanceNA();
                }
                $this->ParseSubAccounts($details);
            }
        }

        $this->SetBalance($balance ?? null);
    }

    public function ParseSubAccounts($response, $parseSubAccounts = false)
    {
        foreach ($response->pointsInContractsPerUseDate as $item) {
            $year = date('Y', strtotime($item->useDate));
            $displayName = date('F Y', strtotime($item->useDate)) . " (Membership # {$response->membershipId})";
            $balance = $totalAnnualAllotment = 0;

            foreach ($item->contracts as $contract) {
                $this->logger->debug("Balance: + {$contract->pointsBalance}");
                $balance += $contract->pointsBalance;
                $this->logger->debug("TotalAnnualAllotment: + {$contract->annualPointsAllotment}");
                $totalAnnualAllotment += $contract->annualPointsAllotment;
            }

//            if ($parseSubAccounts === true) {
            $this->AddSubAccount([
                'Code' => sprintf('disneyvacation%s', str_replace(' ', '', $displayName)),
                // like as 'October 2017'
                'DisplayName' => $displayName,
                'MemberID'    => $response->membershipId,
                // Points Remaining
                'Balance' => $balance,
                // Total Annual Allotment of Vacation Points
                'TotalAnnualAllotment' => $totalAnnualAllotment,
            ]);
            /*} elseif (strstr($displayName, $year)) {
                // Points Remaining
                $this->SetBalance($balance);
                // Total Annual Allotment of Vacation Points
                $this->SetProperty("TotalAnnualAllotment", $totalAnnualAllotment);
                // MemberID
                $this->SetProperty("MemberID", $response->membershipId);
                $this->sendNotification('No sub accounts // MI');
            }*/
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['PreloadAsImages'] = true;
        $arg['CookieURL'] = 'https://dvcmember.disney.go.com/';
        $arg['SuccessURL'] = 'https://disneyvacationclub.disney.go.com/home/points/';

        return $arg;
    }

    public function gen_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function jsonToForm($json)
    {
        $this->logger->notice(__METHOD__);
        $data = ArrayVal($json, 'data');

        if (!$data) {
            $error = ArrayVal($json, 'error');

            if (isset($error['errors'][0]['code'])) {
                switch ($error['errors'][0]['code']) {
                    case 'AUTHORIZATION_CREDENTIALS':
                        // wrong captcha answer
                        if ($this->http->FindPreg("/\"data\":\{\"type\":\"GenericErrorData\",\"reasonCode\":\"PALOMINO_CHECK_FAILED\"/")) {
                            $this->logger->error("wrong captcha answer");
                            $this->captchaReporting($this->recognizer, false);

                            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
                        }

                        $this->captchaReporting($this->recognizer);

                        // refs#24283
                        // When 2fa fails this error will not be true
                        if ($error['errors'][0]['data'] === null) {
                            throw new CheckException("The credentials you entered are incorrect. Reminder: passwords are case sensitive.", ACCOUNT_INVALID_PASSWORD);
                        }

                        break;

                    case 'AUTHORIZATION_ACCOUNT_LOCKED_OUT':
                        $this->captchaReporting($this->recognizer);

                        throw new CheckException("You have entered the wrong password too many times. For security reasons, we limit the number of sign in attempts. Please try again in a few minutes, or reset your password.", ACCOUNT_LOCKOUT);

                    case 'AUTHORIZATION_ACCOUNT_SECURITY_LOCKED_OUT':
                        $this->captchaReporting($this->recognizer);

                        throw new CheckException("We've detected a problem with your account, and need you to reset your password to continue. Please check your email for instructions.", ACCOUNT_LOCKOUT);

                        break;

                    case 'PROFILE_DISABLED':
                        $this->captchaReporting($this->recognizer);

                        throw new CheckException("Account Deactivated", ACCOUNT_INVALID_PASSWORD);

                        break;

                    case 'CLIENT_CONFIG_ERROR':
                        $this->captchaReporting($this->recognizer);

                       $this->throwProfileUpdateMessageException();

                        break;
                }
            }// switch ($error['errors'][0]['code'])

            return null;
        }

        $formBasic = $this->jsonToFormRecur($data);
        $formGuest = [];

        foreach ($formBasic as $key => $value) {
            $formGuest[sprintf('guestProfile%s', $key)] = $value;
        }

        return $formGuest;
    }

    protected function parseReCaptcha($currentURL = null)
    {
        $this->logger->notice(__METHOD__);
        // $key = $this->http->FindPreg("/recaptchaV3Enabled\":true,\"recaptchaV3SiteKey\":\"([^\"]+)/");
        $key = $this->http->FindPreg("/recaptchaV4Config.+?,\"reCaptchaSiteKey\":\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $currentURL ?? $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "homepage",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $currentURL ?? $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "enterprise",
            "invisible" => 1,
            "action"    => "homepage",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function jsonToFormRecur($json)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        foreach ($json as $key1 => $value1) {
            if (!is_array($value1)) {
                $result[sprintf('[%s]', $key1)] = (string) $value1;
            } else {
                $subResult = $this->jsonToFormRecur($value1);

                foreach ($subResult as $key2 => $value2) {
                    $result[sprintf('[%s]%s', $key1, $key2)] = $value2;
                }
            }
        }

        return $result;
    }

    private function loginSuccessful($headers)
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 3, true);
        // Name
        $profile = $response['data']['profile'] ?? $response['data']['accountRecoveryProfiles'][0];
        $this->SetProperty("Name", beautifulName(ArrayVal($profile, 'firstName') . " " . ArrayVal($profile, 'lastName')));

        if (!isset($this->Properties['Name']) || trim($this->Properties['Name']) == '') {
            $this->logger->error("Name not found");

            return false;
        }

        /*
        $needToUpdateAccount = false;
        // Please Update Your Account
        if ($this->http->FindPreg('/\{\"code\":\"PPU_LEGAL\",\"category\":\"PPU_ACTIONABLE_INPUT\"/'))
            $needToUpdateAccount = true;
        */

        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.disneymovieinsiders.com/user?t=" . date("UB"), $headers);
        $response = $this->http->JsonLog();
        $email = $response->email_address ?? null;
        $username = $response->username ?? null;
        $firstName = $response->first_name ?? null;
        $lastName = $response->last_name ?? null;

        if (
            !in_array(strtolower($this->AccountFields['Login']), [$email, $username])
            && trim($firstName . " " . $lastName) != trim($this->Properties['Name'])
            && trim($firstName) != trim($this->Properties['Name'])
            && !in_array(strtolower($this->AccountFields['Login']), ['icksoo@gmail.com'])
        ) {
            $this->logger->error("may be wrong data");
            $this->logger->error("[login]: {$this->AccountFields['Login']}");
            $this->logger->error("[email]: {$email}");
            $this->logger->error("[username]: {$username}");
            $this->logger->error("[first_name]: {$firstName}");
            $this->logger->error("[last_name]: {$lastName}");

            /*
            if (isset($response->message) && $response->message == 'User does not exist.') {
                $this->http->GetURL("https://api.disneymovieinsiders.com/user/vppa-status?t=" . date("UB"), $headers);
                $response = $this->http->JsonLog();

                if (isset($response->vppa_accepted) && $response->vppa_accepted === false) {
                    $this->throwAcceptTermsMessageException();
                }
            }
            * /

            return false;
        }
        */

        foreach ($headers as $name => $value) {
            $this->http->setDefaultHeader($name, $value);
        }

        return true;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://disneyvacationclub.disney.go.com/sign-in/?appRedirect=http%3A%2F%2Fdisneyvacationclub.disney.go.com%2F");
            $iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'disneyid-iframe' or @id = 'oneid-iframe']"), 30);
            $this->savePageToLogs($selenium);

            if (!$iframe) {
                $this->logger->error('no iframe');

                return $this->checkErrors();
            }

            $selenium->driver->switchTo()->frame($iframe);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Email"]'), 7);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "BtnSubmit"]'), 0);

            if (!$loginInput || !$button) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $button->click();

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Password"]'), 8);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "BtnSubmit"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$button) {
                if ($message = $this->http->FindSingleNode('//*[@id = "InputIdentityFlowValue-error"]')) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        $message == 'Please enter a valid email address.'
                        || strstr($message, 'This email isn\'t properly formatted.')
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                if ($this->http->FindSingleNode('//h1[span[contains(text(), "Create Your Account")]]')) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return $this->checkErrors();
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/"access_token"|"errors":\[\{"code":/g.exec( this.responseText )) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                    
                    if (/"sessionId":/g.exec( this.responseText )) {
                        localStorage.setItem("responseDataQuestion", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            ');
            $button->click();

            $apiKey = '';
            /*try {
                $executor = $selenium->getPuppeteerExecutor();
                $json = $executor->execute(
                    __DIR__ . '/puppeteer.js'
                );
            } catch (Exception $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $retry = true;

                return false;
            }
//            $this->logger->debug(var_export($json, true), ['pre' => true]);
//            $this->logger->info(json_encode($json));
            $apiKey = ArrayVal($json['headers'], 'api-key');*/
            $this->logger->debug("api-key: {$apiKey}");

            sleep(4);
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $responseDataQuestion = $selenium->driver->executeScript("return localStorage.getItem('responseDataQuestion');");
            $this->logger->info("[Form responseDataQuestion]: " . $responseDataQuestion);

            /*
            $selenium->driver->switchTo()->defaultContent();
            $this->savePageToLogs($selenium);
            */
            $selenium->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'Welcome Back Home,')]
                | //p[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')]
                | //h2/span[contains(text(), 'Please Update Your Account')]
                | //h2[contains(text(), 'Please Update Your Account')]
                | //p[contains(text(), 'To continue, we need the following information:')]
                | //p[contains(text(), 't find an account matching that information.')]
                | //h2[contains(text(), 'Please verify your Membership')]
                | //h1[contains(text(), 'Please Verify Your Membership')]
                | //span[contains(text(), 'Email me at')]
                | //span[contains(text(), 'Text a code to my phone')]
                | //p[contains(text(), 'We sent a code to')]
                | //p[contains(@class, 'login-credentials-error')]
            "), 15);
            $this->savePageToLogs($selenium);

            // invalid credentials false/posirive issue
            if ($sendCode = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Email me at')] | //span[contains(text(), 'Text a code to my phone')]"), 0)) {
                $selenium->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener("load", function() {
                            if (/"access_token"|"errors":\[\{"code":/g.exec( this.responseText )) {
                                localStorage.setItem("responseData", this.responseText);
                            }
                            
                            if (/"sessionId":/g.exec( this.responseText )) {
                                localStorage.setItem("responseDataQuestion", this.responseText);
                            }
                        });
                        return oldXHROpen.apply(this, arguments);
                    };
                ');

                $sendCode->click();

                $selenium->waitForElement(WebDriverBy::xpath("
                    //p[contains(text(), 'Welcome Back Home,')]
                    | //p[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')]
                    | //h2/span[contains(text(), 'Please Update Your Account')]
                    | //h2[contains(text(), 'Please Update Your Account')]
                    | //p[contains(text(), 'To continue, we need the following information:')]
                    | //p[contains(text(), 't find an account matching that information.')]
                    | //h2[contains(text(), 'Please verify your Membership')]
                    | //h1[contains(text(), 'Please Verify Your Membership')]
                    | //p[contains(@class, 'login-credentials-error')]
                "), 15);
                $this->savePageToLogs($selenium);

                sleep(4);
                $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $this->logger->info("[Form responseData]: " . $responseData);

                $responseDataQuestion = $selenium->driver->executeScript("return localStorage.getItem('responseDataQuestion');");
                $this->logger->info("[Form responseDataQuestion]: " . $responseDataQuestion);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == "TPR-DVC.WEB-PROD.token") {
                    $value = $this->http->FindPreg("/5=([^\;]+)/", false, $cookie['value']);
                    $this->logger->debug($value);
                    $value = $this->http->JsonLog(base64_decode($value));

                    if (!empty($value)) {
                        $this->State['Authorization'] = $value->access_token;
                        $token = $this->State['Authorization'];
                        $this->http->setDefaultHeader("Authorization", "Bearer {$token}");
                        $this->State['SWID'] = $value->swid;
                        $swid = $this->State['SWID'];
                        $this->http->setDefaultHeader("swid", $swid);
                    }
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (
                !empty($responseData)
                && (isset($responseData->data->token->access_token) || !empty($responseDataQuestion))
            ) {
                if ($questionObject = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We sent a code to')]"), 0)) {
                    $question = $questionObject->getText();
                    $error = ArrayVal($this->http->JsonLog($responseData, 5, true), 'error');
                    $assessmentId = $error['errors'][0]['data']['assessmentId'] ?? null;
                    $responseDataQuestion = $this->http->JsonLog($responseDataQuestion);
                    $sessionId = $responseDataQuestion->data->sessionId ?? null;

                    if (!$sessionId) {
                        $this->logger->error("something went wrong, sessionId not found");

                        return false;
                    }

                    $headers = [
                        "Accept"          => "*/*",
                        "Content-Type"    => "application/json",
                        "correlation-id"  => $correlationId ?? $this->gen_uuid(),
                        "conversation-id" => $conversationId ?? $this->gen_uuid(),
                        "oneid-reporting" => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=', //todo: fake
                        "device-id"       => 'null',
                        "expires"         => -1,
                        "Origin"          => "https://cdn.registerdisney.go.com",
                        "Referer"         => 'https://cdn.registerdisney.go.com/',
                    ];

                    $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key', $apiKey));
                    $headers["correlation-id"] = ArrayVal($this->http->Response['headers'], 'correlation-id', null) ?? $this->gen_uuid();
                    $headers["conversation-id"] = ArrayVal($this->http->Response['headers'], 'conversation-id', null) ?? $this->gen_uuid();

                    $this->State['2faHeaders'] = $headers;
                    $this->State['2faData'] = [
                        "passcode"     => "",
                        "sessionIds"   => [
                            $sessionId,
                        ],
                        "assessmentId" => $assessmentId,
                    ];

                    $this->Question = $question;
                    $this->ErrorCode = ACCOUNT_QUESTION;
                    $this->Step = "Question";

                    return false;
                }

                if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the account system is having a problem.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindPreg('/role="alert">We didn\'t find an account matching that information. Make sure you entered it correctly or/')) {
                    throw new CheckException("We didn't find an account matching that information. Make sure you entered it correctly or create an account.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->http->FindSingleNode("//h2[
                        contains(text(), 'Please Update Your Account')
                        or contains(text(), 'Stay in touch!')
                    ]
                        | //h2/span[contains(text(), 'Please Update Your Account')]
                    ")
                ) {
                    $this->throwAcceptTermsMessageException();
                }

                $this->loginResp = $this->http->JsonLog($responseData, 5, true);
                $this->jsonToForm($this->loginResp);
                $this->http->SetBody($responseData);

                return true;
            } elseif ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the account system is having a problem.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } elseif ($this->http->FindSingleNode("//h2[contains(text(), 'Please verify your Membership')] | //h1[contains(text(), 'Please Verify Your Membership')]")) {
                throw new CheckException("Disney Vacation Club website is asking you to verify your Membership, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            } elseif ($selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')] | //p[contains(text(), 'To continue, we need the following information:')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            } elseif ($this->http->FindSingleNode("//h2[
                    contains(text(), 'Please Update Your Account')
                    or contains(text(), 'Stay in touch!')
                ]
                    | //h2/span[contains(text(), 'Please Update Your Account')]
                ")
            ) {
                $this->throwAcceptTermsMessageException();
            } elseif (!empty($token) && !empty($swid)) {
//                $this->http->GetURL("https://dvc-lightcycle-api.wdprapps.disney.com/api/v1/profile/{$swid}");
                $this->http->GetURL("https://registerdisney.go.com/jgc/v8/client/TPR-DVC.WEB-PROD/guest/{$swid}?feature=no-password-reuse&expand=profile&expand=displayname&expand=linkedaccounts&expand=marketing&expand=entitlements&expand=s2&langPref=en-US");
                $this->loginResp = $this->http->JsonLog(null, 5, true);
                $this->jsonToForm($this->loginResp);
                $this->http->SetBody($responseData);

                return true;
            } elseif (!empty($responseData)) {
                $this->loginResp = $this->http->JsonLog($responseData, 5, true);
                $this->jsonToForm($this->loginResp);
                $this->http->SetBody($responseData);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return false;
    }
}
