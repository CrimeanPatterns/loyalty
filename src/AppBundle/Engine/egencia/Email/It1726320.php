<?php

namespace AwardWallet\Engine\egencia\Email;

class It1726320 extends \TAccountCheckerExtended
{
    public $mailFiles = "egencia/it-3.eml, egencia/it-3048144.eml, egencia/it-3559641.eml, egencia/it-3563559.eml, egencia/it-3563713.eml, egencia/it-4887307.eml, egencia/it-9.eml";
    public $reBody = 'Thank you for booking your trip with Egencia';
    public $reBody2 = "This e-mail contains a copy of an Egencia";
    public $reBody3 = "This email contains a copy of an Egencia";
    public $reSubject = "Egencia";
    public $reFrom = "corptravel@customercare.egencia.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "HTML" => function (&$itineraries) {
                $tripNumber = $this->http->FindSingleNode("(//text()[normalize-space(.)='Itinerary number:']/following::text()[string-length(normalize-space(.))>1][1])[1]");

                //#################
                //##   FLIGHT   ###
                //#################

                $rls = [];

                foreach ($this->http->FindNodes("//text()[contains(., 'confirmation code:')]") as $text) {
                    if (preg_match("#(.*?)\s+confirmation code:\s+(\w+)#", $text, $m)) {
                        $rls[trim($m[1])] = trim($m[2]);
                    }
                }

                $xpath = "//*[normalize-space(text())='Depart']/ancestor::table[1][./../table[1][contains(., 'Flight')]]";
                $nodes = $this->http->XPath->query($xpath);

                $airs = [];

                foreach ($nodes as $root) {
                    if ($AirlineName = $this->http->FindSingleNode(".//tr[1]/td[last()]", $root)) {
                        if (isset($rls[$AirlineName])) {
                            $airs[$rls[$AirlineName]][] = $root;
                        } else {
                            $airs['null'][] = $root;
                        }
                    }
                }

                foreach ($airs as $rl => $roots) {
                    $it = [];

                    $it['Kind'] = "T";
                    // RecordLocator
                    if ($rl !== 'null') {
                        $it['RecordLocator'] = $rl;
                    } else {
                        $it['RecordLocator'] = CONFNO_UNKNOWN;
                    }

                    // TripNumber
                    $it['TripNumber'] = $tripNumber;

                    // TicketNumbers
                    $it['TicketNumbers'] = array_unique($this->http->FindNodes("//text()[contains(.,'ticket number(s):')]", null, "#ticket number\(s\):\s+(\d+)#"));

                    // Passengers
                    $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Traveler:' or normalize-space(.)='Account holder:']/following::text()[string-length(normalize-space(.))>1][1]");

                    // AccountNumbers
                    // Cancelled
                    // TotalCharge
                    $it['TotalCharge'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='Depart']/ancestor::table[1][./../table[1][contains(., 'Flight')]]/ancestor::tr[1]//text()[contains(.,'Total price:')]", null, true, "#Total price:\s+(.+)#"));

                    // BaseFare
                    // Currency
                    $it['Currency'] = currency($this->http->FindSingleNode("//*[normalize-space(text())='Depart']/ancestor::table[1][./../table[1][contains(., 'Flight')]]/ancestor::tr[1]//text()[contains(.,'Total price:')]", null, true, "#Total price:\s+(.+)#"));

                    // Tax
                    // SpentAwards
                    // EarnedAwards
                    // Status
                    // ReservationDate
                    // NoItineraries
                    // TripCategory

                    /**
                     * sometimes there is
                     * Depart    11:40 am    Milwaukee (MKE)        Southwest Airlines
                     * Stops    St. Louis (STL)
                     * Arrive.
                     */
                    $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                    foreach ($roots as $root) {
                        $date = strtotime($this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[not(contains(., 'Flight'))][1]", $root));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//tr[contains(., 'Arrive')]/td[last()]", $root, true, "#(\d+)#");

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode(".//tr[contains(., 'Depart')]", $root, true, "#\(([A-Z]{3})\)#");

                        // DepName
                        // DepDate
                        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode(".//tr[contains(., 'Depart')]/td[2]", $root), $date);

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode(".//tr[contains(., 'Arrive')]", $root, true, "#\(([A-Z]{3})\)#");

                        // ArrName
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode(".//tr[contains(., 'Arrive')]/td[2]", $root, true, "#(\d+:\d+(?:\s*[ap]m)?)#"), $date);

                        // AirlineName
                        $itsegment['AirlineName'] = $this->http->FindSingleNode(".//tr[1]/td[last()]", $root);

                        if (!empty($itsegment['AirlineName'])) {
                            $account = $this->http->FindSingleNode("//td[not(.//td) and contains(., '" . $itsegment['AirlineName'] . " frequent flyer')]", null, true,
                                "#\#(\w{5,})#");

                            if (!empty($account)) {
                                $it['AccountNumbers'][] = $account;
                                $it['AccountNumbers'] = array_unique($it['AccountNumbers']);
                            }
                        }
                        // Operator
                        $itsegment['Operator'] = $this->http->FindSingleNode(".//td[contains(.,'Operated by:')]", $root, null, "#Operated by:\s*(.+)#");

