<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "ana/it-10039193.eml, ana/it-10084197.eml, ana/it-7584210.eml, ana/it-7788242.eml";

    public static $dictionary = [
        "en" => [
            "SEGMENTS"=> "#((?:Name[:]*|\n\s*\w{2}\d+\s+From).*?)(?:About Boarding|Check-in)#ms",
            "RL"      => "#Reservation Number\s*\n\s*(?:\(It may differ from the one for other carriers.\)\s*\n\s*)?([A-Z\d]+)\s*\n#ms",
        ],
        "fr" => [
            "SEGMENTS"=> "#(Name:.*?A l'embarquement)#ms",
            "RL"      => "#Numéro de ré\s+([A-Z\d]+)#ms",
        ],
        "de" => [
            "SEGMENTS"=> "#(Name:.*?- Boarding)#ms",
            "RL"      => "#ANA-Buchungsnummer\s+([A-Z\d]+)#ms",
        ],
    ];

    public $lang = "en";
    private $reFrom = ".ana.";
    private $reSubject = [
        "en" => "[From ANA] Completion of Check-in",
        "fr" => "[De la part d'ANA] Enregistrement terminé",
        "de" => "[Von ANA] Check-in abgeschlossen",
    ];
    private $reBody = 'ANA';
    private $reBody2 = [
        "en"  => "Thank you very much for flying with ANA",
        'en2' => 'Please find the mobile boarding pass for Flight',
        "fr"  => "Merci d'avoir choisi ANA",
        "de"  => "Vielen Dank, dass Sie mit ANA fliegen",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if(strpos($headers["from"], $this->reFrom)===false)
        //			return false;

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

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

        // $this->http->FilterHTML = true;
        $this->http->setEmailBody(str_replace("<br>", "", $parser->getHTMLBody()));
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($itineraries);

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

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(&$itineraries)
    {
        $text = $this->http->XPath->query(".")->item(0)->nodeValue;

        if ($this->lang == 'en') {
            $text = preg_replace("#[^\s\w-:\.\(\)]+#ms", "", $text);
        }
        // echo $text;
        // die();
        preg_match_all($this->t("SEGMENTS"), $text, $segments);
        // print_r($segments);
        // die();
        $airs = [];

        foreach ($segments[1] as $stext) {
            if (!$rl = $this->re($this->t("RL"), $stext)) {
                if (!$rl = $this->re("#ANA Reservation Number\s+(\w+)#ms", $text)) {
                    $this->http->log("rl not matched");

                    return;
                }
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            // TicketNumbers
            // AccountNumbers
            $it['Passengers'] = [];
            $it['TicketNumbers'] = [];
            $it['AccountNumbers'] = [];

            foreach ($segments as $stext) {
                $it['Passengers'][] = trim($this->re("#Name:\s+(.+)#", $stext));
                $it['TicketNumbers'][] = trim($this->re("#ETKT No.:\s+(.+)#", $stext));
                $it['AccountNumbers'][] = trim($this->re("#\n\s*(.*?FQTV FFP\s+-\s+.+)#", $stext));
            }
            $it['Passengers'] = array_filter(array_unique($it['Passengers']));
            $it['TicketNumbers'] = array_filter(array_unique($it['TicketNumbers']));
            $it['AccountNumbers'] = array_filter(array_unique($it['AccountNumbers']));

            if (empty($it['Passengers'])) {
                preg_match_all("#Passenger Name\s+(\S.*?)\n#msi", $text, $pass);
                $it['Passengers'] = array_unique(array_map("trim", $pass[1]));
            }

            if (empty($it['TicketNumbers'])) {
                preg_match_all("#e-Ticket number\s+(\S.*?)\n#msi", $text, $pass);
                $it['TicketNumbers'] = array_unique(array_map("trim", $pass[1]));
            }

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
            foreach ($segments as $stext) {
                $root = null;
                $date = strtotime($this->normalizeDate($this->re("#Date\s*:\s+(.+)#", $stext)));
                $itsegment = [];

                // FlightNumber
                if (!$itsegment['FlightNumber'] = $this->re("#Flight:\s+\w{2}(\d+)\s+[A-Z]{3}\s+-\s+[A-Z]{3}#", $stext)) {
                    $itsegment['FlightNumber'] = $this->re("#\n\s*\w{2}(\d+)\s+From#", $stext);
                }

                if (empty($itsegment['FlightNumber']) && preg_match('/Flight Information\s+([A-Z\d]{2})\s*(\d+)\s*/', $stext, $m)) {
                    $itsegment['AirlineName'] = $m[1];
                    $itsegment['FlightNumber'] = $m[2];
                }

                // DepCode
                // DepName
                // DepartureTerminal
                // ArrCode
                // ArrName
                // ArrivalTerminal
                $itsegment['DepCode'] = $this->re("#Flight:\s+\w{2}\d+\s+([A-Z]{3})\s+-\s+[A-Z]{3}#", $stext);
                $itsegment['ArrCode'] = $this->re("#Flight:\s+\w{2}\d+\s+[A-Z]{3}\s+-\s+([A-Z]{3})#", $stext);

                if (
                    !$itsegment['ArrCode'] && !$itsegment['ArrCode'] && (preg_match("#\n\s*\w{2}\d+\s+From\s+(.*?)\s+to\s+(.+)#", $stext, $m) || preg_match('/Flight Information\s+[A-Z\d]{2}\s*\d+\s*(.+?)\s*-\s*(.+)/', $stext, $m))
                ) {
                    $itsegment['DepCode'] = $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $itsegment['DepName'] = $m[1];
                    $itsegment['ArrName'] = $m[2];
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#Scheduled? Time\s*:\s+(.+)#", $stext), $date);

                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                if (!isset($itsegment['AirlineName']) && !$itsegment['AirlineName'] = $this->re("#Flight:\s+(\w{2})\d+\s+[A-Z]{3}\s+-\s+[A-Z]{3}#", $stext)) {
                    $itsegment['AirlineName'] = $this->re("#\n\s*(\w{2})\d+\s+From#", $stext);
                }

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                $itsegment['BookingClass'] = $this->re("#Seat No.:\s+\(([A-Z])\)\s+\d+\w#", $stext);

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->re("#Seat No.:\s+\([A-Z]\)\s+(\d+\w)#", $stext);

                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }
    }

    private function t($word)
    {
        // $this->http->log($word);
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
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
            "#^(\d+)([^\s\d]+)$#", //07DEC
        ];
        $out = [
            "$2 $3 $4, $1",
            "$1 $2 $year",
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
