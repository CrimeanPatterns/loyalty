<?php

namespace AwardWallet\Engine\azul\Email;

class It3692505 extends \TAccountCheckerExtended
{
    public $mailFiles = "azul/it-3692505.eml";
    public $reBody = 'Azul';
    public $reBody2 = "O seu voo para";
    public $reSubject = " Sua viagem está chegando, faça já o web check-in e evite filas.";
    public $reFrom = "no-reply@voeazul.com.br";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = $this->text();
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Código do localizador:')]",
                    null, true, "#Código\s+do\s+localizador\s*:\s*([A-Z\d]{5,7})\b#");

                if (empty($it['RecordLocator'])) {
                    $it['RecordLocator'] = re("#Código\s+do\s+localizador\s*:\s*([A-Z\d]{5,7})\b#", $text);
                }
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[normalize-space(text())='Passageiros']/ancestor::tr[2]/following-sibling::tr[1]//tr[not(.//tr) and string-length(normalize-space(./td[2]))>1]");

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

                $xpath = "//*[normalize-space(text())='Voo:']/ancestor::tr[2]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $date = strtotime(preg_replace("#^\s*(\d+)/(\d+)/(\d{4})\s*$#", '$1.$2.$3',
                        $this->http->FindSingleNode("./td[3]", $root, true, "#Data\s*:\s*(\d+/\d+/\d{4})#")));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#Voo\s*:\s*(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = re("#\(([A-Z]{3})\)#", $this->http->FindSingleNode("./td[5]", $root));

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime(re("#\d+:\d+#", $this->http->FindSingleNode("./td[7]", $root)), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = ure("#\(([A-Z]{3})\)#", $this->http->FindSingleNode("./td[5]", $root), 2);

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(ure("#(\d+:\d+)#", $this->http->FindSingleNode("./td[7]", $root), 2), $date);

                    // AirlineName
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;

                    // Aircraft
                    // TraveledMiles
                    // Cabin
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
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
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
        return ["pt"];
    }
}
