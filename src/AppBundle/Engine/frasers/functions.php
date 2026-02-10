<?php

class TAccountCheckerFrasers extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://347497-fhapi.adobeioruntime.net/apis/member/profile';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['loginToken']) || empty($this->State['memberId'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.frasershospitality.com/en/fraser-world/');

        $loginURL = $this->http->FindSingleNode('//section[@data-login-url]/@data-login-url');

        if (!$this->http->ParseForm("loginform") || !$loginURL) {
            return $this->checkErrors();
        }

        $this->http->FormURL = $loginURL;
        $this->http->Form = [];
        $this->http->SetInputValue('loginId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->loginToken, $response->profile->member_id)) {
            $this->State['loginToken'] = $response->loginToken;
            $this->State['memberId'] = $response->profile->member_id;

            return $this->loginSuccessful();
        }

        if ($message = $response->body->error ?? null) {
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                'WrongPassword',
                'UserNotExist',
            ])) {
                throw new CheckException("Please check your email and password. Your login credentials did not match our records.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // AccountID: 3263363
        if ($this->http->FindPreg("/^\{\s*\"body\":\s*\{\s*\"activationId\":\s*\"not_available\",\s*\"error\": \"login_failed\",\s*\"success\":\s*false\s*\},
 /")) {
            throw new CheckException("We encountered an error while logging you in. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        // Invalid credentials
        if ($message = $this->http->FindSingleNode("
                //li[contains(text(), 'Please enter valid credentials.')]
                | //li[contains(text(), 'Wrong email format')]
                | //li[contains(text(), 'Please check your username and password')]
                | //li[contains(text(), 'Please enter a valid email')]
                | //li[contains(text(), 'This account has been deactivated. If you feel you have reached this message in error please contact us.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($response->body->data->points->result->CardName));
        // Membership No
        $this->SetProperty('MembershipNumber', $response->body->data->points->result->MembershipNo);
        // Member Type
        $this->SetProperty('MembershipTier', $response->body->data->points->result->MembershipLevel);
        // Member Since
        $this->SetProperty('MemberSince', date("d/m/Y", strtotime($response->body->data->points->result->MemberSince)));
        // Balance - Current Points
        $this->SetBalance($response->body->data->points->result->CurrentPoints);
        // Points expiring in this month
        $this->SetProperty('ExpiringInThisMonth', $response->body->data->points->result->PointsExpiringThisMonth);
        // Points Expiring Within 3 Months
        $this->SetProperty('ExpiringWithinTheeMonth', $response->body->data->points->result->PointsExpiringWithin3Months);

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://347497-fhapi.adobeioruntime.net/apis/member/awards", ["memberId" => $this->State['memberId']], $this->getHeaders());
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, "awards");
        $awards = $response->body->data->data->awards ?? [];

        foreach ($awards as $award) {
            // Voucher Number
            $code = $award->voucher_number;
            // Expiry Date
            $exp = $award->expiration;
            // Issue Date
            $displayName = $award->title;
            $this->AddSubAccount([
                'Code'             => "Voucher" . $code,
                'DisplayName'      => $displayName . " (#{$code})",
                'Balance'          => null,
                'IssueDate'        => $award->date_issued,
                'MembershipNumber' => $code,
                'ExpirationDate'   => strtotime($exp),
            ]);
        }// foreach ($awards as $award)
    }

    public function ParseItineraries()
    {
//        $this->http->GetURL('https://www.frasershospitality.com/en/fraser-world/account/#!tab1');
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://347497-fhapi.adobeioruntime.net/apis/member/stays", ["memberId" => $this->State['memberId']], $this->getHeaders());
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, "stay");
        $stays = $response->body->data->status->stay ?? [];

        if ($stays === []) {
            return $this->noItinerariesArr();
        }

        $pastIts = 0;

        foreach ($stays as $stay) {
            if (!$this->ParsePastIts && strtotime($stay->departure) < time()) {
                $this->logger->notice("skip old stay #{$stay->pms_confirmation_number}");
                $pastIts++;

                continue;
            }

            $this->parseItinerary($stay);
        }

        if (!$this->ParsePastIts && $pastIts > 0 && $pastIts === count($response->body->data->status->stay)) {
            return $this->noItinerariesArr();
        }

        return [];
    }

    private function getHeaders()
    {
        $this->logger->notice(__METHOD__);

        return [
            "Accept"        => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"  => "application/x-www-form-urlencoded; charset=UTF-8",
            "Authorization" => "Bearer {$this->State['loginToken']}",
            "Origin"        => "https://www.frasershospitality.com",
            "Referer"       => "https://www.frasershospitality.com/",
        ];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->PostURL(self::REWARDS_PAGE_URL, ["memberId" => $this->State['memberId']], $this->getHeaders());
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'MembershipNo');

        $email = $response->body->data->profile->result->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function parseItinerary($stay): void
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();
        $confNo = $stay->pms_confirmation_number;
        $this->logger->info("Parse Hotel #{$confNo}", ['Header' => 3]);

        $h->general()
            ->confirmation($confNo, "Confirmation Number", true)
            ->status($stay->reservation_status_description)
        ;

        $h->hotel()
            ->name($stay->pms_property_name)
            ->noAddress()
        ;

        $h->booked()
            ->checkIn2($stay->arrival)
            ->checkOut2($stay->departure)
            ->guests($stay->noOfGuests)
        ;

        if ($h->getStatus() == 'Cancelled') {
            $h->general()->cancelled();
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }
}
