<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAgoda extends TAccountChecker
{
    use ProxyList;
    use OtcHelper;

    public const PROFILE_PAGE_URL = 'https://www.agoda.com/account/profile.html';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptchaVultr());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::PROFILE_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (!strstr($this->AccountFields['Login'], '@')) {
            throw new CheckException('Incorrect login. Agoda Rewards recently made a change to their login form. You now need to log in using your email address instead of your account number. Please update your credentials in order to fix this error.', ACCOUNT_INVALID_PASSWORD);
        }

//        $this->http->GetURL("https://www.agoda.com");
        $this->http->GetURL("https://www.agoda.com/account/signin.html?returnurl=/account/profile.html");
        $token =
            $this->http->FindSingleNode("//input[@id = 'requestVerificationToken']/@value")
            ?? $this->http->FindPreg("/reactHeader.verificationToken\s*=\s*\"([^\"]+)/")
        ;

        if (!$token) {
            /*
            sleep(5);
            $this->http->GetURL("https://www.agoda.com");
            $token =
                $this->http->FindSingleNode("//input[@id = 'requestVerificationToken']/@value")
                ?? $this->http->FindPreg("/reactHeader.verificationToken\s*=\s*\"([^\"]+)/")
            ;

            if ($token) {
                $this->sendNotification("token was found // RR");
            }

            if ($this->attempt <= 3) {
                throw new CheckRetryNeededException(3);
            }
            */

            return $this->checkErrors();
        }
        $this->logger->debug("Token => " . $token);

        $data = [
            "credentials" => [
                "password" => $this->AccountFields['Pass'],
                "authType" => "email",
                "username" => $this->AccountFields['Login'],
            ],
            "captchaEnabled" => true,
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json; charset=utf-8",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.agoda.com/ul/api/v1/signin', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        // reCaptcha
        $response = $this->http->JsonLog();

        if (isset($response->captchaInfo->captchaType) && $response->captchaInfo->captchaType == 'arkose') {
            $this->logger->warning('Not implemented');
            /*$captcha = $this->parseFunCaptcha($response->captchaInfo->apiKey);

            if ($captcha === false) {
                return false;
            }

            $data["captchaVerifyInfo"] = [
                "captchaResult" => [
                    "recaptchaToken" => $captcha,
                ],
                "captchaType"   => "recaptcha",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.agoda.com/ul/api/v1/signin', json_encode($data), $headers);
            $this->http->RetryCount = 2;*/
        }

        if (isset($response->captchaInfo->captchaType) && $response->captchaInfo->captchaType == 'recaptcha') {
            $captcha = $this->parseReCaptcha($response->captchaInfo->apiKey ?? '6LfGHMcZAAAAAAN-k_ejZXRAdcFwT3J-KK6EnzBE');

            if ($captcha === false) {
                return false;
            }

            $data["captchaVerifyInfo"] = [
                "captchaResult" => [
                    "recaptchaToken" => $captcha,
                ],
                "captchaType"   => "recaptcha",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.agoda.com/ul/api/v1/signin', json_encode($data), $headers);
            $this->http->RetryCount = 2;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, we were unable to service your request
        if ($message = $this->http->FindPreg("/(Sorry\, we were unable to service your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 500 Internal Server Error
        if ($message = $this->http->FindSingleNode("//*[contains(text(), '500 Internal Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if ($this->http->FindPreg("/\"message\":\"Authenticated\"/ims") || $this->http->FindPreg("/\{\"success\":true,\"responseCode\":1,\"code\":\d+/ims")) {
            $headers = [
                "Accept"           => "*/*",
                "X-Requested-With" => "XMLHttpRequest",
                "Content-type"     => "application/json; charset=utf-8",
            ];
            /*
            $this->http->PostURL("https://www.agoda.com/api/login/token", "{\"pageTypeId\":980}", $headers);
            $this->http->JsonLog();
            */
            $this->http->PostURL("https://www.agoda.com/api/cronos/layout/logincallback", "{\"pageTypeId\":980}", $headers);
            $this->http->JsonLog();

            return true;
        }

        // {"success":false,"responseCode":32,"code":15,"otpInfo":{"target":"...@gmail.com","token":"...","otpType":"email"}}
        // {"success":false,"responseCode":8,"code":15,"otpInfo":{"target":"...@gmail.com","token":"...","otpType":"email"}}
        // New OTP has been sent to your email myself@iphoting.com. An OTP will expire in 10 minutes
        if (isset($response->otpInfo->target, $response->otpInfo->token)) {
            $this->State['token'] = $response->otpInfo->token;
            $this->State['target'] = $response->otpInfo->target;
            $this->AskQuestion("New OTP has been sent to your email {$response->otpInfo->target}. An OTP will expire in 10 minutes", null, "Question");

            return false;
        }

        $message = $response->message ?? null;
        // Email or Password is incorrect.
        if (in_array($message, [
            'PasswordIsInCorrect',
            'UserNameNotFound',
        ])
            || $this->http->FindPreg("/\{\"success\":false,\"responseCode\":4,\"code\":\d+/ims")
            || $this->http->FindPreg("/\{\"success\":false,\"responseCode\":2,\"code\":\d+/ims")
        ) {
            throw new CheckException("Email or Password is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }
        /*
        // Retries
        if ($this->http->FindPreg("/\{\"status\":2(?:\}|,)/ims")) {
            throw new CheckRetryNeededException(2, 10);
        }
        // Password is case sensitive and must be at least 8 characters long.
        if ($this->http->FindPreg("/\{\"status\":5(?:\}|,)/ims")) {
            throw new CheckException("Password is case sensitive and must be at least 8 characters long.", ACCOUNT_INVALID_PASSWORD);
        }
        */

        // retries
        if ($this->http->Response['body'] == '{"message":"The requested resource does not support http method \'GET\'."}') {
            throw new CheckRetryNeededException(3, 10);
        }

        // funcaptcha
        if ($this->http->Response['body'] == '{"success":false,"responseCode":7,"captchaInfo":{"captchaType":"arkose","apiKey":"1C2BB537-D5F7-4A66-BC74-25881B58F1D6","_type":"com.agoda.universallogin.models.captcha.ArkoseInfo"},"downStreamServiceFailure":false}') {
            throw new CheckRetryNeededException();
        }

        if (
            $this->http->Response['body'] == '{"message":"An error has occurred."}'
            || (
                isset($response->responseCode)
                && $response->responseCode == 32 // AccountID: 4496244
            )
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->DebugInfo =
            $response->message
            ?? $response->responseCode
            ?? "Response code: " . ($this->http->Response['code'] ?? null)
        ;

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $data = [
            "credentials"    => [
                "password" => $this->AccountFields['Pass'],
                "authType" => "email",
                "username" => $this->AccountFields['Login'],
            ],
            "otpVerifyInfo"  => [
                "code"    => $answer,
                "target"  => $this->State['target'],
                "token"   => $this->State['token'],
                "otpType" => "email",
            ],
            "captchaEnabled" => true,
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json; charset=utf-8",
        ];
        $this->http->PostURL('https://www.agoda.com/ul/api/v1/signin', json_encode($data), $headers);
        $response = $this->http->JsonLog();

        // The OTP code is incorrect. Please re-enter or request a new OTP
        if (isset($response->responseCode) && $response->responseCode == 11) {
            $this->AskQuestion($this->Question, "The OTP code is incorrect. Please re-enter or request a new OTP", "Question");

            return false;
        }

        // todo: debug
        // Access is allowed
        if ($this->http->FindPreg("/\"message\":\"Authenticated\"/ims") || $this->http->FindPreg("/\{\"success\":true,\"responseCode\":1,\"code\":\d+/ims")) {
            $headers = [
                "Accept"           => "*/*",
                "X-Requested-With" => "XMLHttpRequest",
                "Content-type"     => "application/json; charset=utf-8",
            ];
            $this->http->PostURL("https://www.agoda.com/api/login/token", "{\"pageTypeId\":980}", $headers);
            $this->http->JsonLog();
            $this->http->PostURL("https://www.agoda.com/api/cronos/layout/logincallback", "{\"pageTypeId\":980}", $headers);
            $this->http->JsonLog();
        } else {
            $message = $response->message ?? null;
            // Email or Password is incorrect.
            if (in_array($message, [
                'PasswordIsInCorrect',
                'UserNameNotFound',
            ])
            ) {
                throw new CheckException("Email or Password is incorrect.", ACCOUNT_INVALID_PASSWORD);
            }
        }

        return $this->IsLoggedIn();
    }

    public function profileLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("deprecated? // RR");

        if ($this->http->ParseForm(null, "//form[contains(@class, 'mmb-signin-form')]")
            && $this->http->FindSingleNode("//p[contains(text(), 'For security, please sign in to access your information')]")) {
//            $this->http->SetInputValue('email', $this->AccountFields['Login']);
//            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
//            $this->http->PostForm();

            //$token = $this->http->FindPreg("/reactHeader.verificationToken = \"([^\"]+)/");
            $token = $this->http->FindSingleNode("//input[@id = 'requestVerificationToken']/@value");

            if (!$token) {
                return $this->checkErrors();
            }

            $this->logger->debug("Token => " . $token);

            $data = [
                "email"           => $this->AccountFields['Login'],
                "password"        => $this->AccountFields['Pass'],
                "pageType"        => "newhome",
                "pageTypeId"      => 1,
                "captcha"         => true,
                "captchaResponse" => null,
                "languageId"      => 1,
                "token"           => $token,
            ];
            $headers = [
                "Accept"           => "*/*",
                "Content-Type"     => "application/json; charset=utf-8",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->http->PostURL('https://www.agoda.com/api/login/signin', json_encode($data), $headers);

            if (!$this->http->GetURL("https://www.agoda.com/account/bookings.html")) {
                return $this->checkErrors();
            }

            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//dt[contains(text(), 'Name:')]/following-sibling::dd[1]")));
            // Access is allowed
            if ($this->http->FindSingleNode("//li[@data-url = '/home/logout']") && !empty($this->Properties['Name'])) {
                return true;
            }
        }

        return false;
    }

    public function Parse()
    {
        $noBalance = false;

        if ($this->http->currentUrl() != self::PROFILE_PAGE_URL) {
            $this->http->GetURL(self::PROFILE_PAGE_URL);
        }

        // provider bugfix
        if (strstr($this->http->currentUrl(), 'resulturl=/profile.html&loginreason=1&')) {
            $this->profileLoginForm();
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id = 'mmb-name-component-display-name-value']")));

        // refs 15558, Gift Cards
        //if not => Not yet transferred to another program
        if ($giftCardsLinks = $this->http->FindSingleNode('//section[@data-selenium="mmb-menu-component"]/descendant::a[contains(@href, "giftcards.html")]/@href')) {
            $this->http->NormalizeURL($giftCardsLinks);
            $this->http->GetURL($giftCardsLinks);

            $giftcardsPageParams = $this->http->FindSingleNode("//script[contains(text(), 'PageParams =')]", null, true, "/PageParams\s*=\s*([^<]+)\s*\]\]\>\s*$/");
            $giftcardsPageParams = $this->http->FindPreg("/(.+\})\s+window\./", false, $giftcardsPageParams) ?? $giftcardsPageParams;
//            $this->logger->debug(">>".$giftcardsPageParams."<<");
            if ($giftcardsPageParams) {
                $giftcardsPageParams = $this->http->JsonLog($giftcardsPageParams, 3, false, 'formattedAmountWithCurrency');
                $balance = $giftcardsPageParams->summary->availableBalance->inUserCurrency;

                if ($balance !== null) {
                    $this->SetBalance($balance);
                    $this->SetProperty("Currency", $giftcardsPageParams->userCurrency ?? null);

                    if (!in_array($giftcardsPageParams->userCurrency, ['$', 'USD', 'AUD'])) {
                        $this->sendNotification("agoda - refs #15558. New currency");
                    }

                    // Total redeemed amount
                    $this->SetProperty("TotalRedeemedAmount", $giftcardsPageParams->summary->redeemedBalance->formattedAmountWithCurrency ?? null);
                    // Total expired amount
                    $this->SetProperty("TotalExpiredAmount", $giftcardsPageParams->summary->expiredBalance->formattedAmountWithCurrency ?? null);
                    $noBalance = false;
                }// if ($balance !== null)
                else {
                    $noBalance = true;
                }
            }// if ($noBalance)
            $pagesCnt = $giftcardsPageParams->giftcardItemList->totalPages ?? 0;
            $currentPage = $giftcardsPageParams->giftcardItemList->page ?? 0;
            $token = $this->http->FindSingleNode('//input[@id = "requestVerificationToken"]/@value');
            $page = 1;
            $subAccounts = [];

            do {
                $this->logger->debug("page: {$currentPage} / iterator: {$page} / totalPages: {$pagesCnt}");

                if ($page > 1 && isset($token)) {
                    $data = [
                        "page"     => $page,
                        "culture"  => "",
                        "listType" => 1,
                        "token"    => $token,
                    ];
                    $this->http->PostURL("https://www.agoda.com/api//giftcardapi/getgiftcardlist", $data);
                    $giftcardsPageParams = $this->http->JsonLog();
                    $pagesCnt = $giftcardsPageParams->giftcardItemList->totalPages ?? 0;
                    $currentPage = $giftcardsPageParams->giftcardItemList->page ?? 0;
                }

                $nodes = $giftcardsPageParams->giftcardItemList->giftcardItems ?? [];
                $couponsCount = count($nodes);
                $this->logger->notice("Total {$couponsCount} coupons were found on Page #{$currentPage}");
                $this->SetProperty("CombineSubAccounts", false);

                foreach ($nodes as $node) {
                    $card = $node->cardNumber;
                    $balance = $node->balance->formattedAmountWithCurrency;
                    $exp = strtotime($node->expiryDate);

                    if ($exp && !empty($balance)) {
                        $subAccounts[] = [
                            'Code'           => 'agodaAgodaCashCard' . $card,
                            'DisplayName'    => 'AgodaCash ID: ' . $card,
                            'ExpirationDate' => $exp,
                            'Balance'        => $this->http->FindPreg("#\s(\d[\d\.]+)#", false, $balance),
                            'Currency'       => $this->http->FindPreg("#(.+?)\s\d[\d\.]+#", false, $balance),
                        ];
                    } else {
                        $this->sendNotification("agoda - refs #15558 - coupons ExpirationDate changed format // RR");
                    }
                }// foreach ($nodes as $node)
                $page++;
            } while (
                $currentPage < $pagesCnt
                && $page < 15
            );

            usort($subAccounts, function ($a, $b) { return $a['ExpirationDate'] - $b['ExpirationDate']; });
            $subAccounts = array_slice($subAccounts, 0, 10);

            foreach ($subAccounts as $s) {
                $this->AddSubAccount($s);
            }
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $noBalance
            && !empty($this->Properties['Name'])
        ) {
            $this->logger->notice("Account without balance");
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->http->GetURL("https://www.agoda.com/account/bookings.html");

        $token = $this->http->FindSingleNode("(//input[@id = 'requestVerificationToken']/@value)[1]");

        if (!$token) {
            return [];
        }

        // Cancelled
        if ($cancelled = $this->parseBookingList('Cancelled', $token)) {
            $this->logger->debug('Total ' . count($cancelled) . ' cancelled reservations');

            foreach ($cancelled as $item) {
                if (isset($item->BookingId)) {
                    $h = $this->itinerariesMaster->createHotel();
                    $h->general()->confirmation($item->BookingId);
                    $h->general()->cancelled();
                    $this->logger->debug('Parsed itinerary:');
                    $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
                }// if (isset($item->BookingId))
            }// foreach ($cancelled as $item)
        }// if($cancelled = $this->parseBookingList('Cancelled', $token))

        // Upcoming
        if ($upcoming = $this->parseBookingList('Upcoming', $token)) {
            $this->logger->debug('Total ' . count($upcoming) . ' upcoming reservations');

            foreach ($upcoming as $item) {
                if (isset($item->BookingItemUrl)) {
                    $url = $item->BookingItemUrl;
                    $this->http->NormalizeURL($url);
                    $this->http->GetURL($url);
                    //$this->http->JsonLog($this->http->FindPreg('#window.editBookingPageParams\s*=\s*(\{.+?\})\s*\n#s'), 0, true);

                    $regexp = '#window.editBookingPageParams\s*=\s*(\{.+?\})\s*\n#s';
                    preg_match($regexp, $this->http->Response['body'], $m);

                    if (!$m) {
                        $this->logger->info("regexp not found: " . $regexp);
                        //$this->sendNotification('check parse itineraries // MI');

                        continue;
                    }
                    $s = $m[1];
                    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8'); // remove bugged symbols
                    $s = preg_replace('/[\x{0000}-\x{0019}]+/ums', ' ', $s); // remove unicode special chars, like \u0007
                    $s = preg_replace("/\p{Mc}/u", ' ', $s); // normalize spaces
                    $s = trim(preg_replace("/\s+/u", " ", preg_replace("/\r|\n|\t/u", ' ', $s)));

                    $booking = $this->http->JsonLog($s, 0, true);

                    if ($bookingItem = ArrayVal($booking, 'BookingItem')) {
                        $this->ParseItinerary($bookingItem);
                    }
                }
            }
        }

        // Past
        if ($this->ParsePastIts) {
            $this->logger->info("Past Itineraries", ['Header' => 2]);

            if ($past = $this->parseBookingList('Past', $token)) {
                $this->logger->debug('Total ' . count($past) . ' past reservations');

                foreach ($past as $item) {
                    if (isset($item->BookingItemUrl)) {
                        $url = $item->BookingItemUrl;
                        $this->http->NormalizeURL($url);
                        $this->http->GetURL($url);
                        $booking = $this->http->JsonLog($this->http->FindPreg('#window.editBookingPageParams\s*=\s*(\{.+?\})\s*\n#s'), 1, true);

                        if ($bookingItem = ArrayVal($booking, 'BookingItem')) {
                            $this->ParseItinerary($bookingItem);
                        }
                    }
                }
            } elseif ($cancelled === null && $upcoming === null && $past === null) {
                return $this->itinerariesMaster->setNoItineraries(true);
            }
        } elseif ($cancelled === null && $upcoming === null) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function parseBookingList($status, $token)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice($status);
        $result = [];
        $page = 1;
        $totalPage = 1;

        do {
            $this->http->RetryCount = 0;
            $data = json_encode([
                'DepartureStatus'             => $status,
                'PageIndex'                   => $page,
                'SortBy'                      => 'CheckinDate',
                'WithSharingStatus'           => false,
                'IncludedReviews'             => false,
                'BookingIdSearchValue'        => null,
                'CheckinDate'                 => null,
                'PartnerBookingIdSearchValue' => '',
                'IsCronos'                    => false,
                'token'                       => $token,
            ]);
            $headers = [
                'Accept'           => '*/*',
                'Content-Type'     => 'application/json; charset=UTF-8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer'          => 'https://www.agoda.com/account/bookings.html?sort=CheckinDate&state=' . $status . '&page=' . $page,
            ];
            $this->http->PostURL('https://www.agoda.com/api/en-us/mmbc/getbookingslist', $data, $headers);
            $response = $this->http->JsonLog(null, 0);
            $this->http->RetryCount = 2;

            // BookingsList
            if (!isset($response->BookingsList)) {
                return $result;
            }
            // TotalPage
            if (isset($response->BookingsList->PaginationList->totalPage)) {
                $totalPage = $response->BookingsList->PaginationList->totalPage;
                // noItinerariesArr
                if ($totalPage === 0) {
                    return null;
                }
            }

            $groups = $response->BookingsList->Groups ?? [];
            $items = $response->BookingsList->Items ?? [];

            if (!$groups && !$items) {
                return $result;
            }

            if ($groups) {
                foreach ($groups as $group) {
                    if (isset($group->BookingItem->BookingId) && isset($group->BookingItem->BookingItemUrl)) {
                        $result[] = (object) [
                            'BookingId'      => $group->BookingItem->BookingId,
                            'BookingItemUrl' => $group->BookingItem->BookingItemUrl,
                        ];
                    }
                }
            } elseif ($items) {
                foreach ($items as $item) {
                    if (isset($item->BookingId) && isset($item->BookingItemUrl)) {
                        $result[] = (object) [
                            'BookingId'      => $item->BookingId,
                            'BookingItemUrl' => $item->BookingItemUrl,
                        ];
                    }
                }
            }
            $page++;
        } while ($page <= 20 && $page <= $totalPage);

        return $result;
    }

    public function ParseItinerary($booking)
    {
        $this->logger->notice(__METHOD__);
        $property = ArrayVal($booking, 'Property');

        if (!ArrayVal($property, 'PropertyName', null) && !ArrayVal($property, 'Address', null)) {
            $this->logger->error('Skip: bug reservation');

            return;
        }

        $conf = ArrayVal($booking, 'BookingId');
        $this->logger->info('Parse Itinerary #' . $conf, ['Header' => 3]);
        $h = $this->itinerariesMaster->createHotel();
        $h->general()->confirmation($conf);

        if ($date = strtotime(ArrayVal($booking, 'BookingDate'), false)) {
            $h->general()->date($date);
        }

        // Property
        $h->hotel()->name(ArrayVal($property, 'PropertyName'));
        $address = ArrayVal($property, 'Address', []);

        $h->hotel()->address(ArrayVal($address, 'Address1') . ' ' .
            ArrayVal($address, 'City') . ' ' .
            ArrayVal($address, 'Country') . ' ' .
            ArrayVal($address, 'PostalCode'));

        $detailedAddr = $h->hotel()->detailed();
        $detailedAddr->address(ArrayVal($address, 'Address1'));
        $detailedAddr->city(ArrayVal($address, 'City'));
        $zip = trim(ArrayVal($address, 'PostalCode'));

        if (!empty($zip)) {
            $detailedAddr->zip($zip);
        }
        $detailedAddr->state(ArrayVal($address, 'State'));
        $detailedAddr->country(ArrayVal($address, 'Country'));

        // Dates
        $dates = ArrayVal($booking, 'Dates');
        $h->booked()->checkIn(strtotime(ArrayVal($dates, 'CheckInDateNonCulture'), false));
        $h->booked()->checkOut(strtotime(ArrayVal($dates, 'CheckOutDateNonCulture'), false));

        // RoomName
        $room = $h->addRoom();
        $room->setType(ArrayVal(ArrayVal($booking, 'Room', []), 'RoomName'));

        // TODO: Guests
        $guests = ArrayVal($booking, 'Guests');
        $primaryGuest = ArrayVal($guests, 'PrimaryGuest');
        $guestNames = [];
        $guestNames[] = beautifulName(ArrayVal($primaryGuest, 'FirstName') . ' ' . ArrayVal($primaryGuest, 'LastName'));

        foreach (ArrayVal($guests, 'SecondaryGuests', []) as $guest) {
            $guestName = trim(beautifulName(ArrayVal($guest, 'FirstName') . ' ' . ArrayVal($guest, 'LastName')));

            if (!empty($guestName)) {
                $guestNames[] = $guestName;
            }
        }

        if (!empty($guestNames)) {
            $h->general()->travellers(array_unique(array_filter($guestNames)));
        }

        // Occupancy
        $occupancy = ArrayVal($booking, 'Occupancy');
        $h->booked()->rooms(ArrayVal($occupancy, 'NumberOfRooms'));
        $h->booked()->guests(ArrayVal($occupancy, 'NumberOfAdults'));
        $h->booked()->kids(ArrayVal($occupancy, 'NumberOfChildren'));

        // Amount
        $nonCancelledPaymentDetails = ArrayVal(ArrayVal($booking, 'PaymentDetails', []), 'NonCancelledPaymentDetails', []);
        $totalBookingValue = ArrayVal($nonCancelledPaymentDetails, 'TotalBookingValue', []);
        $total = ArrayVal($totalBookingValue, 'Amount');

        if ($total) {
            $h->price()->total(PriceHelper::cost($total));
            $h->price()->currency(ArrayVal($totalBookingValue, 'Currency'));
        }

        // CancellationPolicy
        $cancellationPolicy = ArrayVal($booking, 'BookingConditions');
        $policyTexts = ArrayVal($cancellationPolicy, 'PolicyTexts');

        if ($policyTexts && is_array($policyTexts)) {
            $h->setCancellation(join(' ', $policyTexts));
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'GBP':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                case 'EUR':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'AUD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f " . $properties['Currency']);

                case 'USD':
                case '$':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }// switch ($properties['Currency'])
        }// if (isset($properties['SubAccountCode'], $properties['Currency']))

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function parseFunCaptcha($key, $retry = true)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindSingleNode('(//script[contains(@src, "-api.arkoselabs.com/v2/")])[1]/@src', null, true, "/v2\/([^\/]+)/");
        $this->logger->debug("[key]: {$key}");

        if (!$key) {
            return false;
        }

        /*if ($this->attempt > 1) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => 'https://www.agoda.com/',
                    "funcaptchaApiJSSubdomain" => 'client-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );

            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }*/

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => 'https://www.agoda.com/',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//span[@id = "mmb-email-component-display-email-value"]')
        ) {
            return true;
        }

        return false;
    }
}
