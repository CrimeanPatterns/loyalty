<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\JsExecutor;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCarlson extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.radissonhotels.com/en-us/radisson-rewards/secure/my-account';

    private $domain = 'radissonhotels';

    private $rememberMe = true; // todo: it broke auth since 11 Oct 2020, enabled again 16 Oct 2020
    /**
     * @var mixed
     */
    private $transactionid;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        if ($this->attempt >= 0) {
            $this->setProxyGoProxies();
        } else {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
        /*
        if ($this->attempt > 0) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
        */
    }

    public function IsLoggedIn()
    {
        $this->checkProgramSelection($this->AccountFields['Login2']);
        $this->http->RetryCount = 0;
        $this->http->GetURL(str_replace(['radissonhotels', 'radissonhotelsamericas'], $this->domain,
            self::REWARDS_PAGE_URL), [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->checkProgramSelection($this->AccountFields['Login2']);

        // refs #22835
        if (!empty($this->AccountFields['Login2']) && $this->AccountFields['Login2'] != 'Europe') {
            if ($this->AccountFields['Partner'] == 'awardwallet') {
                throw new CheckException("The Radisson Rewards Americas program has merged with Choice Privileges. After completing your enrollment, you can add your Choice Privileges account via <a href='/account/add/36'>this page</a>.", ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckException("The Radisson Rewards Americas program has merged with Choice Privileges. After completing your enrollment, you can track your rewards through the Choice Privileges loyalty program.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.{$this->domain}.com/en-us/radisson-rewards/login");

        $this->checkErrors();

        if (!$this->http->ParseForm(null, "//form[contains(@class, 'js-landing-login-form')]")) {
            if (
                $this->http->Response['code'] == 403
                || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $retry = false;

        if (false && $this->attempt == 0) {
            $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");
            $key = $this->sendSensorData($sensorPostUrl);
        } else {//if ($this->attempt == 2) {
//            $this->http->removeCookies();
            $key = 1000;
            $this->getCookiesFromSelenium();
        }

        $this->DebugInfo = "key: {$key}";
        /*
        $this->http->SetInputValue('email-rewards', $this->AccountFields['Login']);
        $this->http->SetInputValue('password-rewards', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', 'true');
        */
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json;charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
            "Origin"           => "https://www.{$this->domain}.com",
            "Referer"          => "https://www.{$this->domain}.com/en-us/radisson-rewards/login",
        ];
        $data = [
            "user"       => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "rememberMe" => $this->rememberMe, //true,  todo: it broke auth since 11 Oct 2020
        ];
        $this->http->RetryCount = 0;

        if (!$this->http->getCookieByName("authorization_token")) {
            $this->http->PostURL("https://www.{$this->domain}.com/loyalty-api/authentication", json_encode($data),
                $headers);
        }

        if ($this->attempt == 1) {
            if ($this->http->Response['code'] == 403) {
                $this->sendStatistic(false, $retry, $key);

                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 0);
            } else {
                $this->sendStatistic(true, $retry, $key);
            }
        } elseif ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2, 0);
        }

        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //div[contains(text(), "Sorry, the site is temporarily down for maintenance. Please check back again soon.")]
                | //h1[contains(text(), "Server Error (500)")]/following-sibling::p[contains(text(), "An error has occurred. We apologize for any inconvenience.")]
                | //p[contains(text(), "Radisson Hotel Group are updating a number of systems that may affect your Reservations and Loyalty experience")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "503 Service Temporarily Unavailable")]
                | //h1[contains(text(), "HTTP Status 500 – Internal Server Error")]
                | //h1[contains(text(), "504 Gateway Time-out")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // Access is allowed
        if ($this->http->getCookieByName("authorization_token")) {
            $this->http->GetURL(str_replace(['radissonhotels', 'radissonhotelsamericas'], $this->domain,
                self::REWARDS_PAGE_URL));

            // provider bug fix
            if (
                strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            ) {
                throw new CheckRetryNeededException(2, 1);
            }

            $this->markProxySuccessful();

            return true;
        }
        $response = $this->http->JsonLog();
        $type = $response->type ?? null;

        // refs #24282
        if (isset($response->mfaRequired, $response->phone) && $response->mfaRequired == true) {
            $this->State['rewardsNumber'] = $response->rewardsNumber;

            $this->AskQuestion("A code was sent to {$response->phone->prefix} {$response->phone->number}", null, "Question");

            return false;
        }// if (isset($response->mfaRequired, $response->phone) && $response->mfaRequired == true)

        switch ($type) {
            case 'problem:loyalty-invalid-credentials':
                /*
                $this->AccountFields['Login2'] = 'Americas';

                if (
                    (filter_var($this->AccountFields['Pass'], FILTER_VALIDATE_URL) === false)
                    && $this->LoadLoginForm()
                    && $this->http->getCookieByName("authorization_token")
                ) {
                    $this->logger->notice("Set Login2 = {$this->AccountFields['Login2']}");

                    $this->State['Login2'] = $this->AccountFields['Login2'];

                    $this->http->GetURL(str_replace(['radissonhotels', 'radissonhotelsamericas'], $this->domain, self::REWARDS_PAGE_URL));

                    return true;
                }
                */

                throw new CheckException('The email address/Radisson Rewards number or the password is not correct. Please try again or click ‘Forgot password’ to reset it.', ACCOUNT_INVALID_PASSWORD);

            case 'problem:loyalty-first-login':
            case 'problem:password-expired':
                throw new CheckException("The password has expired. Please reset it.", ACCOUNT_INVALID_PASSWORD);

            case 'problem:invalid-search-parameter':
                throw new CheckException("The corporate/travel agency ID code is invalid.", ACCOUNT_INVALID_PASSWORD);

            case 'problem:loyalty-account-locked':
                throw new CheckException("The account is temporarily blocked. Please try again in 30 minutes.", ACCOUNT_INVALID_PASSWORD);

            case 'problem:loyalty-account-not-activated':
                throw new CheckException("You must activate your account before logging in.", ACCOUNT_PROVIDER_ERROR);

            default:
                $this->logger->error("[Unknown error type]: {$type}"); // see window.errorLabels =  Object.assign(window.errorLabels || {}
        }
//
//        if (in_array(, [
//            'zilfo2000@yahoo.com',
//
//        ]))
//        throw new CheckException('The email address/Radisson Rewards number or the password is not correct. Please try again or click ‘Forgot password’ to reset it.', ACCOUNT_INVALID_PASSWORD);

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json;charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
            "Origin"           => "https://www.{$this->domain}.com",
            "Referer"          => "https://www.{$this->domain}.com/en-us/radisson-rewards/login",
        ];
        $data = [
            "rewardsNumber" => $this->State['rewardsNumber'],
            "verifyCode"    => $answer,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.{$this->domain}.com/loyalty-api/mfa/validate/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $message = $response->detail ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'The mfa code introduced is invalid') {
                $this->AskQuestion($this->Question, $message, "Question");

                return false;
            }

            $this->DebugInfo = $message;

//            return false;
        }

        $this->http->GetURL(str_replace(['radissonhotels', 'radissonhotelsamericas'], $this->domain, self::REWARDS_PAGE_URL));

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h2[contains(@class, "name")]')));
        // Balance - points
        if (!$this->SetBalance($this->http->FindSingleNode('//span[contains(@class, "number-points")]'))) {
            if ($message = $this->http->FindSingleNode('//h2[contains(text(), "There was an unexpected issue")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->checkErrors();
        }
        // Radisson Rewards member
        $this->SetProperty("AccountNumber",
            $this->http->FindSingleNode('//strong[contains(text(), "Number")]/following-sibling::span[contains(@class, "member-number")]'));
        // exp. date
        $this->SetProperty("StatusExpiration",
            $this->http->FindSingleNode('//span[contains(@class, "expiration-date ")]'));
        // Status
        $status = $this->http->FindSingleNode('(//img[contains(@data-src, "tier-")]/@data-src)[1]');
        $status = basename($status);
        $this->logger->debug("Status: {$status}");

        switch ($status) {
            case 'tier-club.png':
                $this->SetProperty("Status", "Club");
                unset($this->Properties['StatusExpiration']);

                break;

            case 'tier-silver.png':
                $this->SetProperty("Status", "Silver");

                break;

            case 'tier-gold.png':
                $this->SetProperty("Status", "Gold");

                break;

            case 'tier-platinum.png':
                $this->SetProperty("Status", "Platinum");

                break;

            default:
                if ($this->ErrorCode == ACCOUNT_CHECKED) {
                    $this->sendNotification("new status: $status");
                }
        }// switch ($status)
        //# Qualification Period
        $this->SetProperty("YearBegins", strtotime("1 JAN"));
        // nights
        $this->SetProperty("TotalNights", $this->http->FindSingleNode('//span[contains(@class, "number-nights")]'));
        // stays
        $this->SetProperty("TotalStays", $this->http->FindSingleNode('//span[contains(@class, "number-stays")]'));

        $headers = [
            "Accept"           => "*/*",
            "Accept-Language"  => "en-US",
            "Content-Type"     => "application/json;charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
            "Accept-Encoding"  => "gzip, deflate, br",
        ];

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.{$this->domain}.com/loyalty-api/me/summary", $headers);
            $response = $this->http->JsonLog();
            // Name
            $this->SetProperty("Name",
                beautifulName($response->customerAccount->firstName . " " . $response->customerAccount->lastName));
            // Balance - points
            $this->SetBalance($response->customerAccount->points ?? null);
            // Radisson Rewards member
            $this->SetProperty("AccountNumber", $response->customerAccount->rewardsNumber);
            // exp. date
            $this->SetProperty("StatusExpiration", $response->customerAccount->tierExpirationDate ?? null);
            // Status
            $this->SetProperty("Status", $response->customerAccount->tier ?? null);
            // Qualification Period
            $this->SetProperty("YearBegins", strtotime("1 JAN"));
            // nights
            $this->SetProperty("TotalNights", $response->customerAccount->nights ?? null);
            // stays
            $this->SetProperty("TotalStays", $response->customerAccount->stays ?? null);
        }

        // My e-Certs
        $this->logger->info('e-Certs', ['Header' => 3]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.{$this->domain}.com/booking-management-api/ecerts", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $ecerts = $response->ecerts ?? [];
        $this->logger->debug("Total " . count($ecerts) . " e-Certs were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($ecerts as $ecert) {
            $displayName = $ecert->name;
            $exp = $ecert->expirationDate;
            $code = $ecert->code;

            if (strtotime($exp) && isset($displayName, $code)) {
                $this->AddSubAccount([
                    'Code' => 'carlson' . $code,
                    // Display Name
                    'DisplayName' => "E-Cert {$code} {$displayName}",
                    'Balance'     => null,
                    // Expiration Date
                    'ExpirationDate' => strtotime($exp),
                ], true);
            }// if (strtotime($exp) && isset($displayName, $code))
        }// foreach ($ecerts as $ecert)

        // Expiration date    // refs #10282
        $this->logger->info('Expiration date', ['Header' => 3]);

        if ($this->AccountFields['Login2'] == 'Americas') {
            $this->http->GetURL("https://www.{$this->domain}.com/en-us/radisson-rewards/secure/my-statements");
        } else {
            $this->http->GetURL("https://www.{$this->domain}.com/loyalty-api/me/v2/transactions?offset=1&limit=1000",
                $headers);
        }
        $expire = $this->ParseLastDate();
        $this->logger->debug(var_export($expire, true), ['pre' => true]);
        // Find the nearest date with non-zero balance
        unset($date);

        for ($i = 0; $i < count($expire); $i++) {
            $this->logger->debug("[{$expire[$i]['date']}]: {$expire[$i]['points']}");
            $expire[$i]['date'] = str_replace('-', '/', $expire[$i]['date']);

            if ((!isset($date) || strtotime($expire[$i]['date']) > $date) && $expire[$i]['points'] != 0) {
                $date = strtotime($expire[$i]['date']);
                $this->SetExpirationDate(strtotime("+2 years", $date));

                if ($this->AccountFields['Login2'] !== 'Americas') {
                    $expire[$i]['date'] = str_replace('/', '-', $expire[$i]['date']);
                }

                $this->SetProperty('LastActivity', $expire[$i]['date']);
            }// if ((!isset($date) || strtotime($expire[$i]['date']) > $date) && $expire[$i]['points'] != 0)
        }// for($i = 0; $i < count($expire); $i++)

        // https://redmine.awardwallet.com/issues/20077
        if (isset($this->Properties["AccountExpirationDate"]) && $this->Properties["AccountExpirationDate"] < strtotime("31 Dec 2021")) {
            $this->logger->notice("correcting exp date");
            $this->SetExpirationDate(strtotime("31 Dec 2021"));
        }
    }

    public function ParseLastDate($transactionType = '')
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        if ($this->AccountFields['Login2'] == 'Americas') {
            $nodes = $this->http->XPath->query("//table[contains(@id, 'my-statements-table')]/tbody/tr[td]");
            $this->logger->debug("Total {$nodes->length} history rows were found");

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);

                $totalPointsPos = '5';

                if ($this->http->FindSingleNode("td[5]", $node) === null) {
                    $totalPointsPos = '4';
                }

                if ($this->http->FindSingleNode("td[1]", $node)) {
                    $result[] = [
                        'date'   => $this->http->FindSingleNode("td[1]", $node),
                        'points' => $this->http->FindSingleNode("td[{$totalPointsPos}]", $node),
                    ];
                }// if ($this->http->FindSingleNode("td[1]", $node)
            }// for($i=0; $i < $nodes->length; $i++)
        } else {
            $this->logger->debug("Transactions witch Type = {$transactionType}");
            // Searsh all dates witch balance - Type = $transactionType
            $nodes = $this->http->JsonLog()->transactions ?? [];
            $this->logger->debug("Total " . count($nodes) . " history rows were found");

            foreach ($nodes as $node) {
                $sign = '';

                if ($node->type == 'REDEEM') {
                    $sign = '-';
                }

                $result[] = [
                    'date'   => $node->postedDate,
                    'points' => $sign . $node->totalPoints,
                ];
            }// for($i=0; $i < $nodes->length; $i++)
        }

        return $result;
    }

    public function ParseItineraries(): array
    {
        $result = [];
        //$this->http->GetURL("https://www.{$this->domain}.com/en-us/radisson-rewards/secure/my-reservations");
        $this->http->RetryCount = 0;

        if ($this->AccountFields['Login2'] == 'Americas') {
            $headers = [
                'Accept'          => 'application/json, text/plain, */*',
                'Content-Type'    => 'application/json',
                'Accept-Language' => 'en-us', // 500 error because of this nasty
            ];
            $this->http->GetURL("https://www.{$this->domain}.com/booking-management-api/bookings/future?calculateBookingTypes=false",
                $headers);
        } else {
            //$this->http->disableOriginHeader();

            $script = /** @lang JavaScript */
                'function t() {
                for (var e = 0 < arguments.length && void 0 !== arguments[0] ? arguments[0] : 40, t = "", n = 0; n < e; n++)
                    t += Math.floor(15 * Math.random()).toString(15);
                    return t
            }sendResponseToPhp(t(40));';
            $jsExecutor = $this->services->get(JsExecutor::class);
            $this->transactionid = $jsExecutor->executeString($script);

            $headers = [
                'Accept'          => 'application/json, text/plain, */*',
                'Content-Type'    => null,
                'Pragma'          => 'no-cache',
                'Accept-Language' => 'en-us', // 500 error because of this nasty
                'Connection'      => null,
                'transactionid'   => $this->transactionid,
                'Referer'         => "https://www.{$this->domain}.com/en-us/radisson-rewards/secure/my-reservations",
            ];
            $this->http->GetURL("https://www.{$this->domain}.com/booking-management-api/bookings/future?type=&offset=0&limit=2&order=asc",
                $headers);
        }

        $noFuture = $this->http->FindPreg('/"bookings":\[\]/');
        $response = $this->http->JsonLog();
        $this->ParseItinerariesFor($response);

        if (!$noFuture) {
            // Cancelled
            if ($this->AccountFields['Login2'] == 'Americas') {
                $this->http->GetURL("https://www.{$this->domain}.com/booking-management-api/bookings/cancelled?calculateBookingTypes=false",
                    $headers);
            } else {
                $this->http->GetURL("https://www.{$this->domain}.com/booking-management-api/bookings/cancelled?type=&offset=0&limit=2&order=desc",
                    $headers);
            }

            $response = $this->http->JsonLog();
            $this->logger->info("Parse cancelled", ['Header' => 2]);
            $this->ParseItinerariesFor($response);
        }

        if ($this->ParsePastIts) {
            $this->logger->info("Parse Past Itinerary", ['Header' => 2]);

            if ($this->AccountFields['Login2'] == 'Americas') {
                $this->http->GetURL("https://www.{$this->domain}.com/booking-management-api/bookings/past?calculateBookingTypes=false",
                    $headers);
            } else {
                $this->http->GetURL("https://www.{$this->domain}.com/booking-management-api/bookings/past?type=&offset=0&limit=2&order=desc",
                    $headers);
            }
            $noPast = $this->http->FindPreg('/"bookings":\[\]/');
            $response = $this->http->JsonLog();

            $this->logger->info("Parse past", ['Header' => 2]);
            $this->ParseItinerariesFor($response);

            if ($noFuture && $noPast) {
                return $this->noItinerariesArr();
            }
        } elseif ($noFuture) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.{$this->domain}.com/en-us/reservation/search";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->getCookiesConfNoFromSelenium();
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] == 403 || strstr($this->http->Error, 'Network error 92 - HTTP/2 stream 0')) {
            $this->setProxyGoProxies();
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        }

        if (!$this->http->ParseForm("booking-management-search")) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $script =  /** @lang JavaScript */
            'function t() {
                for (var e = 0 < arguments.length && void 0 !== arguments[0] ? arguments[0] : 40, t = "", n = 0; n < e; n++)
                    t += Math.floor(15 * Math.random()).toString(15);
                    return t
            }
            sendResponseToPhp(t(40));';
        $jsExecutor = $this->services->get(JsExecutor::class);
        $this->transactionid = $jsExecutor->executeString($script);

        $headers = [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/json',
            'Referer'          => "https://www.{$this->domain}.com/en-us/reservation/search",
            'transactionid'    => $this->transactionid,
            'x-requested-with' => 'XMLHttpRequest',
            'Accept-Language'  => 'en-us',
        ];
        $data = [
            'bookingId'         => $arFields['ConfNo'],
            'firstName'         => $arFields['FirstName'],
            'lastName'          => $arFields['LastName'],
            'taxIncludedRegion' => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.{$this->domain}.com/booking-management-api/booking", json_encode($data),
            $headers);
        $booking = $this->http->JsonLog();

        if (isset($booking->title) && $booking->title == 'The itinerary was not found on the system') {
            return 'The reservation information you provided was not recognized. Did you enter it correctly?';
        }

        $this->http->GetURL("https://www.{$this->domain}.com/content-api/hotels/{$booking->stay->hotelCode}/booking-summary",
            $headers);
        $hotel = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        $this->parseItineraryV2($booking, $hotel);

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            'FirstName' => [
                'Caption'  => 'First name',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('FirstName'),
                'Required' => true,
            ],
            'LastName' => [
                'Caption'  => 'Last name',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
            'ConfNo' => [
                'Caption'  => 'Confirmation code',
                'Type'     => 'string',
                'Required' => true,
                'Size'     => 40,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Posted Date"  => "PostingDate",
            "Type"         => "Info",
            "Description"  => "Description",
            "Points"       => "Info",
            "Bonus"        => "Bonus",
            "Total Points" => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s',
                $startDate) : 'all') . ']');
        $result = [];
        $startTimer = $this->getTime();

        if ($this->AccountFields['Login2'] == 'Americas') {
            $this->http->GetURL("https://www.{$this->domain}.com/en-us/radisson-rewards/secure/my-statements");
        } else {
            $headers = [
                "Accept"           => "*/*",
                "Accept-Language"  => "en-US",
                "Content-Type"     => "application/json;charset=UTF-8",
                "X-Requested-With" => "XMLHttpRequest",
                "Accept-Encoding"  => "gzip, deflate, br",
            ];
            $this->http->GetURL("https://www.{$this->domain}.com/loyalty-api/me/v2/transactions?offset=1&limit=1000",
                $headers);
        }
