<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerDisneycruise extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->GetURL("https://disneycruise.disney.go.com/login/");
//        $this->http->GetURL("https://disneycruise.disney.go.com/en/login/?appRedirect=%2Fwhy-cruise-disney%2Fcastaway-club%2F");

        $clientId = $this->http->FindPreg("/clientId\":\"([^\"]+)/");

        if (!$clientId) {
            return $this->checkErrors();
        }

        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/{$clientId}-PROD/api-key?langPref=en-US", []);

        return $this->seleniumAuth();

//        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/TPR-DCL-LBJS.WEB-PROD/api-key?langPref=en-US", []);

        $apiKey = ArrayVal($this->http->Response['headers'], 'api-key');
        $correlationId = ArrayVal($this->http->Response['headers'], 'correlation-id', null);
        $conversationId = ArrayVal($this->http->Response['headers'], 'conversation-id', null);

        $this->http->GetURL("https://cdn.registerdisney.go.com/v2/{$clientId}-PROD/en-US?include=config,l10n,js,html&?clientID=TPR-DCL-LBJS.WEBscheme=https&postMessageOrigin=https%3A%2F%2Fdisneycruise.disney.go.com%2Fen%2Flogin&cookieDomain=disneycruise.disney.go.com&config=PROD&logLevel=INFO&topHost=disneycruise.disney.go.com&cssOverride=https%3A%2F%2Fcdn1.parksmedia.wdprapps.disney.com%2Fmedia%2Flightbox%2Fdcl%2Fstyles%2Fbranded-web.css&responderPage=%2Fauthentication%2Fresponder.html&buildId=17c0e49b0a9");
//        if (!$this->http->ParseForm(null, '//section[contains(@class, "workflow-login")]//form')) {
//            return $this->checkErrors();
//        }

        // enterprise ...
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "loginValue" => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Content-Type"      => "application/json",
            "Accept"            => "*/*",
            'authorization'     => sprintf('APIKEY %s', $apiKey),
            'g-recaptcha-token' => $captcha,
            "correlation-id"    => $correlationId ?? $this->gen_uuid(),
            "conversation-id"   => $conversationId ?? $this->gen_uuid(),
            "Origin"            => "https://cdn.registerdisney.go.com",
            "Referer"           => '',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/{$clientId}-PROD/guest/login?langPref=en-US", json_encode($data), $headers);
        $this->http->RetryCount = 2;
//        */

