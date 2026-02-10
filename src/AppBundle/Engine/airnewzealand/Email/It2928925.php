<?php

namespace AwardWallet\Engine\airnewzealand\Email;

class It2928925 extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-2928925.eml, airnewzealand/it-2929463.eml";
    private $reBody = 'Air New Zealand travel';
    private $reBody2 = "PNR Ref";
    private $reSubject = " Itinerary-PNR";
    private $reFrom = "Qikfax.service@airnz.co.nz";

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
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        $this->parseEmail($itineraries, $body);

        $result = [
            'emailType'  => 'It2928925',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function parseEmail(&$itineraries, $text)
    {
        $text = preg_replace("#[^\w\s\d\-\.\:]+#", "", str_replace("<br>\n", "\n", $text));

        $it = [];

        $it['Kind'] = "T";
        // RecordLocator

        $it['RecordLocator'] = re("#PNR Ref:\s+([A-Z\d]+)#", $text);
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

        $flights = trim(re("#---------------------------------------------------------------(.*?)\n\n\n\n#ms", $text));
        $flights = explode("\n\n\n", $flights);

        foreach ($flights as $fl) {
            $itsegment = [];
            // FlightNumber
            // AirlineName
            $itsegment['AirlineName'] = re("#\s{2,}([A-Z]{2})\s{2,}(\d+)#msi", $fl);
            $itsegment['FlightNumber'] = re(2);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            // DepDate
            // ArrName
            // ArrDate
            $keys = [
                'Dep'=> 'DEPART',
                'Arr'=> 'ARRIVE',
            ];
            $q = "#__VAR__\s+\w+\s+(?<Date>\d+\s+\w+\s+\d+)\s+(?<Name>.*?)\s+(?<Time>[\d+\.]+)(?<APM>[AP]M)#";

            foreach ($keys as $k=>$var) {
                if (preg_match(str_replace("__VAR__", $var, $q), $fl, $m)) {
                    $itsegment[$k . 'Name'] = $m['Name'];
                    $itsegment[$k . 'Date'] = strtotime($m['Date'] . ', ' . str_replace(".", ":", $m['Time']) . ' ' . $m['APM']);
                }
            }

            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            if (isset($itsegment['DepName']) && $itsegment['ArrName']) {
                $itsegment["Duration"] = re("#TOTAL TIME\s+{$itsegment['DepName']}\s+TO\s+{$itsegment['ArrName']}\s+([^\n]+)#", $fl);
            }

            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }
}
