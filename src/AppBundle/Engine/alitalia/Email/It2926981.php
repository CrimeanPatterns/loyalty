<?php

namespace AwardWallet\Engine\alitalia\Email;

class It2926981 extends \TAccountCheckerExtended
{
    public $mailFiles = "alitalia/it-2926981.eml, alitalia/it-2926993.eml, alitalia/it-3143997.eml, alitalia/it-4557267.eml";
    public $reBody = 'alitalia.com';
    public $reBody2 = "Your travel";
    public $reBody3 = "Andata";
    public $reSubject = "Booking summary";
    public $reFrom = "noreply@alitalia.it";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'BOOKING CODE (PNR)') or contains(text(), 'CODICE DI PRENOTAZIONE (PNR) È')]/ancestor::tr[1]/following-sibling::tr[2]|//*[contains(text(), 'BOOKING CODE (PNR)') or contains(text(), 'CODICE DI PRENOTAZIONE (PNR) È')]/ancestor-or-self::td[1]/following-sibling::td[1]");

                // TripNumber
                // Passengers
                $it['Passengers'] = array_map("beautifulName", $this->http->FindNodes("//text()[contains(.,'Ticket number') or contains(.,'Numero del biglietto:')]/preceding::text()[normalize-space(.)][1]"));

                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                $it['Status'] = $this->http->FindSingleNode("//*[contains(text(), 'BOOKING CODE (PNR)') or contains(text(), 'CODICE DI PRENOTAZIONE (PNR) È')]/ancestor::tr[1]/following-sibling::tr[3]", null, true, "#booking\s+(\w+)#i");

                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//td[contains(text(), 'Outbound') or contains(text(), 'Inbound') or contains(text(), 'Andata') or contains(text(), 'Ritorno')]/ancestor::tr[2]/following-sibling::tr[contains(./td[2], ':')]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $date = en($this->http->FindSingleNode("./preceding-sibling::tr[contains(., 'Outbound') or contains(., 'Inbound') or contains(., 'Andata') or contains(., 'Ritorno')][1]", $root, true, "#-\s*(\d+\s+\w+\s+\d+)#"));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#[A-Z]{3}#");

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime($date . ', ' . $this->http->FindSingleNode("./td[2]", $root));

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[5]", $root, true, "#[A-Z]{3}#");

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($date . ', ' . $this->http->FindSingleNode("./td[4]", $root, true, "#\d+:\d+(?:\s*[AP]M)?#i"));

                    if ($day = $this->http->FindSingleNode("./td[4]", $root, false, "#([\+\-]\s*\d+)\s*$#")) {
                        $itsegment['ArrDate'] = strtotime($day . ' days', $itsegment['ArrDate']);
                    }

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#([^\s\d]+)#");

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root);

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    $type = $this->http->FindSingleNode("./preceding::text()[contains(., 'Outbound') or contains(., 'Inbound') or contains(., 'Andata') or contains(., 'Ritorno')][1]", $root);
                    $itsegment['Seats'] = implode(",", $this->http->FindNodes("//img[contains(@src, '/passenger.png')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//text()[normalize-space(.)='{$type}']/following::text()[normalize-space(.)][1]"));

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

        return strpos($body, $this->reBody) !== false && (
            strpos($body, $this->reBody2) !== false
            || strpos($body, $this->reBody3) !== false
        );
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
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
        return ["en", "it"];
    }
}