//        if (!$this->http->ParseForm("signInForm")) {
//            return $this->checkErrors();
//        }
//        $this->http->SetInputValue('username', $this->AccountFields['Login']);
//        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('submit', "");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://disneycruise.disney.go.com/login/";
        $arg["SuccessURL"] = "https://disneycruise.disney.go.com/castaway-club/";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // DisneyCruiseLine.com is currently undergoing maintenance.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'DisneyCruiseLine.com is currently undergoing maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our system is temporarily unavailable as we perform routine maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Some of our digital experiences may be unavailable at this time.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Some of our digital experiences may be unavailable at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // provider problems
        if ($this->http->Response['code'] == 503 && $this->http->FindPreg("/An error occurred while processing your request\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 500 && $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->FindSingleNode("(//a[contains(text(),'Welcome, ')])[1]", null, false, '/Welcome,\s+([^!]+)/')) {
            return true;
        }

        $response = $this->http->JsonLog(null, 5);
        // Access is allowed
        if (!empty($response->data->token->access_token) /*&& $this->http->Response['code'] == 200*/) {
            $this->captchaReporting($this->recognizer);
            $this->State['Authorization'] = $response->data->token->access_token;
            $this->State['SWID'] = $response->data->token->swid;

            return true;
        }
        // AccountID: 4812976
        if (
            isset($response->error->keyCategory)
            && !empty($response->data->token->access_token)
            /*&& $this->http->Response['code'] == 400*/
            && $response->error->keyCategory == 'PPU_ACTIONABLE_INPUT'
            && $this->http->FindPreg('/\{"code":"(?:MISSING_VALUE|PPU_LEGAL)","category":"PPU_ACTIONABLE_INPUT","inputName":"(?:profile.addresses|GTOU)/')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }
        // 500 site error
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"SYSTEM_UNAVAILABLE\",\"category\":\"SYSTEM_UNAVAILABLE\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The credentials you entered are incorrect. Reminder: passwords are case sensitive.
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"AUTHORIZATION_CREDENTIALS\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            // wrong captcha answer
            if ($this->http->FindPreg("/\"data\":\{\"type\":\"GenericReasonCodeErrorData\",\"reasonCode\":\"PALOMINO_CHECK_FAILED\"\}/")) {
                $this->logger->error("wrong captcha answer");
                $this->captchaReporting($this->recognizer, false);

                return false;
            }

            // We sent a code to ... . Enter the code here to continue.
            $assessmentId = $response->error->errors[0]->data->assessmentId ?? null;

            if (
                $this->http->FindPreg("/\"data\":\{\"type\":\"GenericErrorData\",\"reasonCode\":\"PALOMINO_CHECK_FAILED\",\"assessmentId/")
                && $assessmentId
            ) {
                $this->logger->notice("2fa");
                $this->captchaReporting($this->recognizer);

                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $this->parseQuestion($assessmentId);

                return false;
            }

            $this->captchaReporting($this->recognizer);

            throw new CheckException("The credentials you entered are incorrect. Reminder: passwords are case sensitive.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/"errors":\[\{"code":"AUTHORIZATION_ACCOUNT_LOCKED_OUT","category":"FAILURE_CONTACT_CSR"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("You've tried too many passwords. Try again later, or get a temporary password.", ACCOUNT_LOCKOUT);
        }

        if (
            $this->http->FindPreg('/Could not authorize the given credentials/')
            || $this->http->FindPreg('/"errors\":\[\{\"code\":\"INVALID_VALUE\",\"category\":\"ACTIONABLE_INPUT\"/')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("The credentials you entered are incorrect. Reminder: passwords are case sensitive.", ACCOUNT_INVALID_PASSWORD);
        }
        // It looks like multiple accounts are associated with that email address.
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"AUTHORIZATION_MASE_ERROR\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("It looks like multiple accounts are associated with that email address. We'd like to send you a message with instructions to help you choose your primary Disney account.", ACCOUNT_INVALID_PASSWORD);
        }
        // You are not eligible to register on this site. Your information has not been collected.
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"GUEST_GATED_AGEBAND\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("You are not eligible to register on this site. Your information has not been collected.", ACCOUNT_INVALID_PASSWORD);
        }
        // Account Deactivated
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"PROFILE_DISABLED\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Account Deactivated", ACCOUNT_PROVIDER_ERROR);
        }
        // We've detected a problem with your account, and need you to reset your password to continue. Please check your email for instructions.
        if ($this->http->FindPreg('/"errors":\[\{"code":"AUTHORIZATION_ACCOUNT_SECURITY_LOCKED_OUT","category":"FAILURE_BY_DESIGN"/')
            || $this->http->FindPreg('/"errors":\[\{\"code\":\"AUTHORIZATION_INVALID_OR_EXPIRED_TOKEN\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("We've detected a problem with your account, and need you to reset your password to continue. Please check your email for instructions.", ACCOUNT_LOCKOUT);
        }

        /*
        if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [500, 400])) {
            return $this->checkErrors();
        }
        * /
        // part of login process
        if ($this->http->FindPreg('/(?:"success":true|profile":\{"swid":")/ims')) {
            return true;
        }

        $this->http->JsonLog();

        // The sign-in process for Disney accounts has changed. To access your account, please provide the requested information.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The sign-in process for Disney accounts has changed. To access your account, please provide the requested information.')]")) {
            $this->logger->debug("Skip update profile");

            return true;
        }

        if (
            $this->http->currentUrl() == 'https://disneycruise.disney.go.com/'
            || $this->http->Response['code'] == 500
            || $this->http->FindPreg('/profile":\{"swid":"/ims')
        ) {
            $this->logger->debug("Force redirect");
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://disneycruise.disney.go.com/castaway-club/");

            if ($this->http->Response['code'] == 404) {
                throw new CheckRetryNeededException();
            }

            if (
                $this->http->Response['code'] == 500
                && in_array($this->AccountFields['Login'], [
                    'rayarashi',
                ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // Retry
            if (
                $this->http->FindSingleNode("//pre/text()[contains(.,'Handler processing failed; nested exception is java.lang.NoClassDefFoundError')]")
            ) {
                sleep(5);
                $this->http->GetURL("https://disneycruise.disney.go.com/castaway-club/");
            }
            $this->http->RetryCount = 2;
        }

        $this->checkProviderErrors();

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")
            || $this->http->FindSingleNode("//div[contains(text(), 'Club ID')]/following-sibling::div[1]")
            || $this->http->FindSingleNode("//div[contains(text(), 'Club ID')]/span")
            || $this->http->FindSingleNode("//a[contains(text(), 'Welcome,')]")
            || $this->http->getCookieByName("WOMID")
        ) {
            return true;
        }
        // Invalid login or password
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'but the email or password you entered does not match our records')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, the Email or Username and/or Password do not match our records, so please try again.
        if ($message = $this->http->FindPreg("/(Sorry, the Email or Username and\/or Password do not match our records\, so please try again\.)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The email or username and/or password do not match our records. Please try again.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "The email or username and/or password do not match our records. Please try again.")]')) {
            throw new CheckException("The email or username and/or password do not match our records. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // Error:You have exceeded the limit of sign-in attempts. Please wait and try again or reset your password.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "You have exceeded the limit of sign-in attempts")]')) {
            throw new CheckException("Error:You have exceeded the limit of sign-in attempts. Please wait and try again or reset your password.
", ACCOUNT_INVALID_PASSWORD);
        }
        */

        return $this->checkErrors();
    }

    public function checkProviderErrors()
    {
        $this->logger->debug(__METHOD__);
        // Sorry, but we're having a problem retrieving your Castaway Club ID. Please call 1-800-449-3380 for assistance.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, but we\'re having a problem retrieving your Castaway Club ID. Please call 1-800-449-3380 for assistance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // setup Security Questions
        if (($this->http->FindSingleNode('//p[contains(text(), "We need some help to ensure your account information is complete.")]') && $this->http->FindNodes("//h3[contains(text(), 'Security Questions') or contains(text(), 'Terms of Use') or contains(text(), 'Complete Your Registration')]"))
            || $this->http->FindSingleNode('//p[contains(text(), "We need you to update your password. We\'ve emailed ")]')) {
            $this->throwProfileUpdateMessageException();
        }
    }

    public function Parse()
    {
        $loginResp = $this->http->JsonLog(null, 0, true);
        // Name
        $name = Html::cleanXMLValue(sprintf('%s %s %s ',
            $loginResp['data']['profile']['firstName'] ?? $loginResp['data']['accountRecoveryProfiles'][0]['firstName'] ?? null,
            $loginResp['data']['profile']['middleName'] ?? null,
            $loginResp['data']['profile']['lastName'] ?? $loginResp['data']['accountRecoveryProfiles'][0]['lastName'] ?? null
        ));

        if (empty($name)) {
            $name = $this->http->FindSingleNode("(//a[contains(text(),'Welcome, ')])[1]", null, false, '/Welcome,\s+([^!]+)/');
        }
        $this->SetProperty("Name", beautifulName($name));

        /*
        $na = false;
        ## Full Name
        if ($this->http->currentUrl() != 'https://disneycruise.disney.go.com/profile/')
            $this->http->GetURL("https://disneycruise.disney.go.com/profile/");
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@name = 'firstName']/@value")
            .' '.$this->http->FindSingleNode("//input[@name = 'middleName']/@value")
            .' '.$this->http->FindSingleNode("//input[@name = 'lastName']/@value"));
        if (strlen($name) > 2)
            $this->SetProperty('Name', beautifulName($name));
        elseif ($this->http->FindSingleNode("//h3[contains(text(), 'Set Up Your Member Account')]")
                // provider bug workaround
                || ($this->http->Response['code'] == 500 && empty($this->http->Response['body'])))
            $na = true;
        elseif ($this->http->FindSingleNode('//div[contains(text(), "We\'ve updated our Terms of Use and you need to read and agree to the new terms to continue.")]'))
            $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//h1[contains(@class, 'uTitle')]", null, true, "/([^\,]+)/ims")));
        else {
            $this->logger->debug("Open old profile page");
            $this->http->GetURL("https://disneycruise.disney.go.com/profile/personal-information/edit/");
            $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@name = 'firstName']/@value")
                .' '.$this->http->FindSingleNode("//input[@name = 'middleInitial']/@value")
                .' '.$this->http->FindSingleNode("//input[@name = 'lastName']/@value"));
            $this->SetProperty('Name', beautifulName($name));
        }
        */
        $token = $this->loginResp['data']['token']['access_token'] ?? $this->State['Authorization'];
        $this->http->setDefaultHeader("Authorization", "BEARER {$token}");
        $swid = $this->loginResp['data']['profile']['swid'] ?? $this->State['SWID'];
        $this->http->setDefaultHeader("swid", $swid);
        $this->http->setCookie("SWID", $swid, ".disneycruise.disney.go.com", "/", null, true);
        $this->http->setCookie("SWID", $swid, ".go.com");

//        $this->http->GetURL("https://disneycruise.disney.go.com/why-cruise-disney/castaway-club/");
//        $this->http->GetURL("https://disneycruise.disney.go.com/authentication/get-client-token/");
        $this->http->GetURL("https://disneycruise.disney.go.com/profile-api/authentication/get-client-token/");
        $response = $this->http->JsonLog();

        if (!isset($response->access_token)) {
            $this->logger->error("token not found");

            return;
        }

        sleep(2);

        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            //            "Authorization" => "BEARER {$response->access_token}",
            "Authorization" => "BEARER {$token}",
            "Content-Type"  => "application/json",
            "Referer"       => "https://disneycruise.disney.go.com/why-cruise-disney/castaway-club/",
            "X-Page-Id"     => "https://disneycruise.disney.go.com/why-cruise-disney/castaway-club/",
        ];
        $this->http->GetURL("https://disneycruise.disney.go.com/dcl-cruise-101-webapi/cruise-101/castaway-club/affiliations/", $headers);
        $response = $this->http->JsonLog();
        // Status - You are a ... Castaway Club member.
        $this->SetProperty("Status", $response->clientType ?? null);
        // Castaway Club ID
        $this->SetProperty("ClubID", $response->pastPassengerId ?? null);
        // Eligible Cruises - You've sailed with us ... time(s). - FOR ELITE LEVELS TAB
        $this->SetProperty('EligibleCruises', $response->numberFullFareCruises ?? null);
        // Balance - Eligible Cruises
        $this->SetBalance($response->numberFullFareCruises ?? null);

        // TODO: not a member?
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($response->isCastawayClubMember) && $response->isCastawayClubMember === false
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }
        /*
        ## Enter your Castaway Club ID
        elseif ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Enter your Castaway Club ID')]"))
            throw new CheckException("Disney Cruise Line (Castaway Club) website is asking you to enter your Castaway Club ID, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR); /*checked* /
        else
            $this->checkProviderErrors();
        */

        $this->http->GetURL("https://api.wdprapps.disney.com/pep/profile?isUser=true&notificationsType=convert&brand=dcl&locale=en-us&userType=GUEST", $headers);
        $this->http->JsonLog();
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $swid = $this->State['SWID'];

        if (!isset($swid)) {
            $this->logger->error("swid not found");

            return [];
        }
        $this->logger->debug('SWID: ' . $swid);
        $res = $this->ParseItinerariesMyReservations($swid);

        /*
        if (empty($res)) {
            $this->http->GetURL("https://disneycruise.disney.go.com/castaway-club/my-cruises/");

            if ($this->http->FindSingleNode("//p[contains(text(), 'You currently have no upcoming cruises booked')]")) {
                return $this->noItinerariesArr();
            }
            $headers = ['X-Requested-With' => 'XMLHttpRequest'];
            $this->http->GetURL("https://disneycruise.disney.go.com/my-disney-cruise/my-reservations/", $headers);
            $data = $this->http->JsonLog();

            if (!(null !== $data && isset($data->status) && $data->status === 404 && $data->message = 'Not Found')) {
                $this->sendNotification("debug. something else// ZM");
            }
//            $this->http->GetURL("https://disneycruise.disney.go.com/my-disney-cruise/my-reservations/");
        }
        */
        // $res = $this->ParseItinerariesMyCruises();
        return $res;
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

    public function parseQuestion($assessmentId)
    {
        $apiKey = ArrayVal($this->http->Response['headers'], 'api-key');
        $correlationId = ArrayVal($this->http->Response['headers'], 'correlation-id', null);
        $conversationId = ArrayVal($this->http->Response['headers'], 'conversation-id', null);
        $this->http->RetryCount = 0;

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $data = [
            "loginValue" => $this->AccountFields['Login'],
        ];
        $headers = [
            "Accept"          => "*/*",
            "Content-Type"    => "application/json",
            'authorization'   => sprintf('APIKEY %s', $apiKey),
            "correlation-id"  => $correlationId ?? $this->gen_uuid(),
            "conversation-id" => $conversationId ?? $this->gen_uuid(),
            "oneid-reporting" => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=',
            "device-id"       => 'null',
            "expires"         => -1,
            "Origin"          => "https://cdn.registerdisney.go.com",
            "Referer"         => 'https://cdn.registerdisney.go.com/',
        ];
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/TPR-DCL-LBJS.WEB-PROD/guest/recovery-methods?langPref=en-US", json_encode($data), $headers);
        $response = $this->http->JsonLog(null, 3, false, 'mask');

        $email = $this->AccountFields['Login'];

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $email = $response->data->recoveryMethods[0]->mask ?? null;
        }

        if (!$email) {
            $this->logger->error("email not found");

            // We're sorry, the account system is having a problem.
            if ($this->http->FindPreg("/\"errors\":\[\{\"code\":\"INVALID_VALUE\",\"category\":\"ACTIONABLE_INPUT\",\"inputName\":\"loginValue\",/")) {
                throw new CheckException("We're sorry, the account system is having a problem.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $question = "We sent a code to {$email}. Enter the code here to continue.";

        $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key'));
        $headers["correlation-id"] = ArrayVal($this->http->Response['headers'], 'correlation-id', null) ?? $this->gen_uuid();
        $headers["conversation-id"] = ArrayVal($this->http->Response['headers'], 'conversation-id', null) ?? $this->gen_uuid();

        $data = [
            "lookupValue" => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/TPR-DCL-LBJS.WEB-PROD/notification/otp/recovery?langPref=en-US", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $sessionId = $response->data->sessionId ?? null;

        if (!$sessionId) {
            $this->logger->error("something went wrong");

            return false;
        }

        $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key'));
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

        return true;
    }

    public function ProcessStep($step)
    {
        $this->State['2faData']["passcode"] = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/TPR-DCL-LBJS.WEB-PROD/otp/redeem?langPref=en-US", json_encode($this->State['2faData']), $this->State['2faHeaders']);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $code = $response->error->errors[0]->code ?? null;

        // The code you entered is incorrect or expired.
        if ($code === 'DEVICE_LINK_INVALID_OTP_CODE') {
            $this->AskQuestion($this->Question, "The code you entered is incorrect or expired.", "Question");

            return false;
        }

        if (!isset($response->data->access_token)) {
            return false;
        }

        $data = [
            "swid"          => $response->data->swid,
            "recoveryToken" => $response->data->access_token,
        ];
        $this->http->PostURL("https://registerdisney.go.com/jgc/v8/client/TPR-DCL-LBJS.WEB-PROD/guest/login/recoveryToken?expand=profile&expand=displayname&expand=linkedaccounts&expand=marketing&expand=entitlements&expand=s2&langPref=en-US&feature=no-password-reuse", json_encode($data), $this->State['2faHeaders']);
        $response = $this->http->JsonLog();

        if (!empty($response->data->token->access_token) /*&& $this->http->Response['code'] == 200*/) {
            $this->captchaReporting($this->recognizer);
            $this->State['Authorization'] = $response->data->token->access_token;
            $this->State['SWID'] = $response->data->token->swid;

            $data = [
                "swid"             => $this->State['SWID'],
                "accessToken"      => $this->State['Authorization'],
                "refreshToken"     => $response->data->token->refresh_token,
                "sessionEventType" => "LOGIN",
            ];
            $headers = [
                "Accept"       => "*/*",
                "Content-Type" => "application/json",
                "Referer"      => "https://disneycruise.disney.go.com/en/login?appRedirect=%2Fwhy-cruise-disney%2Fcastaway-club%2F&cancelUrl=%2Fwhy-cruise-disney%2Fcastaway-club%2F",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://disneycruise.disney.go.com/profile-api/authentication/session", json_encode($data), $headers);
//            $this->http->PostURL("https://disneycruise.disney.go.com/profile/log/info", '["Authenticator - onUpdateSession",{"swid":"' . $this->State['SWID'] . '"}]', $headers);
//            $this->http->GetURL("https://disneycruise.disney.go.com/why-cruise-disney/castaway-club/");
            $headers = [
                "Accept"        => "application/json",
                "authorization" => "BEARER {$this->State['Authorization']}",
                "content-type"  => "application/json",
            ];
            $this->http->GetURL("https://registerdisney.go.com/jgc/v8/client/TPR-DCL-LBJS.WEB-PROD/guest/%7B" . str_replace(['{', '}'], '', $this->State['SWID']) . "%7D", $headers);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();

            return true;
        }

        return false;
    }

    protected function beautifulName($name)
    {
        if (is_array($name)) {
            $name = implode(' ', $name);
        }

        $name = beautifulName($name);
        $name = trim($name);
        $name = preg_replace('/\s{2}/', ' ', $name);

        return $name;
    }

    protected function ArrayVal($ar, $indices, $default = null)
    {
        if (is_string($indices)) {
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

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/"recaptchaV3Enabled":true_,"recaptchaV3SiteKey":"([^"]+)/');
        $action = 'homepage';

        if (!$key) {
            $action = 'login';
            $key = $this->http->FindPreg('/"recaptchaV4Config":\{"default":\{"reCaptchaEnabled":true,"reCaptchaSiteKey":"([^"]+)/');
        }

        $this->logger->debug("data-sitekey: {$key}");
        $this->logger->debug("action: {$action}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $currentURL ?? $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.9,
            "pageAction" => $action,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
//
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
//        $this->recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "pageurl"   => $this->http->currentUrl(),
//            "proxy"     => $this->http->GetProxy(),
//            "version"   => "v3",
//            "action"    => $action,
//            "min_score" => 0.9,
//        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function jsonToForm($json)
    {
        $this->logger->notice(__METHOD__);
        $data = ArrayVal($json, 'data');

        if (!$data) {
            $error = ArrayVal($json, 'error');

            if (isset($error['errors'][0]['code'])) {
                switch ($error['errors'][0]['code']) {
                    case 'AUTHORIZATION_CREDENTIALS':
                        // wrong captcha answer
                        if (
                            isset($error['errors'][0]['data']['type'])
                            && $error['errors'][0]['data']['type'] == 'GenericErrorData'
                            && $error['errors'][0]['data']['reasonCode'] == 'PALOMINO_CHECK_FAILED'
                            && !isset($error['errors'][0]['data']['assessmentId'])
//                            $this->http->FindPreg("/\"data\":\s*\{\"type\":\"GenericErrorData\",\"reasonCode\":\"PALOMINO_CHECK_FAILED\"/")
                    ) {
                            $this->logger->error("wrong captcha answer");
                            $this->captchaReporting($this->recognizer, false);

                            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
                        }

                        // We sent a code to ... . Enter the code here to continue.
                        $assessmentId = $error['errors'][0]['data']['assessmentId'] ?? null;

                        if (
                            isset($error['errors'][0]['data']['type'])
                            && $error['errors'][0]['data']['type'] == 'GenericErrorData'
                            && $error['errors'][0]['data']['reasonCode'] == 'PALOMINO_CHECK_FAILED'
                            && $assessmentId
                        ) {
                            $this->logger->notice("2fa");
                            $this->captchaReporting($this->recognizer);

                            if ($this->isBackgroundCheck()) {
                                $this->Cancel();
                            }

                            $this->parseQuestion($assessmentId);

                            return false;
                        }

                        $this->captchaReporting($this->recognizer);

                        throw new CheckException("The credentials you entered are incorrect. Reminder: passwords are case sensitive.", ACCOUNT_INVALID_PASSWORD);

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

    private function ParseItinerariesMyReservations($swid)
    {
        $this->logger->notice(__METHOD__);
        $res = [];

        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];
        $this->http->GetURL(sprintf("https://disneycruise.disney.go.com/my-disney-cruise/my-reservations/api/v1/cruise-reservations/%s?avatar=1", urlencode($swid)), $headers);
        $data = $this->http->JsonLog(null, 3, true, 'items');

        $profiles = ArrayVal($data, 'profiles', []);
        $passengerMap = [];

        foreach ($profiles as $key => $value) {
            $name = $this->beautifulName(ArrayVal($value, 'name'));
            $passengerMap[$key] = trim($name);
        }

        $items = ArrayVal($data, 'items', []);

        if (empty($items) && $this->http->FindPreg("/\{\"items\":\[\],\"assets\":\{\},\"profiles\"/")) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        foreach ($items as $item) {
            $res[] = $this->ParseItineraryApi($item, $passengerMap);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function ParseItineraryApi($item, $passengerMap)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];
        $res['TripCategory'] = TRIP_CATEGORY_CRUISE;

        // RecordLocator
        $conf = ArrayVal($item, 'confirmationNumber');
        $this->logger->info(sprintf('Parse Itinerary #%s', $conf), ['Header' => 3]);
        $res['RecordLocator'] = $conf;
        // RoomNumber
        $res['RoomNumber'] = $this->ArrayVal($item, ['stateroom', 'number']);
        // RoomClass
        $res['RoomClass'] = $this->ArrayVal($item, ['stateroom', 'cabinCategory']);
        // Deck
        $res['Deck'] = $this->ArrayVal($item, ['stateroom', 'deck']);

        // CruiseName
        $res['CruiseName'] = $this->ArrayVal($item, ['sailingAsset', 'product', 'name']);
        // ShipName
        $res['ShipName'] = $this->ArrayVal($item, ['sailingAsset', 'ship', 'name']);

        // Passengers
        $guests = ArrayVal($item, 'guests', []);
        $passengers = [];

        foreach ($guests as $guest) {
            $id = ArrayVal($guest, 'id');

            if ($id) {
                $passengers[] = $passengerMap[$id];
            }
        }
        $res['Passengers'] = $passengers;

        // TotalCharge
        $res['TotalCharge'] = $this->ArrayVal($item, ['invoice', 'balance', 'total']);
        // Currency
        $res['Currency'] = $this->ArrayVal($item, ['invoice', 'balance', 'currency']);

        // TripSegments
        // $cruise = $this->ParseSegmentsApiSwid($swid, $conf);
        $cruise = $this->ParseSegmentsApiProp($conf);
        $this->converter = new CruiseSegmentsConverter();
        $res['TripSegments'] = $this->converter->Convert($cruise);

        return $res;
    }

    private function ParseSegmentsApiProp($conf)
    {
        $this->logger->notice(__METHOD__);

        $this->logger->info('details:');
        $url = sprintf('https://disneycruise.disney.go.com/my-disney-cruise/my-reservations/api/v1/trip/%s/', $conf);
        $this->http->GetURL($url);
        $data = $this->http->JsonLog(null, 0, true);

        $days = ArrayVal($data, 'days', []);
        $result = [];

        foreach ($days as $day) {
            $ports = ArrayVal($day, 'ports', []);

            foreach ($ports as $port) {
                $seg = [];
                $seg['Port'] = ArrayVal($port, 'name');
                $events = ArrayVal($port, 'events', []);

                foreach ($events as $event) {
                    $this->logger->info('event:');
                    $this->logger->info(var_export($event, true));
                    $subtype = ArrayVal($event, 'subType');

                    if ($subtype === 'guest-onboard') {
                        $dt = strtotime($event['startDateTime']);
                        $dt -= $dt % 60;
                        $seg['DepDate'] = $dt;
                        $result[] = $seg;
                    } elseif ($subtype === 'guest-onshore') {
                        $dt = strtotime($event['startDateTime']);
                        $dt -= $dt % 60;
                        $seg['ArrDate'] = $dt;
                        $result[] = $seg;
                    } elseif ($subtype === 'embarkation') {
                        $dt = strtotime($event['startDateTime']);
                        $dt -= $dt % 60;
                        $seg['DepDate'] = $dt;
                        $result[] = $seg;
                    } elseif ($subtype === 'disembarkation') {
                        $dt = strtotime($event['startDateTime']);
                        $dt -= $dt % 60;
                        $seg['ArrDate'] = $dt;
                        $result[] = $seg;
                    }
                }
            }
        }

        return $result;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
//            $selenium->setKeepProfile(true);
            //$selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
//            $selenium->http->GetURL("https://disneycruise.disney.go.com/en/login?appRedirect=%2Fwhy-cruise-disney%2Fcastaway-club%2F");
            $selenium->http->GetURL("https://disneycruise.disney.go.com/login/");
            $iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'oneid-iframe']"), 10);
            $this->savePageToLogs($selenium);

            if (!$iframe) {
                $this->logger->error('no iframe');

                if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }
            $selenium->driver->switchTo()->frame($iframe);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[contains(@placeholder, "Email")]'), 7);
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
                if ($message = $this->http->FindSingleNode('//span[@id = "InputIdentityFlowValue-error"]')) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        $message == "This email isn't properly formatted. Try again?"
                        || strstr($message, "There's a problem with the account for this email address")
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                if ($this->http->FindSingleNode('//h1[span[contains(text(), "Create Your Account") or contains(text(), "Create an account to continue")]]')) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return $this->checkErrors();
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/"access_token":|"errors\":\[\{"code":/g.exec( this.responseText )) {
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
            sleep(4);
            $authData = $selenium->driver->executeScript("return localStorage.getItem('auth_data');");
            $this->logger->info("[got auth data]: " . $authData);
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);
            $responseDataQuestion = $selenium->driver->executeScript("return localStorage.getItem('responseDataQuestion');");
            $this->logger->info("[Form responseDataQuestion]: " . $responseDataQuestion);

            /*
            $selenium->driver->switchTo()->defaultContent();
            $this->savePageToLogs($selenium);
            */
            $selenium->waitForElement(WebDriverBy::xpath("
                //a[contains(text(), 'Welcome,')]
                | //span[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')]
                | //h2[contains(text(), 're sorry, the account system is having a problem.')]
                | //div[contains(@class, 'message-error')]
                | //h2[contains(text(), 'Please Update Your Account')]
                | //h2[contains(text(), 'Time for a new password!')]
                | //h2[contains(text(), 'Stay in touch!')]
                | //h1[@id = 'Title' and contains(., 'Stay in touch!')]
                | //button[contains(text(), 'Update My Account')]
                | //button[contains(text(), 'Skip')]
                | //span[contains(text(), 'Email me at') or contains(text(), 'Email a code to')]
                | //span[contains(text(), 'Text a code to )]
                | //p[contains(text(), 'We sent a code to')]
                | //p[contains(text(), 'To protect your account, we've temporarily locked it')]
            "), 15);
            $this->savePageToLogs($selenium);

            // skip "update password"
            if ($skipBtn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Skip')]"), 0)) {
                $skipBtn->click();
                $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Welcome,')]"), 15);
                $this->savePageToLogs($selenium);
            }

            // invalid credentials false/posirive issue
            if ($sendCode = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Email me at') or contains(text(), 'Email a code to')]"), 0)
                ?? $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Text a code ')]"), 0)
            ) {
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
                $sendCodeBtn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'BtnSubmit']"), 0);
                $this->savePageToLogs($selenium);

                $sendCodeBtn->click();

                $selenium->waitForElement(WebDriverBy::xpath("
                    //a[contains(text(), 'Welcome,')]
                    | //span[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')]
                    | //h2[contains(text(), 're sorry, the account system is having a problem.')]
                    | //div[contains(@class, 'message-error')]
                    | //h2[contains(text(), 'Please Update Your Account')]
                    | //h2[contains(text(), 'Time for a new password!')]
                    | //h2[contains(text(), 'Stay in touch!')]
                    | //h1[@id = 'Title' and contains(., 'Stay in touch!')]
                    | //button[contains(text(), 'Update My Account')]
                    | //button[contains(text(), 'Skip')]
                    | //p[contains(text(), 'We sent a code to')]
                    | //p[contains(text(), 'To protect your account, we've temporarily locked it')]
                    | //div[contains(@class, 'error-container')]/span
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
                if ($selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')]"), 0)) {
                    $this->throwProfileUpdateMessageException();
                }

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
                        "oneid-reporting" => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=',
                        "device-id"       => 'null',
                        "expires"         => -1,
                        "Origin"          => "https://cdn.registerdisney.go.com",
                        "Referer"         => 'https://cdn.registerdisney.go.com/',
                    ];

                    $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key')); //todo
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

                if ($this->http->FindSingleNode("//h2[
                        contains(text(), 'Please Update Your Account')
                        or contains(text(), 'Stay in touch!')
                    ]
                    | //h1[@id = 'Title' and contains(., 'Stay in touch!')]
                ")) {
                    $this->throwAcceptTermsMessageException();
                }

                if ($this->http->FindSingleNode("//h2[
                        contains(text(), 'Time for a new password!')
                    ]")
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                if ($this->http->FindSingleNode("(//a[contains(text(),'Welcome, ')])[1]", null, false, '/Welcome,\s+([^!]+)/')) {
                    return true;
                }

                $this->jsonToForm($this->http->JsonLog($responseData, 5, true));
                $this->http->SetBody($responseData);

                if ($this->ErrorCode == ACCOUNT_QUESTION) {
                    return false;
                }

                return true;
            } elseif ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the account system is having a problem.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } elseif ($this->http->FindSingleNode('//p[contains(text(), "To protect your account, we\'ve temporarily locked it.")]')) {
                throw new CheckException("To protect your account, we've temporarily locked it.", ACCOUNT_PROVIDER_ERROR);
            } elseif ($message = $this->http->FindSingleNode("//div[contains(@class, 'error-container')]/span")) {
                $this->logger->notice("[Error]: {$message}");

                if (strstr($message, 'We couldn\'t log you in. Please check your email and password and try again')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            } elseif ($this->http->FindSingleNode("(//a[contains(text(),'Welcome, ')])[1]", null, false, '/Welcome,\s+([^!]+)/')) {
                return true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
