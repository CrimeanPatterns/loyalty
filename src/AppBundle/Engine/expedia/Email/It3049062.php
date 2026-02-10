<?php

namespace AwardWallet\Engine\expedia\Email;

class It3049062 extends \TAccountCheckerExtended
{
    public $mailFiles = "expedia/it-3049062.eml";
    public $reBody = 'Expedia.de';
    public $reBody2 = "Vielen Dank, dass Sie";
    public $reBody3 = "Flugübersicht";
    public $reSubject = "Ihre Expedia Buchung";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Expedia.de Buchungsnummer (Flug)')]", null, true, "#Expedia.de Buchungsnummer \(Flug\)\s*:\s*(\w+)#");
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[contains(text(), 'Kosten und Reisedetails')]/ancestor::tr[1]/following-sibling::tr/td[2][contains(., 'Erwachsener')]/preceding-sibling::td[1]");
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

                $xpath = "//*[substring(normalize-space(text()), 1, 6)='Abflug']/ancestor::tr[1]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($nodes as $root) {
                    $date = $this->en(preg_replace("#\s+(\d{2})$#", " 20$1", str_replace(".", " ", en($this->http->FindSingleNode("./preceding::tr[contains(./td[1]/@style, '#F6F6F6')][1]", $root, true, "#\w+\s+(\d+\.\w+\.\d+)#")))));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[contains(., 'Flug')]/..", $root, true, "#Flug\s+(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)#");

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime($date . ", " . $this->http->FindSingleNode(".//text()[contains(., 'Abflug')]", $root, true, "#\d+:\d+#"));

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($date . ", " . $this->http->FindSingleNode(".//text()[contains(., 'Ankunft')]", $root, true, "#\d+:\d+#"));

