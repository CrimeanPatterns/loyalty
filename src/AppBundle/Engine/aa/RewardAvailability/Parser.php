<?php

namespace AwardWallet\Engine\aa\RewardAvailability;

use AwardWallet\Engine\ProxyList;

class Parser extends \TAccountChecker
{
    use ProxyList;

    public $isRewardAvailability = true;
    private $depData;
    private $arrData;
    private $index;

    private $userAgents = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/115.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6.1 Safari/605.1.15',
    ];

    public static function getRASearchLinks(): array
    {
        return ['https://www.aa.com/search' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->index = random_int(0, count($this->userAgents) - 1);
        $this->http->setUserAgent($this->userAgents[$this->index]);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
//        $this->setProxyBrightData();
        $this->setProxyGoProxies(null, 'uk');
    }

    public function IsLoggedIn()
    {
        return true;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD', // !important
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['Adults'] > 9) {
            $this->SetWarning("you can check max 9 travellers");

            return ['routes' => []];
        }
        $settings = $this->getRewardAvailabilitySettings();

        if (!in_array($fields['Currencies'][0], $settings['supportedCurrencies'])) {
            $fields['Currencies'][0] = $settings['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['DepDate'] > strtotime('+360 day')) {
            $this->SetWarning('The requested departure date is too late.');

            return ['routes' => []];
        }

        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type'    => 'application/json',
            'Origin'          => 'https://www.aa.com',
            'Refer'           => 'https://www.aa.com/booking/choose-flights/1',
        ];

        $postData = '{"metadata":{"selectedProducts":[],"tripType":"OneWay","udo":{}},"passengers":[{"type":"adult","count":' . $fields['Adults'] . '}],"requestHeader":{"clientId":"AAcom"},"slices":[{"allCarriers":true,"cabin":"","departureDate":"' . date("Y-m-d", $fields['DepDate']) . '","destination":"' . $fields['ArrCode'] . '","destinationNearbyAirports":false,"maxStops":null,"origin":"' . $fields['DepCode'] . '","originNearbyAirports":false}],"tripOptions":{"corporateBooking":false,"fareType":"Lowest","locale":"en_US","pointOfSale":null,"searchType":"Award"},"loyaltyInfo":null,"version":"","queryParams":{"sliceIndex":0,"sessionId":"","solutionSet":"","solutionId":""}}';

        $this->http->PostURL('https://www.aa.com/booking/api/search/itinerary', $postData, $headers, 20);
        $flightResult = $this->http->JsonLog($this->http->Response['body'], 1, true);

        if (!empty($flightResult['error'])) {
            if ($flightResult['error'] == '309') {
                $this->SetWarning("No flights. Try another criteria search");
            } else {
                $this->sendNotification("unknown error // VM");

                throw new \CheckException('unknown error', ACCOUNT_ENGINE_ERROR);
            }
        }

        $routes = [];

        $routId = 0;

        foreach ($flightResult['slices'] as $sliceKey => $slice) {
            foreach ($slice['pricingDetail'] as $pricingDetailKey => $pricingDetailValue) {
                if (isset($pricingDetailValue['perPassengerDisplayTotal']['amount'])
                    && ((int) $pricingDetailValue['perPassengerDisplayTotal']['amount'] === 0)) {
                    continue;
                }

                $classOfService = $this->decodeClassOfService($pricingDetailValue['productType']); //productType	"COACH"

                $routes[$routId] = [
                    'num_stops' => $slice['stops'],
                    'tickets'   => $pricingDetailValue['seatsRemaining'] ?: null,
                    'payments'  => [
                        'currency' => $pricingDetailValue['perPassengerDisplayTotal']['currency'],
                        'taxes'    => $pricingDetailValue['perPassengerDisplayTotal']['amount'],
                    ],
                    'redemptions' => [
                        'miles'   => $pricingDetailValue['perPassengerAwardPoints'],
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'classOfService' => $classOfService,
                ];

                foreach ($slice['segments'] as $segmentKey => $segment) {
                    if (count($segment['legs']) > 1) {
                        $this->sendNotification("Legs count > 1 // VM");
                        $this->logger->debug("Legs count > 1 slice id = {$sliceKey} segment id = {$segmentKey} " . print_r($segment['legs'], 1));

                        unset($routes[$routId]);

                        continue 3;
                    }

                    $segmentProductDetails = $this->getSegmentProductDetails($segment['legs'], $pricingDetailValue['productType']);

                    $routes[$routId]['connections'][$segmentKey] = [
                        'num_stops' => 0,
                        'departure' => [
                            'date'    => $this->decodeDate($segment['departureDateTime']),
                            'airport' => $segment['origin']['code'],
                        ],
                        'arrival' => [
                            'date'    => $this->decodeDate($segment['arrivalDateTime']),
                            'airport' => $segment['destination']['code'],
                        ],
                        'cabin'          => $this->decodeCabin($segmentProductDetails['cabinType']),
                        'flight'         => [$segment['flight']['carrierCode'] . $segment['flight']['flightNumber']],
                        'airline'        => $segment['flight']['carrierCode'],
                        'aircraft'       => $segment['legs'][0]['aircraft']['code'],
                        'meal'           => $this->decodeMeal($segmentProductDetails['meals']),
                        'fare_class'     => $segmentProductDetails['bookingCode'],
                        'classOfService' => $this->decodeClassOfService($segmentProductDetails['cabinType']),
                    ];
                }
                $routId++;
            }
        }

        $this->logger->debug('Parsed data:' . $routId);
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function decodeCabin($classOfService)
    {
        $classOfService = strtolower($classOfService);
        $classOfService[0] = strtoupper($classOfService[0]);

        switch ($classOfService) {
            case 'Coach':
            case 'Main':
            case 'Partner Premium':
                return 'economy';

            case 'Premium':
            case 'Premium Coach':
            case 'Premium_economy':
                return 'premiumEconomy';

            case 'Business':
            case 'Partner Business':
                return 'business';

            case 'First':
            case 'First Class':
                return 'firstClass';
        }
        $this->sendNotification("check cabin data: {$classOfService} // VM");

        return null;
    }

    private function getSegmentProductDetails($legs, $cabin, $legsKey = 0)
    {
        $cabin = strtoupper($cabin);
        $segmentProductDetails = [];
        $cabinType = '';

        foreach ($legs[$legsKey]['productDetails'] as $productDetailsKey => $productDetailsItem) {
            if (strtoupper($productDetailsItem['productType']) === $cabin) {
                $segmentProductDetails = $productDetailsItem;

                break;
            }
        }

        //$this->sendNotification("check SegmentProductDetails data: {$cabin} // VM");

        return $segmentProductDetails;
    }

    private function decodeClassOfService($classOfService)
    {
        $classOfService = strtolower($classOfService);

        switch ($classOfService) {
            case 'coach':
                return 'Main';

            case 'premium_economy':
                return 'Economy';

            case 'business':
                return 'Business';

            case 'first':
                return 'First';
        }
        $this->sendNotification("check classOfService data: {$classOfService} // VM");

        return null;
    }

    private function decodeMeal($meal)
    {
        $mealString = '';

        if (empty($meal) && !is_array($meal)) {
            return $mealString;
        }

        foreach ($meal as $item) {
            switch ($item) {
                case 'BS':
                    return 'Beverage service';

                case 'R':
                    return 'Refreshments';

                case 'L':
                    return 'Lunch';

                case 'F':
                case 'S':
                    return 'Snacks (for a fee)';

                case 'B':
                    return 'Breakfast';

                case 'D':
                    return 'Dinner';

                case 'M':
                    return 'Meal';

                case 'G':
                    return 'Dinner, Breakfast';
            }
            $this->sendNotification("check meal data: {$item} // VM");
        }

        return $mealString;
    }

    private function decodeDate($dateString)
    {
        $date = new \DateTime($dateString);

        return $date->format('Y-m-d H:i');
    }
}
