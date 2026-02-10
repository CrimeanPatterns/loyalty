<?php

namespace AwardWallet\Engine\ebookers\Email;

class ThreeTrip extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ThreeTrip',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'travellercare@ebookers.com') !== false
            || isset($headers['subject']) && preg_match("#Booking confirmation#", $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'travellercare@ebookers.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//span[contains(text(), 'ebookers record locator:')]")->length > 0;
    }

    protected function parseEmail()
    {
        $its = [];
        $its['RecordLocator'] = $this->http->FindSingleNode("//strong[contains(normalize-space(.), 'ebookers record locator')]", null, true, "#[\w\s]+ ([\w\d]+)#");
        $totalCharge = $this->http->FindNodes("//tr[contains(normalize-space(.), 'Total trip cost')]/th/following-sibling::td/span");

        if (preg_match("#(\S{1})([\d.,]+)#", array_shift($totalCharge), $var)) {
            $its['Currency'] = 'GBP';
            $its['TotalCharge'] = preg_replace("#(\.)#", ',', $var[2]);
        }
//        AirTrip
        $flightRoots = $this->http->XPath->query("//h3[contains(normalize-space(.), 'Flight information')]");

        foreach ($flightRoots as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
//            $it['Currency'] = array_shift($this->http->FindNodes("following::tr[contains(normalize-space(.), 'Booked together')]/th/following-sibling::td/span", $root, true, "#\s*(\S{1})[\d\S]+#"));
//            $totalCharge = $this->http->FindNodes("following::tr[contains(normalize-space(.), 'Total trip cost')]/th/following-sibling::td/span", $root);
//            if(preg_match("#(\S{1})([\d.,]+)#", array_shift($totalCharge), $var)){
//                $it['Currency'] = 'GBP';
//                $it['TotalCharge'] = preg_replace("#(\.)#", ',', $var[2]);
//            }
//            $it['rec'] = $this->http->FindNodes(".", $root);
            $it['Passengers'] = $this->http->FindNodes("preceding::th[contains(text(), 'Traveller ')]/following::strong[1]", $root);
            $it['RecordLocator'] = $this->http->FindSingleNode("//p[contains(text(), 'Virgin Atlantic record locator')]/strong");
            $rows = $this->http->XPath->query(".//following::td[3]//tr[position() = 1 or position() = 8]/td[2]", $root);

            foreach ($rows as $row) {
                $seg = [];
//                Date
                $date = $this->http->FindSingleNode(".", $row);
//                DepName
                $seg['DepName'] = $this->http->FindSingleNode("ancestor::tr/following-sibling::tr[2]/td[2]/text()[1]", $row);
//                DepCode
                $seg['DepCode'] = $this->http->FindSingleNode("ancestor::tr/following-sibling::tr[2]/td[2]/*", $row, true, "#([\w]{3})#");
//                Class, plane
                $airlineName = $this->http->FindSingleNode("ancestor::tr/following-sibling::tr[1]/td[4]/span", $row);

                if (preg_match("#([\w\s]+) ([\d]{2,5})#", $airlineName, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
//                ArrName
                $seg['ArrName'] = $this->http->FindSingleNode("ancestor::tr/following-sibling::tr[4]/td[2]/text()[1]", $row);
//                ArrCode
                $seg['ArrCode'] = $this->http->FindSingleNode("ancestor::tr/following-sibling::tr[4]/td[2]/*", $row, true, "#([\w]{3})#");
//                depTime
                $seg['DepDate'] = strtotime($date . '2016' . ' ' . $this->http->FindSingleNode("ancestor::tr/following-sibling::tr[2]/td[1]/span", $row));
//                duration
                $seg['Duration'] = $this->http->FindSingleNode("ancestor::tr/td[3]", $row, true, "#([\w\d\s]+) Total#");
//                arrTime
                $seg['ArrDate'] = strtotime($date . '2016' . ' ' . $this->http->FindSingleNode("ancestor::tr/following-sibling::tr[4]/td[1]", $row));
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }
//        HotelTrip
        $hotelRoots = $this->http->XPath->query("//h3[contains(normalize-space(.), 'Hotel Information')]");

        foreach ($hotelRoots as $root) {
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Hotel confirmation')]/following-sibling::text()", $root);
//            $it['ConfirmationNumber'] = $this->http->FindSingleNode("following::span[1]/following-sibling::span", $root);
//            HotelName
            $it['HotelName'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Hotel')]/ancestor::p/following-sibling::p[1]/strong[1]", $root);
//            Address
            $it['Address'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Hotel confirmation')]/ancestor::ul/following-sibling::p/text()[1]", $root);
//            Phone
            $it['Phone'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Hotel confirmation')]/ancestor::ul/following-sibling::p/*[3]", $root);
//            Fax
            $it['Fax'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Hotel confirmation')]/ancestor::ul/following-sibling::p/*[5]", $root);
            $timeInOut = $this->http->FindSingleNode("following::strong[contains(text(), 'Hotel check-in')]/following::text()[1]", $root);

            if (preg_match("#([\S\w]+ [\w]+) ([\S\s\W]+)#", $timeInOut, $m)) {
                $timeIn = $m[1];
                $timeOut = $m[2];
            }

            if (empty($timeIn)) {
                return;
            }
//            CheckinDate, CheckoutDate
            $it['CheckInDate'] = strtotime($this->http->FindSingleNode("following::strong[contains(text(), 'Check-in')]/following::text()[1]", $root, true, "#(.*) |#") . ' ' . $timeIn);
            $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("following::strong[contains(text(), 'Check-out')]/following::text()[1]", $root) . ' ' . $timeOut);
//            Rooms
            $it['Rooms'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Room(s)')]", $root, true, "#Room\S+ ([\d]+)#");
//            $it['Guests'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Room')]/following-sibling::strong[contains(text(), 'Guest')]", $root, true, "#Guest\S+ (\d+)#");
//            $it['night'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Room')]/following-sibling::strong[contains(text(), 'Night')]", $root, true, "#Night\S+ (\d+)#");
            $childrensAdults = $this->http->FindSingleNode("following::p[contains(text(), 'Adults')]", $root);

            if (preg_match("#Adults: (\d+), Children: (\d+)#", $childrensAdults, $math)) {
                $it['Guests'] = $math[1];
                $it['Kids'] = $math[2];
            }
//            RoomTypeDescription
            $it['RoomTypeDescription'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Room description')]/following::text()[1]", $root);
            $its[] = $it;
        }
//        CarRental
        $carRoots = $this->http->XPath->query("//h3[contains(normalize-space(.), 'Car information')]");

        foreach ($carRoots as $root) {
            $it = ['Kind' => 'L'];
            $it['Number'] = $this->http->FindSingleNode("following::p[contains(normalize-space(.), 'Car Booking Reference')]", $root, true, "#Booking Reference: ([\d\w]+)#");
            $pickup = $this->getCarLocation('Pick-up', $root);

            if (preg_match("#([\w\S\s]+) (\w+ \w+)#", $pickup, $mathec)) {
                $it['PickupLocation'] = $mathec[2];
                $it['PickupDatetime'] = strtotime(preg_replace("#([|]+)#", '', $mathec[1]));
            }
            $it['PickupPhone'] = $this->http->FindSingleNode("following::strong[contains(text(), 'Phone')]/following::text()[2]", $root);
            $pickoff = $this->getCarLocation('Drop-off:', $root);

            if (preg_match("#([\w\S\s]+) (\w+ \w+)#", $pickoff, $v)) {
                $it['DropoffDatetime'] = strtotime(preg_replace("#([|]+)#", '', $v[1]));
                $it['DropoffLocation'] = $v[2];
            }
            $it['AccountNumbers'] = $this->http->FindSingleNode("following::th[contains(text(), 'Billing Account number:')]/following::td[1]", $root);
            $its[] = $it;
        }

        return $its;
    }

    protected function getCarLocation($str, $root)
    {
        return $this->http->FindSingleNode("following::strong[contains(text(), '{$str}')]/following::strong[1]/text()[1]", $root);
    }
}
