<?php

namespace AwardWallet\Engine\ctrip\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "ctrip/it-1641290.eml, ctrip/it-2307309.eml, ctrip/it-2708862.eml, ctrip/it-2708863.eml";
    public $reBody = "ctrip.com";
    public $reBody2 = "Flight reservation confirmation";
    public $reBody3 = "Ctrip Hotel Reservation Department";
    public $reBody4 = "Room conditions";

    public $reFrom = "/ctrip\.com/";
    public $reSubject = "Confirmation for Air-Ticket Issue and Delivery";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "it-2685817.eml"
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text() ,'Order No.')]", null, true, "#Order No. (\d+)#");

                // TripNumber
                // Passengers
                $Passengers = $this->http->FindNodes("//*[contains(text(),'Passenger name(s)')]/../following-sibling::tr/td[1]");

                foreach ($Passengers as $k=>$p) {
                    if (strpos($p, '/') !== false) {
                        $p = explode('/', $p);
                        $Passengers[$k] = $p[0];
                        $Passengers[] = $p[1];
                    }
                }
                $it['Passengers'] = $Passengers;

                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(),'Total amount')]/../following-sibling::tr[1]/td[1]", null, true, "#([0-9,.]+)#"));

                // BaseFare
                $it['BaseFare'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(),'Total amount')]/../following-sibling::tr[1]/td[2]", null, true, "#([0-9,.]+)#"));

                // Currency
                $it['Currency'] = trim($this->http->FindSingleNode("//*[contains(text(),'Total amount')]/../following-sibling::tr[1]/td[1]", null, true, "#([^0-9,.]+)#"));

                // Tax
                $it['Tax'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(),'Total amount')]/../following-sibling::tr[1]/td[3]", null, true, "#([0-9,.]+)#"));

                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[contains(text(),'Flight No.')]/../following-sibling::tr";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments roots not found: $xpath", LOG_LEVEL_ERROR);
                }

                foreach ($segments as $root) {
                    $itsegment = [];

                    // FlightNumber
                    $FlightNumber = trim($this->http->FindSingleNode("./td[2]", $root));
                    $itsegment['FlightNumber'] = $FlightNumber ? re("#[A-Z]+(\d+)#", $FlightNumber) : FLIGHT_NUMBER_UNKNOWN;

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([^\)-]+)#");

                    // DepName
                    $itsegment['DepName'] = trim($this->http->FindSingleNode("./td[3]", $root, true, "#^[^\(]+#"));

                    // DepDate
                    $DepDate = strtotime(str_replace('/', '.', $this->http->FindSingleNode("./td[5]", $root)));
                    $itsegment['DepDate'] = $DepDate ? $DepDate : MISSING_DATE;

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[4]", $root, true, "#\(([^\)-]+)#");

                    // ArrName
                    $itsegment['ArrName'] = trim($this->http->FindSingleNode("./td[4]", $root, true, "#^[^\(]+#"));

                    // ArrDate
                    $ArrDate = strtotime(str_replace('/', '.', $this->http->FindSingleNode("./td[6]", $root)));
                    $itsegment['ArrDate'] = $ArrDate ? $ArrDate : MISSING_DATE;

                    // AirlineName
                    $itsegment['AirlineName'] = $FlightNumber ? re("#([A-Z]+)\d+#", $FlightNumber) : AIRLINE_UNKNOWN;

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./td[7]", $root);

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    // Meal
                    // Smoking
                    // Stops

                    $it['TripSegments'][] = $itsegment;
                }

                if (!isset($it['TripSegments'])) {
                    return null;
                }

                if ($this->is_bad_segments($it['TripSegments'])) {
                    unset($it['TripSegments']);
                }

                $itineraries[] = $it;
            },
            $this->reBody4 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(),'Booking no.')]/following-sibling::*[1]");

                // TripNumber
                // ConfirmationNumbers
                // HotelName
                $it['HotelName'] = $this->http->FindSingleNode("//*[contains(text(),'Address:')]/ancestor::table[3]//tr[1]//td[1]/a");

                // 2ChainName
                // CheckInDate
                $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check-in')]/following-sibling::*[1]", null, true, "#from\s+(.*?)\s+to#"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(str_replace('until ', '', $this->http->FindSingleNode("//*[contains(text(),'Check-out')]/following-sibling::*[1]")));

                // Address
                $it['Address'] = $this->http->FindSingleNode("//*[contains(text(),'Address:')]/following-sibling::*[1]");

                // DetailedAddress
                // Phone
                $it['Phone'] = preg_replace("#[^0-9-]+#", ", ", $this->http->FindSingleNode("//*[contains(text(),'Phone:')]/following-sibling::*[1]"));

                // Fax
                // GuestNames
                $guest = $this->http->FindSingleNode("//*[contains(text(),'Guest')]/following-sibling::*[1]");

                if ($guest) {
                    $it['GuestNames'] = [$guest];
                }

                // Guests
                // Kids
                // Rooms
                // Rate
                // RateTyp
                // CancellationPolicy
                $it['CancellationPolicy'] = trim($this->http->FindSingleNode("//*[contains(text(),'Cancellation policy')]/..", null, true, "#Cancellation policy:.+$#"));

                // RoomType
                $it['RoomType'] = $this->http->FindSingleNode("//*[contains(text(),'Room type')]/following-sibling::*[1]");

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = $this->http->FindSingleNode("//*[contains(text(),'Total price')]/following-sibling::*[1]/p", null, true, "#^\S+\s+([0-9\.]+)#");

                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(),'Total price')]/following-sibling::*[1]/p", null, true, "#^(\S+)\s+[0-9\.]+#");

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reFrom, $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return ((strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false))
               || ((strpos($body, $this->reBody4) !== false && strpos($body, $this->reBody4) !== false));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->reFrom, $headers["from"]) && strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re) !== false) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function is_bad_segments($array)
    {
        foreach ($array as $key => $value) {
            if ($value['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN) {
                return false;
            }
        }

        return true;
    }
}
