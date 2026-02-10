<?php

namespace AwardWallet\Engine\egencia\Email;

class It2762900 extends \TAccountCheckerExtended
{
    public $mailFiles = "egencia/it-2762900.eml";
    public $reBody = "choisi Egencia pour réserver votre voyage";
    public $reSubject = 'Egencia';
    public $reSubject2 = 'Confirmation';
    public $reSubject3 = 'réservation';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // "it-2762900.eml"
            $this->reBody => function (&$itineraries) {
                // FLIGHTS

                $it = [];

                $it['Kind'] = "T";
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Vol')]/ancestor::table[1]/following-sibling::table[1]//*[contains(text(),'Code de confirmation')]", null, true, "#Code de confirmation\s*:\s*([A-Z0-9]+)#ms");
                // TripNumber
                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = (float) preg_replace(["#\s+#", '#,#'], ['', '.'], $this->http->FindSingleNode("//*[contains(text(), 'Vol')]/ancestor::td[1]/following-sibling::td[contains(., 'total')][1]", null, true, "#Prix total\s*:\s+([\d\s,]+)#"));

                // BaseFare
                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Vol')]/ancestor::td[1]/following-sibling::td[contains(., 'total')][1]", null, true, "#Prix total\s*:\s+[\d\s,]+\s+(\S+)#");

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                // segments
                $xpath = "//*[contains(text(), 'Vol')]/ancestor::table[1]/following-sibling::table[contains(.,'Départ')]";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("Segments roots not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($segments as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode(".//*[contains(text(), 'Départ')]/ancestor::td[1]/following-sibling::td[2]", $root, true, "#\(([^\)]+)\)#");

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime(re("#\w+\s+(\d+)-(\w+)-(\d+)#", $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[contains(.,'-')][1]", $root, true)) . ' ' . en(re(2)) . ' ' . re(3)
                    . ', ' . $this->http->FindSingleNode(".//*[contains(text(), 'Départ')]/ancestor::td[1]/following-sibling::td[1]", $root));

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode(".//*[contains(text(), 'Arrivée')]/ancestor::td[1]/following-sibling::td[2]", $root, true, "#\(([^\)]+)\)#");

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(re("#\w+\s+(\d+)-(\w+)-(\d+)#", $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[contains(.,'-')][1]", $root, true)) . ' ' . en(re(2)) . ' ' . re(3)
                    . ', ' . str_replace("jour", "day", $this->http->FindSingleNode(".//*[contains(text(), 'Arrivée')]/ancestor::td[1]/following-sibling::td[1]", $root)));

                    // AirlineName
                    $itsegment['AirlineName'] = trim($this->http->FindSingleNode(".//*[contains(text(), 'Départ')]/ancestor::td[1]/following-sibling::td[4]", $root));

                    $data = explode(',', $this->http->FindSingleNode("./following-sibling::table[1]//tr[1]", $root));

                    if (strpos($data[0], "Seat") === false || strpos($data[1], "Classe") === false) {
                        return false;
                    }

                    // Aircraft
                    end($data);
                    $itsegment['Aircraft'] = trim($data[key($data)]);

                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = trim(re("#Classe\s+(.+)#", $data[1]));

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    $itsegment['Seats'] = trim(re("#Seat\s+(.+)#", $data[0]));

                    // Duration
                    $itsegment['Duration'] = trim($data[key($data) - 1]);

                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;

                // CAR

                // roots
                $xpath = "//*[contains(text(), 'Voiture') and not(contains(text(), ':'))]/ancestor::table[1]/following-sibling::table[1]";
                $roots = $this->http->XPath->query($xpath);

                if ($roots->length == 0) {
                    $this->http->Log("Roots not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($roots as $root) {
                    $it = [];

                    $it['Kind'] = "L";

                    // Number
                    $it['Number'] = trim($this->http->FindSingleNode(".//*[contains(text(), 'Numéro de confirmation')]/..", $root, true, "#Numéro de confirmation\s*:\s+(.+)#"));
                    // TripNumber
                    // PickupDatetime
                    $it['PickupDatetime'] = strtotime(re("#(\d+)\s+(\w+)\s+(\d+)#", $this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Prise') and contains(text(),'en charge')]/ancestor::td[1]/following-sibling::td[1]", $root)) . ' ' . en(re(2)) . ' ' . re(3));

                    // PickupLocation
                    $it['PickupLocation'] = $this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Prise') and contains(text(),'en charge')]/ancestor::td[1]/following-sibling::td[2]", $root);

                    // DropoffDatetime
                    $it['DropoffDatetime'] = strtotime(re("#(\d+)\s+(\w+)\s+(\d+)\s+(\d+:\d+)#", $this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Restitution')]/ancestor::td[1]/following-sibling::td[1]", $root)) . ' ' . en(re(2)) . ' ' . re(3) . ', ' . re(4));

                    // DropoffLocation
                    $it['DropoffLocation'] = orval(
                        $this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Restitution')]/ancestor::td[1]/following-sibling::td[2]", $root),
                        $this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Prise') and contains(text(),'en charge')]/ancestor::td[1]/following-sibling::td[2]", $root)
                    );

                    // PickupPhone
                    $it['PickupPhone'] = $this->http->FindSingleNode(".//*[contains(text(), 'Téléphone')]", $root, true, "#Téléphone\s+(.+)#");

                    // PickupFax
                    // PickupHours
                    // DropoffPhone
                    // DropoffHours
                    // DropoffFax
                    // RentalCompany
                    $it['RentalCompany'] = trim($this->http->FindSingleNode(".//*[contains(text(), 'Téléphone')]", $root, true, "#^(.*?)Téléphone#"));

                    // CarType
                    // CarModel
                    // CarImageUrl
                    // RenterName
                    $it['RenterName'] = trim($this->http->FindSingleNode("//*[contains(text(), 'Voyageur:')]/..", null, true, "#Voyageur:\s+(.*?)\s+Numéro#ms"));

                    // PromoCode
                    // TotalCharge
                    $it['TotalCharge'] = (float) preg_replace(["#\s+#", '#,#'], ['', '.'], $this->http->FindSingleNode("//*[contains(text(), 'Voiture')]/ancestor::td[1]/following-sibling::td[contains(., 'total')][1]", null, true, "#Prix total estimé\s*:\s+([\d\s,]+)#"));

                    // Currency
                    $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Voiture')]/ancestor::td[1]/following-sibling::td[contains(., 'total')][1]", null, true, "#Prix total estimé\s*:\s+[\d\s,]+\s+(\S+)#");

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

                // HOTELS

                // roots
                $xpath = "//*[contains(text(), 'Hôtel')]/ancestor::table[1]/following-sibling::table[1]";
                $roots = $this->http->XPath->query($xpath);

                if ($roots->length == 0) {
                    $this->http->Log("Roots not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($roots as $root) {
                    $it = [];

                    $it['Kind'] = "R";

                    // ConfirmationNumber
                    $it['ConfirmationNumber'] = trim($this->http->FindSingleNode(".//*[contains(text(), 'Numéro de confirmation')]/..", $root, true, "#Numéro de confirmation\s*:\s+(.+)#"));

                    // TripNumber
                    // ConfirmationNumbers

                    // Hotel Name
                    $it['HotelName'] = trim($this->http->FindSingleNode("./tr[2]/td[1]", $root));

                    // 2ChainName

                    // CheckInDate
                    $it['CheckInDate'] = strtotime(re("#(\d+)\s+(\w+)\s+(\d+)#", $this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Arrivée')]/ancestor::td[1]/following-sibling::td[1]", $root)) . ' ' . en(re(2)) . ' ' . re(3));

                    // CheckOutDate
                    $it['CheckOutDate'] = strtotime(re("#\w+\.\s+(\d+)\s+(\w+)\s+(\d+)$#ms", $this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Arrivée')]/ancestor::td[1]/following-sibling::td[1]", $root)) . ' ' . en(re(2)) . ' ' . re(3));

                    // Address
                    $it['Address'] = trim($this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Arrivée')]/ancestor::td[1]/following-sibling::td[2]", $root, true, "#^(.*?)[0-9\(\)\s-]{11,100}Télécopieur#"));

                    // DetailedAddress

                    // Phone
                    $it['Phone'] = trim($this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Arrivée')]/ancestor::td[1]/following-sibling::td[2]", $root, true, "#^.*?([0-9\(\)\s-]{11,100})Télécopieur#"));

                    // Fax
                    $it['Fax'] = trim($this->http->FindSingleNode("./following-sibling::table[1]//*[contains(text(), 'Arrivée')]/ancestor::td[1]/following-sibling::td[2]", $root, true, "#^.*?[0-9\(\)\s-]{11,100}Télécopieur\s+(.+)#"));

                    // GuestNames
                    // Guests
                    // Kids
                    // Rooms
                    // Rate
                    // RateType
                    // CancellationPolicy
                    // RoomType
                    // RoomTypeDescription
                    // Cost
                    // Taxes
                    // Total
                    $it['Total'] = (float) preg_replace(["#\s+#", '#,#'], ['', '.'], $this->http->FindSingleNode("//*[contains(text(), 'Hôtel')]/ancestor::td[1]/following-sibling::td[contains(., 'total')][1]", null, true, "#Prix total\s*:\s+([\d\s,]+)#"));

                    // Currency
                    $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Hôtel')]/ancestor::td[1]/following-sibling::td[contains(., 'total')][1]", null, true, "#Prix total\s*:\s+[\d\s,]+\s+(\S+)#");

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
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["subject"], $this->reSubject2) !== false && strpos($headers["subject"], $this->reSubject3) !== false;
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

    public static function getEmailLanguages()
    {
        return ["fr"];
    }
}
