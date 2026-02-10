<?php

class TAccountCheckerCellarpass extends TAccountChecker
{
    private $userId;

    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token']) || !isset($this->State['tenantKey'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful($this->State['token'], $this->State['tenantKey']);
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.cellarpass.com/login');

        if (!$this->http->ParseForm('loginfrom')) {
            return $this->checkErrors();
        }

        $urlJs = $this->http->FindPreg('/<\/script>.+?src="(main.+?\.js)/');

        if (!isset($urlJs)) {
            return false;
        }

        $this->http->GetURL("https://www.cellarpass.com/" . $urlJs);
        $tenant_id =
//            $this->http->FindPreg('/TENANT_ID."(.+?)",/')
//            ??
            $this->http->FindPreg('/"(9B5BF34A-1E32-4E0C-8A92-6E0256224A55)",/')
        ;
        $tenant_key =
            $this->http->FindPreg('/TENANT_KEY."(.+?)",/')
            ?? $this->http->FindPreg('/"(F1D5798B-AC66-4579-8829-D40076D7B6F9)",/')
        ;

        if (
            !isset($tenant_id)
            || !isset($tenant_key)
            || filter_var($tenant_id, FILTER_VALIDATE_URL)
            || filter_var($tenant_key, FILTER_VALIDATE_URL)
        ) {
            return false;
        }
        // 7b5f47ae-fba1-4b81-aca0-e2180a2f0c4e|9B5BF34A-1E32-4E0C-8A92-6E0256224A55|2022-03-10T10:15:56.469Z|F1D5798B-AC66-4579-8829-D40076D7B6F9
        $this->headers['TenantKey'] = '4222c5d4-2fc3-4e96-b9fc-84f7759d1b79|' . $tenant_id . '|' . date('Y-m-d\TH:i:s\Z') . '|' . $tenant_key . '';

        $data = [
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://web3.cellarpass.com/api/account/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->token) && $this->loginSuccessful($response->token, null)) {
            $this->State['token'] = $response->token;
            $this->State['tenantKey'] = $this->headers['TenantKey'];

            if (!empty($response->userId)) {
                $this->userId = $response->userId;
            }

            return true;
        }

        if (isset($response->message) && $response->message == "We didn't recognize the email or password you entered.") {
            throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $reward = $this->http->JsonLog(null, 0);
        //PointsToNextReward
        if (!isset($reward->data->nextRewardPoints) || !isset($reward->data->earnedPoints)) {
            $this->sendNotification("refs #18927: NextRewardPoints or EarnedPoints is empty!");

            return;
        }
        $this->SetProperty('PointsToNextReward', $reward->data->nextRewardPoints - $reward->data->earnedPoints);
        //Earned
        $this->SetBalance($reward->data->earnedPoints ?? null);
        //User-Profile
        $this->http->GetURL('https://web3.cellarpass.com/api/account/user-profile', $this->headers);
        $profile = $this->http->JsonLog();
        //Name
        $firstName = $profile->firstName ?? '';
        $lastName = $profile->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
    }

    public function ParseItineraries()
    {
        $this->http->GetURL("https://web3.cellarpass.com/api/account/user-reservation?toDate=" . date('Y-m-d\TH:i:s\Z') . "&fromDate=" . date('Y-m-d\TH:i:s\Z',
                strtotime('-1 years')) . "&isPastEvent=false", $this->headers);

        $response = $this->http->JsonLog();

        if ($this->http->FindPreg("/^\{\"data\":\[\]\}$/") && $this->ParsePastIts == false) {
            return $this->noItinerariesArr();
        }
        $itineraryList = $response->data ?? [];

        foreach ($itineraryList as $it) {
            if (empty($it->reservationId)) {
                continue;
            }
            $this->http->GetURL("https://web3.cellarpass.com/api/account/user-reservation/" . $it->reservationId, $this->headers);
            $reservation = $this->http->JsonLog();
            $data = $reservation->data ?? [];
            $this->parseEvent($data);
        }

        if ($this->ParsePastIts) {
            $this->http->GetURL("https://web3.cellarpass.com/api/account/user-reservation?toDate=" . date('Y-m-d\TH:i:s\Z') . "&fromDate=" . date('Y-m-d\TH:i:s\Z',
                    strtotime('-1 years')) . "&isPastEvent=true", $this->headers);

            $response = $this->http->JsonLog();
            $itineraryList = $response->data ?? [];

            foreach ($itineraryList as $it) {
                if (empty($it->reservationId)) {
                    continue;
                }
                $this->http->GetURL("https://web3.cellarpass.com/api/account/user-reservation/" . $it->reservationId,
                    $this->headers);
                $reservation = $this->http->JsonLog();
                $data = $reservation->data ?? [];
                $this->parseEvent($data, 'past ');
            }
        }

        return [];
    }

    private function loginSuccessful($token, $tenantKey)
    {
        $this->logger->notice(__METHOD__);
        $this->headers['Authorization'] = 'Bearer ' . $token;

        if (!empty($tenantKey) && empty($this->headers['TenantKey'])) {
            $this->headers['TenantKey'] = $tenantKey;
        }

        $this->http->GetURL("https://web3.cellarpass.com/api/account/user-reward", $this->headers, 20);
        $response = $this->http->JsonLog();

        if (isset($response->data)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseEvent($rd, $type = '')
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parse {$type}itinerary #" . $rd->bookingCode, ['Header' => 3]);

        if ($rd->subTotal < 0 && empty($rd->discount)) {
            $this->sendNotification("refs #18927: Confirmation #{$rd->bookingCode} Total: {$rd->subTotal} and Discount: {$rd->discount}, need to check");
        }
        $r = $this->itinerariesMaster->add()->event();

        $date = strtotime(date('d.m.Y', strtotime($rd->eventDate)));

        $r->general()
            ->date(strtotime($rd->bookingDate))
            ->confirmation($rd->bookingCode, 'Confirmation #')
            ->status($rd->statusText)
            ->traveller($rd->guestName, true)
            ->cancellation($rd->cancelPolicy);
        $r->price()
            ->cost($rd->subTotal < 0 ? str_replace('-', '', $rd->subTotal) : $rd->subTotal)
            ->total($rd->totalAmount < 0 ? '0' : $rd->totalAmount)
            ->tax($rd->tax)
            ->discount($rd->discount)
            ->fee('Convenience Fee', $rd->convenienceFee);
        //->fee('Fee Per Person',$rd->feePerPerson)
        $currency = $this->getCurrency($rd->feeDueText ?? null);

        if ($currency) {
            $r->price()->currency($currency);
        }
        $r->place()
            ->name($rd->eventName)
            ->address($rd->memberAddress)
            ->phone($rd->memberPhoneText);
        $r->booked()
            ->guests($rd->totalGuests)
            ->end(strtotime($rd->eventEndTimeText, $date))
            ->start(strtotime($rd->eventStartTimeText, $date));

        if (!empty($rd->statusText) && $rd->statusText == 'Cancelled') {
            $r->general()->cancelled();
        }
        $r->setEventType(EVENT_EVENT);

        $this->logger->info('Parsed event:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function getCurrency($text)
    {
        return str_replace("$", "USD", $this->http->FindPreg("/^(?<c>(?:[A-Z]{3}|[^\d.,]{1}))\d+/", false, $text));
    }
}
