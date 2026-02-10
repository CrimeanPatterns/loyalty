<?php

namespace AwardWallet\Engine\azul\Email;

class It3242276 extends \TAccountCheckerExtended
{
    public $mailFiles = "azul/it-3242276.eml";
    public $reBody = 'Azul';
    public $reBody2 = "tudoazul/tudo-azul.png";
    public $reSubject = "Confirmacao de Compra";
    public $reFrom = "no-reply@tudoazul.viajanet.com.br";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Número da reserva']/following::text()[1]");
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Nome']/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(./td[2]))>1]/td[2]", null, "#[^\(]+#");
                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // $it['BaseFare'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='Total de pontos']/ancestor-or-self::td[1]/following-sibling::td[1]"));

                // Currency
                $it['Currency'] = currency($this->http->FindSingleNode("//*[normalize-space(text())='Total para Troca:']/.."));

                // Tax
                $it['Tax'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='Taxa de embarque']/ancestor-or-self::td[1]/following-sibling::td[1]"));

                // SpentAwards
                $it['SpentAwards'] = $this->http->FindSingleNode("//*[normalize-space(text())='Total de pontos']/ancestor-or-self::td[1]/following-sibling::td[1]");

                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[normalize-space(text())='ida' or normalize-space(.)='volta']/ancestor::table[1]/following-sibling::table[2]/tbody/tr[4]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($nodes as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./../tr[1]", $root, true, "#(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(\s*([A-Z]{3})\s*\)#");

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime(
                        en(str_replace(" de ", " ", $this->http->FindSingleNode("./following-sibling::tr[2]/td[4]/span[2]", $root, true, "#,\s*(.+)#"))) . ', ' .
                        str_replace(["h", "min"], [":", ""], $this->http->FindSingleNode("./following-sibling::tr[2]/td[4]/span[1]", $root)),
                        $this->date
                    );

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[6]", $root, true, "#\(\s*([A-Z]{3})\s*\)#");

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(
                        en(str_replace(" de ", " ", $this->http->FindSingleNode("./following-sibling::tr[2]/td[7]/span[2]", $root, true, "#,\s*(.+)#"))) . ', ' .
                        str_replace(["h", "min"], [":", ""], $this->http->FindSingleNode("./following-sibling::tr[2]/td[7]/span[1]", $root)),
                        $this->date
                    );

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./..//img[contains(@src, 'IconsCiasViajanet')]/@src", $root, true, "#/(\w{2})\.gif#i");

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root);

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
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));
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
