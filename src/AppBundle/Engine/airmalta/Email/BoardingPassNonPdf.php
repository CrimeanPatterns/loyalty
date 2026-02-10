<?php

namespace AwardWallet\Engine\airmalta\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassNonPdf extends \TAccountChecker
{
    public $mailFiles = "airmalta/it-7338955.eml, airmalta/it-7543926.eml";
    public $reFrom = "@airmalta.com";
    public $reSubject = [
        "en"=> "Your Air Malta Boarding Pass",
    ];
    public $reBody = 'Air Malta';
    public $reBody2 = [
        "fr"=> "Numéro de Réservation:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Numéro de Réservation:");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Passager:")];

        // TicketNumbers
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

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)#", $this->nextText("Vol:"));

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = $this->nextText("De:");

        // DepartureTerminal
        $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("//text()[" . $this->eq("De:") . "]/ancestor::td[1][count(./descendant::text()[normalize-space(.)])=4]/descendant::text()[normalize-space(.)][3]");

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("De:") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]")));

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->nextText("À:");

        // ArrivalTerminal
        $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("//text()[" . $this->eq("À:") . "]/ancestor::td[1][count(./descendant::text()[normalize-space(.)])=4]/descendant::text()[normalize-space(.)][3]");

        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("À:") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]")));

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+#", $this->nextText("Vol:"));

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        $itsegment['BookingClass'] = $this->re("#^([A-Z])$#", $this->nextText("Vol:", null, 3));

        // PendingUpgradeTo
        // Seats
        // Duration
        // Meal
        // Smoking
        // Stops

        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

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

        $pdfs = $parser->searchAttachmentByName(".*.pdf");

        if (isset($pdfs[0])) {
            return null;
        }// pdf parse in BoardingPassPdf

        $this->http->FilterHTML = true;
        $this->http->setBody($parser->getHTMLBody());
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

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
            "#^(\d+ [^\d\s]+ \d{4}) - (\d+:\d+)$#", //23 Feb 2016 - 11:30
        ];
        $out = [
            "$1, $2",
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
