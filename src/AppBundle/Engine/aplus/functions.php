<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use AwardWallet\Schema\Parser\Common\Hotel;

class TAccountCheckerAplus extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $host = 'secure.accor.com';
    public $JSESSIONID = null;
    public $fromIsLoggedIn = false;

    private $config = []; // deprecated
    private $cntSkippedPastIts = 0;
    private $lastName = null;
    private $authentification = null; // deprecated
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        // incapsula issue
        if ($this->smartBrightDataEnabling()) {
            $this->setProxyBrightData();
        } else {
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA), false);
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->host}/account/index.html#/en/dashboard", [], 20);
        $this->http->RetryCount = 2;

        $this->JSESSIONID = $this->getJSESSIONID();

        if (!isset($this->JSESSIONID)) {
            $this->logger->error("SessionId not found");

            return false;
        }

        if (!$this->prePareLogin()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            $this->fromIsLoggedIn = true;

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->smartSeleniumEnabling()) {
            $this->selenium();
        }

        // for JSESSIONID
        if (!$this->http->GetURL("https://{$this->host}")) {
            return $this->checkErrors();
        }

        $this->http->GetURL("https://api.accor.com/authentication/v2.0/authorization?appId=all.accor&ui_locales=en&redirect_uri=https://all.accor.com/enroll-loyalty/check-authent.html&redirect_site_uri=https://all.accor.com/a/en.html");

        if (!$this->prePareLogin()) {
            if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if (
            $this->http->ParseForm("form-id")
            || $this->http->ParseForm(null, '//form[contains(@action, "authorization.ping")]')
        ) {
            $this->http->unsetInputValue("pf.rememberUsername");

            if (!$this->prePareLogin()) {
                if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
                }

                return false;
            }

            $prmUrl = $this->http->FindSingleNode('//input[@name = "prmUrl"]/@value');

            if (!$prmUrl) {
                return false;
            }

            $this->http->FormURL = "https://login.accor.com{$prmUrl}?persistent=yes";

            $this->http->SetInputValue('pf.username', $this->AccountFields['Login']);
            $this->http->SetInputValue('pf.pass', $this->AccountFields['Pass']);
            $this->http->SetInputValue('rememberUsername', 'on');
            $this->http->SetInputValue('pf.ok', 'clicked');
            $this->http->SetInputValue('pf.rememberUsername', '');

            sleep(2);
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/x-www-form-urlencoded",
                "Origin"       => "https://login.accor.com",
            ];

            if (!$this->http->PostForm($headers) && $this->http->Response['code'] != 404) {
                return $this->checkErrors();
            }

            return true;
        }

        $prmUrl = $this->http->FindPreg('/flowId=([^\&]+)/', false, $this->http->currentUrl());

        if (!$prmUrl) {
            if ($this->http->FindPreg("/_Incapsula_Resource/")) {
                $this->markProxyAsInvalid();
                $this->smartBrightDataEnabling(true);

                throw new CheckRetryNeededException(3, 1);
            }

            return $this->checkErrors();
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br, zstd",
            "Content-Type"    => "application/json",
            "X-XSRF-Header"   => "PingFederate",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.accor.com/pf-ws/authn/flows/{$prmUrl}?action=checkUsernamePassword", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 302 && $this->http->currentUrl() == 'https://secure.accor.com') {
            $this->http->GetURL("https://attente.accor.com/");
        }

        /**
         * We’ll be back soon.
         * We are temporarily performing some maintenance in order to provide you with the best experience.
         * Please excuse us for the inconvenience.
         * In the meantime, please contact the hotel directly should you wish to make or modify a booking.
         */
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "bigger") and contains(., "We are temporarily performing some maintenance in order to provide you with the best experience.")]')) {
            throw new CheckException("The {$this->AccountFields['DisplayName']} website is currently unavailable and undergoing maintenance. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // Website is currently unavailable
        if ($message = $this->http->FindPreg("/(is\s*currently\s*unavailable\s*and\s*undergoing\s*maintenance\.)/ims")) {
            throw new CheckException("The {$this->AccountFields['DisplayName']} website is currently unavailable and undergoing maintenance. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }

        // Service Temporarily Congested
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Congested')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // A technical error has occurred. Please try again in a few moments.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'A technical error has occurred. Please try again in a few moments.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // A technical problem has occurred on our site
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'A technical problem has occurred on our site')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request due to maintenance downtime or capacity problems.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your request due to maintenance downtime or capacity problems.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The site is momentarily unavailable
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The site is momentarily unavailable')]/text()[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 404) {
            $this->http->GetURL("https://all.accor.com/");
            //# Site Maintenance
            if ($message = $this->http->FindPreg("/(is\s*currently\s*unavailable\s*and\s*undergoing\s*maintenance\.)/ims")) {
                throw new CheckException("The {$this->AccountFields['DisplayName']} website is currently unavailable and undergoing maintenance. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
            }
        }
        // retries
        if ($this->http->Response['code'] == 0) {
            // A technical problem has occurred on our site. Please try again.
            if ($this->attempt === 1 && $this->http->Error == 'Network error 0 - ') {
                throw new CheckException("A technical problem has occurred on our site. Please try again.", ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckRetryNeededException(3, 10);
        }

        // provider error
        if (
            ($this->http->Response['code'] == 500 && $this->http->currentUrl() == 'https://authentication.accorhotels.com/cas/login')
            || ($this->http->Response['code'] == 500 && $this->http->FindPreg('/\^\{\s*"error":\s*\{\s*"message": "Internal Server Error",\s*"details": "Unable to get a response from backend",\s*"code": "500"\s*\}\s*\}/'))
            || $this->http->FindSingleNode('//h1[contains(text(), "Error 503 Resource temporarily unavailable")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($response && !isset($response->resumeUrl)) {
            $message = $response->details[0]->userMessage ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'We didn\'t recognize the username or password you entered. Please try again.')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Your account is disabled')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;

                return false;
            }// if ($message)

            if ($this->http->FindPreg("/_Incapsula_Resource/")) {
                $this->smartSeleniumEnabling(true);
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 7);
            }

            return false;
        }// if (!isset($response->resumeUrl))

        if (isset($response->resumeUrl)) {
            $this->http->GetURL($response->resumeUrl);
        }

        // retries
        if (($this->http->currentUrl() == "https://{$this->host}/authentication/loginDown.jsp"
            || strstr($this->http->currentUrl(), 'http://www.accorhotels.com/gb/error/error.shtml'))) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
        $state = $output['state'] ?? null;
        $code = $output['code'] ?? null;
        $appId = $output['appId'] ?? null;

        if (!$state || !$code || !$appId) {
            $this->logger->error("something went wrong");
        }

        if ($state && $code && $appId) {
            $this->http->FormURL = "https://api.accor.com/authentication/v2.0/login";
            $this->http->Form = [];
            $this->http->SetInputValue("state", $state);
            $this->http->SetInputValue("code", $code);
            $this->http->SetInputValue("appId", $appId);
            $this->http->SetInputValue("redirect_uri", "https://all.accor.com/authentication/landing/index.shtml");
            $this->http->SetInputValue("t", date("UB"));
            $this->http->SetInputValue("lang", "en");

            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "apikey"       => "l7xx8785261b2a33457db88959a8679a1307",
                "Content-Type" => "application/x-www-form-urlencoded",
                "Origin"       => "https://all.accor.com",
            ];

            $this->http->RetryCount = 0;
//            $this->http->PostForm($headers);
            $this->http->PostURL("https://api.accor.com/authentication/v2.0/login?appId={$appId}&state={$state}&code={$code}&redirect_uri=https:%2F%2Fall.accor.com%2Fenroll-loyalty%2Fcheck-authent.html", "{}", $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
                $this->State['Authorization'] = $response->access_token;
//                $this->http->GetURL($response->redirect_site_uri);
                $this->JSESSIONID = $this->getJSESSIONID();

                if (!isset($this->JSESSIONID)) {
                    $this->logger->error("SessionId not found");

                    if ($this->http->FindPreg("/_Incapsula_Resource/") && (!$this->smartSeleniumEnabling() || $this->attempt < 2)) {
                        $this->smartSeleniumEnabling(true);
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException(3, 1);
                    }

                    if ($this->http->FindPreg("/\"details\": \"(?:Gateway internal error. Please contact the administrator.|Unable to get a response from backend)\"|/")) {
                        throw new CheckRetryNeededException(2);
                    }

                    return false;
                } // if (!isset($this->JSESSIONID))

                return $this->loginSuccessful();
            }

            if ($this->http->Response['code'] == 400) {
                throw new CheckRetryNeededException(3, 7);
            }

            if (
                $this->http->Response['code'] == 401
                && isset($response->error->message, $response->error->details)
                && $response->error->message == 'Authentication error'
                && $response->error->details == "You are not allowed to access the services exposed by this Gateway."
            ) {
                throw new CheckException("We're sorry, our website is experiencing technical difficulties. We are working to resolve this issue as quickly as possible. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Invalid credentials
        if ($this->http->FindSingleNode("//div[contains(text(), 'error.login.failed.wrong.identifiers')]")
            || strstr($this->http->currentUrl(), '&codeErreur=10&userlogin=' . $this->AccountFields['Login'])) {
            // retries
            $this->logger->debug('Sometimes on this provider wrong credentials error is false-positive.');

            throw new CheckRetryNeededException(2, 10, "Not a valid identification. Please try again or create a profile (My Profile).", ACCOUNT_INVALID_PASSWORD);
        }
        // Not a valid identification. Please try again or create a profile (My Profile)
        if ($message = $this->http->FindSingleNode("//div[@id = 'message'] | //p[@id = 'api-service-error']", null, true, "/(Not a valid.? identification. Please try again or .+)/")) {
            $message = str_replace('. .', '.', $message);

            throw new CheckException(str_replace('Â', '', $message), ACCOUNT_INVALID_PASSWORD);
        }
        // Account is locked
        if ($this->http->FindSingleNode("//div[contains(text(), 'error.login.failed.account.blocked')]")
            || strstr($this->http->currentUrl(), '&codeErreur=3&userlogin=' . $this->AccountFields['Login'])) {
            throw new CheckException("Your account is locked. To unlock your account,
            please go to the provider site and take appropriate actions", ACCOUNT_LOCKOUT);
        } /*checked*/
        // A technical problem has occurred on our site
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'generic.error.technical')]")) {
            throw new CheckException("A technical problem has occurred on our site. Please try again.", ACCOUNT_PROVIDER_ERROR);
        }
        // The site is momentarily unavailable.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The site is momentarily unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /**
         * We’ll be back soon.
         * We are temporarily performing some maintenance in order to provide you with the best experience.
         * Please excuse us for the inconvenience.
         * In the meantime, please contact the hotel directly should you wish to make or modify a booking.
         */
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "bigger") and contains(., "We are temporarily performing some maintenance in order to provide you with the best experience.")]')) {
            throw new CheckException("The {$this->AccountFields['DisplayName']} website is currently unavailable and undergoing maintenance. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function getJSESSIONID()
    {
        $cookies = array_merge(
            $this->http->GetCookies($this->host),
            $this->http->GetCookies($this->host, "/", true),
            $this->http->GetCookies(".accor.com", "/"),
            $this->http->GetCookies($this->http->FindPreg('/\w+(\..+)/', false, $this->host)),
            $this->http->GetCookies($this->http->FindPreg('/\w+(\..+)/', false, $this->host), "/", true)
        );

        return $cookies['JSESSIONID'] ?? null;
    }

    public function Parse()
    {
        // Suite Night Upgrade  // refs #18683
        $this->logger->info('Suite Night Upgrade ', ['Header' => 3]);
        $userWebData = $this->http->JsonLog(str_replace("{}&& ", '', $this->http->Response['body']), 0, false, 'loyaltyCards');
        $this->SetProperty("CombineSubAccounts", false);
        $awards = $userWebData->user->loyalty->account->awards ?? [];
        $this->logger->debug(var_export($awards, true), ['pre' => true]);

        foreach ($awards as $award) {
            $upgrades = [];
            $details = $award->details;

            if (empty($award->details)) {
                $this->logger->debug("skip empty details {$award->type}");

                continue;
            }

            foreach ($details as $detail) {
                if ($detail->status != 'REDEEMABLE') {
                    continue;
                }

                if (isset($upgrades[$detail->expirationDate])) {
                    $upgrades[$detail->expirationDate]++;
                } else {
                    $upgrades[$detail->expirationDate] = 1;
                }
            } // foreach ($roomUpgrades as $roomUpgrade)

            $code = 'suiteNightUpgrade';
            $displayName = 'Suite Night Upgrade';
            // refs #18683
            if ($award->name == 'StayPlus') {
                $code = 'stayPlusNight';
                $displayName = 'Stay Plus night';
            }

            foreach ($upgrades as $key => $value) {
                $exp = strtotime($this->ModifyDateFormat($key), false);
                $this->AddSubAccount([
                    'Code'           => $code . $exp,
                    'DisplayName'    => $displayName,
                    'Balance'        => $value,
                    'ExpirationDate' => $exp,
                ]);
            }// foreach ($upgrades as $key => $value)
        }// foreach ($awards as $award)

        $userData = $userWebData->user ?? null;
        $loyaltyAccount = $userWebData->user->loyalty->account->loyaltyCards[0] ?? null;

        if (!isset($userData) || !isset($loyaltyAccount)) {
            if ($this->fromIsLoggedIn) {
                $this->http->RetryCount = 0;
            }
            $this->http->GetURL("https://{$this->host}/bean/getViewBeans.action?beans=OriginViewBean|AClubViewBean|LoyaltyAccountViewBean|NextBookingViewBean|CurrenciesViewBean|SearchCriteriaViewBean&httpSessionId={$this->JSESSIONID}&t=" . date('UB') . "&lang=gb", [], 20);
            $beanData = $this->http->JsonLog(str_replace("{}&& ", '', $this->http->Response['body']), 3);
            // alternative page with json
            if (!isset($beanData->viewBeans->LoyaltyAccountViewBean->account->loyaltyCards[0])) {
                $this->logger->notice("Try to load alternative page with properties");
                $this->http->GetURL("https://{$this->host}/bean/getViewBeans.action?beans=OriginViewBean|NextBookingViewBean|LoyaltyLastActivityViewBean|LoyaltyAccountViewBean&httpSessionId={$this->JSESSIONID}&t=" . date('UB') . "&lang=gb");
                $beanData = $this->http->JsonLog(str_replace("{}&& ", '', $this->http->Response['body']));
            } // if (!isset($beanData->viewBeans->LoyaltyAccountViewBean->account->loyaltyCards[0]))
            $this->http->RetryCount = 2;

            // isLoggedIn issue
            if (
                ($this->fromIsLoggedIn
                    && ($this->http->Response['code'] == 503
                        || $this->http->Response['code'] == 0
                        || $this->http->Response['code'] == 504
                        || $this->http->FindPreg("/actionErrors\":\[\"([^<\"]+)/ims"))
                    || $this->http->currentUrl() == 'https://attente.accorhotels.com')
            ) {
                throw new CheckRetryNeededException(3, 1);
            }

            // A technical problem has occurred on our site...
            if (isset($beanData->actionErrors) && !empty($beanData->actionErrors)) {
                throw new CheckException($this->http->FindPreg("/actionErrors\":\[\"([^<\"]+)/ims"), ACCOUNT_PROVIDER_ERROR);
            }
            // Loyalty Account
            if (!isset($loyaltyAccount) && isset($beanData->viewBeans->LoyaltyAccountViewBean->account->loyaltyCards[0])) {
                $loyaltyAccount = $beanData->viewBeans->LoyaltyAccountViewBean->account->loyaltyCards[0];
            }
            // User's Information
            if (!isset($userData) && isset($beanData->viewBeans->LoyaltyAccountViewBean)) {
                $userData = $beanData->viewBeans->LoyaltyAccountViewBean;
            }
        }// if (!isset($userData) || !isset($loyaltyAccount))

        if (!isset($userData)) {
            $this->logger->notice(">>> User's Information not found");
        }

        // Balance - Number of points
        $this->SetBalance($loyaltyAccount->points ?? null);
        // Status
        $this->SetProperty('Status', $loyaltyAccount->currentTiering ?? null);
        //# Name
        if (isset($userData->firstName) && isset($userData->lastName)) {
            $this->SetProperty("Name", beautifulName($userData->firstName . ' ' . $userData->lastName));
            $this->lastName = $userData->lastName;
        } else {
            $this->logger->notice(">>> Name not found");
        }
        // Card Number
        $this->SetProperty("AccountNumber", $loyaltyAccount->cardNumber ?? null);
        //# Points Expiration Date
        if (isset($loyaltyAccount->pointsExpirationDate)) {
            $this->logger->debug(">>> pointsExpirationDate " . $loyaltyAccount->pointsExpirationDate);
            $exp = $this->ModifyDateFormat($loyaltyAccount->pointsExpirationDate);
//            $exp = str_replace('T00:00:00','', $loyaltyAccount->pointsExpirationDate);
        } else {
            $this->logger->notice(">>> Points Expiration Date not found");
        }

        if (isset($exp)) {
            $exp = strtotime($exp);

            if ($exp != false) {
                $this->SetExpirationDate($exp);
            }
        }
        //# Card Expiration Date
        if (isset($loyaltyAccount->cardExpirationDate)) {
            $this->logger->debug(">>> cardExpirationDate " . $loyaltyAccount->cardExpirationDate);
//            $exp = str_replace('T00:00:00','', $loyaltyAccount->cardExpirationDate);
            $exp = $this->ModifyDateFormat($loyaltyAccount->cardExpirationDate);
            $exp = strtotime($exp);

            if ($exp !== false) {
                $exp = date("m/d/Y", $exp);
                $this->SetProperty("StatusExpirationDate", $exp);
            }// if ($exp !== false)
        }// if (isset($loyaltyAccount->cardExpirationDate))
        else {
            $this->logger->notice(">>> Status Expiration Date not found");
        }
        // Points last 12 months
        $this->SetProperty("Pointslast12months", $loyaltyAccount->pointsLast12Months ?? null);
        // Points to Platinum
        $this->SetProperty("Pointstoplatinum", $loyaltyAccount->pointsToNextTiering ?? null);
        // Nights to Platinum
        $this->SetProperty("Nightstoplatinum", $loyaltyAccount->nightsToNextTiering ?? null);
        // Qualifying Stays
        $this->SetProperty("QualifyingStays", $loyaltyAccount->nbStay ?? null);
        // Qualifying Nights
        $this->SetProperty("QualifyingNights", $loyaltyAccount->nbNight ?? null);

        // for ParseItineraries
        $this->authentification = $this->http->getCookieByName('authentification'); // deprecated
        // User is not a member of this loyalty program
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                $this->http->FindPreg("/\"LoyaltyAccountViewBean\"\:\{\"account\"\:null\,/ims")
                || $this->http->FindPreg("/\,\"locked\"\:false\,\"loyaltyCards\":\[\]/ims")
            ) {
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    // Accept the general terms of use applicable
                    $this->http->GetURL("https://{$this->host}/json/offer/search.action?locations=profilTop|profilMiddle|profilBottom&httpSessionId=" . $this->JSESSIONID . "&t=" . date("UB") . "&lang=gb");
                    $response = json_decode(str_replace("{}&& ", '', $this->http->Response['body']));

                    if (isset($response->viewBeans->OffersViewBean->offers->profilTop[0]->description)
                        && trim($response->viewBeans->OffersViewBean->offers->profilTop[0]->description) == "Your customer account is changing. Accept {0} applicable to  your customer account and make the most of your benefits."
                    ) {
                        $this->SetWarning("Le Club Accorhotels (Accor Hotels, Novotel, etc.) website is asking you to accept the general terms of use applicable to your customer account, until you do so we would not be able to retrieve your account information."); /*review*/

                        return;
                    }

                    $this->SetWarning(self::NOT_MEMBER_MSG);
                }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
            }
            // Your account is locked
            elseif ($this->http->FindPreg("/\,\"locked\"\:true\,\"loyaltyCards\":\[\]/ims")) {
                throw new CheckException("Your account is locked", ACCOUNT_LOCKOUT);
            } elseif ($this->http->FindPreg("/_Incapsula_Resource/")) {
                $this->smartSeleniumEnabling(true);

                throw new CheckRetryNeededException(2, 1);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseItineraries()
    {
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Apikey' => 'l7xx8785261b2a33457db88959a8679a1307',
            'Origin' => 'https://all.accor.com',
        ];
        $data = http_build_query([
            'fields'    => 'id,metaInfo.*,orderItems.*,orderItems.hotelBooking.*,orderItems.hotelBooking.hotel[name,checkInHour,media,contact,factsheetUrl,id,brand,label,localization,rating,loyaltyProgram],-orderItems.hotelBooking.softBenefits,orderItems.hotelBooking.reservation.*',
            'filter'    => 'orderItems:(hotelBooking!=[null]+hotelBooking.status!=CANCELLED+hotelBooking.status!=OBSOLETE);$:orderItems!=[empty]',
            'startDate' => date("Y-m-d"),
            'sortType'  => 'asc',
        ]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.accor.com/orders/v1/contacts/me/orders?$data", $headers);
        // provider bug fix, it helps
        if ($this->http->Response['code'] == 500) {
            sleep(5);
            $this->http->GetURL("https://api.accor.com/orders/v1/contacts/me/orders?$data", $headers);
        }

        $this->http->RetryCount = 2;

        $itineraryList = $this->http->JsonLog();

        if ($this->http->FindPreg('/"title":\s*"(?:Authentication Error|Unauthorized)",\s+"status":\s*"401",/')) {
            return [];
        }

        if (!isset($itineraryList) && $this->http->Response['code'] == 204) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        foreach ($itineraryList as $item) {
            $this->currentItin++;
            $arrivalDate = explode('-', $item->orderItems[0]->hotelBooking->reservation->stayInfo->arrivalDate);

            $data = http_build_query([
                'secured'               => 'true',
                'historic.folderNumber' => $item->id,
                'historic.bookingName'  => $this->lastName,
                'historic.yearIn'       => $arrivalDate[0],
                'historic.monthIn'      => $arrivalDate[1],
                'historic.dayIn'        => $arrivalDate[2],
            ]);

            $this->http->GetURL("https://secure.accor.com/cancellation/search.action?$data");
            $detailId = $this->http->FindPreg('#\#/details/(.+)#', false, $this->http->currentUrl());
            $this->http->GetURL("https://secure.accor.com/rest/v3/all.accor/bookings/$detailId", ['Accept' => 'application/json, text/plain, */*']);
            $detail = $this->http->JsonLog(null, 2);

            if ($this->http->FindPreg('/\{"errors":\[\{"code":"RESOURCE_NOT_FOUND","message":"Resource not found"\}\]\}/')
                || $this->http->FindPreg('/\{"errors":\[\{"code":"UNKNOWN_ERROR","message":"We’re sorry, a technical error has occured."\}\]\}/')
                || $this->http->FindSingleNode("//div[@id='content']//text()[contains(., 'We are temporarily performing some maintenance in order to provide you with the best experience.')]")) {
                $this->logger->error('Skip itinerary');

                continue;
            }

            $this->parseItinerary($item, $detail);
        }

        return [];
    }

    public function ParseItinerariesOld()
    {
        $result = [];
        /*
         $kt2bds = $this->http->getCookieByName('kt2bds');
        */

        if (!empty($this->lastName)) {
            $lastName = urlencode($this->lastName);
        } else {
            /*
                /**
                 * @deprecated
                 * /
                $authentification = urldecode($this->http->getCookieByName('authentification'));

                if (empty($authentification)) {
                    $authentification = urldecode($this->authentification);
                }
                $this->logger->debug($authentification);
                // FE: GUSTAVO M PINHO -> lastname: M PINHO
                // Samanta Cristina Oliveira Alves => Oliveira Alves
                // Marcos Vinicius de Oliveira => de Oliveira
                $lastNameText = $this->http->FindPreg("/^(?:[\w-\s]+?)\s+([\w-]+\s*[\w-]*)$/u", false, $authentification);
                $this->logger->debug('lastNameText: ' . $lastNameText);

                if (strpos($lastNameText, ' ') !== false) {
            */
            $this->sendNotification("check lastName from cookie // ZM");
            /*
                }
                $lastName = urlencode($lastNameText);
            */
        }

        if ($this->fromIsLoggedIn === true) {
            $this->http->RetryCount = 1;
        }

        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];
        $this->http->GetURL("https://{$this->host}/rest/v4.0/user/bookings?appId=all.accor&language=en", $headers);
        $this->http->RetryCount = 2;

        if (
            $this->fromIsLoggedIn === true
            && strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        $data = $this->http->JsonLog(null, 3, true, 'active');
        $dataObj = $this->http->JsonLog(null, 0);

        if (isset($dataObj->BookingResponse->statusMessage) && $dataObj->BookingResponse->statusMessage == 'Technical error') {
            sleep(5);
            $this->http->GetURL("https://{$this->host}/rest/v4.0/user/bookings?appId=all.accor&language=en", $headers);
            $data = $this->http->JsonLog(null, 3, true, 'active');
            $dataObj = $this->http->JsonLog(null, 0);
        }
        $this->cntSkippedPastIts = 0;

        if (isset($data['BookingResponse']['bookingOrderList'])) {
            if (is_array($data['BookingResponse']['bookingOrderList']) && empty($data['BookingResponse']['bookingOrderList'])) {
                return $this->noItinerariesArr();
            }

            if (empty($lastName)) {
                $this->logger->error('there were problems above. empty lastName');

                return [];
            }

            $maxRedirects = $this->http->getMaxRedirects();
            $this->logger->debug("maxRedirects " . $maxRedirects);
            $itNumber = 0;
            $this->logger->debug("Total " . count($data['BookingResponse']['bookingOrderList']) . " itineraries were found");

            foreach ($data['BookingResponse']['bookingOrderList'] as $bookingOrder) {
                if (isset($bookingOrder['BookingOrderRest']['active'])) {
                    $number = $bookingOrder['BookingOrderRest']['number'] ?? null;
                    $this->logger->info("[{$itNumber}] Parse Itinerary #{$number}", ['Header' => 3]);
                    $itNumber++;

                    if ($bookingOrder['BookingOrderRest']['active'] != true) {
                        $this->parseFromBookingOrderRest($bookingOrder['BookingOrderRest']);

                        continue;
                    }

                    if ($number === null) {
                        $this->logger->error("Skip reservation, no booking number");
                        $this->sendNotification("active booking without booking number // ZM");

                        continue;
                    }

                    if ($itNumber > 19) {
                        $this->increaseTimeLimit();
                    }

                    if (isset($bookingOrder['BookingOrderRest']['bookingList'])) {
                        // get first, need only dateIn
                        $booking = $bookingOrder['BookingOrderRest']['bookingList'][0];
                        [$dayIn, $monthIn, $yearIn] = explode("/", $booking['BookingRest']['dateIn']);
                        $dayIn = (int) $dayIn;
                        $monthIn = (int) $monthIn;
                        $this->http->setMaxRedirects(10);
                        $retry = 1;

                        do {
                            if ($retry !== 1) {
                                sleep(2);
                            }
                            $this->logger->debug('try #' . $retry);
                            $this->http->GetURL("https://{$this->host}/lien_externe.svlt?goto=review-booking&historic.folderNumber={$number}&historic.bookingName={$lastName}&historic.dayIn={$dayIn}&historic.monthIn={$monthIn}&historic.yearIn={$yearIn}&code_langue=en");
                            $currentUrl = $this->http->currentUrl();
                            $bookingId = $this->http->FindPreg("/\?bookingId=(.+?)&/", false, $currentUrl);
                            $retry++;

                            if ($this->http->FindPreg("/_Incapsula_Resource/")) {
                                break;
                            }

                            if (empty($bookingId) && !$this->http->FindSingleNode("//p[contains(.,'A technical problem has occurred on our site. Please try again. If the problem persists, send us a message using Contact form.')]")) {
                                $this->sendNotification('other error // ZM');

                                break;
                            }
                        } while (empty($bookingId) && $retry <= 2);

                        if (empty($bookingId)) {
                            if ($this->http->FindPreg("/_Incapsula_Resource/")) {
                                $this->logger->error('can\'t find bookingId and retrieve reservation');
                                $this->logger->error('Parse from orederRest');
                                $this->parseFromBookingOrderRest($bookingOrder['BookingOrderRest']);
                            } else {
                                $this->logger->debug('try retrieve');
                                $arFields = [
                                    'ConfNo'   => $number,
                                    'DateIn'   => implode("/", [$monthIn, $dayIn, $yearIn]),
                                    'LastName' => $lastName,
                                ];
                                $this->logger->debug(var_export($arFields, true));
                                $it = [];
                                $cntResMaster = count($this->itinerariesMaster->getItineraries());
                                $this->CheckConfirmationNumberInternal($arFields, $it);

                                if ($cntResMaster === count($this->itinerariesMaster->getItineraries())) {
                                    $this->sendNotification('check empty bookingId and empty retrieve // ZM');
                                }
                            }

                            continue;
                        }
                        $this->http->setDefaultHeader('Referer',
                            "https://{$this->host}/account/index.html#/en/dashboard");
                        $this->http->setMaxRedirects(0);
                        $this->http->RetryCount = 0;
                        $this->http->GetURL("https://secure.accor.com/rest/v2.0/all.accor/bookings/{$bookingId}?pricing=true&pricingDetails=true&pricingFees=true");
                        $this->http->RetryCount = 2;

                        if ($this->http->Response['code'] === 302) {
                            $this->logger->error('Parse from orederRest not openable itinerary');
                            $this->parseFromBookingOrderRest($bookingOrder['BookingOrderRest']);

                            continue;
                        }
                        $reservation = $this->http->JsonLog(null, 3, true);
                        $this->http->setMaxRedirects($maxRedirects);

                        if ($this->http->FindPreg('/"code":"RESOURCE_NOT_FOUND"/')) {
                            $this->logger->error('Skipping not found itinerary');
                            $this->parseFromBookingOrderRest($bookingOrder['BookingOrderRest']);

                            continue;
                        }

                        if ($this->http->FindPreg('/"code":"UNKNOWN_ERROR"/')) {
                            $this->logger->error('We’re sorry, a technical error has occured');
                            $this->parseFromBookingOrderRest($bookingOrder['BookingOrderRest']);

                            continue;
                        }

                        if (!empty($reservation)) {
                            $this->parseHotelRetrieve($number, $reservation);
                        }
                    }
                }
            }
            $cntReservations = count($data['BookingResponse']['bookingOrderList']);
            $cntParsedIts = count($this->itinerariesMaster->getItineraries());

            if ($cntParsedIts === 0 && $cntReservations === $this->cntSkippedPastIts && !$this->ParsePastIts) {
                return $this->noItinerariesArr();
            }
        } elseif (isset($dataObj->BookingResponse) && property_exists($dataObj->BookingResponse, 'bookingOrderList')
            && isset($data['BookingResponse']['statusMessage'])
        ) {
            $this->logger->error($data['BookingResponse']['statusMessage']);
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Reservation #",
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
            "DateIn"   => [
                "Caption"  => "Date of arrival",
                "Type"     => "date",
                "Size"     => 40,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://{$this->host}/gb/cancellation/search-booking.shtml";
    }

    public function ArrayVal($ar, $indices)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return null;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $dateIn = explode("/", $arFields['DateIn']);
        $dateIn = sprintf('%s-%s-%s', $dateIn[2], $dateIn[0], $dateIn[1]);
        $arFields['DateIn'] = $dateIn;

        $hotelsId = ['9069', '8167', '9729', '3665'];
        $id = array_rand($hotelsId);
        $this->http->GetURL("https://secure.accor.com/rest/v2.0/accorhotels.com/hotels/" . $hotelsId[$id]);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->getBooking($arFields);

        if (stripos($this->http->Response['body'], '{"errors":[{"code":"UNKNOWN_ERROR","message":""}]}') !== false) {
            $name = $arFields['LastName'];
            $arFields['LastName'] = $this->http->FindPreg('/(\w+)\s*$/', false, $name);
            $this->getBooking($arFields);
        }
        $data = $this->http->JsonLog(null, 0, true);

        if ($data && $this->http->Response['code'] == 200) {
            $this->parseHotelRetrieve($arFields['ConfNo'], $data);
        } elseif ($this->http->FindPreg("/\{\"errors\":\[\{\"code\":\"RESOURCE_NOT_FOUND\",\"message\":\"Resource not found\"\}\]\}/")) {
            return "No reservation matches your search criteria. Please modify your request.";
        } else {
            $this->sendNotification("failed to retrieve itinerary by conf #");
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date of arrival"  => "PostingDate",
            "Description"      => "Description",
            "Transaction date" => "Info.Date",
            "Night(s)"         => "Info",
            "Points"           => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL("https://secure.accor.com/web/user/v1.0/user/loyalty/transactions?appId=all.accor&language=en");

        $response = json_decode(str_replace("{}&& ", '', $this->http->Response['body']));
//            $this->http->Log("<pre>".var_export($response, true)."</pre>", false);
        if (!isset($response->LCAHTransactions)) {
            $this->logger->error("History not found!");

            return $result;
        }

        $historyLines = $response->LCAHTransactions;
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate, $historyLines));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate, $historyLines)
    {
        $result = [];

        if (count($historyLines) > 0) {
            $this->logger->debug("Found " . (count($historyLines) - 1) . " items");

            for ($i = 0; $i < count($historyLines); $i++) {
                $historyLines[$i] = $historyLines[$i]->LCAHTransactionRest;
                // {"transactionDate":"21/06/2021","transactionDescription":"Stay with points : null","nightsCount":0,"points":-3000,"rewardPoints":-3000,"statusPoints":0,"offerCode":"BURN_4STAY","offerName":"Burn4Stay","cancelled":false}
                $dateStr = $this->ModifyDateFormat(str_replace('T00:00:00', '', $historyLines[$i]->operationDate ?? $historyLines[$i]->transactionDate));
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }

                $result[$startIndex]['Date of arrival'] = $postDate;
                $result[$startIndex]['Description'] = $historyLines[$i]->transactionDescription ?? null;
                $result[$startIndex]['Transaction date'] = strtotime($this->ModifyDateFormat($historyLines[$i]->transactionDate));
                $result[$startIndex]['Night(s)'] = $historyLines[$i]->nightsCount;
                $result[$startIndex]['Points'] = $historyLines[$i]->points;
                $startIndex++;
            }
        }

        return $result;
    }

    protected function getBooking($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $url = sprintf("https://{$this->host}/rest/v2.0/accorhotels.com/bookings?lastName=%s&number=%s&dateIn=%s&pricing=true&pricingDetails=true",
            urlencode($arFields['LastName']),
            $arFields['ConfNo'],
            $arFields['DateIn']
        );
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];
        $this->http->GetURL($url, $headers);
        $this->http->RetryCount = 2;
    }

    protected function prePareLogin()
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login'] = trim($this->AccountFields['Login']);
        $login = $this->AccountFields['Login'];

        if (
            (is_numeric($this->AccountFields['Login']) && strlen($this->AccountFields['Login']) === 16)
            || strpos($this->AccountFields['Login'], '3081031') !== false
            || strpos($this->AccountFields['Login'], '3081034') !== false
        ) {
            $this->logger->notice("truncate login");
            $login = $this->http->FindPreg('/^(?:3|8)\d{6}(\d{7}\w)\w$/', false, $this->AccountFields['Login']);

            if (empty($login)) {
                $login = $this->http->FindPreg('/^(?:3|8)\d{6}(\d{8})\d+$/', false, $this->AccountFields['Login']);

                // Account:ID 929067
                if (empty($login)) {
                    $login = $this->http->FindPreg('/^(?:3|8)\d{6}(\d{8})$/', false, $this->AccountFields['Login']);
                }

                // Account:ID 5680578
                if (empty($login)) {
                    $login = $this->http->FindPreg('/^“?(?:3|8)\d{6}(\d{7})/', false, $this->AccountFields['Login']);
                }
            }

            if (empty($login)) {
                return false;
            }
        }

        $this->AccountFields['Login'] = $login;

        return true;
    }

    private function parseItinerary($item, $data)
    {
        if (!isset($data->number)) {
            $this->logger->error("\$data->number not found");

            return [];
        }

        $this->logger->info("[{$this->currentItin}] Parse Train #{$data->number}", ['Header' => 4]);

        $h = $this->itinerariesMaster->add()->hotel();
        $h->general()->confirmation($data->number, 'Booking number');

        foreach ($data->item as $_item) {
            if (isset($_item->detail->adults)) {
                $h->booked()
                    ->guests($_item->detail->adults);
            }

            if (isset($_item->room->label, $_item->room->description)) {
                $r = $h->addRoom();
                $r->setType($_item->room->label);
                $r->setDescription($_item->room->description, true);
            }
        }

        if ($data->pricing) {
            $h->price()->total($data->pricing->amount->afterTax);
            $h->price()->tax($data->pricing->amount->otherTax->amount->excluded);
            $h->price()->currency($data->pricing->currency);
        }

        if (isset($data->benefits->voucher->points) && $data->benefits->voucher->points > 0) {
            $h->price()->spentAwards($data->benefits->voucher->points);
        }

        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Apikey' => 'IApj1uS1vtKsoFaGEj1YwDWGXf6asodw',
            'Origin' => 'https://secure.accor.com',
        ];
        $this->http->GetURL("https://api.accor.com/catalog/v1/hotels/{$data->hotel->code}", $headers);
        $hotels = $this->http->JsonLog();
        $h->hotel()->name($data->hotel->name);

        if (isset($hotels->contact->faxPrefix, $hotels->contact->fax)) {
            $h->hotel()->fax("+({$hotels->contact->faxPrefix}){$hotels->contact->fax}");
        }

        if (isset($hotels->contact->faxPrefix, $hotels->contact->phone)) {
            $h->hotel()->phone("+({$hotels->contact->phonePrefix}){$hotels->contact->phone}");
        }
        $address = "";

        if (isset($hotels->localization->address->street)) {
            $address = "{$hotels->localization->address->street}, ";
        }
        $address .=
            "{$hotels->localization->address->city}"
            . ", {$hotels->localization->address->country}"
            . ", {$hotels->localization->address->zipCode}";
        $h->hotel()->address($address);

        $checkIn = $item->orderItems[0]->hotelBooking->reservation->stayInfo->arrivalDate;
        $checkOut = $item->orderItems[0]->hotelBooking->reservation->stayInfo->departureDate;

        $checkIn = isset($hotels->checkInHour) ? strtotime($hotels->checkInHour, strtotime($checkIn)) : strtotime($checkIn);
        $checkOut = isset($hotels->checkOutHour) ? strtotime($hotels->checkOutHour, strtotime($checkOut)) : strtotime($checkOut);
        $h->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $headers = [
            "Authorization" => "Bearer {$this->State['Authorization']}",
        ];
        $this->http->GetURL("https://secure.accor.com/web/user/v2/user?appId=all.accor&httpSessionId={$this->JSESSIONID}&t=" . date("UB") . "&lang=en", $headers);
        $this->http->RetryCount = 2;
        $userWebData = $this->http->JsonLog(str_replace("{}&& ", '', $this->http->Response['body']), 3, false, 'loyaltyCards');

        if (
            isset($userWebData->user->uaUserId)
            && (
                (stripos($this->http->Response['body'], $this->AccountFields['Login']) !== false)
                || (stripos($this->http->Response['body'], substr($this->AccountFields['Login'], 0, strpos($this->AccountFields['Login'], '@'))) !== false) // AccountID: 5323970
                || in_array($this->AccountFields['Login'], [
                    'rj170590@gmail.com', // AccountID: 3320529
                    '12799371', // AccountID: 2772048
                    'reuterra@gmail.com', // AccountID: 5467441
                    'kennylukhc@gmail.com', // AccountID: 4680077
                    'mshimoide@yahoo.com.br', // AccountID: 2686554
                    'peter.c.sohn@gmail.com', // AccountID: 623489
                    'basilio.carvalho@spencergp.com', // AccountID: 5873276
                    'patriciatutora@robeliz.com.br', // AccountID: 1045460
                    'rob.anderson@led-linear.com', // AccountID: 7288294
                    'mkac@globo.com', // AccountID: 2462847
                ])
            )
        ) {
            $this->http->setDefaultHeader("Authorization", "Bearer {$this->State['Authorization']}");
            $this->markProxySuccessful();

            return true;
        } elseif ($userWebData && isset($userWebData->errorMsg) && $userWebData->errorMsg == 'Unexpected error. Please try again') {
            throw new CheckException("We're sorry, our authentication system is currently down and you may not be able to access your account. Our team is already hard at work to get everything back up and running as soon as possible ", ACCOUNT_PROVIDER_ERROR);
        } else {
            $this->checkErrors();
        }

        return false;
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
            $selenium->useFirefox();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://{$this->host}/account/index.html#/en/dashboard");
            } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "log in")]]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                //            return false;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $retry = true;
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(3, 5);
        }
    }

    private function smartSeleniumEnabling($enableSelenium = false)
    {
        $this->logger->notice(__METHOD__);
        $selenium = 'false';

        $cache = Cache::getInstance();
        $cacheKey = "selenium_aplus";
        $data = $cache->get($cacheKey);

        if (!empty($data) && $data === 'true') {
            $this->logger->debug("return true");

            return true;
        }

        if ($enableSelenium === true) {
            $selenium = 'true';
        }

        $this->logger->debug("selenium: {$selenium}");
        $cache->set($cacheKey, $selenium, 300);

        return $selenium === 'true';
    }

    private function smartBrightDataEnabling($enableBrightData = false)
    {
        $this->logger->notice(__METHOD__);
        $brightData = 'false';

        $cache = Cache::getInstance();
        $cacheKey = "selenium_aplus_brightData";
        $data = $cache->get($cacheKey);

        if (!empty($data) && $data === 'true') {
            $this->logger->debug("return true");

            return true;
        }

        if ($enableBrightData === true) {
            $brightData = 'true';
        }

        $this->logger->debug("selenium: {$brightData}");
        $cache->set($cacheKey, $brightData, 300);

        return $brightData === 'true';
    }

    private function parseFromBookingOrderRest(array $data)
    {
        $this->logger->notice(__METHOD__);
        $number = $data['number'] ?? null;

        if (isset($data['bookingList']) && !empty($data['bookingList'])) {
            $bookingList = $data['bookingList'][0];
            $booking = $bookingList['BookingRest'];
            $checkIn = strtotime(str_replace("/", ".", $booking['dateIn']));
            $checkOut = strtotime(str_replace("/", ".", $booking['dateOut']));

            if (!$this->ParsePastIts && $checkOut < strtotime(date("Y-m-d"))) {
                $this->logger->debug("Skip past itinerary");
                $this->cntSkippedPastIts++;

                return;
            }
            $filteredBookingList = [];
            $cntCancelledNotFiltered = 0;

            foreach ($data['bookingList'] as $v) {
                if ($v['BookingRest']['dateIn'] === $booking['dateIn'] && $v['BookingRest']['dateOut'] === $booking['dateOut']) {
                    $filteredBookingList[] = $v;
                } elseif (null !== $v['BookingRest']['cancellationNumber']) {
                    $cntCancelledNotFiltered++;
                }
            }

            // The site does not display clearly, example 1 room, 3 apartments with unclear dates. In general, we collect as one room
            /*if (count($filteredBookingList) + $cntCancelledNotFiltered !== count($data['bookingList'])) {
                $this->sendNotification("check accommodations // ZM");
            }*/

            $r = $this->itinerariesMaster->add()->hotel();

            if (!empty($number)) {
                $r->general()->confirmation($number);
            } else {
                $r->general()->noConfirmation();
            }

            if (null !== $booking['cancellationNumber']) {
                $r->general()
                    ->cancelled()
                    ->cancellationNumber($booking['cancellationNumber']);
            }
            $r->booked()
                ->checkIn($checkIn)
                ->checkOut($checkOut)
                ->rooms(count($filteredBookingList));
            $r->hotel()->name($data['hotel']['HotelRest']['name']);
            $this->getHotelInfoByCode($data['hotel']['HotelRest']['code'], $r);
            $pax = $kids = 0;

            foreach ($filteredBookingList as $bookingList) {
                $book = $bookingList['BookingRest'];
                $room = $r->addRoom();
                $room
                    ->setType($book['roomLabel']);

                if (isset($book['bookingNumber'])) {
                    $room
                        ->setConfirmation($book['bookingNumber']);
                }
                $pax += $book['nbPax'];
                $kids += $book['nbChildren'];
            }
            $r->booked()
                ->guests($pax)
                ->kids($kids);
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
        }
    }

    private function getHotelInfoByCode($hotelCode, AwardWallet\Schema\Parser\Common\Hotel $r)
    {
        $this->logger->notice(__METHOD__);
        $res = [];
        /** @var HttpBrowser $http2 */
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $this->increaseTimeLimit();
        $http2->GetURL("https://secure.accorhotels.com/rest/v2.0/accorhotels.com/hotels?q=$hotelCode");
        $data = $http2->JsonLog(null, 0, true);

        if (null === $data || $http2->Response['code'] == 302) {
            $this->logger->debug('retry get info');
            sleep(5);
            $http2->GetURL("https://secure.accorhotels.com/rest/v2.0/accorhotels.com/hotels?q=$hotelCode");
            $data = $http2->JsonLog(null, 0, true);
        }

        if (null === $data || $http2->Response['code'] == 302) {
            $r->hotel()->noAddress();
        }

        if (isset($data['hotel'][0]['address'])) {
            $addressData = $data['hotel'][0]['address'];
            $r->hotel()->address("{$addressData['street']}, {$addressData['town']}, {$addressData['cityCode']}, {$addressData['country']}");
        }

        if (isset($data['hotel'][0]['contact']['phone'])) {
            $r->hotel()->phone($data['hotel'][0]['contact']['phone']);
        }

        if (isset($data['hotel'][0]['contact']['fax']) && $data['hotel'][0]['contact']['fax'] !== '(+)') {
            $r->hotel()->fax($data['hotel'][0]['contact']['fax']);
        }
    }

    private function parseHotelInfo(string $hotelUrl, AwardWallet\Schema\Parser\Common\Hotel $r)
    {
        $this->logger->notice(__METHOD__);
        $cntTry = 1;

        do {
            $data = null;
            $this->logger->debug('try #' . $cntTry);

            if ($cntTry > 1) {
                sleep(5);
            }
            $this->http->GetURL($hotelUrl);

            if ($msg = $this->http->FindPreg('/("code":"UNKNOWN_ERROR")/')) {
                $this->logger->error($msg);
            } else {
                $data = $this->http->JsonLog(null, 0, true);
            }
            $cntTry++;
        } while (empty($data) && $cntTry <= 2);

        if (empty($data)) {
            $r->hotel()->noAddress();

            return;
        }

        $r->hotel()->address($this->getAndCombine($data, [
            ['address', 'street'],
            ['address', 'code'],
            ['address', 'town'],
            ['address', 'country'],
        ]));
        // Phone
        $phone = $this->ArrayVal($data, ['contact', 'phone']);

        if ($phone !== '(+)') {
            $r->hotel()->phone($phone);
        }
        // Fax
        $fax = $this->ArrayVal($data, ['contact', 'fax']);

        if (!in_array($fax, ['(+)', '(+)N/A', '(+0)', '(+)0', null])) {
            $r->hotel()->fax($fax);
        }
    }

    private function getAndCombine($data, $keys)
    {
        $this->logger->notice(__METHOD__);
        $s = '';

        foreach ($keys as $key) {
            $s = sprintf('%s %s', $s, $this->ArrayVal($data, $key));
        }
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);

        return $s;
    }

    private function findEuroRate($data, $currency)
    {
        $this->logger->notice(__METHOD__);

        foreach (ArrayVal($data, 'currency', []) as $item) {
            if (ArrayVal($item, 'code', []) === $currency) {
                $res = ArrayVal($item, 'euroRate');
                $this->logger->info($res);

                return $res;
            }
        }
        $this->logger->info(sprintf('Could not find euroRate for %s', $currency));

        return null;
    }

    private function parsePricing($data, AwardWallet\Schema\Parser\Common\Hotel $r)
    {
        $this->logger->notice(__METHOD__);
        // exchange rates
        if (!$this->config) {
            $this->http->GetURL("https://{$this->host}/rest/v2.0/all.accor/config");
            $this->logger->info('=config');
            $this->config = $this->http->JsonLog(null, 0, true);
        }

        if (!$this->config) {
            $this->logger->error('could not load price data');

            return;
        }

        if ($this->ArrayVal($this->config, ['tracking', 'currency']) !== 'EUR') {
            $this->sendNotification('currency logic changed');

            return;
        }
        //$userCurrency = $this->ArrayVal($this->config, ['country', 'currency']);
        $userCurrency = 'EUR';
        $pricingCurrency = $this->ArrayVal($data, ['item', 0, 'pricing', 'currency']);

        if (!$pricingCurrency) {
            $this->logger->info('pricing currency is empty');

            return;
        }

        if (!$userCurrency) {
            $this->sendNotification('currency logic changed');

            return;
        }
        $pricingToEuro = $this->findEuroRate($this->config, $pricingCurrency);
        $userToEuro = $this->findEuroRate($this->config, $userCurrency);
        $this->logger->info('exchange rates:');
        $this->logger->info(var_export([
            'userCurrency'    => $userCurrency,
            'pricingCurrency' => $pricingCurrency,
            'pricingToEuro'   => round($pricingToEuro, 2),
            'userToEuro'      => round($userToEuro, 2),
        ], true), ['pre' => true]);

        if (!$pricingToEuro || !$userToEuro) {
            $this->sendNotification('currency logic changed');

            return;
        }
        // Currency
        $r->price()->currency($userCurrency);
        $multiplier = $pricingToEuro / $userToEuro;
        // Total
        $total = $this->ArrayVal($data, ['pricing', 'amount', 'afterTax']) * $multiplier;
        $r->price()->total(round($total, 2));
        // Cost
        $cost = $this->ArrayVal($data, ['pricing', 'amount', 'beforeTax']) * $multiplier;
        $r->price()->cost(round($cost, 2));
        // Taxes, Fees
        $vat = ($this->ArrayVal($data, ['pricing', 'amount', 'vat', 'amount', 'included'])
                + $this->ArrayVal($data, ['pricing', 'amount', 'vat', 'amount', 'excluded'])) * $multiplier;
        $descrVat = $this->ArrayVal($data, ['pricing', 'amount', 'vat', 'detail', 0, 'label']);

        if (!empty($vat)) {
            $r->price()->fee($descrVat, round($vat, 2));
        }
        $fees = ($this->ArrayVal($data, ['pricing', 'amount', 'fees', 'amount', 'included']) + $this->ArrayVal($data,
                    ['pricing', 'amount', 'fees', 'amount', 'excluded'])) * $multiplier;
        $descrFees = $this->ArrayVal($data, ['pricing', 'amount', 'fees', 'detail', 0, 'label']);

        if (!empty($fees)) {
            $r->price()->fee($descrFees, round($fees, 2));
        }
        $other = ($this->ArrayVal($data, ['pricing', 'amount', 'otherTax', 'amount', 'included'])
                + $this->ArrayVal($data, ['pricing', 'amount', 'otherTax', 'amount', 'excluded'])) * $multiplier;
        $descrOther = $this->ArrayVal($data, ['pricing', 'amount', 'otherTax', 'detail', 0, 'label']);

        if (!empty($other)) {
            $r->price()->fee($descrOther, round($other, 2));
        }
        // Points
        $points = $this->ArrayVal($data, ['benefits', 'voucher', 'points']);

        if (!empty($points)) {
            $r->price()->spentAwards($points . ' points');
        }

        return;
    }

    private function parseHotelRetrieve($confNo, $data)
    {
        $this->logger->notice(__METHOD__);

        $r = $this->itinerariesMaster->add()->hotel();
        $r->general()
            ->confirmation($confNo)
            ->cancellation($this->ArrayVal($data, ['item', 0, 'cancellation', 'policy', 'label']), false, true);

        if (strcasecmp($this->ArrayVal($data, ['item', 0, 'status']), 'CANCELLED') === 0) {
            $r->general()
                ->status('CANCELLED')
                ->cancelled()
                ->cancellationNumber($this->ArrayVal($data, ['item', 0, 'cancellation', 'number']), false, true);
        }

        $r->hotel()->name($this->ArrayVal($data, ['hotel', 'name']));

        $checkInDate = strtotime($this->ArrayVal($data, ['item', 0, 'stay', 'date']));
        $nights = $this->ArrayVal($data, ['item', 0, 'stay', 'nights']);
        $r->booked()
            ->checkIn($checkInDate)
            ->checkOut(strtotime(sprintf('+%s days', $nights), $checkInDate))
            ->rooms(count($data['item']));

        // Hotel Info
        $hotelId = $this->http->FindPreg('/hotels\/(\w+)/', false, $this->ArrayVal($data, ['hotel', 'href']));
        $hotelUrl = sprintf("https://{$this->host}/rest/v2.0/accorhotels.com/hotels/%s", $hotelId);

        if (!$hotelId) {
            return;
        }
        $this->parseHotelInfo($hotelUrl, $r);

        // GuestNames
        // 2 guest: one 'civility'=>'CHI' (name 1в1 as mom)
        // 2 rooms  - the same guest
        // 'civility'=>'CHI' (name 1в1 as mom)
        $guestNames = beautifulName($this->getAndCombine($data, [
            ['item', 0, 'stay', 'guest', 0, 'civility'],
            ['item', 0, 'stay', 'guest', 0, 'firstName'],
            ['item', 0, 'stay', 'guest', 0, 'lastName'],
        ]));
        $r->general()->traveller($guestNames, true);

        $kids = $guests = 0;

        foreach ($data['item'] as $item) {
            $room = $r->addRoom();
            $room
                ->setType($this->ArrayVal($item, ['room', 'label']), true, true)
                ->setDescription($this->ArrayVal($item, ['room', 'description']), true, true);

            if (empty($room->getType()) && empty($room->getDescription())) {
                $r->removeRoom($room);
            }
            $guests += $this->ArrayVal($item, ['stay', 'adults']);
            $kids += $this->ArrayVal($item, ['stay', 'children']);
        }
        $r->booked()
            ->guests($guests)
            ->kids($kids);

        $this->parsePricing($data, $r);
        $this->detectDeadLine($r);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     *
     * @param Hotel $r
     */
    private function detectDeadLine(AwardWallet\Schema\Parser\Common\Hotel $r)
    {
        if (empty($cancellationText = $r->getCancellation())) {
            return;
        }
        $time = $this->http->FindPregAll("/^No(?: cancellation)? (?:charge|and modification fees) applies(?: if booking cancelled)? prior to (\d+:\d+|\d+\s*[ap]m)\s*h?\s*(?:[(]?local time[)]?)?,? (?:up to )?(\d+) days?\s*prior to arrival./i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][2] . ' days', $time[0][1]);

            return;
        }
        $time = $this->http->FindPregAll("/^Hotel will charge\s*(?:0.00 pct of the amount of the stay)?, if booking is cancelled prior to (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]?, up to (\d+) days? prior to arrival./i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][2] . ' days', $time[0][1]);

            return;
        }
        $time = $this->http->FindPregAll("/^No cancellation charge until (\d+) days? prior to arrival, (\d+:\d+|\d+\s*[ap]m)\s*h?\s*\./i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][1] . ' days', $time[0][2]);

            return;
        }
        $time = $this->http->FindPregAll("/^All reservations must be cancelled by (\d+:\d+|\d+\s*[ap]m) hotel [(]?local time[)]? (\d+) days? prior to arrival/i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][2] . ' days', $time[0][1]);

            return;
        }
        $time = $this->http->FindPregAll("/^No charge for cancellations made before (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]? (\d+) days? before arrival and for bookings with (?:loyalty|ALL) card made before /i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][2] . ' days', $time[0][1]);

            return;
        }
        $time = $this->http->FindPregAll("/^No cancellation charge applies if booking cancelled prior to (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]?, (\d+) days? prior to arrival./i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][2] . ' days', $time[0][1]);

            return;
        }
        $time = $this->http->FindPregAll("/^No cancellation charge applies until (\d+) days?, (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?Local time[)]? before arrival./i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][1] . ' days', $time[0][2]);

            return;
        }
        $time = $this->http->FindPregAll("/^No penalty if cancelled before ([\d:]+[ap]m) up to (\d+) days? prior to arrival\. Within/i",
            $cancellationText, PREG_SET_ORDER);

        if (isset($time[0])) {
            $r->booked()->deadlineRelative($time[0][2] . ' days', $time[0][1]);

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge applies prior to (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]? on the day of arrival/i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^No charge applies prior to (\d+:\d+|\d+\s*[ap]m)\s*h?\s* on the day of arrival\./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge until day of arrival, (\d+:\d+|\d+\s*[ap]m)\s*h?\s*\./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^The offer may be cancelled in its entirety.*? up to (\d+) hours before the arrival date; otherwise/i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative($time . ' hours');

            return;
        }

        if ($time = $this->http->FindPreg("/^Cancellation free until (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]? on the day of arrival\./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^Free cancell?ation up to (\d+:\d+|\d+\s*[ap]m)\s*h?\s*\(hotel local time\) on the day of arrival./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^Free cancell?ation until (\d+:\d+|\d+\s*[ap]m) (?:[(]?local time[)]?)?\s*(?:on)?\s*the day before (?:your )?arrival./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('-1 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^You can cancell? your reservation until (\d+:\d+|\d+\s*[ap]m) (?:[(]?local time[)]?)?\s*(?:on)?\s*the day before (?:your )?arrival./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('-1 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^Cancellations until (\d+:\d+|\d+\s*[ap]m) the day before arrival will not suffer penalty./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('-1 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge applies if booking cancelled until day of arrival./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', '00:00');

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge applies if booking cancelled until (\d+) days? prior to arrival\./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative($time . ' days', '00:00');

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge applies if booking cancelled prior to (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]? on day of arrival./i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^No charge for cancellation up to the day of arrival,* (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]?. After this/i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^Cancellation without charge until the day of arrival, (\d+:\d+\s*[ap])\.?m\.? [(]?local time[)]?\. /i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time . 'm');

            return;
        }

        if ($time = $this->http->FindPreg("/^Cancellation without penalty up to (\d+:\d+|\d+\s*[ap]m) one day before arrival. /i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('1 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^There will be no charge before (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]? on the arrival date. After this deadline,/i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge applies (?:up to|between \d+ and) (\d+) days? prior (?:to )?arrival. Beyond that time, /i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative($time . ' days', '00:00');

            return;
        }

        if (($time = $this->http->FindPreg("/^This booking is not allowed to cancel or changeable after (.+)/i",
                false, $cancellationText)) && ($dateDeadline = strtotime($time, false)) !== false
        ) {
            $r->booked()->deadline($dateDeadline);

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge applies between \d+ days? and the day of arrival, (\d+:\d+|\d+\s*[ap]m)\s*h?\s*[(]?local time[)]?. Beyond that time, /i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative('0 days', $time);

            return;
        }

        if ($time = $this->http->FindPreg("/^No cancellation charge applies until (\d+) days? before arrival. Between \\1 days/i",
            false, $cancellationText)
        ) {
            $r->booked()->deadlineRelative($time . ' days', '00:00');

            return;
        }

        if ($time = $this->http->FindPreg("/^Cancel. fees apply on day of arrival at (\d+:\d+|\d+\s*[ap]m)\s*h?$/i",
                false, $cancellationText)
            || $time = $this->http->FindPreg("/^Cancel. fees apply from (\d+) days prior.$/i",
                false, $cancellationText)
        ) {
            $this->logger->debug("skip deadline");

            return;
        }

        $r->booked()
            ->parseNonRefundable("/^If the booking is cancelled, hotel will charge the first (?:\d+ )?night'?s'? accommodation.$/i")
            ->parseNonRefundable("/^In case of cancellation the amount will not be refunded.$/i")
            ->parseNonRefundable("/The amount due is not refundable even if the booking is cancelled or modified/i")
            ->parseNonRefundable("/Full deposit is not refundable even if the booking is cancelled or modified/i")
            ->parseNonRefundable("/^Hotel will charge [123456789].+?, if booking is cancelled up to (\d+) days? prior to arrival. Beyond that time/i")
            ->parseNonRefundable("/^The reservation cannot be cancelled or modified./i")
            ->parseNonRefundable("/^Booking may not be cancelled./i")
            ->parseNonRefundable("/^If booking cancelled between \d+ \& \d+ days, 18:00 \(Local time\) before arrival, hotel will charge .+? Thereafter, amount due not refundable./i")
            ->parseNonRefundable("/^No refund even if booking cancel or modif/i");
    }
}
