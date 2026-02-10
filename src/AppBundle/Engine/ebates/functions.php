<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEbates extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""        => "Select your region",
        'Canada'  => 'Canada',
        "Germany" => "Germany",
        'UK'      => 'UK',
        'USA'     => 'USA',
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headersGermany = [
        "Accept"       => "*/*",
        "Content-Type" => "application/json",
    ];

    private $client_id = "am_de";
    private $domain = "de";

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] == 'points') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    // refs #14471
    public static function DisplayName($fields)
    {
        switch ($fields["Login2"]) {
            case 'Canada':
                $fields["DisplayName"] = 'Rakuten.ca';

                break;

            case 'UK':
                $fields["DisplayName"] = 'Rakuten.co.uk';

                break;

            case 'Germany':
                $fields["DisplayName"] = 'Rakuten.de';

                break;

            default:
                $fields["DisplayName"] = 'Rakuten.com';

                break;
        }

        if (isset($fields['Properties']['PayoutOn'])) {
            return $fields["DisplayName"] . " (Payout on {$fields['Properties']['PayoutOn']['Val']})";
        }

        return $fields["DisplayName"];
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $redirectURL = 'https://www.rakuten.ca/login?form';

                break;

            case 'Germany':
                $redirectURL = 'https://login.account.rakuten.com/sso/authorize?client_id=am_de&redirect_uri=https://www.rakuten.de/club-everywhere&r10_audience=cat:refresh&response_type=code&scope=openid&prompt=login#/sign_in';

                break;

            case 'UK':
                $redirectURL = 'https://login.account.rakuten.com/sso/authorize?client_id=am_uk&redirect_uri=https://rakuten.co.uk&r10_audience=cat:refresh&response_type=code&scope=openid&state=%2F';

                break;

            case 'USA':
            default:
                $redirectURL = 'https://www.rakuten.com/auth/getLogonForm.do';
        }

        $arg["RedirectURL"] = $redirectURL;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        if ($this->AccountFields['Login2'] == 'UK') {
            $this->client_id = 'am_uk';
            $this->domain = 'co.uk';
        }

        if ($this->AccountFields['Login2'] == 'Canada') {
            $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
            $this->http->setHttp2(true);
        }
    }

    public function IsLoggedIn()
    {
        if (in_array($this->AccountFields['Login2'], ['Germany', 'UK'])) {
            if (!isset($this->State['headers'])) {
                return false;
            }

            if ($this->loginSuccessful()) {
                return true;
            }

            return false;
        }
        $url = "https://www.rakuten.com/pending-cash-back.htm";

        if ($this->AccountFields['Login2'] == 'Canada') {
            $url = 'https://www.rakuten.ca/member/dashboard';
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL($url, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (in_array($this->AccountFields['Login2'], ['Germany', 'UK'])) {
            return call_user_func([$this, "LoadLoginFormOfGermany"]);
        }

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Oops. The email address and/or password you entered is incorrect. Remember, passwords are case-sensitive. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        return call_user_func([$this, "LoadLoginFormOf" . $this->AccountFields['Login2']]);
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        if (in_array($this->AccountFields['Login2'], ['Germany', 'UK'])) {
            return call_user_func([$this, "LoginOfGermany"]);
        }

        return call_user_func([$this, "LoginOf" . $this->AccountFields['Login2']]);
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        return call_user_func([$this, "ParseOf" . $this->AccountFields['Login2']]);
    }

    /* ------------ USA ------------ */

    public function LoadLoginFormOfUSA()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->ParseForms = false;
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.rakuten.com/auth/getLogonForm.do");
        $this->http->RetryCount = 2;

        // under construction
        if ($message = $this->http->FindSingleNode("//div[@id='under-construction']")) {
            throw new CheckException("Please pardon the dust. We're working hard to bring you an even better shopping experience. We should be back up shortly. Thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] != 200) {
            // retries
            if (
                (isset($this->http->Response['code']) && $this->http->Response['code'] == 0)
                || empty($this->http->Response['body'])
                || $this->http->FindSingleNode('//h1[contains(text(), "406 Not Acceptable")]')
            ) {
                throw new CheckRetryNeededException(3, 5);
            }

            return $this->checkErrors();
        }

        // loading ajax form
        $this->http->GetURL("https://www.rakuten.com/ajax/si.htm");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'signInAjax.do')]")) {
            return $this->checkErrors();
        }

        return $this->selenium();
        /*$this->http->Inputs = array(
            'password' => array(
                'maxlength' => 12,
            ),
        );*/
        $this->http->SetInputValue("terms", $this->http->FindSingleNode("//input[@name='terms']/@value"));
        $this->http->SetInputValue("urlIdentifier", '/|skinny');
        $this->http->SetInputValue("type", "skinny");
        $this->http->SetInputValue("_csrf", $this->http->FindSingleNode("//input[@name='_csrf']/@value"));
        $this->http->SetInputValue("split_entry_id", "813");
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("isAjax", "true");
        $this->http->unsetInputValue('email_address');

        if ($captcha = $this->parseReCaptcha()) {
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
        }

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setDefaultHeader('x-requested-with', 'XMLHttpRequest');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "Service Unavailable")]
                | //h1[contains(text(), "We’re currently under maintenance.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance (Under construction error)
        if (($message = $this->http->FindSingleNode("//div[@id = 'under-construction']/img[contains(@src, 'data:image/jpeg;base64,R0lGODlhIAOIAtUAAP/MAsDepbi4t') or contains(@src, 'maintenance.png')]/@src"))
            || ($message = $this->http->FindSingleNode('//div[@id="main"]/div[@id="under-construction"]'))) {
            throw new CheckException("Please pardon the dust. We're working hard to bring you an even better shopping
             experience. We should be back shortly. Thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // Don't panic! We'll be back in a few. Ebates is performing scheduled maintenance
        if ($message = $this->http->FindSingleNode('//img[contains(@alt, "Don\'t panic! We\'ll be back in a few. Ebates is performing scheduled maintenance")]/@alt')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Proxy Error
        if ($this->http->FindPreg("/<title>502 Proxy Error<\/title>/")
            // 404 Not Found
            || $this->http->FindPreg("/<title>404 Not Found<\/title>/")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/An error occurred while processing your request\./ims")
            || $this->http->FindPreg('/The server encountered an internal error or misconfiguration/')
            || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - DNS failure")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Access has been blocked
        if ($message = $this->http->FindPreg('/Your access to this site is prohibited/i')) {
            $this->DebugInfo = self::ERROR_REASON_BLOCK;
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(4, 10);
        }// if ($message = $this->http->FindPreg('/Your access to this site is prohibited/i'))

        // invalid credentials, hard code
        if (substr_count($this->AccountFields['Pass'], '❹') >= 1
            || substr_count($this->AccountFields['Pass'], '❶') >= 1
            || substr_count($this->AccountFields['Pass'], '❷') >= 1
            || substr_count($this->AccountFields['Pass'], '❺') >= 1) {
            throw new CheckException("Oops. The email address and/or password you entered is incorrect. Remember, passwords are case-sensitive. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    // AccountID: 867822, 3003716, 1097310
    public function internalRedirect()
    {
        $this->logger->notice(__METHOD__);

        if ($redirect = $this->http->FindPreg("/var updatedUrl = decodeURIComponent\(\"([^\"]+)/")) {
            $this->http->GetURL(urldecode($redirect));
        }
    }

    public function LoginOfUSA()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        /*
        if (!$this->http->PostForm() && $this->http->Response['code'] != 302) {
            // Oops. There was a problem. Please try again.
            if (
                $this->http->FindSingleNode("//h1[contains(text(), '400 Bad Request')]")
                && $this->http->Response['code'] == 400
            ) {
                throw new CheckException("Oops. There was a problem. Please try again.", ACCOUNT_PROVIDER_ERROR);
            }

            // retries
            if (
                $this->http->FindSingleNode('//h1[contains(text(), "406 Not Acceptable")]')
            ) {
                throw new CheckRetryNeededException(3, 5);
            }

            return $this->checkErrors();
        }
        */
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $error = $response->message ?? $this->http->FindSingleNode('//div[contains(@class, "auth-err") and not(contains(@class, "hide"))] | //div[contains(@class, "error invalid")]') ?? null;

        if ($this->http->FindPreg('/"status":"success"/')) {
            $this->http->GetURL("https://www.rakuten.com/");
            $this->internalRedirect();
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($error) {
            $this->logger->error("[Error]: {$error}");

            if ($error == 'Please review our new Privacy Policy and Terms & Conditions.') {
                $this->captchaReporting($this->recognizer);
                $this->throwAcceptTermsMessageException();
            }

            if (
                $error == 'Invalid username/password'
                || $error == 'Oops. The email address and/or password you entered is incorrect. Remember, passwords are case-sensitive. Please try again.'
                || $error == 'Your account is currently unavailable. Please contact Customer Care for assistance.'
                || $error == 'Time to reset your password. Update your password every so often to keep your account secure. Please reset your password.'
                || $error == 'Your password must be at least 8 characters long.'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if ($error == "java.lang.IllegalStateException: Expected BEGIN_OBJECT but was STRING at line 1 column 20") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Unknown error happened. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $error == 'Oops. There was a problem, please try again later.'
                || $error == 'Oops. There was a problem. Please try again.'
            ) {
                $this->captchaReporting($this->recognizer);

//                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                throw new CheckRetryNeededException(3, 0, $error, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $error == 'Your account is currently unavailable. Please contact Member Services for assistance.'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            // Oops. The verification code you entered was incorrect. Please try again.
            if (
                $error == 'invalid_recaptcha'
                || $error == 'Oops. Captcha validation is required. Please try again.'
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
            // Time to reset your password. Update your password every so often to keep your account secure. Please reset your password.
            if ($error == 'Password force reset') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Time to reset your password. Update your password every so often to keep your account secure. Please reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $error;

            return false;
        }

        if (($response->csrf_validation_failed ?? null) === true) {
            throw new CheckRetryNeededException(2, 1);
        }

        // broken accounts
        if (
            ($response->status ?? null) === 'success'
            /*
            && in_array($this->AccountFields['Login'], [
                'daiyuzong@foxmail.com',
                'folofjc@hotmail.com',
                'bach.franziska@gmail.com',
                'fatima_kassam@yahoo.com',
            ])
            */
        ) {
            return true;
        }

        // Your Account Deletion is Pending
        if ($this->AccountFields['Login'] == 'stew12@gmail.com') {
            $this->http->GetURL('https://www.rakuten.com/social-connection.htm');
        }

        // Your Account Deletion is Pending
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Your Account Deletion is Pending")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ParseOfUSA()
    {
        $headers = [
            'x-requested-with' => 'XMLHttpRequest',
            'Accept'           => '*/*',
        ];
        $this->http->GetURL('https://www.rakuten.com/social-connection.htm', $headers);
        $this->internalRedirect();
        $response = $this->http->JsonLog($this->http->FindPreg("/ebates\.member = (.+)\;/"));
        // Name
        $this->SetProperty("Name", beautifulName($response->EbatesMember ?? null));
        // Member Since
        $this->SetProperty("MemberSince", $response->MemberSince ?? null);
        // Cash Paid
        $this->SetProperty("CashPaid", $response->CashPaid ?? null);
        // Total Cash Back
        $this->SetProperty("LifetimeCashBack", $response->TotalCashBack ?? null);

        $this->http->GetURL("https://www.rakuten.com/pending-cash-back.htm");

        if ($this->http->currentUrl() == "https://www.rakuten.com/") {
            $this->http->GetURL("https://www.rakuten.com/pending-cash-back.htm");
        }

        // isLoggedIn issue
        if ($this->attempt == 0 && $this->http->currentUrl() == "https://www.rakuten.com/myaccount/verify.htm") {
            throw new CheckRetryNeededException(2, 0);
        }

        $this->internalRedirect();
        // Name
        $name = $this->http->FindSingleNode("//span[contains(@class, 'member-name')]");
        $this->SetProperty("Name", beautifulName($name ?? $this->Properties['Name']));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(text(), 'Member since')]", null, true, "/Member\s*since\s*([^<]+)/"));
        // Lifetime Cash Back
        $this->SetProperty("LifetimeCashBack", str_replace('Cash Back', '', $this->http->FindSingleNode("//span[contains(@class, 'member-lifetime-cb') and contains(text(), '$')]")));
        // Lifetime MR Points
        $this->SetProperty("LifetimeMRPoints",
            $this->http->FindSingleNode("//span[contains(@class, 'member-lifetime-cb') and contains(text(), 'Rewards Point')]", null, true, "/(.+)\s*Membership/")
            ?? $this->http->FindSingleNode("//div[contains(text(), 'Lifetime Earnings')]/following-sibling::div/div[contains(text(), 'Membership Rewards® Points')]/preceding-sibling::div")
        );
        // Balance - Cash Pending
        $this->SetBalance(
            $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $this->http->FindSingleNode("//h2[contains(text(), ' Balance')]/span") ?? (isset($response->CashPending) && !strstr($response->CashPending, 'cashPending') ? $response->CashPending : null))
            ?? $this->http->FindSingleNode('//span[contains(text(), "Membership Rewards")]/preceding-sibling::span[@class = "amount"]')
            ?? $this->http->FindSingleNode('//div[contains(text(), "American Express Membership Rewards® Points")]/following-sibling::div[1]')
        );

        // refs #19487, isLoggedIn issue
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && (
                (isset($response->CashPending) && strstr($response->CashPending, 'cashPending'))
                || $this->http->FindSingleNode("//h2[contains(text(), ' Balance')]/span") === '$totalCash'
            )
            && $this->attempt == 0
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($message = $this->http->FindSingleNode("//div[contains(text(), 'You requested account deletion on')]"))
        ) {
            throw new CheckException("Your Account Deletion is Pending. " . $message, ACCOUNT_PROVIDER_ERROR);
        }

        // Payout on (Cash Back)
        $this->SetProperty("PayoutOn", $this->http->FindSingleNode("//div[contains(text(), 'Your next')]", null, true, "/sent\s*(?:by|on)\\s*([\d\/]+)/"));
        // Points to be transferred (Amex points)
        $this->SetProperty("PointsToBeTransferred", $this->http->FindSingleNode("//span[contains(text(), 'points will be transferred to your American Express')]", null, true, "/(.+)\s+points will be transferred to your American Express/"));

        // refs #18287, #23867
        if (
            $this->http->FindSingleNode('//a[@href="/my-account.htm"]/span[contains(text()," Points")]')
            /*strstr($this->http->FindSingleNode("//h2[contains(text(), ' Balance')]/span"), 'points')
            || $this->http->FindSingleNode('//span[contains(text(), "Membership Rewards")]/preceding-sibling::span[@class = "amount"]')
            || $this->http->FindSingleNode('//div[contains(text(), "American Express Membership Rewards® Points")]/following-sibling::div[1]')*/
        ) {
            $this->SetProperty("Currency", "points");
            unset($this->Properties['CashPaid']);

            return;
        }

        $this->expirationBalance();
    }

    /* ------------ Canada ------------ */

    public function LoadLoginFormOfCanada()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL("https://www.rakuten.ca/login?form");

        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrorsCanada();
        }
        $this->http->SetInputValue("fe_member_uname", $this->AccountFields['Login']);
        $this->http->SetInputValue("fe_member_pw", $this->AccountFields['Pass']);
        $this->http->SetInputValue("signin", 'Log In');

        return $this->selenium();

        if ($this->http->FindSingleNode("//form[@id = 'login-form']//input[@id = 'captcha']/@name") == 'captcha') {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue("captcha", $captcha);
        }// if ($this->http->FindSingleNode("//form[@id = 'login-form']//input[@id = 'captcha']/@name") == 'captcha')
        else {
            if ($captcha = $this->parseReCaptcha()) {
                $this->http->SetInputValue("g-recaptcha-response", $captcha);
            }
        }

        return true;
    }

    public function checkErrorsCanada()
    {
        $this->logger->error(__METHOD__);
        // Internal Server Error - Read
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoginOfCanada()
    {
        $this->http->RetryCount = 0;
        /*
        if (!$this->http->PostForm() && $this->http->Response['code'] != 401) {
            return $this->checkErrorsCanada();
        }
        */
        $this->http->RetryCount = 2;

        // logged in
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "error-box success-message")]/div[contains(@class, "clr-red")]/text()[last()]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'User is inactive')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($message = $this->http->FindSingleNode("//li[@class = 'err']/label")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect password
        if ($message = $this->http->FindPreg("/Incorrect password/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Member not found
        if ($message = $this->http->FindPreg("/Member not found email={$this->AccountFields['Login']} userName={$this->AccountFields['Login']}/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Member does not exist with email or username:  userName=... email=...
        if ($message = $this->http->FindPreg("/Member does not exist with email or username:\s*userName={$this->AccountFields['Login']} email={$this->AccountFields['Login']}/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry that username/password combination isn't correct
        if ($message = $this->http->FindPreg("/Sorry that username\/password combination isn't correct/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, that username/password combination is not valid
        if ($message = $this->http->FindPreg("/Sorry, that username\/password combination is not valid/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // This account has been temporary locked out due to excessive authentication failures.
        if ($this->http->FindPreg("/This account has been temporary locked out due to excessive authentication failures\./ims")) {
            throw new CheckException("This account has been temporary locked out due to excessive authentication failures.", ACCOUNT_LOCKOUT);
        }
        // Member does not exist with email or username:  email=... userName=...
        if ($message = $this->http->FindPreg("/(Member does not exist with email or username:\s*email=[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Member not found with email or username
        if ($message = $this->http->FindSingleNode('//form[@id = "login-form"]/div[contains(normalize-space(),"Member not found with email or username:")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // no errors, not auth
        if (
            $this->http->currentUrl() == 'https://www.rakuten.ca/login?success=false'
            && $this->http->Response['code'] == 401
            && $this->AccountFields['Login'] == 'nghia.quoc.dao@gmail.com'
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ParseOfCanada()
    {
        if ($this->http->currentUrl() != 'https://www.rakuten.ca/member/dashboard') {
            $this->http->GetURL('https://www.rakuten.ca/member/dashboard');
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(., 'Welcome,')]/following-sibling::span[1]", null, true, "/([^']+)/")));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(text(), 'Member since')]", null, true, "/Member \s*since\s*([^<]+)/"));
        // Cash Paid
        $this->SetProperty("CashPaid", $this->http->FindSingleNode("//p[contains(text(), 'Cash Paid:')]/a"));
        // Balance - Cash Back Balance
        $this->SetBalance($this->http->FindSingleNode("//a[@id = 'cashPending']", null, true, self::BALANCE_REGEXP_EXTENDED));
        // Lifetime Cash Back
        $this->SetProperty("LifetimeCashBack", $this->http->FindSingleNode("//span[contains(text(), 'Lifetime Cash Back')]/following-sibling::span[1]"));
        // refs #14471
        $this->http->GetURL("https://www.rakuten.ca/member/pending-cash-back");
        // Payout on
        $this->SetProperty("PayoutOn", $this->http->FindSingleNode("//span[contains(text(), 'Your next')]", null, true, "/sent\s*(?:by|on)\s*([\d\/]+)/"));

        $this->expirationBalance();
    }

    /* ------------ Germany ------------ */

    public function LoadLoginFormOfGermany()
    {
        $this->http->removeCookies();

        $this->http->FilterHTML = false;
        $this->http->GetURL("https://login.account.rakuten.com/sso/authorize?client_id={$this->client_id}&redirect_uri=https://www.rakuten.{$this->domain}&r10_audience=cat:refresh&response_type=code&scope=openid&prompt=login#/sign_in");
        $this->http->FilterHTML = true;

        $correlationId = $this->http->FindPreg("/correlationId:\s*\"([^\"]+)/");
        // Step Login
        $firstPageUserId = $this->http->FindPreg("/route:\"firstPage_UserId\",pageID:\"([^\"]+)/");
        // Step Pass
        $deviceRegistration = $this->http->FindPreg("/route:\"deviceRegistration\",pageID:\"([^\"]+)/");

        if (!$correlationId && !$firstPageUserId && !$deviceRegistration) {
            return $this->checkErrors();
        }

        // Step Login
        // getting challenge token and arguments for challenge solving
        $data = [
            'page_type' => 'LOGIN_START',
            'lang'      => 'en-US',
            'rat'       => null,
            'param'     => null,
        ];
        $this->http->PostURL("https://login.account.rakuten.com/util/gc?client_id={$this->client_id}&tracking_id={$correlationId}", json_encode($data), $this->headersGermany);
        $response = $this->http->JsonLog();

        if (!isset($response->cdata, $response->mdata, $response->token)) {
            return $this->checkErrors();
        }
        $challengeToken = $response->token;
        $cdata = $this->http->JsonLog(stripslashes($response->cdata));
        $cid = $cdata->body->result->cid ?? null;
        $mdata = $this->http->JsonLog(stripslashes($response->mdata));
        $mask = $mdata->body->mask ?? null;
        $key = $mdata->body->key ?? null;
        $seed = $mdata->body->seed ?? null;

        if (!isset($cid, $mask, $key, $seed)) {
            return $this->checkErrors();
        }

        // solving challenge
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $script = '
            function solvePow(obj, item, mask) { 
                if (void 0 === mask) {
                    mask = "";
                }
                var format = function(b, x) {
                    return 0 === b.substring(0, x.length).localeCompare(x);
                };
                var buildTokenList = function(dec_step) {
                    var result = "";
                    var maxvalue = 0;
                    for (; 16 - dec_step > maxvalue; maxvalue++) {
                        result = result + "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789".charAt(Math.floor(62 * Math.random()));
                    }
                    return result;
                };
                var id = obj + buildTokenList(obj.length);
                var iterations = 0;
                var x = x64hash128(id, item);
                var result = format(x, mask);
                for (; !result;) {
                    iterations = iterations + 1;
                    id = obj + buildTokenList(obj.length);
                    result = format(x = x64hash128(id, item), mask);
                }
                return id;
                return {
                    result : id,
                    iterations : iterations,
                    key : obj,
                    seed : item,
                    mask : mask
                };
            }
            function x64Add(e, n) {
                var r = [0, 0, 0, 0];
                return r[3] += (e = [e[0] >>> 16, 65535 & e[0], e[1] >>> 16, 65535 & e[1]])[3] + (n = [n[0] >>> 16, 65535 & n[0], n[1] >>> 16, 65535 & n[1]])[3],
                    r[2] += r[3] >>> 16,
                    r[3] &= 65535,
                    r[2] += e[2] + n[2],
                    r[1] += r[2] >>> 16,
                    r[2] &= 65535,
                    r[1] += e[1] + n[1],
                    r[0] += r[1] >>> 16,
                    r[1] &= 65535,
                    r[0] += e[0] + n[0],
                    r[0] &= 65535,
                    [r[0] << 16 | r[1], r[2] << 16 | r[3]]
            }
            function x64Multiply(e, n) {
                var r = [0, 0, 0, 0];
                return r[3] += (e = [e[0] >>> 16, 65535 & e[0], e[1] >>> 16, 65535 & e[1]])[3] * (n = [n[0] >>> 16, 65535 & n[0], n[1] >>> 16, 65535 & n[1]])[3],
                    r[2] += r[3] >>> 16,
                    r[3] &= 65535,
                    r[2] += e[2] * n[3],
                    r[1] += r[2] >>> 16,
                    r[2] &= 65535,
                    r[2] += e[3] * n[2],
                    r[1] += r[2] >>> 16,
                    r[2] &= 65535,
                    r[1] += e[1] * n[3],
                    r[0] += r[1] >>> 16,
                    r[1] &= 65535,
                    r[1] += e[2] * n[2],
                    r[0] += r[1] >>> 16,
                    r[1] &= 65535,
                    r[1] += e[3] * n[1],
                    r[0] += r[1] >>> 16,
                    r[1] &= 65535,
                    r[0] += e[0] * n[3] + e[1] * n[2] + e[2] * n[1] + e[3] * n[0],
                    r[0] &= 65535,
                    [r[0] << 16 | r[1], r[2] << 16 | r[3]]
            }
            function x64Rotl(e, n) {
                return 32 == (n %= 64) ? [e[1], e[0]] : 32 > n ? [e[0] << n | e[1] >>> 32 - n, e[1] << n | e[0] >>> 32 - n] : [e[1] << (n -= 32) | e[0] >>> 32 - n, e[0] << n | e[1] >>> 32 - n]
            }
            function x64LeftShift(e, n) {
                return 0 == (n %= 64) ? e : 32 > n ? [e[0] << n | e[1] >>> 32 - n, e[1] << n] : [e[1] << n - 32, 0]
            }
            function x64Xor(e, n) {
                return [e[0] ^ n[0], e[1] ^ n[1]]
            }
            function x64Fmix(e) {
                return e = this.x64Xor(e, [0, e[0] >>> 1]),
                    e = this.x64Multiply(e, [4283543511, 3981806797]),
                    e = this.x64Xor(e, [0, e[0] >>> 1]),
                    e = this.x64Multiply(e, [3301882366, 444984403]),
                    this.x64Xor(e, [0, e[0] >>> 1])
            }
            function x64hash128(e, n) {
                for (var r = (e = e || "").length % 16, t = e.length - r, a = [0, n = n || 0], i = [0, n], u = [0, 0], o = [0, 0], c = [2277735313, 289559509], s = [1291169091, 658871167], f = 0; t > f; f += 16)
                    u = [255 & e.charCodeAt(f + 4) | (255 & e.charCodeAt(f + 5)) << 8 | (255 & e.charCodeAt(f + 6)) << 16 | (255 & e.charCodeAt(f + 7)) << 24, 255 & e.charCodeAt(f) | (255 & e.charCodeAt(f + 1)) << 8 | (255 & e.charCodeAt(f + 2)) << 16 | (255 & e.charCodeAt(f + 3)) << 24],
                        o = [255 & e.charCodeAt(f + 12) | (255 & e.charCodeAt(f + 13)) << 8 | (255 & e.charCodeAt(f + 14)) << 16 | (255 & e.charCodeAt(f + 15)) << 24, 255 & e.charCodeAt(f + 8) | (255 & e.charCodeAt(f + 9)) << 8 | (255 & e.charCodeAt(f + 10)) << 16 | (255 & e.charCodeAt(f + 11)) << 24],
                        u = this.x64Multiply(u, c),
                        u = this.x64Rotl(u, 31),
                        u = this.x64Multiply(u, s),
                        a = this.x64Xor(a, u),
                        a = this.x64Rotl(a, 27),
                        a = this.x64Add(a, i),
                        a = this.x64Add(this.x64Multiply(a, [0, 5]), [0, 1390208809]),
                        o = this.x64Multiply(o, s),
                        o = this.x64Rotl(o, 33),
                        o = this.x64Multiply(o, c),
                        i = this.x64Xor(i, o),
                        i = this.x64Rotl(i, 31),
                        i = this.x64Add(i, a),
                        i = this.x64Add(this.x64Multiply(i, [0, 5]), [0, 944331445]);
                switch (u = [0, 0],
                    o = [0, 0],
                    r) {
                    case 15:
                        o = this.x64Xor(o, this.x64LeftShift([0, e.charCodeAt(f + 14)], 48));
                    case 14:
                        o = this.x64Xor(o, this.x64LeftShift([0, e.charCodeAt(f + 13)], 40));
                    case 13:
                        o = this.x64Xor(o, this.x64LeftShift([0, e.charCodeAt(f + 12)], 32));
                    case 12:
                        o = this.x64Xor(o, this.x64LeftShift([0, e.charCodeAt(f + 11)], 24));
                    case 11:
                        o = this.x64Xor(o, this.x64LeftShift([0, e.charCodeAt(f + 10)], 16));
                    case 10:
                        o = this.x64Xor(o, this.x64LeftShift([0, e.charCodeAt(f + 9)], 8));
                    case 9:
                        o = this.x64Xor(o, [0, e.charCodeAt(f + 8)]),
                            o = this.x64Multiply(o, s),
                            o = this.x64Rotl(o, 33),
                            o = this.x64Multiply(o, c),
                            i = this.x64Xor(i, o);
                    case 8:
                        u = this.x64Xor(u, this.x64LeftShift([0, e.charCodeAt(f + 7)], 56));
                    case 7:
                        u = this.x64Xor(u, this.x64LeftShift([0, e.charCodeAt(f + 6)], 48));
                    case 6:
                        u = this.x64Xor(u, this.x64LeftShift([0, e.charCodeAt(f + 5)], 40));
                    case 5:
                        u = this.x64Xor(u, this.x64LeftShift([0, e.charCodeAt(f + 4)], 32));
                    case 4:
                        u = this.x64Xor(u, this.x64LeftShift([0, e.charCodeAt(f + 3)], 24));
                    case 3:
                        u = this.x64Xor(u, this.x64LeftShift([0, e.charCodeAt(f + 2)], 16));
                    case 2:
                        u = this.x64Xor(u, this.x64LeftShift([0, e.charCodeAt(f + 1)], 8));
                    case 1:
                        u = this.x64Xor(u, [0, e.charCodeAt(f)]),
                            u = this.x64Multiply(u, c),
                            u = this.x64Rotl(u, 31),
                            u = this.x64Multiply(u, s),
                            a = this.x64Xor(a, u)
                }
                return a = this.x64Xor(a, [0, e.length]),
                    i = this.x64Xor(i, [0, e.length]),
                    a = this.x64Add(a, i),
                    i = this.x64Add(i, a),
                    a = this.x64Fmix(a),
                    i = this.x64Fmix(i),
                    a = this.x64Add(a, i),
                    i = this.x64Add(i, a),
                ("00000000" + (a[0] >>> 0).toString(16)).slice(-8) + ("00000000" + (a[1] >>> 0).toString(16)).slice(-8) + ("00000000" + (i[0] >>> 0).toString(16)).slice(-8) + ("00000000" + (i[1] >>> 0).toString(16)).slice(-8)
            }
        ';

        $challengeResult = $jsExecutor->executeString($script . 'var r = solvePow("' . $key . '","' . $seed . '","' . $mask . '");sendResponseToPhp(r);');
        $this->logger->debug("challenge result: $challengeResult");

        // sending login
        $data = [
            "user_id"           => $this->AccountFields['Login'],
            "type"              => null,
            "linkage_token"     => "",
            "without_sso"       => false,
            "authorize_request" => [
                "client_id"             => $this->client_id,
                "redirect_uri"          => "https://www.rakuten.{$this->domain}",
                "scope"                 => "openid",
                "response_type"         => "code",
                "ui_locales"            => "en-US",
                "state"                 => "",
                "max_age"               => null,
                "nonce"                 => "",
                "code_challenge"        => "",
                "code_challenge_method" => "",
                "r10_required_claims"   => "",
                "r10_audience"          => "cat:refresh",
            ],
            "challenge"         => [
                "cres"  => $challengeResult,
                "token" => $challengeToken,
            ],
        ];
        $headers = $this->headersGermany + [
            "X-Correlation-ID" => $correlationId,
        ];
        $this->http->PostURL("https://login.account.rakuten.com/v2/login/start", json_encode($data, JSON_UNESCAPED_SLASHES), $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->token)) {
            $errorCode = $response->errorCode ?? null;
            $message = $response->message ?? null;

            if ($message === "User ID does not exist" && $errorCode === "USER_NOT_FOUND") {
                throw new CheckException("The user ID or email you have provided is not associated with any existing accounts. If the error persists, Please contact customer support", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $passwordToken = $response->token;

        // Step Pass
        // getting another challenge arguments and token
        $data = [
            'page_type' => 'LOGIN_COMPLETE_PASSWORD',
            'lang'      => 'en-US',
            'rat'       => null,
            'param'     => null,
        ];
        $this->http->PostURL("https://login.account.rakuten.com/util/gc?client_id={$this->client_id}&tracking_id={$correlationId}", json_encode($data), $this->headersGermany);
        $response = $this->http->JsonLog();

        if (empty($response->token)) {
            return $this->checkErrors();
        }
        $challengeToken = $response->token;
        $cdata = $this->http->JsonLog(stripslashes($response->cdata));
        $cid = $cdata->body->result->cid ?? null;
        $mdata = $this->http->JsonLog(stripslashes($response->mdata));
        $mask = $mdata->body->mask ?? null;
        $key = $mdata->body->key ?? null;
        $seed = $mdata->body->seed ?? null;

        if (!isset($cid, $mask, $key, $seed)) {
            return $this->checkErrors();
        }
        // solving challenge
        $challengeResult = $jsExecutor->executeString($script . 'var r = solvePow("' . $key . '","' . $seed . '","' . $mask . '");sendResponseToPhp(r);');
        $this->logger->debug($challengeResult);
        // sending password
        $data = [
            "user_key"  => $this->AccountFields['Pass'],
            "token"     => $passwordToken,
            "challenge" => [
                "cres"  => $challengeResult,
                "token" => $challengeToken,
            ],
        ];
        $this->http->PostURL("https://login.account.rakuten.com/v2/login/complete", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function LoginOfGermany()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response->redirect_uri)) {
            // 4655674
            if (strstr($response->redirect_uri, 'https://login.account.rakuten.com/profiling?token=')) {
                $this->throwProfileUpdateMessageException();
            }

            $this->http->setMaxRedirects(1);
            $this->http->RetryCount = 0;
            $this->http->GetURL($response->redirect_uri);
            $this->http->RetryCount = 2;
            $this->http->setMaxRedirects(5);

            $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

            if (!isset($code)) {
                return false;
            }

            $data = [
                "clientId"    => $this->client_id,
                "code"        => $code,
                "redirectUri" => "https://www.rakuten.{$this->domain}",
            ];
            $this->http->PostURL("https://oec-club-api-production.rakutenapps.com/token/oec-access-token", json_encode($data), $this->headersGermany);
            $response = $this->http->JsonLog();

            if (!isset($response->accessToken)) {
                return false;
            }

            $this->State['headers'] = [
                "Authorization" => "Bearer {$response->accessToken}",
                "Accept"        => "application/json, text/plain, */*",
            ];

            return $this->loginSuccessful();
        } elseif (
            isset($response->action, $response->agreement_links->tac, $response->agreement_type)
            && $response->action == "USER_AGREE"
            && $response->agreement_type == "RAKUTEN"
            && $response->agreement_links->tac == 'https://rakuten.co.uk/terms-and-conditions'
        ) {
            $this->throwAcceptTermsMessageException();
        }

        // Diese E-Mail-Passwort-Kombination ist uns nicht bekannt. Bitte korrigieren Sie Ihre Eingabe.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Diese E-Mail-Passwort-Kombination ist uns nicht bekannt. Bitte korrigieren Sie Ihre Eingabe.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Es ist ein technischer Fehler aufgetreten.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Um Ihr Einkaufserlebnis noch sicherer zu gestalten, bitten wir Sie, Ihr Passwort zu aktualisieren.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Um Ihr Einkaufserlebnis noch sicherer zu gestalten, bitten wir Sie, Ihr Passwort zu aktualisieren.')]")) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrorsGermany();
    }

    public function ParseOfGermany()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Balance - Meine einlösbaren Superpunkte
        $this->SetBalance($response->pointsBalance);
        // Offene Points
        $this->SetProperty("Pending", $response->pendingPointsBalance);

        // https://eu.account.rakuten.com/gateway/callback?exchange_token=%40Gt.GCPy795h4CKGK9w4NyiJ4elljCx7zdluU_yIJ6M_yAk.fo2gGRd8KotYQfxh.i6Nzc2m6MDFFVjE2TTlTV0ZZNENIMTUyRUYzN0pEN1Gjc3Vi2ShyaWQ6NDY2OGY3Y2QtN2E1ZS00OTEyLTk3MTEtNWQ2ZDg3MGU4YTk3o2lzc6NjYXSjaWF0zl_wS-ikY2Vwa8CncmFrdXRlboKkcHVycKhleGNoYW5nZaRmYWN0gahwYXNzd29yZIGhdM5f8DvxpGFzdWKS2SBnaWQ6MXJvbGk2MTBmY3A1bnprNGxiN3psYmNmdjR3Nq1laWQ6Mzk3MTAxNzIwo2F1ZJGjbXlypGNsaWSrb21uaV9jbGllbnSjZXhwzl_wTCSjZW5j2gKLQEliLmlZNkFKVk04dk5wbF9hd1Z6ZklPTFdqVnF5SHhTajQ4UXBmNXFSaUluSG8uZEVRX0IzM25WM2VpUnFqMlZEZ3lLWVdwTTRacjBGWHZUaW5JNlhtZFFqQzMzZHVXNENzT3I2UnNPaTdvU1pBZUpKQ1RqSU1MMUFzSm9DWXRKS29aTFVsampkRzgtQkhqSnY5d0VGX0MzTzVFWTRNeHpMTFRlcmhXUWlWVmdkdGxpNHBVQUQ4YnJ4am4tWDBWc21Valk4QWZLdnlHdDd6MWtTTWVoN2hOakdwU1JpOHFmQ0o0UmV6MmNjSkhHcDdDTWtYeHZqUUgyd19Cb3loZ1Bxd0xZLUpELUxmZk04cV9YUnRCcm0xOVJteU1CNjNKdmdrTTdNZ2d3azZjUWRWeDhGNllWWTRKWGFUMXJvaXRjZTM3Um9PVUNmOFRiMm1aa1pRSWRMX3FiUnJpRFMwVEhzek9iOWVldGNIMGd0NHBnQjhqSXVHanNGV0xubk9XZDd2NGF2bHpWMXNJb1p3eWVtNkR5d2NCbXpKRzBuUW05bHZETEY1RTY3N2ZsTW90Mk1SNUtMc0tVT0lVQlB3NTI2ZXdrNGRTU2xkWXRhSWNVTjUyS3NYNTc4YXZYbnJIZWNRSGZ5V25Ga2lIcWo5NmE1RHY1U3ZVakJuZ2k3eE0zOVZEcjFILU0yLVNNQkgwR1BZWFFFTXhsZ1hpSkIzY3RDcUFFQVVrYThUREVqSEJRSGc0M1NWUExkWHByVmtmRkNudlZKQUJKanY3b1o5VW8xVFZuMWFMY0s2MV84OE81S1J3VV9uNUlscC03R0lkT3N3RFZ3MC1CeFJXenAtS3REQ05PVEJwU2NR._-rOS3R58vM-obYwWFR0jhLeBBQmbp2o2HaWxQLwDlV1RfdQbwE2tLdhNPUy7LUJK7M6lKwr_46vaOzyWVnKDg&clientId=am_de
        // https://eu.account.rakuten.com/v2/member/summary/EUR
        // https://eu.account.rakuten.com/v2/member/points/transaction/EUR?year=2021&month=01

        /*
        // Expiration Date
        $pointsToExpire = null;
        if ($this->http->FindPreg("/verfallen/i"))
            $this->sendNotification('refs #18118 - check exp date //MI');
        $expires = $this->http->XPath->query("//div[@class='order-description']//span[contains(text(),' Superpunkte verfallen')]/ancestor::p");
        $this->logger->debug("Total " . $expires->length . " exp nodes found");
        foreach ($expires as $expire) {
            $expiration = $this->http->FindSingleNode("./span[2]", $expire, false, '/(\d+\.\d+.\d{4})/');
            $pointsToExpire = $this->http->FindSingleNode("./span[1]", $expire, false, '#([\d,.]+) Superpunkte verfallen#');
            $this->logger->debug("expiration date " . $expiration);
            $expiration = strtotime($expiration, false);
            if ($expiration != false && (!isset($exp) || $exp >= $expiration) && $pointsToExpire > 0) {
                if (isset($exp) && $exp == $expiration)
                    $this->sendNotification("refs #17221, buycom - {$exp} == {$expiration}");
                ## Points to Expire
                $this->SetProperty("PointsToExpire", $pointsToExpire);
                ## Expiration Date
                $exp = $expiration;
                $this->SetExpirationDate($exp);
            }
        }
        */
    }

    /* ------------ UK ------------ */

    public function LoadLoginFormOfUK()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL("https://login.account.rakuten.com/sso/authorize?client_id=am_uk&redirect_uri=https://rakuten.co.uk&r10_audience=cat:refresh&response_type=code&scope=openid&state=%2F");
        /*

//        $this->http->GetURL("https://rat.rakuten.co.jp/?cpkg_none=%7B%22acc%22%3A%221249%22%2C%22aid%22%3A1%2C%22cp%22%3A%7B%22psx%22%3A1657013481844%2C%22his%22%3A%22%E2%9D%AE01%E2%9D%AF%22%2C%22s_m%22%3A%22Main.Update%22%2C%22s_f%22%3A%22update%22%2C%22f_p%22%3A%22c32f7418b55d13f6a0d3be575eedb9e2%22%2C%22f_f%22%3A%7B%7D%2C%22cid%22%3A%22am_uk%22%2C%22cor%22%3A%22e977dd59-5235-4514-a441-c2335b2c9598%22%2C%22x%22%3A1536%2C%22y%22%3A578%2C%22url%22%3A%22https%3A%2F%2Flogin.account.rakuten.com%2Fsso%2Fauthorize%3Fclient_id%3Dam_uk%26redirect_uri%3Dhttps%3A%2F%2Frakuten.co.uk%26r10_audience%3Dcat%3Arefresh%26response_type%3Dcode%26scope%3Dopenid%26state%3D%252F%23%2Fsign_in%22%2C%22w_s%22%3Afalse%2C%22lng%22%3A%22en-US%22%2C%22env%22%3A%22production%22%2C%22msg%22%3A%22SolvedPOW%2Citerations%3A33511%2Ckey%3A74%2Cmask%3A116d%2Cseed%3A626900443%2Cresult20aPXDGJK7H5xiJy%22%2C%22evt%22%3A%22ChallengerCore%22%2C%22foc%22%3Atrue%2C%22vis%22%3Atrue%2C%22src%22%3A%22%2Fwidget%2Fjs%2F4hk58wd8hqxo493p8gm5bej-2.5.8.1.min.js%22%2C%22inf%22%3A%222.5.8.1-c616-75b2%22%7D%7D");

        $cid = $this->getCidUK("7d541e9c-fc7b-41c9-82fe-21797fff7eb7");

        if (!$cid) {
            return $this->checkErrorsUK();
        }

        $data = [
            "user_id"            => $this->AccountFields['Login'],
            "type"               => null,
            "linkage_token"      => "",
            "without_sso"        => false,
            "authorize_request"  => [
                "client_id"                    => "am_uk",
                "redirect_uri"                 => "https://rakuten.co.uk",
                "scope"                        => "",
                "response_type"                => "",
                "ui_locales"                   => "en-US",
                "state"                        => "",
                "max_age"                      => null,
                "nonce"                        => "",
                "display"                      => "page",
                "code_challenge"               => "",
                "code_challenge_method"        => "",
                "r10_required_claims"          => "",
                "r10_audience"                 => "",
                "r10_jid_service_id"           => "",
                "r10_preferred_authentication" => null,
                "token"                        => null,
            ],
            "challenge"          => [
                "pid"  => "7d541e9c-fc7b-41c9-82fe-21797fff7eb7",
                "cid"  => $cid,
                "cres" => "20aPXDGJK7H5xiJy",//todo
            ],
            "webauthn_supported" => false,
        ];
        $headers = [
            "Accept"          => "*
        /*",
            "Accept-Language" => "en-US",
            "Referer"         => "https://login.account.rakuten.com/",
            "Content-Type"    => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.account.rakuten.com/v2/login/start", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (
            !isset($response->token)
            || !isset($response->type)
            || $response->type != 'password'
        ) {
            return $this->checkErrorsUK();
        }

        $cid = $this->getCidUK("e30c73da-a8c5-430f-8edb-e06a22d50590");

        if (!$cid) {
            return $this->checkErrorsUK();
        }

        $data = [
            "user_key"  => $this->AccountFields['Pass'],
            "token"     => $response->token,
            "challenge" => [
                "pid"  => "e30c73da-a8c5-430f-8edb-e06a22d50590",
                "cid"  => $cid,
                "cres" => "c9hbM8v4j6XvYzOc",//todo
            ],
        ];
        $this->http->PostURL("https://login.account.rakuten.com/v2/login/complete", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        */

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useGoogleChrome();

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://login.account.rakuten.com/sso/authorize?client_id=am_uk&redirect_uri=https://rakuten.co.uk&r10_audience=cat:refresh&response_type=code&scope=openid&state=%2F");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "user_id"]'), 10);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "cta" and contains(., "Next")]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$button) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@id = "password_current"]
                | //div[@id = "ie-flex-fix-320"]
            '), 5);

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password_current"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "cta" and contains(., "Sign in")]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$button) {
                return true;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $button->click();

            sleep(5);
            $this->logger->debug("wait results");

            $selenium->waitForElement(WebDriverBy::xpath('
                //section[@data-qa-id="logged-in-homepage-welcome-message-title"]
                | //div[@id = "ie-flex-fix-320"]
            '), 5);
            $this->savePageToLogs($selenium);

            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strpos($xhr->request->getUri(), 'https://oec-club-api-production.rakutenapps.com/member/information') !== false) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                    $this->State['headers'] = $xhr->request->getHeaders() + ["Origin" => "https://rakuten.co.uk"];
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);
            }
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return true;
    }

    public function checkErrorsUK()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function LoginOfUK()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response->email)) {
            return $this->loginSuccessful();
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "ie-flex-fix-320"]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'That user ID or email could not be found.')) {
                throw new CheckException("That user ID or email could not be found.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        /*

        if (isset($response->redirect_uri)) {
            $this->http->GetURL($response->redirect_uri);

            $state = $this->http->FindPreg("/rakuten\.co\.uk([^&]+)/", false, $this->http->currentUrl());

            if (!$state) {
                return $this->checkErrorsUK();
            }

            $this->http->GetURL("https://login.account.rakuten.com/sso/authorize?client_id=am_uk&redirect_uri=https://rakuten.co.uk&r10_audience=cat:refresh&response_type=code&scope=openid&state={$state}");

            $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

            if (!$code) {
                return $this->checkErrorsUK();
            }

            $data = [
                "clientId"    => "am_uk",
                "redirectUri" => "https://rakuten.co.uk",
                "code"        => $code,
            ];
            $this->http->PostURL("https://oec-club-api-production.rakutenapps.com/token/oec-access-token", json_encode($data));
            $response = $this->http->JsonLog();

            if (!isset($response->accessToken)) {
                return $this->checkErrorsUK();
            }

            $headers = [
                "Accept"        => "application/json, text/plain, *
        /*",
                "Authorization" => "Bearer {$response->accessToken}",
                "Origin"        => "https://rakuten.co.uk",
            ];
            $this->http->GetURL("https://oec-club-api-production.rakutenapps.com/member/information", $headers);
            $this->http->JsonLog();
        }
        */

        return $this->checkErrorsUK();
    }

    public function ParseOfUK()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Balance - Points Available ... Points
        $this->SetBalance($response->pointsBalance);
        // Pending Points
        $this->SetProperty("Pending", $response->pendingPointsBalance);
        // Cashback Available
        $this->SetProperty("CashbackAvailable", $response->cashbackBalance);
        // Cashback Pending
        $this->SetProperty("CashbackPending", $response->pendingCashbackBalance);
    }

    public function GetHistoryColumns(): array
    {
        return [
            'Date'        => 'PostingDate',
            'Description' => 'Description',
            'Points'      => 'Miles',
            'Cash Back'   => 'Info',
            'Amount'      => 'Amount',
            'Currency'    => 'Currency',
        ];
    }

    public function ParseHistory($startDate = null): array
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');

        $startTimer = $this->getTime();

        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $this->http->GetURL('https://www.rakuten.ca/member/pending-cash-back');
                $result = array_merge($result, $this->ParseHistoryUSAorCanada($startDate));

                break;

            case 'USA':
                $this->http->GetURL('https://www.rakuten.com/pending-cash-back.htm');
                $result = array_merge($result, $this->ParseHistoryUSAorCanada($startDate));

                break;

            case 'Germany':
                $this->http->GetURL('https://rakuten.de/transaktionen');
                $result = array_merge($result, $this->ParseHistoryUKorGermany($startDate));

                break;

            case 'UK':
                $this->http->GetURL('https://rakuten.co.uk/transactions');
                $result = array_merge($result, $this->ParseHistoryUKorGermany($startDate));

                break;
        }

        $this->getTime($startTimer);

        return $result;
    }

    protected function checkErrorsGermany()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Momentan führen wir Änderungen an unseren Seiten durch.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Something went wrong and our site was not accessible
        if ($message = $this->http->FindPreg('/Da ist wohl gerade etwas schiefgegangen und unsere Seite war nicht erreichbar\./i')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($this->AccountFields['Login2'])) {
            $region = 'USA';
        }

        return $region;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login2'] == 'Canada') {
            $http2 = clone $this->http;

            if ($this->http->FindSingleNode("//form[@id = 'login-form']//input[@id = 'captcha']/@name") == 'captcha') {
                $file = $http2->DownloadFile("https://www.rakuten.ca/captcha", "jpg");
                $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
                $this->recognizer->RecognizeTimeout = 100;
                $captcha = $this->recognizeCaptcha($this->recognizer, $file);
                unlink($file);

                return $captcha;
            }// if ($this->http->FindSingleNode("//input[@id = 'captcha']/@name") == 'captcha')
        }// if ($this->AccountFields['Login2'] == 'Canada')
        else {
            $captcha = $this->http->FindSingleNode("//img[@id = 'returningcaptchaImg']/@src");

            if (!$captcha) {
                return false;
            }
            $this->http->NormalizeURL($captcha);
            $file = $this->http->DownloadFile($captcha, "jpg");
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $this->recognizer->RecognizeTimeout = 100;
            $captcha = $this->recognizeCaptcha($this->recognizer, $file);
            unlink($file);

            return $captcha;
        }

        return false;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login2'] == 'Canada') {
            $key =
                $this->http->FindSingleNode('//div[@id = "loginCaptcha"]/@data-sitekey')
                ?? $this->http->FindPreg("/ebates\.recaptcha\s*=\s*\{\s*siteKey:\s*\"([^\"]+)\"\s*,\s*enabled:\s*true/")
            ;
            $currentUrl = $this->http->currentUrl();
        } else {
            $key = $this->http->FindSingleNode("//input[@name='recaptcha-key']/@value");

//            if (!$key && $this->attempt > 1) {
//                $key = '6LeaJgcUAAAAAGvdeHpN60l0OrVT8znFD2fSB9Gl';
            $key = '6LcX6fQZAAAAAC-PhgK4ep1bFNO2n1BKWG-Tt2-u';
//            }
            $currentUrl = 'https://www.rakuten.com/auth/getLogonForm.do';
        }

        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $currentUrl,
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        // watchdog workaround
        $this->increaseTimeLimit(180);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->AccountFields['Login2'] == 'Canada'
            && $this->http->FindSingleNode('//a[contains(text(), "Logout")]')
            && $this->http->currentUrl() != 'https://www.rakuten.ca/member/verify'
        ) {
            return true;
        } elseif (in_array($this->AccountFields['Login2'], ['Germany', 'UK'])) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://oec-club-api-production.rakutenapps.com/member/information", $this->State['headers'], 20);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (
                isset($response->email)
                && strtolower($response->email) == strtolower($this->AccountFields['Login'])
            ) {
                return true;
            }
        } elseif (
            $this->AccountFields['Login2'] != 'Canada'
            && $this->http->FindSingleNode('
                    (//a[contains(@href, "/my-account.htm")])[1]
                ')
            && !strstr($this->http->currentUrl(), '/account/verify?')
        ) {
            return true;
        }

        return false;
    }

    // refs #14298
    private function expirationBalance()
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->Balance) || $this->Balance == '0.00') {
            return null;
        }
        $this->logger->info('Expiration date', ['Header' => 3]);

        for ($i = -1; ++$i < 3;) {
            if ($this->AccountFields['Login2'] == 'Canada') {
                $time = mktime(0, 0, 0, date('n') - $i, date('d'), date('Y'));
                $this->http->GetURL('https://www.rakuten.ca/member/shopping-trips/tickets?page=1&timeZone=America/Toronto&monthNo=' . (date('n', $time) - 1));
                $this->internalRedirect();
            } else {
                $this->http->PostURL('https://www.rakuten.com/account/w-shoppingtrips.htm?timespan=' . $i, [
                    'pageNum'      => 1,
                    'pageStartNum' => 1,
                    'pageEndNum'   => 50,
                ], ['x-requested-with' => 'XMLHttpRequest']);
                $this->internalRedirect();
            }
            $lastDate = $this->http->FindSingleNode('//table[contains(@class, "tt-table")]/tbody/tr[1]/td[1]');
            $this->logger->debug("lastDate -> {$lastDate}");

            if (!empty($lastDate)) {
                break;
            }
        }// for ($i = -1; ++$i < 3;)

        if (empty($lastDate)) {
            return;
        }

        $lastDate = $this->http->FindPreg("/(\d+\/\d+\/\d+)/", false, $lastDate);
        $this->logger->debug("lastDate -> {$lastDate}");
        $expirationDate = strtotime($lastDate);
        $this->logger->debug("expirationDate -> {$expirationDate}");

        if (!empty($expirationDate)) {
            $balance = (float) trim($this->Balance, '$'); //filter_var($this->Balance, FILTER_SANITIZE_NUMBER_FLOAT);
            $expireBalance = $balance >= 2 ? 2 : $balance;

            if ((float) $expireBalance > 0) {
                $this->SetProperty('ExpiringBalance', '$' . trim($expireBalance, '$'));
                $this->SetProperty('LastActivity', date('m/d/Y', strtotime($lastDate)));
                $this->SetExpirationDate(strtotime('+12 month', strtotime($lastDate)));
            }// if ((float) $expireBalance > 0)
        }// if (!empty($expirationDate))
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();

            $selenium->http->SetProxy($this->proxyReCaptcha());

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            $passwordXpath = '//input[@name = "password"]';
            $url = "https://www.rakuten.com/auth/getLogonForm.do";

            if ($this->AccountFields['Login2'] == 'Canada') {
                $url = "https://www.rakuten.ca/login?form";
                $passwordXpath = '//input[@id = "signin_password"]';
            }

            try {
                $selenium->http->GetURL($url);
                // provider bug fix
                if ($selenium->http->currentUrl() == 'https://www.rakuten.com') {
                    $selenium->http->GetURL($url);
                }
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $retry = true;

                return false;
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username" or @name = "fe_member_uname"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath($passwordXpath), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//input[contains(@value, "Sign In")] | //button[@id = "button-login"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            // captcha
            $iframe = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'susi-captcha-cont')]//iframe"), 10, false);

            if ($iframe) {
                $selenium->driver->executeScript("$('div.susi-captcha-cont iframe').remove();");
                $captcha = $this->parseRecaptcha();

                if ($captcha === false) {
                    return false;
                }
                $this->logger->notice("Remove iframe");
                $selenium->driver->executeScript('document.getElementsByName("g-recaptcha-response")[0].value = "' . $captcha . '";');
            }

            $button->click();

            sleep(3);
            $this->logger->debug("wait results");

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //a[contains(@id, "Point")]
                | //div[contains(@class, "auth-err")]
                | //span[contains(@class, "member-welcome-text")]
                | //div[contains(@class, "error-box success-message")]
            '), 5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);

                return true;
            }

            $result = true;
        } catch (Facebook\WebDriver\Exception\Internal\WebDriverCurlException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return $result;
    }

    private function ParseHistoryUSAorCanada($startDate): array
    {
        $this->logger->notice(__METHOD__);

        if (isset($startDate)) {
            $startDate = strtotime('-5 days', $startDate);
        }

        $result = array_merge([], $this->ParsePageHistoryUSAorCanada($startDate));

        /* // Pagination
        $nextPageURL = ($this->AccountFields['Login2'] == 'USA')
            ? 'https://www.rakuten.com/account/w-payment-rollback.htm'
            : ''; // Canada pagination URL

        $paymentIDs = $this->http->FindPreg('#<select id="cb-paid-new"[^>]*>(.+)</select>#s');
        if (is_null($paymentIDs)) return $result;
        $paymentIDs = $this->http->FindPregAll('/<option value="(\d+)/', $paymentIDs);

        if (empty($paymentIDs)
            || count($paymentIDs) < 3
            || empty($paymentIDs[0])
            || empty($paymentIDs[1])
        ) {
            return $result;
        }
        unset($paymentIDs[0], $paymentIDs[1]);

        foreach ($paymentIDs as $page) {
            $this->http->PostURL("$nextPageURL?paymentid=$page&isCash=1&paidDropAjax=true&type=points", '');
            $result = array_merge($result, $this->ParsePageHistoryUSAorCanada($startDate));
        }
        */

        return $result;
    }

    private function ParsePageHistoryUSAorCanada($startDate): array
    {
        $this->logger->notice(__METHOD__);
        $currency = ($this->AccountFields['Login2'] == 'USA') ? 'USD' : 'CAD';
        $result = [];
        $rows = $this->http->XPath->query('//tbody[@class = "datatable"]/tr[not(contains(@class, "div-expander"))]');
        $this->logger->debug("Total {$rows->length} history items were found (including 'Pending' transactions)");

        foreach ($rows as $row) {
            $pointsOrCashback = $this->http->FindSingleNode('td/span[contains(@class, "discountCol")]', $row);

            if ($pointsOrCashback === 'Pending') {
                continue;
            }

            $postingDateStr = $this->http->FindSingleNode('td/span[@class = "acct-date"]', $row);

            if (is_null($pointsOrCashback)
                || is_null($postingDateStr)
                || !strtotime($postingDateStr)
            ) {
                return $result;
            }

            $postingDateUnix = strtotime($postingDateStr);

            if (isset($startDate) && $postingDateUnix < $startDate) {
                $this->logger->notice("break at date $postingDateStr ($postingDateUnix)");

                return $result;
            }

            $properties = [
                'Date'        => $postingDateUnix,
                'Description' => $this->http->FindSingleNode('td/span[@class = "acct-store"]/a', $row),
                'Amount'      => $this->http->FindSingleNode('td/span[@class = "acct"]', $row),
                'Currency'    => $currency,
            ];

            if (str_contains($pointsOrCashback, '$')) {
                $properties['Cash Back'] = $pointsOrCashback;
            } else {
                $properties['Points'] = $pointsOrCashback;
            }
            $result[] = $properties;
        }

        return $result;
    }

    private function ParseHistoryUKorGermany($startDate): array
    {
        $this->logger->notice(__METHOD__);

        if (!is_null($this->http->FindSingleNode('//div[starts-with(@class, "transaction-table_transactionTable__noResults")]'))) {
            $this->sendNotification('refs #21956 history not empty // BS');
        }

        return [];
    }
}
