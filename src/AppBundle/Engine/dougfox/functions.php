<?php

class TAccountCheckerDougfox extends TAccountChecker
{
    private $headers = [
        "Accept"          => "application/json",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "application/json",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['token'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.dougfoxparking.com/");

        if (!$this->http->FindSingleNode("(//div[contains(text(), 'Point Club')])[1]")) {
            return false;
        }
        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://parkapiv2.dougfoxparking.com/v1/club/login", json_encode($data), $this->headers, 20);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $msg = $response->msg ?? null;

        if ($msg) {
            $this->logger->error("[Error]: {$msg}");

            if ($msg == "Username or password is incorrect.") {
                throw new CheckException($msg, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $access_token = $response->access_token ?? null;

        if (!$access_token) {
            return false;
        }

        if ($this->loginSuccessful($access_token)) {
            $this->State['token'] = $access_token;

            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->headers['Authorization'] = $this->State['token'];
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", $response->user->long_name ?? null);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://parkapiv2.dougfoxparking.com/v1/club/me', ["Authorization" => $this->State['token']], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Balance - Your Point Total
        $this->SetBalance($response->point_total ?? null);
        // Your Membership Number
        $this->SetProperty("Number", $response->number ?? null);

        // for ParseItineraries
        $this->http->RetryCount = 0;
        // past
        $this->logger->warning("past itineraries");
        $requestData = [
            'sort'       => '{"field":"travel_to_date","order":"desc"}',
            'pageSize'   => 5,
            'pageNumber' => 1,
        ];
        $requestData = urlencode(json_encode($requestData));
        $this->http->GetURL('https://parkapiv2.dougfoxparking.com/v1/club/0015968/recent-trips?' . $requestData, $this->headers, 20);
        $response = $this->http->JsonLog(null, 0);

        if (isset($response->count, $response->items) && $response->count > 0 && is_array($response->items)) {
            $this->logger->info("have past");
        }

        // https://dougfoxparking.com/booking-claim - retrieve
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://parkapiv2.dougfoxparking.com/v1/club/0014566/upcoming-trips', $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->count, $response->items)) {
            return [];
        }
        $skipped = 0;

        $this->http->setCookie('df_auth_token', $this->State['token'], 'dougfoxparking.com');

        foreach ($response->items as $item) {
            $lastDate = strtotime($item->travel_to_date);

            if ($lastDate < time()) {
                $skipped++;

                continue;
            }

            if ($item->type == 'booking') {
                $this->http->GetURL("https://dougfoxparking.com/bookings/{$item->id}");
                $this->parseItinerary($item->id);
            } elseif ($item->type == 'time_voucher') {
                $this->AddSubAccount([
                    "Code"        => "dougfoxVoucher{$item->id}",
                    "DisplayName" => "Parking Voucher {$item->number}",
                    // 2021-12-25T00:00:00-08:00
                    'ExpirationDate' => strtotime(str_replace('T', '', preg_replace('/-\d+:\d+$/', '', $item->travel_to_date))),
                    "Redeemed"       => $item->points_used,
                    // Dec 17, 2021
                    "Park"    => date('M j, Y', strtotime(str_replace('T', '', preg_replace('/-\d+:\d+$/', '', $item->travel_from_date)))),
                    "Balance" => null,
                ]);
            } else {
                $this->sendNotification("new type {$item->type} // MI");
            }
        }

        if ($skipped === count($response->items)) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    private function loginSuccessful($access_token)
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "token" => $access_token,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://parker.auth.dougfoxparking.com/v1/introspect", json_encode($data), $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->user->email ?? null;
        $validation_err = $response->validation_err ?? null;

        if (
            $email && strtolower($email) == strtolower($this->AccountFields['Login'])
            && !strstr($validation_err, 'token is expired by')
        ) {
            return true;
        }

        return false;
    }

    private function parseItinerary($conf)
    {
        $this->logger->notice(__METHOD__);
        $p = $this->itinerariesMaster->createParking();
        $this->logger->info("Parse Itinerary #{$conf}", ['Header' => 3]);
        $p->general()->confirmation($conf);

        if ($traveller = $this->http->FindSingleNode("//strong[normalize-space(text())='Booking Number:']/following-sibling::div[2]")) {
            $p->general()->traveller($traveller);
        }

        $month = $this->http->FindSingleNode("//label[normalize-space(text())='Park']/following-sibling::div[1]/strong", null, false, '/\w+ \d+/');
        $year = $this->http->FindSingleNode("//label[normalize-space(text())='Park']/following-sibling::div[1]/span", null, false, '/\w+ (\d{4})/');
        $time = $this->http->FindSingleNode("//label[normalize-space(text())='Park']/following-sibling::div[2]/span");
        $this->logger->debug($time = "$month $year, $time");
        $p->booked()->start2($time);
        $month = $this->http->FindSingleNode("//label[normalize-space(text())='Return']/following-sibling::div[1]/strong", null, false, '/\w+ \d+/');
        $year = $this->http->FindSingleNode("//label[normalize-space(text())='Return']/following-sibling::div[1]/span", null, false, '/\w+ (\d{4})/');
        $time = $this->http->FindSingleNode("//label[normalize-space(text())='Return']/following-sibling::div[2]/span");
        $this->logger->debug($time = "$month $year, $time");
        $p->booked()->end2($time);

        $p->place()->address($this->http->FindSingleNode("//text()[contains(., 'Our lot is located at ')]", null, false, '/located at (.+)/'));

        $this->http->GetURL("https://parkapiv2.dougfoxparking.com/v1/bookings/{$conf}", $this->headers);
        $response = $this->http->JsonLog();

        $p->price()->total($response->quote->rate->total->amount);
        $p->price()->currency($response->quote->rate->total->currency);
        $p->price()->cost($response->quote->rate->subtotal->amount);
        $p->price()->tax($response->quote->rate->tax_total->amount);
    }
}
