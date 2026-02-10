<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAscott extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        'Accept'                    => '*/*',
        'Content-Type'              => 'application/json',
        'applicationtype'           => "DISCOVER_ASR",
        'lang'                      => "en",
        'ocp-apim-subscription-key' => '69a18c02ec4d4e90a8be1ff8927ea380',
    ];

    private $propertyCash = [];

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn(): bool
    {
        if (
            !isset($this->State['token'])
            || !isset($this->State['tokenExpiry'])
            || !isset($this->State['subscriptionKey'])
        ) {
            return false;
        }

        $tokenExpiry = new DateTime($this->State['tokenExpiry']);

        if (new DateTime() > $tokenExpiry) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.discoverasr.com/en');

        if ($this->http->Response['code'] !== 200) {
            return false;
        }

        $keyCaptcha = $this->parseCaptcha();

        if ($keyCaptcha === false) {
            return false;
        }

        $data = [
            'email'          => $this->AccountFields['Login'],
            'password'       => $this->AccountFields['Pass'],
            'recaptchaToken' => $keyCaptcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.the-ascott.com/ascott/api/authen/login', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login(): bool
    {
        $response = $this->http->JsonLog(null, 1);

        if (isset($response->content->token)) {
            $this->State['token'] = $response->content->token;

            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);
                $this->State['tokenExpiry'] = $response->content->tokenExpiry;

                return true;
            }
        }

        $errorCode = $response->errorCode ?? null;
        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($errorCode === 'E68') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if ($errorCode === 'CPRV_1500') {
                throw new CheckException('Invalid email address or password. Click on the Forgot Password link to reset your password.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Invalid email address or password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Account has been locked out. Please reset your password.') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse(): void
    {
        $this->http->RetryCount = 0;
        $response = $this->http->JsonLog(null, 0)->content ?? null;
        // Member ID
        $this->SetProperty('Number', $response->profileDto->memberId ?? null);

        $this->http->GetURL('https://api.the-ascott.com/ascott/api/loyalty/user-tier', $this->headers);
        $response = $this->http->JsonLog()->content ?? null;
        // Balance
        $this->SetBalance($response->currentPoints ?? null);
        // Status
        $this->SetProperty('Status', ucfirst($response->currentTier ?? null));

        $this->http->GetURL('https://api.the-ascott.com/ascott/api/currency/exchangerate', $this->headers);
        $exchangeRate = $this->http->JsonLog(null, 0, true)['quotes'] ?? [];
        $this->http->GetURL('https://api.the-ascott.com/ascott/api/loyalty/account-preference', $this->headers);
        $userCurrency = $this->http->JsonLog(null, 1, false, 'currency')->content->currency ?? null;

        if (empty($userCurrency)) {
            $this->http->GetURL('https://api.the-ascott.com/ascott/api/configurations/client-country', $this->headers);
            $userCurrency = $this->http->JsonLog(null, 1, false, 'currency')->content->currency ?? null;
        }
        $spendToNextTier = null;
        $currentSpend = null;

        if (is_numeric($exchangeRate['USD' . $userCurrency] ?? null) && is_numeric($exchangeRate['USDSGD'] ?? null)) {
            if (is_numeric($response->spendToNextTier ?? null)) {
                $spendToNextTier = $exchangeRate['USD' . $userCurrency] / $exchangeRate['USDSGD'] * $response->spendToNextTier; // converting
                $spendToNextTier = round($spendToNextTier, 2);
                $spendToNextTier = $userCurrency . ' ' . $spendToNextTier; // adding currency code
            }

            if (is_numeric($response->currentSpend ?? null)) {
                $currentSpend = $exchangeRate['USD' . $userCurrency] / $exchangeRate['USDSGD'] * $response->currentSpend;
                $currentSpend = round($currentSpend, 2);
                $currentSpend = $userCurrency . ' ' . $currentSpend;
            }
        }
        // Qualifying spending to next status
        $this->SetProperty('SpendToNextTier', $spendToNextTier);
        // Current spend
        $this->SetProperty('CurrentSpend', $currentSpend);

        $this->http->GetURL('https://api.the-ascott.com/ascott/api/loyalty/get-member-status', $this->headers);
        $response = $this->http->JsonLog()->content ?? null;
        // Name
        $firstName = $response->profileDto->firstName ?? null;
        $lastName = $response->profileDto->lastName ?? null;
        $this->SetProperty('Name', beautifulName($firstName . ' ' . $lastName));
        // Member since
        $this->SetProperty('MemberSince', $response->profileDto->memberSince ?? null);

        $this->logger->info('Expiration Date', ['Header' => 3]);
        // Expiring balance
        $this->http->GetURL('https://api.the-ascott.com/ascott/api/loyalty/expiring-point-list', $this->headers);
        $response = $this->http->JsonLog()->content->expiringPointList ?? [];

        foreach ($response as $month) {
            if (empty($month->points)) {
                continue;
            }
            $dt = new DateTime($month->time);
            $this->SetExpirationDate($dt->getTimestamp());
            $this->SetProperty('ExpiringBalance', $month->points);

            break;
        }

        // Vouchers
        $this->logger->info('Vouchers', ['Header' => 3]);
        $this->http->GetURL('https://api.the-ascott.com/ascott/api/loyalty/get-all-voucher', $this->headers);
        $response = $this->http->JsonLog()->content->availableVouchers ?? null;
        $pagesTotal = $response->pageTotal ?? 0;

        if ($pagesTotal > 1) {
            $this->sendNotification('refs #18295 more than 1 page of vouchers // BS');
        }

        $vouchers = $response->vouchers ?? [];

        foreach ($vouchers as $voucher) {
            $properties = [
                'Code'          => $voucher->voucherCode ?? null,
                'DisplayName'   => $voucher->title ?? null,
                'Balance'       => $voucher->quantity,
                'ProgrammeCode' => $voucher->voucherProgrammeCode ?? null,
            ];

            if (!empty($voucher->expiryDate)) {
                $dt = new DateTime($voucher->expiryDate);
                $properties['ExpirationDate'] = $dt->getTimestamp();
            }

            $this->AddSubAccount($properties);
        }
        $this->http->RetryCount = 2;
    }

    public function ParseItineraries(): array
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api.the-ascott.com/ascott/api/loyalty/reservations', $this->headers);
        $content = $this->http->JsonLog(null, 3, true)['content'] ?? null;

        if ($this->http->FindPreg('/^\{"content":\{"past":\{"pageTotal":1,"reservations":\[\]\},"cancelled":\{"pageTotal":1,"reservations":\[\]\}\}\}$/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return $content;
        }
        $content = $this->http->JsonLog(null, 0)->content ?? null;

        $this->parseItinerariesByType('upcoming', $content);
        $this->parseItinerariesByType('current', $content);
        $this->parseItinerariesByType('cancelled', $content);

        if ($this->ParsePastIts) {
            $this->parseItinerariesByType('past', $content);
        }
        $this->http->RetryCount = 2;

        return [];
    }

    public function GetConfirmationFields(): array
    {
        return [
            "ConfNo"    => [
                "Caption"  => "Confirmation Number",
                "Type"     => "string",
                "Size"     => 13,
                "Required" => true,
            ],
            "Email"  => [
                "Caption"  => "Email",
                "Type"     => "string",
                "Size"     => 255,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields): string
    {
        return 'https://www.discoverasr.com/en/booking/property-listing/search-for-reservation';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->RetryCount = 0;
        $msg = $this->parseItinerary(null, $arFields['ConfNo'], $arFields['Email']);
        $this->http->RetryCount = 2;

        return $msg;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/recaptcha-sitekey="([^"]+)/');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseItinerariesByType($type, $reservations): void
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parse {$type} itineraries:", ['Header' => 2]);
        $pagesTotal = $reservations->{$type}->pageTotal ?? 0;

        if ($pagesTotal > 0) {
            $reservations = $reservations->{$type}->reservations ?? [];

            for ($page = 0; $page < $pagesTotal; $page++) {
                foreach ($reservations as $reservation) {
                    if ($type == 'cancelled') {
                        $this->parseItineraryCancelled($reservation);
                    } else {
                        $this->parseItinerary($reservation);
                    }
                }
                $nextPage = $page + 1;

                if ($nextPage < $pagesTotal) {
                    $this->logger->debug("Retrieving next page of $type reservations.");
                    $this->http->GetURL("https://api.the-ascott.com/ascott/api/loyalty/reservations?type=$type&itemsperpage=10&page=$nextPage", $this->headers);
                    $reservations = $this->http->JsonLog()->content->reservations ?? [];
                }
            }
        }
    }

    private function parseItineraryCancelled($reservation, $confirmNo = null, $email = null): ?string
    {
        $this->logger->notice(__METHOD__);
        $confNo = $reservation->apartments[0]->confirmNo ?? $confirmNo;
        $cancellationNo = $reservation->apartments[0]->cancellationNo ?? $confirmNo;
        $this->logger->info("Parse Cancelled Itinerary #{$cancellationNo}", ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();
        $h->general()->confirmation($confNo);

        if (isset($cancellationNo)) {
            $h->general()->cancellationNumber($cancellationNo);
        }
        $h->general()->cancelled();

        return "";
    }

    private function parseItinerary($reservation, $confirmNo = null, $email = null): ?string
    {
        $this->logger->notice(__METHOD__);
        $confNo = $reservation->apartments[0]->confirmNo ?? $confirmNo;
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);
        $data = [[
            'companyEmail'    => '',
            'confirmNo'       => $confNo,
            'email'           => $reservation->email ?? $email,
            'isFromSearch'    => false,
            'itineraryNumber' => $reservation->itineraryNo ?? null,
            'primaryEmail'    => $this->AccountFields['Login'] ?? '',
        ]];

        if (!is_null($confirmNo)) {
            $data[0]['isFromSearch'] = true;
            $data[0]['primaryEmail'] = '';
            $data[0]['lstRoomTypeCodeByProperty'] = [];
        }

        $this->http->PostURL('https://api.the-ascott.com/ascott/api/loyalty/manage-reservation', json_encode($data), $this->headers);
        $rData = $this->http->JsonLog(null, 2)->content ?? null;

        if ($this->http->FindPreg('/^\{\}$/')) {
            return 'Sorry, we’re unable to find your reservation. Please try again.';
        }

        if (empty($rData)) {
            return null;
        }

        if (isset($rData->existBookingCPRV) && $rData->existBookingCPRV === false
            && isset($rData->foundBookerInfo) && $rData->foundBookerInfo === false
        ) {
            return 'Your booking cannot be found at this moment. Please try again tomorrow or contact websupport@the-ascott.com.';
        }
        $r = $this->itinerariesMaster->add()->hotel();
        $r->addConfirmationNumber($confNo, 'Confirmation No.', true);
        $property = $this->parseProperty($rData->propertyCode);
        $cost = 0;
        $tax = 0;
        $total = 0;
        $pointsRedeemed = 0;
        $priceIsValid = true;
        $guestCount = 0;
        $kidsCount = 0;
        $roomsCount = count($rData->apartments ?? []);
        $r->setRoomsCount($roomsCount);

        if ($roomsCount > 1) {
            $this->sendNotification('refs #18295 reservation with more than 1 room // BS');
        }

        foreach ($rData->apartments as $apartment) {
            $room = $r->addRoom();
            $room->setType($apartment->apartmentTypeName ?? null);
            $room->setRateType($apartment->ratePlanName ?? null, true, false);
            $cn = $apartment->confirmNo ?? null;
            $room->setConfirmation($cn);
            $room->setConfirmationDescription('Confirmation No.');

            if ($cn != $confNo) {
                $r->addConfirmationNumber($cn, 'Confirmation No.');
            }
            $firstN = $apartment->guestName->firstName ?? null;
            $lastN = $apartment->guestName->lastName ?? null;
            $r->addTraveller(beautifulName($firstN . ' ' . $lastN), true);
            $guestCount += $apartment->guestTotal ?? 0;
            $kidsCount += $apartment->childTotal ?? 0;

            if (!is_numeric($apartment->cost ?? null)
                || !is_numeric($apartment->tax ?? null)
                || !is_numeric($apartment->total ?? null)
                || !is_numeric($apartment->pointsRedeemed ?? null)) {
                $priceIsValid = false;

                continue;
            }

            if (is_numeric($apartment->nightTotal ?? null) && !empty($rData->displayCurrency)) {
                $costPerNight = round($apartment->cost / $apartment->nightTotal, 2);
                $room->setRate("{$rData->displayCurrency} {$costPerNight}/night");
            }

            $cost += $apartment->cost;
            $tax += $apartment->tax;
            $total += $apartment->total;
            $pointsRedeemed += $apartment->pointsRedeemed;
        }

        if ($priceIsValid && !empty($rData->displayCurrency)) {
            if ($pointsRedeemed !== 0) {
                $r->price()->spentAwards($pointsRedeemed . ' pts');
            }
            $r->price()->currency($rData->displayCurrency)
                ->cost($cost)
                ->tax($tax)
                ->total(round($total, 2));
        }

        $policy = null;

        if (is_array($rData->apartments[0]->policies ?? null)) {
            $policies = array_reverse($rData->apartments[0]->policies);
            $descriptions = array_column(array_column($policies, 'policyDescriptions'), 0);
            $policy = implode(' ', $descriptions);

            if (str_contains($policy, 'Booking is non-refundable once confirmed')) {
                $r->setNonRefundable(true);
            }
            $r->setCancellation($policy);
        }

        if (!empty($rData->pointsEarn)) {
            $r->setEarnedAwards($rData->pointsEarn . ' pts');
        }

        if (!empty($rData->status)) {
            $r->setStatus($rData->status);
        }

        // hotel specific properties
        $r->hotel()->name($property->propertyName ?? null);
        $r->hotel()->address($property->address ?? null);
        $r->hotel()->phone($property->phone ?? null);

        if (!empty($rData->apartments[0]->checkInDate) && !empty($property->checkinTime)) {
            $r->booked()->checkIn2($rData->apartments[0]->checkInDate . ' ' . $property->checkinTime);

            if (!is_null($policy)) {
                $deadline = $this->http->FindPreg("/Booking must be cancelled at least (.+) before arrival/", false, $policy);

                if (!is_null($deadline)) {
                    $r->parseDeadlineRelative($deadline, $property->checkinTime);
                } else {
                    $deadline = $this->http->FindPreg("/Booking must be cancelled by (.+) local time the day before arrival/", false, $policy);

                    if (!is_null($deadline)) {
                        $r->parseDeadlineRelative('1 day', $deadline);
                    }
                }
            }
        }

        if (!empty($rData->apartments[0]->checkOutDate && !empty($property->checkoutTime))) {
            $r->booked()->checkOut2($rData->apartments[0]->checkOutDate . ' ' . $property->checkoutTime);
        }

        if ($guestCount > 0) {
            $r->setGuestCount($guestCount);
            $r->setKidsCount($kidsCount);
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return null;
    }

    private function parseProperty($propertyCode)
    {
        if (!empty($this->propertyCash[$propertyCode])) {
            return $this->propertyCash[$propertyCode];
        }
        $this->http->GetURL("https://www.discoverasr.com/apis/discoverasr/properties/reservation/p_lang/eq/en/p_propertyCodes/eq/{$propertyCode}.json", $this->headers);
        $property = $this->http->JsonLog(null, 1)->content->bookableProDtos[0] ?? null;
        $this->propertyCash[$propertyCode] = $property;

        return $property;
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $headers = $this->headers + [
            'authorization'             => $this->State['token'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api.the-ascott.com/ascott/api/authen/get-profile', $headers, 40);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->content->profileDto->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            $this->http->setDefaultHeader('authorization', $this->State['token']);

            return true;
        }

        return false;
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
