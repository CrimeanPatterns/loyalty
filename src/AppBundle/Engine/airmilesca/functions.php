<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirmilesca extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://services.api.airmiles.ca/services/airmiles/sling/no-cache/profile?language=ENGLISH';
    /** @var CaptchaRecognizer */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
        // $this->http->SetProxy($this->proxyDOP()); // The server encountered an internal error or misconfiguration and was unable to complete your request.
    }

    public function IsLoggedIn()
    {
        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.airmiles.ca/en/login", [], 30);
        /*
        $this->http->GetURL("https://www.airmiles.ca/content/airmiles/ca/en/fragments/blue-section-guest1/jcr:content/root/responsivegrid/bluesection.model.json", [], 30);
        $response = $this->http->JsonLog(null, 3, false, 'actions');
        $actions = $response->Small->actions ?? [];

        foreach ($actions as $action) {
            if ($action->text == 'Sign in') {
                $this->http->GetURL("$action->url", [], 30);

                break;
            }
        }
        */
        $this->http->RetryCount = 2;
        $clientId = $this->http->FindPreg('/client=(.+?)&/', false, $this->http->currentUrl());
        $state = $this->http->FindPreg('/state=(.+?)&/', false, $this->http->currentUrl());
        $redirect_uri = $this->http->FindPreg('/redirect_uri=(.+?)&/', false, $this->http->currentUrl());
        $connection = $this->http->FindPreg('/connection=([^&]+)/', false, $this->http->currentUrl());

        if (!isset($clientId, $state, $redirect_uri, $connection)) {
            return $this->checkErrors();
        }
        $captcha = null;

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            $connection = 'member-email-idp-recaptcha';
        }

        if (strstr($connection, 'idp-recaptcha')) {
            $captcha = $this->parseCaptcha('6LdhQd4ZAAAAALjx6VSEzBl47vrl4Y0nbrcIRN6u');

            if ($captcha === false) {
                return false;
            }
            $captcha = ' ' . $captcha;
        }

        $this->http->RetryCount = 0;
        $data = [
            "client_id"     => $clientId,
            "redirect_uri"  => urldecode($redirect_uri),
            "tenant"        => "airmiles-v2",
            "response_type" => "code",
            "scope"         => "memberbanner",
            "audience"      => "https://members.loyalty.com",
            "state"         => $state,
            "username"      => $this->AccountFields['Login'] . $captcha,
            "password"      => $this->AccountFields['Pass'],
            "connection"    => $connection,
        ];
        $headers = [
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTYuNCJ9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin'          => 'https://auth.airmiles.ca',
        ];
        $this->http->PostURL('https://oauth.airmiles.ca/usernamepassword/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $this->http->setMaxRedirects(10);
        $this->http->RetryCount = 0;

        if ($this->http->ParseForm("hiddenform")) {
            if ($message = $this->http->FindSingleNode('//h3[contains(text(), "Oops!, something went wrong")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->http->PostForm();
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $code = $response->code ?? null;
        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'ReCAPTCHA') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if ($message == 'UnsuccessfulAuthentication' && $code == 'InvalidUsernamePassword') {
                throw new CheckException("Oops! Invalid Collector Number/PIN combination. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'This login attempt has been blocked because the password you\'re using was previously disclosed through a data breach (not in this application)')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($message == 'InactiveMember' && $code == 'ValidationError') {
                throw new CheckException("Uh oh, this Collector Account has been closed. To become a new Collector, please go to airmiles.ca.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'IncompleteEnrollment' && $code == 'ValidationError') {
                throw new CheckException("The sign-in credentials you've entered do not match our records.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'AuthorizeError') {
                throw new CheckException("What you’ve entered does not match our records. You may not be set up to sign in with your email address.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'MergedMember' && $code == 'ValidationError') {
                throw new CheckException("Uh oh, this Collector Account has been closed.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'LockedMember') {
                throw new CheckException("Due to security reasons, your PIN has been locked.", ACCOUNT_LOCKOUT);
            }

            if (in_array($message, ['read ECONNRESET', 'SystemError']) && $code == 'UnknownError') {
                throw new CheckException("We're experiencing technical difficulties. Please check back shortly.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'SystemError') {
                throw new CheckRetryNeededException();
            }

            if ($message == "Request to Webtask exceeded allowed execution time") {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Access is allowed
//        $this->http->GetURL("https://www.airmiles.ca/arrow/Home?changeLocale=en_CA");
        if ($this->http->getCookieByName("atokid", "services.api.airmiles.ca", "/", true) && $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//title[contains(text(), "Something went wrong.")]')) {
            throw new CheckRetryNeededException(2, 3, $message);
        }

        // AccountID: 3534880
        if (strstr($this->http->currentUrl(), '&error_description=Please%20use%20password')) {
            throw new CheckException("This account has enabled sign-in with email. Please sign in using email address and password.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $name = ($response->personalDetails->firstName ?? null) . ' ' . ($response->personalDetails->lastName ?? null);
        $this->SetProperty("Name", beautifulName($name));
        // Collector Number
        $this->SetProperty('Number', $response->cardNumber ?? null);

        $this->getURLProxy("https://services.api.airmiles.ca/services/airmiles/sling/no-cache/member-banner");

        if (in_array($this->http->Response['code'], [403])) {
            $this->http->GetURL("https://services.api.airmiles.ca/v1/conversion/skip");
            $this->getURLProxy("https://services.api.airmiles.ca/services/airmiles/sling/no-cache/member-banner");
        }
        $response = $this->http->JsonLog();

        // provider bug fix
        if (
            $this->http->Response['code'] == 401
            && isset($response->ssoURL)
        ) {
            throw new CheckRetryNeededException(3, 5);
        }

        // Sub Accounts  // refs #4470

        $nodes = [
            'Cash Miles'  => 'cashBalance',
            'Dream Miles' => 'dreamBalance',
        ];
        $i = 0;

        foreach ($nodes as $key => $value) {
            $balance = $response->{$value} ?? null;
            $displayName = $key;

            if (isset($balance, $displayName) && ($balance != 0 || $i == 0)) {
                $this->AddSubAccount([
                    'Code'        => 'airmilesca' . str_replace([' ', 'ê'], ['', 'e'], $displayName),
                    'DisplayName' => $displayName,
                    'Balance'     => $balance,
                ]);
            }// if (isset($balance))
            else {
                $this->logger->notice("Skip -> {$displayName}: {$balance}");
            }
            $i++;
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (!empty($this->Properties['SubAccounts'])) {
            $this->SetBalanceNA();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//title[contains(text(), "We apologize, we are experiencing technical difficulties")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            $message = $this->http->FindSingleNode('//p[contains(@class, "page_title")]/following-sibling::p[1]');

            if ($this->http->FindPreg("/Until 06:00 on Aug 09 2020,we'll be making some improvements atairmiles\.ca to better serve you in the future\./", false, $message)) {
                $cleanedMessage = "Until 06:00 on Aug 09 2020, we'll be making some improvements at airmiles.ca to better serve you in the future.";

                throw new CheckException($cleanedMessage, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//p[contains(normalize-space(),"Until 06:00 on Dec 13 2020,we\'ll be making some improvements atairmiles.ca to better serve you in the future.In the meantime, check out what\'s new")]')) {
                $cleanedMessage = "Until 06:00 on Dec 13 2020, we'll be making some improvements at airmiles.ca to better serve you in the future.";

                throw new CheckException($cleanedMessage, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Status
        $status = '';

        switch ($response->tier) {
            case 'B':
                $status = 'Blue';

                break;

            case 'G':
                $status = 'Gold';

                break;

            case 'O':
                $status = 'Onyx®';

                break;

            default:
                $this->sendNotification("Unknown status: {$response->tier}");
        }
        $this->SetProperty('Status', $status);
        // Current status (until ...)
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode("//p[contains(@class, 'current-status-label')]", null, true, "/until\s*([^\)]+)/"));

        // You've collected: ... Miles this year
//		$this->http->GetURL("https://www.airmiles.ca/en/profile/transaction-history.html");
        $from = str_replace('+00:00', '.000Z', date('c', strtotime("-1 year -1 day"))); // 2019-12-14
        $to = str_replace('+00:00', '.000Z', date('c')); // 2020-12-15T18:59:59.999Z
        $this->http->GetURL("https://services.api.airmiles.ca/services/airmiles/sling/no-cache/transactions?page=1&size=19999&from={$from}&to={$to}&sort=transactionDate,desc&locale=en");
        $response = $this->http->JsonLog(null, 3, false, 'cashMilesEarned');
        // refs#23697
        $lastActivity = 0;
        $genericActivityDtoList = $response->_embedded->genericActivityDtoList ?? [];

        foreach ($genericActivityDtoList as $activity) {
            $expDate = strtotime($this->http->FindPreg('/^(.+?)T/', false, $activity->transactionDate));

            if ($expDate && $expDate > $lastActivity) {
                $this->logger->debug("Expiration Date: $expDate");
                $lastActivity = $expDate;
            }
        }// foreach ($genericActivityDtoList as $activity)

        if ($lastActivity > 0) {
            // Last Activity
            $this->SetProperty("LastActivity", date('F j, Y', $lastActivity));
            // Expiration Date
            $this->SetExpirationDate(strtotime('+2 year', $lastActivity));
        }

        if (isset($response->_embedded)) {
            $this->SetProperty("YTDMiles", $response->_embedded->transactionSummary->cashMilesEarned + $response->_embedded->transactionSummary->dreamMilesEarned);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        // $this->http->GetURL("https://services.api.airmiles.ca/services/airmiles/sling/no-cache/member-banner", [], 20);
        //		$this->http->GetURL("https://www.airmiles.ca/arrow/MyProfile");
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!empty($response->cardNumber)) {
            return true;
        }

        return false;
    }

    private function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/'sitekey'\s*:\s*'(.+?)'/");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //  From Sunday, June 30 at 8:00 p.m. ET to Monday, July 1 at 11:00 p.m. ET, we will be upgrading our systems to better serve you.
        if ($message = $this->http->FindSingleNode('
                //p[contains(., "will be upgrading our systems to better serve you.")]
                | //p[contains(text(), "We\'re undergoing some maintenance right now, but we\'ll be up and running shortly.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Scheduled maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'are unavailable due to scheduled maintenance until')]", null, true, "/(.+)Thank you for/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Scheduled maintenance
        if ($message = $this->http->FindPreg("/(Thanks for visiting our site. We are currently performing scheduled[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Website is currently unavailable
        if ($message = $this->http->FindPreg("/Website is currently unavailable/ims")) {
            throw new CheckException("The airmiles.ca Website is currently unavailable. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request
        if ($message = $this->http->FindPreg("/An error occurred while processing your request./ims")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }

        // Website is temporarily unavailable
        if ($message = $this->http->FindPreg("/Website is temporarily unavailable/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Airmiles.ca is temporarily unavailable [^<]+/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Site outage /*checked*/
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'sites are experiencing slow response times or site outages')]", null, true, "/([^-<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Airmiles.ca is experiencing a site outage
        if ($message = $this->http->FindPreg("/Airmiles.ca is experiencing a site outage[^<]+/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Status 500
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 500')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/(The server is temporarily unable to service your request.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * wrong error
        ##  The server encountered an internal error or misconfiguration and was unable to complete your request.
        if ($message = $this->http->FindPreg("/(The server encountered an internal error or misconfiguration and was unable\s*to\s*complete your request\.)/ims"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        */
        // Maintenance
        if ($this->http->FindSingleNode("//title[contains(text(), 'Site Outage (Planned Outage')]")
            && ($message = $this->http->FindSingleNode("//span[@class = 'return_time']/parent::p"))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindSingleNode("//title[contains(text(), 'Site Outage (Volume Outage)')]")) {
            throw new CheckException("With so many Collectors visiting airmiles.ca we sure are feeling the love. Although our site is currently unavailable, we will be back up again soon!", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Although airmiles.ca needs a short break')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize, we are experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'We apologize, we are experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if (
            in_array($this->http->Response['code'], [0, 500])
            // Service Temporarily Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")
        ) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        return false;
    }

    private function setDisplayName($name)
    {
        if (strstr($name, "Cash")) {
            $name = "Cash Miles";
        } elseif (strstr($name, "Dream")) {
            $name = "Dream Miles";
        }

        return $name;
    }

    private function getURLProxy($url)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'  => '*/*',
            'Referer' => 'https://www.airmiles.ca/en/profile/transaction-history.html',
            'Origin'  => 'https://www.airmiles.ca',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL($url, $headers, 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/(?:The server encountered an internal error or misconfiguration and was unable\s*to\s*complete your request\.|An error occurred while processing your request\.<p>)/ims")) {
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL($url, $headers, 20);
        }
        // it's worked
        elseif (
            (isset($response->errorCode) && in_array($response->errorCode, [500]))
            || $this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')
        ) {
            sleep(5);
            $this->http->GetURL($url, $headers, 20);
        }
    }
}
