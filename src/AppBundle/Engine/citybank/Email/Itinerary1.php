<?php

namespace AwardWallet\Engine\citybank\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "citybank/it-1.eml, citybank/it-2.eml, citybank/it-3.eml, citybank/it-4.eml, citybank/it-5.eml";

    public function ParseReservationStatusHotel()
    {
        $result = ["Kind" => "R"];

        if ($this->http->FindSingleNode("//td[contains(text(), 'Your Reservation Status')]/parent::tr/following-sibling::tr[contains(.,'Status')]/following-sibling::tr[normalize-space()]/td[2]") == "Confirmed") {
            $result["Status"] = "Confirmed";
        }

        $block = $this->findFirstNode("//tr[contains(., 'Check-In') and contains(., 'Check-Out') and not(contains(., 'Booking Confirmation'))]/parent::*");

        if (!$block) {
            return null;
        }

        $result["HotelName"] = beautifulName($this->http->FindSingleNode("(tr[1]/td[1])[1]", $block));
        $result["Address"] = trim($this->http->FindSingleNode("(tr[1]/td[1])[1]/following-sibling::td[1]", $block) . ", " . $this->http->FindSingleNode("tr[1]/td[1]/span[3]", $block), " ,");

        if (!$result["Address"]) { // type 2
            $result["Address"] = $result["HotelName"];
            $result["HotelName"] = $this->http->FindSingleNode("(//*[contains(text(), 'Reservation Details')]/ancestor-or-self::tr[1]/following-sibling::tr[1]//table[1]//tr[1])[1]", null, true, "#Hotel\s*\-\s*(.*?)\s*\(#");
            $result["Address"] = trim(str_replace($result["HotelName"], '', $result["Address"]));
        }

        $dates = $this->http->FindSingleNode("//*[contains(text(), 'Check-In:')]/ancestor-or-self::td[1]");

        if (preg_match("#Check\-In\s*:\s*(\w{3},\s*\w{3}\s*\d+,\s*\d{4})#ims", $dates, $m)) {
            $result["CheckInDate"] = strtotime($m[1]);
        }

        //$dates = $this->http->FindSingleNode("//*[contains(text(), 'Check-Out:')]/ancestor-or-self::td[1]");
        if (preg_match("#Check\-Out\s*:\s*(\w{3},\s*\w{3}\s*\d+,\s*\d{4})#ims", $dates, $m)) {
            $result["CheckOutDate"] = strtotime($m[1]);
        }

        if (!isset($result["CheckOutDate"])) {
            $dates = $this->http->FindSingleNode("//*[contains(text(), 'Check-Out:')]/ancestor-or-self::td[1]");

            if (preg_match("#Check\-Out\s*:\s*(\w{3},\s*\w{3}\s*\d+,\s*\d{4})#ims", $dates, $m)) {
                $result["CheckOutDate"] = strtotime($m[1]);
            }
        }

        $result["Guests"] = $this->http->FindSingleNode("tr[contains(., 'Adult(s)')]/td//td[contains(., 'Adult(s)')]", $block, true, "/(\d+) Adult\(s\)/");
        $result["Kids"] = $this->http->FindSingleNode("tr[contains(., 'Child(ren)')]/td//td[contains(., 'Child(ren)')]", $block, true, "/(\d+) Child\(ren\)/");
        $result["RoomType"] = $this->http->FindSingleNode("tr[contains(., 'Room Type')]/td//td[contains(., 'Room Type')]/div[contains(text(), 'Room Type')]/span", $block);

        $result["ConfirmationNumber"] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Confirmation Number:')]/ancestor-or-self::td[1]", $block, true, "#Booking Confirmation Number:\s*([\d\w]+)#");

        if (!$result["ConfirmationNumber"]) {
            $result["ConfirmationNumber"] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Confirmation')]/ancestor-or-self::td[1]", null, true, "#Booking Confirmation\s*\#\s*:\s*([\d\w]+)#");
        }

        $result["GuestNames"] = $this->http->FindSingleNode("tr[contains(., 'Booking Confirmation')]/td//td[contains(., 'Booking Confirmation')]/following-sibling::td[1]", $block);
        $result["Total"] = $this->http->FindSingleNode("//td[contains(text(), 'Total Charges')]/following-sibling::td[2]", null, true, "/[\d\.]+/");

        return ["Itineraries" => [$result], "Properties" => []];
    }

    public function ParseReservationStatusFlight()
    {
        $result = [
            "Kind"         => "T",
            "TripSegments" => [],
        ];

        $block = $this->findFirstNode("//tr[td[contains(text(), 'Reservation Details')]]/following-sibling::tr/td[contains(., 'Passenger')]");

        $passBlock = $this->findFirstNode("table//table[contains(., 'Passenger')][following-sibling::table[not(contains(., 'Passenger'))]]", $block);

        if (!$passBlock) {
            return null;
        }

        $result["RecordLocator"] = $this->http->FindSingleNode("//td[contains(text(), 'Airline Reference')]", null, true, "/[\s\:\#]([A-Z0-9]{6})$/");

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode("//td[contains(text(), 'Agency Reference')]", null, true, "/[\s\:\#]([A-Z0-9]{6,})$/");
        }

        $result["Status"] = $this->http->FindSingleNode('//td[contains(text(), "Your Reservation Status")]/parent::tr/following-sibling::tr[1]/td//tr[contains(., "Status")]/following-sibling::tr/td[2]');
        $result["Passengers"] = implode(", ", array_filter($this->http->FindNodes("descendant::td[contains(., 'Passenger')]/span[last()]", $passBlock), "strlen"));
        $rows = $this->http->XPath->query("following-sibling::table[1]//tr[count(td) = 5]", $passBlock);

        for ($i = 0; $i < $rows->length; $i++) {
            $segment = [];
            $row = $rows->item($i);

            if (preg_match("/^([A-Z\d]{2})\s*\#\s*(\d+)$/", $this->http->FindSingleNode("td[1]", $row), $m)) {
                $segment["FlightNumber"] = $m[2];
                $segment["AirlineName"] = $m[1];
            }
            $segment["DepDate"] = strtotime($this->http->FindSingleNode("td[2]/span[1]", $row) . " " . $this->http->FindSingleNode("td[2]/span[2]", $row));
            $segment["DepName"] = trim($this->http->FindSingleNode("td[2]/span[3]", $row, true, "/^([^\(]+)\([A-Z]{3}/"), " ,");
            $segment["DepCode"] = $this->http->FindSingleNode("td[2]/span[3]", $row, true, "/\(([A-Z]{3})\)/");
            $segment["ArrDate"] = strtotime($this->http->FindSingleNode("td[3]/span[1]", $row) . " " . $this->http->FindSingleNode("td[3]/span[2]", $row));
            $segment["ArrName"] = trim($this->http->FindSingleNode("td[3]/span[3]", $row, true, "/^([^\(]+)\([A-Z]{3}/"), " ,");
            $segment["ArrCode"] = $this->http->FindSingleNode("td[3]/span[3]", $row, true, "/\(([A-Z]{3})\)/");
            $segment["Duration"] = $this->http->FindSingleNode("td[5]/span[1]", $row, true, "/\d+ hr \d+ min/");
            $segment["Cabin"] = $this->http->FindSingleNode("td[5]/span[2]", $row);
            $segment["BookingClass"] = $this->http->FindSingleNode("td[5]/span[3]", $row, true, "/\((.+)\)/");
            $result["TripSegments"][] = $segment;
        }

        return ["Itineraries" => [$result], "Properties" => []];
    }

    public function ParseReservationFlight()
    {
        $result = ["Kind" => "T", "TripSegments" => []];
        $rows = $this->http->XPath->query("//*[tr[contains(., 'Passenger')]][tr[contains(., 'Airline Reference Number') and not(contains(., 'Passenger'))]]/tr");
        $passengers = [];

        for ($i = 0; $i < $rows->length; $i++) {
            $row = $rows->item($i);

            if (preg_match("/Passenger \d+/", $this->http->FindSingleNode("td[1]", $row))) {
                $passengers[] = $this->http->FindSingleNode("td[2]", $row);
            }

            if (stripos($this->http->FindSingleNode("td[1]", $row), "Airline Reference Number") === 0) {
                $result["RecordLocator"] = $this->http->FindSingleNode("td[1]", $row, true, "/Airline Reference Number[\s\:]*([A-Z0-9]{6})/");

                if (empty($result["RecordLocator"])) {
                    $result["RecordLocator"] = $this->http->FindSingleNode("td[1]", $row, true, "/Agency Reference Number[\s\:]*([A-Z0-9]{6})/");
                }
            }
            $cells = $this->http->XPath->query("td[1]/table//tr/td", $row);

            if ($cells->length > 5) {
                $segment = [];
                $info = $this->http->FindNodes("text()", $cells->item(1));

                if (count($info) >= 3) {
                    if (preg_match("/^([A-Z0-9]{2})\s*\#\s*(\d+)$/", $info[0], $m)) {
                        $segment["AirlineName"] = $m[1];
                        $segment["FlightNumber"] = $m[2];
                    }
                    $segment["Cabin"] = $info[1];
                    $segment["Aircraft"] = $info[2];
                }

                if (preg_match("/([A-Z][a-z]{2} \d+\, \d{4} \d{1,2}:\d{2} [AP]M)([^\(]+) \(([A-Z]{3})/", CleanXMLValue($cells->item(2)->nodeValue), $m)) {
                    $segment["DepDate"] = strtotime($m[1]);
                    $segment["DepName"] = trim($m[2]);
                    $segment["DepCode"] = $m[3];
                }

                if (preg_match("/([A-Z][a-z]{2} \d+\, \d{4} \d{1,2}:\d{2} [AP]M)([^\(]+) \(([A-Z]{3})/", CleanXMLValue($cells->item(3)->nodeValue), $m)) {
                    $segment["ArrDate"] = strtotime($m[1]);
                    $segment["ArrName"] = trim($m[2]);
                    $segment["ArrCode"] = $m[3];
                }
                $segment["Duration"] = CleanXMLValue($cells->item(5)->nodeValue);
                $result["TripSegments"][] = $segment;
            }
        }

        if (count($passengers) > 0) {
            $result["Passengers"] = implode(", ", $passengers);
        }

        return ["Itineraries" => [$result], "Properties" => []];
    }

    public function ParseReservationCar()
    {
        $result = ['Kind' => 'L'];
        $http = $this->http;
        $xpath = $http->XPath;

        $result['Number'] = $http->FindSingleNode('//text()[contains(., "Booking Confirmation Number:")]/following-sibling::node()[1]');

        $result['PickupDatetime'] = strtotime(str_replace('-', ' ', implode(' ', $http->FindNodes('//text()[contains(., "Pick-Up Date/Time:")]/following-sibling::node()[count(./following-sibling::br) = 1]'))));
        $result['DropoffDatetime'] = strtotime(str_replace('-', ' ', implode(' ', $http->FindNodes('//text()[contains(., "Drop-Off Date/Time:")]/following-sibling::node()[count(./following-sibling::br) = 1]'))));

        $result['PickupLocation'] = implode(' ', $http->FindNodes('//text()[contains(., "Pick-Up Location:")]/following-sibling::node()'));
        $result['DropoffLocation'] = implode(' ', $http->FindNodes('//text()[contains(., "Drop-Off Location:")]/following-sibling::node()'));

        if ('Same as pick-up' === $result['DropoffLocation']) {
            $result['DropoffLocation'] = $result['PickupLocation'];
        }
        $result['RenterName'] = $http->FindSingleNode('//text()[contains(., "Booking Confirmation Number:")]/ancestor::td[1]/following-sibling::td[1]');

        if (preg_match('/(\S)?(\d+.\d+|\d+)\s*([^\d]+)?/ims', $http->FindSingleNode('//text()[contains(., "(Includes Taxes and Fees)")]/preceding-sibling::node()[last()]'), $matches)) {
            if (!empty($matches[1])) {
                if ('$' === $matches[1]) {
                    $result['Currency'] = 'USD';
                } else {
                    $result['Currency'] = $matches[1];
                }
            }

            if (!empty($matches[3])) {
                $result['Currency'] = $matches[3];
            }
            $result['TotalCharge'] = $matches[2];
        }

        if ($carDesc = explode('-', $http->FindSingleNode('//text()[contains(., "(Includes Taxes and Fees)")]/ancestor::td[1]/preceding-sibling::td[last()]'))) {
            $result['CarModel'] = $carDesc[0];

            if (isset($carDesc[1])) {
                $result['CarType'] = $carDesc[0];
                $result['CarModel'] = $carDesc[1];
            }
        }

        return [
            'Itineraries' => [$result],
            'Properties'  => [],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $this->http->SetBody($parser->getHTMLBody(), true);
        $type = $this->getEmailType();

        switch ($type) {
            case "ReservationStatusFlight":
                $result = $this->ParseReservationStatusFlight();

                break;

            case "ReservationStatusHotel":
                $result = $this->ParseReservationStatusHotel();

                break;

            case "ReservationFlight":
                $result = $this->ParseReservationFlight();

                break;

            case "ReservationStatusCar":
                $result = $this->ParseReservationCar();

                break;

            case "ReservationFlightAndHotel":
                $it1 = $this->ParseReservationFlight();
                $it2 = $this->ParseReservationStatusHotel();
                $result = ['Itineraries' => [reset($it1['Itineraries']), reset($it2['Itineraries'])]];

                break;

            default:
                $result = 'Undefined email type';

                break;
        }

        return [
            'parsedData' => $result,
            'emailType'  => $type,
        ];
    }

    public function getEmailType()
    {
        if ($this->http->XPath->query('//img[@alt="Flight Information"]')->length > 0
            && $this->http->XPath->query('//img[@alt="Hotel Information"]')->length) {
            return 'ReservationFlightAndHotel';
        }

        if ($this->http->XPath->query('//img[@alt="Flight Information"]')->length > 0) {
            return 'ReservationFlight';
        }

        if ($this->http->XPath->query('//img[@alt="Hotel Information"]')->length > 0) {
            return 'ReservationStatusHotel';
        }

        $status = $this->findFirstNode('//td[contains(text(), "Your Reservation Status")]');

        if ($status) {
            $type = $this->http->FindSingleNode("parent::tr/following-sibling::tr[1]/td//tr[contains(., 'Item')]/following-sibling::tr", $status);

            if (empty($type)) {
                $type = $this->http->FindSingleNode("parent::tr/following-sibling::tr[1][contains(., 'Item')]/following-sibling::tr[1]", $status);
            }

            if ($type && stripos($type, 'Hotel') === 0) {
                return 'ReservationStatusHotel';
            }

            if ($type && stripos($type, 'Flight') === 0) {
                return 'ReservationStatusFlight';
            }

            if ($type && stripos($type, 'Car') === 0) {
                return 'ReservationStatusCar';
            }
        }

        return 'Undefined';
    }

    public static function getEmailTypesCount()
    {
        return 5;
    }

    public function findFirstNode($xpath, $root = null)
    {
        $nodes = $this->http->XPath->query($xpath, $root);

        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (preg_match("#From:\s*TravelRewards@affinion.usaa.com#i", $parser->getPlainBody())) {
            return true;
        }

        if (preg_match("#From:(\s|<[^>]+>)*[^@]+@ScoreCardRewards#", $parser->getHtmlBody())) {
            return true;
        }

        return isset($this->http->Response['body']) && stripos($this->http->Response['body'], 'Citi ThankYou Rewards') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], "Citi ThankYou Rewards") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]travelcenter/ims', $from);
    }
}
