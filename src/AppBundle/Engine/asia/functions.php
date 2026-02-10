<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAsia extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected const ABCK_CACHE_KEY = 'asia_abck';
    protected const BMSZ_CACHE_KEY = 'asia_bmsz';

    private $needToUpdatePassword = false;
    private $cookiesRefreched = false;
    private $accessDenied = false;

    private $currentItin = 0;

    // [19 Oct 2014]: checking via amazon is not working
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
        $this->http->disableOriginHeader();

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 2) {
            $this->http->setRandomUserAgent(10);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        }

        $this->http->setUserAgent($this->State[$userAgentKey]);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.cathaypacific.com/cx/en_GB.html");
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // refs #4525
        $this->http->FilterHTML = false;
        // $this->http->GetURL("https://www.cathaypacific.com/cx/en_US/frequent-flyers/my-account/account-balance.html");
        $loginURL = "https://www.cathaypacific.com/cx/en_US/sign-in.html?switch=Y";

        $this->http->GetURL($loginURL);

        if ($this->http->Response['code'] == 403) {
            $userAgentKey = "User-Agent";
            $this->http->setRandomUserAgent(15);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }

            $this->http->GetURL($loginURL);

            if ($this->http->Response['code'] == 403) {
                $this->http->SetProxy($this->proxyReCaptcha());
                $this->http->setRandomUserAgent(10);
                $agent = $this->http->getDefaultHeader("User-Agent");

                if (!empty($agent)) {
                    $this->State[$userAgentKey] = $agent;
                }
                $this->http->GetURL($loginURL);

                if ($this->http->Response['code'] == 403) {
                    $this->sendNotification("403, debug // RR");
                }
            }
        }// if ($this->http->Response['code'] == 403)

        $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
        $bmsz = Cache::getInstance()->get(self::BMSZ_CACHE_KEY);
        $this->logger->debug("_abck from cache: {$abck}");
        $this->logger->debug("bm_sz from cache: {$bmsz}");

        if (!$abck || !$bmsz || $this->attempt > 0) {
            $this->getSensorDataFromSelenium();

            if (!$this->accessDenied) {
                return true;
            }

            // 50% help
            $this->http->removeCookies();
            $this->http->GetURL($loginURL);

            if ($this->http->Response['code'] == 403) {
                throw new CheckRetryNeededException(3, 3);
            }
            $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
            $bmsz = Cache::getInstance()->get(self::BMSZ_CACHE_KEY);
        }

        $this->http->setCookie('_abck', $abck, ".cathaypacific.com");
        $this->http->setCookie('bm_sz', $bmsz, ".cathaypacific.com");

