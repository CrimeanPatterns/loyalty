<?php

// refs #1780

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBoots extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        ""        => "Select region",
        "Ireland" => "Ireland",
        "UK"      => "United Kingdom",
    ];

//    function GetRedirectParams($targetURL = NULL){
//        $arg = parent::GetRedirectParams($targetURL);
//        $arg['NoCookieURL'] = true;
//        switch ($this->AccountFields['Login2']) {
//            case 'Ireland':
//                $arg['SuccessURL'] = "https://www.boots.ie/webapp/wcs/stores/servlet/ADCRegistrationFormCRMView?storeId=10552&langId=-1&catalogId=19552";
//                break;
//            case 'UK':
//            default:
//                $arg['SuccessURL'] = 'https://www.boots.com/webapp/wcs/stores/servlet/ADCRegistrationFormCRMView?storeId=10052';
//                break;
//        }// switch ($this->AccountFields['Login2'])
//        return $arg;
//    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptchaVultr());
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        $fields["Login2"]["Options"] = $this->regionOptions;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $url = 'https://www.boots.ie/LogonForm?catalogId=28502&myAcctMain=1&langId=-1&storeId=11353';

                break;

            case 'UK':
            default:
                $url = "https://www.boots.com/LogonForm?catalogId=28501&myAcctMain=1&langId=-1&storeId=11352";

                break;
        }// switch ($this->AccountFields['Login2'])

        $arg["RedirectURL"] = $url;
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $url = "https://www.boots.ie/AjaxLogonForm?myAcctMain=1&catalogId=28502&langId=-1&storeId=11353";

                break;

            case 'UK':
            default:
                $url = "https://www.boots.com/MyAdvantageCardHomeView?catalogId=28501&storeId=11352&langId=-1";

                break;
        }// switch ($this->AccountFields['Login2'])
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
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid e-mail address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $url = 'https://www.boots.ie/LogonForm?catalogId=28502&myAcctMain=1&langId=-1&storeId=11353';
                $domain = "ie";
                $store = '11353';
                $recaptchaEterprise = '6LdgDZcpAAAAAOgdYGkWQmOHFAWa3WS5d_upCwly';

                break;

            case 'UK':
            default:
                $url = "https://www.boots.com/LogonForm?catalogId=28501&myAcctMain=1&langId=-1&storeId=11352";
                $store = '11352';
                $domain = "com";
                $recaptchaEterprise = '6Lc3DpcpAAAAAIxsPVlWZMOf3YYxK5leUcay8nx5';

                break;
        }// switch ($this->AccountFields['Login2'])

        $this->http->GetURL($url);
        $this->incapsula();
        $this->queueCaptcha($domain, $url);

        if (!$this->http->ParseForm("Logon")) {
            $this->incapsula();
            $this->queueCaptcha($domain, $url);
        }

        if (!$this->http->ParseForm("Logon")) {
            $this->incapsula();
            $this->queueCaptcha($domain, $url);
        }

        if (!$this->http->ParseForm("Logon")) {
            // incapsula workaround
            if (
                $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
                || $this->http->FindPreg('/^\{"isVerified":false\}$/')
            ) {
                throw new CheckRetryNeededException(3, 3);
            }

            return $this->checkErrors();
        }