                        // Aircraft
                        $itsegment['Aircraft'] = $this->http->FindSingleNode("./following-sibling::table[1]//tr[1]", $root, true, "#,\s+([^,]*?)$#");

                        // TraveledMiles
                        // Cabin
                        $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::table[1]", $root, true, "#(\w+)/Coach\s+Class\s+\((\w)\)#");

                        // BookingClass
                        $itsegment['BookingClass'] = $this->http->FindSingleNode("./following-sibling::table[1]", $root, true, "#\w+/Coach\s+Class\s+\((\w)\)#");

                        // PendingUpgradeTo
                        // Seats
                        $itsegment['Seats'][] = $this->http->FindSingleNode("./following-sibling::table[1]", $root, true, "#Seat\s+(\d{1,3}[A-Z])\s*(,|$)#");
                        $itsegment['Seats'] = array_filter($itsegment['Seats']);

                        // Duration
                        $itsegment['Duration'] = $this->http->FindSingleNode("./following-sibling::table[1]", $root, true, "#\d+hr\s+\d+mn#");

                        // Meal
                        // Smoking
                        // Stops
                        $it['TripSegments'][] = $itsegment;
                    }
                    $itineraries[] = $it;
                }

                //################
                //##   HOTEL   ###
                //################

                $xpath = "//*[normalize-space(text())='Check in']/ancestor::table[1]";
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $root) {
                    $it = [];

                    $it['Kind'] = "R";

                    // ConfirmationNumber
                    $it['ConfirmationNumber'] = orval(
                        $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[2]//td[last()]", $root, true, "#Confirmation number\s*:\s*\#?(\w+)#"),
                        $this->http->FindSingleNode("//text()[normalize-space(.)='Itinerary number:']/following::text()[string-length(normalize-space(.))>1][1]")
                    );

                    // TripNumber
                    $it['TripNumber'] = $tripNumber;

                    // ConfirmationNumbers

                    // Hotel Name
                    $it['HotelName'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[2]/td[1]", $root);

                    // 2ChainName

                    // CheckInDate
                    $it['CheckInDate'] = strtotime($this->http->FindSingleNode("(.//td[2]//text()[normalize-space(.)])[1]", $root));

                    // CheckOutDate
                    $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("(.//td[2]//text()[normalize-space(.)])[2]", $root));

                    // Address
                    $it['Address'] = implode(" ", $this->http->FindNodes("(.//td[3]//text()[normalize-space(.)])[position()=1 or position()=2]", $root));

                    // DetailedAddress

                    // Phone
                    $it['Phone'] = $this->http->FindSingleNode("(.//td[3]//text()[normalize-space(.)!=''])[3]", $root, true, "#^.*?([\d \-\(\)]+)#");

                    // Fax
                    $it['Fax'] = $this->http->FindSingleNode("(.//td[3]//text()[normalize-space(.)!=''])[last()]", $root, true, "#([\d \-\(\)]+)\s*$#");

                    // GuestNames
                    $it['GuestNames'] = $this->http->FindSingleNode("//*[normalize-space(text())='Account holder:']/following::text()[normalize-space(.)][1]");

                    // Guests
                    // Kids
                    // Rooms
                    // Rate
                    // RateType
                    $it['RateType'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[4]", $root);

                    // CancellationPolicy
                    $it['CancellationPolicy'] = $this->http->FindSingleNode("./..//*[normalize-space(text())='Cancellation and Changes:']/following::text()[normalize-space(.)][1]", $root);

                    // RoomType
                    $it['RoomType'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[3]", $root, true, "#(.*?),#");

                    // RoomTypeDescription
                    // Cost
                    // Taxes
                    // Total
                    $it["Total"] = cost($this->http->FindSingleNode("./../table[1]//td[2]", $root));

                    // Currency
                    $it["Currency"] = currency($this->http->FindSingleNode("./../table[1]//td[2]", $root));

                    // SpentAwards
                    // EarnedAwards
                    // AccountNumbers
                    // Status
                    // Cancelled
                    // ReservationDate
                    // NoItineraries
                    $itineraries[] = $it;
                }

                //##############
                //##   CAR   ###
                //##############

                $xpath = "//*[normalize-space(text())='Pick up']/ancestor::table[1]";
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $root) {
                    $it = [];

                    $it['Kind'] = "L";

                    // Number
                    $it['Number'] = orval(
                        $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[2]/td[2]", $root, true, "#Confirmation\s+number\s*:\s*(\w+)#"),
                        CONFNO_UNKNOWN // it-5603047.eml
                    );

                    // TripNumber
                    $it['TripNumber'] = $tripNumber;

                    // PickupDatetime
                    $it['PickupDatetime'] = strtotime($this->http->FindSingleNode(".//tr[1]/td[2]", $root, true, "#\d+-\w+-\d+#") . ', ' . $this->http->FindSingleNode(".//tr[1]/td[2]", $root, true, "#\d+:\d+\s+[AP]M#"));

                    // PickupLocation
                    $it['PickupLocation'] = $this->http->FindSingleNode(".//tr[1]/td[3]", $root);

                    // DropoffDatetime
                    $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode(".//tr[2]/td[2]", $root, true, "#\d+-\w+-\d+#") . ', ' . $this->http->FindSingleNode(".//tr[2]/td[2]", $root, true, "#\d+:\d+\s+[AP]M#"));

                    // DropoffLocation
                    $it['DropoffLocation'] = orval(
                        $this->http->FindSingleNode(".//tr[2]/td[3]", $root),
                        $this->http->FindSingleNode(".//tr[1]/td[3]", $root)
                    );

                    // PickupPhone
                    // PickupFax
                    // PickupHours
                    $it['PickupHours'] = $this->http->FindSingleNode(".//tr[4]", $root, true, "#Hours\s+of\s+operation\s*:\s*(.*?),\s+.*?$#");

                    // DropoffPhone
                    // DropoffHours
                    $it['DropoffHours'] = $this->http->FindSingleNode(".//tr[4]", $root, true, "#Hours\s+of\s+operation\s*:\s*.*?,\s+(.*?)$#");

                    // DropoffFax
                    // RentalCompany
                    $it['RentalCompany'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[2]/td[1]", $root, true, "#(.*?)\s+Telephone#");

                    // CarType
                    $it['CarType'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//tr[3]", $root, true, "#(.*?):#");

                    // CarModel
                    // CarImageUrl
                    // RenterName
                    // PromoCode
                    // TotalCharge
                    $it["TotalCharge"] = cost($this->http->FindSingleNode("./../table[1]//td[2]", $root));

                    // Currency
                    $it["Currency"] = currency($this->http->FindSingleNode("./../table[1]//td[2]", $root));

                    // TotalTaxAmount
                    // SpentAwards
                    // EarnedAwards
                    // AccountNumbers
                    // Status
                    // ServiceLevel
                    // Cancelled
                    // PricedEquips
                    // Discount
                    // Discounts
                    // Fees
                    // ReservationDate
                    // NoItineraries
                    $itineraries[] = $it;
                }

                //################
                //##   TRAIN   ###
                //################

                $xpath = "//*[normalize-space(text())='Depart']/ancestor::table[1][./../table[1][contains(., 'Train')]]";
                $nodes = $this->http->XPath->query($xpath);

                $trains = [];

                foreach ($nodes as $root) {
                    if ($rl = $this->http->FIndSingleNode("./../table[2]", $root, true,
                        "#Confirmation number\s*:\s*(\w+)#")) {
                        $trains[$rl][] = $root;
                        $lastRL = $rl;
                    } elseif (isset($lastRL)) {
                        $trains[$lastRL][] = $root;
                    } else {
                        $trains['null'][] = $root;
                    }
                }

                foreach ($trains as $rl => $roots) {
                    $it = [];
                    $it['Kind'] = 'T';

                    // RecordLocator
                    if ($rl !== 'null') {
                        $it['RecordLocator'] = $rl;
                    } else {
                        $it['RecordLocator'] = CONFNO_UNKNOWN;
                    }

                    // TripNumber
                    $it['TripNumber'] = $tripNumber;

                    // Passengers
                    $it['Passengers'] = $this->http->FindSingleNode("//*[normalize-space(text())='Account holder:']/following::text()[normalize-space(.)][1]");

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
                    $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

                    // Parse segments
                    foreach ($roots as $root) {
                        $date = strtotime($this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]", $root));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                        // DepCode
                        $itsegment['DepName'] = $this->http->FindSingleNode(".//tr[contains(., 'Depart')]/td[normalize-space()][3]", $root);

                        if (!empty($itsegment['DepName'])) {
                            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                        }

                        // DepName
                        // DepAddress
                        // DepDate
                        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode(".//tr[contains(., 'Depart')]/td[2]", $root), $date);

                        // ArrCode
                        $itsegment['ArrName'] = $this->http->FindSingleNode(".//tr[contains(., 'Arrive')]/td[normalize-space()][3]", $root);

                        if (!empty($itsegment['ArrName'])) {
                            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                        }

                        // ArrName
                        // ArrAddress
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode(".//tr[contains(., 'Arrive')]/td[2]", $root), $date);

                        // Type
                        $itsegment['Type'] = trim($this->http->FindSingleNode(".//tr[contains(., 'Depart')]/td[last()]", $root));

                        // TraveledMiles
                        // Cabin
                        $itsegment['Cabin'] = $this->http->FindSingleNode(".//tr[contains(., 'Arrive')]/td[last()]", $root, true, "#Class\s*:\s*(\w+)#");

                        // BookingClass
                        // PendingUpgradeTo
                        // Seats
                        // Duration
                        // Meal
                        // Smoking
                        // Stops
                        $it['TripSegments'][] = $itsegment;
                    }
                    $itineraries[] = $it;
                }
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false || strpos($body, $this->reBody2) !== false || strpos($body, $this->reBody3) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }
        $processor = $this->processors["HTML"];
        $processor($itineraries);

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }
}