//        if (!$this->http->ParseForm(null, "//div[contains(@class, 'signIn')]//form")) {
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->http->FilterHTML = true;
        $data = [
            "response_type" => "code",
            "scope"         => "openid",
            "client_id"     => "20b10efd-dace-4053-95ac-0fcc39d43d84",
            "redirect_uri"  => "https://api.cathaypacific.com/mpo-login/v2/login/createSessionAndRedirect",
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "login_url"     => "https://www.cathaypacific.com/cx/en_GB/login.html?source=masterLoginbox",
            "target_url"    => "https://www.cathaypacific.com:443/cx/en_GB.html",
            "login_type"    => filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false ? "memberIdOrUsername" : "email",
        ];
        $this->http->PostURL("https://api.cathaypacific.com/mlc2/authorize", $data);

        if ($this->http->currentUrl() === 'https://www.cathaypacific.com/cx/en_GB/sign-in.html?source=masterLoginbox&error=2009') {
            throw new CheckException('Your member account is temporarily locked after too many unsuccessful login attempts. You can reset your password by confirming your personal information or contact our Customer Care for assistance. [Error code: 2009]', ACCOUNT_LOCKOUT);
        }

        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            if ($this->accessDenied) {
                // don't try selenium again
                throw new CheckRetryNeededException(3, 3);
            }
            // need refresh SensorData
            $this->getSensorDataFromSelenium();
        }// if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]'))

        return true;
    }

    public function Login()
    {
        if ($this->http->currentUrl() == 'https://www.cathaypacific.com/cx/en_HK/membership/elevate-your-cathay-experience.html') {
            $memberId = $this->AccountFields['Login'];

            if (!is_numeric($memberId)) {
                $this->http->GetURL("https://api.cathaypacific.com/mpo-services/v3/transit/profile");
                $response = $this->http->JsonLog();
                $memberId = $response->memberId ?? null;
            }

            $headers = [
                'Accept'           => 'application/json, text/javascript, */*',
                'Content-Type'     => 'application/json; charset=UTF-8',
                'X-Requested-With' => 'XMLHttpRequest',
            ];
            $this->http->PostURL("https://api.cathaypacific.com/mpo-services/v3/transit/skip", '{"memberId":"' . $memberId . '"}', $headers);
            $response = $this->http->JsonLog();

            $this->http->GetURL("https://api.cathaypacific.com/mlc2/profile-transit/authorize?client_id={$response->clientId}&original_client_id={$response->originClientId}");
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // https://www.cathaypacific.com/cx/en_HK/sign-in.html?loginType=1&error=2020
        parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
        $message = $output['error'] ?? null;

        if ($message) {
            switch ($message) {
                case 2001:
                    throw new CheckException("Inactive Membership number / Username. Please contact our Global Contact Centres for assistance.", ACCOUNT_INVALID_PASSWORD);

                case 2004:
                    throw new CheckException("Your sign-in details are incorrect. Please check your details and try again.", ACCOUNT_INVALID_PASSWORD);

                case 2017:
                    throw new CheckException("Please note that Registered account holders cannot sign in on or after 27 April 2022. Sign up to Asia Miles today and turn your everyday activities into exciting awards and experiences.", ACCOUNT_INVALID_PASSWORD);

                case 2009:
                    throw new CheckException("Your member account is temporarily locked after too many unsuccessful login attempts. You can reset your password by confirming your personal information or contact our service centre for assistance.", ACCOUNT_LOCKOUT);

                case 2020:
                    throw new CheckException("The email address you have entered is incorrect. Please check the email address and try again.", ACCOUNT_INVALID_PASSWORD);

                case 5002:
                    throw new CheckException("Email sign-in will be available after 17 Aug 2022 for members who have verified their email. Please sign in using your membership number or username.", ACCOUNT_INVALID_PASSWORD);

                default:
                    $this->DebugInfo = $message;
            }

            return false;
        }

        if ($message = $this->http->FindSingleNode("//label[contains(@class, 'textfield__errorMessage')]")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Input contains invalid characters.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Inactive Membership number.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            // false/positive
            if (strstr($this->DebugInfo, 'Please enter a valid membership number.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (
            $this->http->currentUrl() == 'https://www.cathaypacific.com/cx/en_GB/membership/password-setup/security-reset.html'
            || $this->http->currentUrl() == 'https://www.cathaypacific.com/cx/en_HK/membership/password-setup/security-reset.html'
        ) {
            throw new CheckException('To strengthen security and better protect your account, we now require alphanumeric passwords. Please update your password, as your PIN can no longer be used.', ACCOUNT_INVALID_PASSWORD);
        }

        // Consent for Cathay Pacific
        if (
            (
                strstr($this->http->currentUrl(), 'https://www.cathaypacific.com/cx/en_HK/membership/special-consent/')
                && strstr($this->http->currentUrl(), '-consent.html?consent=true&minor=')
            )
            || $this->http->FindSingleNode('//p[contains(text(), "Your privacy is important. We have set a policy that covers how we collect, use, and transfer your personal information.") or contains(text(), "Your privacy is important to us. We have set a policy that covers how we collect, use, and transfer your personal information.")]')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            throw new CheckRetryNeededException(2, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $status = '';

        if (isset($response->currentTierCode)) {
            switch ($response->currentTierCode) {
                case 'AM':
                    $status = 'AM';

                    break;

                case 'GR':
                    $status = 'Green';

                    break;

                case 'SL':
                    $status = 'Silver';

                    break;

                case 'GO':
                    $status = 'Gold';

                    break;

                case 'DM':
                    $status = 'Diamond';

                    break;

                default:
                    $this->sendNotification("asia: Unknown status: {$status}");
            }
        }// switch ($response->currentTierCode)
        // Name
        $this->SetProperty("Name", beautifulName($response->embossedName ?? null));

        // refs #12497
        // Balance - Asia Miles
        $this->SetBalance($response->asiaMilesAvailableForRedemption ?? null);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->needToUpdatePassword) {//todo
                throw new CheckException("Cathay Pacific (Asia Miles) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Membership No
        $this->SetProperty('MembershipNumber', $response->membershipNumber ?? null);

        // SubAccount - Marco Polo Club

        if (isset($response->currentClubMiles) && $status != 'AM') {
            // Tier
            $this->SetProperty("Tier", $status);
            // for Elite Level tab  // refs #16658
            $this->SetProperty("ClubPoints", $response->currentClubPoints);

            $this->AddSubAccount([
                'Code'             => 'CathayPacificClubMiles',
                'DisplayName'      => "Status Points",
                'Balance'          => $response->currentClubPoints,
                'TotalClubMiles'   => $response->lifeStatusMiles,
                'TotalClubSectors' => $response->lifeStatusSectors,
                'MemberSince'      => $response->joiningDate,
            ]);
        }// if (isset($clubMiles))

        // Expiration    // refs #18711
        $this->logger->info('Expiration date', ['Header' => 3]);

        if (isset($response->asiaMilesToExpire)) {
            $this->logger->debug("Total nodes found: " . count($response->asiaMilesToExpire));

            for ($i = 0; $i < count($response->asiaMilesToExpire); $i++) {
                $milesToExpire = $response->asiaMilesToExpire[$i]->asiaMiles;
                $exp = $response->asiaMilesToExpire[$i]->expiryDate;

                if ($milesToExpire > 0 && strtotime($exp)) {
                    $this->logger->notice("set exp date by OLD rules");
                    // Expiration Date
                    $this->SetExpirationDate(strtotime($exp));
                    // Miles to Expire
                    $this->SetProperty("MilesToExpire", number_format($milesToExpire));
                    unset($this->Properties['AccountExpirationWarning']);

                    break;
                }// if ($milesToExpire > 0)
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if (isset($response->asiaMilesToExpire))

        $this->http->GetURL("https://api.cathaypacific.com/mpo-profile/v1/member/{$this->Properties['MembershipNumber']}/profile?fields=generalProfile,awardMiles,contactInfo,preference");
        $response = $this->http->JsonLog();
        $milesToExpire = $response->awardMiles->activityBucketInfo->expiryMiles ?? null;
        $exp = $response->awardMiles->activityBucketInfo->expiryDate ?? null;
        $this->logger->debug("[expiryMiles]: {$milesToExpire}");
        $this->logger->debug("[expiryDate]: {$exp}");
        $exp = preg_replace("/(\d{4})(\d{2})(\d{2})/", "$2/$3/$1", $exp);
        $this->logger->debug("[expiryDate]: {$exp}");

        // AccountID: 6722297
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetBalance($response->awardMiles->asiaMilesAvailableForRedemption ?? null);
        }

        if (
            $milesToExpire
            && $exp
            && $milesToExpire > 0
            && (
                !isset($this->Properties['AccountExpirationDate'])
                || (isset($this->Properties['AccountExpirationDate']) && $this->Properties['AccountExpirationDate'] > strtotime($exp))
                || (isset($this->Properties['AccountExpirationDate'], $this->Properties['AccountExpirationWarning']))
            )
        ) {
            $this->logger->notice("set exp date by NEW rules");
            // Expiration Date
            $this->SetExpirationDate(strtotime($exp));
            // Miles to Expire
            $this->SetProperty("MilesToExpire", number_format($milesToExpire));
            unset($this->Properties['AccountExpirationWarning']);
        }
    }

    public function ParseItineraries(): array
    {
        $this->http->RetryCount = 0;
        $headers = [
            'Accept-Encoding'  => 'gzip, deflate, br',
            'Accept'           => 'application/json, text/plain, */*',
            'Content-Type'     => 'application/json',
            'Cache-Control'    => 'no-cache',
            'Pragma'           => 'no-cache',
            'Language'         => 'en',
            'Accept-Language'  => 'en-HK',
            'Origin'           => 'https://www.cathaypacific.com',
            'Referer'          => 'https://www.cathaypacific.com/',
            'Connection'       => null,
        ];
        $headers2 = [
            'Accept-Encoding'  => 'gzip, deflate, br',
            'Referer'          => 'https://www.cathaypacific.com/',
            'Connection'       => null,
        ];
        $this->http->GetURL('https://flights.cathaypacific.com/en_HK.html');
        sleep(2);
        $this->http->GetURL('https://www.cathaypacific.com/mb/#!/en_HK/login');
        sleep(2);
        $this->http->GetURL('https://api.cathaypacific.com/mb-api/mblogin/v1/user?languageLocale=en_HK&currentPage=SUMMARY_PAGE', $headers);
        sleep(2);
        $this->http->GetURL('https://api.cathaypacific.com/mlc2/sessionValidate?response_type=code&scope=openid%20offline_access&client_id=4a8151c4-5c76-4de7-b28d-61071b09d181&redirect_uri=https%3A%2F%2Fapi.cathaypacific.com%2Fmb-api%2Fmblogin%2Fv2%2Ftoken&target_url=https%3A%2F%2Fwww.cathaypacific.com%2Fmb%2F%23!%2Fen_HK%2Fhub%3F&login_url=https%3A%2F%2Fwww.cathaypacific.com%2Fmb%2F%23!%2Fen_HK%2Flogin%3Fstatus%3D1110%26', $headers2);

        $this->http->GetURL('https://api.cathaypacific.com/mb-api/mbpnr/v1/retrieveRlocs?tab=all&page=1&isFromOLCILoginPage=false', $headers);

        if ($this->http->Response['code'] == 504 /*|| $this->http->FindPreg('/\{"errorCode":"E1000000","type"/')*/) {
            sleep(2);
            $this->http->GetURL('https://api.cathaypacific.com/mb-api/mbpnr/v1/retrieveRlocs?tab=all&page=1&isFromOLCILoginPage=false', $headers);
            $bookingData = $this->http->JsonLog(null, 0, true);

            if ($bookingData['rlocs'] ?? null) {
                $this->sendNotification('success retry // MI');
            }
        }
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/\{\"rlocs\":\[\],\"allBookingCount\":0,\"todoBookingCount\":0,\"OLCIBookingCount\":0,\"ibeReturnError\":\{\},\"retrieveOneASuccess\":true,\"retrieveEODSSuccess\":true,\"retrieveOJSuccess\":(?:true|false)\}/")
        || $this->http->FindPreg('/{"rlocs":\[\],"refundBookings":\[\],"allBookingCount":0,"todoBookingCount":0,"OLCIBookingCount":0,"ibeReturnError":\{\},"retrieveOneASuccess":true,"retrieveEODSSuccess":true,"retrieveOJSuccess":(?:true|false)}/')
            || $this->http->FindPreg('/"rlocs":\[\],"refundBookings":\[\],"allBookingCount":0,"todoBookingCount":0,"OLCIBookingCount":0,"cathayCreditBookingCount":0,"ibeReturnError":\{\},/')) {
            // "No upcoming booking" (on site when...)
            $seemsNoIts = true;
        }
        $bookingData = $this->http->JsonLog(null, 3, true);

        $rlocs = $bookingData['rlocs'] ?? null;

        if ($rlocs === null) {
            return [];
        }

        foreach ($rlocs as $rloc) {
            $this->http->GetURL("https://api.cathaypacific.com/mb-api/mbpnr/v2/retrieve?oneARloc={$rloc['rloc']}&loginFFPMatched=true");
            $this->increaseTimeLimit();
            $itinData = $this->http->JsonLog(null, 3, true);
            $error = $this->parseItineraryType($itinData);

            if ($error) {
                $this->logger->error("Skipping: {$error}");
            }
        }

        if (empty($rlocs) && isset($seemsNoIts) && count($this->itinerariesMaster->getItineraries()) === 0) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking Reference #",
                "Type"     => "string",
                "Size"     => 6,
                "Required" => true,
            ],
            "givenName" => [
                "Caption"  => "Given Name",
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "familyName" => [
                "Caption"  => "Family Name",
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.cathaypacific.com/mb/#/en_HK/login";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->ParseForms = false;

        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];
        $this->http->GetURL("https://api.cathaypacific.com/acms/v1/i18n?dict=responsive_mmb_error_serverside&lang=en_HK&sling=true",
            $headers);
        $answersJson = $this->http->JsonLog(null, 1, true);

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://www.cathaypacific.com',
            'Referer'      => 'https://www.cathaypacific.com/mb/',
        ];
        $this->http->GetURL("https://api.cathaypacific.com/mb-api/mblogin/v1/nonmember?familyName={$arFields["familyName"]}&givenName={$arFields["givenName"]}&rloc={$arFields["ConfNo"]}&eticket=&loginPageType=MMB",
            $headers);
        $response = $this->http->JsonLog(null, 3, true);
        $this->http->RetryCount = 2;

        if (isset($response['success']) && $response['success'] === false
            && isset($response['errors']) && !empty($response['errors'] && isset($response['errors'][0]['errorCode']))
            && isset($answersJson["responsive_mmb_error_serverside_{$response['errors'][0]['errorCode']}"])
            && isset($answersJson["responsive_mmb_error_serverside_{$response['errors'][0]['errorCode']}"]['sling:message'])
        ) {
            return $answersJson["responsive_mmb_error_serverside_{$response['errors'][0]['errorCode']}"]['sling:message'];
        }

        if (isset($response['success']) && $response['success'] === false
            && isset($response['errors']) && !empty($response['errors'] && isset($response['errors'][0]['errorCode']))
            && $response['errors'][0]['errorCode'] == 'E23Z00501' // not found in list i18n
        ) {
            return 'Sorry, but we are unable to complete your request. Please try again.';
        }
        $bookingInfo = $response['bookingInfo'] ?? null;

        if ($bookingInfo) {
            $error = $this->parseJsonFlight2($bookingInfo);

            if ($error) {
                return $error;
            }

            return null;
        }

        return $this->CheckConfirmationNumberInternalOld($arFields, $it);
    }

    public function GetHistoryColumns()
    {
        return [
            "Crediting Date" => "PostingDate",
            "Activity Date"  => "Info.Date",
            "Activity"       => "Description",
            "Asia Miles"     => "Miles",
            "Bonus"          => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
//        $this->http->GetURL("https://www.cathaypacific.com/cx/en_HK/frequent-flyers/my-account/latest-transactions.html");
        $page = 0;
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
            "Referer"      => "https://www.cathaypacific.com/",
            "Origin"       => "https://www.cathaypacific.com",
        ];
        $startDate = date("Y-m", strtotime("-2 months")) . "-01"; //"2022-06-01",
        $endDate = date("Y-m-d", strtotime('last day of this month')); //,"2022-08-31",

        do {
            $this->logger->info('Page #' . $page, ['Header' => 3]);

            if ($page > 0) {
                $startDate = date("Y-m", strtotime("-3 month", strtotime($startDate))) . "-01";
                $endDate = date("Y-m-d", strtotime('last day of this month', strtotime("+2 month", strtotime($startDate))));
            }

            $data = [
                "startDate" => $startDate,
                "endDate"   => $endDate,
            ];
            $this->http->PostURL("https://api.cathaypacific.com/mpo-web/account/memberTransactionSearch", json_encode($data), $headers);
            $response = $this->http->JsonLog(null, 1, true);
            $statementLists = ArrayVal($response, 'unstatementedTransactions', []);
            $this->logger->debug("Total " . count($statementLists) . " dates were found");
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

            $page++;
        } while (
            $page < 12
        );

        // Sort by date
        usort($result, function ($a, $b) {
            $key = 'Crediting Date';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    private function parseItineraryType($data): ?string
    {
        $error = null;
        $bookingType = $data['bookingType'] ?? null;
        $errorCode = $data['errors'][0]['errorCode'] ?? null;

        if (in_array($errorCode, ['E23Z00113', 'E23Z00501', 'E1000001'])) {
            $error = "There is no active booking under the information you provided. Please check and re-enter. [$errorCode]";

            return $error;
        }

        if ($data === []) {
            $error = 'empty booking data';

            return $error;
        }

        if ($bookingType == 'Flight') {
            $this->parseJsonFlight2($data);
        } elseif ($bookingType == 'Hotel') {
            $this->sendNotification("check new bookingType {$bookingType} // MI", "awardwallet");
            $data = $this->http->JsonLog(null, 0);
            $this->parseJsonHotel($data);
        } else {
            $this->sendNotification("check new bookingType {$bookingType} // MI", "awardwallet");
        }

        return $error;
    }

    private function parseJsonHotel($data): ?string
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();

        if (count($data->details) > 1) {
            $this->sendNotification("it details > 1 // MI");
        }

        $details = $data->details[0] ?? [];
        $this->logger->info("[{$this->currentItin}] Parse Hotel #{$details->bookingReference}", ['Header' => 3]);
        $this->currentItin++;
        $h->general()->confirmation($details->bookingReference);
        $h->general()->date2($details->checkInDate);
        $h->general()->status(beautifulName($details->bookingStatus));
        $h->hotel()
            ->name($details->name)
            ->address($details->adress->compressed)
        ;

        $h->booked()
            ->checkIn2("$details->checkInDate, $details->checkInTime")
            ->checkOut2("$details->checkOutDate, $details->checkOutTime")
        ;

        $travellers = [];

        foreach ($details->rooms as $room) {
            $h->booked()->guests($room->adults);
            $h->booked()->kids($room->children);

            foreach ($room->roomOptions as $roomOption) {
                $r = $h->addRoom();
                $r->setType($roomOption->name);
                $r->setDescription($roomOption->description);

                foreach ($roomOption->guestDetails as $guestDetail) {
                    $travellers[] = "{$guestDetail->middleName} {$guestDetail->givenName} {$guestDetail->surname}";
                }
            }
        }
        $h->general()->travellers(array_unique($travellers));

        $h->price()
            ->total($details->amount)
            ->currency($details->currencyCode)
            ->spentAwards($details->pointsRedeemed)
        ;
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

        return "";
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $token = $this->http->getCookieByName('mlc_prelogin');

        if (!$token) {
            return false;
        }// if (!$token)

        $this->http->GetURL("https://api.cathaypacific.com/mpo-web/account/memberAccountDetails");
        $response = $this->http->JsonLog();

        if (isset($response->errors[0]->code) && $response->errors[0]->code == 'ERR_COMM_003') {
            return false;
        }// if (isset($response->errors[0]->code) && $response->errors[0]->code == 'ERR_COMM_003')

        if (isset($response->membershipNumber)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function getAirLines($headers)
    {
        $this->logger->notice(__METHOD__);
        $airlines = Cache::getInstance()->get('asia_airlines');

        if (!$airlines) {
            $this->http->GetURL('https://www.cathaypacific.com/content/cx/.airline.en_GB.IATA.json', $headers);
            $airlines = json_decode($this->http->Response["body"], true);

            if (empty($airlines['error']) && isset($airlines['airlines'])) {
                $newAirlines = [];

                foreach ($airlines['airlines'] as $airline) {
                    if (isset($airline['code']) && isset($airline['name'])) {
                        $newAirlines[$airline['code']] = $airline['name'];
                    }
                }

                if (count($newAirlines) > 0) {
                    $airlines = $newAirlines;
                    Cache::getInstance()->set('asia_airlines', $airlines, 3600 * 24);
                }
            }
        }

        return $airlines;
    }

    private function parseJsonFlight2($data): ?string
    {
        $this->logger->notice(__METHOD__);

        if (isset($data['segments']) && empty($data['segments'])) {
            $this->logger->error('Skip: Empty segment');

            return null;
        }

        $r = $this->itinerariesMaster->add()->flight();
        $this->logger->info("[{$this->currentItin}] Parse Flight #{$data['rloc']}", ['Header' => 3]);
        $this->currentItin++;
        $r->general()
            ->confirmation($data['rloc'], 'Booking reference');
        $accounts = [];

        foreach ($data['passengers'] as $passenger) {
            $r->general()->traveller(beautifulName($passenger['givenName'] . " " . $passenger['familyName']));

            foreach ($passenger['segmentInfos'] as $sInfo) {
                foreach ($sInfo['profiles'] as $profile) {
                    $accounts[] = $profile['companyId'] . '-' . $profile['membershipNumber'];
                }
            }
        }
        $accounts = array_unique($accounts);

        if (!empty($accounts)) {
            $r->program()->accounts($accounts, false);
        }

        $badReservation = false;

        foreach ($data['segments'] as $segment) {
            $s = $r->addSegment();
            $cancelled = false;

            if (isset($segment['status']) && $segment['status'] === 'CC') {
                $cancelled = true;
            }
            $s->departure()
                ->code($segment['originPort'])
                ->date(strtotime($segment['departureTime']))
                ->terminal($segment['originTerminal'] ?? null, true, true);
            $s->arrival()
                ->code($segment['destPort'])
                ->terminal($segment['destTerminal'] ?? null, true, true);

            if (!empty($segment['arrivalTime'])) {
                $s->arrival()->date(strtotime($segment['arrivalTime']));
            } else {
                $s->arrival()->noDate();
            }

            if ($cancelled && !isset($segment['operateCompany'])) {
                $airName = $segment['marketCompany'];
                $flNumber = $segment['marketSegmentNumber'];
            } else {
                $airName = $segment['operateCompany'];
                $flNumber = $segment['operateSegmentNumber'] ?? $segment['marketSegmentNumber'];
            }
            $s->airline()
                ->name($airName)
                ->number($flNumber);

            if (!$cancelled || !empty($segment['totalduration'])) {
                $s->extra()
                    ->bookingCode($segment['subClass'])
                    ->duration($segment['totalduration']['hour'] . 'h' . $segment['totalduration']['minute'] . 'm')
                    ->aircraft($segment['airCraftType']);
            }
            $seats = [];

            foreach ($segment['passengers'] as $passenger) {
                foreach ($passenger['segmentInfos'] as $sInfo) {
                    $seat = $sInfo['seat']['seatNo'] ?? null;

                    if (!$seat) {
                        continue;
                    }

                    if ($this->http->FindPreg("/^(\d+[A-z])$/", false, $seat)) {
                        $seats[] = $seat;
                    }
                }
            }
            $seats = array_unique($seats);

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
            // From here:
            // https://www.cathaypacific.com/cx/en_US/frequent-flyers/about-the-club/club-benefits/bookable-upgrade.html?switch=Y
            $cabinClass = $segment['cabinClass'];

            if (in_array($cabinClass, ['F'])) {
                $s->extra()->cabin('First');
            } elseif (in_array($cabinClass, ['J', 'C', 'D', 'P', 'I'])) {
                $s->extra()->cabin('Business');
            } elseif (in_array($cabinClass, ['W', 'R'])) {
                $s->extra()->cabin('Premium Economy Class');
            } elseif (in_array($cabinClass, ['Y', 'B', 'H', 'K', 'M', 'L', 'V'])) {
                $s->extra()->cabin('Economy');
            } elseif (!$cancelled) {
                $this->sendNotification("check new cabin {$segment['cabinClass']} // ZM");
            }

            if (isset($segment['status'])) {
                switch ($segment['status']) {
                case 'CF':
                    $s->extra()->status('Confirmed');

                    break;

                case 'WL':
                    $s->extra()->status('Waitlisted');

                    break;

                case 'SA':
                    $s->extra()->status('Standby');

                    break;

                case 'CC':
                    $s->extra()
                        ->status('Cancelled')
                        ->cancelled();

                    break;

                default:
                    $this->sendNotification("check new status {$segment['status']} // ZM");

                    break;
            }
            }

            if ($airName === 'QF' && $segment['originPort'] === 'QZW' && $segment['destPort'] === 'QZY') {
                $badReservation = true;
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        if ($badReservation) {
            $this->logger->error('Reservation -  credit for a flight cancellation that requires ringing to rebook. skip it (otherwise service remove it, unknown AirCode)');
            $this->itinerariesMaster->removeItinerary($r);
        }

        return null;
    }

    private function CheckConfirmationNumberInternalOld($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->LogHeaders = true;
        $this->http->ParseForms = false;

        $this->http->GetURL('https://www.cathaypacific.com');

        if ($this->http->Response['code'] === 403) {
            throw new CheckRetryNeededException(2, 10);
        }

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*',
            'Content-Type'     => 'application/json; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $airlines = $this->getAirLines($headers);

        $this->http->PostURL("https://www.cathaypacific.com/member/login/getProfile", "{}", $headers);

        $params = [];
        $params["recordLocator"] = $arFields["ConfNo"];
        $params["familyName"] = $arFields["familyName"];
        $params["givenName"] = $arFields["givenName"];
        $params["locale"] = "en_US";
        $params["source"] = "CX";
        $this->http->PostURL('https://api.cathaypacific.com/mmb-booking/summary/mmbBookingDetailsByBookingRefNumberLogin', json_encode($params), $headers);
        $response = $this->http->JsonLog(null, 0, true);

        if ($response) {
            $it = $this->parseJsonFlight($response, $airlines);
        } else {
            $this->sendNotification("asia - failed to retrieve itinerary by conf #");
        }

        return null;
    }

    private function parseJsonFlight($response, $airlines): array
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        if (empty($response['bookings'])) {
            return $result;
        }

        foreach ($response['bookings'] as $itin) {
            $it = [
                "Kind"          => "T",
                "RecordLocator" => $itin['recordLocator'],
            ];

            if (!empty($itin['passengers'])) {
                foreach ($itin['passengers'] as $passenger) {
                    $it["Passengers"][] = beautifulName($passenger['familyName'] . " " . $passenger['givenName']);
                }
            }
            $segments = [];

            if (isset($itin['segments'])) {
                foreach ($itin['segments'] as $segment) {
                    $carrier = $segment['carrierCode'];

                    if (isset($airlines[$carrier])) {
                        $airline = $airlines[$carrier];
                    } elseif (strlen($carrier) == 2 && strtoupper($carrier) === $carrier) {
                        $airline = $carrier;
                    } else {
                        $this->sendNotification('asia - check airline');
                        $airline = null;
                    }
                    $ts = [
                        'DepCode'      => $segment['origin'],
                        'ArrCode'      => $segment['destination'],
                        'DepDate'      => strtotime($segment['departureDate'] . ' ' . $segment['departureTime']),
                        'ArrDate'      => strtotime($segment['arrivalDate'] . ' ' . $segment['arrivalTime']),
                        'AirlineName'  => $airline,
                        'FlightNumber' => $segment['flightNumber'],
                        'Aircraft'     => $segment['aircraftType'],
                    ];

                    if ($ts['Aircraft'] !== 'TRN') {
                        $segments[] = $ts;
                    }
                }
            }
            $it['TripSegments'] = $segments;
            $result[] = $it;

            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);
        }

        return $result;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0, true);
        $nodes = ArrayVal($response, 'unstatementedTransactions', []);
        $this->logger->debug("Total " . count($nodes) . " activity rows were found");
        // You don’t have any transactions during the selected period.
        if ($this->http->FindPreg("/\"unstatementedTransactions\":\[\]/")) {
            $this->logger->notice("You don’t have any transactions during the selected period.");
        }

        foreach ($nodes as $node) {
            $dateStr = ArrayVal($node, 'creditingDate');
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Crediting Date'] = $postDate;
            $result[$startIndex]['Activity Date'] = strtotime(ArrayVal($node, 'activityDate'));
            $result[$startIndex]['Activity'] = implode(' ', array_filter(ArrayVal($node, 'activity', [])));

            if (stristr($result[$startIndex]['Activity'], 'Bonus')) {
                $result[$startIndex]['Bonus'] = ArrayVal($node, 'asiaMiles');
            } else {
                $result[$startIndex]['Asia Miles'] = ArrayVal($node, 'asiaMiles');
            }
            $startIndex++;
        }// for ($i = 0; $i < $nodes->length; $i++)
//        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->attempt == 2) {
                $selenium->useChromePuppeteer();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            } elseif ($this->attempt == 1) {
                $selenium->useFirefoxPlaywright();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;

                $selenium->seleniumOptions->setResolution([1920, 1080]);
            } else {
                $selenium->useChromePuppeteer();

                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
                $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if (isset($fingerprint)) {
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL("https://www.cathaypacific.com/cx/en_US/sign-in.html?switch=Y");

            if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                $typeAuth = 'Sign in with membership number';
                $typeLogin = 'membernumber';
            } else {
                $typeAuth = 'Sign in with email';
                $typeLogin = 'email';
            }

            $selenium->waitForElement(WebDriverBy::xpath("//button[contains(normalize-space(),'{$typeAuth}')] | //h1[contains(text(), 'Access Denied')]"), 15);
            $this->savePageToLogs($selenium);

            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(normalize-space(),'{$typeAuth}')]"), 0);

            if (!$btn) {
                $this->logger->error("{$typeAuth} not found");
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('(//h1[contains(text(), "Access Denied")] | //div[contains(text(), "Error loading chunks!")])[1]')) {
                    $retry = true;
                }

                return false;
            }

            $btn->click();
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='{$typeLogin}']"), 2);
            $this->savePageToLogs($selenium);

            if (!$login && ($btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(normalize-space(),'{$typeAuth}')]"), 0))) {
                $this->logger->notice("scroll to btn");
                $x = $btn->getLocation()->getX();
                $y = $btn->getLocation()->getY() - 200;
                $selenium->driver->executeScript("window.scrollBy($x, $y)");
                $this->savePageToLogs($selenium);

                $btn->click();
                $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='{$typeLogin}']"), 2);
                $this->savePageToLogs($selenium);
            }

            $pwd = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='password']"), 0);

            if (!$login || !$pwd) {
                $this->logger->error("login field(s) not found");
                $this->savePageToLogs($selenium);

                return false;
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($login, $this->AccountFields['Login'], 5);
            $pwd->sendKeys($this->AccountFields['Pass']);
            sleep(2);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[normalize-space()='Sign in']"), 0);
            $this->savePageToLogs($selenium);
            $btn->click();

            sleep(5);

            $selenium->waitForElement(WebDriverBy::xpath("
                //span[contains(@class, 'welcomeLabel ')][starts-with(normalize-space(),'Welcome,')] 
                | //h2[contains(text(), 'Confirm your mobile phone number') or contains(text(), 'We need to verify your identity') or contains(text(), 'Enter your mobile phone number')] 
                | //a[contains(., 'Continue to sign in')] 
                | //label[contains(@class, 'textfield__errorMessage')] 
                | //div[contains(@class, 'serverSideError__messages')] 
                | //h1[contains(text(), 'Access Denied')]
                | //h1[contains(text(), 'Update your password')]
                | //span[contains(text(), 'This site can’t be reached')]
                | //h1[contains(text(), 'Secure Connection Failed')]
            "), 10);
            $this->savePageToLogs($selenium);

            $_abck = $bm_sz = $mlc_prelogin = null;

            $msg = $this->http->FindSingleNode('//label[contains(@class, "textfield__errorMessage")]')
                ?? $this->http->FindSingleNode('//div[contains(@class, "serverSideError__messages")]');

            if ($msg) {
                if ($msg === 'Your sign-in details are incorrect. Please check your details and try again. [ Error Code: 2004 ]'
                    || strpos($msg, 'Your member account is temporarily locked after too many unsuccessful login attempts. You can reset your password by confirming your personal information') !== false
                ) {
                    throw new CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }

                if (stripos($msg, 'Please enter a valid membership number') !== false
                    || strpos($msg, 'The email address you have entered is incorrect') !== false
                    || strpos($msg, 'Email has not been linked and verified. Please sign in using your membership number') !== false
                    || strpos($msg, 'Inactive Membership number / Username.') !== false
                    || strpos($msg, 'Inactive Membership number.') !== false
                    || strpos($msg, 'Your email has not been set as a sign-in ID. Please sign in using your membership number.') !== false
                ) {
                    throw new CheckException($msg, ACCOUNT_INVALID_PASSWORD);
                }

                $this->sendNotification('check error');
                $this->DebugInfo = $msg;

                return false;
            }

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Update your password')]")) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->FindSingleNode("//h2[contains(text(), 'Confirm your mobile phone number') or contains(text(), 'We need to verify your identity') or contains(text(), 'Enter your mobile phone number')] | //a[contains(., 'Continue to sign in')]")) {
                $later = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(normalize-space(),'Remind me later')] | //a[contains(., 'Continue to sign in')]"), 0);

                if (!$later) {
                    $this->savePageToLogs($selenium);

                    return false;
                }

                $later->click();
                $selenium->waitForElement(WebDriverBy::xpath("
                    //span[contains(@class, 'welcomeLabel ')][starts-with(normalize-space(),'Welcome,')] 
                    | //label[contains(@class, 'textfield__errorMessage')] 
                    | //h1[contains(text(), 'Access Denied')]
                    | //span[contains(text(), 'This site can’t be reached')]
                    | //h1[contains(text(), 'Secure Connection Failed')]
                "), 15);
            }

            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode("//span[contains(@class, 'welcomeLabel ')][starts-with(normalize-space(),'Welcome,')]")
                || ($this->attempt == 0) // debug
            ) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    if ($cookie['name'] === 'bm_sz') {
                        $bm_sz = $cookie['name'];
                        Cache::getInstance()->set(self::BMSZ_CACHE_KEY, $cookie['value'], 60 * 60);
                    } elseif ($cookie['name'] === '_abck') {
                        $_abck = $cookie['name'];
                        Cache::getInstance()->set(self::ABCK_CACHE_KEY, $cookie['value'], 60 * 60);
                    } elseif ($cookie['name'] === 'mlc_prelogin') {
                        $mlc_prelogin = $cookie['name'];
                    }

                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                }
            }

            if (isset($_abck, $bm_sz)) {
                $this->cookiesRefreched = true;
            }

            if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')] | //h1[contains(text(), 'Secure Connection Failed')]")) {
                $this->markProxyAsInvalid();
                $retry = true;
            } elseif ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->accessDenied = true;

                if (!$this->cookiesRefreched) {
                    $retry = true;
                }
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability && $this->accessDenied) {
                    // retrying doesn't help
                    throw new CheckException('Access Denied', ACCOUNT_ENGINE_ERROR);
                }
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
