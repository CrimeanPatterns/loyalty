<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAcerentacar extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PROFILE_PAGE = 'https://www.www.acerentacar.com/MemberAccount.aspx';

    private $memberId = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        //$proxy = $this->http->getLiveProxy('https://www.acerentacar.com/');
        //$this->http->SetProxy($proxy);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.acerentacar.com/');

//        if (!$this->http->ParseForm('Form1')) {
//            return $this->checkErrors();
//        }
        $data = json_encode([
            'email'      => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
            'rememberMe' => true,
        ]);

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.acerentacar.com:6093/api/user/signin', $data, $headers);
        $this->http->RetryCount = 0;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Error in '/' Application.
        if ($message = $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->FindPreg('/^eyJ/')) {
            $this->http->setDefaultHeader("Authorization", "Bearer {$this->http->Response['body']}");

            return true;
        }
        $error = $this->http->JsonLog();

        if (isset($error->title) && in_array($error->title, ['Unauthorized', 'Not Found'])) {
            throw new CheckException('Username or password are incorrect', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/^Could not authenticate user$/")) {
            throw new CheckException('Your email or password is incorrect', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $base64 = $this->http->FindPreg("/\.([^.]+)/");

        if (!$base64) {
            return;
        }

        $base64response = $this->http->JsonLog(base64_decode($base64));

        if (!$base64response->memberId) {
            return;
        }

        $this->memberId = $base64response->memberId;
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        /*
        $this->http->GetURL("https://api.acerentacar.com:6093/api/pointstransaction/{$this->memberId}", $headers);
        // Balance - Points available for awards
        $this->SetBalance($this->http->Response['body']);
        */
        $this->SetBalance($base64response->points ?? null);

        $this->http->GetURL("https://api.acerentacar.com:6093/api/member", $headers);
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
    }

    public function ParseItineraries()
    {
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $this->http->GetUrl("https://api.acerentacar.com:6093/api/booking/member", $headers);
        $response = $this->http->JsonLog();

        // No Reservations at this Time.
        if ($this->http->Response['body'] === '[]') {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        foreach ($response as $item) {
            $this->sendNotification('check it // MI');

            $this->CheckConfirmationNumberInternal([
                'ConfNo'   => $item->confirmationNumber,
                'LastName' => $item->lastName,
                'Email'    => $item->emailAddress,
            ], $it, $item);
        }

        return [];
    }

    public function ParseItinerary($data, $item = null)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();

        if (!$data) {
            $this->logger->error("something went wrong");

            return;
        }

        $r->general()
            ->confirmation($data->confirmationNumber);

        if (isset($item) && $item->bookingStatusId == 2) {
            $r->general()->cancelled();
        }

        $r->car()
            ->type($data->vehicleDriveType)
            ->model($data->vehicleModel)
            ->image("https://acerentacar.com/_next/image?url=%2FCarPics%2F{$data->confirmationNumber}&w=384&q=75");

        $r->pickup()->phone($data->phoneNumber);
        $r->pickup()->date2($data->pickupDateTime);
        $r->dropoff()->date2($data->dropoffDateTime);

        $this->http->GetUrl("https://api.acerentacar.com:6093/api/location/{$data->pickupLocationCode}");
        $location = $this->http->JsonLog(null, 1);
        $r->pickup()
            ->location($location->location->locationName)
            ->phone($location->location->phoneNumber)
            ->detailed()->address($location->location->address1)->city($location->location->city)->state($location->location->state);
        $this->http->GetUrl("https://api.acerentacar.com:6093/api/location/{$data->dropoffLocationCode}");
        $location = $this->http->JsonLog(null, 1);
        $r->dropoff()
            ->location($location->location->locationName)
            ->phone($location->location->phoneNumber)
            ->detailed()->address($location->location->address1)->city($location->location->city)->state($location->location->state);

        $r->price()->total($data->totalAmount);
        $r->price()->currency($data->currencyCode);

        foreach ($data->fees as $fee) {
            $r->price()->fee($fee->feeName, $fee->totalFee);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 30,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "Email" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.bangkokair.com/managing-my-booking";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it, $item = null)
    {
        $this->http->LogHeaders = true;
        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.acerentacar.com:6093/api/booking/{$arFields['ConfNo']}/{$arFields['LastName']}/{$arFields['Email']}", $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if ($this->http->Response['code'] == 500) {
            return 'Request failed. Please try again and contact our Customer Experience team if you are not able to view your reservation.';
        }

        if (empty($response)) {
            return null;
        }

        $this->ParseItinerary($response, $item);

        return null;
    }

    private function normalizeSimpleXML($obj, &$result)
    {
        $data = $obj;

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $res = null;
                $this->normalizeSimpleXML($value, $res);

                if (($key == '@attributes') && ($key)) {
                    $result = $res;
                } else {
                    $result[$key] = $res;
                }
            }
        } else {
            $result = $data;
        }
    }

    private function XML2JSON($xml)
    {
        $result = null;
        $xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $xml);
        $this->normalizeSimpleXML(simplexml_load_string($xml), $result);

        return json_encode($result);
    }
}
