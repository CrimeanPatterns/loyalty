<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;

// TODO: merge with parsers aeroplan/It4351513 (in favor of aeroplan/It4351513)

class Changes extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-4100855.eml, aeroplan/it-4100863.eml, aeroplan/it-8416620.eml, aeroplan/it-8416728.eml";

    public $reFrom = "flightnotification@aircanada.ca";
    public $reSubject = [
        "en"=> "Air Canada Flight Notification",
        "fr"=> "sur les vols d'Air Canada",
    ];
    public $reBody = 'Air Canada';
    public $reBody2 = [
        "en"=> "Revised:",
        "fr"=> "Numéro de vol:",
    ];

    public static $dictionary = [
        "en" => [
            "Revised:"=> ["Revised:", "Scheduled:", "*REVISED*"],
            "reDep"   => "#Departing\s+(?<Name>.*?)(?:\s+\((?<Code>[A-Z]{3})\))?\s+on\s+(?<Date>\w+\s+\d+,\s+\d{4})\s+@\s+(?<Time>\d+:\d+)#",
            "reArr"   => "#Arriving\s+in\s+(?<Name>.*?)(?:\s+\((?<Code>[A-Z]{3})\))?(\s*on)?\s+(?<Date>\w+\s+\d+,\s+\d{4})\s+@\s+(?<Time>\d+:\d+)#",
            //			"Arriving in" => "",
            //			"Departure Terminal" => "",
            //			"Arrival Terminal" => "",
        ],
        "fr" => [
            "Revised:"           => ["*RÉVISÉ*", "Révisé:", "Prévu:"],
            "Flight Number:"     => "Numéro de vol:",
            "reDep"              => "#Départ de\s+(?<Name>.*?)(?:\s+\((?<Code>[A-Z]{3})\))?\s+le\s+(?<Date>\d+\s+[^\s\d]+,\s+\d{4})\s+à\s+(?<Time>\d+:\d+)#",
            "reArr"              => "#Arrivée à\s+(?<Name>.*?)(?:\s+\((?<Code>[A-Z]{3})\))?\s+le\s+(?<Date>\d+\s+[^\s\d]+,\s+\d{4})\s+à\s+(?<Time>\d+:\d+)#",
            "Arriving in"        => "Arrivée à",
            "Departure Terminal" => "Aérogare d'embarquement",
            "Arrival Terminal"   => "Aérogare d'arrivée",
        ],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $this->text = preg_replace("#\n\s*>+#", "\n", $this->text);
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

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

        $flight = $this->re("#(?:" . $this->opt($this->t("Revised:")) . ")\s+(.*?" . $this->t("Arriving in") . ".*?)\n\s*\n#msi", $this->text);

        if (
            preg_match($this->t("reDep"), $flight, $dep)
            && preg_match($this->t("reArr"), $flight, $arr)
        ) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#" . $this->t("Flight Number:") . "\s+\w{2}(\d+)#", $this->text);

            // DepCode
            $itsegment['DepCode'] = isset($dep['Code']) && !empty(trim($dep['Code'])) ? $dep['Code'] : TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $dep['Name'];

            // DepartureTerminal
            //			$itsegment['DepartureTerminal'] = $this->re("#Départ\s+[^\n]+\n.*Aérogare d'embarquement\s+(.*?),#", $flight);
            $itsegment['DepartureTerminal'] = $this->re("#" . $this->t("Departure Terminal") . "[ ]+(.*?),#", $flight);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($dep['Date'] . ', ' . $dep['Time']));

            // ArrCode
            $itsegment['ArrCode'] = isset($arr['Code']) && !empty(trim($arr['Code'])) ? $arr['Code'] : TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $arr['Name'];

            // ArrivalTerminal
            //			$itsegment['ArrivalTerminal'] = $this->re("#Arrivée\s+[^\n]+\n.*Aérogare d'arrivée\s+(.*?),#", $flight);
            $itsegment['ArrivalTerminal'] = $this->re("#" . $this->t("Arrival Terminal") . "[ ]+(.*?),#", $flight);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($arr['Date'] . ', ' . $arr['Time']));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#" . $this->t("Flight Number:") . "\s+(\w{2})\d+#", $this->text);

            // Operator
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
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $itineraries = [];

        $this->text = $body = strip_tags($parser->getBody());

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+)$#",
            "#^(\d+) ([^\s\d]+), (\d{4}), (\d+:\d+)$#", //13 novembre, 2016, 11:05
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", str_replace("*", "\*", $field)) . ')';
    }
}