//        $this->http->FormURL = 'https://www.boots.' . $domain . '/LoginRequestDispatcher';
        $this->http->FormURL = 'https://www.boots.' . $domain . '/webapp/wcs/stores/servlet/BootsCiamsLogon';
        $this->http->SetInputValue("logonId", $this->AccountFields['Login']);
        $this->http->SetInputValue("logonPassword", $this->AccountFields['Pass']);
        $this->http->SetInputValue("rememberMe", "true");

        $apikey = $this->http->FindPreg("/apikey=([^\&\"\']+)/");

        if (!$apikey) {
            return false;
        }

        $context = "R2162263861";
        $sdk = "js_latest";
        $callback = "gigya.callback";
        $pageURL = "https://www.boots.{$domain}/UserRegistrationForm?editRegistration=Y&catalogId=28501&langId=-1&storeId={$store}&userRegistrationStyle=strong";

        $this->State['pageURL'] = $pageURL;
        $this->State['form'] = $this->http->Form;

        // for cookies
        $this->http->GetURL("https://account.boots.{$domain}/accounts.webSdkBootstrap?apiKey={$apikey}&pageURL={$pageURL}format=jsonp&callback={$callback}&context={$context}");
        $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 0);

        $this->http->Form = [];
        $this->http->FormURL = "https://account.boots.{$domain}/accounts.login?context={$context}&saveResponseID={$context}";
        $this->http->SetInputValue('loginID', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('sessionExpiration', "-1");
        $this->http->SetInputValue('lang', "en");
        $this->http->SetInputValue('targetEnv', "jssdk");
        $this->http->SetInputValue('include', "profile,data,emails,subscriptions,preferences,");
        $this->http->SetInputValue('includeUserInfo', "true");
        $this->http->SetInputValue('loginMode', "standard");
        $this->http->SetInputValue('APIKey', $apikey);
        $this->http->SetInputValue('source', "showScreenSet");
        $this->http->SetInputValue('sdk', $sdk);
        $this->http->SetInputValue('authMode', "cookie");
        $this->http->SetInputValue('pageURL', $pageURL);
        $this->http->SetInputValue('format', "jsonp");
        $this->http->SetInputValue('callback', $callback);
        $this->http->SetInputValue('context', $context);
        $this->http->SetInputValue('utf8', "✓");

        $captcha = $this->parseReCaptchaEnterprise($recaptchaEterprise);

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('captchaToken', $captcha);
        $this->http->SetInputValue('captchaType', "reCaptchaEnterpriseScore");

        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;

        $query = http_build_query([
            'APIKey'         => $apikey,
            'saveResponseID' => $context,
            "pageURL"        => $pageURL,
            'noAuth'         => 'true',
            'sdk'            => $sdk,
            'format'         => 'jsonp',
            'callback'       => $callback,
            'context'        => $context,
        ]);
        $this->http->GetURL("https://account.boots.{$domain}/socialize.getSavedResponse?{$query}");
        $response = $this->http->JsonLog($this->http->FindPreg("/{$callback}\((.+?)\);/s"));

        if (
            !isset($response->sessionInfo->login_token)
            || !isset($response->UIDSignature)
            || !isset($response->UID)
        ) {
            if ($message = $this->http->FindPreg('/"errorDetails":\s*"Login Failed Captcha Required"/')) {
                throw new CheckRetryNeededException(3, 5);
            }

            if (
                isset($response->errorDetails, $response->errorMessage)
                && in_array($response->errorDetails, ['Pending Two-Factor Authentication', 'Pending Two-Factor Registration'])
                && in_array($response->errorMessage, ['Account Pending TFA Verification', 'Account Pending TFA Registration'])
            ) {
                if (!isset($response->regToken)) {
                    $this->logger->error("regToken not found");

                    return false;
                }

                $param = [];
                $param['regToken'] = $response->regToken;
                $param['APIKey'] = $apikey;
                $param['source'] = "showScreenSet";
                $param['sdk'] = $sdk;
                $param['pageURL'] = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/BootsLogonForm?myAcctMain=1&catalogId=28501&storeId=11352&langId=-1&krypto=Y3f%2FdIB%2FWsMO%2FhGiALa84CNvo3c07v6WeSXpQqa3KMzSvct7m1hARerbXOCPeL28Ug7viqjK2SSug5nojP8KUAacf4QwuF9fdYeAjfXZZO9mo8IiTccwgc9AHmR%2BwPS1UWqSYhcDv51YiraRUi20qXKI31Ct1lUxcS501ehgDMk%3D";
                $param['format'] = "json";
                $this->http->GetURL("https://account.boots.{$domain}/accounts.tfa.getProviders?" . http_build_query($param));
                $this->http->JsonLog();
                $mode = 'verify';

                if (strstr($response->errorMessage, 'Registration')) {
                    $mode = 'register';
                }

                if ($this->parseQuestion($response, $apikey, $mode)) {
                    return false;
                }
            }

            // The email address and/or password you entered has not been recognised.
            if ($message = $this->http->FindPreg("/\"errorDetails\": \"(?:invalid loginID or password|Old Password Used)\"/")) {
                throw new CheckException("The email address and/or password you entered has not been recognised.", ACCOUNT_INVALID_PASSWORD);
            }
            /*
             * You've entered an incorrect password too many times.
             * To reset your password, please click 'forgotten your password?' or call our Customer Service Centre on 0345 609 0055.
             */
            if ($message = $this->http->FindPreg("/\"errorDetails\": \"(Account temporarily locked out)\"/")) {
                throw new CheckException("Account locked. You've entered an incorrect password too many times.", ACCOUNT_LOCKOUT); /*review*/
            }

            return false;
        }

        return $this->finalAuthRequests($response);
    }

    public function finalAuthRequests($response)
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $domain = "ie";

                break;

            case 'UK':
            default:
                $domain = "com";

                break;
        }// switch ($this->AccountFields['Login2'])

        $form = $this->State['form'];
        $apikey = $this->State['APIKey'];
        $context = "R2162263861";
        $sdk = "js_latest";
        $callback = "gigya.callback";

        $login_token = $response->sessionInfo->login_token;
        $this->http->RetryCount = 0;
        $this->http->setCookie("glt_{$apikey}", $response->sessionInfo->login_token, ".boots.{$domain}");

        $query = http_build_query([
            "fields"      => "UID",
            "expiration"  => "30",
            "APIKey"      => $apikey,
            "sdk"         => $sdk,
            "login_token" => $login_token,
            "authMode"    => "cookie",
            "pageURL"     => $this->State['pageURL'],
            "format"      => "jsonp",
            "callback"    => $callback,
            "context"     => $context,
        ]);
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Referer"         => $this->State['pageURL'],
        ];
        $this->http->GetURL("https://account.boots.{$domain}/accounts.getJWT?{$query}", $headers);
        $responseIdToken = $this->http->JsonLog($this->http->FindPreg("/{$callback}\((.+?)\);/s"));

        if (!isset($responseIdToken->id_token)) {
            $this->logger->error("id_token not found");

            return false;
        }
        $idToken = $responseIdToken->id_token;

        $this->http->FormURL = 'https://www.boots.' . $domain . '/webapp/wcs/stores/servlet/BootsCiamsLogon';
        $this->http->Form = $form;
        $this->http->SetInputValue('uidSignature', $response->UIDSignature);
        $this->http->SetInputValue('uid', $response->UID);
        $this->http->SetInputValue('signatureTimestamp', $response->signatureTimestamp);
        $this->http->SetInputValue('idToken', $idToken);

        return true;
    }

    public function ProcessStep($step)
    {
        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $domain = "ie";
                $store = '11353';

                break;

            case 'UK':
            default:
                $store = '11352';
                $domain = "com";

                break;
        }// switch ($this->AccountFields['Login2'])

        if ($step == 'QuestionPhone') {
            $this->State['params']['phone'] = $this->Answers[$this->Question];
            $this->sendVerificationCode($this->Answers[$this->Question]);

            return false;
        }

        $param = [];
        $param['gigyaAssertion'] = $this->State['gigyaAssertion'];
        $param['phvToken'] = $this->State['phvToken'];
        $param['code'] = $this->Answers[$this->Question];
        $param['regToken'] = $this->State['regToken'];
        $param['APIKey'] = $this->State['APIKey'];
        $param['source'] = "showScreenSet";
        $param['sdk'] = "js_latest";
        $param['pageURL'] = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/BootsLogonForm?myAcctMain=1&catalogId=28501&storeId={$store}&langId=-1&krypto=Y3f%2FdIB%2FWsMO%2FhGiALa84CNvo3c07v6WeSXpQqa3KMzSvct7m1hARerbXOCPeL28Ug7viqjK2SSug5nojP8KUAacf4QwuF9fdYeAjfXZZO9mo8IiTccwgc9AHmR%2BwPS1UWqSYhcDv51YiraRUi20qXKI31Ct1lUxcS501ehgDMk%3D";
        $param['sdkBuild'] = "12833";
        $param['format'] = "json";
        $this->http->GetURL("https://account.boots.{$domain}/accounts.tfa.phone.completeVerification?" . http_build_query($param));
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
        $param['APIKey'] = $this->State['APIKey'];
        $param['source'] = "showScreenSet";
        $param['sdk'] = "js_latest";
        $param['pageURL'] = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/BootsLogonForm?myAcctMain=1&catalogId=28501&storeId={$store}&langId=-1&krypto=Y3f%2FdIB%2FWsMO%2FhGiALa84CNvo3c07v6WeSXpQqa3KMzSvct7m1hARerbXOCPeL28Ug7viqjK2SSug5nojP8KUAacf4QwuF9fdYeAjfXZZO9mo8IiTccwgc9AHmR%2BwPS1UWqSYhcDv51YiraRUi20qXKI31Ct1lUxcS501ehgDMk%3D";
        $param['format'] = "json";
        $this->http->GetURL("https://account.boots.{$domain}/accounts.tfa.finalizeTFA?" . http_build_query($param));
        $response = $this->http->JsonLog();

        // Wrong verification code
        if (isset($response->errorMessage) && $response->errorMessage == 'Invalid parameter value') {
            $this->AskQuestion($this->Question, 'Wrong verification code', "Question");

            return false;
        }

        sleep(1);

        $param = [];
        $param['regToken'] = $this->State['regToken'];
        $param['targetEnv'] = "jssdk";
        $param['include'] = "profile,data,emails,subscriptions,preferences,";
        $param['includeUserInfo'] = 'true';
        $param['APIKey'] = $this->State['APIKey'];
        $param['source'] = "showScreenSet";
        $param['sdk'] = "js_latest";
        $param['pageURL'] = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/BootsLogonForm?myAcctMain=1&catalogId=28501&storeId={$store}&langId=-1&krypto=Y3f%2FdIB%2FWsMO%2FhGiALa84CNvo3c07v6WeSXpQqa3KMzSvct7m1hARerbXOCPeL28Ug7viqjK2SSug5nojP8KUAacf4QwuF9fdYeAjfXZZO9mo8IiTccwgc9AHmR%2BwPS1UWqSYhcDv51YiraRUi20qXKI31Ct1lUxcS501ehgDMk%3D";
        $param['format'] = "json";
        $this->http->GetURL("https://account.boots.{$domain}/accounts.finalizeRegistration?" . http_build_query($param));
        $finalizeRegistration = $this->http->JsonLog();

        if (!isset($finalizeRegistration->UIDSignature)) {
            $this->logger->error("UIDSignature not found");

            return false;
        }

        $this->finalAuthRequests($finalizeRegistration);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->incapsula();

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // fixed provider bug, Sign out link sometimes not loading
        $http2 = clone $this->http;

        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $url = "https://www.boots.ie/ADCAccountSummary?catalogId=28502&langId=-1&storeId=11353";
                $domain = "ie";

                break;

            case 'UK':
            default:
                $url = "https://www.boots.com/ADCAccountSummary?catalogId=28501&langId=-1&storeId=11352";
                $domain = "com";

                break;
        }// switch ($this->AccountFields['Login2'])
        $http2->GetURL($url);

        if ($http2->FindSingleNode("(//a[contains(text(), 'Sign Out')])[1]")
            || $http2->FindSingleNode("//p[contains(text(), 'Card number')]/following-sibling::p[1]")
        ) {
            return true;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Hello. We are busy updating our site to make it an even better experience for you.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Hello. We are busy updating our site to make it an even better experience for you.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're busy updating our website right now to make it even better.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re busy updating our website right now to make it even better.")]
                | //p[contains(text(), "Thanks for your patience, we’re busy upgrading our site right now but once we’re back up")]
                | //p[contains(text(), "Sorry, our website is temporarily unavailable. Please try again later when you\'ll be able to shop more great offers.")]
                | //p[contains(text(), "Sorry, our website is temporarily unavailable.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently busy updating our website.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently busy updating our website.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're really busy right now as so many of you are shopping our amazing deals.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re really busy right now as so many of you are shopping our amazing deals.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Boots.com is currently unavailable as we're busy making changes to the website.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Boots.com is currently unavailable as we\'re busy making changes to the website.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're busy right now getting ready for our amazing sale. Come back soon and you'll be able to shop great savings.
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'ll be back soon")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Boots.com is currently experiencing on unusually high volume of requests
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Temporarily Unavailable')]")) {
            throw new CheckException("Boots.com is currently unavailable", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# We have encountered a problem processing this last request
        if ($message = $this->http->FindPreg("/(We have encountered a problem processing this last request\.)/ims")) {
            throw new CheckException("We have encountered a problem processing this last request. Please try again
            later.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# We're sorry. Unfortunately we couldn't find the page you requested
        if ($message = $this->http->FindSingleNode('//img[contains(@alt, "We\'re sorry. Unfortunately we couldn\'t find the page you requested")]/@alt')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Boots.com is temporarily unavailable
        if ($this->http->FindSingleNode("//img[contains(@src, 'http://www.boots-holding.co.uk/holding-img-2.0.jpg')]/@src")
            || $this->http->FindSingleNode("//img[contains(@src, 'http://www.holding.boots.com/holding-img-GB.jpg')]/@src")) {
            throw new CheckException("Boots.com is temporarily unavailable", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindSingleNode("//img[contains(@src, 'http://www.holding.boots.com/holding-img-GB-Maintenance.jpg')]/@src")) {
            throw new CheckException("We're currently running planned maintenance across the site.", ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently updating our website
        if ($this->http->FindSingleNode("//img[contains(@src, 'http://www.holding.boots.com/holding-new-UK.jpg')]/@src")) {
            throw new CheckException("We're currently updating our website.", ACCOUNT_PROVIDER_ERROR);
        }
        // Generic System Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Generic System Error') or contains(text(), 'Error Page Exception')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, we can\'t find the page you\'re looking for")]')
            || strstr($this->http->currentUrl(), 'http://www.boots.com/webapp/wcs/stores/servlet/TopCategoriesDisplay')) {
            throw new CheckRetryNeededException(2, 7);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->incapsula();

        if ($message = $this->http->FindSingleNode('//div[@class="messageerror"]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/var errStr = '(The following command exception has occurred during processing: &#034;java.lang.ClassCastException: java.lang.Long incompatible with java.lang.String&#034;.)';/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // fixed provider bug, Sign out link sometimes not loading
        $http2 = clone $this->http;

        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $url = "https://www.boots.ie/ADCAccountSummary?catalogId=28502&langId=-1&storeId=11353";
                $domain = "ie";

                break;

            case 'UK':
            default:
                $url = "https://www.boots.com/ADCAccountSummary?catalogId=28501&langId=-1&storeId=11352";
                $domain = "com";

                break;
        }// switch ($this->AccountFields['Login2'])
        $http2->GetURL($url);

        if ($http2->FindSingleNode("(//a[contains(text(), 'Sign Out')])[1]")
            || $http2->FindSingleNode("//p[contains(text(), 'Card number')]/following-sibling::p[1]")
        ) {
            return true;
        }

        // no card
        if (
            $this->http->FindSingleNode('//span[contains(text(), "sign up to Advantage Card")]')
            && (
                $http2->FindSingleNode("//span[contains(normalize-space(text()), 'Error information is listed below. For further details, increase the logging for your WebSphere Commerce system, and check the log file.')]")
                || $http2->currentUrl() == 'https://www.boots.ie/AjaxLogonForm?myAcctMain=1&catalogId=28502&langId=-1&storeId=11353'
            )
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // The email address and/or password you entered has not been recognised.
        if ($message = $this->http->FindPreg("/The email address and\/or password you entered has not been recognised\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Technical Issue
        if (
            $http2->FindPreg("/eStoreProductOverlay\('gigyaUnavailableLogin_overlay'\)\;/")
            && ($message = $http2->FindSingleNode('//div[@id = "gigyaUnavailableLogin_overlay"]//p[contains(text(), "Sorry, we\'re currently having a technical issue and are unable to log you into your account. You can place Retail orders as a guest, or for a Pharmacy order, please try logging in later.")]'))
        ) {
            $this->logger->error("request has been blocked");
            $this->DebugInfo = 'request has been blocked';

            return false;
            /*
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            */
        }

        // Account locked
        if (
            $message = $this->http->FindSingleNode("//div[@id = 'acount_locked_overlay' and not(contains(@style, 'display: none;'))]//p[contains(text(), 'Account locked')]")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // You have requested a new password for this account.
        if ($message = $this->http->FindSingleNode("//a[contains(text(), 'You have requested a new password for this account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, something's gone wrong
        if ($message = $this->http->FindSingleNode('//h5[contains(text(), "Sorry, something\'s gone wrong")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // try to catch strange error
        $http2 = $this->http;

        if ($http2->ParseForm("Logon")) {
//            $http2->FormURL = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/LoginRequestDispatcher";
//            $http2->Form['logonId'] = $this->AccountFields['Login'];
//            $http2->Form['logonPassword'] =  $this->AccountFields['Pass'];
//            $http2->PostForm();
            // As an Advantage Card holder, if you wish to manage your Advantage Card account online you must first sign in or register for Boots.com
            if ($message = $http2->FindSingleNode("//div[contains(text(), 'As an Advantage Card holder, if you wish to manage your Advantage Card account online you must first sign in or register for Boots.com')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // no errors, no session
            $errorMessage = $http2->FindSingleNode("//div[@id = 'errorMessage']");
            $this->logger->debug($errorMessage);
            $this->logger->debug($http2->FindSingleNode("//div[@id = 'estore_signin']"));

            if ((!$errorMessage || $errorMessage == 'It seems that this form contains errors please check any fields marked in red')
                && !$http2->FindSingleNode("//div[@id = 'estore_signin']")
                && ($http2->FindSingleNode('//input[@name = "logonId"]/@onblur', null, true, "/this.placeholder = '{$this->AccountFields['Login']}'/")
                    || $http2->FindSingleNode('//input[@name = "logonId"]/@onblur', null, true, "/this.placeholder = 'email@address.com'/"))) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($http2->ParseForm("Logon"))

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h2[contains(@class, "my_account_header")]', null, true, "/hello\s*([^<]+)/")));

        // Get URL - Advantage Card account
        $url = "/ADCAccountSummary";

        if ($this->AccountFields['Login2'] == 'UK') {
            $url = "ADCAccountSummary?catalogId=28501&storeId=11352&langId=-1";
        }
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);

        // Balance - Your points balance is
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'balance')]/following-sibling::p[1]"));
        // Advantage Card number
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Card number')]/following-sibling::p[1]"));
        // Date points last updated on
        $this->SetProperty("PointsLastUpdated", $this->http->FindSingleNode("//p[contains(text(), 'Last updated')]/following-sibling::p[1]"));
        // This is equivalent to
        $this->SetProperty("BalanceEquivalent", $this->http->FindSingleNode("//p[contains(text(), 'Equivalent')]/following-sibling::p[1]"));
        // Expiration date  // refs 15561, 21220
        $exp = strtotime($this->ModifyDateFormat($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Last updated')]/following::text()[normalize-space(.)!=''][1]")));

        if ($exp !== false) {
            $this->SetExpirationDate(strtotime("+1 years", $exp));
        }
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'UK';
        }

        return $region;
    }

    protected function queueCaptcha($domain, $url)
    {
        $this->logger->notice(__METHOD__);

        if (!stristr($this->http->currentUrl(), "https://queue.boots.{$domain}/")) {
            return false;
        }
        $key = $this->http->FindPreg("/captchaPublicKey:\s*'([^\']+)/");
        $captchaVerifyEndpoint = $this->http->FindPreg("/challengeVerifyEndpoint:\s*'([^\']+)/");
        $customerId = $this->http->FindPreg("/customerId:\s*'([^\']+)/");
        $eventId = $this->http->FindPreg("/eventId:\s*'([^\']+)/");
        $culture = $this->http->FindPreg("/culture:\s*'([^\']+)/");

        if (!$captchaVerifyEndpoint || !$customerId || !$eventId || !$culture) {
            $this->logger->error("queue captcha parameters not parsed");

            return false;
        }

        $this->http->PostURL("https://queue.boots.{$domain}/challengeapi/queueitcaptcha/challenge/" . strtolower($culture), "");
        $response = $this->http->JsonLog();

        if (!isset($response->imageBase64) || !isset($response->key)) {
            $this->logger->error("queue captcha parameters not found");

            return false;
        }

        $captcha = $this->parseCaptcha($response->imageBase64);

        if ($captcha === false) {
            $this->logger->error("queue captcha: recognizing failed");

            return false;
        }

        $data = [
            //            "challengeType" => "recaptcha",
            "challengeType" => "botdetect",
            //            "sessionId"     => $captcha,
            "sessionId"     => base64_encode(json_encode([
                "stats"     => [
                    "userAgent"      => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36",
                    "screen"         => "1536 x 960",
                    "browser"        => "Chrome",
                    "browserVersion" => "97.0",
                    "isMobile"       => false,
                    "os"             => "Mac OS X",
                    "osVersion"      => "10.15",
                    "cookiesEnabled" => true,
                    "tries"          => 1,
                    "duration"       => 15059,
                ],
                "sessionId" => $response->sessionId,
                "meta"      => $response->meta,
                "solution"  => $captcha,
                "key"       => $response->key,
            ])),
            "customerId"    => $customerId,
            "eventId"       => $eventId,
            "version"       => 6,
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Accept-Language"  => "en-US,en;q=0.5",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
            "Origin"           => "https://queue.boots.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->NormalizeURL($captchaVerifyEndpoint);
        $this->http->PostURL($captchaVerifyEndpoint, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $isVerified = $response->isVerified ?? null;

        if ($isVerified != 'true') {
            $this->logger->error("queue captcha fail");

            if ($this->attempt == 0) {
                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }
        $params = [
            'c'   => $customerId,
            'e'   => $eventId,
            't'   => $url,
            'cid' => "en-GB",
            'scv' => json_encode([
                "sessionId"     => $response->sessionInfo->sessionId,
                "timestamp"     => $response->sessionInfo->timestamp,
                "checksum"      => $response->sessionInfo->checksum,
                "sourceIp"      => $response->sessionInfo->sourceIp,
                "challengeType" => $response->sessionInfo->challengeType,
                "version"       => $response->sessionInfo->version,
            ]),
        ];
        $this->http->GetURL("https://queue.boots.{$domain}/?" . http_build_query($params)); //todo

        return true;
    }

    protected function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");

        if (!$formURL) {
            return false;
        }

        if ($key = $this->http->FindSingleNode("//div[@class = 'form_container']//div[@class = 'h-captcha']/@data-sitekey")) {
            $captcha = $this->parseReCaptcha($key, "hcaptcha");
        } else {
            $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");
            $captcha = $this->parseReCaptcha($key);
        }

        if ($captcha === false) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        sleep(2);
        $this->http->GetURL($referer);

        if ($this->http->Response['code'] == 503) {
            $this->http->GetURL($this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost());
            sleep(1);
            $this->http->GetURL($referer);
        }

        return true;
    }

    protected function parseReCaptcha($key, $method = 'userrecaptcha')
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer();
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 160;
        $parameters = [
            "method"  => $method,
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);

        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $captcha;
    }

    protected function parseReCaptchaEnterprise($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.3,
            //            "pageAction"   => "login",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    protected function parseCaptcha($imageBase64)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("decode image data and save image in file");
        // decode image data and save image in file
        $imageData = base64_decode($imageBase64);
        $image = imagecreatefromstring($imageData);
        $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".jpeg";
        imagejpeg($image, $file);

        if (!isset($file)) {
            return false;
        }
        $this->logger->debug("file: " . $file);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Sign Out')])[1]")
            || $this->http->FindSingleNode("//p[contains(text(), 'Card number')]/following-sibling::p[1]")
            || $this->http->FindSingleNode("//p[@id = 'advantageCardDetails']")
        ) {
            return true;
        }

        return false;
    }

    private function parseQuestion($response, $apikey, $mode)
    {
        $this->logger->notice(__METHOD__);
        $this->State['regToken'] = $response->regToken;
        $this->State['Properties'] = $this->Properties;

        $this->http->RetryCount = 0;

        switch ($this->AccountFields['Login2']) {
            case 'Ireland':
                $domain = "ie";
                $store = '11353';

                break;

            case 'UK':
            default:
                $store = '11352';
                $domain = "com";

                break;
        }// switch ($this->AccountFields['Login2'])

        $this->logger->debug("init 2fa");
        $param = [];
        $param['provider'] = 'gigyaPhone';
        $param['mode'] = $mode;
        $param['regToken'] = $this->State['regToken'];
        $param['APIKey'] = $apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = "js_latest";
        $param['pageURL'] = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/BootsLogonForm?myAcctMain=1&catalogId=28501&storeId={$store}&langId=-1&krypto=Y3f%2FdIB%2FWsMO%2FhGiALa84CNvo3c07v6WeSXpQqa3KMzSvct7m1hARerbXOCPeL28Ug7viqjK2SSug5nojP8KUAacf4QwuF9fdYeAjfXZZO9mo8IiTccwgc9AHmR%2BwPS1UWqSYhcDv51YiraRUi20qXKI31Ct1lUxcS501ehgDMk%3D";
        $param['format'] = "json";
        $this->http->GetURL("https://account.boots.{$domain}/accounts.tfa.initTFA?" . http_build_query($param));
        $initTFA = $this->http->JsonLog();

        if (!isset($initTFA->gigyaAssertion)) {
            $this->logger->error("gigyaAssertion mot found");

            return false;
        }

        $this->logger->debug("get phone info");
        $param = [];
        $param['gigyaAssertion'] = $initTFA->gigyaAssertion;
        $param['APIKey'] = $apikey;
        $param['source'] = "showScreenSet";
        $param['sdk'] = "js_latest";
        $param['pageURL'] = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/BootsLogonForm?myAcctMain=1&catalogId=28501&storeId={$store}&langId=-1&krypto=Y3f%2FdIB%2FWsMO%2FhGiALa84CNvo3c07v6WeSXpQqa3KMzSvct7m1hARerbXOCPeL28Ug7viqjK2SSug5nojP8KUAacf4QwuF9fdYeAjfXZZO9mo8IiTccwgc9AHmR%2BwPS1UWqSYhcDv51YiraRUi20qXKI31Ct1lUxcS501ehgDMk%3D";
        $param['sdkBuild'] = "12833";
        $param['format'] = "json";
        $this->http->GetURL("https://account.boots.{$domain}/accounts.tfa.phone.getRegisteredPhoneNumbers?" . http_build_query($param));
        $getPhones = $this->http->JsonLog();

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->logger->debug("send Verification Code");
        $param = [];

        $param['gigyaAssertion'] = $initTFA->gigyaAssertion;
        $param['lang'] = 'en';
        $param['regToken'] = $this->State['regToken'];
        $param['source'] = "showScreenSet";
        $param['APIKey'] = $apikey;
        $param['method'] = "sms";
        $param['sdk'] = "js_latest";
        $param['pageURL'] = "https://www.boots.{$domain}/webapp/wcs/stores/servlet/BootsLogonForm?myAcctMain=1&catalogId=28501&storeId={$store}&langId=-1&krypto=Y3f%2FdIB%2FWsMO%2FhGiALa84CNvo3c07v6WeSXpQqa3KMzSvct7m1hARerbXOCPeL28Ug7viqjK2SSug5nojP8KUAacf4QwuF9fdYeAjfXZZO9mo8IiTccwgc9AHmR%2BwPS1UWqSYhcDv51YiraRUi20qXKI31Ct1lUxcS501ehgDMk%3D";
        $param['format'] = "json";

        $this->State['params'] = $param;
        $this->State['APIKey'] = $apikey;
        $this->State['gigyaAssertion'] = $initTFA->gigyaAssertion;

        if ($getPhones->phones === []) {
            $this->Question = "To keep your account secure, a verification code will be sent via text to the number provided below.";
            $this->Step = "QuestionPhone";
            $this->ErrorCode = ACCOUNT_QUESTION;

            return false;
        }

        $this->State['params']['phoneID'] = $getPhones->phones[0]->id;

        return $this->sendVerificationCode($getPhones->phones[0]->obfuscated);
    }

    private function sendVerificationCode($phone)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://account.boots.com/accounts.tfa.phone.sendVerificationCode?" . http_build_query($this->State['params']));
        $sendVerificationCode = $this->http->JsonLog();
        $errorDetails = $sendVerificationCode->errorDetails ?? null;

        if ($errorDetails) {
            if (
                in_array($errorDetails, [
                    'Given phone is not in a valid format',
                    'PhoneNumber is missing country code',
                ])
            ) {
                $this->AskQuestion("To keep your account secure, a verification code will be sent via text to the number provided below.", "An error has occurred, please try again later", "QuestionPhone");

                return false;
            }

            $this->DebugInfo = $errorDetails;

            return false;
        }

        $this->State['phvToken'] = $sendVerificationCode->phvToken;

        $this->Question = "We have sent a verification code to the phone: {$phone}. It will expire in 5 minutes.";
        $this->Step = "Question";
        $this->ErrorCode = ACCOUNT_QUESTION;

        return true;
    }
}
