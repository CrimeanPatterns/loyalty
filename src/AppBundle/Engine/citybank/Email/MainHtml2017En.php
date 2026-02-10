<?php

// bcdtravel

namespace AwardWallet\Engine\citybank\Email;

class MainHtml2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = $its = [];

        $hotels = $this->http->XPath->query('//td[text() = "Hotel"]/ancestor::table[2]');

        if ($hotels->length > 0) {
            $type[] = 'parseHotel';
            $its = $this->parseHotel($hotels, $parser->getHTMLBody());
        }

        $cars = $this->http->XPath->query('//td[text() = "Your Car"]/ancestor::table[2]');

        if ($cars->length > 0) {
            $type[] = 'parseCar';
            $its = $this->parseCar($cars, $parser->getHTMLBody());
        }

        $total = $this->http->FindSingleNode('//td[contains(.,"Charge to ")]/following-sibling::td[1]');
        //$points = $this->http->FindSingleNode('//td[contains(.,"Points redeemed for this reservation")]/following-sibling::td[1]');

        return [
            'parsedData'  => ['Itineraries' => $its],
            'TotalCharge' => [
                'Amount'   => (float) preg_replace('/[^\d.]+/', '', $total),
                'Currency' => preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/'], ['', 'EUR', 'USD'], $total),
            ],
            'emailType' => join(', ', $type),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
                && $this->stripos($headers['from'], ['@travelcenter1.bankofamerica.com']) !== false
                && $this->stripos($headers['subject'], ['Itinerary for ']) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Bank of America') !== false
                && $this->stripos($parser->getHTMLBody(), ['Travel Center booking number:']) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelcenter1.bankofamerica.com') !== false;
    }

    protected function parseHotel(\DOMNodeList $hotels, $html)
    {
        $its = [];

        foreach ($hotels as $root) {
            $i = ['Kind' => 'R'];
            $i['TripNumber'] = $this->http->FindSingleNode('//td[contains(text()," booking number:")]', null, false, '/:\s*([\w-]+)/');
            $i['HotelName'] = $this->http->FindSingleNode('.//td[text()="Hotel"]/ancestor::tr[2]/following-sibling::tr[1]', $root);
            $str = $this->match('/^(.+?)\s*Phone:\s*([)(+\s\d-]+)(?:.*?Fax:\s*([)(+\s\d-]+))?/', $this->http->FindSingleNode('.//td/strong[contains(text(), "Phone:")]/ancestor::td[2]', $root), true);

            if (!empty($str)) {
                $i['Address'] = $str[0];
                $i['Phone'] = $str[1];
                $i['Fax'] = $str[2];
            }
            $i['CheckInDate'] = strtotime($this->http->FindSingleNode('.//text()[contains(.,"Check-in:")]/ancestor::td[1]', $root, false, '/:\s*(.+)/'));
            $i['CheckOutDate'] = strtotime($this->http->FindSingleNode('.//text()[contains(.,"Check-out:")]/ancestor::td[1]', $root, false, '/:\s*(.+)/'));
            $i['Rooms'] = (int) $this->http->FindSingleNode('.//text()[contains(.,"Room(s):")]/ancestor::td[1]', $root, false, '/:\s*(\d+)$/');
            $i['ConfirmationNumber'] = $this->http->FindSingleNode('.//text()[contains(.,"Hotel confirmation number:")]/ancestor::td[1]', $root, false, '/:\s*([\w-]+)\b/');
            $i['GuestNames'] = $this->http->FindNodes('.//text()[contains(.,"must check in to this room")]', $root, '/^(.+?)\s+must check/');
            $i['RoomTypeDescription'] = $this->http->FindSingleNode('.//text()[contains(.,"Room description:")]/ancestor::td[1]/following-sibling::td[1]', $root);
            $i['CancellationPolicy'] = $this->http->FindSingleNode('//text()[contains(.,"Hotel policies and additional")]/ancestor::table[1]');
            $its[] = $i;
        }

        return $its;
    }

    protected function parseCar(\DOMNodeList $cars, $html)
    {
        $its = [];

        foreach ($cars as $root) {
            $i = ['Kind' => 'L'];
            $number = $this->http->FindNodes('//td[contains(text(),"Booking Reference:")]');

            if (count($number) == 1 && preg_match('/^(.+?)\s+Booking Reference:\s*([\w-]+)/s', $number[0], $matches)) {
                $i['RentalCompany'] = $matches[1];
                $i['Number'] = $matches[2];
            }
            $i['TripNumber'] = $this->http->FindSingleNode('//td[contains(text()," booking number:")]', null, false, '/:\s*([\w-]+)/');
            $i['RenterName'] = $this->http->FindNodes('//td[contains(text(),"Car reservation under:")]/following-sibling::td[1]');
            $pickup = $this->match('/Pick-up:(.+?)\|(.+?)\|/u', $this->http->FindSingleNode('.//text()[contains(.,"Pick-up:")]/ancestor::td[1]', $root), true);

            if (!empty($pickup)) {
                $i['PickupDatetime'] = strtotime($pickup[0]);
                $i['PickupLocation'] = $pickup[1];
                $i['PickupPhone'] = $this->http->FindSingleNode('(.//text()[contains(.,"Phone:")]/ancestor::td[1])[2]', $root, false, '/:\s*(.+)/');
            }
            $dropoff = $this->match('/Drop-off:(.+?)\|(.+?)\|/u', $this->http->FindSingleNode('.//text()[contains(.,"Drop-off:")]/ancestor::td[1]', $root), true);

            if (!empty($dropoff)) {
                $i['DropoffDatetime'] = strtotime($dropoff[0]);
                $i['DropoffLocation'] = $dropoff[1];
                $i['DropoffPhone'] = $this->http->FindSingleNode('(.//text()[contains(.,"Phone:")]/ancestor::td[1])[2]', $root, false, '/:\s*(.+)/');
            }

            $car = array_filter($this->http->FindNodes('.//text()[contains(.,"Pick-up:")]/ancestor::table[1]/following-sibling::table[1]//tr', $root));

            if (!empty($car)) {
                $i['CarModel'] = array_shift($car);
                $i['CarType'] = join(', ', array_filter($car, function ($v) {
                    return $this->stripos($v, ['Passenger', 'Luggage']) === false;
                }));
            }
            $its[] = $i;
        }

        return $its;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function stripos($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }
}
