<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAlamo extends TAccountChecker
{
    use ProxyList;

    private $headers = [
        "Accept"                      => "application/json, text/plain, */*",
        "Accept-Encoding"             => "gzip, deflate, br",
        "Content-Type"                => "application/json",
        "locale"                      => "en_US",
        "domain_country_of_residence" => "US",
        "BRAND"                       => "ALAMO",
        "CHANNEL"                     => "WEB",
        "cdn_country_of_residence"    => "US",
        "Origin"                      => "https://www.alamo.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // incapsula workaround
//        $this->http->SetProxy($this->proxyDOP());
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->setProxyMount();

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://prd-east.webapi.alamo.com/gma-alamo/session/current", $this->headers, 30);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 403 && $this->http->FindPreg("/_Incapsula_Resource/")) {
            $this->DebugInfo = 'Incapsula';

            return false;
        }

        $this->http->GetURL("https://www.alamo.com/en/members.html");

        if ($this->http->Response['code'] != 200) {
            if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->DebugInfo = 'Access Denied';
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 5);
            }

            return $this->checkErrors();
        }
        $this->http->Form = [];
        $data = [
            "username"             => $this->AccountFields['Login'],
            "password"             => $this->AccountFields['Pass'],
            "remember_credentials" => true,
            "associate_profile"    => false,
        ];
        $this->http->PostURL("https://prd-east.webapi.alamo.com/gma-alamo/profile/login", json_encode($data), $this->headers);

        if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
            $this->DebugInfo = 'Incapsula';
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 5);

            return false;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->gbo->profile)) {
            return $this->loginSuccessful();
        }

        $message = $response->messages[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                in_array($message, [
                    "The password reset link has expired. Please reset again.",
                    "We're sorry, but there's something wrong with your email, member number or password. Please provide a valid email or member number and password to sign in to your account.",
                    "For your security, please update your password to meet our new secure login requirements.",
                    "We're sorry, but there is something wrong with your loyalty membership. Please call us for assistance.",
                    "We're sorry, your password has expired. Please reset your password and try again.",
                ])
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                in_array($message, [
                    "We are sorry. Something went wrong. Please try again or call us for assistance.",
                ])
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message)

        return $this->checkErrors();
    }

    public function Parse()
    {
        $result = $this->http->JsonLog(null, 0);
        //# Alamo Insider #
        $this->SetProperty("Number", $result->gbo->profile->profile->basic_profile->loyalty_data->loyalty_number ?? null);
        // Name
        $this->SetProperty("Name", beautifulName(($result->gbo->profile->profile->basic_profile->first_name ?? null) . ' ' . ($result->gbo->profile->profile->basic_profile->last_name ?? null)));
        //# Driver's License Expiration Date
        $this->SetProperty("LicenseExpirationDate", $result->gbo->profile->profile->license_profile->license_expiration_date ?? null);
        //# Driver's License Number
        $this->SetProperty("License", $result->gbo->profile->profile->license_profile->license_number ?? null);

        $message = $result->messageList->analyticsMessages->message ?? null;

        if (
            (isset($this->Properties['Number']) || isset($this->Properties['License']) || $message == "We're sorry for the inconvenience, but Nationalcar.com is currently experiencing technical difficulties. Please call 1 (844) 377-0180 and one of our advisors will be happy to assist you.")
            && isset($this->Properties['Name'])
        ) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->http->GetURL("https://prd-east.webapi.alamo.com/gma-alamo/trips/upcoming", $this->headers);
        $response = $this->http->JsonLog();
        // You have no upcoming reservations.
        if ($this->http->FindPreg("/^\{\"more_records_available\":false}$/")) {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (count($this->itinerariesMaster->getItineraries()) > 0) {
                    return $pastItineraries;
                }
            }// if ($this->ParsePastIts)
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $its = $response->upcoming_reservations ?? [];
        $this->logger->debug("Total " . count($its) . " reservations were found");

        foreach ($its as $it) {
            $confNo = $it->confirmation_number;
            $this->logger->info("Parse Rental #{$confNo}", ['Header' => 3]);
            $this->parseItinerary($confNo, $it->customer_first_name, $it->customer_last_name);
            /*
            $r = $this->itinerariesMaster->add()->rental();
            $r->general()
                ->confirmation($it->confirmation_number, 'Confirmation #')
                ->traveller(beautifulName($it->customer_first_name.' '.$it->customer_last_name), true);

            $r->program()->account($it->membership_number, false);

            $pickup = $it->pickup_location;
            $r->pickup()
                ->location($pickup->name)
                ->date2($it->pickup_time)
                ->phone($it->phones->formatted_phone_number)
            ;

            $r->pickup()->detailed()
                ->address(implode(' ', $pickup->address->street_addresses))
                ->city($pickup->address->city)
                ->country($pickup->address->country_code)
                ->zip($pickup->address->postal)
            ;
            if (isset($pickup->address->country_subdivision_name)) {
                $r->pickup()->detailed()->state($pickup->address->country_subdivision_name);
            }

            $dropoff = $it->return_location;
            $r->dropoff()
                ->location($dropoff->name)
                ->date2($it->return_time)
                ->phone($it->phones->formatted_phone_number)
            ;

            $r->dropoff()->detailed()
                ->address(implode(' ', $dropoff->address->street_addresses))
                ->city($dropoff->address->city)
                ->country($dropoff->address->country_code)
                ->zip($dropoff->address->postal)
            ;
            if (isset($dropoff->address->country_subdivision_name)) {
                $r->dropoff()->detailed()->state($dropoff->address->country_subdivision_name);
            }

            $path = null;
            foreach ($it->images as $image) {
                if ($image->name == 'SideProfile') {
                    $path = str_replace(['{width}', '{quality}'], ['768', 'high'], $image->path);
                }
            }
            $r->car()
                ->model($it->vehicle_details->make_model_or_similar_text)
                ->type($it->vehicle_details->name)
                ->image($path, true)
            ;

            $this->logger->debug('Parsed Rental:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
            */
        }// foreach ($pastIts as $pastIt)

        if ($this->ParsePastIts) {
            $this->parsePastItineraries();
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "firstName" => [
                "Caption"  => "First Name",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "lastName"  => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "ConfNo"    => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.alamo.com/en/reserve/view-modify-cancel.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->FindSingleNode("//title[contains(text(), 'Modify or Cancel a Car Rental Reservation - Alamo Rent a Car')]")) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $res = $this->parseItinerary($arFields['ConfNo'], $arFields['firstName'], $arFields['lastName']);

        if (is_string($res)) {
            return $res;
        }

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://prd-east.webapi.alamo.com/gma-alamo/session/current", $this->headers, 30);
        $this->http->RetryCount = 2;
        $result = $this->http->JsonLog();
        $username = $result->gbo->profile->profile->user_name ?? null;
        $loyaltyId = $result->gbo->profile->profile->basic_profile->loyalty_data->loyalty_number ?? null;
        $authState = $result->gma->logged_in ?? null;
        $this->logger->debug("[username]: {$username}");
        $loginMasked = preg_replace('/(\w).*(\w\@)/', '$1•••••$2', strtolower($this->AccountFields['Login']));
        $this->logger->debug("[masked login]: {$loginMasked}");
        $this->logger->debug("[loyaltyId]: {$loyaltyId}");

        if (
            $username && strtolower($username) == strtolower($this->AccountFields['Login'])
            || (
                $authState && $authState === true
                && $loyaltyId && strtolower($loyaltyId) == strtolower($this->AccountFields['Login'])
            )
            || $username && strtolower($username) == $loginMasked
        ) {
            return true;
        }

        return false;
    }

    private function parseItinerary($confNo, $firstName, $lastName)
    {
        $this->http->GetURL("https://prd-east.webapi.alamo.com/gma-alamo/reservations/retrieve?firstName={$firstName}&lastName={$lastName}&confirmationNumber={$confNo}", $this->headers);
        $response = $this->http->JsonLog(null, 4);
        $it = $response->session->gbo->reservation ?? null;

        if (!$it) {
            $this->logger->error("something went wrong");

            return $response->messages[0]->message ?? null;
        }

        $r = $this->itinerariesMaster->add()->rental();
        $r->general()
            ->confirmation($it->confirmation_number, 'Confirmation #')
            ->traveller(beautifulName($it->driver_info->first_name . ' ' . $it->driver_info->last_name), true)
        ;

        if (isset($it->created_time)) {
            $r->general()->date2($it->created_time);
        }

        if (isset($it->profile_details->loyalty_data->loyalty_number)) {
            $r->program()->account($it->profile_details->loyalty_data->loyalty_number, false);
        }

        $pickup = $it->pickup_location;
        $r->pickup()
            ->location($pickup->name)
            ->date2($it->pickup_time)
            ->phone($pickup->phones[0]->formatted_phone_number ?? null, true, true)
            ->detailed()
                ->address(implode(' ', $pickup->address->street_addresses))
                ->city($pickup->address->city)
                ->country($pickup->address->country_code)
                ->zip($pickup->address->postal)
        ;

        if (isset($pickup->address->country_subdivision_name)) {
            $r->pickup()->detailed()->state($pickup->address->country_subdivision_name);
        }

        $dropoff = $it->return_location;
        $r->dropoff()
            ->location($dropoff->name)
            ->date2($it->return_time)
            ->phone($pickup->phones[0]->formatted_phone_number ?? null, true, true)
            ->detailed()
                ->address(implode(' ', $dropoff->address->street_addresses))
                ->city($dropoff->address->city)
                ->country($dropoff->address->country_code)
                ->zip($dropoff->address->postal)
        ;

        if (isset($dropoff->address->country_subdivision_name)) {
            $r->dropoff()->detailed()->state($dropoff->address->country_subdivision_name);
        }

        $path = null;

        foreach ($it->car_class_details->images as $image) {
            if ($image->name == 'SideProfile') {
                $path = str_replace(['{width}', '{quality}'], ['768', 'high'], $image->path);
            }
        }
        $r->car()
            ->model($it->car_class_details->make_model_or_similar_text)
            ->type($it->car_class_details->name)
            ->image($path, true)
        ;

        $price_summary = $it->car_class_details->vehicle_rates[0]->price_summary ?? null;

        if ($price_summary) {
            $r->price()
                ->total(PriceHelper::cost($price_summary->total_charged))
    //            ->tax()
                ->currency(
                    $price_summary->payment_line_items->VEHICLE_RATE[0]->total_amount_view->code
                    ?? $price_summary->estimated_total_view->code
                )
                //->cost(PriceHelper::cost($price_summary->payment_line_items->VEHICLE_RATE[0]->total_amount_view->formatted_amount ?? null), false, true)
            ;
            $fees = $price_summary->payment_line_items->FEE;

            foreach ($fees as $fee) {
                $r->price()->fee($fee->description, PriceHelper::cost(abs($fee->total_amount_view->formatted_amount)));
            }

            if (isset($price_summary->payment_line_items->SAVINGS)) {
                $r->price()->discount(PriceHelper::cost(str_replace('-', '', $price_summary->payment_line_items->SAVINGS[0]->total_amount_view->formatted_amount)));
            }
        }

        $this->logger->debug('Parsed Rental:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return null;
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $this->http->PostURL("https://prd-east.webapi.alamo.com/gma-alamo/trips/past", "{\"membership_number\":\"{$this->Properties['Number']}\"}", $this->headers);
        $response = $this->http->JsonLog(null, 4);
        $pastIts = $response->trip_summaries ?? [];
        $this->logger->debug("Total " . count($pastIts) . " past reservations were found");

        foreach ($pastIts as $pastIt) {
            $r = $this->itinerariesMaster->add()->rental();
            $confNo = $pastIt->confirmation_number;
            $this->logger->info("Parse Past Rental #{$confNo}", ['Header' => 3]);

            if ($confNo === "0") {
                $r->general()->noConfirmation();
            } else {
                $r->general()
                    ->confirmation($confNo, 'Confirmation #');
            }

            $r->general()->traveller(beautifulName($pastIt->customer_first_name . ' ' . $pastIt->customer_last_name), true);

            $r->program()->account($pastIt->membership_number, false);

            $pickup = $pastIt->pickup_location;
            $r->pickup()
                ->location($pickup->name)
                ->date2($pastIt->pickup_time)
                ->detailed()
                    ->address(implode(' ', $pickup->address->street_addresses))
                    ->city($pickup->address->city)
                    ->country($pickup->address->country_code)
                    ->zip($pickup->address->postal)
            ;

            if (isset($pickup->address->country_subdivision_name)) {
                $r->pickup()->detailed()->state($pickup->address->country_subdivision_name);
            }

            $dropoff = $pastIt->return_location;
            $r->dropoff()
                ->location($dropoff->name)
                ->date2($pastIt->return_time)
                ->detailed()
                    ->address(implode(' ', $dropoff->address->street_addresses))
                    ->city($dropoff->address->city)
                    ->country($dropoff->address->country_code)
                    ->zip($dropoff->address->postal)
            ;

            if (isset($dropoff->address->country_subdivision_name)) {
                $r->dropoff()->detailed()->state($dropoff->address->country_subdivision_name);
            }

            $r->car()
                ->model(trim(($pastIt->vehicle_details->make ?? null) . " " . $pastIt->vehicle_details->model))
            ;

            $this->logger->debug('Parsed Rental:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
        }// foreach ($pastIts as $pastIt)

        return [];
    }
}
