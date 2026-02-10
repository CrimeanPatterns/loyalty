<?php

namespace AwardWallet\Engine\egencia\Email;

class It3048144 extends \TAccountCheckerExtended
{
    public $mailFiles = "";
    public $reBody = 'This email contains a copy of an Egencia itinerary sent by';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                // ### FLIGHT ###

                $xpath = "//*[substring(normalize-space(text()), 1, 8) = 'Flight (']/ancestor::table[1]/following-sibling::table[contains(., 'Depart') and contains(., 'Arrive')]/tbody";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("roots not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $flight = [];

                foreach ($nodes as $root) {
                    $rl = false;
                    $rl = re("#your\s+confirmation\s+code\s+(\w+)#", text($this->http->Response['body']));

                    if ($rl !== false) {
                        $flight[$rl][] = $root;
                    }
                }

                foreach ($flight as $rl => $roots) {
                    $it = [];

                    $it['Kind'] = "T";
                    // RecordLocator

                    $it['RecordLocator'] = $rl;
                    // TripNumber
                    // Passengers
                    // AccountNumbers
                    // Cancelled
                    // TotalCharge
                    // BaseFare
                    // Currency
                    // Tax
                    // SpentAwards
                    // EarnedAwards
                    // Status
                    // ReservationDate
                    // NoItineraries
                    // TripCategory

                    foreach ($roots as $root) {
                        $date = str_replace("-", " ", orval(
                            $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::table[1]", $root, true, "#\d+-\w+-\d{4}#"),
                            $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]", $root, true, "#\d+-\w+-\d{4}#")
                        ));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[2]/td[5]", $root, true, "#(\d+)#");

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#\(([A-Z]{3})\)#");

                        // DepName
                        // DepDate
                        $itsegment['DepDate'] = strtotime($date . ', ' . $this->http->FindSingleNode("./tr[1]/td[2]", $root));

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[2]/td[3]", $root, true, "#\(([A-Z]{3})\)#");

                        // ArrName
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($date . ', ' . $this->http->FindSingleNode("./tr[2]/td[2]", $root));

                        // AirlineName
                        $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]/td[5]", $root);

                        // Aircraft
                        $itsegment['Aircraft'] = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]", $root, true, "#,\s*([^,]+)$#");

                        // Cabin
                        $itsegment['Cabin'] = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]", $root, true, "#(\w+)\s+Class\s+\(\w\)#");

                        // BookingClass
                        $itsegment['BookingClass'] = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]", $root, true, "#\w+\s+Class\s+\((\w)\)#");

                        // PendingUpgradeTo
                        // Seats
                        // Duration
                        $itsegment['Duration'] = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]", $root, true, "#\d+hr\s+\d+mn#");

                        // Meal
                        $itsegment['Meal'] = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]", $root, true, "#Dinner#");

                        // Smoking
                        // Stops
                        $it['TripSegments'][] = $itsegment;
                    }
                    $itineraries[] = $it;
                }

                // ### HOTEL ###

                $xpath = "//*[substring(normalize-space(text()), 1, 7) = 'Hotel (']/ancestor::td[2]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("roots not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $it = [];

                    $it['Kind'] = "R";

                    // ConfirmationNumber
                    $it['ConfirmationNumber'] = $this->http->FindSingleNode("./table[2]/tbody/tr[string-length(normalize-space(.))>1][1]/td[2]", $root, true, "#Confirmation number\s*:\s*(.+)#");

                    // TripNumber
                    // ConfirmationNumbers

                    // Hotel Name
                    $it['HotelName'] = $this->http->FindSingleNode("./table[2]/tbody/tr[string-length(normalize-space(.))>1][1]/td[1]", $root);

                    // 2ChainName

                    // CheckInDate
                    $it['CheckInDate'] = strtotime($this->http->FindSingleNode("./table[3]/tbody/tr[1]/td[2]/text()[1]", $root));

                    // CheckOutDate
                    $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("./table[3]/tbody/tr[1]/td[2]/text()[2]", $root));

                    // Address
                    $it['Address'] = $this->http->FindSingleNode("./table[3]/tbody/tr[1]/td[3]/text()[1]", $root) . ', ' . $this->http->FindSingleNode("./table[3]/tbody/tr[1]/td[3]/text()[2]", $root);

                    // DetailedAddress

                    // Phone
                    $it['Phone'] = trim($this->http->FindSingleNode("./table[3]/tbody/tr[1]/td[3]/text()[3]", $root, true, "#[\(\)\d\s]+#"));

                    // Fax
                    $it['Fax'] = trim($this->http->FindSingleNode("./table[3]/tbody/tr[1]/td[3]/text()[3]", $root, true, "#Fax\s+([\(\)\d\s]+)#"));

                    // GuestNames
                    $GuestNames = $this->http->FindNodes("//*[contains(text(), 'Guest')][contains(text(),'name')]/parent::*/following-sibling::*");

                    if (count($GuestNames) > 0) {
                        $it['GuestNames'] = array_unique($GuestNames);
                    }

                    // Guests
                    // Kids
                    // Rooms
                    // Rate
                    // RateType
                    $it['RateType'] = $this->http->FindSingleNode("./table[2]/tbody/tr[4]", $root);

                    // CancellationPolicy
                    $it['CancellationPolicy'] = $this->http->FindSingleNode(".//*[normalize-space(text()) = 'Cancellation and Changes:']/..", $root, true, "#Cancellation and Changes:\s+(.+)#");

                    // RoomType
                    // RoomTypeDescription
                    $it['RoomTypeDescription'] = $this->http->FindSingleNode("./table[2]/tbody/tr[3]", $root);

                    // Cost
                    // Taxes
                    // Total
                    // Currency
                    // SpentAwards
                    // EarnedAwards
                    // AccountNumbers
                    // Status
                    // Cancelled
                    // ReservationDate
                    // NoItineraries
                    $itineraries[] = $it;
                }
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
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
}