                    if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                        $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                    }

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[contains(., 'Flug')]/..", $root, true, "#([A-Z]+)#");

                    // Aircraft
                    $itsegment['Aircraft'] = $this->http->FindSingleNode("./following-sibling::tr[2]//text()[contains(., 'Class')]/ancestor::td[1]", $root, true, "#,\s*([^,]+)$#");

                    // TraveledMiles
                    $itsegment['TraveledMiles'] = $this->http->FindSingleNode("./td[4]//text()[contains(.,'Meilen')]", $root, true, "#\(\s*([\d\.]+)\s+Meilen\s*\)$#");

                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::tr[2]//text()[contains(., 'Class')]", $root, true, "#(\w+)\s+Class#");

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[contains(., 'Dauer:')]", $root, true, "#Dauer\s*:\s*(.+)#");

                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;

                //## HOTEL ###

                $xpath = "//*[contains(text(), 'Hotelübersicht')]/ancestor::tr[2]/following-sibling::tr[1]";
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $root) {
                    $it = [];

                    $it['Kind'] = "R";

                    // ConfirmationNumber
                    $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(., 'Expedia.de Buchungsnummer (Hotel)')]", null, true, "#Expedia.de Buchungsnummer \(Hotel\)\s*:\s*(\w+)#");

                    // TripNumber
                    // ConfirmationNumbers

                    // Hotel Name
                    $it['HotelName'] = $this->http->FindSingleNode(".//*[contains(text(), 'Anreise')]/ancestor::tr[1]/../tr[1]", $root);

                    // 2ChainName

                    // CheckInDate
                    $it['CheckInDate'] = strtotime(str_replace("-", " ", $this->en(en($this->http->FindSingleNode(".//*[contains(text(), 'Anreise')]/ancestor::td[1]", $root, true, "#Anreise\s*:\s*\w+\s+(\d+-\w+-\d+)#")))));

                    // CheckOutDate
                    $it['CheckOutDate'] = strtotime(str_replace("-", " ", $this->en(en($this->http->FindSingleNode(".//*[contains(text(), 'Anreise')]/ancestor::td[1]", $root, true, "#Abreise\s*:\s*\w+\s+(\d+-\w+-\d+)#")))));

                    // Address
                    $it['Address'] = $this->http->FindSingleNode(".//*[contains(text(), 'Anreise')]/ancestor::tr[1]/td[2]", $root);

                    // DetailedAddress

                    // Phone
                    $it['Phone'] = $this->http->FindSingleNode(".//text()[contains(.,'Telefon:')]", $root, true, "#Telefon:\s*(.*?)\s*(Fax|$)#");

                    // Fax
                    $it['Fax'] = $this->http->FindSingleNode(".//text()[contains(.,'Fax:')]", $root, true, "#Fax:\s*(.*?)\s*$#");

                    // GuestNames
                    $it['GuestNames'] = $this->http->FindNodes("//*[contains(text(), 'Kosten und Reisedetails')]/ancestor::tr[1]/following-sibling::tr/td[2][contains(., 'Erwachsener')]/preceding-sibling::td[1]");

                    // Guests
                    $it['Guests'] = $this->http->FindSingleNode(".//text()[contains(.,'Erwachsene/Senioren')]", $root, true, "#(\d+)\s+Erwachsene/Senioren#");

                    // Kids
                    // Rooms
                    // Rate
                    // RateType

                    // CancellationPolicy
                    $it['CancellationPolicy'] = str_replace("\n", " ", $this->http->FindSingleNode("//*[contains(text(), 'Cancellation information')]/parent::*/parent::*/following-sibling::*[1]/td/p"));

                    // RoomType
                    $it['RoomType'] = $this->http->FindSingleNode(".//text()[contains(.,'Zimmerbeschreibung:')]", $root, true, "#Zimmerbeschreibung\:\s+(.*?),#");

                    // RoomTypeDescription
                    $it['RoomTypeDescription'] = $this->http->FindSingleNode(".//text()[contains(.,'Zimmerbeschreibung:')]/following::text()[1]", $root);

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

                //## CAR ###

                $xpath = "//*[contains(text(), 'Mietwagenübersicht')]/ancestor::tr[2]/following-sibling::tr[3]";
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $root) {
                    $it = [];

                    $it['Kind'] = "L";

                    // Number
                    $it['Number'] = $this->http->FindSingleNode(".//text()[contains(.,'Mietwagenbestätigungsnummer')]", $root, true, "#Mietwagenbestätigungsnummer\s*:\s*(\d+)#");
                    // TripNumber
                    // PickupDatetime
                    $it['PickupDatetime'] = strtotime($this->http->FindSingleNode(".//text()[contains(.,'Abholen:')]/ancestor::td[1]", $root, true, "#Abholen\s*:\s*\w+\s+(.+)#"));

                    // PickupLocation
                    $it['PickupLocation'] = $this->http->FindSingleNode(".//text()[contains(.,'Standort:')]/ancestor::td[1]", $root, true, "#Standort\s*:\s*(.+)#");

                    // DropoffDatetime
                    $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode(".//text()[contains(.,'Rückgabe:')]/ancestor::td[1]", $root, true, "#Rückgabe\s*:\s*\w+\s+(.+)#"));

                    // DropoffLocation
                    $it['DropoffLocation'] = $this->http->FindSingleNode(".//text()[contains(.,'Standort:')]/ancestor::td[1]", $root, true, "#Standort\s*:\s*(.+)#");

                    // PickupPhone
                    // PickupFax
                    // PickupHours
                    // DropoffPhone
                    // DropoffHours
                    // DropoffFax
                    // RentalCompany
                    // CarType
                    $it['CarType'] = trim($this->http->FindSingleNode("(.//b)[1]", $root), " :");

                    // CarModel
                    // CarImageUrl
                    // RenterName
                    // PromoCode
                    // TotalCharge
                    // Currency
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
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false && strpos($body, $this->reBody3) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject);
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

    public function en($text)
    {
        $replaces = [
            'okt' => 'oct',
        ];

        return strtr(strtolower($text), $replaces);
    }

    public static function getEmailLanguages()
    {
        return ["de"];
    }
}