//        $page = 0;
//        do {
//            $page++;
//            $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
//        } while (
//            $page < 1
//        );

        usort($result, function ($a, $b) {
            return $b['Posted Date'] - $a['Posted Date'];
        });

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];

        if ($this->AccountFields['Login2'] == 'Americas') {
            $nodes = $this->http->XPath->query("//table[contains(@id, 'my-statements-table')]/tbody/tr[td]");
            $this->logger->debug("Total {$nodes->length} transactions were found");

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $dateStr = $this->http->FindSingleNode("td[1]", $node);
                $postDate = strtotime(str_replace('-', '/', $dateStr));

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }
                $result[$startIndex]['Posted Date'] = $postDate;
                $result[$startIndex]['Type'] = $this->http->FindSingleNode("td[2]", $node);

                $descriptionPos = '4';
                $totalPointsPos = '5';

                if ($this->http->FindSingleNode("td[5]", $node) === null) {
                    $descriptionPos = '3';
                    $totalPointsPos = '4';
                }

                $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[{$descriptionPos}]/div[@class = 'row']",
                        $node) ?? $this->http->FindSingleNode("td[{$descriptionPos}]/text()[1]", $node);
                $totalPoints = $this->http->FindSingleNode("td[{$totalPointsPos}]", $node);
                $result[$startIndex]['Total Points'] = $totalPoints;

                $startIndex++;
                // Parse Details
                $detailsTable = $this->http->XPath->query("td[{$descriptionPos}]//div[@id = 'transaction-details-']/div[@class = 'row']",
                    $node);
                $this->logger->debug("[{$i}]: Total {$detailsTable->length} transaction details were found");

                if ($detailsTable->length == 0) {
                    continue;
                }// if ($detailsTable->length == 0)

                for ($j = 0; $j < $detailsTable->length; $j++) {
                    $details = $detailsTable->item($j);
                    $result[$startIndex]['Posted Date'] = $postDate;
                    $result[$startIndex]['Type'] = 'Details';
                    $result[$startIndex]['Description'] = $this->http->FindSingleNode("div[1]", $details);

                    if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Description'])) {
                        $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("div[2]", $details);
                    } else {
                        $result[$startIndex]['Points'] = $this->http->FindSingleNode("div[2]", $details);
                    }
                    $result[$startIndex]['Total Points'] = $totalPoints;
                    $startIndex++;
                }// for ($j = 0; $j < $detailsTable->length; $j++)
            }// for ($i = 0; $i < $nodes->length; $i++)
        } else {
            $nodes = $this->http->JsonLog()->transactions ?? [];
            $this->logger->debug("Total " . count($nodes) . " transactions were found");

            foreach ($nodes as $node) {
                $dateStr = $node->postedDate;
                $postDate = strtotime(str_replace('-', '/', $dateStr));

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }
                $result[$startIndex]['Posted Date'] = $postDate;
                $result[$startIndex]['Type'] = $node->type;
                $result[$startIndex]['Description'] = $node->title;
                $totalPoints = $node->totalPoints;

                $sign = '';

                if ($node->type == 'REDEEM') {
                    $sign = '-';
                }

                $result[$startIndex]['Total Points'] = $sign . $totalPoints;

                $startIndex++;
            }// foreach ($nodes as $node)
        }

        return $result;
    }

    private function parseItineraryV2($item, $booking, $hotel = null): void
    {
        $this->logger->notice(__METHOD__);

        $this->logger->info("Parse Itinerary #{$booking->bookingId}", ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();

        $h->general()->confirmation($booking->bookingId, "Reservation number");

        if ($booking->status === 'Cancelled') {
            $h->general()->cancelled();
        }
        $h->general()->traveller(beautifulName(($booking->customer->firstName ?? null) . " {$booking->customer->lastName}"));

        if (isset($booking->customer->rewardsNumber)) {
            $h->program()->account($booking->customer->rewardsNumber, false);
        }

        $checkInTime = $hotel->hotel->checkInTime ?? $item->hotelInfo->checkInTime;
        $checkOutTime = $hotel->hotel->checkInTime ?? $item->hotelInfo->checkOutTime;

        $h->booked()
            ->checkIn2("{$booking->stay->checkInDate}, {$checkInTime}")
            ->checkOut2("{$booking->stay->checkOutDate}, {$checkOutTime}")
            ->guests($booking->stay->occupancy->adults)
            ->kids($booking->stay->occupancy->children)
            ->rooms(count($booking->rooms));

        $name = $hotel->hotel->name ?? $item->hotelInfo->name;
        $address = $hotel->hotel->address ?? $item->hotelInfo->address;
        $phone = $hotel->hotel->phone ?? $item->hotelInfo->phone;
        $h->hotel()
            ->name($name)
            ->address(join(', ', (array) $address))
            ->phone(preg_replace('/\s*(\+)\s+(\d+)/', '$1$2', $phone));

        $cancellationDate = $booking->stay->rate->conditions->cancellationPoliciesExtended->freeCancellationUpTo ?? null;
        $cancellation = $booking->stay->rate->conditions->cancellationPoliciesExtended->policies[0] ?? null;

        if ($cancellation && strtotime($cancellationDate, false)) {
            // Free cancellation until 18:00:00 4 October 2023. For late cancellation or no show a <value_amount>penalty will be charged. If paid with Points or Cash+Points, the corresponding point deduction will be made.
            if ($this->http->FindPreg('/Free cancellation until \d+:\d+/', false, $cancellation)) {
                // Free cancellation before October 04, 2023, 6pm (hotel time)
                $formatDate = date('F j, Y, ga', strtotime($cancellationDate, false));
                $h->setCancellation("Free cancellation before $formatDate (hotel time)");
            }
        }

        $h->price()->spentAwards($booking->priceSummary->totalPoints ?? null, false, true);
        $h->price()->total($booking->priceSummary->totalWithTax->amount);
        $h->price()->currency($booking->priceSummary->totalWithTax->currency);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function ParseItinerariesFor($response)
    {
        if (empty($response->bookings)) {
            return;
        }
        $headers = [
            'Accept'           => 'application/json, text/plain, */*',
            'Content-Type'     => 'application/json',
            'Referer'          => "https://www.{$this->domain}.com/en-us/reservation/summary",
            //'x-requested-with' => 'XMLHttpRequest',
            'Accept-Language'  => 'en-us',
        ];

        foreach ($response->bookings as $item) {
            $this->logger->info("Parse Itinerary #{$item->bookingId}", ['Header' => 3]);
            $data = [
                'bookingId' => $item->bookingId,
                'firstName' => $item->guest->firstName,
                'lastName'  => $item->guest->lastName,
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.{$this->domain}.com/booking-management-api/booking", json_encode($data),
                $headers);
            $booking = $this->http->JsonLog();
            // TODO: tmp
            if (isset($booking->status) && $booking->status == 500) {
                continue;
            }

            if (isset($booking->title) && $booking->title == 'The itinerary was not found on the system') {
                $this->logger->error('The reservation information you provided was not recognized. Did you enter it correctly?');

                break;
            }
            /*$headers = [
                'Accept'           => 'application/json, text/plain, * / *',
                'Referer'          => "https://www.{$this->domain}.com/en-us/reservation/summary",
                'Accept-Language'  => 'en-us',
                'Origin'           => null,
            ];
            $this->http->GetURL("https://www.{$this->domain}.com/content-api/hotels/{$booking->stay->hotelCode}/booking-summary?tier={$this->Properties['Status']}",
                $headers);

            $hotel = $this->http->JsonLog();*/
            $this->http->RetryCount = 2;

            $this->parseItineraryV2($item, $booking, null);
        }
    }

    private function checkProgramSelection($site)
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->AccountFields['Login2']) && !empty($this->State['Login2'])) {
            $this->logger->notice("set Login2 from State: {$this->State['Login2']}");
            $this->AccountFields['Login2'] = $this->State['Login2'];
            $site = $this->State['Login2'];
        }

        if ($site == 'Americas') {
            $this->domain = 'radissonhotelsamericas';
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            !strstr($this->http->currentUrl(), 'radisson-rewards/login')
            && $this->http->FindNodes("//a[contains(@class, 'btn-logout')]")
            && $this->http->FindSingleNode('//strong[contains(text(), "Number")]/following-sibling::span[contains(@class, "member-number")]')
        ) {
            return true;
        }

        return false;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("carlson sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            '2;3617333;3223604;12,0,0,1,1,0;hT-4leJ#c=GB?q$/GoP}d9r*]l!h<SiI{^EI7)iX(kt+$j&%e+j(uB1`2:N>)FaAdlOSD&z~T9ViM,?M{gyr[A%r9qYT@]v(UZSFCK:8oN8fjR#<6gY=|lW)jw4B+QO<wE6A=G<F o3kVGq4pII-{a`*r9?HMzI;o+z/~70E&vEcsnBVO|x`nO>*v6`blCjTGMK_x#$7psHq&$Nwb@Q9M2zAf$RkjNvr]!|7HHgOEVB{(+m,#ivftnmSStGdsSr)u-6]t=>KIS7lr6lF=z+#161(&Y><`5x|}BIy8W[/%/uFj}d94%/3SAfZV+s-84Rx}@T 5o(R@`;&LC>$,~>0XgsdzS;]roTj`5W 6u1^j!i`t@2qoq$BX` `!sZ{D&$F@ <x?{8n*(zn%A-IWMq(TY$v8$x@giMq!W~l+p3`sB:*Wx)m#0:d:Lys5&/Q|wpR Z/|fe(a~(*/k4H-@W#D{2Nr6zvMy+,Pv4wkE8bFb24,qFCHYhFbDj<C=O<wc|-_~i;6r_8sAw}#fG?Mk5U+b&odGKT+x}1hmpOH4~hQwLanM/@Bj6o.$G&Kqv7wYf~V%i]XJ<xtO *C3tz2e.h;&D?hCCTv1LW?pnTU=F8)X>cmZSe}Wle@,gHyXT}Ai&W&/WFnx!9pn-r7/yCW[e8:,0<v~XvpYa%5J=/PMp7Cja)AJ)[![8%kjJ0m`#A75n+w_Wbie0Z|k]*XIKy8#[^=6-zbTmC0@r5+!2)I<eceKZ S]C)T?#{ekXiH1Y.89c{u5A:_u2/G8CpGQD WM7>y,17u_}@RohE>CWOx.PVxvm@(;I!SY9==IGT?7kRdtJ$1?T]3Bh#+ qIFO{pCA7>-SVtVT=&9C-o$(E[$L{2KNaP8AoT*,+??eHgrv]4YygMBV5>sBx0_NoZVm.n^fk|*YItd|]Q~?&t+At 7pfR}a)ub|JC4F4cv9K`p)jN/MD;8]B[BxZwCYwlt:gWVVj4Bv)H[paY&l6;F]:TtL([$F1A<vPK>^ec^W=qr/=jH~?nF(H?6e#1nU]SH?OLDXY;b{f9f2&4hCTIt_A%hIpX5n6/En^hm+G!>!F$?ahhBspz{8%i`C5f[.dnyd9KK|:}R)O4Tt).zb{ Y=%Xc*=*BJ&1E4t-2@fAt-)Kfo{WH^N5wK8)Lmn()!8He]KbkKI/3&)@sE}l,Or)utL:tdpy(d<Sxz]~5n?8fmH9[CYvwi!Jxpyj}q}onP6c+$|2W:rgE<{ O4I~wk;UP8C3H?SZZ@6V3}2VkR/-uxOT3}6tpr0o_AQ7aq-&9jmP~%|Mu[9I77y*Y[&MTc4aiF]j$,BLXLN+]^K0PK,^BF+,u!,^eqX=5+8Pvj:NQ]N4pq,m]3),?,+a+0x:2L;  r2Xu{RQ{m$|GQ`J,F0HET[iaS5s)A:|v+FU(Aj$RNX<-AZF&#z95KYjh]K4?nO6U[|Cr5V}DTjN6i}aHT`ht@/QYrT0X|pOqsIoAgKv8Cd1;j7+put8W| BFY<-Q& z#Fv?kN0A!tNCKd.+o57zMsA4|:$7P~xy<T/W4lM Vs38>K2@+FNi*e%RZxde8qmo5Q7a^/vTk>q}vL%/%EI1A,Fn*L>JdOjo9I;~_<1pUlDNS$mzn65|O2Y$JmsjW1=NvG0pcD;~a(jpxi@IYv@}O+O8G%/:r_y}W+vaWB?rnM|#Jwn% W~h6yO0Y(1V.}CS/]Z3A3-yQ5#AQBvCe3^DYb@)U22GL2My=)U_PQ([`9&C]M?]$O*v2:r{5z4LU;b)NJRFLAO<S+n9VTNn:u:B`hXN!bx@C>Z0#WlEcw&r+OV.OXEn1&YH@7yvG3k;/:]-ylvv)%IG_A&]20Fs3v]^OTF7}l1ehoFar^yi;CZRyga3Pm P[Q3aJ^XR-._3>C$N@69|>@tx!-i_6Pl5iZQSKVKa]W)]~wQIHK`e0Jl =78rDl)ZgR8b71Q0;kG!Af>v~,FS-fb#fgy*308Qb@kR8QSNY^UL?ONFMu:5xl{]*#~b7UY%^u3H3]Jwn [,vU-xg<]#_Uh_SJv#~[D3C0q2Rm|s17OAvf gk<#`,GzFnCYV{yy;2v;,s|Th_?Td<TZZ)p1eyBT/|+yx7${Omu(0W@U:Tmbk;IM- 70T3[dk}1M$ItUv`9eK7J-#Ik)}A?TYx#b-<uQr.3SXg9W+W4:&ge/.{&p@K*>XY-F^<cC6uRQ(/PeA1E{3|Q/ia =MAv|Gc&Ef[Sal7nDsm!<&Ll73~?MvY ^Q3/ynTm$Y0O<~W1ldlh)2RqLYBU#d=bV@0JgbqB;v0~,jGo7<(g]^zQkge47r/n@S3qa<Viu/zufW?MO)rZv7rg30[Xtop&fFfJpduRcqQpVSQi;I}l#yDR;u@H.&dVa::4l34{$#~=PVcLQdDGIx:,lfH[IM$gKn|rJ|wfrx3Kaj!L[n>$)R,kffQc2L8-a0?JyQQ[di7CgcVWRW5@U/aO:K>I;Q=7wHWgB++)CK#2TW.%)VOYhO+%y&U~e*8-d[i(c?IVv.QuFN{x{`Pt}sM@I;Xxr*{ tMj!rZsh>Z%Gh)Yl{aLy5oXDSz*@FfFgZBc+ZX|{SpGjCmDAgX=e-&igKri=L,9C2-:kiY#1b*j-s-`&8-/3|xeyvIR|(Q]|mk}SUOo-_mj<wT}>aee',
            // 1
            '2;4602179;3359536;11,0,0,0,2,0;UjyUPXP-){r!f5&_qdqkcOU-afyd6p)HNw-Tbm{yV3>P-dfT4`!cxhpCQ09/ixl5;pwUb*-/ran-g?K7BAbo qdFcI~P1Byf?Ld>c,5/zo/w2dpo5_TO_@L,pI1E0<O4FC&p$ON;{kD^H%?0OMMi $F&/f%yf%8eb-]DWPl.t#6k[B]Nz4^j*:J16M){m(K*Ag]/Y-EJgt/UO e&<M}2Wdx p<ftj-U*O5t`,l{HMP!ZY;giB_-Xu0PNTt[]^%E,XH0}w2B{%#b.<EZAI_#ChWmc R/5;t>x;6c(];d9>G1n#z3W@j>fBc~&8+bk|T$<A>~%aDqVfpV8JP^ByE;`G[3LO=q3DzSC*&xsUQ}Zf$}Yo&^XX-}S0^3E[InOfB}pWhSZ>BvOz<an!4FHNcM4qA}>]Ns`o_=;,cvc]-ZHf7(K7D09NG.eR%e|HL?i qWOLYI>SqC-Sw!uBV8wqW+,*[vhE)(S}pTbZ|yd|$rg;hn^6[DO!TlNM)t:lfb?vl3j`!{DFUrB]EOS!G;{zfjgaH/U.7UyR)GgTq&N^FYT5fn*Y4pgwiz]oUV Sa|_+2-qOt+U_wAn$^>#Zg^}b,kew/^7k(8I[b7yaWx@&p@HcAZB%15X51NR7-._P^$q#T!U_2S)J^qw_Hc+jLQDlp/1?HG-w7X!49Ag3`%bwJU%0tBixdpb7.Qa~)/|q(L8*%eCH,O)W|.UHve|}r{^IK#,QXiPH|7=6Mg@bBxc#NdXaPzQrfm{He@Im-=+)M$/(CZHkFzq=w(O/_JXRZ %,d@X5>bHZ0bd<s);hG3 Q$Uyv)`n|4x;=j<o4HwfsR%<9o<#T@&sOCb:CL!Zli8_%S=fAI~N%+FQsJqK9={w?5e?MV=|55j*h@~y4F +:}Wt`gR,fg$VH#[9OeS0R^:d<q]0JAo~.&m{<$V)96*1sqDj-:U=VxTKa1(pNSQfxNq;D$mcT7UyNF*p6T@CepSyrYQ+uU:%g`[#K5nyi%xvp+eTf><<^uW6!h_M9vf)};;j9+jqs%1:8A1~:svtFkQ{&dlZbs#;jz JxnNbg|%J>PA;hEFS|J-vxmlZaK@m56B#VD;Y]u3DE^PO-epnR{l8ijeN^@<nNYfD% r,/b}5LRU_6r0:qHI8>2P;MrG2K[w_A @[ny[:/OSXUV`6EaeZts.}>)2;S#!fWS[~;p}Wi5lPFx>]B{( 19eYoozY>c}gWrzFZ9Q/Mu}3SoOY6*5+L`$%N?K=(ZflbxTUhSvQuB,C60a>;}/EUk9Tre!g]<VD:At`yX&n1[ 8=5W{#r2n^kFhv$A:5a/.%GcDnQ4k7,:5^R-h]^v-/o>U+?_OI0goW3F(fFvl8,Gp!#W#5%~_LP3^;>_]f>}10]u9AGs]9)T%!0hMW[&8z9~?$Fd_oQfKM@Wszoz2ns&(_>!cszN.RYk&>uY#V:!4{[QvCHG<AqCi~:){y#T pC{T5_4HeR?IdGiFpbTZ8=tWs*yo(1m8~b[~louHM-)WlC$3zzj~fnO*O}f-~.ox7}]6/]Gf {o>8?!.|(%>1.o7|]?9`ylA-vjTE)b~RZJq5*r|m^S}<?.zU!}k;eHY)*vXN:$HdhpN{q^`k{(G?T;5l6FCsH0QqbqYR2)W3-3hp:J<Eln/9(F^xKZX6vb?E@UE>3*T)5Q#beS%) Qu.Aniz]dvd-bxzv;+3xuJ3BQ+o-%:Ky(`k).1uXq*eK2,CFX]aX`7&5R0z/8+h0Kz?iimU=k#nDLIado(11=yry;<!*:fs_iK?3KU6/NmF~zTmlC@bZg+@~#oFcnD]i(co;NqT/^4#m|,?_/*NY0JGsa71bh;sDZGfOdzn-@^mGlh;qx5R]}-*d1m0A^wlq!b:w1TY^/ZP|o->Z9T#bG>C%FIN|:}@6HfGt6^kqQx}u?F~I~?n%RL^y]w~CcM^qrGue4aBwN1:6btT~X.Ntk?f-ew7bxzZQ$#YNyTlqPfAgr;ov/k;OUbH jy0o86lgJ]B1M8IXH6GO T1^Axt(`s0s6t~_vB8#hoa[2i$VC[CD,)9%g4~R1CPQGh%Z=9 2}/u$]5SZu|-5!P7tlNFyYd,{zCWD.(3=j>#8vb[V>@YZgM8qm2F7kyIVDC7,loi4 9f8Dt@iisCZF~{geOX%t)`ol8^_AJWms146*/O$$c_?|MR>J<=r5qXSU,Zzo$0:L3KYV^4m!w!6T[oK^o>9;~!7r-)@.sVw_RQk#P)gop^.lw;CeLjLP*Oj?w`fdfsB&Zf6VV3]n*,hYXmszw/xw^MZfS9IQs+KkK_s~8~btvQ3CTo~;?LD]8W>V=6JIu1jx^|J%`I9]{eHaRxw7}mzqV@y9-s92d)r#c|Thzoh1^P)DKoSIMhx*}e,aWRCzHw[*i#^|8;&W]#~~&Xn<Jpu?,!Ar#u;S*VKwW~^v?A@tFEsUT.~xRdu(*%i19na8Z3#WG RkxG^(VT[#9Zx1{ee# 0_sON{9#0K?%O7`1B:{7d}/I&R&L1Mpl~P7FS,N5IymN#~Ox.:|^gr4`DeG*YNHD^E0BH0bX 4~tPcwbp*yQi#@u[sv<,M|=:&yC?(Th0,&-c/N4Un#Fi#O8}9vgMrT_tr)M>I*sS9sUNc|@AA;5b*b1:X|J)*4oC?SScUYm|K=JW_QG_)SZpUjsg1.h#~o+nlzzrW2hE#!a]k0nEB9F%N*0LIXBv}x!5U7EKxCMs7@Sf8y3B(6E[Ami4L)Kc7-8ke,,zk9x`P6t. HpVz-~#QTlnd<1zpP3~*sdmWz1hy2_TV[FSab/s/&u$ RVNF,g?qNm5I%EH/m[&RIRMKGHL)W*B#gx{Sr6uOD TOEJr;i@wTg$JOp2yJ<)xr]F$+%gSW5)3`+.R9$#0+_e(%81m5cydYFb4o7<OCm.H5pCCzXvCo>/,w1,<Jjj:su_+7R0X_YMZw)%,5/hz}=FSe)0L<8wsp*$16t9CqvO>#ISmHrCn{|AZ)Eb ]/sBeZJ~nOD`~.-,)X:OO%fF2Hohug1 /Qb1I0W%hUIN+oymB@U;xVH/ff_]nL,;+ZjNpEtMb0*;uZorKjdE?SeB?f4@V:r+/Sndsk=yfd_xa@]t2keCEy&[T|Vp zTs Ph&%/I{KY6T5u3S1gH6E-+qeH|<]F1^eE[M,zN3W?IsQJa%n76/5*,wMWO]umi :|T7rX',
            // 2
            '2;3619120;3617585;15,0,0,0,2,0;Q2F)CHvhB3><Ykcd+Ejaogm_q`gFa@iFm+3w_Q2c[lXA`-?>i9)d`Xs|%`&l_-UPJe4VWR.*StI(K}z{MonI`@|u207}rhX_W&T,AH-%lgtrEq)+]9%A6dGThp6T_=-fppz&y#ByoIkGkk@K{TCKRI+=&6H?8pZQjY..vvYeip^4vgo}TBC=Vo+Ibs11@y]m j77_63EEVGCoSl.KPgU|(P|Lh#`(nYj!mQO>K[y#6g}eHm&G=;O:E~<Q:]SQZRo}L)gRh}B9RRYH.Mm_SRrQc!=eg%4J;X%]mKY`}*Rd/!syw2U!$b8n[GkLjJ@$qa/QSCRQ )gh<!QI.b03-=@2ePp^%Vw8m3q2fkl }2UnJMmA^3dg]%=!E9mW&n(LYV)[e;%.[uFmDc{TqMt49c~T;|jJ}e/MWvF)_$M[uX7f6KsmzQ4C,gr3ocB,rd,W@i[CJCR0nDr:&6di!]IWm=kN+lsrNFn.gzL>.b*H2AOxTrKRrWC9^2zpXP?A/}S(JgQ=HawdRxg8-,.?T#/IkB #mg]c2N7n*>A#FY7iG3x^BwQx5yq{S;wzg(r}Mf42d!wo2N%iV4(Jh3 0^_Hp8;lnd.{#$~P)T1KMM5{L)54|mL&M}%!)nHMacjp--6vrFf#{>[X-.{zvzV~Zt;>vB6+;9:aw!,&zX*./D<(khdui_|9h:HTd[d5s~-=>w(pv]?;)zM&=L,O=Q.2;CUX>w#}o>_vHAImoG#GQ2gddgBiwcrs=oBUCu)hP[!Bo*K.S5)9S<qxew%BDL#O%x+?4^-1`iDB;Zhw^t`&N/xNrYbK5Kpz,J9;~gO-]tMvzl>uYz7h,HeAA.9l7W.EX~B``,ANw=wF((#T{tk2TQ{Qdy4ek;w]D5Z2+,+^5S;YV~YX380W%!x48jzId8KSIT N5E^,oC#&$D92?=DyNCJijC`=Q($aR`&kTu>bDijD{`Sk8vO+dXDWoXko<}#MGX<wr/cw]B6sx<<W.;L0inxD`ra&nNk5@X$a%9i8g__MBrQ8w+;d^{%E|]}e2p7cz.x_{3]52uq`iAJrVXvmWqpOq^?1avi<Nl]:e}Y8+q$x{`W9 EYPvVyGnj?*nV{_sp#x=Z.z+~vy0!q85k2}h0[8d87+bk:u&XLDot!gmc|fv[a=%rcsoC`c9KWaG;t~Ejmg7ZPAE<*6Q=Z<Y-W_`)X:uapqJK0&(y{rPa|qst/Db_Q`h0fowPLcMJ>909(v:y~*a671LMex@Ce+H-`spX`~rpMLor^V6au&g;G>PE)Lgy8/1~O_qd1)`|m;9N*1a1Y#_?GGnyVce4Iao[3.fN.1`88XVhkc@^-@%wF>nf5T>>_]xgZj2wbbXqxS=_X{A3usgik]-X6U3S7+0/nEY^`JOaw>i-+E,~7i[QnwIlPi.tC_vE:`&-4D5z&Ji.wI3,Plwt;F#I9^GjMW$lpLykFn,Ig9I_&N@)A4C4T|0#s:u^Ea t}Y5/[5PeK}6a(776oAKAr4sG?kbv#Qhy0T//$;>PlIlBe([xS>[Q I9y^y5tB#e^n8=<<mva2o8j$_hn)+=B{5N)vYW7-S5$XA#*>i,l?cOBN)1E6U.@}UG %rpTMud~5{d&4f0m<4cGxJ>X4Ol#j=9|&sytn:X4]G9SmFm<G<`Thb~6g{DfAz u%x%KcF>f$mf3A$_H7#WU3i/#?@k(}YS1?Aual2<o21dXm]2!lcHM$n|TNW!07 0)IP[0aS=k7D@kku[`AB1.vS}Ma] a3M^Jt~E6E8nb@;E)F1|zumFdKBf>-W?eYTux@|Xk%v%P,9FxMN-{%=&5 +R!7Y~Uc_+1D`-5KOT6s#9vWD);BWMUER*jY/rS_[y/Yn;YphveNcXU>I*;DSAwkrK,A]];H+e%avHI}5?,v*T9x$`<H[gy#+jEvq-qy]&~Oo@_2BYH>U{oRg;&5Lg#&&U_6^H>Si.X=c>_8KgXEnC=%+9ZMGSie5xvCgkZx6i-iH2R|?^UV$SZDaG+C}8_J~RRo~jlo]0MI%l1U!NVe+XWWt-TTGn2>j_fBEgqPaRrmT6xIv9BG,_h`6;|/#Al5KMJ9ry^Er;P8lzJO_8AKf,dWIY%g.X-.&8WMwqL-+6s*5NLhonn5a}S($$1lgtgwMsncOtmdBbm82O*Qg)}aO1:H<H,nL@.D^[#$IQ lepF!R|5ES&#~Ww!?}I9&diqy?Puvhq+nVC1 wryD!Tq(HqWk3-:>h^*v?tJ1Xm+c9pQp]y^RtM6q%/X~G|/_t]qpF,&`g#*J.CHC<SF}/MgWkM]~5LC]3Jlw-m!XBLK>0LHv&Y1G=4fY?Dni}]GyXhF{JC6U%{SS=JM+=9#Xahubr}W0@$lcW%p]otwMnNqx4 QH<UY@uz}lw+@bkti!<Sf7<+:L2[Xxo/KDbmle@cE,i(bRy(wRJz+&.k*n{=5j$kNP6VKVp%*!A+J4a0[h[O[#74-v.R3K,otlA}_$Var;t$|Kh^rpc$a~B3>s5uzA<o;{C,Er#F/,Wvo=tu5Su(U_dUGxEL1Vf<q?_U%cn,5%a*NY]m;sA:6@O*?tB4=>v[#i+-d7R}!)Reua3Mw3odWv(yuW`<:z)<?# /gmbZ0lD}y!H|/6<MPw_~@#^V;yc!I>1z{>b 3;{XcZ5?zO4JpSsy$iKgh8hY?A{r*5QKTeK-@S&zzO)d;],IJ*_8E~DJ/#hsRFbXz1ud}}Uvl<$Szf8F`TBSoRa$un]AQK%],F2a<H6ZL]cK8g9KaaU{4PJX=LGO9L#o%GQ9IjL!xI?)wI LPdew@4u#s|t,^-eRpLK<_%p?BZ',
            // 3
            '2;4337985;3228980;10,0,0,0,2,0;I `yUi{mBDyZA+xj#oJR-^~J#r0F~D3_6;sMuA9b<MRY*,nXdfJNM8ITW=|s<rhQQ|pE|>TPJ8$zZ&Rlwp_|M.Jt#s3W_uaV//kYU[|lrH#|oot8k)%/}`ydef*:5nrQV:J{k6NPW3XE<YCXss?3vWD (wOxh]J2L29vv.ffvVw3Ga]|/6k#um3{Fp>uub^QUxdw2c,Gno[jydm[D=MaY_?.(zc[M.N:KBH^nF/;la/A3Yh.Nj;v-{aUs8&N`EXMX>WTMI6Ng[5E@c`Jh4Mov(vXy.tOgPej8u^=;bs4J<~5Csza0IhCOQ=6DweCGNdu?4YUmF&[}vgw)T*Z-[}TSzr_~GG$,Bgq.&Dy$hd/MVT[VOlVn`uTl/(%HqrY!~O^!Gno+qn9rlj8L.J8lV}Qn/(,k>.9UY-VmkZr+rwFb/.YuZ&KN2oJj/8HqFNRh)hoFdAcywt39=Jo9pNA&vC/V%39%K8`,At?{@,1afwO<1Cy:8qYGEzmHnx r=sg0W9a?oNtFDR-y:FpK=},ii@<bW]hOBWi1gCje;p6S1+wj&3uWJ%/&!hp@x<P95MW2g;%aY,a8=lO:n/Z=N5(i*ph!Mk6%K@Q8[gvCbj Q|0,TJqeYQEC@Lc@6cWI8pQlqE B*rEDd9v=`W9`(jn*)ZO6n^*aqjy>F|vYhejGfX{NX-SuaT2BK$vZ9;I:C|1U3cgP:cJvd| FtMoiAi5tVaHnZc(u|&Y|ltkx 0rtDuLsl?{hsCuwj[[3fMC,}LrD%5y8ZY^F$5O?X.Wlgx:U@i}L|jZ]HWUQL)}5`(IdQ$R)23]j4SS>(dMQUTV<!>F;}@ 2Qxk4OK#=d&a*MT ESfPo/>w7aw3s(0% vr|/@zO3Z/6vq=c6/7{p|pb.>v3ZpTB<DH)Z[E,1F{Yp(}<Skh ^}cB W<,l]y=.1aiW/,$ewui6iOO`&p[|wz|1 M]0r[?XKN9[CO=TG9>W{cuj k8bxX)K}_H,hM.&SCrl!gda7+($xl]KX}/k9]nDs.nH|!*$HuMfq+,]CYV>kO0yek>5J7 _1lyyLwNkV}I(MVe4>Jc*hM58us_MxVj{bSqo<hDxc5oFjd3{i{?.KH08y(ag:~8G6,O],X<ze7-K1?_UN[|d8=0!_!or<9YxaMs mB(kT7)PDAogM,F1n|fm=XFSO7U~u~9W?jgJL!!P5R(ty;^S1A6GUR;cC4U= HQu[E4t!ng;~I|u|E!tYef,u;8@8-R/*8`3y[fIT6@+k@bnmB`xq$ CPts!mJFu&5U/ nmY|neW`rLaiDuXt/?Wf0{4%Q-]r(_< v~})*-qw:<<B)jnt34deZShv&Z=fZKn<os$C<E7,pTq$fKe`&Dl(Za63C+kn:$faBs}:9VN]3t~fG+>3z+RbVf<7O@4Na=@BK]7h[!(NG ordTavSnHc&ClFZD8n3zhow?Px ARZJ6i&&%)Hk>%H-l{7YSPu;;3F@c):}$4KA<pK0-; <e8 h7l-A_GnVS=W +RtUcv4/([/n9a X%nM.fIl:;2pMAFeoGnC==Gf]rE(*p_yj21iT8.8v{}cF:MZ8QqeVW :@R+hvQ(R;Gi(m&sb8s^oQUe*t1]c5A;bhzW?Zmq@_Ar-$Z|)4v,sW@qLF(Vf;k_r p%q12sQF$[ZN<j6j.>1tA@!Y0xp,cA{/Hu6KpG0.i&biP_9yBSV8ZnV}qJ?)oFw8]N8`7mG9AWFLFM0 :ae^&?zPH)}eU*})OVVq=1}~ht r1/vl?aY_<C2mfJJ/3Umx?DGsA6*05FKfWq[X%QV[iOD~ss~^fR(`193n>88CJba1#8t,`>E4#JD -)/&1nb,^t%U8/c@Gsv;K^P]=w/4v/N(0}80A%+T*?O5m{ 5Cl$OV~%xT4WqF& if(1?@7}.O_ZAJ |w~4HWd%(3{j9:tWaNlv)4A5O l&E)o=1[TLf>S!%B*t5[=(1 RZp,R.G$|ASU,i`(Log}@x2>64:xJcQOqyQ`~/2}n<lWTzpOuQ}T0ar:@y3qpVfy=XnST[vmd2oS[y5oHpH2fCMlxvDVoe #9nJd};ZGS,RPav5uM0`a%[1WJ^=[D`u1_{V%Oa)w{ZBA:p6mT*1h;4K#w4tFVIX-i+`,n!IaL:gwx_h-GB+vSMiE8u?y12e%U0y.l&Z]t[<Pa4`a>< )UQu>:W?7VeD[<j] i(rt)x^j%f=6}qjpMOzWn1}rmTe9,T@yX2ms/!o3P5k[S`IRX?|]y&B}4C4iQ( 8LsYl.2 JSG*7n%)4%#CTl72(EtNzzPZ8|cRP {iFt`;AhyOaU_=;a!,$Y>]7;qXTZQ`#gDkfr`HYLa+0a;)=0vAQ.X:Lcj?%}m^KPPB&TF@mPRchHO6@nJT[-`H)E5?8JjjBpyxh@92?Jj!QShWe++%3oG?&!(;+9 }V1@b*sm+)2.Ur7~ppX4OaH#wl-ip,@7 e$ard40Rs{OF?Nnq X6DOi?FLIVjq1#+fOm~u=uv:O:4B3_QwjbmAu#tf_1NSqqqlSr2mv!9[#_xd!?_m^R#F5#<$Da{.QWZZDX:,t?`}B|P.L)8VKBr-R%,>[!BV%713sk)oq5G)XDqPu`k58+Z9)o8,1$PrBTonV4hof#Q4_Twa9v.}YXlOMv+Es1f&Wt4)?[:PLn?tfA|rB.F})6eUDfy)j0Y7e LSK&FST.Z`LD~s]/f8GZNt?X%L5xe2f3IZMf4>RqpM~)imHEh@_iVQ=W&N1PN_hyJWco=SLWy~VZYT--d>R}`Gc Jpf3zVJg8^&RMo jKI,77~xwV8LqQpm!d4 >P0#<+FkKLo0xQP<TQ`J].m:beVn4}EEi&Y=}e!A<3mp3`adpp!h&cJ `=Fl,~N;,4mnBnR%zyQ%iVw_srh-K0sV.|0lwgDI0,,$_[mJWM/]RIZhp}BDcEF^bPQ.S]B<:0.v5a9vl?Y9.)BDi+IQV[_:zu5c<32{|-%w:v:x&B~fWw(,1d/G`R]tV#U#afZieonzosH(@SMi@/L^;O%9PTYG+MQZ*#!4,KUZ_cN+@nJ8ITs4_|cuF8YJ1O%R92PVY+ta<>eRLYKJ`d7K,J&/&{;/|Aki?RPq~>S-i-u_8RO%gg;^4rOrL',
            // 4
            '2;4539201;4535600;10,0,0,0,1,0;xI9VIWO^)v<t0TGF=?C-iRwv._E`1hX?,ha1nyMo@[7t&PF~3;I4:UUxxe9SWq<NIMaM^8^XJP.=JMHinCp|NIo94Gn,x]L3=~~[Mj@7;r=*R.:EP_kzhb+VBsM]QC<,3$!_MvPTXFpnWtm29SXxjg?qw9ay+p5J]IQ2 &cdqE#IWp{Gf{3@dX]^onYHgit+pnIt9{Z8sepL9T#{5Xkzz#O^tI,gnNfuxtODK&[[WlJR9S]ERMe)+D#j-?1pVhw6LIjcXT3;rMCJZ#I} k2gez[No0zfuo6rWl{%4UYc[S+XbmP{K.J)#F/3bZkduV.<qzHJN95W+*z4G})|N;?)Ahjhw`B>aU-6i0S?v^W|^UIa$e6zO=b}Mz|#Q{&%/AK)gxMeqlk%}z*F]CPk>%[;b_ 5p@@Ee|Ka?_8RrhuL36Bh.x+=VoY#e4r=oW[b3AvDyA/AQsjM8SP}O1*sOX7$Ev,:,stvEfXva,_}iXtF6^kB`d(4!f&`6}*o#-6:bNrm<a=z8G~YSZ1|<#~]X!VY;G+nrXy~&)) d<TN$N[w)i{?3<x&?:0M?=1[ZU)1-G1bu5KI2k`,(A*0>Cgk<|{D-PZ,,#RTayxN^;uhj+Ut+<l&`gw#azy(0pmERg2^Ad(t^#rQA$M]itKC[]* 7)&xy~T50.y1y]0xc L$nkeZ,XLgsySC1~^#%XEkcpC;BfqhSz$xY4OHrZDEYZ=}dYt;.R7^}2t~avxb[%>QnoK>i[WUEMj#:!c2b Cs!%)gN1`2cf0Pm`eexj)mxmkYG<cL6nV5q^[V;l+W)$Q{om^e=:%;VrYNga0MNq%5(gm<)m`p> Klqa=M?n`w;U!W^m<XJ(oCA?3]zuRdgPY{2f@CK;%+)AoP(MEUgvy.R%X]wSXb#X74[&~}p&uTBsahU`nw.|Yb8_l[d)RfPc*88ad Yd;-z5e?*h;{B`bwH!!!>M J%BW.DRC-kqu:^RzuaM_n.aUxY$04iu2~RyUm(7Ggd z>yeEDukY<iMOd>(ADe/kt~=%BB!.fLr}K$t[V90$p:{4Nrx 2f:oE8W?o@IkG5LnEvo`WKT>;jKmxde~,g)rB6tY}@uCto7&-ur2],e[>|r*O<2_j?yq?Azb<{/OD3c&tUGIIN^Q[.(Hlr]#@(YC/9qoE`?ZC3a!19i[}!P:hwk5`Z|Rna1,stWV~EqFAU&hfVi]~#,=J40[2/9n7zTQEPH;,y1ovdS$#N%3V0jp-%)M+4<oI/yd$*tUF8,ad|c47z@KS~A,op]<&%GnDD-|BW@MDo`3*C*.7/Md)@jU005ti|<mNB<LBc[yVfG#-XE^!3PQ<Ax?V]iMDZeR><jHUtY-|*h+k_ niwCe;W#Ij%6dg!J*{($AB]l-93vr<[P>Ih0I4P)M(.@2)Wum[e^*5?d5<**&_wrc-xh;tA{<t%LxEo+8+o6htIT%3E9#]6i&IX4jI}hrp4I|N+@{<^dESgGc{B+-oCzBT|U3xVJfUt#;E}Q]Js%D&Lo;ay(+V1>Eb,oS7 p(.%Q.0qVO`wvuGv>s5[`dHw.5nC!>;n?gS%lnzf?$`4=|F7[}d}!9zgv7nu%j_L!:5O.>^C(9JqF|0>wNpJ[rQ<NiL$ ckIRD>oOr0n] )H)iH>XS+Zi)Ru@@-nfSdY299ql&{/,H`Bn^?3qU(l$C71^nY</hnIJ:x#u5cW9z!tL>wpWL&e.KCFv6/qVsy)6&7wmm@77n@/GwaR<1{acJHZ!HK/8>P7vaypiqWkq4Uz_p.(bpQ,s==@yQW@ZNp!?96OWlL_j6k])H=Sy?4)4,2w%ULHZe(:hf$!y]=?V2=V$]RUPfPGvikfo9MGEa9Gs{_{%dO#C}YSE^,kA({aM)=W>%-7WfAP*;_noR;MTWJ89UHoo{!{iQe%w[swEV9A!D]m&nd r0sry6DhY.$%re)M=#@CZA(:y-iw3(v;^R7KDvr)^(qiyt*UPIb&Xu[}b![j/yvLxmUwz!b.3alzgq:Uk_/DV>yZ@a?[}{)4)u8`hR<FjBFaWh6ibd&|7oI+i5(DbaoN zj1Gy:xwn/59CrHfb(S:o`:(^MzI.^.vrP=Kh7l5jMjpaH!E1]*sQU(~XH}K[kC-7Lc*^775{<6n&0Rk4JqfHJ13x!;e+1-U~GV!X% aIjQ^VQ3eyK)qQkO5A&hB[&bcs~T&hu9nJsU3yt[{C,eZw{q}<YnIAh{rlNT&sYOXkNSoc}s5[H,/$UN4%RcNtr2^NjG-NUbhb77^*~+Prc8+BaVkbQO?l;1>T|c8B1``rGsPlL$Qvp_Wayt5uR;}Mz)4a(tIVC?,-#b+]lm=vqWuo-5Wb~d65!j+S%yWSRjRZ7-X7Q] 8cdjOA_h#@k%@Fhe>$jSh_r_iW`3T}~mAUU^yB= 1.L]0Hm6KwRIX8khzpNK*;[F!~4UF0T(zXQtO>LpO+2p66fkSj!]8`xY+bs/D,{g.b`uWJ@l%aan~}JUf@`7)k/{q2~c~a+auMKoVaz//n)!Y`k3FRr2=n81 AO J3X&[1H_SXp=B5!Gw4?q/t,l<c-2*Nm;[:x^<c$?G@iJBnN5,Sj7hN&MW~Rb~QjKuq>hNuf1$$a:,8V^.39^$5QO[7[o|>s@VyY6%_}Q[cI{[$LV:i=h7F %hd8KW7U%oKaPyi!PYAoXfb1X;;A_Tkz:91je;z7gkqJK/{T,4UBm0:O3%E4bIa;DVM2VcEsqSS7>%)[,UkT@[i<jV1SO%BoPaGQdx_:/)<}*ts#07DhOq^oG!hQ+rJ.?i[5n;%<]Yf[`QL>+KplT-@_F@,(cV8n0MYKP7,x}k+#{/)qg+k`U+Muj]LF&+eNr2GDN>#Q-47?`@eA;m<GYv<6ffZe:N(|LQx&Wu&i2*MKgg2urE yeKm]xk',
            // 5
            '2;3552564;4405046;9,0,0,0,1,0;%OHmOt5{L<~|PBk<Qo:,ZpwVYPVV%ekO2a,%+zuYF!<jPfaM,/J*(CH}22nExQ) ZLiLM!9l0vO{qRGI:d_gACmP``,#9CJFNyKXRFdK+.!2U|XI&YacE|sr&-P]&xD?sq:Ns@shM]P4SQBEuRPd=|-+@Lr#Ff~M}RUVbGrrRfit}.|zS-Nb#.!&AZ/Iu~HHSRx<Q*~vm m9:M;U#p,Adv%+BJ<X2>g5`2;,5T3Mcw~=Px]nU2Y~mMbK{k0W>`X9<g[-u]<)8@(9P(r$eWfvK.FV0J3T3o3<UNbr@6q06_h7Asu~|p9gZSp7|-uA|Q(Zb]f.pbNd`AMb<Zd1#jR)Ux4W1_FAqJ&4[WtG{qg%Oj+TkKd r|J~/,XvZ5xZTwV)Wd^DoqyCQ%J$T(Ea_wO!.1IdO5D()aDc4AO7oxy_jKnBm]r0GH?!.0g6(eU`F(o/k 6)djzOkO|h2#9br wTk&[36chZ5C)jC=sZ[gxOhI-lL+X%i:;VweA(!j`ObMG!e#<3~;Te[9 iS@hC^wd[)G~j]XXsnW]-~=ZtNz3d5 kfC5kds-& Bc[TC:Q8SZS@(J|(lB]0oePJ9:HPd<@+_7|reg<)=4<+zV+1yI(a=C0+frL:i-=@n@@|97[3xn@y|EqB5h,zaqQcNC7%S(-@hLXARSt&EOp^b0n;%b;{nzh t{es:qz%jF!LSnOKts++KrSexQG/x s;GA;,c`au[2FIdjTT+AXXfK+EwE* k!|ZMdLE(i=P1p(/2n6ZBPwq{lOx8 ccj$#LI[el.4Q{%Pct5XFR2Iz,713CdN2DFem8Gtez^>o@0{HH}Cu;~Ct2R5@e3$W2zGPv8Y;tZg+OG>cjWu`ye1w{@6Fb7:2:Ql!NzF+eWTuT_1%.r>h`Gr^Q<.<)H?5H[CP|Ek!]DG~LW]gR~cU-tJ:.$|/`5M?UydzNwG`1z cEOX+H3d`J4y_%e:pK65CbyQ$bFu1QIhK-0EzA8u}SI+6N*=I9tZ7Ws_3p<+G!w/ykw.`=<ojbWH1hAZy`Han@_O2wE*f27M45KENwvRdZAS4tb}7 m-%OU@ulD4SELwdPv6xNM$HK_95v_$LD>Zpf0|YD2|MJY}o^c4rb0t!,H28rd1QHF +sVOi4lm<&JhlpDgWBPJXq(p(hj&WVm9a+%rX`YL!q/efwMkg]rezn;=IqJ!`m?[?Z%L:s1ql_9Rcw]k6W{S--<$:L31@t=Il|Y8]|3`d)XgG2gS?-?TX?YR`Q0`(LN=Ya-cZ[]cY?!,a}B>`u,[?}`41^t{3$`YPDM`nfE2o#*-p(l2lfVB:@LCF[7JdLf^~[;/2`7Zm2r>-y{n(J2b`{vD8!nkHB[d@v{:8RCgkvUXhjI*CLqCB^I@uEeN.; {@~XAx*/@=bRWn<,.V4k~LksDSGI9ry}6]b##uku>VqR$gP[({BQ<Pe%B8v]rv>*2x<Oo.MUj!l~HZK7]crXCY%SP*}=El<tgv7Yij}E?2U|gR(zA^_YW<L3N#4}9F3j}fv?+~6}-[Z`427%{UR($461kS(AIuOyWf|${EKP5Q>Cm=47mVXc@Jj6N3mCEmO=r{}-R>sbU{7`&^W$AoZaQBEG}0d@hwsi%?eQ3eXyvC{/IEC`l`68,,zB#-j=l>%%5E,sP4/bZ.r}Q9lDRL0^Nc^p_fL2]*!vCdA|z^e=7m[zC>Bmk,i$x(,4;?.$Hg^TMeR96s hi@Fc45%t52xx~E$iVp_Q*Y!tAs[XJ/D( jX{X]S~ZSD#)Z/|xdW!]>CPWaSC<`H4p##&d=M,inc}*&W(^I|sW7*,l<nVjfNpCl9gM1z*rH?e}?r(:WPXQ,C_Iw}qrs12S8G~9VdPJr+FT6C0:``hiA^}`#xs]Wz#MtG%qBJ?%.?p)b,`tCReMO#/%]hl>3]ez-4yf4XTAS_6Z+Y;X#8VLSc4zs-mWX)>gnrkk,WFHfYPkd2M8^-Gz6W6A 7;]$H_+@`&MgI;We$]!iI<d=U6y$Mwtp2Hv2#/Vexv|K5,1mye:uS=t[t[US0gnn/>|9p8jz<MC}`jtOC_$-eY|t;isAnLVMz;E:gL%?h`&X9@O<,)NQ,&&~Mw)3GDKU2_6k:@fL?SUDF,P z8zMo:%z &A%J 8`f+i9tR8C?T)xe//uV<+V?I72E,QjZB]dSw<=-63h3L5wfo4ylX(77L95xO|E$*N*5l`_2r*=wVUH:j@-ef{sayXnF?)-0 IceGHc%^SIrFfUY9WI&-3KIXLY<Mt@<9[ kgH2<s:[Ks63FyH4>:DYJi,* n4/| Uxd[UCM39ym1gswTD-z0(HO 5B,8UAPb&t[:L2BSI&#N+0D!^cUt$AU!nJ6E Vf&X0)|,0;AC%.a{[`N//2[b%JqgGCN>t8L$_ZPeXu{8/%fXb%hLY]`A_+TbLDW5yAW=zzsmv6.j*W/y@wbyHb(GlQ2N)&7.rBcEZor%Gj:YSgD6^%zka8`7i(lxTg)0r7`P=ZQq&Jf@->/yM#Sn%;!1xD P~8cRV)S9>Y8%3l~ERg`Qz%@+X4f>!czxMn7n6Z4*/][.Hm2`q7g4Z1/4a.j+|XO~`$VLT7dvq5<rP}<su<=K&VknA]G;yYQ p>ByvtovvEzZ b6nzLSoG,52t:.pbDEx`Um+4jX!iFZ;hBzm S`eMD4g3g`B9*}gV+y.@0i*/i/aD|bBo>Qk7?VsM3ygpc:P9uRl9B~4FvP7fP2*Lw:[,DWuZB:7/94/#QHn_+k,?EaR(z]N}[tR;0Xv^LD4Q;1,)8u=sj=7H&YW.5wnN5,!um]gaAH9QUcwyeWoFEDs|/',
            // 6
            '2;4276545;3552307;11,0,0,1,1,0;g[~#~N@foTAaYx9{3bxMF]J0HiXAJuzRt~?<D2#?[I6WX[enD$4au7 k5GF.8>Ke6izRr`Cb/^{@1q:$}%M`#J3Df[<o{#Mb|B[u(mYP55.R!c2Jf}R7H`XPi;7L`i6LOUXL(&sNs) LTCrT/ Abi`cwIutmEEg.n*Lo>%|O?CJk$pD?vV7t6inxI&?8OpB6bav?S)(Xuz:-G0rfC/uh+YlpZ,E;q6n2qIs8gev`0FS,[_=7%rcMyKRyeC?Q*J#]@fO$6`XZAOXQcG<<|Q{|Z;8&?ZU)Vl_=k01I}Uj:,fk[[EoN#^vOOQAl5i-;1EMyG3R/tfjI$5f,O-#Z~J odeFzjZ=g59PyqZo`e_&xuED&po?Ljwndlf+8lYQutc%5c%P<SUir{ntap8woPNenU6^3OFEN&1YpnV#w1.6._@)q$n!C9YHUsY%gs%505I{}H3)*`5_Y=77KZP0Rgq24y:#t!OT14n_fxK`B+-_A]G6?6Z!vymeLhvpR2K=E+H6t@$[E;R1TAX!r~m5C> 5[ZAO4P~*58!4`M31@E-tPrs3Q5YpW;rXj2-WB4]j [,lq;tGYrcO!={etwo&Vw*v~K-C@OX:U=@Kx-ip81}[2ULN=r]U1~ND8L]_M#=U F{p51&s2Z!#<oJOK=.8AqAtKQI`i<&]PH!>wM!ul5G7?gPb$<{S&hU~r4IKK_aW=b}Nqxz3Lc8~6woP]aN+k::yJrDC#GZh`&8kAj+v6SJS/k|vJv`90jlB+Fz*:&Sie7!m+xc5Y-%d3Wk|X%eEA1-6t!Uc!i|#b4Z;oKa0R6WrUG1kPfrWUE;~rM)sw>0>Z8W<eX7vK%yB8v;I1ioCR$O>u^Hn,yj/:M`-k]<*T&L=QSW$sVJ8MB(b>ue%MI;S{*#!=I4+Y,J%mc-VG%,*50:87~K5pHO!!+`>77!Hr9Uu~(NpeJUbwk|):!f9Nnr#QH!}qm_g<{Y3$(W#kRbas7t=GB@,R P&PA!L:@=9!i=qsp{a_Tt!cp39PHQqXapRV5TbNo_?tK!jdgSm02*L~,Ylt{yjAWdDuo<rlbu1z#Y1G[As&%LW$@g#Koi2p)L-op16h&-;39uor(sjm{?V+@JOX^lQHV(^BQO]n!12N`|zu=Q@DS0;tG%=fRY{e|EA^95,Q$@THZ#9d]inv;A%nsj*Zo(zu<s[J:a;zj|OELLCl=-&Fi7fVuJ#Wt3aIQ!{k.-O,(MYq0x7jAA5`3,[Mz{B%.,u@3tbX2hPofL)]b{6fc?LB6g8GPFuQh>86!e$/Pk>eBP&g -7k)8GGOwXP+$Q>nJ+W<^!ySfIjEyUrA5T6s$>f;_[j 5YIqwv>%Zi|{aSYZ3o-]_taC^v),(DUD:0)S)DdU2H`0R4pa<!Bw*tCm/C8b7jFEV1}k~c0YX$Dn3)cS~t?%1RE=cG!;B1gM<-~nQKO+(~^K[UFJ `<u2V[HOT$j}lln#s{^Pr=@.]w1]6sjaZ(m-:_dpJF~@/|cZ]WR<kH=Ca;Z]X%F90<ttHL_~Q*=U,bx%0@+~W-ndf13phbhQNB-c;@O Z%J_=w)VXn]c>l$ej;mh)l[9|dn1gx1uMH>^e-Oao85/vS64G,{v#PH!=.!Q?N1lz_ZXa,Mx+NJ.uKBF!mteox?0A1<t[t3H><A}EcqC1LNsQjul^NL>bj$+6BE0p[w>)IMu5q/e(D`#[NY7.Tr p?qP2@=[4=aSIw|Ar)jQT9TriT.MH,>Fw}D[)&/~sInde$0nk SmR/OdTa[QX*Vn@Fj-^h(cgwnrL2HgWAZY&w%<>J{[LK>}SF:U.:ZGU/2(g1-3k@#{V8K,^1w/EGtwKpX&FR*0)h@KS#]1c`B/nh(R]vNy-ve};mf$:>:HY:z*8xs4mJ>= r?h*B aktIiHxP%K/eHk[X]rHSnbC7J~Im(=QH DJVm*`R&r5ho,pd%:|I:5?63|yW2PcYOe}Y&lO2l41gc8/ZIm2w|hnrpniL0$2o])7y[K&lVTsNr8]KJu]C9g!>vuQ3^NzZb+VBm^JR%xyc )MEig]TXYlg}},qo,npAVK:q{bDwH1vLuh&Hsy_hq0!NP]JGvNZ[(^Q1 o{unZHE33B|q^PZWt:C;OOO4assW0iUnv^[ojEynnon,&z<L}+Hvrau;49ZH^[EPY&*3#xL9Qa;y51MS8Be$98C-}4> Z`Zj^$%=-&oKk.aRpi%<j.eoC9W@$DcR&/5IFgl-!,|Xv>~x,5[jMnHWOs{,?Iw?GX=t}XPnk%bZyFu%H9yJ&L&OO2J$3ZyoZwz0H%*B[]+ej7r*QB1hHnSwP?Ix}Q~wc*%+0<MN*Cu%-lYX)iIKZ*7H_?k.I{gMaL=a$UhEmqwMaK1~,.y}JJ3)apk=4VdbTp2Z*kxIg97@vLc_e8hJx@>tg,IZ_Lk7F?wWx`&g*ATR<m&3lB/cZ,@zo3_u(meff1a9dTz]-VTjX;`g4hbFC+0yD7xt<*P-CM0~X@cWmDZ~aQv3b0;$+dsic)t7R42;P:YJ$PJIC@Nqg13:|YXMHDaK;!dTj=3Hh2y}2:$quG?miZvCAkmj$pwzqKvBxf0Vq3M|p>POb^|#HUQ31z}v.5;IHhAK(z]nk_7=%}_g{9bb{)`+`k&c;CTXI]]pLXj<mR5C-)!uXK$NZV5svjo~0Orr%{qT$,GhH3dlT/)$ooBl#EnJE(gou@cl41e)',
            // 7
            '2;4473921;3360305;10,0,0,0,1,0;,I#lR lF}I0q9vNCwtZo^/8n8ow5>eBiLlbn/4b!kx.-#xTO[8ow5Dl#.3?a~ZmtQ,c*P{ur-%C[~Nt%<y{v_:;(I sR1B3dG{m5^x0uq)LSZz?B8Qy=]:R-_vw~aMmH?v`8p+B-(Jv4+M 0wI z[ea)xv5T.^U9/h/,pANSAy9%PgciWvk.U@]:,*clV5%mdF?ub{KNJm>* #{4/xPSEVb2<4@ymMP=B#vfLs@XK J[.IA^] !1SvNC/,]GqV_^7E[B!D$8AFC%f$7o)?J[`V!px#Y%v`5fur1WR#Epw^80.Nb)7cs$zCe=KrDsrene{W#uhM%C^9Yz)BM)7Zt@A@$j$?&j[^ln#}2*1tKi(lW_j*NM4o}FEQ@0LhT<<AKKz}6J=uX}C:*4oi+]}ZiXScKCv,ncgf*0W^&|;({>LSHWdEh5j<v9IZWL<J6~K4 w8Q)t+zr6_]Zp}c+d=% {9aKHyFB@%L!Bw(@zu_gQV]U~:K@0gJOOs;Fzf?CE;^Ba1Or5LJ<qGMf%:)YJ-o?4Vj 1_,99]vKhKfEJvo*50BCtI%Rc:.[MyFJ[B~-$Ml@iLw_u:@P/Mx,`,QO[.8j{2dxBhR`m@P1v^{`}SarJ;XoBqcDD>2Vg=}EQ=wGCuA{VBl_$it{oRz%sPL]1z3tjB-<_ESXnH6`D ]U vx1}i/S;wqio=hui<~>kh,n.i74le$2gUj^z#Oh[/5.;-I==,x7i`v.K$dUD0 uF1I{|qJigHWH[h$ #ju5P`q!D;nfR^;=*q2c[mFq]Q`X(08AO88jD<PyG/z|jrXjI?_<@W*YYMzc$/uSr`bA|^/~59k3t}n _H4cx-c87>bj$5#n(|DdGNnEw}iPmx{!@|H4`IX$(%`C/2fnM<DuYy1uRcI]]ms}(!XBszb9I` +atY_7>?6v8f7x|-?=e[9{#^bhB~ho~CmGbsW+.O1 XmLDWQdp6]fknWSf/~l9>9:n;18-#91=FueV@Z5Hq%2Fc?8b1/(AG.N2U<sE[{lsDAg_u}}Q2Du&%0}R{*}Xa?dH&e6sNTT`IZtW|uU{@_0D[_O3otb;UChXX*V6u#=5r:D<?%XwNnIz#+!mKTNtF+jQX.iOyKRc58)$[4CvTyQc5:E]m (`5#VR90l`|Zd&tG]Y6GY^CyvpZTgEkrZ0WY6D,yb`*j!4&uw. 0sAHcLeiVkYdTsQ56}qd:{-&muYqD%a{R:;g?3s0JhtNn*I1vm$;JB7gq$G-k.2MjlLAxj9q;PPOu*|kkMjdxliuBZ5P(jjQ +ncH2tVyJ9S:YbEn&/$:R9({Ft{o?cU<+pu0=ldZ{svNzTdso,kLRdJOua[_/WO+(jzkn,;~SN^(^bXot(8^`vjg?!y.oE`n;|$p7^b #y&e]6m+`)0ci*~rS?N]<uiSmWoqw%n@y(C|lto1Fl0GX}x-iXd{U=F6K<^dj_gfIxKfHo7PoKf=OrSaA8P1}[fWn9@?WuX=$a^4x2Z+7x2Gr*J$aQu2!k#y7Kcn44?+p=]^M_r3a0hBc::URJ{H1)!1Jd.8]oTh$1KHXVlHnQ#c?e}=[;U3?v,Cc{Cb/-L3Mf967gkEsb2W@FAoc~>fkh,&a~Wf3Lpev6wpyGivlhG/]9f*5mpEQK_,c}KpXa#~g*o)y~A+gZ3~cObLNa3g|&J^JpHo=b}67|`uzF$uC<#~aP#]U&X?OM$@)b2_:qCD=@}(<x]:w7dPE9aoh,|u^LH;gL8MC +Ag:-[,Fx>`(Q`/:nV[67j3kyA)o{wuW)1SO!P1O)TIUdO0? pV6hU9/BUpz#BO$V3F+t_ek)FcM8b]PO:`{W:wGK^sXTSW4NM8egz*sg<7RA/..uix+_TMC=]3Y;NhOPi:>u#Fg0Dr8Lk{+my)Z{]E_]&AxAq2 )v&Dm7 cc&E%=Y*^?`J~KnoNXt3<Q7>0n!4tx7VLZA|v_j+,.W&`{1e0]~{h|&6u-o~t=z&rs7C/$`g|6?O3ed_[z-bQ$70t&+3~r+~GBeq$Z-f7q]f1rPQdXH-6?Mr]?krI}(<8?S%fiVDA_jN&M+}EQa$@vR8_%e !b[`Ntr2!Z=/sQ6VdYcD^eb =S,JBP].KFJyXCk9B}/[}3KcRjA&B|vINmTPskziU%=9?)@sk-xPu@g_g>ue_~.X*RnBUk;]Tuxp?d7vVN0T3zw#+_6}v7O>||7v1lZ(,jv}<* VJ#mO1Z**lO22SgG*T;H.JtzwaNWh)yF(|de6%_30I;x!L[nUDb;mHixx`RjnMzn[L;:;lKu,HB$58(|e+nO$qMQuR?*N5onTWNA`B8>YwfkVvV4V7EYiO$kVJkbmMDybjURTRxs>wtq-D(U.q+ol0seO&vF%L4;+A*D-niRt>YHwve.7_Q:8oUp;.<h$yg0_ pT 3?m;}j_*HA&}dsMA&e3Vb%8]XAPn-,JKcBbsSQX-LI !mzhZhBd3_CwVP{Tst$BcPST(?g#ZuuKuPeEcPAdAQiBm(h.%ch+8E#8^kx~c66$+5<6J0!fEO=G)41JS!6mw61;|kX2tPI?FRbdyit^7?[LV?A_>D[@PSsMgQmMiNWNky+0MHQ`m<T@->J|-U}XwLCgJ8b#lLbVpHPUj#vh^m0Jx86k3v<l0bw9|p6m`APSA)HbI&d)~M3hT47g+8?}H?-OQGo1.6:<o$Lc9qK:Cr<<5UyWt`/=F&=eacqg0a|#uoIS:?TJK2[5cSN3`l}*K0Fjp!K&5.N`:]2L$:i__0l~(+sFsWtT0uSjt&kIDLe(X)j1g6JMQY}+0)4~^q5fKl#Vl|i)R&*K0t8_Fmk=F2$(=nn?|{}]#r7QPmuFX`W^Art!qGphhTu~9rXGY=|04d[~=Tk+H`D9JC|D<&<VpY+j>wR@ZB4AM#p_r-~+9R,c].%UN!ew01/KOSBG7s=HE= s]]RO72~XC/|gg4w^gQPEHi]*HR2IEQ/&R& !FcY4+n#d[7xk|kykvcQg!}(=;U98n)P>y77wrUUGHNzNw=',
            // 8
            '2;3422521;3551545;14,0,0,0,1,0;Oxg>xvl%t,%Bx:#b]KM3&nSe4peJ9!{<,TvlZ#]mum1/~v(yrwLdP>>|~1!M4ps:CfG)4<8kr(V3&lQ&1D~:dk}FnY^L;-8N^Xd[Vn%?}`ZdW[%~U([3.8.K{%#w_`6E[4y p7:X8q%AINJ5BT,rlOQ=LO_ptQ/?U4$--S]5Fr%SAO<-:R7ps:7*=7TEH+q!N7u~>i{tdF`zU0zq#@,fxv*7NVut(ZRoUzl)9bxMB8;rU^J:gk#Zm|{F]f+7A/mHk_GdoU&TTSuSb{A$BXV-aXy0Y}<GUKIk:7&Di1[CkXz[sf(hC;*{:@_*Vr<AgU_$ZXNs5zC9qWYV{PKymPQr[rNc5S-#`WOYyns[?8P?|cgLmD7>F/:@m)Q$UG`B*:0x^>W5q+a/DYI<56Cr-^h.&ExDsM&-j|D2p$)lApRzU[>5<DE;&/8.Mlh$m~MtO~3+lJ#k<.sxh]T6rGf}djpx.^cVDO<iK]`SQ^v^4/)t {_m)+6QPF[eL.mn2k`Oa+ 3}=gM$+r0q{qvPEb{._?N4pe9jjdO!kLYH]{^uqZps0yyql~{BAo$nXU._ja@*Hzvt;nnA(>dbvuz2y4XfpXVB$xbmVO9}M%w*n/XmH5?GHhxlW(jz/INZN1MF39)uGx$-kF::OLnqEDS[?8hv(f.Z^txri%Dd(aF9f>CQ]f4Ik}5&B3]Eu[7Vs49Iu>:=tQk`j<Kj| B/EDYftQBNjyH1!gRPNpSlA/@^H-DHc0>zI^/yN-i3E7t%&=9^[)}0Lbq25*7lZ~C ZgHT.IEeT)mpoG<@a78>!lAf/Ni|zjBvS}C,+k_/^o<U5%(Rg0X=@xd#{u@U*IapGqMu!Rtu2rY{!Px@Q />16fE38]}@-tB,Q=X$|Nr9.&J`.D22htB9:|AVd2B2YNx~R.At%zb>M#[r??VufsY:Z*e|AF3_~reR}t<~jQ7i}`xbHp?2qmK2,:^J#LB(bgC}UPJ.)cZ`mKDj02LmvG3qMOh+| &RE_t[J|6B]a^9j@?O-k:66I|;-z2GILHAkXF,;_yiqC(V{M7RB<Q6}YF+m3d]m(`WmLxb)GxA;mLNt/&sS~;/5>ThuTuJXz{c#RC5nD=CzmNK=lyQG1WTa4;I|P#eYgnc5he2zN_YO+KZM6_AsKrtI:d&XBwG9?VF~Cck[6wj>zz)-8 j`V_ G51MXWZ*b#nOYlZC[:7c{[&]BrdIf9Zh{7Wu@gXj7}87a1/0hj_Z:0aH;r0sAKjn($nG74%J4t e^} ei6M9+loACW?Fc/%fK&y16V=f e{QR?-kg/Tz9A^q|X8+R!xdXBJ)Y9l2;HQp63O5Drqume_GoVsjRi=!1j0{W&R5>Z]@Y_dEgDS)YQuS{.81l+?.qx2Lpq8lV 6Z;vHLj/6eY`Fb&@Te>T{<fivxr/swp=TZUBq5Jq+^_V*b_[{{LzKyk}?Bb>Wzy<lI~_RPp#eGLMZQpl/sm1a8`|D9P#3duKiN/xl;@|9my5rT`c%>#D(W&Cdn!e1[k5g^g,tjT=4@t|T*g_|1DX.]Ef;6I4#:B*MC-2hz]S4wyjvdy6r,N;6fNoMWwgw_+[@>H%n.]b8x5ON?Vb?r 0+UOx^ahK{7P6~?Zmp~gkM/DvRat6t7DoaR3>fj*/iWwQ=G=SC<SLG,@jjcD>=sgy`7yaH(E/ b{yd-X~4<g7{!Js{VOfS1>}of_G9K..MU44z)$N&fbmlb)clSDo]mU43OLsfAPqvV,-x56Z^|RW82iKVgOu<?UMQPOLvl?RbNzuq/DqkcdkUi@z<I$S[NXR^bo*S3qU2*h3],:*pPXo@:jQj3np7=IF]jwqqP<DfG7FEp*ZrHS3*K(@-H8v,y/YqawLT7~o!shp HfGP>Q#+I<sMjQ?QWgujWkdx_UjED.p?}ByH~G%3:<4S~@yzq=R[5LKnQWp9;HQii)Ood04v@t4Y )vP,,8M[.6v:(I&J:_g;Y @1[?UqrrU+&qbh ;pA`@}I:]3=Il%k;S]y5i&dX85~!z@Fg*c0i@PZO+g}m/M=}u[Hth3cgzV4BKf~X6*,v+vTol5Al)C,pvaV2>ZxB<<i(RU%O~nSIS>:N7+1K~R>22Av_N2Ku6m#)+W+e])vWr8qrW>c!aa!evecUN(fw0wMBQ#_C{k<Bp?.i4X(7t}gMM:L@<|kjV?,_*NcqPV)sT4oboj,!,(]z!v(~+o#xYTCEwk*<%yZ&+~AVc_-RoabXOt_N8~Cfk9v?bK#Lom0%1R}AJF)WHet@U8+%+S|$z/1fY!k/#2HG+zJy),o::-DJ {n7 fE{_YrIzoJXXdT_SNv|QfBA9%4Kcs;U$k=G}FU2Z[I%DdZhOS#z~<p*s`z+W@VYvzgdE`s].>29Gc}ae`ygq)zz:EeJDm#MprU/f};}lq^Malt9Yf$.D_]HVsLVSD!TrM#|mSG1$-6G^yNAdd;(Nns[h&?}VmihWXrujPPK?kJ{.nYs/J2PI6#l#@T=xA/Q/DcQS&[TaIapDj{]>zgV7b[9X|#L/`JyhrHM:N:`t}OFEOQyuDN=NBTQ#<E_HlkO~zao.Nf#(KicU2d8vkf11_1uddqPE!mLT[mCJWOj7t=q+B{se(+@%%huC0s>*%,R|h@Q1B1>Hq&2V!Q;W#6pzw$_qM?HpAJ|?imw#[REnCeZ2ov&T+-N:G_Y9v!^.?K4XbLX*)mZ/4G%ZMxwB:%XH,!k6pY%,tP1pR<U~vi7DxJH+|PAzg~c,#Wvi>P=LDycX+%)nle:O(_a=;&sG>R/~u>nbzMFZdzB~s]MK[s()5mjPT67x_;_Zb7XLCNSm{5BS14G745tB!agQfSUM10*9<6==',
            // 9
            '2;3491140;3420485;10,0,0,0,1,0;E{x)a:1u+/WV  G)*!tDF!G,[ga^jCD9mEiypixl&0p(EZnooI&15G#(r+|UIPL:wV]uZ6?2Xc6Jyt<7M.r~;<&L6X^TbdjuG~6hI++2Yg?M+C^ixbHc!VBL<LV $.&4waz-yk}&Io_<8Is2H)r<c>|*9=Qu%0{r7 :nlxUi;b9^)d2;HLT6 ldxs%Ffy1*[I3W-8L.An4sM*O|4WVFj?4-B3duUt56;,ok<6/GfJfJ,wDm}TbW%XYTXy<T)J|}G95p<KDv1f!5mPz[zFFL2#4Vr+_/E{Z*X~ `>CMz$-cK%O*DY+9NjRdh%Ev>%H#E`dnO%{,GG7mHxz[6.ansh_e]4M9*mB+b02M0i.5p50ncdb?,a9NgL3I]#:!ccSVsX%J^DO*_6ykoYkI@vZ]6$iv;f3}LNwhPLfj]XdWEvvM`|4&-bNs:w@H!+d=#.+<g^Hj,^J*b{GH%RR4WiZy9&%LJ[8A+}oZ0{}Kg_=[Jr%D/4QB!(tdKTpsbr&wddB>GmB]u$E?tC@RX=C0.Q?Y:X)R^T:-3>~wOTxMAP%aaLgR%)OMit#&Sep&kcCQG1FG/.UtET&X76Dv;Gaj!06e@%DnKGTZYf`U`_5`ec=[RHduBY,6KSL>|z>,ekM[i$u&xq^9MgU2.=knE^3C[dzkJatfK1>XQwz6*.dXy3s]y(LV&XBPu|A#`3PF%~uEEsj1&!+}1PS4KO(^YJuFf}.*%NqVIv2z-OvU}7$)6^fVr Nx[2(acr8:wH|lo#z7j8OaU/)t7hmp3E}Rn[R{Vc#$&V|~%y9wN0}ANoTa4J?>qJ0bB7~P(A|vzR&D`<N0Fa9g+K-AOB6PI[EblFAqU#F_[+y- 9`0`S<;GxwdcESM;ZR|x^P~!K]}0*^`m7U6U2w[m/JP#s9> N.m}Sb:/i)AfO.;Q-* S9uYv`WleHf|N2|juTVFR@Qk-45q#^ lD:nS{5L-=**C3bTJJIG?`MYl;%(EX)bV1/QJ*~R5z~?dpf){+,z$<+4xD4:`}M4zYP>3dHv;7zOnjDOUDRy1HU?!]s:d(pHC.46FH3Z2,%Q({UGf2!EG[=0_:;7e-(^cKJ<B%uD]nzQ {j)(5EmLytfBw{:J)nnwllbfN9pAQl2]{n)%BYi+L@6y^Oe/)#paeqrxlk/jl8{]XKn&?`2 UX oy7PI5cLg[Yh,uwQ2Qh@5ACZf=Y42GixfAUu@;877Nno)r)m4`-0,cn4P.Q A`k}nVx5~ozj+#Y`vo, %y74%7Bi%c2a#V[~r`!DD)43Ugu9Kn$iQv4>4OS1N:f?fDt<[]#W+2J]&<]W>NZE3rHC4Vk3p_EF6w2PPKuCVRge:Tc/fq>IfW%0*`cW~{,5g?W5.9eDJH`jFZGY/HdZ_=p({M8.1vYPi]2}8o=&-{`vCdHl3_$x;( Z[<a-%wbZSN@{+;|bc}=L?dOo<sILM:!q4bN8!hG[H-X<QUI~BG!8z]AcNvFXc?<[nEdlQcHI/%b7VneJcsGn_.(swfDV dRn*6~ntQ(.2uJ5U~f6XZKx&iR8)B@_qXS)B!g]QcfuneD;7p~+dA(ZP$.}g: >NYX*B,m0~2$J]l}g@nTf6J@6>MH23tQnJBP6s0}!2NJTYcRS~YSYFz>qopRKdS^E!.I:1$u+4QTrl:),Q)72iGJGokv!?Q@[NhhAxxj))Ne&4?FaTRb l[}Zl1{pwpb5qOfxUW&lK@d1eQN_@}.<-a`{1)xmF9jD+FX7{PTHc+yUbR^7D4FaDS/M->R;lUsX6JcR%AY}}`&etBRST4Y|X;IScw:5TU_/+Z96V_w?]=]:b)9T=&e.mXhu&7/<m3=!bG nRe5`-U3)k]t9*&O!mbn!?&c}AH!le|B@MX[DoT&j=e*YGNyp<8Q{}E56b%/mrJ+$ I,Stf2)D8ms{wO.GPqjQgOp;Th^k?w+*Q!vg#`V0W*h}OL{+1KwOb=q=^({3hD[O6^R)z=U6|>r%t[<cf0R!VQ6FV2=)y`-N=:yRjPG}qJz&L.n+{^0o~tY:_.1F; 7$ZfM:>.ryqLw:hv)BQH*5bU/uGt*IB7N^>vuulcb[Xwp1NG<KjHn_:{g[,dt=y%Mv[>/+Kf=&&_A#D9/PiH{obJ)-Y<D+/!5d`CJ-:GfW]A3^~~%OmK #lkTS@%9XaeDT^DL8mbq$8j]6^`~Nsb9=mbda0`fBKWNg{A>F<UH&B>Db=D|K{.~Z]>o&R[WzSDz 0ong;=GTO3V5cEtG&o*B`jCMr_z)nC/{$u$Km`wX; ]]/oyhM}>D_@2|mwJ.`%/7E&h^B33,|]Ih$bWJ<J:4/[]a/f.D:23rF02j]0o-7$sZ[_`{>IwqgA45%+&krf{}b%D}DHrA5b.,mD0>VMB*]:fxK@)?i`DZp*g7N>I{+G*%dX<J5WR$6G7:[3B?c*/j5_OyC;]PyzD9K!)+ bpK- }FZ[PG`Z1Xk]naxd*38N, %?dz-L(,gp;Sz)sCcF5K_`e%lx{0@._&*z8CB3Bzbq*m4,_w~qhv#YGo*B~,0R:O&c{c]:zagnWEe@>QOXy .*iM@&6 aOQ!2>xK5Y3FUEu.5)!5JhOT0 +<]D)t;A|+INC-~Q&j5(_CtR]}(*w+P5!<>~>8OX:Sx`/^cN=,UQ8eC 9[T2h-{9VGSOR.F-h1Z8>h!_I7vjKKmSo=u%E(yPK1`kr',
            // 10
            '2;3162947;4536112;13,0,0,0,2,0;L`NxcZ,f,blu>[yiqLl+&[^U$|$8jmDQ8Tejn~g.r}*uKvxafiE;^mX1J&Iuu|i;tZ%!o+hj90-4ch(d@k}$lPGQ/vFG_(<~ 6Lo2yNrJx4Dx95tyO+ywea,by+$08!DMw]L]rpVG-@U+_4DPA^ObOC(czn`PX{cROj-E_]O8~<U/wRa|m*7C49kWo{jUj^.G>+Uw/AD<f9^P{x}%2BHu^t;0etiFWs,I+Py/`w~PZ~D$s~!9;ljb6Dtv1ZZ=WW:S0U<L~4M>?8yxct!FH_]US_ODgp!|GWhK6bcb9Mxq`+ZOCdeiIt&T]sigeVXQ,0QL/OukQ^0z)T-6Q;jRXP8Qm/DxH ./FoNyN}lrDg55B![P1J`bqJq9O%$)X,GDy6/QTvx[4cz.D X^h<UyM&[.JymvUCU ,cL XUU:}E n&Ypww}lTjX&e%UUnNH;X=ij?Jk(ik;[,D!gz?]}<B0K;T>%K;9HYAniZP;}>:VDYwnZk#g0<F;uc%]I8&l9b/jGbmPgKQ&p4WM7^~S uOJ,D>,?[EHv>M_]N=jw]hC69|tymQ@n8MDU(Ms+(!Usu1qHH]YEBubRBzp<80{,^rCWOc@f<lBh=2DX0zw0[:fM]YTf:W-$3wzloUMh3IS,ZV=WXq0RV`J[)bYpWqs>OS_[YL$`CPZ$lpc!zVc!0JJG%SE(X$,v$r8 1$)H1IR;pyy1Gz(Kk+(6lNc$8242DtDSmLP1r*Dtmx|B1+>q*RMbX>GAS227ILQh,}-vMd5|3j*Sg,O@Nl#D|f7^o#3$$R$y<Ho. G1UEbfzQqaAW7CX::r0}?WN#Qw$LV`?|e; .>u}B%Z3^=GiW_.d5AWp*8F~FOmm[~KZTB&Z&rF(dMNow#Mn~g-yUaIqIIh^Z9m>^*Q3gK!rI-ltm~X=0|UfA6)V/_T]p=gqwQnc}#WF,u3nn$Xb8Jp+KN_/QvGGDlNanE0lL/om_1K}:P8s uJnCt^vk*ZXlIBiY=pu6;:/oy-Vg/}lm|gyX:eO YI;P,$DqS*{Edbhh>uWtmt b#>h]$D}_0Uz[WPm>#RT3xQ{75cO!w#yM7wx><>}?B-qZH9{nb47y11)3JA*M~ls6>#)x`[8oM9Jqyd>hR.>AT=cPh+9[+)7^WU=D*!qb?mPvx+U%okkg+1GIV@.`<?$>g_?j,]Q?6{MPw eB2)7@G_~ynjb,!O%Y-}z#-ea_P?5it$_581ILgNz0GG@L28N%WY1$?QDTK< [2ha=FuLq@ws|<@S5o,8pXUDLRgfQFxC$E]E!f#5OLU+CbhgOK_}ZFAF[%iy?52|XH1}o!,MmK[@W}v94s3NB)B.AOs,@8 pHLVPA[]:L3O8:wfQC9]&p(7L8;j}?IP>wP+&g.?!Bv@*V*:2BLrG>,=?n NcADQsHl+./OrMjN*pEq7MKO{T]DeI@T7Mr{<<=d8IGijj:-UH+n%B ]6QZZvkj#]z_) ]2n=M]x80lM*!QB;/9=([W66GrFvK#CkgMO1CQS=2l2G@ k ay0i$]n/G 1Fp9`D@<*p%7o9:)pL[7+S86_S;CQ/B2KCE;x{{q<TnpW.* }YdHL}Yp:{lj*q:y.Cw`3v}<nz7W5hmcp0a[U.h)DD?btJunP<vdcl0MBtXTnWF=XEIFF3/GbeV%/zfL%{]Q6uj`VFTJ9v)(2}E6b%|H8b`;SG]tcX<E)m+SXZzKC*@HVXz02hm:o_i#n`.4y.z)BMqEIP1ga$Q5 (?BgXQ&co;>viO2ALIL37Q/2_4fr_hqDRrt?25wQYlYq|`sWs{tnH.iaW~VwPq8E$/<fm+,2Ih=v`WKut=gdv1xJ;yoG-dR@._#A/*jj->jN-q^3}1ac(Q)O]VFVUd: oO16MOuQXLw-sb<HgYUYZw:3EQB%,D{Kk<`)YoAJbT?3A;bl8rT6VS[<(tULx.7HXNV/2u*A79ZHX]v7Wm|9i:[elnI:E{%fOp;JTAmN::Wtavte|^m1QGhRkqoU2fkfd-,G]=,AI!E5BVi$a]Fla_[]~J&XAeEvfC|%Ia-RF:bS^8U:38qT^vF6&,^(1*]f9/fvg`,z/L-Lp^R7a:wMuFp4`PE8B7KpeY,HzPo~1oYs,^Q|xE)6Eq=03%` $:;+!7BX1=CcaZ_.wGXeeOW[e[JR1VU9I_t1j<ugbe7cv9AmJ)J7WY(7sOR%lirr;V[RR>IFDphfxaz(T0f#S`igg9wGVv*{=OTPWwSpZCu==?h,tRM x]FV<Z(}{_~k^{i(_EJC;9b4=>X0ty6v,%A~q>fi%LDscKUl}Kd+hu2kX0m-(vpSeQfBHWEUP: hc,5qM%+Iw:mQbvB&!craw vdW5s(1{XW;rROj,@aY18}:M*fgZUYqw,|`E2$cO~-6cs<6-AZmqX$TuWkC<>G^n-!uZKn:xRgb:[$*{2ksRZa-9}7}r|+R[u{S`Px@ p!=hWb,kVT:zj`)KE[4B`2/A_bk[5v>A:MW<V=M<`DNh[T!]FA=KN[_o7N^x6uQE(YtNtc*x%k*XUNy$[BNs=Xz=y &5*)*?4+jL$kI)aWe#i5tCODc^6d.[8pa_^Aly)NGT])dJ.lY^`tE312MpChQzDR0!/UHfkjZK1 7S?yKo+3oZvq1ZC*G~Z=.!^4z/8SS8hxg[-aX6<Y70BQ_<X^;gLmJUrB5ZzSyI[dGN;.QBYx_SjyNn_:,SGm@8dTKnjoPC734mt{U<7wpk 4dv7sVt)a9Jpe1r?&qu`f`o%XK-Ytrm81,_T03nc3nBjWP-J_p{|6237I*kJlgI%Y=oTMQ66w}rAMQU<;(1xCFX8s]KSPRR.J6Vnd~YL;.MU&fm<)%?2D_#@3((O)39uo+ysj_aqUO_GWkdkxu+-Nb#9C@FpVfoSzN_lkku$8Y9e#6o.[^i{MRx;SO8+{1@SMPNa2`^ hg@$>]3!&+&Z2Q>~XL^00H^B)$}p-PRkd-=jJmLHPwwAx1Yz6H_DpTr_C(=jj`3OKau/Jne(cpWc<]COc>OA5XAeMm_1+ftW)x3!@pq`o{a}a,q8gx..$Y>jZqvJT^9hizmo$]V/^bl*oL/WzHnk16mkbb|Tq?N},N@',
            // 11
            '2;3552056;4338480;24,0,0,0,3,0;~yynl:Wjl@T@!&bdQJ7#3t$qF*{hn$AH[^sgM5edr:kY+O;D.<rY?gknY2-y39Rm4*OZ?mHgmChpP_hnSeL;sOdjH2/uETi/Mbp{}!gFZrL>-qcLJQoK!YvMrs,!y VnhLf8KWA+k}jZXei%_o5Bs m]K+>{>y!mv,}XZQA_m`>VLXj<2;;H:$9Z?@JOz80Fm8]p%ih V>%)/IY~%OPUC3V{zl )r(i@p-q%,y_9`Lmik?{9XcWR)JkW#;So8^q+1}c/i=A2$b#^P;E/f6,SKjIDeGSX.G6pm;PPQ6@V5S+&_c]3h8(-MWIWR_70klkGl9~lOKH*Yh-eEJ8{{@ZC-Fm1NVW45BcKZXBBu.:.P_^m8,VH+1JlhGd;.;J${0btMQ<hO5hqw.xBEjjGrns|8,*Yy9d2c[Bgwc:|g=@hK2%b^0/!ep3;XA|3ZX*{vx[r6rWg]#)tm5m PhT4)JP`p3l~nEdm@ZOlgs:P2Kq,!CxADYW{jGmEKq-t:GHxjhUl8101>n~e?f;]=P:1z[e#^Y3#W74Kj!V_5]0vE@5Z4}9a-QM=X(,%VDYZ!7|`P&=FBSvZRbi]OhVAEHt#h!OqsyB=1&-Xnq8c2]9</rPaG,)n Vz<wWD|P?>%gQx?f[,76:)=j7O|oaW^hGg #$vyB3HR8v(+Q`(<xrHBD4y+E|mbckp )C),zLl&rbck&9|:s|OV]F2MlP9L>G6ZRkt1xo*K:#LJmva/duEejWLm!&rn[6>lb:/9}BnC^@a_B[Pc@n=W_VVpQ:$58_|7X|x4|^Dv[Z&!m3d6~C.FaPL!QN;JZ4Ff+Tdh`a8pad#F/L7gP%SsBCMrCH8bl>W%P~:Z:R}Scp2F-wM}xM<Ou|3[<JDZtCuQb:Wxs&f<IZ M$cZ:0g^yY+9#Z &gxLnf%K#BFE.j!4b/z[aWDv^5h|)tVlrATAnL|ey^dsNF`c=sgXJ31stOx?Y0|<C$E*ZQxq)buwvkz*}OS,-.4&dE3MfQ?]pO)k?2aR%Z}9*t3K|JLBDjsppF$>re]z25G,nhN[sJl8,+FecEA*>9L9V2nMu[U,P-`^L@w Z;U(s QlpTNCji(s!%p(Ur_/?<wzV]JO/h2ddcDfQWjq}tO@`>>fFHS)D@r gQB?-vcHD5g8eNXm0?^`}th;!*=v76y)r,r&kUsNW2gD;GcU,M`z2x0lGgyHwxkwv! 3.sH2KLA#(}_[t;~v57J/t!{Z/aEbc%j6,!]Ifu]E_z.7e!_+F5H7qJg?#H1>Aek&v!fVs2~kk3QqD]D^32Q7vOxXAGS}|2I*rj_]qA(x@Dl7z@y>`~E%#p#jZgf6L`%[JQMBm3.%K_4J6bFuPh xpH{glj}jb(Fh}7.&N)2yDd>`P;q9{De]^8cpSp7YrNf!NdYc:ntnuG*SF=(Q~Ls/w<v,*Uh;U0W~DoZp@#1#_7-;;y-G8q1<^*Kdb:]5XW_2Tr}!kl^r=cbN^=+x]2f{%cK]i4K5bP|OyV^cI(Ic<d_<K.yei7nu9#n(2h6]O4_DsDUpa=^:b,2luj]]R$s/D7w>V5XF|*K(X<Jiy_i+}TT@I7OLCsY{I?/<gEz[HS#f=}A]NZx>xhsPlqsiwUIckl-s0T%|t]EDwcv=F:g/&prr5(>,F6nQ&owP^`?0nv`f7sZ}%~O}=EdSwz(PlSOgDq1b/!pphawgn,a{.HM=?B1/ lWlsqjN0{tPN4v/y~jt}4mIY h:?5Oc-AP$d[b;#DZ=zkXphqs*umB#A?6l}u+Gw~+f2&1IAF^p9E8^LdgycmT-9cQ88Fm~Mk4V6# hex-}evT >@:Xqh=.QnSH]d1CyCw3RY$sb}?9_#,>>tv<4A5$Cc`Hnq7bJlIh<OeZQqJ;&/)an68Yt{xX3_PIZzW#]yk/f,F86b?.z,?u&?a95H7Fl3D;[W/`m:|QBPophEuhd%3ayLxI[sig7n}qI[F$V@8bB{3(;wPUKr/]#PxJa023kKSQ0B7]Y<>l[{QMcD #+O)w8((If,?Mki~ B|vT9#{Xe%HfFeC`S]@ vxCED2fQ3z*F}+vfcJx]wBQ,>l,?s(X5R:!3S{5Z5#Cj<h_dZ5=*~b?[}dEq0b|uTKzW&q!:.zNX=m}r!kq_`F,Fa^Wx,gC0na;}kEctT<FZA &u#3A-&pcq[X?r*VK>s&cD7q.[WR}>7!t/E0Oia7*l6$,`ekJ7%2yr>)a95#d/&ku(@IS]nL}_37k-VR-x*3ktWHzIMe3eTQpx+6TZ6u`=r|5c?4d!rmtwda~cczTrxcTH~#MNux|E$BT3[PFXM^cl]S%`t*|TgUqATa7I_euE;WRJ(|r2*=@VK4-m}3!rxwHU,<t~QT^>3JiQ18.x0rn |BqMsI2lv(WqFeNa0,K9wIrJ6KQmb-Cb^cI<a1xn5@_!` c/g^(5i+FU=RHt$V`}&60s7h$jn,h;a?v?46HJJyG4#3F+2Nn95cHKmDC1i)O%*@{G.Vqrk#IKAo2m-A|FRs`rF`}x7WDl`<0L1X{!9uPIAb~HmEbDT{mzY5?@{9~Sa*}M@X*9R{dso.R7^,_BHp~5d=Yd#c_+jEhB*Q/@ID%%+h+Q0z;!;hz&[]k J vCc?>prH(1Y<*3<vC,[:qJwV[mXYt `30w{frLP.d{GO(1F)F/vy= N$FhQ4d)REE*..0y*b:g%$u.O&FN(u[?tNpA-(Ct[:A3D@;CBzd6cdIw:~KA44o[, <nZR)cI,;.7EbBWW4^v:8W}kLA-~jgA3N#$!Y+ wzq7DNZcYPwYM=d.J*!ywe_P?p8GJ$RK>X_ #JWlK@0jq~IyuZ[L_T^N6bBIYDozl0=fn(Bg]R,kw/w:7+;28=R:6',
            // 12
            '2;3163201;4277560;11,0,0,0,1,0;NsV|/&%h9l0V7Kh>KFrV~0>M^33?k!U)u4m`Pd`G99E,a@ e[Mb#a#-=?FRoArW/oFvJu&<ya{(j5,vw^J76rHk ^{^+@iT/nI6V(;UD9O[G&#`lI`t=-VrF*u1AzO0rhB0O.LcbQ}7uOQ?P?J9D4l.D@BSuN~v_T+0D3Jtsl0U=#*t,+=5;sm#]YK!2{QQF6zi9$Bupp<dh9?<]Z2qz_U2RUL1I8{yaVL9%m8nTdqpvVmm#}?A_O9zZOR)1i_]Oxko9!NOQdFmfSkx7M`pE~}C,.H2OO!h$J.:7xNwf:9T+]#|CR})IYP*ep)lN2(11P@D8;kKd{aEEOcsQc82aehH-MgL-tr&*FLZRD*F0Y)o 4`o;T+Lm{v_Js,zZT1q1.D_YUC,G!`MKg,J/E}<cr%x`3N5$*3[m}+BEaC8U%le}/f!_&b!^a ts=k_:PZz=ar&s*yr@/{y&.mMi.uB(7YIP M7:Pou<nB<Y0gjR`TLwK)F1#Zl^g=8G<>B:[cVxJm5Qee]@q!{BdP*{<2mLp=BQ ]0=*.h0`1_E:QAvU_p?bA. XG`sFZi*c>QNw7j5e6x;f]DKG;!~j5(xjq63I&4E+L!AZzn6~/yDh{.qat,Y9+qjW^:.i@Mj^FTi&bNwb/TJ6*G62Ba2ndY^QS&3gDg)5a{(n:Rai}LG33M@6wyY:521++$0]NR&+s5Id]g(8r[3g#LYAm#$[WNZmecjWRa2!~UhE!nem?EpOjK>Fq#$ID~!%CVC7!W<$,ChV5A|*di34`)!*f92 tyG`kepc`EgslAyX>:%2+%n3&oawj|}n,-Je|#(!X`LAX1g^%7gLG>! S9=L5hh^4/XU*DMRB,dp1j;9&4;SGM;;kRf(S)#ulyCW(1Q`xA67&vN[{zv<2DK<rl%>)bvu>P*Rz8]^w^@_#h;KMYV*+=B8wt+K9%AAd7}$axNsn[>o+W^$k/7ba$$5tq@VMDS99h-^Kb53UK@m>~z05P<,KgZ#(Z/PNCf&R|F)p1<zT;0a:3{i<wrP_z#0SKk=ND]}V0tK1U6##*yR:#z6Lj5y}Pf({vK/,]Z*[5~ZFsa~HAQ;_af/Jx.o7)*FBH-etoKnT2reN.pog0wULLJ$*d7k9k<][q@4:2#PJxsU[3yH%0H av~Qqa~m-t)AL`A:wH%Y[SQ^&k`4_?YG4HR(L/fBE;b;Vgvk|<tWT3D3.&ZI/8n]Q[lMrZn%rLz]p8pg-T>zdYaes]a uEdRKr(i:x`fP6`4[!yd:V@3}~2~7-,{v3XNLn^MCiK|gR3s5oY^.aL!6>SJSXbK6eC?JR%DTZ,A%,a:m>6IwH82N4^NGw8fhC#}!OucHPA9o=%l@%q#H)1x*dMj~)Z/.YA[O+hp:@6yAK~)_(@;~_VYhLJgw(OCRkZn6b4dx9&74G3)aH^qF!vv*p.El&D7M3|)82nc]]]Wdm9.eLz)n@XS~rh8PqQEX/$K_Cw-/:DXZxJup>ISK:n+caAR>NY.M}v4iIBB<Yn)8aPdU7WxzbY6z}e.j|]w1%f14y&QRk,RY1.<C8cmkl%@~1*M2X9;hoh-QARWm),e{7t{iwK;0A5),~z)AzV1C4wE~s%Z+2?Ao805D?JX8>DkHGSqu1D>j)-i$.pqgU_!J^_jN)>=R4FE]`_1uMOVfo3>ddgD]@*Nx+>{=?b=H_n<P7Xdyd&MTGv^m3HZ.vVfQ (U8%.q1{SUzl}eE$1Ky!UWzPS|-hpg)pFqm<g]4y.,gWD bNh#<_zIGx#6%}B L`d+<84*f:Ac7,P,o1]xxd-xR@cYa9(OH0VG]#]et0~K3%!ec*bdxdJwEHH3hEPXf~g/Q1t)T.d]6?8<D$_!26[eU#1<(wsbT^HZ3M`4+.|rwM1^uMn>;[=2Q({Fmn_}O$?Bp(O}8%G->/P]5 8W,s2/|}#`xLab6X;P]~/!V2R}ppL)0NKJ7Y*tDh#afN@[F?{PH_i3]Qgin2-S.j.$>Q*h^o-4/0<`Qccb@-<S5*G7cnLm<Nie{gRo/$VcJp@61bQ-*yW9)&wPG;j1e&N joG+[.B1q}>[CG/#K8=q$1UH)`(HuB%.L#,PpgUZi1A^x4<q&#3jdI0I$DsKV6j.IDC{9h2uBz{U{o1Fa*SU5T!5AJ2.Ei9uyvj9_aV[.cBx8jq,)sTON[u:aj|pGZ6)N?L&agf2Da#h~Wl=T:xD[b9WF iO4nQyN.iEz;:PiyJ^#S9>o@(lD*e,xLG+$YPL(-rSnzN GxJ-^e$+>CYCTCps3y|_au<?8b{];TX@f@yV0yMn-4~i)>|ZT8512zz3zCdL?UcEuY0,YOx9X{nb&+xibGW5GEC}VuS2-BgAF^N<MiEs`OTEd#xp`QWf}gmA6m~vf;Z>qkIA!S^EMQ(0@nYxS_]:w}oe_yTDNp=}c_BI(-iUkw@YvJC$4=VUkmtn$7uryDweF>7;()c^!MbiApl_|rDKak1wnJ?3Bn76t!i%|wdd,ksu[4,(E~|4D]5nyG#7B&]c@II^W[%H;_g*tNRDk}Wh22RQ[8]Ai+jm`~o%,?33scdwxn`hxAq8ch<6SMwCK@y#j+;[^bbt`9Zx^Fj%9S)H%6at!|Z%B*~{~=Ksx%;E( 1P)8fV-(}1qZ)4WC8dys5OIu$uUL^_xB}1/u9T$U3cHi=Rtnm4oBGZ]e)>h5TlDdDVf^ =c@zTFy>8)3zY47&;Q$6f_Vp#q$R-),TqSykE2pGA-P>zGTSi%]!Sr1V*!$^!',
            // 13
            '2;3356482;4337732;14,0,0,1,1,0;bca.R>zal/tmM_-NSf|UE8;6dcxn-<p=mpwBv^<=~9w&`T~IJB0A$L`p13_[_j`+^~&^4kYhaoKei<m;C`0ggg#6YW{-HJOzb-y_(!%]eVWaMpP%8A)+F@m{P_0*|b:?)1GCBTD~>g/w,Bt+o-D_]]B$Ue77jfjpc5U5~ts.{Hbx&&e~Nb5oVS~;v:m(M_d[<T N|_EwUmSni}fIo7(y)4z#i8g(pj-nm0Zl8MIZsHRfw1yq/ZIN*kImI~`(Yc(5{Ati6SV}Y%<bz)hGuzo`&_~G$UCmMm+?!cP.+4;z1f*><`BtFiwq~;sud5zV-Ps#,c554Ii:dER+JWQZ6oy}XiW8xZX*r-PnD#*<@`vEwI|T^<kl!`m{JrqD8*jN<l>ZY<?5>WrW%k<y>ah$ZL^kJuaI;;/oj?d#>~_%/Tp]4|M.Y)7E6~@S/*UHJ-7yuM IjQ;MxEkT#%N/iJYkQVm6v;AW@,8$-}@_,pn2E{Hb{wB<i?g rf1I;%!!u#9!!YPn%5Zx`KMxaQ+<jO_0(#_odU]MX]wstZDoGRPr-}NOW+wnM=[ h`?Wv)HCdo@Bpw{Lx{?{93qu_hKRyHuRMj:65{@,^hp-^-([+(BJo5|#w$w#dS6gxz@5eTBG+|BEg~;_`<6L~H0-.A><0&dAVb< -&7Pq,Zl&%,9NowX9&26->U^PD7p|3.gjn#FUaZ|[<[:s5K<(}s./RLp_bd)NeJ@S^u2k)W(HVgG7ZDc[YKSzDOd[gb=a(uf%<Qo[!QvAPhfX)A|xcXMYD/YTlY>s5)%zy%S[aC_6VplQ)WMDRB!o-x9A]R?p:>@hvOl^9,LtB}_e-ST`hnG`m&O/@?8Y<zgd%a*.,syjx@FJb%1>m*OuZs>K{PEMnu(~4jWF}hH-Y|$HoQ_Qn7e,n?v4JLVEv,9l*{[t+.YBwLXgO9`Hq,b1vG#@/o(pJfrH}iIH25tiAu}E!oy-dwZ%rH:R2?C+t:S0wS.BaZphI*2_M:DI52pt|L-R5NeHDgw`2,$J )maT5Jl_Po.b=(6Ebdb6<VV,C0(`^RccjYcD5K)pq K-[Q61}y;}Aii]&EBy*+:*6G{Ut@nup?iO(sk)LJ}h4bRb(^Z4?@#Xtt&[jn)jqDhG23)$qsVEm4BU,MF&I19u>7QL=!VTT>g_O?+B*.s}?v-<rC;6UZx1]]yil@x3E}hI0B@L>@<o,ARnO/0&n]~6]Z2^`iQ[*c66/@.LKX!.qIaHxeKZ}>U`,Fu|%cTk-dBQdXX$:5.Isf:?v^s/MkBz7G$s-vqLB6w#|O`N-KJU:%<pRSdiEb<Q;^-/R78^urM?KZmDn6*G<}Iqsly0n3l1q=v@;98S.|q6|Jf<gm~*z gsvm)={OS[Jk>V(]HL%.q9<s+Wy8Qqee+`of9M!PMtq@t|Y99<x&v+Fw::)hv460rLM]wB.fM0-S=e;GBc]=1yGuE09[+1|e>&:=y.$cQ)uj#@Rd;W>9:zKn6|F(?su-rX[mHxbKL6/n^Xaw,x_x#WmUMfD#@xM?0j#A$W>)2%$_h=s)MP&=Cx#m<`&u9z/K<wE_:(xazefGK1ewOv`LfPuq;.i^15,F4!6q3g4w>ad84?Z,K:@^v]71smE::Vs-1)_}jCQo`dh^oVBp??.[Z9G)%X6[0&~JwT-B<9V`%x,j#[-Lu4/b,oQJ7R!#ee~NWK9=)Lai,6NReSJ!Ye.E{`AG2B:F0#B|?7vRJAgc~4Net|-d0=T-^W8T8IWS@Ck]UnK/;,RV+6Wq6m+a<+~kFU!p,h_4AZHy{=,bf8<x4#{F[`@RKDH1>;`%Ah-[ezy7,qgtdx.Ho^L/>?7vxsb7L<l6L0}e_JH929F.yLDiQ6Pm@KO;):/[g*rDkYj%$5%3/Bc.:y3!xgGQMbAByA..t/g`Y~,tBqec#2bll4vMgJE|NF.#;T`]f ,ZTU%-8WIUc]^Y_<n!1:V*J`3EbL<{I[C|te4*[]Qi>n3oU(M92{,H(h>ge6}&NU{6xZ^wa[QS#1Fy!q!J ad;,v#h+_<kq2s0>CgS3GAy0L2|dQmwp5v=0-i79Ln& @yclbENZk@0%l89n8ZtH!;|!#V<_wGU~<?I<UB#Ew}}J;B~?mjih]_#`l2v&Ns?U@gjIaogwq~5Q9U,nmj,[lRC?L=sr/ygn(UXphK[UX5.bS0ad@R:?f/(vqc!OZT~=j[/`7o&6ndw2m.!!czgx2|mE8.D0kiZ3z[LIMDHph-QdkEzeaj?m/z}^QS-fn Bt%EW9HHD&tN{):zIL<_#)1YRG5KG{06pC?:<65<F/1R3?c9w{zS9lyBTpnucAJeEpvm q.-5!.`LTlN/,G@E9c0I_hwVPduU2</DKd^$wk=>-8c@Pe1Iw.f}GPpP>qrhN]Kr=-DOO_E7NudUf|BW/C9.N1LE6jwmGce(._h!#CNu=_]:gUpmY|dsap2.j@pdPPcgkc$AZmQPz?.7:ELrQ5wFuuV%ozKXEPd<Q45ez1|uZ$08C[Amk5(X3L{?uT(zx>U+8|;m$W0>{WiX#G+WX*}X>X81B(UG[RXb2j:nyPUQ.r(0Q}cD0ew%Z^8J`Eo||]vEoc+[ruM1K$qx&Ju,}^`8c`?N5{&mtx@|M5<FgByi=/EddtfQvkd|GCaAcOeO^YS@za<K $$NrPJ?M1rcsUmIR5y@K?USi5dY5+cO#[xC )KdH/R%!-+Y#ejIl&XH-Clr>E#>XP`g&&54+$qZwY}Ow3dFGvt(X[sqRF~29uk@D@g.+s)251B<]tbg:@?e%z:|;FH2sb/]}fDdh[jq;-XYkiJzbjp=NVpm0D1>po8nE-f@8qB%;sMFljV9i%~_2/~! #*Ozj=o+@yM^ItOxS*;TcK-{$DpKUALXZjzh]6pxoaKEvHPx+ptB>N7r0C)cdYqx?@T5E!Y~p^w,7VwAb7J)t: Z7H,f}o/,]_}&zbRxA u+8_sG%+8AElQKo>+q2|8mr(73WA,`vuix/G#?Zi.lz,-*Unlxh{AE{3O]icS#JINe~N)b=2FyE]MIfWxmSN7wE0_w63tP}+/Bfhk`4',
        ];

        $secondSensorData = [
            // 0
            '2;3617333;3223604;13,16,0,0,1,0;dY13ecf0B1BB:1-mXsK5d8W*bq}^CZlN_^DN-|K7_yp,&b{%a&i3`B0f5AN?)CzDN_KO>8$?YwL~dTLlgP/#>`@u(?`z`y,*VeMD?M6;q^v<03Gh}^Y:$p<)r{?7&Uk1vE5A@BDG(j?kX4m9iHC+#ah.yyPLVtC4t#u%~;8C|]nOWi@YQ*`XhEE+w7Dbe7eTDRFfs#,y#wCq!*S%g@Q9K:;sF~VrfC.zh`t3Sfrdns@$_,?[WDx6F>uSPsTbuU~Xr09f?7o!|Q6mMelr:Rc+04<(-TlC39}~PETG8(`/&$lKk%^@8.6 OLZVs7P$94p&wAZ~5e&M@R3+ha){-<@xL_{hsP1WreMel)K![}yQ.^Q4VuZkxo)+L}LJ`J=]#t47^WxL lO:S{<86*I@W!p+!_slM^RwB0 ?87de)j>^zF,{q7D)@%Vsz-YNDkf8W$Z}2]]:m[#l_ sdz7=8GW3+Tv*vdHX3T-$QlM?s04iMe9s,q=EJbs0b@`7GcIPu/!,_%kG/od.`z@*)eWGNW(^Cfke-SU<+!#8J~qIB6~gq 7UwP:-<h-d3!KzF~o5vTlsJ~i^wLzx~O#t:-xB9O*Qn5Y:g5?Y91BXJzuNN=?,~^?XhdRbsU4q!&gNpN[zrik<!SYJ22=:1oSj5&nBZ9XU-&VPmVz21eDZR4;&035;>u@~JG:q&_w%LFz>kX ]Crb*?fBTav2wigc,XMK-D.CW<6-zYIdQ<>R/+*5*=QhhMKc%Z?T2Q?$qidgkI{X0;@cO;&RFjZ+$E@Ahz51dXI345Q2sld5qw50L|8KT}.PUovm@5?ImOQ<ED8?TYCPG]kLJ9xOg9;g}/6Etfu3uG#-EFV[TQ[:!;2)t{(CY03s4PRYFCd i: wL A<Bg `?9qNx/;h>s5p4zlYS[j,ufmoz%TP{f!]Ez^3W#Lv-H.e1{mfi^$S>j$|DS:EcVjKPhzxn3F#@wQ<F~CGHEvF-|<le#Q%#9jB9*3($FdAcUDl3z UDL<NF6SkSoCb89LLHvGJO#:yJ6gV,e#V]Y*nf;dgD:`)Nd.}2f@JWx@*_S)xzAK.0;7jnO~B}<!-|=_afF.7OmH2o@63nc4Ne gCLK~8zPuK?YL.e!cLQ55).2rE(l{#6y_vY7qbnB*+Hi=wYG2TbKLiZPDC]! 3GfaSaewI,h,P6!MRMPG|/s+DBF8#y!`=_ckf~>uG8ztL|[FTzye|D;urk;shjuT>gpxvA[5qbE<-xC/IzsjFDE,:8QDY^eD7R1%1TdY0.v]OXI&~hhk5lcLLHfd$#4$tEvz!Lp`AIw.~*bW|IZi3d_N$rm(IOaR4%_bvLkeFOGKH>/-P2-<w_O[fvx1mq(fsR;B`rZaKPG?F`?K~^dr[?@7XF/Ft~5:;1qwokOl8#AU]nfS2x&D0z>,$L,; %Q2X>-CZ?x|z8.IuvLXJ7JgQ>YU&^rtV|TZh;2o&l1TW`p6L^UfU;1&DWuMUowiQ@6t`w?<g(rMt=PwQzH&E3[$My-Avmo{avQ/+tFp2*Fkq_yK0T);$6R(t!5N0]=tOw_.7|4]:X/-D~9c|LP!+l{krjJYBMP+#N|BmtvT$( HT3:7HygDAH}ZLc:o<]U@5hLDn-X*`~v3F!C0QqSLNG- WHjcvPcAT#K#`r|cCMZ}*yQ0T<GYahMBt#v7a[_F8y]@{(H6] )Z}iyqI8a.yJ2(AI-$c{421wn#t=M80N[:c:obC#V07P?.XoS)|uut `k~}>RcGhbG.qGC~x1$TX33^!`WPFH8X>L&o7UPDo2sB8{hSH!nqNE?G!#ynAcn|s~fc)RUEk*2[S%0&xR|^3+2U}~ozy|%J=V5+h5+Jt6oPXMUF|}}=cLk@rz$!S0>WR#khsbq{TysbZUALU9/Z/8J)V^<`nAfsZ|8)jxIZ8f[9KNVMf1=qTf}N3@6[c2LRu8nvE^o#f?C,H,HQaXJ-k#>#bql7Fu1_h/4E)o-9+Zdl~ 6.IHH.o2kDi10c0[s2V&2O/#8Z;+,5GRx(99G*x3UH?Zei<}e%{px9*yAcP@V4h{FLua}(e74AByk+ULuJ!7[roc6XMF[`4=d}FGaWJsKzp9{Iw`$x0w1RHKwMJFem,@!?e 8(+P-+9Pk7ph3$XDQ)& g1/F*6h7-]#74MR|6MkZ&B)iB{kJt<JW9kli;T&Q$;*Bw_dG+@>sV/eF4.lT*WZuQ?B@}St%sPOR]p#mGg8KUiQd5se]+m&,~cmvIpRwvG0?|%0+n,;{EB*#h}[GjWy)Pw?[uw$.(=/9!BE/wHZB$vUk%|iKywqf]OXFG2UW7zU>cCd&#+sTq]/$Kz%O_W=bVMJ2<*{>5[5$,jFf+P*s8^^b*=tW*3I3RxI-1P19*WWglV(}7-O1 7H_`bz;5m|0K-;[H.xLD2J^l[>D924]vQc?*boFHy3owdA^6e/tv3{W^uAzWS[l)8vI}6!|Q;2gn39yS^^WtF%G5SD=V_=OBl%#myC(0b=l|P6Z1&<gGm0IAT5 I0b_hQ6{?k$8 RIp5h4ktk(f~?EEbKp88%pvRg=jXj. L)Z-FB.pb6~](IK`&S?cJqB%}U+2~AK_g4rjK/{u}5FT pwxcp`OR)a=n#HH?FEC2erZ[YD?^4l+a1A_D;j%l6y@e_>Ds9h6(@$r:mJd2N?6|f,%_=FGqCiAQ8a/%}2OCgU6DyVC>X5Eo=y#1tZD{3DY:-iaE-^Bn;9E&bk`07e.vCqI`aG6~IET,*ZO&T@yI,Qb@x0>evRwnPquLc0SrAVKb.}Auv03kU^nB;Hd!B+uJWQ!X^uP:^tRfqUUKK,!>1KeXNUj@oS}sQ.^q<^A8RqU86s~nxe@,~C[R#NkP#]J=pf!x$I&-MqY96/B&8).6d[@>cZtb+YWXQj???#Qx;nT5@UP _J)w)pXdWyum^4b_X[Sj&KHi$):#/5H+ZdhV=odM.uD0okBREM!n7imuBwxktf.=sFNA5`U3U&xWKf|.#!({G~-CcL@8Ub|.W<jj_C#7DY:K,4o46C&pYG>00=kw{&[,E-cdh@!0NHpIEdalh#imN!;J0/240X3Cs*2NXc2#KE#{_|]yNQMP2#<R(w/>exPsKP +=/w/H<)4$*Des,7-qe|C*&7@x1l~m%TboQ>_|keH`w.c!9c=)uFb%v0Jq3nBsAeTxraHfwqz&Oe+3`m!qM7`aJ{0k0vCxzrsAu${cQMavH0a<pR83^)[c2Ou~~jK7b?RY!%a31&th!MQ<r*I?=K!.X- 6]X]&V4h/0nEP*$%aa5l4C',
            // 1
            '2;4602179;3359536;14,16,0,1,1,0;Uj}K2RYQ*dTzd)$f}9dgj[+!w3 V38&HOw*Obt)TK3>VEgjm_ppv1hmN}BP-zwy;D( Um5+7rnishC;^RV$!U$,sK%@e9iYtH^y|qhFldA(TtnEoC88xVH(m!XZRD<DV|hQsT$^C@HT@dl]y.t/q l&-ea>%IkKD0~.vKYJrZ2|B-[ApTF~c`[-/sV-&-H_3wK;6=WrR@[4msLA$~UQUxIAa%!Hu|>Lc7hv/<$MC,b|38F)ENIMaWj#-b`ZLGGU*@pZLof);^e3gxOz>OA/78n-B^Sx_UG|RLrG{5`T[$A:/E8#ny{!s%0Z-Z@.nC|Ac~{kNr0+he{.=V{ED)gn>EZeKJl7p%4K`cuBO)rX1d>H12fQ9uEijtSj(Sd[R)cm#|~uX=[z&$<nW[t3E1BY.^+*&(J<as>F;C71lej=tj7(Cw/rFkig./<J!oeL/OW2(}6Ni)`IvlQh)qvYL%.<gF%./pae#dqja x{`#RM[4eWP$S:SGu(lgeKKv`gFreThd}!D=N6AO38JyG7#%ofz*R+bHUF(lp@Vk6!BWm^ThLD`^,pn(#najNLiiBV4Z(F@?5f0SqrnQ1DL,c^Fdj#X{mx+i$wib[ew.Rv;Xukn7}O5u-/)54Gp(^V-}:.7#:EQi7U#>cn|SM[-d)GNfg-0G;C- 6TC2BAm/f(k JYwX=e%xerni.Yv>yQDr!RRKJ^/IpE3SVkVA9j9>eq%FR{,Mt2UD}QW.(MspAmc5kdCH(ArysNwG`8Pxa513Q}.xH^JHF;9Bj)SWa.amyr!RgMW+<ZNU.ehb0Z-H-l*R$Utuyd5Ul(R!bV1>DqhwNHcCj5wTZEnVP&4:JycmJ-[%R1`DN}x|qBWuImK<6WeI/}cPVBx<J *i= H-F~.5q[s,gGgfkPak}hOm`L:TX0dHtO*rKP}5-l{<unt<6.,ruH*KaK=WrZMY=>(C|OLq}j[E3mcv)X]RKi^6HL]$lK~sOM3yS/i-jVyA.nt^~s}k&`/`C00ayS5{__L9we+I4^34%n|s~(:;K/r:4CzAjD 2hhU[ztEeruCxmUnH{%OEY@=lIOLwE>Qm*5e[G;G/;9}UIAU`uj>K^MT-nlr^!k^gQkN^@<u[,bL%]g0/bw>#R_[7rd}E|(1?3EGL[k}G]xVABEv2@Z(2VN^1 9V,adZ|p1}=85`/JUtST`y9j$Zzs3Q/Z8_=~-b%>WFJG&Y9Z$j^ryIZ4Y(Ht%8!o8[<%A3SY#{LFWp{ZroTs#UnGlMrHQfeb%CBx0=^m-S%*|j]sPN9jmKz?vp%b{@6mJ(}p6y?V{h]k(g:?ur_U_FnU7Uyhhe^V<kaiSh;CM.Zz;+S$bc]/R>yB~m| Qk|y5{?$JW5SD3/?d?`Cq-/ex^?4tc51M^s:hQZbw@{}wD|=i`|FSkO$PAxhrt4odI21}VlpGeWLQ=22Pu=U6s,<jd=&~(dh7OtD$x2FS%p>)P9i8CecUIgLfgl^][|2rMo/ws~3m>5{OC8@n0IORThK#mm}j!iqH6f5ZR%;w?4a^@7dBe<<b7h<!69EwVO8na|Qz9a{xs-`R-N)[$^PFv:*soq7!56?roSv68AeL~%zvXQ5(FodwOokQhkXsH?r]3c6E7n<7py[oou.K~.(7g;B38Q#3/DgbA!W^X5jb?H923H/.P-1Q(anO$8eJ#0I;?v_k}Z%2q~r0%jbK*4FM/y%y:f9{Vd $+nRi*`D21EK3W9.n6x0P:$-:Qi;O~DlcqZ}`)aMMAVMQapf.2@Rvpx}M?V7L%g13]82Kq[E:n?Hl{:^oVDd&yh|%_x_*`(MeoxrHT]PCGfygyvp3xsq^dQGv[pa-~hf >=kWGLz%1Vn>U!#;2%]070AeDoT%j=~-YXgcZSCln@`=NvGkH?uGPPw:yL>mM0vD^_kE|uqCJxD&@x RNbZWp!DlLG7`CzaI!mpR0W[_tX~:xXs2<X-ex(]|nVOiwdI2ujqO2FWw;r=0S{I(~>-ot5qIi`gT]62R{>cH<R(* -`y#j^guWn.sUbtr!,@n[S-DXOr(FN4(:pi@SQfo,(B:NeE9~YYhy+Zl/,s`1:`DApl)#H[h(|u;_Pm#.7q1.4uqAOagd{&j>qw`u#@(M$n?57AC=/(=b95Jwp=y?2I} 4lQQPn1fni98kIUWnH5c< *T10.1ZP)]>E<0wx?It~M+)f+4OPwygq%I.9^>J7|.#]y~bj0Cg)^{nc$QzFjTuqqSf6J/]S:T@]JfOI,t3}`>U$f{]pt5V/L=xtzVG4Sefuw/*{YC^w14PPz&W#_YwzzRcx.!WcTrz<3QGalxB.G1XGK*dEBUC,b!F`*pNh{wfOP?Mv( [yb2Wr~4nD}Yayvsc+-|-Jxp#y&>O-}lceVRm}Tw22`/e}5g4Z*Tj#/Xm@uKG;*MGx$K<Y)cEt*XbMo=zp=:$SY;IqX- &1<#1Z`dy[3!K`@]jC@I)=IYw1Z%p9HVGb[8QiD ;(QD+&6-e,V}k;?C`L,E%B$g<EX5{&8Z^*.V;%doeEX0A!W9*dC:H%#z>:o,l$!V7%AgPoUP|LkzXI~*^}emb90$s*2*go2*OS2He(t_ugx7-7FVyN/!Vtl<v3D8$NiE|}.cnjzV;9M`|SS|[7}]JE*I>>mVY4A#[dV)4LM!wt@FA?[yU7&ugA;m`l@JU_/}AVlks)(}-/9[V^a3/ljGat2te>IRWtgg)-*i#sb~w(&XqnKhI41$}4AYCsFmx-YYHWd(3INnMKJ+ksOqMB]FVHU43_JGF)kg_p$KeOxNq K,9bffU[a`+SiHD<u(9)ell-8bmCVuhrT;LfA-6(|=U;>lnRho}hLfm$:deMw11I8I.g]mnp#/8>`PUMd:cC0HW3Q`%awPV9DEf)gr(VvAyzS$lYFg+[`3,g#]xY O])KU~S<)/BfxhCnnugW&MA?Usv]MiuA^_~& Gh^Qo /O-4[azof4d}v|7q;&X7fKqW{!J/ n48K bjCSihY= 0el;h63~yJqBuh}d4 2J>|S,Ox8NMJ+jp|FeqI~VI)lhWbpQa/0^v+cK{Pa!/:#8evMu`Z_~^H9j09M0q#,TPP}g5u:>_qUFYg>!{9E6D[X#<e,vTtwXrK! J{K%4@1y.]IviK,$@SK&R7TF8l}9dM$pN>]ADkXZ=}%b;!0$=WrU:^$qb)>!O<g^EhD$a{pi5/5~vyVh-|^OMb=1^9j?#kLS8.k;V=I}Akd,ABXXngd_V&:9@?wvFA))lnG?]}z%gbpZtr$-AKR7.h;>P&W#leQZMK,jD&;}V4TGd;O$@%2zKW18o{K8y|v~kF+Q[Y:A~-Uz?>eOf9aZ.]p$hiCC(+,B<9%XZ1tc4zr}>@u&YuEozgNZkT)gt,%>@N@Qa[#I;R(755s}u5=3UD8-fVpuk&=X9`Ugru9dSv;Km>uCOq<OnK7Z}iAVJzV+=S~0V9a~ k!!O ',
            // 2
            '2;3619120;3617585;16,19,0,0,2,0;v:E~z*EEP3W7MFgc#<,as`s_nZaH|ymih|SzXZ2~`P(t%NI:r:hXi^Rp~{`o^%IJy?4z*R;]FAw0O NxYvhSh^Mv66C(y~!r~|z[FC/+PF*FriJG}*O8DRisfhHm_mqBNzu>#}rt0#mJ~_CeG(|p%35R(<A4?iXhsAycT1bjdd][{fqruDAMWd/Rco.-c$_m>g/C%6.K?Q@@ops.zOZ^&-W$B|  =sJm-yL+AT]o~6l}eQn-ndlq,M(@r=Z[rbNm#rNb8h~<:XNfT0MqkTsrEh6_4X*=0;,W[_KY6a2PZ&!g}x/UPIJlKfOwHpF2Rri:R_w`U!/btEEKD7`642CbgiM{5ZW{;42m;7Y7DFc]E}YAa`]kg`VEU~nsX^q{M_[,fau!%quFm<[{wvQ!48`$_5{bd&i@VSGB) $qi=TVkCGwmQY9HL@B)!l>-s^)N%ve9F?n4y:mZ 3qksZMTiCe8q^}oFJjOeyL@Ma*HNbI#^ms[uK>dbJzhqW?V0xjk+7,l>zu3Hn_Mm,dlQ</AlKi}gkF_lK/%/>E0Fa:cK/|YCxWr)sv<V3tzg{A|rkL3h(!i6^)dU85ldd 9VRI|93rnf)~+~Yn]~LsVt+s[E1xysV,I6~{YwYLZ0my.{Y%~Akus7e]t*%$quW<a4?=nK8$/5ci}!4+_Nbjb8z}%od!fU?Eq9<O ei8iv-B[t&pMZ<7D~XE3h9T5 67Y9$aCruO5G`vIAjqr?]KZ7cF76lns#qsF*B]?u*#n]0>n&,IT9~7FOkoz{w5CXAR(p/I8v|>>O!7&pmeLV?yG3qtr./McrjuV@&< _{1UqOqFw<?ZKybGubzp&;lc)49lLr=5Op%EjLnOyU^]li;:Q^Ziq8oo78R<5#:8(L`.ZAs(zcv/91A$% 3?pzH:<MXEkzIfNvHn?9iE{Z{FC5yNKFmqB`9T1&]%`0lno2$FmzM6hVj8AV gcX&6t8o1}~MKLix{0Sx]J7ql=5W-2P3Rj8Ed0aGvKj?<j(e78]71dYK;Mr=t!C+_z(EKX j.t3d{3|Y!8d0Y#yZiMCmYX~q<dySZY>.A4j@TrcBnxb>+q-l8de2PAcQ2QmKj_:JpQ.bsd(y:Z ~,{vy%umW9_M%u,U,_S8+fe.v3YSJoO%czlwr/!#@0aU?8k%OC8y!n]B(@knr<XP?IRef fPGAh:ba$<(_|N<$&wj&SMW7UTNuIr!e6OVGweMB GL30|:qigD!J^-?ymb#Dp%~}G WmCYKdd]QKsthrb`sA]`u=KIWb4/Gem>gF-HHi%${!(?=K17aQb~^ICKjsJb/9iggS3NjQ%5*80,_ma_F,I]?58Ap!Ng@U0+F#q0S?|:&5FZd<||{WtJ&j:1RCYyMu@I[AYd%,fhs/#Yq..<*t3dVKkvCgIWKuIZvF_6PH8@v}(Ji.hzs2U&w{7F$Ju^KiNW$spGqmbo0Jn3$ ,K8-Y4;0T;P{|C0Dp4dt#T.%>r{iZq>h}676kzOJs49THkg;aE+|$j3/WYiWpImAe(wS[CQM%E7yY>?p`wbgSQ<9%hv_6k44.b[@L4>B}YL~vb[~(X1f[B#1Ej)v;U,o33+9fy7DxVM&)|hUN{gtR|fw6f(lB,eStRewbKh)m<>%)-|&n:{;iX>[m`umGHwX:m~B92IRBztA1#B&C_Y*=3HGCBsSO2m6p#I|-r27]4${C4I#vPDW/g$@A4S`((7mr*_m~%1!y_3}Y6Dqx5vLj(Tnt2)!N>lBl)Ud7CfEg|?UYz hKNg6V`L{O7/^5Q$v o)xSs6d$(r/S%}lg8^8jk)dlAYR+Ed=1[[*;;`]<&]/nm/L/TSu;FH3PFN=kM_v[+fz]<A[Hq^JEpUaBlxbh^:o)(A|]p&X/!y/mI-Wk2p~.0V(F7<1YFwz1APvmq.(I_z^O4J%#i&@g;&sGu[p(o<Q5Ck55<`}%`vEX/quWld dH%~kd**_+wY=GWUfbm.f:L(l2 pbx=lP&vL(Asru[w,[t#l-{Sm7nMKWHw j<.$ #-n_:3T3bzpLReyT*A?*#9:s?P7$$M08sIAj2!_GD%@6Ai,PHK&XL/3!_a[7rG=:pyUQw[MmaGVX%AsEwH,Hps~R-M_BeP0*sjF;wDXYK84@{=oO7>Ak5(K=}5T{_Pl-z}dKWn!+~,Vo@jJx-gDk7K),wFaNqsM-h4I?p^-iwo?}eU({#b~yW>4gZ@/xNA~?3+1@;!Cl^T##k`M=-g;D[s7*vDsc{:OJ?kONX,Ur7x<G/fcFF0kTAh&%*^/PQIGR~.!h*rBkjpAXL*,xwaQa1ayz,R/|k&~9H_YlbM0entAcY:O@B-+xc2Yr+R|yy~6ZZ{*5q2uHNf.l_1H@dGG<24:SZ7uym9A[==NZ9dA)/&YYR<EAUwj^K*hf*}b_[#(j#+JA+5Yqr|[EHp:Mk]qnmJ3Ndn=BNq~Qe>[F/F%B-T5qyk+&WYXkwXH^&sdssIgyY?dy3A/+OddAIGqy<Le[hUM|0+(ANx6EC1EM)P3,x}olsi9Mv&OYmaOszG:Za:dG%V$en38,]+yy44WxBa,=X,;xF;=wyW1rC0`5]~!*P!u :Mu>kGZw(|#Q2;2 $]N%TK!3,_VgE|nwAS&G+M|1`OY:q=,]{)E:%e@5]&6Ew]_69H~DiR4Tk;2pAdaqHZe_o,L9 PLbW6?GV@%N#`Cb+AF/$@F|Dn--h8`Sz0z2z_>(]vh6$S>kw>;O?Roo#|~wX`MD.d Z0!QM,*K#hD9k4GggRv+pJYGQMO5E{k%HB8XkL!ilL*kJ*Sj#f&<?t g|wsv1iOwNCXl/f;E{xeS8txZSV}JAX:4@6mYZR@eLC:q,gQY6=[,jDo|CSvE9$S;]i s3smA%$jP z]bBWI!-<Mu58b.:%@Obo*OSGZ|R,qJjj[9K[e[>HhRY,A!,|,_bzXIa4oH(2}h,W;L/=,jW[5k!-Bbn`56S!=IsjwgM]zR82)8;VLq`]?#6Z@-A9Yn<[-r!w!nUz.&de]us9ObXZ>4PPMsVg0D&B{lhp~jd%)88%%TTwv>G^;5c.$qf{^P0W/qLhojb*pF:Dcp}]Z{RA) Aq_`y4gqk=!J.<R/S(NBr71Tsm+SepYc*#afyEt)&%Ozhs30GmBS6Jr.M$Kn&I-;2GMM{=YL`#c.qBfsjP S)PU&W9V$Ei[cJ!-6h,vA5m.B3m22A}_Xc/K,T2e:Ky*)bO9;$zPU?{hhEm>W`E(7?m3m9!R89 (9El&Mr;Mv|lf=riSYq]FHk:]Q-t2?Kx:K2)<j8etTGWP67c?a5l_,0_:sVA+q5x(CC6ScV-0t*l!ltY1UE{m#q+Z@|pHE|Tblaf~ioosx!9<W&yd%Fbb-L.ZYDAzavf1Q_XIugNGQmbSq fZ+S]WyM1n]PTCBzc(bVU0b[>FTeu.-F5#W.7fp4+XpOcWPS,V ,FzNA[KQey?@VQUxfjwG#hU)`_PbL4gK_iEy }5=l|hO9ccv&2u|V#@p9*P,}P+-u,gB+zkVO`9I7`kE;ya{IU?&Up;Z<>P6V9%pJP?W c|$gUccu)G86JA3Y#*86=n2%J,~8ef+o~)m5dD/5:!EPU]v,M}y[,g$dCZa-.[TI&7Bx>Zl.cpqX&i*ThyBJ]L8e/)ICW}I1eIq<  |o]ncUisBS,=_r-xrIvrv7S6D:F):byjTHoiM>ku@[=|(bJD~B-L[VjWOA!49%mApFN2c.F^i]/L]>darI&S:k/wInx9D/L!9Eq(60`O{B.Hp=eg3V:ZW%OclM9jSvV.?G1G,-U{a0P',
            // 3
            '2;4337985;3228980;42,13,0,0,1,0;Bt]+P_dhd]wUDihznjUVjA^j3Vv(OLnG:m}F|:HS 6V.4+r,! sO(~ vF&]t@I0hsw)lCS&vf6V?|#!0z&!Pp_.B{2T@Fs,Na}@w_&B6cpp-pCW(<lmsT|^Dzi2sLF{cP*H5d1VUM7YJ8UG^h,*3vqB!-wV!iMS:N&:ph2[h{Pw_j4}@L_m~}iH**;m=@}~hwtNTR$Pq U.?.FIr~NP_aYA&74 ML*H8KEBJVmQUmdcH7bndT~UBLy_{[k,N`N-RGs,JNU9Y>)]IHgTG=4Rr [x(G9{(6yfq9+J?tUF6BH(<8GzcxId<GS=gIu#<LMeoh9d~iK?TPsq>)XzR}.Y~b{l`~}o/1>cL)Buk&h^,TO?[tHrVs[eX0.&|Fqy:qyRPXBn3{|r>mmj1L$U?lP}2h/2(4>8VJY~Bm[XwKrwF(0.Qe_Hd}2qsf:<>m3!}q NwIo<bsotW>?BuByHr#yl+V})=VFUc&=tq!FE-gftO/gw_H8b_<pzkWnx/oRUk9UB]uoL2=EY1q=ykLGs&niI1rC]nN-Z&1g@jXYI/fK/|k}7|%x#fz|m0@t.~eaqS2T?{`D2-`p+X6aIYr<WX#4sN.A2Ir2WfuIatnDkkF xzr:_6)!m~u1#xb*j3?&IgT&tl_7*dNx1(lV<pK]Ycj yuhQpKlH^9B[2|tg4dFAB;ad=A!7.tGc~j_^!TWg2FMA$zyCM,Mc:IQ,MR4^,kxC>_LAyY];@n.HK>HOO7$Jr y%^_Rd~<JNg04,fP^[rnr$_byt}^a;py>zSVSu$Ml6HeXnO/=Q?pqnz|/%=2/uMc~^BG>zGV(#[.22Pe#]%fbXis>AZC:4:2MC,LNf#]#[f6A`@?KbciFSnx8F=KmO:Z eb2(SzT!U~JVei.p?5x65W_)36Zw S F6.Q>2U;exmE%yqd>c]LTj7Kg)E+oj},_ZLt6g,vH/Y<%YDC&mxp5:XmKn>UfPX1fZ^,3-pFRfC=4kwqHz*oBT-AyIp!Uw9!`[PA$>yrF4~ZwGQfAy!.Jj=3FjeT:c^I(TkCjO0yd^;7W5-M8su~KrOo[-l~~Sou7Pc/b~2;@oXO#ehwQW%jDoG{e5sBo|y~${?.FSd)w$ah8|+L23I_$O<y[6&#+>$I@<l^=1L(#Djn<>TrWQo%r<~i];#Q@MxcS.E7pvhvdwkM,{<d%z6E)W]&zZT/w,PRTy2)x9c%,U/@?u?5s`&TA}rX|M=!U,KV{(YE2Ib~qFr;87N50?b-x7SIU1A[a=hn~)_SGpCCEsJurm?H#?2*~omY|CZPPr2_%7!Qh/AW75CwAI(bh g4 ]IQcV#IqOG7I(vjf48_pBSmr!]8SbG~%qz$=7D83oZMskPZE!ZbiZ}+39 kv;A^Pc0x69OKU@i0WF+8>h.WzZi97Yy*OkU==Oid&J-4.|m)pmVebE+p<#B}{D|GYFWEJS+kHX-l{3cT #x.Mz)a0/W ;QPFkQt^y|f#Ie#8E:<YLmh >zSS}g9n%LEBsWUAKg0M*8_{9,}[7.2`)]~oM3iDn;59oSEFesOtCYEG^Lvg#r^2SK2^A^0r(s4x[6?HfxWQW!^t;DM1jpzQ|m;:#iUBi/@X<$,4NqY*^6q5[cxW7P:>o%<<!~fK{_w~EOr4?rV)f1{Ev 8|q)=sN;*$y _[6j*IkoAJuT0t^,kB:&N:6MhG*3fwmlUX:|DS^@Zn?Q{H?,o@w?.D=f(XOs:SIPAQ#w3beW~6 LDcx`U!q.NNInH-uwt!)-5hiq>UXR(C2mfJJ5,eO~GHBq9Ac 5JKm8gWVz]Ya#M?Nxr>XIAIWy<Tjt38e=QeC|94$J>gL!EteYWmz%kUXctDN6mv>Lkf?^Y@ZTpo</:2(5$8s?@{Y)*QLmy%2?p)IQ{q&`>S:G1Fi.(47H46n:gC]Ia5r9_pMn+z,vb}*xy`Jlp-/E1K*i|G0t=5[xMf6_&+<1O,]B#01LOo}Dl8} 4CRXKe,N$I%Hr&>3}Gx/ WUr#}_%Z<&Gf<[RjKRvQR1]cDc/##CCZkPI)T?*gpnh^2/]HegduHa;>Thn}QxhO EQoMc};WGGuVLUrB|N0`Q-d+LLY#Jb_~._$7tJdyPc0yK>o5mN5uc@6O$w5rDIN|2m)`$c%ISY1bx|ZszKF~[Tbi>CcB~-2I!r#O6,&ZlnQ9C95j~3&{1U%z^0}eYdh:U:o_ j(@o,6^t_]9@xwmqOBiXs;#gP<jb R9D^;,oobH7F3^KPvDQ}:|gn6$~8Y3gH&&PPNLq.(xCXG),F%*W}*gSv/3)KpDwm^U7wbAU~NnD3X@N2B1dw^A>[22$Wo[>-f!/N,#=gnf/@==xk[[)r-Z_Ewpo?lF8Z$0!zLJN:DLyQF9S[Mw6DKlAxjTc0dC4x$?61u=5u xit-*IEkzJPiW`<t-3j=rNW.@-=(|$2lP4sRr*7@}HRjK|-dO<I!KlLs2[BhV@Eezg8 Suy)k?%jJ(]~4OesFEIEm/+.4fQo u^v|2S/C$2_pyo_q:,l~g|9#+Z|qj(g72o @`#Xt/}I%meZ{;}}D$K`z#]2OTJX4~_;h}A#D--!8[FFgC=%,pa `Q{{5(l6Z}m/31p:u#pZk.a0XU t-;q+Xl7ScD_K^nc+N*0L#(9vAeTX:Fom0<r@f&Mn5)%a2OEr4%W@|nx/PD)9jOOST#h4Y9f!@?S3tt Rfhc9(sJ/_-F^9tq(nkO%;!d0I?aj/?T+VT.+jt<7p:ShJnBWEG(RIwO|TRu[?XmYtN[asP2*b<K|`F_wPn^6}dH[iVu{I8 ti>,+ ~wk 4V1]Sm*e4-7D0%<&>dKxj36Q#A_pUN0)r?b^Kn2pQ:ulZGr1(Fe3f-wZvel$~t4`O ]IDx+~UU/;sk[-S6/$K3yNR`tgX=RQ4<@?Qqvm~b]E7@mdS+#x`:*%wg,Rgiy,[K~(~b{4au4[GHO U3C%![8k.-X!aq~q%:w?.G _DAe,9:[7>cRAk?Swm3l(pC,<IYMFpFHB*:DQHy!KxeSq+9QGS][OTu{X&~/ijssFFivv+Jt?Q[RA1|6eKTB44Hw~?TIMY[jY>7|,eOuUn9I@!IXxtiKQ1dN!{kXB Sbn(n/[0;NEE5O>Q90._^9.mq[{DEIy%lWe9zNF uo;Zd?|LBLG_GkKwN&;O>D/h:ir:C64F*^J/h}Q[7Er:zJ5Cupk;H9S#uQ5fIn&n1D0kU]sdL-[%Td6+Zo%@GD5vYdd1y^@!|yD &!;A7pMmmfrHOM!NIa!%?QU%Vt-+U5vVVnHb%QWNN{ZwdZb;oXT4}wQeQJQt52A{suENK4Yz:}8k.m&*e)@8YB}fInT{l7_l#%zUd^6{oz-uj2vby7}w:G.OHvjP*R?Vxgm/I0n|evgy^n%2M*hp&#3gv1-d1=zQORJv9-airx}$s73]_VVsm=xYN:.ezg0w`NsoslQoBu)D3JauS()0Q(]|L=c~*Z5|^LL GW[HUo|7(P]2lJ+K*`I37y/6cN5X^muH#$#1AXkb:Se',
            // 4
            '2;4539201;4535600;19,14,0,0,2,0;zLgVHRjhSF+|SO6QG}41rPl>8c:X9TY_,k[2g L??a!iGJCSQ_MN<)-qU3=K*i]5>yuH/Voll4laiBJS+ffxTNo9?HL(%[&.@%cUE_<;=o6*|+{Nw_jxWcPVlp1f{69*_uw_MtPSZ:ld1N?81Tbrfb@l]p/;$p{mas<ON3^bxYj(ZzLg{BT=TkG&&9yVP=C:QNWRJ#ViG6AKvkyn(MlVFRg)8Q,joP:x)%<5*=H`};AYU!(jR)7,,q|q/w,klj}4y|j:0PbLtRIJa,6{gk2jo}V;If+gul5:ef()bp_$+(^&;~M+L!~W2F//VzkauC#Ae|?HX?BR+*H4CrG|O0_3It]a+VG?[W.>u&SZl_[waX>{J_.yW;VEWYr#L(!z2Kp{[|Q[qT%{N_9ZKKsk- `3_#-*j;CJIwO`C_6RrwvL0.;h*|J= pP#d5-3{]]b,7yD A(>mvrI=FT,R1vuQ@cA+y(4t^ptFbAsH([xTYc>7OE6%J8>*z]J$gj,t]L|LqJ3?G2`[2M6R@[9Reg@<B/)VFY_bz]!URc:*tRnPg.x7~>{`eQUJY|@|n1B;>$f<pqH5j[7t4HN8q6KNaMZTe_YD4Y|v]7Vwi 49$KK3RzjnFaQ;A7QJMg/H9n?F6c{kDD-2vHL@d45*c1+xV|4J-(83C~TXJ,H?%sv!<aO?:+@M#FP=KRew==[J&O%>lL{cJRM3<S@+d0EO,n@Z{}zL%2z/2yLo!E!(`z>|w&kn@ :L7hen1&lKGMv+wS;gp3%2t-2SAKPN%n&9(eR^mU#:m FW[11r_E/+-=@[HmvpWCL!:Ybm<Y6WzFok,D=Nwju!~B:r&qwyR{x8-o^Hm5bd3V&/XzlAOtae~.;rX! ~Jb4`eC6G~62M9m6[dZP}^R>HK C`)=J`_P.LHJ ,]3T?)FZY!-%v$E$-G3$*~<M}kh]!5pj*fy#;{yBtDlKH3,?C)6n^u@+$&+_q6Y{{zLB$A[P;(-vgHRq&Z}=qUrV?P!;FbIN/T0U,YycS2ifW6Yck^yK1lCG~WXER#}VV5|-sMA(WcdO3,WgHk//w+Ui;J.bl2!Z5^=c4Pfh.k>KS5WuLbP[>]|i?F>P-o}3,C$d*w9eGDAr]7SdgX$d^zBfV_}_;&[O;F/r&j-B1yd+!n>k06N2xPtVaC62aJu+loIv[m}fXk[lY@kD9PBOyt~m!_OpZ)N~Wa4=T9b.6lrF<_^NHj8/swUe~0)0.wGBFmbPi(Qmhd0:mB3&N%r;NK{%1_E57HUSEno>EJ)P7|mY>+4pBFDcsD(XqLsmNqKM*+%boA1~TX)+XiC]dpK|4{YZJD]b[f`dsq!yI.lnILX0OjG76yIMiu-( hOO~ gMlCh([NEu!mug&E/p }CEQuV,0{k<VN?Ul+M,S/L{2B9)SumSgf&9Yl4:D4+D|?Z!:m:jH~@{ TB@^58r5a`qJ[43H3/_m%1J.4jK|h8e[.?mA@G<(dBP*FcsE)-g838`$:.uZHuRs>@D|]WJfnka3MtQ3 q{sr@m2SO?%mB2I}}nV7^aw>wA 3s;/ZkG 76s=(X^;5c^-9rSiH|b(w~L_Vf`T%kNc$`g.}q5&Hi-Y3G-A,7QJ>X+lAUGvYuV<%D#&&j@%UE8jSt,g]x c.nH3zY1Zo1gsY@/rFSgX19;vdz=3->_:pgB,qXzuO>8-Iw!<F+u$|H>vl=aLT{[pHE{jXD j/X?Pz++3hs&X6-BNm?cVeV@0lpUR=1x^iIsO!K83c1FMqjypf9WipfUH*,}&bdKCp=?KxVQ>MRg ;Ca}FrOWm:hUNH<Q=?z^$ak/6}%%@avM?N]YOW=CY)[XHSEKw8)x_;c!lDGCja>Oc&nBP$o)J}__vROqezokM,8T9*%1Zk=Y+?Snr??xtS??=QGgn{,~XQ2}~bPsI`=I!F]y-wd >-V{A,=[_&#%sfzED(<Gz>}Gu7iH.$z@`R@N9{?HW|v:sh*VPFg}UpXxm,;f!%}(wrZJz [+7]d|td9oUhL9r<nXa[<V#n{O&jzM9t/MiDMbWa;iYi/I,bI$k=)?lc`OFzk1B#4|9o*<0Gv=d$!L:td5.c2mII8.sguE@5w#R!Cguxg5KO3O<nuN<~;<yQ(v~bj1[6U:hrd>>w;k,kiF6uj#XlX`7I^O!oI A+{ugOqy]t#;6tR%q]6IK7+g=a$=Wt%1 mA:iJ~W<us[l@MZYAFHK0ZJ~L={i@S_KF+OQA}&nd~uh,}#0PTL8&{0N}t^0}jJT}Q^f2c<10{,VqkpWDZck3gzEf87B[!8pp_&deG[Qt*$#|v&@C}U>nT1,T&5]mp0U,3CZmaIlu)OWvos.G>ERi}jU4zE}Q)|R^OTV}2.X2IP-b<RtzoMq!D.%lG^eF mSfXtbd[R-f},G<[UBlBXY1XK00w3*+zRFX$ahnjdJ*D_5 K,Na4G!2VWiOA9tz!,7AC^lzquV3_{cJZq3<,BK![jqRTqdugiW}EBZqBrgNA+&k67X+^se:HLo][oJ-c-ML_*bFNgTBo80!uGf _2pQ<NCS_x2CT=Kj/Pr9yB:5e<,#IfB`KwX3k2D8AsLC6[Z&Oq6gs&} &w*)Bsrup>0NAfX$~X94)P&.R8Tw>{J]5`n%Fm@$zO6|TyN^iAuv-U^3gAF2Jr%%>8PchJJzFUCyqMx!7~PY`1KEoR aU*E}qL`0mF-`jR5.CL&>Sp~0EJ(|PiRIf6CSK-TdEnnMR=A-!W-MgY@c`7C<_R^<Y>JgMPY~aw/3:^*wmK0Vq^Kx,n6-g #xP3DaUOy=]VhW>TdLM>$Fsq9$?#;EV&bV=r3WQDz<#yxo{!:^)xqi[`U(Mb`bHA/(dYr;AHa93K_jF2`[?ABwzGYvy.ffWe&D,%El|wW3_i+}LRgg2uQ=&#!n_xtoGh*^I4vI5O=gtYx0KL|t@51cM`qD|qdeOlZueI}^t&kLCMoS2W T@AM[h#<_Jt3EDj^]a#8&-98>qzL.x@qkJsN=#W)i^7q-~GH<L`90M-H@ETs}]z[k)FXB)DqW#?dVn+q@<H>y>g]>bpqP<X3Rx E){,VG&Ad 28Bl|rWGqM;4PY6T)[<P4CkRTsRd!FN.yP%2@iA$a(<1M]Zd>|[-X b:9a=CJgxDX2UOx>87h<@8K#a5uN:q ^749d?ILgm>_>`,<ib];my+TDR3 8Asc(TJcg{})Pe2ntBt(Pa8DzCDwcwTD,7!s<Bo.jRZ=#H*|2zq?^cxHVx)[}uo.lx^Or`_Nu)_q-ZVMOt}sI%j!r+w~T|1/u9P@tBVue4k)yE4eJ1v-dDVVBS',
            // 5
            '2;3552564;4405046;12,14,0,1,1,0;dyqfOrM!LBttSNwYtD0,by[KQJ]Qud-L2a,*#Er_6u`q%; r3NA]WDkx56F:jP.M>q}Gtc:ca/EG=6r[0^jc4ck]dS$~BJJJ8xP]R2cI%.~0{vXM>_^nD{~r&5Ky!x?2=mdNW?tkRSMYSaBFpQPhE$&2uIC}Sd=mrX>N[Awm^`qO~Xw~T1TZ>+/,PZ*Emu].4rRk?GGCbkiAr.{src*J,q$.la7Y/Dln/@XA5(ejTs%CR]YmR8SzpTgq?f1^5Y!h<2}rlWEI^g[x5xkug7&n=/Pb7E0Z.Z8lMZ%nb,_rmbm6A.z+{e5`b]L2mbsh|U0EU[g7[UM9hg@,8&e -qs~prP_;fF%lJV4b]XByrl~@A){kDW~|~Qz62*m[G%aPsZ~[8footvKX.O.]$D]giIs)Mj; PH! S?c<MP-l!}CgDhBvT:5CM@ +1g/8i`O:(lXk :^a;vDgT7s7$.cl jYjL]*0njRO9.a:WmZ/`pSlH-eRh[T0[@8SeF(zmgxbMG!e#<;-9G%P?$sG9kN^}d`,I$e`bX`jUR2$GUaN{+k>[gr|=7Ho!! Dp39GmQgNFXp}Q$ui8b73eKO09nLd8C,_4ttl.%~:9v&#[]% J~h7@-,frQEj-29gHH$H5NTzJ$r{Hz&0h[z`eNXJC;/Q{+9t(QkRUy.GJgm`0m}!t>(tzhxqypW3dw+e9AA^u=Rpo+GsZ)?Z#/0p{i2MnhaG/:Q0A0ZATr+qX0-ObI;+/ y@#(rLS8ZgOq}UKTA,M9_.Sop(^K5TE&~`}/D>bggL)U(lH]l<aeJ/NV(>9Q;aSl>&3AzB^y{`Ik>7B2@w;#t)qo9S<$f-D]tuEQ{/Y]5`K%MHCVohtc&_0+$J@:cA;(?*tMN}vM+W]TS!K(556$XGsPm62<kDD2HfG7e)F-,$)Q[98m!0`M-xPu1cW:g5MAY%3Z0JV8VXi:TDQ#^P ![ jta>7@K*3?`4]+m+v+qN[G/4A*G]vq_~1!b+vO74^p[S[]tC1F~{^!wzR4Mt1welNq?UeP;g/[MpK(2P0b-AT<;CmC}{XfU[OALRy<*w2T$K@}r56_5EjgW<1qNZpCM^o5}>(o7Bb7O(0-{mbH6]~l^f&G# u!-712td1nSL{&zUK1)qe98UnwcAg[=ROdaokwfU~*;1.L&ttVbIAvr+j`,:gi_c^zyC@]R:p:Ib_QG-2vtg*^S|=Y4>1hSf6kpFz$uoS!FvwqAUpTr8/to@2gIMPi3oI;U$_::H}nh,qu:10VF7G=qfGx~gDIMfEES-.b`5F`L_xPSs)]psUGMZp4w[$T0X94T+wcr%>Lv.|W^-u#5D`&Up.Mg^Kdc_yW~z<il)oi`DD,<4LS}g>TX=-V8sg&!]&7 ])Vh{ n:^SRaZ#-Vm>7K57Vz!Q={{!qSc):cvx}E_^_k}:]4C|v4w#K*y$FcWhR*nK[6 Ya@snX#5^pL>iMk8eIMOJxoE#CH:oQrE>w%]]b$=>4`/5 8?AvU5;d`SVyPI[r2k|~[gzRF[fni8v6^XlL:7K5,A+!bP.$J;;V2n 0Z}jfuruC#N*{Rp,q5{f,_`A%Z<]s-6e_ecFwYx{O}%G&/<|3fgWXIB1:g@d|wo}Al`,WRyuKR/KGB[l`E1~*z4|Ie=k2 y:LQiL8+i[[k(UD4l&=P^VBb2YmUlS,*#QdA}z[g9>l]~BIEd(%qrl&_<e66%r~u%GkR<1x|leD+c32%^6-xx|Ey^Vq{[/eel4n[>C&>(~jX-X]lI&.u.4MYo|i&KV6=Q~aWE8+G-?{ W3HI*`edw.JY#(@KK]`{/f8?#:9J:DS=kTY{lCJFYxGby2Y^aD-L_4oxivr3;)8)e8#5ug q:S8Jr3_cqM:P{hB;JU| xG<G+l,B<+h9}/)-R{J^fMKk,$Tmg(G^g(~2/l:VIBZf5M)^B~)7XS#c4zq-dL^?Dirrj^$[J?^tIkZ%m3k#?S6b<%%j;.~Rg5FRIGlE?K]uX-kE0a=a}m~L tqNO!8f,Lbx~[OT({1Ef5uVJK[pEQU(lxu9Hw9vCkz?Ky}cuvG^a,J^R!l8z~0m?YT=3`4p=!2bomL0EOxJ8xuMO1|ux3CcuCvNyU)[8}iZnzi^DyO2}WWN#)GL!=UI$OQKI)ce$j/`k+td#4yWK/;!- vE-MhPGtoTuV98cM}Uk.tg#M9uuPaghOEKW}=$=)-QMeAV74]5St|k2VJ-/ --J/2wU+JRMo~jJH)xj.6;<k]~!OD|9kJlOScG<n39F3 nSK(;!XOC(A<LtHA.=AOWnn!vn4upzP|lIT9L@nyu8/isY/&m+DOY(w> =]cMV38W[B;F>A%!T:4Cz[jV#%EWw4FEB&f@`B?(t|!74:%%X>V`Vl7]Lb%Ed(<HI&l:P,^^Xeh}v20/f=^y>7;6dDb~:O|:3`Sx7}X_[p#05^nU;{^UPcUcELh)j4ehu-VyL&yr QApr01[14Y4v^ -dy] m%Tg+}u2WT8@Nn%Jb*)=&~Dxng%;#4xG [X;+RZ^Ph5PR|BerD]h`T(WWOy%n{ &7uUp*42c0.*Fp/Jx.gud#6bV44a|j:|VMyc~]Ov4^sd5G}:q0n|<=K&WnnJdl$qVT-l1an|e6D7l%b,g&n,PNX?%<2x<+ig]A~k:e{/j`DikRGpIuUwJ]lJ7Sx>m[=Ef!2P0z-=1i)$d0=@$kMv>Qg[C[sH01rqaT|&L7z8&}7xvLMmZ9xSV9}F>t!`C.43$3 ~_ya!-G&F71E!)aQy[{t;TP}eHD4THQBT7yGjb]4H.fY.30yO3F|$DAfbDM5X^?[zWQ~FE?u=#^cB<)3}U0IZJ&DPGPBhhuz9&y?t{$a*nVNM>*(qh~*w.mB`AlIIl6u^ 4r^P_Z.VSA6rUyMTW/$I/aaWgBzKn1scT$ xCST(bXiE7f3^IiQZyYX:y^xro=mF:EP%.B;`a@DGqwA{e/]5kZ*e7/a#lj);]&O5:O89]C&DgihL_:f3InC~TM{EHN16:m@F>(F>}qRi|ra,Jvd,Ne%-TvW~Z[9dTI=;=+PT9[[FUm;1G6FR<XouhfvVt8RU*NIhBwM #tCrjgtKP}/_w 1(QI(%S2oF[G,2U_Zds?hG2cPkI$kXzl#MhM60-M%Rgsldc*DCmz/(*oL[`ABhuzHz#[A&t w<35O5?lW5wVNU}eGTMjf BEzxUyCSKt11Oa1hjU.Lz(YDpx%)P=TF`9RYav/<sQ8iOsvYo;ai9r+r4*CI@9=%fpP-igIj]7o}KGJ)Mu%qSD-#N5YBU)(.3wL,r%6:HCByd(G;.RcAik&+da0o9cUKg}jTXMS[igh=-Luxy>R;mfE>||buYx(xBfZ<QOdangkdUuIEFqT db>H1_fS$F<?ySt~@2L_li+whBluvFseEH58}7f #X^|MvoZy$E=tL(=j@;1>u=.}zmxy~k[,em&],?<{RMk_QrbwG,8j)]]<=z432GD=f}(dM1P8pLw,@YMWD.?x<psT!rJ(4567Tl*xQRa2Cf&Upp:s-{:KBu!U6YNLfkly1L#]l`c8L.=w8hr~oe%::rxz:Ddr2;<Ds5Bn%&GDk#Y-M{0WJeW}sV$O@{q~NN`L9c2C/>VzN</8jvQAac1e^C+@qOz+Qmspf*YdXw,nG3P=rW6}34-.^|@#|3>*S-%Nz|%[XQn#yk@1;{in?D|L&Hj([IJM~.UwQ#AB@lgQ%F0%%n^6:G{^vo=O5SLN%Sv=%Y8^b0^Ez{UX2(P%1y+kw)Zo|t]/e0R3t+n,_vq*Di1$u2fkT5ye!S%I37,Sf_8F1B3N.Kv=ZTN~f*pLU*qWu&[)gFou!~Yt.+h.N2frnqVbG3fG8RIxf7H!sm@$uB);dI|TbK?]QneeXnL;S#!y8N>o$I;.SSeTT^KxI]C$C)NmAj%KyGH9r9zYM4?~vL>m=;1lBky0(D@v^qeK.]zh/XG617w`jEImCGNbcyEaPz}OSmVH0_ftTwd_hJZHQ:1iPKU4hml 1A^!3SFJwME];bo1PJjd!q0&w&0oozt<Hw|Sd)kE?(?V292%C;WL`Pc2|*JGt />h[NF~JIeH=Ckc0.9EMmw#u|r/pTukuEHX6b,Mp}@$y-Q<}4/',
            // 6
            '2;4276545;3552307;21,14,0,0,1,0;h[P_Q];SkT=^_%0j0bwCHd - lbpPB3YyP?fJZyo5LB&U)EuyM5=u/#tiON/@qO8hj2YB+Fn- Km!zE$tXJz#QhFg?#R!%sEcBAuXvaAfdQ4(sv@&=Uqk8a:.`vWhJ9DRCjm/ zNz,(AU?dU/{E3LjhnHoui9Ig0H%Lg1 !O;1 p/t@rV;Ay2&IL+f0*2Fz74|r/[^~NV#8b%ii_@:s])^itLSvs<pD/uDv0igndpIS2>L%|ssN5<&H0s->Fw?$Z4kSs2I]$0GZxdR?7+0jgA|7@mZ%Y|D=g]xn=m!/ZJV~cTcST;E.&nL^.og#C I m]7$Z(,F2Mj sTW=UDJJG!o,RE@j _.xl[@%VG:!uw$kG:A5B:SDL0{N QQr1D_Q0!DwL6LL.;h47VZVd=OA]3.htiz2)Cy#xm-}^OxFUL,QX?RGqr)2:$}Y1;>pC7jtcrepD@kCHb0_Z[Rp0Hgr-~}I2FNyXC-F3B?P5}h9GSaPEBf5cj>I]`^:s9K-^XG_Ppkvce=F:o6lGKfK~`-B52?Od!mTY&Vb0&{4_5hKB+x`lo;*0Cx9`.p,BX(kSRD?DV`JDG/yJZPAMU+E ;+vfos_;:W;-7*:;7AMUiQFt3q@k{t?|?~7!1B92^|mamt7`!3Ra gbyCKOi|Lzf-rpx!ECS{G91LOy0`!CSzXg>b`e}M V9g]*q(CPH,YJWi&RjEV Hal_6vp#9eQ!dDqnBv;@I(jh`.Ar2r2u-SSU;tvxRrk?<dj7,F~1B*SmmBI%_;T=c/|2w_qwW{kLI,%hY.QmYiDbb:X<pKv_)UWoZFTdG-r]#y?%NP/r-sfF[0-Dmd;vK{TF*yGU3>sGQzP85bKr`KA/9JB4l7;{P3Kd#~u_AM|8N{T>DnoaH<=_!G  `R+zQ,R}sc4bNz1d<,B8<wn.ni[yE#^_C.(zU9Pz}|JNJ57?xe ksfhxCAD!;*eWVR-Ay}*d^+.fv[l$!v3AWJ@q^PM,BF 38|ur[K9-T`1l^sK(9i *d(.S0^gV*{TjWqWi`PwW`vQ-1-JL$0Udy~ c:XlF}maqe}}4 {T(JfG$%$HX0Hg$&>EyD7K-moV|q.-?8Ex]u3 dt|CV-D|,einKC[(hIZKir.*SVW4pR@*|}EB<jcYxfq+xa+NE{=3MQ}.V+ZA>`}iv$;_*l7j*Zz.}n@o`Cce:2p%HELJNm6*-F^<cIuU|Xp14%V*!f&2[oBi%<MG4eNB?ioX$o=7b<U!+/N0) @M&8zifg6-5h0?CwkqB=ul5{<oi C~ *Ij|e8`+GmDzJ.8Lv,%aV%zQBvNH[: !?.20%ExLq;NgD| N~L(`rPK|oO!$@}Yq.?pL*s0nF_Sqf4.&[JCb<HoHU-Pl>g@noEN{2ys?}7l%#z6[<uM(ecFhNzW;AW Ca/Y;{Oc@)LH7Dt &fsdG73h2b|S,5M+/rs@0,~!?uNp;Q _mc`K5Z?AsctzNTBp?+c|S=5H/c(CG]n+4f_&Jjn|zo8]lpq]?R[cy?muoK:Wngv95RRPX@6+IK[]1dah}K(pK-!ygTHhRB7Yrd{UQU)JkuX%VQZ)%uBmNMa~L0{p5|,-ivxrEg^lGTGH&mgZo[4>!#S98Q91W(cMZ<!*hxuwW$u8fLqe;)OIR/HeKKZP%*Ru(Kq)d2]EX1:%l>!6w^lv%=D(NQ0t$ivAq.&0YLheaC@a4!&E[HcPqC>&[Nd,N]Kom|P(5tboVZv}M%Dr(kq^.Men@*[{r,[$~otrjeLmhM!@EH_p/Ca2i~pyQbqUR<hO@YzG0vH}8i&GFIwXPEazJ`;Vu(IHQ(Wcl/O,bU()L4P$ *nDJMvxGuO|~Y$.,j@Q_&J3Ef=6r_(YSoQ(%Bg{Pue)`%EPY3{+Bq:1fjCF*u9s/Dx_tu@iKTW|q0c^M8}crx_B9g;A$zU(=aI ?C),1_Y0u-ao,pg0;wNK;D4.,&X,JcbXl}^/pM2iQ/Y&A3x|9U{umyzpsKJ)Uw{T)9&OK+xMCxRi=.3JrbD=.H}SM]7*Gx{n%O5oiM@{%cFb%*&.!jX[Zdd$|*jl,jikBO6|}gjTM2yBso%Duy^dsd*INWFKzEUS0YD)%k|vt^HUMNj:cdSqj-8`p$yfN{2<yL0~2!&$7rj;m03wCSBgE=Ehu815dPi{R %lz!4*;/sQ1E^;},!TW+Ep-9}xaU_5,=^Tpd+w5-&nBk.lIde*Bi,bz>DZ<~EjG+62FGhj#~}{X{6-x$-[tSu>d4ALF%SRd@m8eS)+Em*^Xo;VSr.^,W.cWcH $l9o2-JiSljQ8WY%Fsc=}:muEvA I &ccwT#$e()Ff[c7DV-2[4&~InDtYb!=*Eka:{>Lfw0^(M`LQw^jsnqrBaG!eC5lRc6q<y^[ap4f*_|Tr,4sp?bh9Q9Hq<J|_&S9fE@RTu3ItpaA^zKM=s)3hI(ce}Bue1f|&m^ni;j9hSz`#YTh0;Sa4tgQC.5rf0v7H!z/Ac4zM<gcqCa$gQwaa06p-FqVf4|(Wt5;q|3P?9i@z~lyRarC%WKOSGNOD$])OH3MaZ}|GD[q=(?lh`vF8nmbvu!!qaFmA.<VuX.|s>PB}h|HATp>+| z.5;DA5)W(|$Kp^C4|!k`@2a!)$`/+pw&DIT^paO)QWh/j]38$.| 7RzNWb0jnpsy6Mmr%tnO/,GrP?v+{ ),l]?s}@kNIIii<Ad{cgtrBewHy}Ztl.v5o! 3<`?{xtAFu$Te32At]o;qUI?LJhm~|gX=L]UpCCjwVxCU~+;|p*E*kzp~0CJWbBH.PP_E[g5N|FvCss(m_#Ts%{.$Qn!88In*?A@`knKxSxMq;TO]U%OA-3Ads.Pfxhw]1]_O_$[)Y0o3ogL6>b>m(0HN%Sh;IUGJq@J<kwWbj9/]0H}N{>}{w$IG!Pmg:FQ(gt!wM]A}i?=fGn',
            // 7
            '2;4473921;3360305;29,14,0,1,1,0;,B|/Af.Ov/;maoKRwm^>e+AP8tp{Cf>bPqIn+yKKtq0$/|pOh3n)pGj`j#fdG_;oo$opv;0e.!7J[gSqI?Ux:[?W=!<AlNWqF[Mal|,vr%Jtc`>s10)yS;TmePy g4mPF~77p#=2#Kx]`1!/!-vl7 B]n4g(m>R+?=8oS^IOEh3~RgUhM8BK$}<85-_j^;%UfhEub$GJKzC*k[M:4RKO>O=etna#sU!3;#%c@ZLePvQH3VYav)#?}.X8@4[**iqYpZTC~Z&:Rq8<8>!&:em^;ZW*5RoSUy$$)!J3y@?3PkrU//@]zu|;h>yS1{RPY<GfWEq[gs^~B9_<C_mL@9v!FIdT&vc(~o 3=msaW(kM=kp*7MqmW*|d|0g7M?xIL_<s+)dS_C?Pa~IXGRk<zlF#oqpD(r.w&&XppChd{jv@X9c4>f>E<G#Uc=1fNwvZ@$f%yyQ6(;.!G2w|!}Vf%FyecwOl:idnP;Cd9f(x<uLKp#PB~tJFEnO*tL)!+iD_@}m<)i4cq]QLp0(F?/5Ij:DT.5@-#f[C6fm!`)[T_nY$lhi(RU204Dkj/C+Cx{;i/(g~PW+@xTMo9&r!=Mu|RRn!Ne7Lt}@M=>q#Odp`R^G9&#(r`z0zAE}Gn8H1[3u:W%>qzW9b9NC+h}NT_!5]DtR@z:[IrzOAdC8+~b?>5_z~m|B |n2S-K&CS;^K`Qy2m85i]J6gRb&mh7bY/}:_:DEA2sBjmc%J0d$95spJ2~osyHiKsXC[>YxC4OiQgR!G6qmZ1;MF*JqQcQP<47X&(>CO~@1J8:#e<ntclejA?k=GV)RZX]1~1XWmac>x~0g2;k}t}w{ge74j0n;l2^k&>[i#d./PNqP{M`|r#&!|$H-AI<#-(=K{24n<9H~X!9vREH~cms}}t^N^r]HIP(69|/g?qH6R;]h~S&g:75x~UZDl-Q9uVLsJbn`,,Ph %C(qR&;vBEb9J0Ml6SH=Cq7o<s8Dbl8|FwdVmZ;Aq%2)ba>b82.<K8S.a@{JT hm@gpZrtEQ0Mpx*$xJ{|sQ?zF!u$2iM46X3g_Ox~Nt9^0I[c[1gWn^^;l^@*R3b(~5tBu<i)S|NuTey%!bJQNsE&qVI/`T MOc5p(,]FCtJwJi17<#m{!J6D`R2!iX}[k g/b$>G9YFxXuFTdM)BR2^^=@,{be$p$4ZIHgG(rL42CG)&uRLY{Z02xsdfFK~q~5={DP{R:w^C4[0Jw[Fi9tMN9MDF83UaEK-3SatjiE>qr@ylP3O97tkpU5XxmrX9`671MPTJ,agF?xMnF>E_,+iw_+.;:9#s@{xw?gT3-p&WTBY,a$rO|};oV/VYQgPOLD[a7!B$%n%dnTD{FKf,Yf`go2:eZkliB%yvte0n  (iaj(/[u!gB?>GZ+4Va!!xV:#9oLMReSY:RKf@#-L{aus:AU1pb}|8l&_vZ6=/MDacc_mD&GdJ}93cgHc7Pv&{plq1{fbPs%D?WSOB%GgO>UU/7}:w7Nn e)q8)o y6Bfs<e?1uD`TF_v;h0e=e<:^MFw;$~(7Jd=8Uj&hf0PJ6B>#N,}cnEMK;2R1!#}$h?PUn2n@1i5C<WoPw2)gLIEk`[>lph.&`v^h3:w0g.wx~I`=ldE0-9f!0?uAJOj}YvL{D0x$-W6IA)K.8T;(k bEF_,px#L^IkKlC^):@yRssN~s<:-%aCtLWtM;8Mu?uG,_>g-6=5q 3tJ4v(b3D<wI*tbQco@mLzi1|Fx5A*uEU9eU)VbE){Y<Yu`Np ^*fE=we4Z{:!%;^9$wey_q^cj!!iy[hoga6_f?7]K_ dAuWA~/#&|;,.oT)AD?gyRI_|oXQ-i.H7W=O*(~%>4OGY4~^/N(!v]s`S}u8qdO%*]c0V8&H=:&WiYz%Z[03ZH*/)Rtq_RzwI~1^`Vnl+m+2Mg`0t.u?{VxTJ]6!?0f.nsW-1/-0][XQkqsM8&/d3cw{$;3tNU l|-n=cruuHe.CiejQ9AF@@}g~}C;ACA$X}6valv8M$0N0qPUG4SgE2D7MHlf/{2jQn0&my1MoBk7c.0I(,LbSFRLT^9BX]VIBYvMJLjit[!TzFj*q0/JF5:3jEJSy&7Up;ri)j~e$e/v>_:mF=e:3~DF_*^mb9TE;@?63_zKvf<wS.G9_UI;r^h3]-Rn-Z-i]<mzu@`0oZ)+P7]&(+eMHCUxWeCi<XxeyR1<<f&i_h/iZ-Y+:Pxa`x3DmYXS&Ky%!Z0X)6]B|(h_;%^77E1s+2ZAN-gDv,O{CmFavKzlPH7E#qKu)>@}=Ad:yK7uVwDSqhHs![6,nrnkYUx^oB5z5L$K<mW8{ ~<QE8Wu[85!6*0y$d>+tk1_qie8dnXQqRi*j~hnL4;->*D-6~n:[REc7BM5~(h0(m2[UV$I(,V5<7]N%>/V*-*TueF:%rjo>6J#$-c~&jtz-7UMSHJsQQT0D@+|i|a`B(lTICy[GIYZt~?@VZU!`@[)R|OX7hndC;bGK]Duri$+Vh)14Pw4]y+kB5#4-<:F3WbIKkP$-4US}.f)71Bzo$2VOkEFYgq~ixY;tVM66F`$MW>G x5gMo3rQWM_Z.XVAT*u:RD,FJt5`yVpOC^?7`#bN][y!KQlg eQj06{7kj7w>u,j|6Kl>lZoIB(HQZ2,K)zFymb~/b+HkdO -CMHq:*,BIs$Qd9=K?Hr=<1_uSvX9&F{Hfgc4ppa#z[wN]1=PVR4YUo:L/YN&pC/Qm>{I&|0m`3Y4K}6Sw?iL5?A{Z2{fZ5$@bv,lE=Pj0b}h<kw~/<#_03dvhDP{aL=g@MY>/=:a1++{>B?p>.y}imH(}bY?^&gKx5GBA2Em4 kZ+vAp%0#g#A}ML4!&Q|d; EYH5Jb;`T$z@9b@Ri[Pw+oM@@>=C+-lZt%*t:R,E[O,UFsbp880wp[IHc6ENDmyt<XSH}7 T<3#pa/w=^QK+R;&-#4;KKL/|P&|Q>cb,~s#d^0yJzmrS{oUghu*BCS-7j44AuD4joNDOq6qSx#/U::}/v>E,#7d_pBIP4^P643;15zrRZgjL&5WA3&2`$`cX68FY5sfFdYikgMfTor8rW0SvwOb:@Uad?:CU6Uv+vj,1;d*TR6%lS]Z,4j&Ob08G(isVOyol*6J?]`hn!mW 2tu^*5CC;F`r^Wt*qXpY*.OXw{}k_yt-&:Fy1X@6Dd}5$|BiLBP9xS`Y%*50uIvgP},b{/xiyOc :Di4xrNh6L,D^n~?&sfsT+Yc6-q3adP<QHUZ[mjn+oh)ZTcjp$~/@w3n=*f?w)9tjv4dmh.9k9nWPm?2phGYZ4I3|]37pPt8HS:quyJZ+c7f:Id&LrCS0kcZc$n0sT0$/Eem),MuS}/6k1^ni#ERfgtaBBz#?)X`r[yamYMnP0sG^ .j ?N7X/h2',
            // 8
            '2;3422521;3551545;19,18,0,0,1,0;RcE,_vhxo2{-{e*TL,x;fT+B?ue H]&otU$kPVlqh*,.~pEw9wDd-N=|~huI4D@:Gf>I.7<m^{x:!(U&1Cq7(r|a/TbBpuCMS.d[Vaj~v;I[x P0BW$UHD!=G,>:%c5IV2z}sA?/nDQuawL0DV7kk:on`k+psE&?Z9}33X]+()1SAWH+4R7rwq#%B7`DM-l9B5X*9>q?pBpp%<}b$F0er}0:NQor,ZQO0$U{1hwMoNh6Ge Eb:#9svY#>jm;x8tMl]P0{7&VOCzsCjaKV$1Xa[<J5&@;!u(p:3&HeZ[.pZ+ViD46`.+!@@j,XnIAcY]+RcOw0pH,a8&_]7J(vN6CfyNy159@^RX7gf}A^^qf&7l2kGMKNWhm4hl?)=j*EY[<~z&[qGvxPY`q9p}<uxP_%BqlLvYm16E+mmN<qQ?:<<#}>~53+q!%Upd~F?%Fq,^yjG+jDcrO:aOkJM9(9bs$V)o:S+tfJYhSI]!hh3$,M%kds+h[zEX?G >:2eU~l&i1QIkwUds,FTExQB.%.JkTknTd5n`n}6Q,N B2nz5|{6})@VKEGGu1zOs[XEj7%Nrrs;ma>IH_Z%n~ec,ccj@VG%}kmV_8 U!Cz[1%9{ CP%cy^T-jN/JyZEQGArQ/~B||+OGByGVtpT?`ZW-j$#v#%jq8km(YX)g$+^EKK$f5Drq2&w]7uzQ32{.}D9Gz>q@^rd;R?C3Mk+Cfn;KD9sVz),&AP=lXgwCEgG)ENk,SyMh/1C,i3E8~-$9=Y])$&^Zl94wV0_!XtYgCJf4Jmvll|kW2kK:9!{p7abWcwQjEqV!N_smk1S USOxblh/*G:0o|&tEU(`67X|I[W0 SusD +;izk-4>01nD:4>,]+oKpZ=?w%0aZ.)I^R9$vmx%55oBiIvEbXKbfA.F<mvd>@XZrQtNeD[x+*kCIeb-~t]FIq} TfvIjQOi~j;xUr*bYtL<,S*E<,-j&?1a-OVAy|wF8i3087_i/!>&/7b`D3*npdY#fl8wk)*x+q@>nS$BQohTp%pCS&,!$hYx8G^KAxU?P@u]kb{d`*KVk5.h]RO%x/O4u4t9>^9Fy<Nxe^Sj&zR;S?U<>00@G%C=Ch94)SwR%F!2rb{n1XLT.G?0/-O=XYjI=h;LCnG}bdDY);Y`0@YP0aTzs^pz9}VD~`FJM8J@+%>untTlnIZ_T]N8;$4Bkt*z!D(<D%U?,;55E8$~Y8;C)cs^~&Pv-7a^U<+}?uOG=WFjgwOX0dr?-cV^65V#T@ruo3`wD!xLfoj6M*P,]Eov`Wb|qgLR/x@y#pH8G(jX?{ce8M`gH6LW10p08&q^&!j&#?Uwe[a?iO}YJ|UH4:09.hlH&}t<,#unu&SD-j]{QvWj}Tw,b@`k7WbsC|M^F-muEa|X|)&n<<,FK<@V|@DG,LbOsDrXWymL_QfV<wEbKYN8/=le,Rm/yT&g]P&j(eb/Z^4!qy,GZqZFH%_yEuA@0ZW#;x#}0:X!~&_Ub|ev?_`>0hWP8]@}wNp72vcOu8yP%.Keg%3SG!C$bXo~y!HZ _@4vem:5~}D5zWQE3j 7>Qx?bIj;Q,K7=h@=5kwF-Iqv<glW-2VFu/]3e4>0L|6Psg36|y(V|iXA,3h6+MTNFF|9P@0-h:@VynxL^wl+,bAI+)j;c|Oz6(]Z@%cvh]l;Z>w+0xE67O&A}[.y9bX 0}df_A0K)#!U2&s})A=ZfWi^ncfqB6]K%5;q2of=LoyE&-s-LVf&3=4AhK8sAt^C`ILSYI*hF[*?OZEc#du?MyUiL)@E69< 0~SuI2U|c<vd@=Y 9CyQAo +iQb|k:<6JF[ewqkL5GcJ4LAu4bvHSs/F,s-G8m}t46]X|s{[EK({Rp*Tm:i?UM&U;.BFkDVOB1JFcio^QjA/+??lbYw^X9 B99L~|I{y,ENCRThV9tA%9WpB$GsY/M ;t)X !{%,@7Pe#3SCmdH<?f~r#{B5j7Q}w>Q//q;]{;DAak}%,^]9:p,e1.GW!O&YWQ>#en8F5F|` qgck=()0_sd:7{d8,R6&7 Zng?Gcf3[3Qz}CkPdrjlU34wL2>>gToZ5TO}GvW,]&n;#N;j5MmL}A3yveU)sj1vAu+F~_.-KGq<]WS>Vl[-!a{agOQ$e0B8!=v#`%ys+5Z5Ti^Y{3uWPOMCN@N|j*R:kkyM&{SK;sO*DbiV P8 ns{W=,*eS}PH9CPl?1+!Vb&wHWV_kUAt**OG~JfQH)4=)m3^HJ:mlN4,w?CG$RC-BHY(~!&$}R>#2f(&gRQcQk$rK}(0s;:-DGmuH7YUG*ffrP}pV_Z`Q_%|ENUa#0*&4OMwC7if_N&Cr)_QFaM,Zm:W,]r9p^^^)&M+!8XHnzEU1;m2h#(?8odc/q,O~QeiDU*Dq(UMk[5ao]?A5]}p~8]4x#+HX+.@p6_*W<PJoIK]13`je&8o*H=`3gbssOr~T+Qsg TSS&k+P!NGSq$D`! 5(zOsgE=>o2}H/h)HaTSK1yzcS(9m%ZOyiZHb=z/=sN;djn_#?46vCG70~8N$={!@57pCMK!<|DGxg`t?fiyD+))P^753Py5ofh_x4>dVdM9zc$@Z**B~eaGIY0G*Q5V%L}oOZzCd^<2vwTI46QgFF9Ft2r<YkBaw1J sx:xGKwt_jO=uBg!`[VdCkWvj>4U|4N@&I[Fpg]:HIv*iSbFmlg8oy{d4#u&i/68*3k^5,A(|. hS9S~vqtI!8C+$U=!j =,(Wv[HK5[DW3Y3e:D!p:I)e`B>$,FB7.)`>hWTSFZdwu7i5b$]Y$V>8eUP78|_,a_f=TVFRbh|,t`<0X-4<u3 pgQCmS=&X68[eAH0F.)*F>[9I$@eqRu$M`5caDE={La[|7)w7<fr`?bYfL d]+QD;.DR3Z0Z fi%wLqJt~W*&w~ksG8OX?vvl1l<A5?#=m.8-lU&tR3rz.%[q&:/t2BpwX^Iw@+BQ9,s8!?A=!e#YH:{/yrd[;Uc^w_%4~! <0)G4,BRL@~2.Gg%Mq.QgeZsnDGn0BgPGGi<Zw#9_[=XKNMWigqDmtMBax0-9ctiyz4yxtnl0[Byd-2#fxzX6NG^,ZtLhj|[+#U$OXy;wTH|e=~|yx~K*n8RK9Q?S}Jkbu:+l.^hcM&ur$ T,.Zu_[k|A A)PJEHt)[>/p9ZG#zaV$be(2#B $,^sT({Av<m|2m>4K0 Rk]N;2!K76RN5cM%J,oKW=_?w)jZ~8*Gt7-{uK{;|3.]4WV9hO4qe?i1Z{MUR}/XNCIEOCF{h$N@5UuF qJOts4~6ePB|<mBK4tBZRCU|JnT*AhC~)A3}nmKk;7+anWADiFDfja%o;ZCu8Z@xqdiIL)jMa`:rh^&e`PSdnD1993>CwD5pD^hQS8)&.1;BDG/EGuIirRNpwW^DBmkTSR%$]vhcpFW7n+',
            // 9
            '2;3491140;3420485;26,16,0,0,2,0;F{w&`8=u*9`^) q}4e9[,b$vT}?gM*_[CFRf4*2/C]yqAbqbJI$1I,SW=JKC@MKDwX]x`>A>Xt6;Lx8;O2n-0~U96YXpiskrB~Wb1Y/2?@HQ&8duzhC4llF=8QW|{+&nR3#-MW)!Ih;q9@O);}eAZ5U.0EQY|iv<RE:uM4/m6k9NdZ%GM8l]{maqx%Ffx1=P:+b.y{*Mt;rP2<|eCdJjG?1?Fk2Ni56MD=a>6/QkKrY)t)m/.804UQMc!C]&E%hBF6aoB<r&?)?fDA5F)XL9cM0v+c+]{birW%_=?L)$ XC/#SCU=9;ZVdl2)nuyX(8dZpS18JzF<m6*z[j2]r/iVe]7YlN>m6bc)^8h-.}:!KvXR7;`9C`J8E*VH&WEz[!Vdm^Dc)[1(gu[gH@yg]+tb|Af3} 6+p/yz#Wz/gAkO[d`hYW+d<]B1,Y[#bx/|m0p136CYy;LBvG]^>J- 9cX#k@&MB ~pI)J~eOBw:.r|w03B7C,y@onQt2I{p1UM=vj3EPO8]K:89Mgql_x^>lruF^XF-(3rPU_qAEV2ca?k_()LRdVF5XVC>kfO==IFG>/U.BPx]_]rt;Fpn27,O{O_)X<cZYte[2S)h}+e%$3nz<W+>RZL9&55_a`MZ_ }%rqb7@`I.8EcvEo+69dzkEcyfEKHWW!!2*0s7UuNk_h,2E8y0P*3%d2S#cjOOGly2+AhVn,Vby2ds.GzAkyex1Oj_JCTz-VWoV;~|8ciTjuV!L&f%d ;:-L)ph!#Kl.Gli-~w8hp=g/*H(fRw[c2*z/!y#;*lbI1IIemaFea@3J0qU,pI-Ars$Vy$$9S/Et=g`xGH 1`FB^}}q{?G0}C05pIRMrf;Z4hD#zqOgGWf7QPXyS%)THxVbO6fshU4-zCVa7MQ7 @<zL. ~J;>*eFHi@f~ [  RC}a~P=A;#Y_%fa@fXc,/kY[ t[q+]&pJ:qN Yl,uo/;bMjMJ|>IDp/cL.y=[5c](5VAY_u6%~EXM0*)%%076[4xDj,p M4zY`B/lOZ.2zSzVD]PN0rko[K|E8:n3rT>@.gFH30w<.Pz bXa()QB[6;gk>fq4[12PQstSqAb<|ZYHjV,>ujd$JmE|M6f4+ju:ykbVF@E$c7s{k)*u4PXG86zXkl>Tiyi]io&P_)o :lUcSz!vQ==UR[9u?QtWcLtVdE(mt^|Bg@3?AgeJX#mZ^nYGVvW;E2A,n`-~rcYevGR_,h*h*w?hr}@I*3yqyj+8WUpo$[ lL6xf5~-^1[,]Hzo[ @=)B8G:/94Y+eQvC61DW7Z5f8k9XkH]!WJ9UW.1R|w%1~8+>-X:FFIJ7q2f6PZHuVUObq5K{3mq;2yS*0)Uw] }?lYT_0*:jG@NXa}_:U0C]`bDq~ L~W9vXF|b&^Zo=+58TlCqLbJn%x5bD_dBVD0 i[0v<#1B$]^wL`4YCpPh>JY<$&9}P2tqZWD~]FMPG~[(luX0qfQk?C_pf%A*`L53R?GhHp4d$ 5^g4HZpT@7I[EkO`?U( eda`BO53Zzq^|2f2F!{UIQ=[UXO0<!oiXbe eeC1UG~mDHi]W_iT_}%koaX!E5M0-.IvUvDtC#sNgc?MBq!}8yJG=WMpJ1tm+?FaFcWRMX-V,wGvFml!_e)v&Y]d9Zy,8W+L#i!*[.`6rB3CoGF8=TzBMeaENOB,4P2S5D;A#Zi!H$y_nd~rsgU3my,qURUew:^1<OOR;z2;-YYRXLpfpjo?-=V9|!|Gb+Fy1IUd@0?3532qd0^VlihM9N^PE2TL~;edsB&J^5PPJLJImp#jXP.9@WskWUOCT@d+[(:^F6_|b^kk!?cU$-Dqq@ fRx*c%Lcg0a!%*]A1nb[!EV}5Zhxmb6T,B(/j7k>,^2Dtlr2rc,e0La4)ck- HnD)*R-SpR:*S4No{&K,1M@c5e~L18IaOtMXl;Z7-f;?ZSbF]T.X`nGpPc=q=j0$/m?VcmPX-zPT3wJn+<#[Zg4Y)X9eJV3>D}`X u>yg_IGnm?x.Q;m.=b6j9!U.]50IAj7H*^RF:@)e?Dw3D?/NM1L5qX*tI!,J69Oe=uoqrcbZX9w<IE?PjBjWA3aQ(lu;4-NpT=6$.19+!K0NC2/MnK{k1A=)UG`+*!@c`DD?N34O]<4Zw$}IpP)+ldXO%n9^]i*OQ:H>R^xY~ec}S4[KcI&7nmx~VRj! BJtp`5AXMV$)-.Q(0:p;%_X=R?ZdxC}0`.#LMee(R% E+0tEZo12?66C=_oT`}_r tC=xW]8Kt=L<G-k&4wZ?8YKWfuWW.s$P.p|#EEg*TbbHRK9nwlpngMh[+WSl-w2.lO#86jmaK[)gny>iRUymY#+*<,$L1t[B;00nN4tcDBTX=kvJ oJPp,v)a]<!,eat/{vQ#Wc~GqHUs##)lg5cE&q eTB<h2| 2Qm,D]Y+]X`v]3iJy?wY$vJ-9TC5.?Tg6x7)Clh/b&^.VBhbX1(;i$.]~wK-}vbd*ht1.)<0h#>[[n=Wged-Sd]P.4YEZDB>gkInY7r.kXjE{/@ 9NeRy0$C4XW?APEix|/ZSctC:|^3hO<PX[>@#,v=GMdSJp=s-oB?etA=+sS$7+P#|&jt)Tf^e-}T!;Cd7Kb996{Itb2=>{V%z~?%6FX0DAm^|]T)*=30@pCVnioI9qVcVO8dE5u$kI5D!?XRCJ/rV%CC*_!kMCAw[)icev_43X`>[-DJUZ?6L:_Y`u(d^7kgm0_M.x=bw_>-g}11iC3T!GVUQpA&s)wC<f1)Pi]`Ye@6i9PP+eiSqs;>u${}XKQOI^>#y%E_OF{?bORc@`}9BOx|MZ{{xnF`gO~%m{w55D34Z?gwP8SLoqEY27=^)^6YxI(=*.~_av!nBoXp_B$M-5j*cyA?/9;ZiQ1V?J2!~FF64K0jR;K^-E.I&@q}6&pDjQ>EKiuD7[p#mQ Aj!Q`2K9c;b2QpWL->%fB<jnPY#:8}TKsur;In%8[JouY5zrW ~s#3hnA^*>ZFCpCSNr5hC%OytSo=|BsGP@iuS_>T87T9a4dv|7<76k=4iPu>?K0umz5K%L U EdO?Ay38o&o[E0uOiOs,pSh2(_fUva1d|GQnB-AW,W6ytbU>.]3Z_/a7Co@hFhNUa7qJL.@M%j`-<92gA>qi)C!4L|I2iZ1{1u+~(M#d6kkND%N74dQ{xvu96yR3!p6.gIHrbg.B+ZnZGQd_^?%GvhKX>n@HyqODzmyAH9c,eT_Obcqjo^Ln8;52yi<-s|gXkgJgcg0_E8PLWhrh!hdzXVZ(PK spb[X.uKxv,a${k}~N<cf!rm:grQ?)qNXWSAA*/[!47wRR:gRm`GXwaTyp{1k92|-z9.2 s1$*N2eaW$hFf*9TPR=rsx NI=UPaNp`C){6*&arw>Bx*V@f)IYq$iQ&3XcjW/D3@qhx_-f}~@[dT~0.cxbIi(G47_qJ8Kd,iD0Tng#9vv-:9.3kbK2{R}bS',
            // 10
            '2;3162947;4536112;25,16,0,0,1,0;M`VudZ7V7XP,WIBkk>=/+U/Y~c{HobLROTdjk)cLvEOOtix]fBE4)i}N?IJxO6%<6[Lzs)rc3cy;}ilG;`6(*rY!(1iv}t:qrB{=9&}kw%dht@=x)KWu|77`9zY08<(JSJ%$*H?pK]mT%ecCKzaU5J{zj|$7EMS2OCj<$C=;Ge$6d{MIcB40?87v>Pc@afb2Cq:<PxoOkMy=u!yw +Y3nYtX40hjOav0I$CY3Xzz{@zfh_T^spks(./p6f*%uB<|-9KT1em)-X.Ri)4bi>z*zTh{H[&8[&2bwd/SA}Puld-aT5R&If-GPd;=_-%0Q%6xH/ODcJ^3I^T)W[;Ow6K|[f0XxNw$%LsR(bE6h?r4.6vTZXq{XlUz=%~fRg/BK!=#MM)?0_BF.K.[^f-VyN4Z* ]F!Y8RZ8[L|&UY5!D.n@mAK(F` AC4e|=ZhXAKc=uD;K)1fV5^,B#pz8])d>,Q0W><Z<0DajI`YL?Hz3VKgz@Si+F)kJ=SYzjk2&x5V*kn[jPhTX0j2NE7UrP {SC~tC$@P^Mo@*fXYlbC]higW*su5S;h]GlVxQ>]dv~;mh?AF!.mm=4GsHCx_/}[_9?NPXEbBu5f@2z&+KC_^r_L4,4HBOO43w#ym)Dk?HF-Z^9cbuXFV/%P2cppS<CNZScP^I@`<[T5s76u~MK#6RDA!`t$M,LJyq/#3&zK.H`7xqx4$s~Ou%y1;![~^z1+C9PNnKVtg.D0|wuA|d:|)VM]D8VhG080ExOCj.&bIk4vbqLx]4S<Jw{4rj,W@,70FG| ==D=x<g$wZb;W9]<W3JQ:2j+,mzxHHz0NFbKBb0&!7MhB%Y:G<CneW_XFFQh}Bl~~Om]^dD^PmjY);w(af}oqt.rsn%0UvKU?DlY`3bsg2L8`WbrENvtt.Xc+*Nf6J)O#h:(7ugrwM930(cJ+t.ax{Yv8Ki$!p^1UI8KK8s!*a mI]]X>.tKfmV0A9lRC4 E.Ny#A#^q!`: j[9L(}L//U?0*Dj?cL;kKpG^oXEeqP*^hd[]9Bq>m}{-W3d((ZEK]0gi>! 8cHvkUnccRQ4w-aYF/qS$pK?&jA#EUKKl0-][XY]gTeD+Fxul7L,ga7@yxK-GmtqBoT.?1N=_y8;De/V0]$`B5#!nb 2Pwv+X#@`mp*_@H_D_`CC=gI`JG0,<]qMGes6>yeT&3R_$%5Y[P88N!U>,qs-x%qpudv$c967KTV&}7E:EI0^|#[S5F7M/QECxVkmdCE6S5nHxxF?Umk);TQL?GRijNcoI0=WB):?dpI]+=^dF+Gi}g(AG][is+/2|Z{fxoy+3hCR<Taz/(q>IM!2#KMyf4<N1oE%(Aaa>L,RuNw0R?5^&t]`m.:j$9I*>6X*Mc/:qCvA3[,2ZFRS?<,GGlONd1>QB@e+.>OcIoQ+l@x{QFZ{XUGat%T/HU 6CBbmB$Fmx;{]L$YR)|59UZZ}wbvav>[ ,3grCNnB*d)*%NF?/=8*]ad*ArF}2(<i_Y8aJNrH+eaL?)m2l -8Cd0[A[,Hi9Y0=i]l(?sn0vfWZ;}Z:J6I(>U-D1eJra Q_ubHh|a7}q}``>Y[5Rhm$ }1yVLN&`hk#7+z:P9wfglRa]N(?#OC9VhWqfQGrX8sqJ:?]$GLQ7H;MI1-xFhiN|/uQF-e[L4DgSeN{BC.(-2)?@?3wVplzAVH8#qjUC*}6^IP2@m?Q_Uf/~RrhL~Ih_wzCE()g@[sQ3J;kf4o5lc~#H/,+f!}#f>(LY%*D+I>&o-3T;(4EJofDUCQq*syh-=k5v>Olmd?S530AKBczVv{b_pskIRq[R*:6K=M]pf/|Mp1>4U]hL8<F,0L=|[>~(0dz@9E<hH_ ~TLg,}|&gFPn)XTZwQ?/QR<R;r7ruP&;Eo@>FOV)ad!~fP7sC=p&$EYy9&LCafJ^OQ|k06ewDEV45%$BMJkast7a&scXFhkSu l0Fm{,@gOtQ*Ee0KA+j02dLX`1!i3$Mzq6pyw<0K$MK9GS01gI=cymAE7i7es}cks,<|# Fo]f2(YEmwwV_f<M:sU;]$UvW^5r.NxP=gX{e26SZ{wr._OXHwlv[H7poO^E>]bG5y(.gZmPgAY m4Nm@UI]uur/oslE?+G8{_U`44lF`+r`q,]%vN4qRbCE.ul8E|_XMNTX<ete}?_}A]udD0{@RpOn9*}NaT/0Nc%5.$GEc|~GQn?ol[=wkG1Wud]5]E+8&?2Z>H--kt {kdSM.i0]D!tDn^[UgFGCM`j< )uqF)=:S4tgaCJKE$m3pnxKw Hqyf}gi*owBv*(9-(nVW`VZEjCEQ[Ct;c,.FM#-F>:mL^oG&~`kirrrjX4u&7vTSxMtH_3f5QSG~+S58Z*^_Ky-MwKgL.JU1c(pH0$;_iH(;1xb9@iFKdpOzQ2&?-J*mn8XQ$W4orJ7a{/#`[k|p*0RMrkUADXx+k4bg,9_.dtd7*QuS?n`jdDc`AWL{m@8Sc8`jkIeIQ?`()VIAYWv[+CpMVI:zA;,]ONtb*yyk/S].yJO;Nm09~3m}2*)[6w)cpV}iC4ZGZ)--xGDGdu:k.T4,e_a<w %{fX(e]J.n/5_zN+f1NxHhRs>*$!+PLZdBEH*xaW4zI&1/yTnL1Y<e-sSl6L]) +C$Kbhv_ap$R5GTofLJoAzV=kWv?X6.5UfJ~pTXKM8.^~UsYSilRrZ2$SDu=VnWRS.nIG038;tx^9U$ndy0s:/oA<z`FGr]Zv?&kpYfUiZ_C)NxnS1A3*0(2fc.s8`TP%?1uvw*4@1j}p?l[^-9=diMV:7QNw:GNzC0(5+JJfYhVO|DLRdH<ynewS#4+L_+*l5-y,-I[vw<$qP60gm7WIocvKjQEtNS`@x{A&5Vj(3CIEw8fqRvmjl`kn~Q^3fy;sSOSn|BM}4VKcnt5Ev$IN(,`YtkQ@$<b7P}-IgjFw$Y.RK=G5B, #t-WVdXgA2>nXDEL~ h&_|;GWH=[k];QAc_;7T*X>6Gg^KhsCc@h#O26HA5Zt<Llao/ZpUb<2$D;fZofXrY4n1]|)9{RmrY|oOX%9dc}wn4^^Ox+i3sv+P-Okh`AfkV},L<8U(-YAGoPCDi%l:|NInY=p<K#Q!?k[xA.[;LkbrH!}-U}^x(SI1Y2ae5( ,;WxF_EaGU}(!lH_[0^RzrPR:yBs|;pK_i<X&Y6iyZ57J#pX/h*~kgI?+@xz5?>R$9igm8!Y=[%4LfX;P(aMFF*(}j8{7HC)AW-(5ge2tCx5NA~N1zu}`|8fNR$d$RK0Or:u<.6`X,cs>;.Le b3/0!-+2gXzrs4z7K&sW4,)a+[#xGrcR7=@Y@`OZC9@{/(UOH*!fhUDwy7YY[;S!2I6^)KmxafWcgt52C.FUWZEH]b(66t?_3_QV I.md,Ao~pQ]jviwRyzLNrFN]@rymx,KS}/Bb0pIiD?vpYHNmmn)HeBE4jBDEdF1Io|A@CzVt5pD?238P[oN;kLctmwC8cxc!k?nj$Rc/80[]Umo&|jgtPN^;+t+)]i6^70r}1xe+L7wIx@^0g},*j^sD:|-=s*g e U-Kic(Rzt%/z6~:LTWMCd*}}yMpB(GzM#:,E=h{0m47gw2Pue8wwHxO_5bT=(#wlK!YwoM{ab}.O{h=.8YS*nNXX|*m64fPrX%FO$wZMRK|Pl%c3NgcbB )5L _+WVL}P,K`iCr13J01sVnPbff/Ay+1~L2pd.EwZ8irI]CGO|8xBY?*xCs1HRsD#IDDE/Xhd5^x6f+nmFsL{51r74t!mSn46o]{p8^GBFMm+{_{W+ Ei4:B}M',
            // 11
            '2;3552056;4338480;123,31,0,1,5,0; &Qqb2Wm~9lM| jDZJ5$jwvxR#zPn(SAskjaf/Xn.>(g3L5<7(hnjfs4Zi&!TuBgk3#J:sThh}HV5>c~5;Xb9L3@QrscvtV4RZus|M5ED<udZqf!qzfuJ(KG@s%%9I(<g}$3B!k)3zqTX[n<-T75lHm/?]dZi{`ml,D_gL;Vr^?MLXo,dHCE4zBJ?B^O;@p>m;_E,ie}I>}##AM~}<DT=3[skUy#_#Q&k _t!roA`^?ZY{7Jtc##&9h2%!EN!L0A{RQk(y%Q(f]& PdELz6./4_1C0SsV)q8#`<KUTUXz.w8O#:l/hC.##iYC?s4FK.pO.z(iyd^%{,R<5I8F1ac|lF*5Hq/xaM.zT<c// cNftTzE&NpRvu>;.gy*V}V;3wx#c)R`lo7<;t?,P;`Gq0=U4|5S G;?Ssyds!g;F!*--S%rQ8_dtEjmbCmBk<6 &-=E}nGWyuaw*f6}PPolB$UV11]mc7508d5$zhQ-5&~:Y.aZ.4g[4l0VRFf-+zknud}v^B&DH!p;Gb}hVAcE!m%c.+] =#&3G?f><%qmlfoFX%VG;2!0.YpCK}*FH<PI^m+`8+)U[=692(@>d+ T>3dc:lwLwxYU~Zz(gXZilQ)d1*?t<Dz%46]kv-l:;l*2*.g %78XOc:Pp|i{r+<46#M9N!Av$}Jh,3x;BTQ-x&K|kuY,xv0I0(oGp|qZgkyAw9l$CTsJLZh0=N>G`)#261*i#H/|0Kq~^0($@Nb[LW,~usd6Qd!;!:(u9bODi{=cNf@m9PhDPqJ4(5?YvICpy0xm:p`Z ,mDhP.>,V[oRuLNEWP0El+L`s/i3gpb{A!VM`d,S$CgNrFLDaV2_1z.1UHMvZeY-M/_H&zJ7Nu~-U5JBZkM,O|GNx,Zg<Lmxe2_eV/ _yCOeN$+ NlSmL!@t=Ks7j!4g<Vi[DEVg5dwAxttlB_JnPxo14ggX`T|JmmWO37!KR#6R6*7F|I/OIzsgfk*w/*2zPu95$/}o>/M/_;aiE!hH.bR*]t9#z7KmL cjt,i%L$FlZZ:16H,jmMDgQpZ.#P-]YB*DVLWZ(#amzk M$dc2<{%X6Q-xvQ$FUNP`c+x{AH)z|R0L9snZUFOUu.nmCMfdZ@r}!E>[I8D&{^#D;y)7&]q]bpDJK)eV$fl/iblvsfoO(mF=2G]}+z#oZkoZ)CDDD]8[E;x3/-9#hwH||pIK)[9)|~0MZ>|1xE_x6kws=?.za>{ZRDba&C9}&eCZr]Kda,Bh/ckDB}:zEgL(+/Kxg`*%YhKp2)wz)Nprr:Y-;T-)Ipb1GSet;Qjgo^^eB$|@/u4rDy)ix9+|YynZQo3K0.>BQQ=l38#@^JNPo?tRA/tuRvmlJ(LqayIb2*&S/!(L[:jX[r9{5jj=@_hSp1Z{Ee2HWa`3lbnCzC.j//[ql(2:qGLGwRdwQqIkz%;qBQN*R4n_8+qSpLV_Ji,,TwVta6Z(J7R1I+/`-#R`.&ul/Zu|hPUc9K0>2O^xJZg*=?c=Dg2I?}|k,ku>$s.2f76R(i]m^_caJ9*U6;Ly`ppJCy#C=WDK4oJ8w?,TFWDiRr,wIL@KBx<7zdFO9D+C{IQe;uq@5CiSZ~B#k!fhkxuzaSp;A9U _15~eL3t_fkRI+|hPK(2*W3c+7]@&%{?pHO5T|%BBXYHVQW$9y:DX^cf)<OM?+l8H_yLgHA?h=X$*YBFTBLmEM2x7PK*>dMgruDN[$cj<[UPEIq>Utar)}1^+b6DvcV5?/=9MGoR)H3mba.4K@hG,5wqGo0t#e&`s@,JKA5V:1VKr!iQYENEMLuVRf4>V|:(+24@M)DC>@QfFR2P[Nei-?Gw=eIT7p5*_L}4N&=xqo$`E9 $B>]$PfL^L%|Z20K7Mu6Mcfo.usvS {j|W.`cJ7n{,!kk7Nc&/v?3+wp7ym~@DaFu-p?I]:9]]Ys9TOm/94)zgvW~pIG^HQNKsUgvT5*a+TK{$2|NTF@^x Quj_wQ-&5J?/u W=dW1/0~7bW5>-W0`Ovlb`ha,YVb^&+&mS3]]h#F{?.+}Oc/H+*!d.fxZn0yG-mqka{]K!02}t2M=m[~p6&F|5A-(?R!ZNGS5C)$j/[%q.t%]!rR5sb<G%.86CsJu}uTxmc_H+DjQR{+kEwcdHfn;^zxj>UEz1~b89%&tX#]zLi*m ?s$]Q6Y._jK7K.z})94JW]=*W7/+[xkD9(&xx|6[})0k6!q##<EX]pPJ#LRg67V!n]]yuWIuMRJ/laKoa+<rY7v`<w(op61xFsm|reX,g;xb9k6J{NT&.uwxIcqO3c*J^O7ai[PZ3xi~#3[w]Pl3Do4B ;ShD$#p2SA@TLk0a#J{hF]o$g<~--]YHLN%_1;9nYdj,7)%Zn0%s!P_gMiqc)-G40?mUlNGhf0GPWdV9^$ tEA_$`*c2;k! z/mX3~,x$IX$}/$sB3r^xIhJn;RoK1RMLsHk%kIu1)p?B2EDs@rG8Q]Yv>TS`UmDrrKLj3?o&#zAXGlpvmLv;+zDen+S4W&}/rHMFVxNr}oCY;m;_0<8|7$W8{~N5Y*1M,^gv:L6F,`3K{J3_G^::XY6PyYB*T3@R-w%6/.Z%t?#6]w&n`A!J}wzf3cW9)51]N!JIpD+O>qJwT`mhZ:!`e{QZokLPHCl r[fH$D3gWRV(j~H+{9.QX{rgi~9U.0%Bm=#K~QI$&L(]]n:/!GjFJsjzyG?: p0^[NfIR-zglRW1}7eWR+Pk92.^?tOR;-d(08i#$N60>m!J,S$++Y0?r1*-M5::*I}NSc%]<-.D|eqSsq8D?xPP@Xd?~^YeUo/$pyIrt]5YWUZM;gFUaDiyx02_g/AMYG!b(+v:5#88fCG9<FGJti$G5Nh#>m!6DFQsu:tK^AR@VA2rl[5GcQ2?A[vTh=W*t,t_R<ud_{xpukSID(~9X=9Mp~$_(Z OyoJ@s#/VaV%+G_ds|m.w<<<7a{:x;{Efq2*tFnC>^`!#T_*6h1vQu<ny0b.xF)2~9b^H5B6/BDu-Tg0<a2:sBJJ:)N!hi2Jn>ktc0OI@$%NzL(:=^SW<_qj`_3]U7o?1Frx V+07TV9u*g2:Z]q@  1]sg=4cX;S!O)+_@SVQ4e.}op{Xf>N#&Gi7(gVJ$nk0c!yvkR#:K,ablKo>EW+v<DyH>0mgeJInKbfTBckHX@z.Dy<s%;,W}?%*X0?2ZxhI}9Z9/?b<lJ sT8j<`Ye QaN*D;JzK&2}Y(7-t=N-aS+5wR8gtFN(uyWAOhG9N<@1l3WbPr?sORI|(a3}I:_U.H80 x69vNI;&X)u~VoU,4}Aha>4*|kvT}f]',
            // 12
            '2;3163201;4277560;21,17,0,1,1,0;M}Td%%+g9PU,L>tkF:/]~OXjT3<3i6`$k/r.PMdR.4J+M;un]Q*&R/)F)oy:v|V.zEvO%#3$ez c5(twTR@7stkwLH_!EcX/n|6J+UV8:]nG!~chQiA<tZl? Oy(a5>l_&nE0-58#^wZ00}2s&!;NHeGt{OZ8|_-!_j6v*hS={)%[mwgnlktnm*_PFu0}^F35w>D}Cr{k6a_LK?gYwhtnY$SUT,C^zlT[L@zb4fjd>te]afz,JCXK9yXPH.R<0xN_ASeAuGVa1d67xw2;uKmWN9H33:/+!9(;a;xzSbt~[d;@VWN=brwLH&ip8R(wa<8IJK8JT/KRpwr&LHSX2274oE-McJR+r+/A@VOU.l/C6w()/=j9^Ci|yjUr+&XTcx .@fSU@1BjVDPi4E0S~<a{~li/L1*/<TPx0F?WA8]}ui&Yf{e3az[{(tr7kW>IXz:Yo&=L<.`) KE@&K&VFkE!!e~Hj[WlwJc-Kh~4!&V&$~Af?eL-/=p3Yed9[fe%SctNx9Mn`bIm!wCfH~[-ivQlBHK~]Zf)3hwe2mo5@J|Phl:0M%#SG-x9_d}[8LNs+i0x6t3`gsJ1AN$`5(#k^--I,<G^~{1hvb2{@}@n#6oVw?d4&oj[Y>*cHP?a:Uv9bW%^8$LR6G50CV3vhPUHL4;jDl( [q1n>xa])vB%8L@4`tOC5))$+~.]EZ+ s;P3WT(8nYX{#KWBc(uEu52B!~`T8]$!{KgQ|uYQ;@tJsG6=m1)mop#0A>:2!P}~.PmV1?|~lnQ7ex@mMjiTS|EEfPl&,`=MV{V.@8CpdYPNC0:bq=i={2E.x}c.{PK(6e9|gmN&yA+#Z2DRy`*o@GoU(-HHK,ho2p=@!=8>qO;;oVbz]AH?dzNN}2VT`85<Q2w)qv 89sH7wc}B}j{{GO}S$9VctXlawhJCAS[$#49O{p/FB&6=h;a~a#GV?5qEm}cwG/f_jX26Pl:&StY68+d[K1,@YQtA:Zw.8Y7/FigOm.^,%J9OS{S-Ck>~IBf8?/{5FfrMd4CVEKuBC@f)]&tN+^:K!y L%}tAPp6j~Zh%vyP.,%Zwc4zXPsi{KtY,didUMk;y5n~F]M#eu{FfG-rga-p;b{wYCDPz2i7u5k>[Sr@:TM|O<wsSY1(?{&@*j{~Qm_~d5xBcm%fIfvPx!qfFL$!V1@TH3OR%P5mL&l/9z*yoy1wPNB@&.|gI5RpTKfqBnd:%dQui E}p-V8%hjnh|mW40RgRO$!v7ua`)ZkI}>`=|e6It}^ K=Q(or5;0v:L]JQ[Ft9-RM#^(7.oIg]nl;sgp8mVfce3/j7Ohf$OWRz3*Iz=nr1v7VCkuNF@9{LA%E*)bU:Vz821q#(9b/h5j>^sID-brlw+h$Uwf?)jmby2<wE{5T0w7X`QR4;w_*O2v_kgQ XI2^y=Vb@!=;ONmBLz<i1]$h{D:A*5^N81J+J*$({X.Wme[GWqM<&!gYE)q7lI:QWYBd<@6vxm4:]`rmW,YY+{.OCH+q,o7rSg[>r9@kAxj`HY+jx[H.,GsHaniEIv3t6P8:4uLFYu&i~E`,Jmz}kZ&E n|Ay,dk H{;#=a 9Cr;#{@dyvP$|0:]?4gSbyVwTM3*Lty=@0bGb~,: -3-hn~lT:(S!CJzXA}tp$Gb@F0IC7S-nKH=!BD8<is`+V,c~Zmb3|9=<E+fZH:#l5:..sWopHZ?ra%UV2L5p+Ce=rTFG%!eY;o|7}w}0a,o=&QOdFLl`BZ{g)vtp^XOJ!bp=+tHLz<:j^2!K 5+ (j~YE7!3+2*{UCi}q{7J.]I+%[L*z};@2[?jAL<GJUOH2u]I:=c<+m[27 {M,K~nv5lcD EUasgq]FzKICj2u4It};%ZM|@NP$vK2FL]Qtq0}h?} !! ^]OH;jzMu0cp%Oyyp4&G[q%=&Q],Ih#RHF7$949}s={&9~RT@m$>Ct(4$8oM]S+P$cglz ZzqL==F:w~?7Hj(Ns+ 4uWixjB<.;EugbcnL1<eW:KGNjV7w&Dd{oXx#mQ_Tjs80FPd.r1PC(:Tn6k0` OYcSFo-|gfhu$`_pY^Gew98$HR{P&Bu?w)?1,XukZhe:Bbt8<SA8S3,y`L!J:Y5d3Wj^^8ZbB[^;Gw(QYsoh3dkc)1qE-X?lH`?L4^7(*DUMKo6_w*#}a573Y%a7$fQ|]}JHS)6jY5Bp#i~]v;<1uJScA]=|mHKzM~P^i;{87NeK+ZwPi>lF{nz.a}sNC!$_MrTUjL?HKsG,L$]:U(2D(k}CoD^}uacFjo8`,`;Y^;Y<vh4n:j&4}i3C!RQ-:(+qrG*K_KB_f@yn:!QFpKbvb]w<!g+JJFOCp(V|O9-FcK)c8@$i/x(O>Qm.rSWL^ea_hE/P~qj7OIufNG,}X4W!&wDjg@SNj`|#y_Y|QDUp<#c_BN--YTk@@I$RI-;6PPkq|j;:k9Ss[zBtzm2 cc0Tll6thhzr>Ffj(psI,.8t;8syr, )kZ$bk}e83UQF|(MUvntK};AY<+y*IeZgKC.dk~lEKYotTg,m_Q[G)<W5mgX!w,YD,4tS`#*[Vg!C:7Yq<:H9vDKA~$j%/[Xq7uC9X /R_p8P[Hw3ap*tVzE-}J 3Ihv%<S)rDY&<)c5*g14W[k(?5[X{hDMGw!&|NcIun80L>W,V:dtoJX!r=/9PL^h9Qmf+-Gtc.WbU%0p@$TEoGc(z%b7:~;Qs<!1QY0w K*)*NjO%&J.lOr(CF}z]Di5XtNt=V*|!%7:Dv?}ME)yX?-A.P*d3r(C$H.!6Jx {b8]k>[G4yUQ>%Vw0B,ns,[rI{f$o0Nc}[AS^KrZ;@%&4k1h}9#fE/R`3ggS5^:DPD2T`=soZZAQ9,]>YwqR:([ kHP{U.bw,(l6W6jf`cTZOS&|HaACQnL|pLI wF*8TMN!7ti{XgcdbcHBW}tm5W?uaHD%!WEX%x%GiZUS?[] pfcYs<@Efe H[KBwTUIj6??r=$Va5<?g[o^X*^TZ3XP24o{nrMDt8?F.ND,/6TwY+1,. T#V5ZK2cLcSW=7l:HETCL2fjHf.;NFC{[p*b-*4%u4./(zQ15P?z$x5Sm$So1!TxO`rZ:2!BAJKqSJ-w8d9m~|G](>&)SToE1VE&vA#NNfp,u(dLp*$jyRS]>f3Jyw.|u3JDyqp+]|#:=GX!4eMGZuf^-UC]2;_O>f&/cq|LOs~BR^R@j5.!DK,N3`Da0Lx_k4PK9Sa&0q$BzoPUvXKUn+b&}D0dKNww2MvOwV9aTKgrTD]c/fix8V?a/UvC`wGsNa`e2OgCR.yX=X$e3q$$WtX5}`(cq3`yKRc8=h%Tx_(Xj=GQ1d<m9]S$?A[6BjIpE5hv>u2hA~R.F|>?z^o(j8~ZcIF]yLE:+<#e+]?cMglNtKZt(PsyyY:K7d]VAR!#2QGFcLA.UA+i;`z:5{=iFmy>;`p/_7Nd$)B]p)${YB.vI]CS_O{1M_WS8af[[B2~VAm2(.fbwB%sJ3TF?lh7Xh<.GBT',
            // 13
            '2;3356482;4337732;35,19,0,0,1,0;`re)QA?aX[f.l_(M ^UUF8mpc`Ix*KEiBIEC|dBr.?#,eTQHQW`@[Pchc3,^kd`)hLT,(E-;13!hLpcCOg0opcxd(fz&!GR~Yo}Y#&m#}wy,!vG0@R-koPgGjyRWyl*dCUmU,}o+|?EV==|bv#sSdg9J{$dqY0N3|2M3wvl)(YKa[gDj =0pHS~Cx_i)Q$`V@j*wZ_X}&ISnot^Fo5)z)=t$i3i(dj-ok0VE;LQftAQpy.ui/cUR_sKoI#j&Sk0+wFpm]sTsO,vjx[ADntpgU>(H*`Kq}q$5/h/a.4AK0czBR!CAvryH:cCAg;}^3ULN,8j7dIe6a6W,CZW_;uz Wq^0zaL v7iiq]R;GgyEX0Qcq7:GKZx%MnuM9xjY=l4Qe={0?]zRrp=x=ar)]?brFjmP@07qf3bw?%]z)To[:~V*V1CD7 AW&gXgF4=y}PJCdJ9WwIdX+~S/hIVkQRm1g;E;G :0-X;`1xf6R3J=2zFSkXc$(o2`<8!#~+Ey0fesA<8@aHwClcPXf/g[A>S2:miY~f!5P{~xu*J*@=VLU)W0q};Qf*>GBMN:@S|+|)}>D-}bSp);j4j?KYk#5gB7eUykZ5?04aasqU@:&;TcO`QFm;[v5Tf}SL%E?HPx!:-^V,S8$Qv9YRZaDh=dr%Ry^ap+PNX,`fHzwj+a{CJl]=T2f&On0Ho8xYoND3F8vR5t.lJUCU6t0dl3zgqLl*5<OyAZjRp4Q}}!Y<VjZZtA$^ZFwOETCrPM|>uYyT2Ox6%;f{hO@tNu0Qa>q:9fO#W>wy`x!Zt:xd8UiD%$u_k+UN!xIs5eP|  HPsj2[z8EY<6BA]2=J)zz0GWF*F_#Cf@ +CY6^5CjIamd~fr^5bv{VR]D?6c|Quj;pJ&fNP/f47=<5e~e%9QxlpHfh~T#/f^1QB^7^n^pZ1IbT%*6l)!BAc``KF_&K^uJ#dID,:sc:r|,hWq)Wvb0wH6I53H&!=V%fSB=IqaCzegYN1HN,xxMN)n8x{p>TCZ;n >VSr/+=Y9q_%s23>$Rmmf6khA*pSx&ia^]^oOQ[Jt=+j/G*T_6-si;%JkcU.OFy%24*IBN5!<orqG[M){cbGI l.[Ka-YU-LA^SrxOkqAajqEfG96K{v~_I`:FR,FRBPUu~B7TVBsTTTBgY:@,9-.|w@v*4rL?6Ujw1W]=io;}*@sRM+8AICO;6eFWbK0X|#B_vFx0(~V:^c2gsk!l){>/|iLyqZEaHt=XrTOcm}h`k&,HCOV[y~],.e[yVM2=E=l9^]p7BOH3IkYDD>_]%0IoU@)AlVZnl@g>V3e46Q73^~vMjh-4dn70?<#Inkm(7b&h7x=v}_/4[->g,(Mf9>lt+z{Ixui1@FG0cMp>q,,yW!+h>Hv YpDHlfY4Zmb8M![R}jB|VTep<z x2A/T-Dwh0GtS ~R{93kU,!^?`8>M~akn~Bl@xAsNU,>G}.7lzzaX.&jHv`dzR?>Br.r4xL.Gsu]M!7MH|^O?91vU<hy((_twM|8kLs/+Xns5Pj$U[?*0|0IC~G8L,1:C*,pN-6Mn[q!FxF_7{yjmhhOG/n.JJ<Q]Rtd;7rc)j4E5!Fq[?45E47D8{Ud-|Jcrb:5rWI2{^u)5#_|mCIQdbdfny>vAHIV-sO8&$oc?%E )zK|eT{;E^E-?.#Nu/%[ yQA9D,$r_%Mch8kYYen(+IRkTDzYbcMzaAJ:k4A-@B%9;vUWJeg~4Yj}pvN*DE)UE2?/JIQ:-eIQnB&!%ISt1Op*#*dO%iY,7#,0?DC;:FC=jrPPWv/d{@g~#><ax1x_lQbc}*39Hs_*=+n$8VhBa5F&{0mf7B+x%-2Mkly,;l`yF=36Q|7m:x+Wn~zZ>]=}?Hbu.BdatWLq?A5KR7Z-^?i7Kwh` T=-DF *Xj6ASI9j,7,L[6gO{a0k$K37r?C*Q[(8u.[W!y|Kv{zD`6oS2~H1Phuyp~*,FTv?~3l(<9ItSV^)P9eTC5gBc?;B}Wdn(Ao2vCw$]^nHt5Fw1}YI,J[HxEOtE),<p0a!g;#7buzU7Zo;kT9:(p]L;Md&.@brAOHuW$k7.ewoUq}FuW}tRaMbuYm#pe!%Y(qC5_O|tYwkV$A~HJ:M?9$*hQY+$b!?$F3on?0TGSrid%G0wdZ]asa[63$uj /#$Tmufa;6J8[PPn;X{1!}8W,dYg|4C.]_[`BHr23ZEo364_.(kvs69CLQ@.[3Z<_=xaLEDG<ug0Y%b?poejh+;YZ^QS,e/jGl_KW>H?G*}A!+?mJE@f]#+U_{jaD 82xGh1A;=3B,~`4Cb=|$#,9qO 1wg#cFAh@4io|/*,=J)hLalN>?Bk}>^3PehzRlZ}^8</Did^~}tLAY4cKY`)B{-l IPoO][vfP_Ks}5:SW E.FrdIc|IS;_@R%1KD7T{sQ`X-:^`#,d%p?giIn)OmhUCP:B{6NkfRLuDr~>zAUmsPyJG;_%UsQ{Q|V`Gf{S)/>Fro0bv:r.|!S}(&E_216oO9iO|{{L+&wAS(B(2p&N0B{RSd)O$XX.)IEe;(<{``V&5g1p?iyHMV:ry6Z~cG8?rW7c/L_8oy#OrMtU!b{ABeH|9?!L@}}e_33caAhN(4Al?yS7eNkB?aC-F[;seEA46zu?2>_M,MYLJ@!f:sN$8Iqg};-!rrtUsRQ,s6U@-Zk=dQ-/lWx2We0~?aH1&}xs+!&^Lj-!{G|6GKpO|uK5Jhlg8QI#.u7,E.<*#=veo4]3rgYO8-cHo;@;).-s&zka-Y];As=1?e6$cO<G?4sb:_^a?it[ao.UOTl^St[rm=968jW74@xk<f(1d<D/IJp~NFbac8a+-g2*uvr,J&#lEo6A#Mfa:tO_+;JXT0r+GxKZIMNZ`qaxkIn}(rt~;Uz0cxGEH.p-M}ao^l|A=M:@)i#?Yw&.77z=.L}!-|d/F$r({+0Sa|wzaQ9,% !+a|_~StKMgWO{5(g~*:Mm+<8N9&N$hbv4a|d9r5g{0-/OoiTkqJNs+Tit^O#QNI_~S%X7(P|:TUU#R@EWd2G$4Us,%yLt~$Kf?s_5sK+BfgvJ|y}7Bw8o,co^!(i@DClB}n:vdF2Rut}`FGEj![ XnQq.0:wVkmAgI{!hQ1*It|2uTkZ>I E;dCO,$<}Xw$g1pKL=9qd-nw8tZp!gi[&k/y0twSl_&$Z?1z}[fG@oLf~j_rU]SsC_0rkO->6Sh+:J=X[+Xwnp(Jh(Ki (,pJF#pn ,TAA3t+8B-95C0`G&GE>,_kLtuWR/tO*=Q;p8TD3Y!3*PMM)x<rl`6K7vgGL/(dGw??:q=)i !2<I>_E@Q.(>d+tQUHB|$iV&OR`9R`JTPz|@<KND{:VX$HF,6t{n{aV&Xe7B!pz5-M^*#<75mN/mtrj.^Q4]]%DLO1m#g?3%R!-[=] diN^{-.%n-W0`xdce&6xh=IyCLLj/u?diTAXtX&91=:^aqjiF!VdL9/_NpCWG!0(mw`<Ihd`o^k]#7.5sb$C> :P{6',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("set key: {$key}");

        $headers = [
            "Accept"       => "*/*",
            "Origin"       => "https://www.{$this->domain}.com",
            "content-type" => "text/plain;charset=UTF-8",
        ];
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $headers);
        $this->http->JsonLog();
        sleep(1);

        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $secondSensorData[$key]]), $headers);
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function parseItinerary($alterNumber = null): void
    {
        $this->logger->notice(__METHOD__);
        $confNo = $this->http->FindSingleNode("//strong[contains(@class, 'reservation-number')]");

        $travellers = $accounts = [];
        $travellerNodes = $this->http->XPath->query('//div[contains(@class, "last-name")]/ancestor::div[contains(@class, "row")]');
        $this->logger->debug("Total {$travellerNodes->length} travelers were found");

        if (!$confNo && $travellerNodes->length == 0) {
            $this->logger->error('Something went wrong with that action.');

            return;
        }

        if (!$this->http->FindSingleNode('//h2[contains(@class, "subtitle-section")]')
            && !$this->http->FindSingleNode('//h3[contains(text(), "Hotel information")]')
            && !$confNo && $travellerNodes->length > 0) {
            $this->logger->error('There is no information about the hotel, there are such reservations.');

            return;
        }

        $h = $this->itinerariesMaster->add()->hotel();

        foreach ($travellerNodes as $node) {
            $title = $this->http->FindSingleNode('.//div[contains(@class, "title")]', $node);
            $firstName = $this->http->FindSingleNode('.//div[contains(@class, "first-name")]', $node);
            $lastName = $this->http->FindSingleNode('.//div[contains(@class, "last-name")]', $node);
            $travellers[] = trim(beautifulName("{$firstName} {$lastName}"));
            $accounts[] = $this->http->FindSingleNode('.//div[contains(@class, "number")]', $node);
        }

        if ($confNo) {
            $h->general()
                ->confirmation($confNo, "Reservation number", true);
        } else {
            $h->general()->noConfirmation();
        }

        $h->general()
            ->cancellation(implode('; ',
                $this->http->FindNodes('//span[contains(text(), "Cancellation policy")]/following-sibling::ul/li')),
                true)
            ->travellers(array_unique(array_filter($travellers)), true);

        if (isset($alterNumber)) {
            $h->general()->confirmation($alterNumber, "Alternative Reservation number");
        }

        $h->program()->accounts(array_unique(array_filter($accounts)), false);

        $deadline =
            $this->http->FindPreg("/(?:Cancel by|prior to) ([^,]+,? \w{3} \d+ \d{4}) = no penalty/", false,
                $h->getCancellation())
            ?? $this->http->FindPreg("/Cancel today thru (\w{3} \d+ \d{4}) = no penalty/", false,
                $h->getCancellation());

        if ($deadline) {
            $this->logger->debug($deadline);
            $deadline = str_replace(['hotel time, ', 'hotel time on'], '', $deadline);
            $deadline = str_replace('0:00 AM ', '0:00 ', $deadline);
            $deadline = str_replace(',', '', $deadline);
            $this->logger->debug($deadline);
            $h->booked()->deadline2($deadline);
        } elseif ($this->http->FindPreg("/reservation is non refundable/ims", false, $h->getCancellation())) {
            $h->booked()->nonRefundable();
        }

        $occupancy = $this->http->FindSingleNode('(//*[self::strong or self::div][contains(@class, "occupancy")])[1]');
        $checkIn = $this->http->FindSingleNode('(//div[contains(@class, "check-in")])[1]');

        if (!$checkIn) {
            $checkIn = $this->http->FindSingleNode('(//strong[contains(@class, "check-in font-medium")])[1]');
        }

        $checkOut = $this->http->FindSingleNode('(//div[contains(@class, "check-out")])[1]');

        if (!$checkOut) {
            $checkOut = $this->http->FindSingleNode('(//strong[contains(@class, "check-out font-medium")])[1]');
        }

        $h->booked()
            ->checkIn2(str_replace(' - ', '', $checkIn))
            ->checkOut2(str_replace(' - ', '', $checkOut))
            ->guests($this->http->FindPreg('/(\d+)\s+adult/im', false, $occupancy))
            ->kids($this->http->FindPreg('/(\d+)\s+childr/im', false, $occupancy) ?? null, false, true)
            ->rooms(intval($this->http->FindPreg('/(\d+)\s+room/im', false, $occupancy)));

        $phone = $this->http->FindSingleNode('//span[contains(@class, "phone")]', null, false);

        if (!empty($phone)) {
            //hardcode bad symbol
            $phone = str_ireplace(['&zwnj;', '&8203;', '​'], '', $phone); // Zero-width
        }
        $h->hotel()
            ->name($this->http->FindSingleNode('//h2[contains(@class, "subtitle-section")]'))
            ->phone($phone, false, true);

        if ($address = $this->http->FindSingleNode('//a[span[@data-test="address"]]')) {
            $h->hotel()->address($address);
        } else {
            $h->hotel()->noAddress();
        }

        $rooms = $this->http->XPath->query('//section[@class = "entity-confirmation-rooms"]');
        $this->logger->debug("Total {$rooms->length} rooms were found");

        foreach ($rooms as $room) {
            $h->addRoom()
                ->setType($this->http->FindSingleNode('.//strong', $room), true, true)
                ->setRateType($this->http->FindSingleNode(".//div[contains(@class, 'rate-details')]/text()[1]", $room))
                ->setRate($this->http->FindSingleNode('//h3[contains(text(), "verage nightly rate*")]', $room, true,
                    "/rate\*\s*(.+)/"), false, true)
                ->setDescription($this->http->FindSingleNode('.//strong/following-sibling::div', $room, true,
                    "/^\s*\-?\s*(.+)/"), true, true);
        }

        $totalInfo = $this->http->FindSingleNode('//span[contains(@class, "total__cash")]');
        $h->price()
            ->cost(PriceHelper::cost($this->http->FindSingleNode('//td[contains(text(), "Subtotal")]/following-sibling::td',
                null, true, '/[A-Z]{3}\s*([\-\d.,]+)/')), false, true)
            ->total(PriceHelper::cost($this->http->FindPreg('/[A-Z]{3}\s*([\-\d.,]+)/', false, $totalInfo)), false,
                true)
            ->currency($this->http->FindPreg('/([A-Z]{3})\s*[\-\d.,]+/', false, $totalInfo), false, true);
        $tax = PriceHelper::cost($this->http->FindSingleNode('//td[contains(text(), "Estimated taxes")]/following-sibling::td',
            null, true, '/[A-Z]{3}\s*([\d.,]+)/'));

        if ($tax) {
            $h->price()->tax($tax);
        }
        $fee = PriceHelper::cost($this->http->FindSingleNode('//td[contains(text(), "Estimated additional fees")]/following-sibling::td',
            null, true, '/[A-Z]{3}\s*([\-\d.,]+)/'));

        if ($fee) {
            $h->price()->fee("Estimated additional fees", $fee);
        }

        $spentAwards = $this->http->FindNodes('//td[contains(text(), "Price per room")]/following-sibling::td[contains(text(), "points")]',
            null, "/(.+)\s+point/");

        if ($spentAwards) {
            $h->price()->spentAwards(array_sum($spentAwards) . " points");
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryBasic($root = null, $cancelled = false, $parseAddress = false): void
    {
        $this->logger->notice(__METHOD__);

        $confNo = $this->http->FindSingleNode(".//p[contains(@class, 'reservation-number')][1]", $root, true,
            "/number\s*([^<]+)/");
        $cancelledString = $cancelled ? '(cancelled)' : '';
        $this->logger->info("Parse Itinerary #{$confNo} {$cancelledString}", ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();

        $h->general()->confirmation($confNo, "Reservation number");

        if ($cancelled === true) {
            $h->general()->cancelled();
        }

        $occupancy = $this->http->FindSingleNode('.//span[contains(@class, "occupancy") and contains(@class, "text-bold")]',
            $root);
        $h->booked()
            ->checkIn2($this->http->FindSingleNode('.//span[contains(text(), "Check-in")]/following-sibling::span[1]',
                    $root) . " " . $this->http->FindSingleNode('.//span[contains(text(), "Check-in")]/following-sibling::span[2]',
                    $root))
            ->checkOut2($this->http->FindSingleNode('.//span[contains(text(), "Check-out")]/following-sibling::span[1]',
                    $root) . " " . $this->http->FindSingleNode('.//span[contains(text(), "Check-out")]/following-sibling::span[2]',
                    $root))
            ->guests($this->http->FindPreg('/(\d+)\s+adult/im', false, $occupancy))
            ->kids($this->http->FindPreg('/(\d+)\s+childr/im', false, $occupancy) ?? null, false, true)
            ->rooms(intval($this->http->FindPreg('/(\d+)\s+room/im', false, $occupancy)));

        $h->hotel()
            ->name($this->http->FindSingleNode('.//a[@class = "color-gunmetal"]', $root))
            ->noAddress();

        if ($parseAddress === true && ($hotelInfoLink = $this->http->FindSingleNode('.//a[@class = "color-gunmetal"]/@href',
                $root))) {
            $this->http->NormalizeURL($hotelInfoLink);
            $this->http->GetURL($hotelInfoLink);

            $hotelName = $this->http->FindSingleNode('//section//h2[@itemprop = "name"]//h2[@itemprop = "name"]');

            if (!$hotelName) {
                $hotelName = $this->http->FindSingleNode('//h2[@itemprop = "name"]//h2[@itemprop = "name"]');
                $phone = $this->http->FindSingleNode('//span[contains(@class, "t-phone")]');
                $address = $this->http->FindSingleNode('//a[@class = "t-full-address"]');
            } else {
                $phone = $this->http->FindSingleNode('//section//span[contains(@class, "t-phone")]');
                $address = $this->http->FindSingleNode('//section//a[@class = "t-full-address"]');
            }

            $h->hotel()
                ->name($hotelName)
                ->phone($phone)
                ->address($address);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

//        $cacheKey = 'carlson_abck';
//        $result = Cache::getInstance()->get($cacheKey);
//
//        if (!empty($result) && $this->attempt < 2) {
//            $this->logger->debug("set _abck from cache: {$result}");
//
//            $this->http->setCookie("_abck", $result);
//
//            return null;
//        }

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

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
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            if ($this->attempt == 1) {
                $selenium->useFirefox();

                $request = FingerprintRequest::firefox();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            } else {
//                $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            }

//            $selenium->http->SetProxy($this->proxyWhite());
//            $selenium->http->setUserAgent(\HttpBrowser::PUBLIC_USER_AGENT);
//            $selenium->disableImages();
//            $selenium->http->removeCookies();
            //$selenium->keepCookies(false);
            //$selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.{$this->domain}.com/en-us/radisson-rewards/login");

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "privacy_pref_optin"]'), 3);

            if ($btn) {
                $btn->click();
            }

            $login = $selenium->waitForElement(WebDriverBy::id('email-rewards'), 5);
            $pass = $selenium->waitForElement(WebDriverBy::id('password-rewards'), 2); // 0 is not always enough
//            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "btn-primary")]'), 0);

            $this->savePageToLogs($selenium);

            if (!$login || !$pass/* || !$btn*/) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("enter Login");
//            $login->click();
//            $login->clear();
//            $login->sendKeys($this->AccountFields['Login']);
//            $pass->clear();
//            $pass->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript("document.querySelector('.entity-hero').scrollIntoView({block: \"end\"});");
            $selenium->driver->executeScript("document.querySelector('#email-rewards').value = '{$this->AccountFields['Login']}';");
            $this->logger->debug("enter Password");
            $selenium->driver->executeScript("document.querySelector('#password-rewards').value = '" . str_replace([
                "\\",
                "'",
            ], ["\\\\", "\'"], $this->AccountFields['Pass']) . "';");

            if ($this->rememberMe) {
                $selenium->driver->executeScript('let rememberMe = document.querySelector(\'#remember-me-rewards\'); if (rememberMe) rememberMe.checked = true;');
            }
            $this->savePageToLogs($selenium);
            $this->logger->debug("click");
            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript('document.querySelector(\'#main button.text-uppercase\').click();');
//            $btn->click();

            if ($selenium->waitForElement(WebDriverBy::xpath('
             //div[contains(text(), "There is an error. Please try later.")]
            '), 7)) {
                $btn = $selenium->waitForElement(WebDriverBy::xpath("//*[normalize-space(text())='Close']"), 0);

                if ($btn) {
                    $btn->click();
                }
                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript('document.querySelector(\'#main button.text-uppercase\').click();');
            }
            $this->savePageToLogs($selenium);

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //ul[@role = "alert"]
                | //div[contains(text(), "There is an error. Please try later.")]
                | //strong[contains(text(), "The corporate/travel agency ID code is invalid.")]
                | //span[contains(@class, "member-number")]
            '), 15);

            // AccountID: 6739296
            if ($res && $res->getText() === 'INVALID FORMAT') {
                throw new CheckException('The email address/Radisson Rewards number or the password is not correct. Please try again or click ‘Forgot password’ to reset it.', ACCOUNT_INVALID_PASSWORD);
            }

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
//                if ($cookie['name'] == '_abck') {
//                    $result = $cookie['value'];
//                    $this->logger->debug("set new _abck: {$result}");
//                    Cache::getInstance()->set($cacheKey, $result, 60 * 60 * 20);
//
//                    $this->http->setCookie("_abck", $result);
//                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(2, 0);
            }
        }

        return null;
    }

    private function getCookiesConfNoFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

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
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            if ($this->attempt == 1) {
                $selenium->useFirefox();

                $request = FingerprintRequest::firefox();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            } else {
//                $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            }

//            $selenium->http->SetProxy($this->proxyWhite());
//            $selenium->http->setUserAgent(\HttpBrowser::PUBLIC_USER_AGENT);
//            $selenium->disableImages();
//            $selenium->http->removeCookies();
            //$selenium->keepCookies(false);
            //$selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://www.{$this->domain}.com/en-us/");
            sleep(5);
            $selenium->http->GetURL("https://www.{$this->domain}.com/en-us/reservation/search");

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "privacy_pref_optin"]'), 3);

            if ($btn) {
                $btn->click();
            }

            $firstName = $selenium->waitForElement(WebDriverBy::id('first-name'), 5);
//            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "btn-primary")]'), 0);

            $this->savePageToLogs($selenium);

            if (!$firstName) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(2, 0);
            }
        }

        return null;
    }
}
