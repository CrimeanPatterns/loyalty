<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAlaskaair extends TAccountChecker
{
    use ProxyList;
    use PriceTools;

    private const REWARDS_PAGE_URL = "https://www.alaskaair.com/www2/ssl/myalaskaair/myalaskaair.aspx?view=miles&lid=account-overview:mileage-activity";
    /** @var CaptchaRecognizer */
    private $recognizer;
    private $currentItin = 0;

    private $headers = [];

    private $limitedAccount = false;

    private $accountGuid = null;
    private $history = [];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'], $properties['Discount'])
            && strstr($properties['SubAccountCode'], 'AlaskaairDiscountCodes')) {
            return $properties['Discount'];
        }

        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'alaskaairMyWallet')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /* =============================== */
        // prevent servers overloaded if authorizations is down
        $this->http->RetryCount = 1;
        /* =============================== */
        $this->http->SetProxy($this->proxyWhite(), false);
    }

    public function IsLoggedIn()
    {
        return $this->getTokens();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (strlen($this->AccountFields['Pass']) < 5 || strlen($this->AccountFields['Pass']) > 50) {
            throw new CheckException('The password you entered is invalid. The password must be between 5 and 50 characters in length.', ACCOUNT_INVALID_PASSWORD);
        }

        if (strstr($this->AccountFields['Login'], 'onerror=prompt(1)')) {
            throw new CheckException("The sign-in information entered does not match our records. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if (!$this->http->GetURL('https://www.alaskaair.com/www2/ssl/myalaskaair/MyAlaskaAir.aspx?CurrentForm=UCSignInStart') || !$this->http->ParseForm('myalaskaair')) {
            $this->http->TimeLimit = 600;
            $this->checkAlaskaError();

            if ($this->http->FindSingleNode("//body[contains(text(), 'Warmup in progress. Please try again later.')]")) {
                throw new CheckRetryNeededException(2);
            }
        }

        // parsing form on the page
        if (!$this->http->ParseForm('myalaskaair')) {
            return $this->checkAlaskaError();
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "This feature is temporarily unavailable because our database is currently down.")]')) {
            throw new CheckException("My Account Feature is temporarily unavailable because our database is currently down.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->FormURL = 'https://www.alaskaair.com/www2/ssl/myalaskaair/myalaskaair.aspx';
        // enter the login and password
        $this->http->SetInputValue('FormUserControl$_signInProfile$_userIdControl$_userId', $this->AccountFields['Login']);
        $this->http->SetInputValue('FormUserControl$_signInProfile$_passwordControl$_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('_jsEnabled', 1);
        $this->http->SetInputValue('FormUserControl$_signIn', 'SIGN IN');
        $this->http->SetInputValue('FormUserControl$_signInProfile$_rememberMe', 'on');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkAlaskaError();
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // To return to using alaskaair.com, click the Continue button.
        if ($message = $this->http->FindPreg("/To return to using alaskaair.com, click the Continue button/ims")) {
            throw new CheckException("Please login to alaskaair.com and update your account first,
        	then we should be able to update your balance.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        // check for invalid password
        if ($message = $this->http->FindSingleNode("//*[@id='_userIdValidator']/text()", null, true, "/^([^\.]+)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[@id=\"_userIdValidator\" and @class = \"errorText\"]")) {
            throw new CheckException(preg_replace('/^(error)/ims', '', $message, 1), ACCOUNT_INVALID_PASSWORD);
        }

        // Invalid credentials
        if ($message = $this->http->FindPreg("/The request was not successful.\s*See the red message\(s\) below and make changes as needed\./ims")) {
            throw new CheckException("Invalid User ID, Mileage Plan number or Password. Please confirm the accuracy of the information entered.", ACCOUNT_INVALID_PASSWORD);
        }
        // The sign-in information entered does not match our records
        if ($message = $this->http->FindPreg("/(The sign-in information entered does not match our records\.\s*Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The allowed number of sign-in attempts has been reached
        if ($message = $this->http->FindPreg("/(The allowed number of sign-in attempts has been reached.\s*This User ID has been temporarily disabled.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // My Account is currently unavailable and will be restored as quickly as possible.
        if ($message = $this->http->FindPreg("/My Account is currently unavailable and will be restored as quickly as possible\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our web server encountered an internal error. It was logged to aid our staff in finding a solution.
        if (($message = $this->http->FindPreg("/Our web server encountered an internal error\.\s*It was logged to aid our staff in finding a solution\./"))
            && (
                $this->http->currentUrl() == 'https://www.alaskaair.com/errors/customerror.aspx'
                || strstr($this->http->currentUrl(), 'https://www.alaskaair.com/UserReset/LockedAccount?userid=')
            )
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (($message = $this->http->FindSingleNode('//p[contains(normalize-space(text()), "You\'ve reached the maximum number of sign-in attempts. Your account has been locked until you reset your password.")]'))
            && strstr($this->http->currentUrl(), 'https://www.alaskaair.com/UserReset/LockedAccount?userid=')
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // AccountID: 1741966, 721968
        if (
            $this->http->FindSingleNode('//h1[contains(normalize-space(text()), "Do you need to reset your password?") or normalize-space(text()) = "Forgot your password?"]')
            && strstr($this->http->currentUrl(), 'https://www.alaskaair.com/UserReset/LockedAccount?userid=')
        ) {
            throw new CheckException("You've reached the maximum number of sign-in attempts. Your account has been locked until you reset your password.", ACCOUNT_LOCKOUT);
        }

        if ($this->http->currentUrl() == 'https://www.alaskaair.com/errors/CustomError.aspx') {
            throw new CheckException('The sign-in information entered does not match our records. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        /*
        if (!stristr($this->http->currentUrl(), 'https://www.alaskaair.com/www2/ssl/myalaskaair/MyAlaskaAir.aspx')) {
            $this->http->GetURL("https://www.alaskaair.com/www2/ssl/myalaskaair/MyAlaskaAir.aspx");
        }

        // refs #10315
        if ($this->http->FindSingleNode("//a[contains(@title, 'Join Mileage Plan')]")
            || $this->http->FindSingleNode("//div[@id = 'FormUserControl__myOverview__noMileagePlanNumberNotificationPreviewOnly']//a[contains(text(), 'JOIN NOW')]")) {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return true;
        }
        */

        $this->checkAlaskaError();
        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }
        // don't use it in loginSuccessful, because we get properties from cookies
        if (
            // AccountID: 4374028
            $this->http->FindSingleNode('
                //span[b[contains(text(), "Password Protected")] and contains(., "The Mileage Program number is password protected and not available for viewing activity online.")]
                | //span[b[contains(text(), "Mileage Program Discrepancies")] and contains(., "The Mileage Program account has some discrepancies.")]
            ')
        ) {
            $this->limitedAccount = true;

            return true;
        }

        // retries
        if ($this->http->ParseForm('myalaskaair')) {
            $this->http->TimeLimit = 600;

            throw new CheckRetryNeededException(3);
        }

        if ($this->http->FindSingleNode("//body[contains(text(), 'Warmup in progress. Please try again later.')]")) {
            throw new CheckRetryNeededException(2);
        }

        return $this->checkAlaskaError();
    }

    public function getTokens()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"  => "application/json, text/plain, */*",
            "Origin"  => "https://www.alaskaair.com",
            "Referer" => "https://www.alaskaair.com/",
        ];
        $this->http->GetURL("https://www.alaskaair.com/account/token", $headers, 20);
        $response = $this->http->JsonLog();

        if (
            !isset($response->loggedIn)
            || !isset($response->token)
            || !isset($response->alaskaLoyaltyNumber)
            || $response->loggedIn != 'true'
        ) {
            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.alaskaair.com/trips");

        if (!$this->getTokens()) {
            return;
        }
        $tokenInfo = $this->http->JsonLog(null, 0);
        $this->headers = [
            "Accept"                    => "*/*",
            "Origin"                    => "https://www.alaskaair.com",
            "Referer"                   => "https://www.alaskaair.com/",
            "Authorization"             => "{$tokenInfo->type} {$tokenInfo->token}",
            "Ocp-Apim-Subscription-Key" => "4feac8b892704e54a54758a9ee092cb0", // https://apis.alaskaair.com/1/guestServices/admin/headerFooter/footer.js
        ];

        $this->accountGuid = $tokenInfo->accountGuid;

        $this->http->RetryCount = 0; // Unable to retrieve your Loyalty Information. issue
        $this->http->GetURL("https://apis.alaskaair.com/1/Marketing/LoyaltyManagement/MileagePlanUI/api/Member?accountGuid={$this->accountGuid}", $this->headers);

        $accounts_with_403 = [
            '265374502', // I don't know WTF with this chinese account, AccountID: 4370823
            '14811333',
            '156735762',
            'GregTWilliams',
            'felixlo@gmail.com',
            '207199930',
            '172271912',
            'porritt@wi.rr.com',
            'alankaionlam@college.harvard.edu',
            '143381534',
            '258134214',
        ];

        // it helps
        if (
            $this->http->Response['code'] == 403
            && !in_array($this->AccountFields['Login'], $accounts_with_403)
            && $this->AccountFields['Partner'] == 'awardwallet'
            && $this->attempt == 0
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // provider bug fix
        if (
            $this->http->Response['code'] == 500
            && isset($response->message)
            && $response->message == 'Internal server error'
        ) {
            sleep(5);
            $this->http->RetryCount = 1;
            $this->http->GetURL("https://apis.alaskaair.com/1/Marketing/LoyaltyManagement/MileagePlanUI/api/Member?accountGuid={$this->accountGuid}", $this->headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
        }

        // refs #10315, not a member
        if (
            $this->http->Response['code'] !== 403
            && !empty($response)
            && $response->loyalty === null
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
            // Name
            $firstName = $response->account->legalName->firstName ?? null;
            $lastName = $response->account->legalName->lastName ?? null;
            $this->SetProperty("Name", beautifulName("$firstName $lastName"));
            // Discount Codes   // refs #5308
            $this->discountCodes();
            // My wallet // refs #16282
            $this->myWallet($tokenInfo);
            // Guest upgrades // refs #16446
            $this->guestUpgrades();

            return;
        }

        if (!empty($response)) {
            // Balance - Available Miles
            $this->SetBalance($response->loyalty->memberBalance);
            // Status
            $status = $response->loyalty->tierName;

            if ($status == 'Regular') {
                $status = "Member";
            }

            $this->SetProperty("Status", $status);
            // Name
            $this->SetProperty("Name", beautifulName($response->loyalty->firstName . " " . $response->loyalty->lastName));
            // Mileage Plan number
            $this->SetProperty("Number", $response->loyalty->mileagePlanNumber);
            // Member Since
            if (!empty($response->loyalty->startDate)) {
                $this->SetProperty("MemberSince", date("m/d/Y", strtotime($response->loyalty->startDate)));
            }
            // YTD Alaska Miles
            $this->SetProperty("Miles", $response->loyalty->asMiles);
            // YTD Alaska Segments
            $this->SetProperty("Segments", $response->loyalty->asSegments);
            // YTD Qualifying Partner Miles
            $this->SetProperty("PartnerMiles", $response->loyalty->asoaMiles);
            // YTD Qualifying Partner Segments
            $this->SetProperty("PartnerSegments", $response->loyalty->asoaSegments);
            // Alaska Miles toward Million Mile Flyer
            $this->SetProperty("MillionMilerMiles", $response->loyalty->lifetimeMiles);
        }

        // Beginning of period for elite levels
        $this->SetProperty("YearBegins", strtotime("1 JAN"));

        // broken accounts
        if (
            $this->ErrorCode === ACCOUNT_ENGINE_ERROR
            && (
                // AccountID: 3619907, 216700
                (
                    $this->http->Response['code'] == 500
                    && $this->http->FindPreg('/^Unable to retrieve Mileage Plan information\.$/', false, $this->http->Response['body'])
                )
                // I don't know WTF with this chinese account, AccountID: 4370823
                || in_array($this->AccountFields['Login'], $accounts_with_403)
                || $this->http->Response['code'] == 403
                // AccountID: 4868060, 4687752, 3516474
                || (
                    isset($response->loyalty)
                    && $response->loyalty->memberBalance === null
                    && $response->loyalty->asMiles === null
                    && $response->loyalty->statusMessage === "Active Member not found"
                    && $response->loyalty->tierName === null
                )
            )
        ) {
            $this->logger->notice("broken account, get properties from cookies");
            $fName = urldecode($this->http->getCookieByName("AS%5FNAME"));
            // Balance - Available miles
            $balance = $this->http->FindPreg("/BM=([^\&]+)/ims", false, $fName);
            $this->SetBalance(str_replace(",", "", $balance));

            if (
                $this->ErrorCode === ACCOUNT_ENGINE_ERROR
                && $this->http->FindPreg("/BM=&/ims", false, $fName)
            ) {
                $this->logger->notice("broken account, set Balance 0");
                $this->SetBalance(0);
            }

            // Mileage Plan #
            $this->SetProperty("Number", $this->http->FindPreg("/MP=([^\&]+)/ims", false, $fName));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindPreg("/FN=([^\&]+)/", false, $fName) . " " . $this->http->FindPreg("/LN=([^\&]+)/", false, $fName)));
        }

        // Get property 'LastActivity' and Expiration Date // refs #7542, 4157, 21848
        $this->getExpirationDate();

        // Discount Codes   // refs #5308
        $this->discountCodes();
        // My wallet // refs #16282
        $this->myWallet($tokenInfo);
        // Guest upgrades // refs #16446
        $this->guestUpgrades();
        // Alaska Lounge Passes // refs #17434
        $this->alaskaLoungePasses();
        // refs #14648
        $this->profileInfo();
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $ocpKey = $this->getTokenForIts();
        $res = $this->http->JsonLog();

        if (null === $res) {
            $this->logger->debug("retry to get token");
            $ocpKey = $this->getTokenForIts();
            $res = $this->http->JsonLog();
        }

        if ($res && $res->loggedIn === false) {
            $this->logger->error("token failed");

            if (!$this->LoadLoginForm() || !$this->Login()) {
                $this->logger->error("not logged in");

                return null;
            }
            $ocpKey = $this->getTokenForIts();
            $res = $this->http->JsonLog();
        }

        if (null === $res || !isset($res->loggedIn)) {
            $this->logger->error("failed to get token");

            return [];
        }

        $headers = [
            'Alaskaloyaltynumber'       => $res->alaskaLoyaltyNumber,
            'Authorization'             => ucfirst($res->type) . ' ' . $res->token,
            'Ocp-Apim-Subscription-Key' => $ocpKey,
            'Myaccountguid'             => $res->accountGuid,
            'Accept'                    => '*/*',
            'Origin'                    => 'https://www.alaskaair.com',
            'Referer'                   => 'https://www.alaskaair.com/trips',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apis.alaskaair.com/1/guestservices/customermobile/trips/list", $headers);
        $this->http->RetryCount = 2;
        $res = $this->http->JsonLog();

        if (isset($res->errors) && !empty($res->errors)) {
            if (isset($res->errors[0], $res->errors[0]->code)
                && ($res->errors[0]->code === 'FindTrackedTripsFailed'
                    || $res->errors[0]->code === 'FFNLookupFailed' // No FFN found. Unable to call Loyalty Management GraphQL service.
                )
            ) {
                $this->logger->error($res->errors[0]->message);

                if ($res->errors[0]->code === 'FindTrackedTripsFailed') {
                    $noSendNotifications = true;
                }

                $this->http->RetryCount = 0;
                $this->http->GetURL("https://apis.alaskaair.com/1/guestservices/customermobile/trips/list", $headers);
                $this->http->RetryCount = 2;
                $res = $this->http->JsonLog();

                if (!isset($noSendNotifications)) {
                    if (!isset($res->errors) || empty($res->errors)) {
                        $this->sendNotification("retry helped // MI");
                    } else {
                        $this->sendNotification("retry not work // MI");

                        return [];
                    }
                }
            } else {
                $this->sendNotification("check error // MI");

                return [];
            }
        } elseif (in_array($this->http->Response['code'], [401, 403, 500])) {
            $this->logger->notice("Retry");
            sleep(3);
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://apis.alaskaair.com/1/guestservices/customermobile/trips/list", $headers);

            if (in_array($this->http->Response['code'], [401, 403, 500])) {
                sleep(5);
                $this->http->GetURL("https://apis.alaskaair.com/1/guestservices/customermobile/trips/list", $headers);
            }
            $this->http->RetryCount = 2;
        }

        if ($res === null) {
            $this->logger->error("can't get list");

            return [];
        }

        if ($this->http->FindPreg("/^\{\"errors\":\[\],\"upcoming\":null,/")
        || $this->http->FindPreg("/^\{\"errors\":\[\],\"upcoming\":\[\],/")
            || $this->http->FindPreg("/^\{\"errors\":\[\{\"message\":\"No FFN found. Unable to call Loyalty Management GraphQL service.\",\"code\":\"FFNLookupFailed\"\}\],\"upcoming\":null/")
        ) {
            return $this->noItinerariesArr();
        }

        if (isset($res->upcoming) && is_array($res->upcoming)) {
            foreach ($res->upcoming as $upcoming) {
                $pnr = $upcoming->confirmationCode;
                $lname = $upcoming->passengers[0]->lastName;
                $this->increaseTimeLimit();
                $itinUrl = "https://www.alaskaair.com/booking/reservation-lookup?LNAME={$lname}&RECLOC={$pnr}&lid=myas:trips-upcoming-details";
                $this->http->GetURL($itinUrl);

                if ($this->http->FindPreg('/Warmup in progress. Please try again later./')
                    || $this->http->FindSingleNode("//h1[contains(text(),'Reservation temporarily inaccessible')]")
                    /*|| !$this->http->FindSingleNode("//span[contains(text(),'Confirmation ')]")*/) {
                    sleep(7);
                    $this->http->GetURL($itinUrl);
                }

                if ($this->http->FindPreg("/<h1>Error Encountered<\/h1>\s+<span id=\"_message\"><p>Our web server encountered an internal error/")
                    && $this->http->FindPreg('/Please\s+try\s+your\s+transaction\s+again/')
                ) {
                    // sometimes retry helps
                    sleep(5);
                    $this->http->GetURL($itinUrl);

                    if ($this->http->FindPreg("/<h1>Error Encountered<\/h1>\s+<span id=\"_message\"><p>Our web server encountered an internal error/")
                        && $this->http->FindPreg('/Please\s+try\s+your\s+transaction\s+again/')
                    ) {
                        $this->logger->error("Error Encountered: Our web server encountered an internal error...");

                        continue;
                    }
                }

                if ($error = $this->http->FindSingleNode("//div[@class='errorTextSummary']")) {
                    $this->logger->error($error);

                    continue;
                }

                if ($error = $this->http->FindSingleNode("//*[self::div or self::p][contains(@class,'errorAdvisory')]")) {
                    $this->logger->error($error);

                    continue;
                }

                if ($this->http->FindSingleNode("//h1[contains(text(), 'Confirm Your Schedule Change')]")
                    && $this->http->FindSingleNode("//h2[contains(text(), 'New Schedule')]")
                ) {
                    $this->parseChangedItineraryV2(strtotime($upcoming->startDate), $upcoming->passengers);
                } elseif ($this->http->ParseForm('modern-view-pnr')) {
                    $this->http->PostForm();
                    $this->parseItineraryHtmlV2();
                } else {
                    $this->parseItineraryHtml();
                }
            }
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "LastName" => [
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Confirmation Code or E-Ticket number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.alaskaair.com/booking/reservationlookup/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->LogHeaders = true;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
//        $this->handleCaptcha();
        if (!$this->http->ParseForm("reservationLookUpForm")) {
            $this->sendNotification("alaskaair - failed to retrieve itinerary by conf #");

            return null;
        }
        $this->http->SetInputValue('TravelerLastName', $arFields["LastName"]);
        $this->http->SetInputValue('CodeOrNumber', $arFields["ConfNo"]);
        $this->http->SetInputValue('Continue', 'CONTINUE');

        if (!$this->http->PostForm()) {
            return null;
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'server encountered an internal error')]")) {
            $this->logger->error($message);
            $this->logger->debug('retry...');
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
//        $this->handleCaptcha();
            if (!$this->http->ParseForm("reservationLookUpForm")) {
                $this->sendNotification("alaskaair - failed to retrieve itinerary by conf #");

                return null;
            }
            $this->http->SetInputValue('TravelerLastName', $arFields["LastName"]);
            $this->http->SetInputValue('CodeOrNumber', $arFields["ConfNo"]);
            $this->http->SetInputValue('Continue', 'CONTINUE');

            if (!$this->http->PostForm()) {
                return null;
            }

            if ($this->http->FindSingleNode("(//*[contains(text(), 'Confirm your schedule change')])[1]")) {
                $this->sendNotification('schedule change // MI');
            }

            if ($this->http->FindSingleNode("//p[contains(text(), 'server encountered an internal error')]")) {
                $this->sendNotification("retry - fail");

                return null;
            }
            $this->sendNotification("retry - ok");
        }

        if (($message = $this->http->FindNodes("//div[@class='errorText']/text()"))) {
            return implode("", $message);
        }

        if (($message = $this->http->FindNodes("//div[@class='errorTextSummary']//text()[not(./ancestor::*[1][@class='hidden'])]"))) {
            return implode("", $message);
        }
        $result = $this->parseItineraryV2();

        if (!$result) {
            $this->parseItineraryHtml();
        }

        if (!empty($result) && is_string($result)) {
            return $result;
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Date" => "PostingDate",
            "Activity Type" => "Description",
            "Status"        => "Info",
            "Miles"         => "Info",
            "Bonus"         => "Bonus",
            "Total"         => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');

        $startIndex = sizeof($result);
        $pageResult = $this->ParsePageHistory($startIndex, $startDate);
        $result = array_merge($result, $pageResult);

        // Sort
        usort($result, function ($a, $b) {
            if ($a['Activity Date'] == $b['Activity Date']) {
                return 0;
            }

            return ($a['Activity Date'] < $b['Activity Date']) ? 1 : -1;
        });

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->currentUrl() == 'https://www.alaskaair.com/account/overview'
        || $this->http->currentUrl() == 'https://www.alaskaair.com/account/mileageplan/activity') {
            return true;
        }

        return false;
    }

    private function checkAlaskaError()
    {
        $this->logger->notice(__METHOD__);
        // Looks like we are experiencing a temporary technical issue. We are aware of the issue and are actively looking into it.
        if ($message = $this->http->FindSingleNode("//span[strong[contains(text(), 'Looks like we are experiencing a temporary technical issue.')]]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# This feature is temporarily unavailable because our database is currently down
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'This feature is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# My Account is currently unavailable and will be restored as quickly as possible
        if ($message = $this->http->FindPreg("/(My Account is currently unavailable and will be restored as quickly as possible\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@id, 'errorMessage')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Error Encountered
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'server encountered an internal error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 - Bad Gateway
        if ($this->http->FindSingleNode('//h1[contains(text(), "502 - Bad Gateway")]')
            // Server Error in '/' Application
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // Fastly error: unknown domain www.alaskaair.com
            || $this->http->FindPreg("/Fastly error: unknown domain: www.alaskaair.com. Please check that this domain has been added to a service./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Error
        if ($message = $this->http->FindPreg("/(Our web server encountered an internal error\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 0) {
            throw new CheckException("Our web server encountered an internal error. It was logged to aid our staff in finding a solution.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");

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

    private function profileInfo()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Zip Code', ['Header' => 3]);
        $this->logger->debug('ZipCodeParseDate: ' . ($this->State['ZipCodeParseDate'] ?? null));
        $this->logger->debug('Time: ' . strtotime("-1 month"));

        if (isset($this->State['ZipCodeParseDate']) && $this->State['ZipCodeParseDate'] > strtotime("-1 month")) {
            $this->logger->notice('valid Zip Code');

            return;
        }

        if (!$this->http->ParseForm('myalaskaair')) {
            $this->http->GetURL("https://www.alaskaair.com/www2/ssl/myalaskaair/MyAlaskaAir.aspx?view=tprofiles");
        }

        if (!$this->http->ParseForm('myalaskaair')) {
            $this->logger->error('parse Zip Code failed');

            return;
        }
        $this->http->Form['__EVENTARGUMENT'] = 2;
        $this->http->Form['__EVENTTARGET'] = 'FormUserControl$_tabMenu';
        $this->http->Form['_jsEnabled'] = 1;
        $this->http->PostForm();

        if (!$this->http->ParseForm('myalaskaair')) {
            $this->logger->error('parse Zip Code failed');

            return;
        }
        $this->http->Form['__EVENTTARGET'] = 'FormUserControl$_myInfoSettings$_address';
        $this->http->Form['_jsEnabled'] = 1;
        $this->http->PostForm();

        $target = $this->http->FindSingleNode("//div[@id = 'FormUserControl__myInfoSettings__myContactAddress__addressDisplayList']//a[contains(@href, '_editAddressLinkButton')]/@href", null, true, "/__doPostBack\('([^\']+)/");

        if (!$this->http->ParseForm('myalaskaair') || !$target) {
            $this->logger->error('parse Zip Code failed');

            return;
        }
        $this->http->Form['__EVENTTARGET'] = $target;
        $this->http->Form['_jsEnabled'] = 1;
        $this->http->PostForm();

        $zip = $this->http->FindSingleNode("//input[@name = 'FormUserControl\$_address\$_zip']/@value");
        $country = $this->http->FindSingleNode('//select[@id = "FormUserControl__address__country"]//option[@selected]/@value');

        if ($country == 'US' && strlen($zip) == 9) {
            $zipCode = substr($zip, 0, 5) . " " . substr($zip, 5);
        } else {
            $zipCode = $zip;
        }
        $this->SetProperty("ZipCode", $zipCode);
        $street = $this->http->FindSingleNode("//input[@name = 'FormUserControl\$_address\$_addr1']/@value");
        $street2 = $this->http->FindSingleNode("//input[@name = 'FormUserControl\$_address\$_addr2']/@value");

        if ($street2) {
            $street .= ", " . $street2;
        }

        if ($zipCode && $street) {
            $this->SetProperty("ParsedAddress",
                $street
                . ", " . $this->http->FindSingleNode("//input[@id = 'FormUserControl__address__city']/@value")
                . ", " . $this->http->FindSingleNode('//select[contains(@name, "FormUserControl$_address$_state") and not(contains(@style, "display: none"))]//option[@selected and @value != ""]')
                . ", " . $zipCode
                . ", " . $country
            );
            $this->State['ZipCodeParseDate'] = time();
        }// if ($zipCode && $street)
    }

    private function discountCodes()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Discount and companion fare codes', ['Header' => 3]);
        $this->http->GetURL("https://www.alaskaair.com/www2/ssl/myalaskaair/myalaskaair.aspx?view=discounts&lid=utilNav:discountCodes");

        if ($message = $this->http->FindPreg("/(There are no valid Discount Codes saved in your account\.)/ims")) {
            $this->logger->notice(">>>> " . $message);
        }
        $codes = $this->http->XPath->query("//table[contains(@id, 'DiscountCodes')]//tr[td[6]]");
        $this->logger->debug("Total nodes found: " . $codes->length);

        if ($codes->length > 0) {
            for ($i = 0; $i < $codes->length; $i++) {
                $code = Html::cleanXMLValue(implode(' ', $this->http->FindNodes('td[1]/text()', $codes->item($i))));
                $displayName = Html::cleanXMLValue($this->http->FindSingleNode('td[3]', $codes->item($i)));
                $exp = $this->http->FindSingleNode('td[6]', $codes->item($i));
                $exp = str_replace('-', '/', $exp);
                $this->logger->debug(">>>> " . $exp);

                if (strtotime($exp)) {
                    $subAccounts[] = [
                        'Code'           => 'AlaskaairDiscountCodes' . $i,
                        'DisplayName'    => $displayName,
                        'Balance'        => null,
                        'Discount'       => $this->http->FindSingleNode('td[2]', $codes->item($i)),
                        'DiscountCode'   => $code,
                        'ExpirationDate' => strtotime($exp),
                    ];
                }
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }// if ($codes->length > 0)
    }

    private function guestUpgrades()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Guest upgrades', ['Header' => 3]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://apis.alaskaair.com/1/Marketing/LoyaltyManagement/MileagePlanUI/api/MPVoucher", "", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'validVouchers');

        if (!isset($response->voucherDetailsByStatus->validVouchers)) {
            return;
        }
        $upgrades = $response->voucherDetailsByStatus->validVouchers;
        $this->logger->debug("Total " . count($upgrades) . " Guest upgrades were found");
        $count = (count($upgrades) > 8 ? 8 : count($upgrades));
        $this->logger->debug("Count: $count");

        for ($i = 0; $i < $count; $i++) {
            // Upgrade code
            $displayName = $upgrades[$i]->voucherNumber;
            // Expiration Date
            $exp = strtotime($upgrades[$i]->voucherExpiryDate, false);
            $subAcc = [
                'Code'           => 'alaskaairGuestUpgrades' . str_replace('-', '', $displayName),
                'DisplayName'    => "Upgrade code {$displayName}",
                'Balance'        => null,
                "ExpirationDate" => $exp,
            ];
            $this->AddSubAccount($subAcc, true);
        }
    }

    private function alaskaLoungePasses()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Alaska Lounge Passes', ['Header' => 3]);

        $this->http->GetURL("https://www.alaskaair.com/www2/ssl/myalaskaair/myalaskaair.aspx?view=boardroomvalid&lid=account-overview:valid-lounge-passes");

        if (!$this->http->ParseForm('myalaskaair')) {
            return;
        }
        $this->http->Form['__EVENTARGUMENT'] = 19;
        $this->http->Form['__EVENTTARGET'] = 'FormUserControl$_tabMenu';
        $this->http->Form['_jsEnabled'] = 1;
        $this->http->PostForm();

        if ($message = $this->http->FindPreg("/(There are no valid Alaska Lounge Passes saved in your account\.)/ims")) {
            $this->logger->notice(">>>> " . $message);
        }
        $upgrades = $this->http->XPath->query("//table[contains(@id, 'boardRoomValid')]//tr[td[2]]");
        $this->logger->debug("Total {$upgrades->length} Alaska Lounge Passes were found");

        foreach ($upgrades as $upgrade) {
            // Upgrade code
            $displayName = $this->http->FindSingleNode("td[1]", $upgrade);
            // Expiration Date
            $exp = strtotime($this->http->FindSingleNode("td[2]", $upgrade), false);
            $subAcc = [
                'Code'           => 'alaskaairAlaskaLoungePasses' . $displayName,
                'DisplayName'    => "Lounge pass code {$displayName}",
                'Balance'        => null,
                "ExpirationDate" => $exp,
            ];
            $this->AddSubAccount($subAcc, true);
        }// foreach ($certificates as $certificate)
    }

    private function myWallet($tokenInfo)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('My wallet', ['Header' => 3]);

        if (empty($tokenInfo->alaskaLoyaltyNumber)) {
            return;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apis.alaskaair.com/1/marketing/loyaltymanagement/wallet/wallet/balance?mileagePlanNumber={$tokenInfo->alaskaLoyaltyNumber}", $this->headers);
        $this->http->RetryCount = 2;
        $myWalletBalance = $this->http->FindPreg("/^([\d\.\-\,]+)$/");

        if ($myWalletBalance === null) {
            $this->logger->error("something went wrong");

            return;
        }

        if (!empty($myWalletBalance) && $this->http->Response['code'] == 200) {
            $this->http->GetURL("https://apis.alaskaair.com/1/marketing/loyaltymanagement/wallet/certificates?mileagePlanNumber={$tokenInfo->alaskaLoyaltyNumber}", $this->headers);
            $certificates = $this->http->JsonLog();
            $this->logger->debug("Total " . count($certificates) . " Certificates were found");
            $expirationList = [];

            foreach ($certificates as $certificate) {
                // Available Balance
                $balance = PriceHelper::cost($certificate->availableBalance);
                // Certificate Type
                $displayName = $certificate->type;

                if ($balance == '0.00' || $certificate->expirationDate === null) {
                    $this->logger->debug("Skip {$displayName} / {$balance}: {$certificate->expirationDate}");

                    continue;
                }
//                $this->logger->notice("Adding {$displayName} / {$balance}");
                // Expiration Date
                $exp = strtotime($certificate->expirationDate, false);

                if (isset($expirationList[$exp])) {
                    $expirationList[$exp]['ExpiringBalance'] = $expirationList[$exp]['ExpiringBalance'] + $balance;
                } else {
                    $expirationList[$exp]['ExpiringBalance'] = $balance;
                }
//                $this->logger->debug(var_export($expirationList, true), ['pre' => true]);
            }// foreach ($certificates as $certificate)
            ksort($expirationList);
        }// if (!empty($myWalletBalance))

        $subAcc = [
            'Code'        => 'alaskaairMyWallet',
            'DisplayName' => 'My Wallet',
            'Balance'     => $myWalletBalance,
        ];

        if (!empty($expirationList)) {
            // Expiration Date
            $subAcc["ExpirationDate"] = key($expirationList);
            $subAcc["ExpiringBalance"] = '$' . current($expirationList)['ExpiringBalance'];
        }// if (!empty($expirationList)

        $this->AddSubAccount($subAcc, true);
    }

    private function getHistory()
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->accountGuid)) {
            $this->logger->error("accountGuid not found");

            return [];
        }

        if (!empty($this->history)) {
            return $this->history;
        }

        $headers = $this->headers;
        $headers["Accept"] = "application/json, text/plain, */*";
        $headers += [
            "AccountGuid" => $this->accountGuid,
        ];
        $this->http->GetURL("https://apis.alaskaair.com/1/Retain/Mileage/Plan/mpactivitybff/member/activities?Range=24", $headers);
        $response = $this->http->JsonLog(null, 1);
        $this->logger->debug("Total " . ((is_array($response) || ($response instanceof Countable)) ? count($response) : 0) . " history rows were found");
        $this->history = $response;

        return $this->history;
    }

    private function getExpirationDate()
    {
        $this->logger->info('Expiration date', ['Header' => 3]);
        $response = $this->getHistory();

        if (!$response) {
            return;
        }

        $activities = $response->activities ?? [];

        foreach ($activities as $row) {
            // Activity Date
            $date = $row->activityDate;
            // Total
            $totalMiles = $row->total;
            $this->logger->debug("Date: {$date} / Miles: {$totalMiles}");

            if ($totalMiles != 0) {
                $lastActivityDate = strtotime($date);

                if ($lastActivityDate !== false) {
                    $this->SetProperty("LastActivity", $date);
                    $accountExpirationDate = strtotime("+3 year", $lastActivityDate);
                    $this->SetExpirationDate($accountExpirationDate);
                }// if ($lastActivityDate !== false)

                break;
            }// if ($totalMiles != 0)
        }// foreach ($response as $row)
    }

    private function getTokenForIts()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.alaskaair.com/trips");
        $urlJs = $this->http->FindPreg("/src=\"(bundle.js\?ver=[\d.]+)\"><\/script><\/body>/");
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.alaskaair.com/trips/" . $urlJs);
        $ocpKey = $this->http->FindPreg("/\"Ocp\-Apim\-Subscription\-Key\":\"(\w+)\"/");
        $this->http->GetURL("https://www.alaskaair.com/account/token");
        $this->http->RetryCount = 2;

        return $ocpKey;
    }

    private function parseItineraryHtmlV2()
    {
        $this->logger->notice(__METHOD__);
        $pnr = $this->http->FindSingleNode("//h2[contains(@class,'confirmation-code')]");
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $pnr), ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();
        $f->general()
            ->confirmation($pnr, 'Confirmation code');

        $passengers = $this->http->XPath->query('//div[@class="passenger-card"]');

        foreach ($passengers as $passengerNode) {
            $traveller = $this->http->FindSingleNode('./h2', $passengerNode);
            $f->general()->traveller($traveller);
            $account = $this->http->FindSingleNode('.//div[contains(@class,"passenger-frequent-flyer-number")]',
                $passengerNode, false, '/[\w\s]+\s+(\d+)/');

            if ($account) {
                $f->program()->account($account, false);
            }

            if ($ticket = $this->http->FindSingleNode('./p[contains(text(),"Ticket")]', $passengerNode, false,
                '/Ticket\s+(\d+)/')) {
                $f->issued()->ticket($ticket, false, $traveller);
            }
        }

        $segments = $this->http->XPath->query('//div[@class="reservation-itinerary"]/div[starts-with(@id,"init-")]');

        foreach ($segments as $segmentNode) {
            $details = $this->http->JsonLog(html_entity_decode($this->http->FindSingleNode('.//div[@class="itinerary-details"]/@data',
                $segmentNode)));

            $i = 0;

            foreach ($details as $detail) {
                $s = $f->addSegment();

                $i++;
                $s->airline()->name($detail->OperatingAirlineCode);
                $s->airline()->number($detail->OperatingFlightNumber);
                $s->departure()->code($detail->DepartureAirport);
                $s->arrival()->code($detail->ArrivalAirport);

                $rootSegmentXpath = "(.//div[@class='itinerary-details-flex-layout'])[$i]";
                $date = $this->http->FindSingleNode($rootSegmentXpath . '//div[contains(text(),"Departs")]/following-sibling::div[@class="itinerary-details-time-display"]', $segmentNode);
                $s->departure()->date2(str_replace('|', '', $date));
                $date = $this->http->FindSingleNode($rootSegmentXpath . '//div[contains(text(),"Arrives")]/following-sibling::div[@class="itinerary-details-time-display"]', $segmentNode);
                $s->arrival()->date2(str_replace('|', '', $date));

                $seats = $this->http->XPath->query($rootSegmentXpath . '//div[@class="itinerary-details-seating"]', $segmentNode);

                foreach ($seats as $seatNode) {
                    $s->extra()->cabin($this->http->FindSingleNode('.//div[@class="seating-cabin"]', $seatNode, false, '/(\w+) \([A-Z]+\)/'));
                    $s->extra()->bookingCode($this->http->FindSingleNode('.//div[@class="seating-cabin"]', $seatNode, false, '/\w+ \(([A-Z]+)\)/'));
                    $s->extra()->seats($this->http->FindNodes('.//span[@class="seat-number"]', $seatNode));
                }

                $tripDetails = $this->http->FindNodes('.//div[@class="itinerary-details-tripspan"]/div', $segmentNode);

                foreach ($tripDetails as $tripDetail) {
                    // 2h 44min
                    if ($this->http->FindPreg('/(?:\d+h|\d+min)/', false, $tripDetail)) {
                        $s->extra()->duration($tripDetail);
                    }
                    // Nonstop
                    elseif ($stop = $this->http->FindPreg('/^\s*(\d+) stop/', false, $tripDetail)) {
                        $s->extra()->stops($stop);
                    }
                    // 1023 miles
                    elseif ($miles = $this->http->FindPreg('/(\d+)\s+miles/', false, $tripDetail)) {
                        $s->extra()->miles($miles);
                    }
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryHtml()
    {
        $this->logger->notice(__METHOD__);

        $pnr = $this->http->FindSingleNode("//span[contains(text(),'Confirmation ')]/following-sibling::h2[1]");
        $passengers = array_map(function ($item) {
            return beautifulName($item);
        },
            $this->http->FindNodes('
            //div[contains(@class,"passenger-info")]//div[contains(@class,"vpnr-pax-name")] | 
            //div[contains(@id, "travelerDetails")]//dt[contains(text(),"Name:")]/following-sibling::dd[1] | 
            //div[contains(@id, "TravelerInfo")]//*[contains(text(),"Name:")]/following-sibling::text()[1]'));

        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $pnr), ['Header' => 3]);

        if ($error = $this->http->FindSingleNode("//p[contains(.,'flight schedules have changed in a way that impacts your upcoming trip, so we found the closest possible match to your original itinerary')]")) {
            $this->logger->error($error);

            return;
        }

        if ($error = $this->http->FindSingleNode("//table[@id='flightInformationTable']//div[contains(.,'There are no flights currently attached to this reservation')]")) {
            $this->logger->error($error);

            return;
        }
        $r = $this->itinerariesMaster->add()->flight();

        $r->general()
            ->confirmation($pnr, 'Confirmation code');

        if (!empty($passengers)) {
            $r->general()->travellers($passengers, true);
        }

        $ticketNumbers = $this->http->FindNodes('
        //div[contains(@class,"passenger-info")]//span[contains(text(),"E-ticket:")]/following-sibling::text()[1] | 
        //div[contains(@id, "TravelerInfo")]//*[contains(text(),"E-ticket")]/following-sibling::text()[1]');
        $ticketNumbers = array_values(array_filter($ticketNumbers, function ($s) {
            return !$this->http->FindPreg('/Not available/i', false, $s);
        }));

        if (!empty($ticketNumbers)) {
            $r->setTicketNumbers($ticketNumbers, false);
        }

        $accounts = $this->http->FindNodes('//*[abbr[@title="Mileage Plan Number"]]/following-sibling::dd[1] | //abbr[@title="Mileage Plan Number"]/following-sibling::text()[1]');

        foreach ($accounts as $account) {
            if ($temp = $this->http->FindPreg('/(\d+[\w\d]*)|[Award]{5}/', false, $account)) {
                $arrAccounts[] = $temp;
            }
        }

        if (isset($arrAccounts[0])) {
            $arrAccounts = array_values(array_unique($arrAccounts));
            $r->setAccountNumbers($arrAccounts, false);
        }

        $seats = [];
        $meals = [];
        $seatNodes = $this->http->XPath->query("//div[@class='seat-table-row']/div[@class='seat-table-cell'][1]");

        if ($seatNodes->length > 0) {
            $this->sendNotification('Check old seats // MI');

            foreach ($seatNodes as $node) {
                $route = $this->http->FindSingleNode(".", $node);
                $seat = $this->http->FindSingleNode("./following-sibling::div[1][@class='seat-table-cell']", $node,
                    false,
                    "/^\d+[A-z]$/");
                $meal = $this->http->FindSingleNode("./following-sibling::div[2][@class='seat-table-cell'][not(contains(.,'View options'))]",
                    $node);

                if (!empty($seat)) {
                    $seats[$route][] = $seat;
                }

                if (!empty($meal)) {
                    $meals[$route][] = $meal;
                }
            }
        }
        $seatNodes = $this->http->XPath->query("//table[@id='SeatInfo0']/tbody/tr");

        if ($seatNodes->length > 0) {
            foreach ($seatNodes as $node) {
                $route = $this->http->FindSingleNode("./td[1]", $node);
                $seat = $this->http->FindSingleNode("./td[2]", $node, false, "/^\d+[A-z]$/");
                $meal = $this->http->FindSingleNode("./td[3][not(contains(.,'View menu'))]", $node);

                if (!empty($seat)) {
                    $seats[$route][] = $seat;
                }

                if (!empty($meal)) {
                    $meals[$route][] = $meal;
                }
            }
        }

        $this->parsePayment($r);

        $segments = $this->http->XPath->query($xpath = "//table[@id='flightInformationTable']//tr[not(contains(.,'Departs'))][normalize-space()!=''][count(./td)=3]");

        if ($segments->length == 0) {
            $segments = $this->http->XPath->query($xpath = "//h2[contains(text(),'New schedule')]/following-sibling::table[@class='flightstable clear']//tr[not(contains(.,'Departs'))][normalize-space()!=''][count(./td)=3]");
        }
        $this->logger->debug($xpath);
        $this->logger->debug("Total segments found: " . $segments->length);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $flightText = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[3]", $segment);

            if ($this->http->FindPreg("/^\d+$/", false, $flightText)) {
                $s->airline()
                    ->noName()
                    ->number($flightText);
            } else {
                $s->airline()
                    ->name($this->http->FindPreg("/^(.+)\s+\d+$/", false, $flightText))
                    ->number($this->http->FindPreg("/^.+\s+(\d+)$/", false, $flightText));
            }
            $confAirline = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'{$s->getAirlineName()}')][./ancestor::*[1][contains(.,'confirmation code')]]/following-sibling::*[1]",
                null, false, "/^[\w+]{5,6}$/");

            if (!empty($confAirline)) {
                $s->airline()->confirmation($confAirline);
            }
            $operator = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Operated by')]", $segment, false, "/Operated by\s*(.+?)(?:\s+as\s+|$)/");

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }
            $cabin = $this->http->FindSingleNode("./td[1]/div[2]/span[1][contains(@class,'cabin')]", $segment);

            if ($bookingCode = $this->http->FindPreg("/^.+\s+\(([A-Z]{1,2})\)$/", false, $cabin)) {
                $s->extra()
                    ->bookingCode($bookingCode)
                    ->cabin($this->http->FindPreg("/^(.+)\s+\([A-Z]{1,2}\)$/", false, $cabin));
            } elseif ($cabin) {
                $s->extra()->cabin($cabin);
            }

            $depart = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Depart')]", $segment,
                false, "/Depart\s*(.+)/");

            $depDateStr = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Depart')]/following-sibling::div[1]",
                $segment);
            $depDate = $this->http->FindPreg('/\d+:\d+[ap]m\s*\w+,\s+(.+)$/i', false, $depDateStr);
            $weekday = $this->http->FindPreg('/\d+:\d+[ap]m\s*(\w+),\s+.+$/i', false, $depDateStr);
            $weekdayNumber = (int) date('N', strtotime($weekday));
            $depDate = EmailDateHelper::parseDateUsingWeekDay("$depDate, " . date('Y'), $weekdayNumber);
            // 11:59pm Tue, Nov 22
            $depTime = $this->http->FindPreg('/(\d+:\d+[ap]m)\s*\w{3},.+$/i', false, $depDateStr);
            $depDateTime = strtotime($depTime, $depDate);

            $s->departure()
                ->name($this->http->FindPreg("/^(.+)\s+\([A-Z]{3}\)$/", false, $depart))
                ->code($this->http->FindPreg("/^.+\s+\(([A-Z]{3})\)$/", false, $depart))
                ->date($depDateTime);

            $rowWithStop = $this->http->FindSingleNode("./td[1]/div[2]", $segment);
            $stopAirports = $this->http->FindNodes(".//div[starts-with(@id,'FlightDetailInfo_')]/div[contains(normalize-space(),'Stop')]",
                $segment,
                "/Stop(?:\/plane change)? in\s*(.+)/");

            if ($this->http->FindPreg("/(no[n\- ]*stop)/i", false, $rowWithStop)
                || !$this->http->FindPreg("/\bstops?\b/i", false, $rowWithStop)
                || empty($stopAirports)
            ) {
                $this->logger->debug('[parse 1 segment]');
                $stops = $this->http->FindPreg("/(\d+) stop(?:\/plane change|\s*\|)/", false, $rowWithStop);

                if (empty($stops)) {
                    $stops = 0;
                }
                $s->extra()
                    ->stops($stops)
                    ->duration($this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Duration')]",
                        $segment, false, "/Duration:\s*(\d.+)/"), false, true)
                    ->aircraft($this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Aircraft')]/following-sibling::div[1]/div[1]",
                        $segment), true, true)
                    ->miles($this->http->FindSingleNode("./td[1]/div[3]", $segment, false,
                        "/Distance:\s*(\d.+?)\s*\|/"), false, true);

                $arrive = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Arrive')]", $segment,
                    false, "/Arrive\s*(.+)/");

                $arrDateStr = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Arrive')]/following-sibling::div[1]",
                    $segment);
                $this->logger->debug('arrDateStr: ' . $arrDateStr);
                $arrDate = $this->http->FindPreg('/\d+:\d+[ap]m\s*\w+,\s+(.+)$/i', false, $arrDateStr);
                $weekday = $this->http->FindPreg('/\d+:\d+[ap]m\s*(\w+),\s+.+$/i', false, $arrDateStr);
                $weekdayNumber = (int) date('N', strtotime($weekday));
                $arrDate = EmailDateHelper::parseDateUsingWeekDay("$arrDate, " . date('Y'), $weekdayNumber);
                $arrTime = $this->http->FindPreg('/(\d+:\d+[ap]m)\s*\w{3},.+$/i', false, $arrDateStr);
                $arrDateTime = strtotime($arrTime, $arrDate);

                $s->arrival()
                    ->name($this->http->FindPreg("/^(.+)\s+\([A-Z]{3}\)$/", false, $arrive))
                    ->code($this->http->FindPreg("/^.+\s+\(([A-Z]{3})\)$/", false, $arrive))
                    ->date($arrDateTime);

                if ($s->getDepCode() && $s->getArrCode()) {
                    if (isset($seats["{$s->getDepCode()}-{$s->getArrCode()}"])) {
                        $s->extra()->seats(array_unique($seats["{$s->getDepCode()}-{$s->getArrCode()}"]));
                    }

                    if (isset($meals["{$s->getDepCode()}-{$s->getArrCode()}"])) {
                        $s->extra()->meals(trim($meals["{$s->getDepCode()}-{$s->getArrCode()}"], " *"));
                    }
                }
            } else {
                $this->logger->debug('[parse 2+ segments]');
                $stops = $this->http->FindPreg("/(\d+) stops?(?:\/plane change|\s*\|)/", false, $rowWithStop);
                $stopAirport = array_shift($stopAirports);

                $s->arrival()
                    ->name($this->http->FindPreg("/^(.+)\s+\([A-Z]{3}\)$/", false, $stopAirport))
                    ->code($this->http->FindPreg("/^.+\s+\(([A-Z]{3})\)$/", false, $stopAirport))
                    ->noDate();

                $arrive = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Arrive')]", $segment,
                    false, "/Arrive\s*(.+)/");

                $arrDateStr = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(normalize-space(),'Arrive')]/following-sibling::div[1]",
                    $segment);
                $this->logger->debug('arriveStr: ' . $arrDateStr);
                $arrDate = $this->http->FindPreg('/\d+:\d+[ap]m\s*\w+,\s+(.+)$/i', false, $arrDateStr);
                $weekday = $this->http->FindPreg('/\d+:\d+[ap]m\s*(\w+),\s+.+$/i', false, $arrDateStr);
                $weekdayNumber = (int) date('N', strtotime($weekday));
                $arrDate = EmailDateHelper::parseDateUsingWeekDay("$arrDate, " . date('Y'), $weekdayNumber);
                $arrTime = $this->http->FindPreg('/(\d+:\d+[ap]m)\s*\w{3},.+$/i', false, $arrDateStr);
                $arrDateTime = strtotime($arrTime, $arrDate);
                $aircraft = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(translate(normalize-space(),' ',''),'{$s->getDepCode()}-{$s->getArrCode()}')][./div[1][contains(.,'Aircraft')]]/following-sibling::div[1]/div[1]",
                    $segment);
                $meal = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(translate(normalize-space(),' ',''),'{$s->getDepCode()}-{$s->getArrCode()}')][./div[last()][contains(.,'Meal')]]/following-sibling::div[1]/div[last()]",
                    $segment);
                $s->extra()
                    ->aircraft($aircraft)
                    ->meal(trim($meal, " *"));

                if ((int) $stops > 1) {
                    $dDate = $this->http->FindSingleNode("./td[2]", $segment, false, "/\([A-Z]{3}\)\s*(.+)\s+\d+:\d+/");
                    $aDate = $this->http->FindSingleNode("./td[3]", $segment, false, "/\([A-Z]{3}\)\s*(.+)\s+\d+:\d+/");

                    if ($dDate !== $aDate) {
                        $this->logger->error("Skip reservation. 2+ Stops overnight");
                        $this->itinerariesMaster->removeItinerary($r);

                        return;
                    }
                }

                foreach ($stopAirports as $rowAirport) {
                    $s1 = $r->addSegment();
                    $s1->airline()
                        ->name($s->getAirlineName())
                        ->number($s->getFlightNumber());
                    $s1->departure()
                        ->name($this->http->FindPreg("/^(.+)\s+\([A-Z]{3}\)$/", false, $stopAirport))
                        ->code($this->http->FindPreg("/^.+\s+\(([A-Z]{3})\)$/", false, $stopAirport))
                        ->noDate()
                        ->day($arrDate);
                    $s1->arrival()
                        ->name($this->http->FindPreg("/^(.+)\s+\([A-Z]{3}\)$/", false, $rowAirport))
                        ->code($this->http->FindPreg("/^.+\s+\(([A-Z]{3})\)$/", false, $rowAirport))
                        ->noDate()
                        ->day($arrDate);

                    $aircraft = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(translate(normalize-space(),' ',''),'{$s1->getDepCode()}-{$s1->getArrCode()}')][./div[1][contains(.,'Aircraft')]]/following-sibling::div[1]/div[1]",
                        $segment);
                    $meal = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(translate(normalize-space(),' ',''),'{$s1->getDepCode()}-{$s1->getArrCode()}')][./div[last()][contains(.,'Meal')]]/following-sibling::div[1]/div[last()]",
                        $segment);
                    $s1->extra()
                        ->aircraft($aircraft)
                        ->meal(trim($meal, " *"));
                    $stopAirport = $rowAirport;
                }
                $s1 = $r->addSegment();
                $s1->airline()
                    ->name($s->getAirlineName())
                    ->number($s->getFlightNumber());
                $s1->departure()
                    ->name($this->http->FindPreg("/^(.+)\s+\([A-Z]{3}\)$/", false, $stopAirport))
                    ->code($this->http->FindPreg("/^.+\s+\(([A-Z]{3})\)$/", false, $stopAirport))
                    ->noDate();
                $s1->arrival()
                    ->name($this->http->FindPreg("/^(.+)\s+\([A-Z]{3}\)$/", false, $arrive))
                    ->code($this->http->FindPreg("/^.+\s+\(([A-Z]{3})\)$/", false, $arrive))
                    ->date($arrDateTime);
                $aircraft = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(translate(normalize-space(),' ',''),'{$s1->getDepCode()}-{$s1->getArrCode()}')][./div[1][contains(.,'Aircraft')]]/following-sibling::div[1]/div[1]",
                    $segment);
                $meal = $this->http->FindSingleNode(".//div[starts-with(@id,'FlightDetailInfo_')]/div[starts-with(translate(normalize-space(),' ',''),'{$s1->getDepCode()}-{$s1->getArrCode()}')][./div[last()][contains(.,'Meal')]]/following-sibling::div[1]/div[last()]",
                    $segment);
                $s1->extra()
                    ->aircraft($aircraft)
                    ->meal(trim($meal, " *"));

                if ($s->getDepCode() && $s1->getArrCode()) {
                    if (isset($seats["{$s->getDepCode()}-{$s1->getArrCode()}"])) {
                        $s->extra()->seats($seats["{$s->getDepCode()}-{$s1->getArrCode()}"]);
                        $s1->extra()->seats($seats["{$s->getDepCode()}-{$s1->getArrCode()}"]);
                    }

                    if (isset($meals["{$s->getDepCode()}-{$s->getArrCode()}"])) {
                        if (empty($s->getMeals())) {
                            $s->extra()->meals($meals["{$s->getDepCode()}-{$s1->getArrCode()}"]);
                        }

                        if (empty($s1->getMeals())) {
                            $s1->extra()->meals(trim($meals["{$s->getDepCode()}-{$s1->getArrCode()}"], " *"));
                        }
                    }
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function parseChangedItineraryV2($date = null, $pax = [])
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        $xpath = $this->http->XPath;
        // RecordLocator
        $conf = $this->http->FindSingleNode("//div[normalize-space(@class)='confirmCode']");
        $flight->addConfirmationNumber($conf, 'Confirmation code', true);
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);

        if (!empty($pax)) {
            foreach ($pax as $p) {
                $flight->addTraveller($p->firstName . ' ' . $p->lastName, true);
            }
        }
        // Segments
        $nodes = $xpath->query("//h2[contains(text(), 'New Schedule')]/following-sibling::table//tr[td[3]]");
        $this->logger->debug("Total {$nodes->length} segments were found");
        $departsDate = $date;
        $this->logger->debug("departsDate: {$departsDate} ");

        if (!isset($departsDate) || empty($departsDate)) {
            $departsDate = time();
        }
        $this->logger->debug("departsDate: {$departsDate}");

        for ($i = 0; $i < $nodes->length; $i++) {
            $seg = $flight->addSegment();
            // FlightNumber
            $flightNumber = $this->http->FindSingleNode("td[1]/div[1]/span[2]", $nodes->item($i), true, "/\s([\d]+)\s*/ims");

            if (empty($flightNumber)) {
                $flightNumber = $this->http->FindSingleNode("td[1]/div[1]/span[1]", $nodes->item($i), true, "/\s([\d]+)\s*/ims");
            }
            $seg->setFlightNumber($flightNumber);
            // AirlineName
            $airline = $this->http->FindSingleNode("td[1]/div[1]/span[1]/img/@alt", $nodes->item($i));

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("td[1]/div[1]/div[1]", $nodes->item($i), true, '/Operated by\s*(.+)\sas/ims');
            }

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("td[1]/div[1]/span[1]", $nodes->item($i), true, "/\s*(.+){$flightNumber}/ims");
            }
            $seg->setAirlineName($airline);
            // Cabin
            $seg->setCabin($this->http->FindSingleNode("td[1]/div[2]/span[1]", $nodes->item($i), true, "/([^\(]+)\(/ims"), false, true);
            // BookingClass
            $seg->setBookingCode($this->http->FindSingleNode("td[1]/div[2]/span[1]", $nodes->item($i), true, "/\(([^\)]+)/ims"), false, true);
            // DepCode
            $seg->setDepCode($this->http->FindSingleNode("td[2]", $nodes->item($i), true, "/\((\w{3})\)/ims"));
            // DepName
            $seg->setDepName(trim($this->http->FindSingleNode("td[2]", $nodes->item($i), true, "/^([^\(]+)/ims")));
            // DepDate
            $depDateInfo = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $date1 = $this->http->FindPreg("/[a-zA-Z]{3}\s*\,\s*([a-zA-Z]{3}\s*\d+)/ims", false, $depDateInfo);
            $time1 = $this->http->FindSingleNode("td[2]/strong", $nodes->item($i));

            if (!$time1) {
                $time1 = $this->http->FindPreg('/(\d+:\d+\s*(?:pm|am)?)/i', false, $depDateInfo);
            }
            $depDate = null;

            if ($date1 && $time1) {
                $depDate = $date1 . ' ' . $time1;
                $depDate = EmailDateHelper::parseDateRelative($depDate, $departsDate);

                if ($depDate) {
                    $seg->setDepDate($depDate);
                }
            }
            $this->logger->info(var_export([
                'depDateInfo' => $depDateInfo,
                'date1'       => $date1,
                'time1'       => $time1,
                'depDate'     => $depDate,
            ], true), ['pre' => true]);
            // ArrCode
            $seg->setArrCode($this->http->FindSingleNode("td[3]", $nodes->item($i), true, "/\((\w{3})\)/ims"));
            // ArrName
            $seg->setArrName(trim($this->http->FindSingleNode("td[3]", $nodes->item($i), true, "/^([^\(]+)/ims")));
            // ArrDate
            $arrDateInfo = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            $date2 = $this->http->FindPreg("/[a-zA-Z]{3}\s*\,\s*([a-zA-Z]{3}\s*\d+)/ims", false, $arrDateInfo);
            $time2 = $this->http->FindSingleNode("td[3]/strong", $nodes->item($i));

            if (!$time2) {
                $time2 = $this->http->FindPreg('/(\d+:\d+\s*(?:pm|am)?)/i', false, $arrDateInfo);
            }
            $arrDate = null;

            if ($date2 && $time2) {
                $arrDate = $date2 . ' ' . $time2;
                $arrDate = EmailDateHelper::parseDateRelative($arrDate, $departsDate);

                if ($arrDate) {
                    $seg->setArrDate($arrDate);
                }
            }
            $this->logger->info(var_export([
                'arrDateInfo' => $arrDateInfo,
                'date2'       => $date2,
                'time2'       => $time2,
                'arrDate'     => $arrDate,
            ], true), ['pre' => true]);
            // Aircraft
            $seg->setAircraft($this->http->FindSingleNode(".//li[contains(text(), 'Aircraft:')]", $nodes->item($i), true, "/:\s*([^<]+)/ims"), false, true);
        }// for ($i = 0; $i < $nodes->length; $i++)

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

        return true;
    }

    private function parsePayment($flight)
    {
        $this->logger->notice(__METHOD__);
        $passengerNumber = $this->http->FindPreg('/Flight\s+total\s+for\s+(\d+)\s+passengers/i');

        if ($passengerNumber > 1) {
            // TotalCharge and Currency
            $priceStr = $this->http->FindPreg('/Flight\s+total\s+for\s+\d+\s+passengers?:\s+(.+?)\n/i');
            $totalStr = $this->http->FindPreg('/miles\s+\+\s+(.+?)$/', false, $priceStr) ?: $priceStr;

            if ($totalStr) {
                $fee = $this->http->FindPreg('/Booking\s+Fee:\s+.+?([\d.,]+)/i');
                $total = PriceHelper::cost($this->http->FindPreg('/([\d.,]+)/', false, $totalStr));

                if (!empty($fee)) {
                    $total += PriceHelper::cost($fee);
                }
                $flight->price()->total($total, false, true);
                $flight->price()->currency($this->currency($totalStr), false, true);
            }
            // SpentAwards
            $spent = $this->http->FindPreg('/A total of ([\d.,]+ miles) has been deducted/i');

            if (!$spent) {
                $spent = $this->http->FindPreg('/\s*(.+?\s+miles)\s+\+/', false, $priceStr);
            }
            $flight->price()->spentAwards($spent, false, true);
        } else {
            // TotalCharge
            $path = "//span[@class = 'totalPrice']";
            $totalStr = $this->http->FindSingleNode($path, null, true, "/[\d.\,]+/ims");

            if (!isset($totalStr)) {
                $path = "//span[contains(text(), 'Total:')]/following-sibling::span[1]";
                $totalStr = $this->http->FindSingleNode($path, null, true, "/[\d.\,]+/ims");
            }

            if (!isset($totalStr)) {
                $path = "//div[h5[contains(text(), 'Total')]]/following-sibling::div[@class = 'amount']";
                $totalStr = $this->http->FindSingleNode($path, null, true, "/[\d.\,]+/ims");
            }
            // Currency
            $currencyStr = $this->http->FindSingleNode($path, null, true, '/([A-Z]{3}|\$)/');

            if ($currencyStr === '$') {
                $currencyStr = 'USD';
            }

            if (stripos($this->http->FindSingleNode($path), 'Miles +') !== false) {
                $totalStr = $this->http->FindSingleNode($path, null, true, "/([\d.\,]+)$/ims");
                $spent = $this->http->FindSingleNode($path, null, true, "/[\d.\,]+\s*Miles/ims");
                $flight->price()->spentAwards($spent, false, true);
            }
            $total = PriceHelper::cost($totalStr);
            $flight->price()->total($total, false, true);
            $currency = $this->currency($currencyStr);
            $flight->price()->currency($currency, false, true);
            // Tax
            $taxStr = $this->http->FindSingleNode("//div[h6[contains(text(), 'Taxes and fees')]]/following-sibling::div[1]", null, true, "/[\d.\,]+/ims");

            if (!isset($taxStr)) {
                $taxStr = $this->http->FindSingleNode("//span[contains(text(), 'Taxes, fees and charges:')]/following-sibling::span[1]", null, true, "/[\d.\,]+/ims");
            }
            $tax = PriceHelper::cost($taxStr);

            if ($tax) {
                $flight->price()->tax($tax);
            }
            // BaseFare
            $costStr = $this->http->FindSingleNode("//div[h6[contains(text(), 'Fare')]]/following-sibling::div[1]") ?: '';

            if (!$this->http->FindPreg('/miles/i', false, $costStr)) {
                $costStr = $this->http->FindPreg('/([\d.\,]+)/', false, $costStr);
            } else {
                $costStr = null;
            }
            $cost = PriceHelper::cost($costStr);
            $flight->price()->cost($cost, false, true);
        }
    }

    private function parseItineraryV2($date = null)
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindSingleNode('//div[contains(@class, "advisoryTextNoIcon") and contains(text(), "There are no flights currently attached to this reservation.")]')) {
            $this->logger->error('Skipping: ' . $msg);

            return $msg;
        }

        $xpath = $this->http->XPath;

        if (!$this->http->FindSingleNode("//div[@id='confirmationCode']") && !$this->http->FindSingleNode("//div[@class='confirmCode']")) {
            return false;
        }

        $flight = $this->itinerariesMaster->createFlight();
        // RecordLocator
        $conf = $this->http->FindSingleNode("//div[normalize-space(@class)='confirmCode']");
        $flight->addConfirmationNumber($conf, 'Confirmation code', true);
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);
        $isChanged = !empty($this->http->FindSingleNode("//text()[contains(normalize-space(),'Confirm Your Schedule Change')]"));
        // ConfirmationNumbers
        $confirmInfo = $this->http->FindSingleNode('//div[contains(@class, "confirmCodeWrapCarHotel")]');
        preg_match_all('/code:\s*(\w+)/i', $confirmInfo, $confirmationNumbers);

        if ($confirmInfo && $confirmationNumbers) {
            $confs = array_filter($confirmationNumbers[1], function ($num) use (&$conf) {
                return $num !== $conf;
            });
            $confs = array_values(array_unique($confs));

            foreach ($confs as $num) {
                $flight->addConfirmationNumber($num);
            }
        }
        // Passengers
        $passengers = array_map(function ($item) {
            return beautifulName($item);
        }, $this->http->FindNodes('//div[normalize-space(@class)="vpnr-pax-name"] | //div[contains(@id, "travelerDetails")]//dt[contains(text(),"Name:")]/following-sibling::dd[1] | //div[contains(@id, "TravelerInfo")]//*[contains(text(),"Name:")]/following-sibling::text()[1]'));

        if (!empty($passengers) || !$isChanged) {
            $flight->setTravellers($passengers);
        }
        // TicketNumbers
        $ticketNumbers = $this->http->FindNodes('//div[contains(@id, "TravelerInfo")]//*[contains(text(),"E-ticket")]/following-sibling::text()[1]');
        $ticketNumbers = array_values(array_filter($ticketNumbers, function ($s) {
            return !$this->http->FindPreg('/Not available/i', false, $s);
        }));

        if (!empty($ticketNumbers) || !$isChanged) {
            $flight->setTicketNumbers($ticketNumbers, false);
        }
        // Seats
        $flightSeats = $this->http->FindNodes('//div[starts-with(@id, "Seats")]');
        // AccountNumber
        $accounts = $this->http->FindNodes('//*[abbr[@title="Mileage Plan Number"]]/following-sibling::dd[1] | //abbr[@title="Mileage Plan Number"]/following-sibling::text()[1]');

        foreach ($accounts as $account) {
            if ($temp = $this->http->FindPreg('/(\d+[\w\d]*)|[Award]{5}/', false, $account)) {
                $arrAccounts[] = $temp;
            }
        }

        if (isset($arrAccounts[0])) {
            $arrAccounts = array_values(array_unique($arrAccounts));
            $flight->setAccountNumbers($arrAccounts, false);
        }
        // Payment Info
        $this->parsePayment($flight);
        // TripSegments
        if ($isChanged) {
            $nodes = $xpath->query("//a[contains(text(), 'Details')]/preceding-sibling::span/ancestor::tr[1][./ancestor::table[1]/preceding::text()[normalize-space()='New Schedule']]");
            $details = $xpath->query("//a[contains(text(), 'Details')]/preceding-sibling::span/ancestor::tr[1][./ancestor::table[1]/preceding::text()[normalize-space()='New Schedule']]/following-sibling::tr[1]");
        } else {
            $nodes = $xpath->query("//a[contains(text(), 'Details')]/preceding-sibling::span/ancestor::tr[1]");
            $details = $xpath->query("//a[contains(text(), 'Details')]/preceding-sibling::span/ancestor::tr[1]/following-sibling::tr[1]");
        }
        $this->logger->debug("Total {$nodes->length} segments were found");
        $this->logger->debug("Total {$details->length} segment details were found");
        $this->logger->debug("reservation has " . ($isChanged ? '' : 'not ') . 'changed');
        $departsDate = $date;
        $this->logger->debug("departsDate: {$departsDate} ");

        if (!isset($departsDate) || empty($departsDate)) {
            $departsDate = time();
        }
        $this->logger->debug("departsDate: {$departsDate} ");

        for ($i = 0; $i < $nodes->length; $i++) {
            $seg = $flight->addSegment();
            // FlightNumber
            $flightNumber = $this->http->FindSingleNode("td[1]/div[1]/span[img]/following-sibling::span[1]", $nodes->item($i), true, "/\b([\d]+)\b/ims");

            if (!isset($flightNumber)) {
                $flightNumber = $this->http->FindSingleNode("td[1]/div[1]/span/following-sibling::span[1]", $nodes->item($i), true, "/\b([\d]+)\b/ims");
            }
            $seg->setFlightNumber($flightNumber);
            // AirlineName
            $airline = $this->http->FindSingleNode("td[1]/div[1]/span/img/@alt", $nodes->item($i));

            if (empty($airline)) {
                $flightInfo = $this->http->FindSingleNode("td[1]/div[1]/span[contains(., '{$flightNumber}') and not(@class = 'sr-only')]", $nodes->item($i));
                $airline = $this->http->FindPreg("/\s*(.+){$flightNumber}/ims", false, $flightInfo);

                if ($flightInfo && !$airline) {
                    $seg->setNoAirlineName(true);
                }
            }

            if (!empty($airline)) {
                $seg->setAirlineName($airline);
            }
            // Cabin
            $cabinInfo = $this->http->FindSingleNode("td[1]/div[2]/span[1]", $nodes->item($i));
            $seg->setCabin($this->http->FindPreg("/^([^\(]+)[(|$]/ims", false, $cabinInfo), false, true);
            // BookingClass
            $seg->setBookingCode($this->http->FindPreg("/\(([^\)]+)/ims", false, $cabinInfo), false, true);
            // Operator
            $operatorInfo = $this->http->FindSingleNode('./td[1]/div[4]/div[1]', $nodes->item($i));
            $operator = $this->http->FindPreg('/Operated by (.+?)$/', false, $operatorInfo);

            if ($operator) {
                $operator = $this->http->FindPreg('/\s+as\s+(.+)/', false, $operator) ?: $operator;
                $operator = $this->http->FindPreg('/(.+?)\s+Arrive/', false, $operator) ?: $operator;
            }

            if (!$operator) {
                $operator = $this->http->FindSingleNode("td[1]/div[1]/div[1]", $nodes->item($i), true, '/Operated by\s*(.+)\sas/ims');
            }

            if ($operator) {
                $seg->setOperatedBy($operator, false, true);
            }
            // DepCode
            $seg->setDepCode($this->http->FindSingleNode("td[2]", $nodes->item($i), true, "/\((\w{3})\)/ims"));
            // DepName
            $seg->setDepName(trim($this->http->FindSingleNode("td[2]", $nodes->item($i), true, "/^([^\(]+)/ims")));
            // DepDate
            $depDateInfo = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $date1 = $this->http->FindPreg("/[a-zA-Z]{3}\s*\,\s*([a-zA-Z]{3}\s*\d+)/ims", false, $depDateInfo);
            $time1 = $this->http->FindSingleNode("td[2]/strong", $nodes->item($i));

            if (!$time1) {
                $time1 = $this->http->FindPreg('/(\d+:\d+\s*(?:pm|am)?)/i', false, $depDateInfo);
            }
            $depDate = null;

            if ($date1 && $time1) {
                $depDate = $date1 . ' ' . $time1;
                $depDate = EmailDateHelper::parseDateRelative($depDate, $departsDate);
                $seg->setDepDate($depDate);
            }
            $this->logger->info(var_export([
                'depDateInfo' => $depDateInfo,
                'date1'       => $date1,
                'time1'       => $time1,
                'depDate'     => $depDate,
            ], true), ['pre' => true]);

            if (!$depDate) {
                $this->sendNotification('alaskaair - check itinerary dates');
            }
            // ArrCode
            $seg->setArrCode($this->http->FindSingleNode("td[3]", $nodes->item($i), true, "/\((\w{3})\)/ims"));
            // ArrName
            $seg->setArrName(trim($this->http->FindSingleNode("td[3]", $nodes->item($i), true, "/^([^\(]+)/ims")));
            // ArrDate
            $arrDateInfo = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            $date2 = $this->http->FindPreg("/[a-zA-Z]{3}\s*\,\s*([a-zA-Z]{3}\s*\d+)/ims", false, $arrDateInfo);
            $time2 = $this->http->FindSingleNode("td[3]/strong", $nodes->item($i));

            if (!$time2) {
                $time2 = $this->http->FindPreg('/(\d+:\d+\s*(?:pm|am)?)/i', false, $arrDateInfo);
            }
            $arrDate = null;

            if ($date2 && $time2) {
                $arrDate = $date2 . ' ' . $time2;
                $arrDate = EmailDateHelper::parseDateRelative($arrDate, $departsDate);

                if ($arrDate) {
                    $seg->setArrDate($arrDate);
                }
            }
            $this->logger->info(var_export([
                'arrDateInfo' => $arrDateInfo,
                'date2'       => $date2,
                'time2'       => $time2,
                'arrDate'     => $arrDate,
            ], true), ['pre' => true]);
            // Seats
            $arrSeats = [];

            for ($p = 0; $p < count($passengers); $p++) {
                if (isset($flightSeats[$p])) {
                    $seatsF = explode(',', $flightSeats[$p]);

                    if (isset($seatsF[$i])) {
                        $arrSeats[] = trim($seatsF[$i]);
                    }
                }// if (isset($flightSeats[$p]))
            }// for ($p = 0; $p < count($passengers); $p++)
            $depCode = $seg->getDepCode();
            $arrCode = $seg->getArrCode();

            if (isset($arrSeats[0])) {
                $seats = $arrSeats;
            } else {
                $seats = $this->http->FindNodes("//div[contains(text(), '{$depCode}-{$arrCode}')]/following-sibling::div[contains(@class, 'seat-table-cell')]");
            }
            $seats = array_filter($seats, function ($s) {
                return $s !== '--' && !$this->http->FindPreg('/manage|assigned|view/i', false, $s);
            });
            $seats = array_values(array_unique($seats));
            $seg->setSeats($seats);
            // Stops
            $stops = count($this->http->FindNodes("td[contains(., 'Stop in')]", $nodes->item($i)));

            if ($stops > 0) {
                $seg->setStops($stops);
            }
            $stopInfo = $this->http->FindSingleNode('./td[1]/div[2]', $nodes->item($i));

            if ($this->http->FindPreg('/Nonstop/', false, $stopInfo)) {
                $seg->setStops(0);
            }
            // Duration
            $duration = null;

            if ($details->length > 0) {
                $duration = str_replace(['ours', 'inutes'], '', $this->http->FindSingleNode("td[1]", $details->item($i), true, "/Duration\s*:\s*([^\|]+)\s*(?:\||Aircraft)/ims"));
            }

            if (!$duration) {
                $durationInfo = $this->http->FindSingleNode('./td[1]//div[contains(@class,"flightDetailsContainer")]//text()[contains(.,"Duration")]', $nodes->item($i), false, "/Duration:\s*(.+)/");
                $dur = str_replace(['ours', 'inutes'], '', $durationInfo);
                $dur = preg_replace('/\s{2,}/', ' ', $dur);
                $duration = $dur;
            }

            if ($this->http->FindPreg("/\d+/", false, $duration)) {
                $seg->setDuration($duration, true);
            }
            // TraveledMiles
            $miles = null;

            if ($details->length > 0) {
                $miles = $this->http->FindSingleNode("td[1]", $details->item($i), true, "/Distance\s*:\s*([\d\.\,\s]+)/ims");
            }

            if (!$miles) {
                $milesInfo = $this->http->FindSingleNode('./td[1]/div[3]', $nodes->item($i));
                $miles = $this->http->FindPreg('/Distance:\s*([\d,]+\s*mi)/', false, $milesInfo);
            }
            $seg->setMiles($miles, false, true);
            // Meal
            $meal = $this->http->FindSingleNode(".//li[contains(text(), 'Meal:')]", $nodes->item($i), true, "/:\s*([^<]+)/ims");

            if (!$meal) {
                $cnt = count($this->http->FindNodes("./td[1]//div[contains(@class,'flightDetailsContainer')]/descendant::text()[contains(.,'Meal')]/ancestor::div[1]/preceding-sibling::div", $nodes->item($i))) + 1;
                $meal = $this->http->FindSingleNode("./td[1]//div[contains(@class,'flightDetailsContainer')]/descendant::text()[contains(.,'Meal')]/ancestor::div[2]/following-sibling::div[1]/div[{$cnt}]", $nodes->item($i));
            }
            $seg->addMeal($meal, false, true);
            // Aircraft
            $aircraft = $this->http->FindSingleNode("(.//li[contains(text(), 'Aircraft:')])[1]", $nodes->item($i), true, "/:\s*([^<]+)/ims");

            if (!$aircraft) {
                $cnt = count($this->http->FindNodes("./td[1]//div[contains(@class,'flightDetailsContainer')]/descendant::text()[contains(.,'Aircraft')]/ancestor::div[1]/preceding-sibling::div", $nodes->item($i))) + 1;
                $aircraft = $this->http->FindSingleNode("./td[1]//div[contains(@class,'flightDetailsContainer')]/descendant::text()[contains(.,'Aircraft')]/ancestor::div[2]/following-sibling::div[1]/div[{$cnt}]", $nodes->item($i));
            }
            $seg->setAircraft($aircraft, false, true);
        }// for ($i = 0; $i < $nodes->length; $i++)

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

        return true;
    }

    private function handleCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $captcha = $this->parseCaptcha();

        if (!$captcha) {
            return false;
        }
        $uuid = $this->http->FindPreg('/var uuid = "([\w\-]+)"/');
        $name = $this->http->FindPreg('/var name = "([\w\-]+)"/');
        $this->logger->debug("uuid: {$uuid}");
        $this->logger->debug("name: {$name}");

        $this->sendNotification("alaskaair. reCaptcha");

        $params = json_encode([
            'r' => $captcha,
            'v' => '',
            'u' => $uuid,
        ]);
        $this->http->setCookie('_pxvid', $uuid);
        $this->http->setCookie("_px2", base64_encode($params));

        $this->http->RetryCount = 0;
        $current = $this->http->currentUrl();
        $this->http->GetURL(sprintf("https://www.alaskaair.com/px/captcha/?pxCaptcha=%s", urlencode($params)));
        $this->http->RetryCount = 2;

        $this->http->GetURL("https://ipinfo.io/json"); //todo
        $this->http->JsonLog();

        $this->http->GetURL($current);

        return true;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $response = $this->getHistory();

        if (!$response) {
            return [];
        }

        $activities = $response->activities ?? [];

        foreach ($activities as $row) {
            // Activity Date
            $dateStr = $row->activityDate;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }

            $result[$startIndex]['Activity Date'] = $postDate;

            $desc = [
                $row->partnerName ?? '',
                $row->solarMarketingFlight ?? '',
                $row->productName ?? '',
            ];

            $result[$startIndex]['Activity Type'] = Html::cleanXMLValue(implode(' ', $desc));
            $result[$startIndex]['Status'] = $row->status;
            $result[$startIndex]['Miles'] = $row->miles;
            $result[$startIndex]['Bonus'] = $row->bonus;
            $result[$startIndex]['Total'] = $row->total;
            $startIndex++;
        }// foreach ($response as $row)

        return $result;
    }
}
