<?php

namespace AwardWallet\Engine\bahn\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "bahn/it-296063155.eml";
    public $reFrom = "@bahn.de";
    public $reSubject = [
        "en"=> "Reservation confirmation",
    ];
    public $reBody = 'www.bahn.com';
    public $reBody2 = [
        "en"=> "Your booking information:",
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Order number:") . "]", null, true, "#:\s+(.+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->http->FindSingleNode("//text()[" . $this->starts("Ordering customer:") . "]", null, true, "#:\s+(.+?)\s*(?:E-mail:.+)?\s*$#")];

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts("Total price of reservations:") . "]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[" . $this->starts("Total price of reservations:") . "]"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $xpath = "//text()[" . $this->contains("Sitzplatz") . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $parts = explode(", ", $this->http->FindSingleNode(".", $root));

            if (count($parts) < 6) {
                $this->http->log("incorrect parts count");

                return;
            }
            $date = strtotime($this->re("#(\d+\.\d+\.\d{4})#", $parts[0]));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#(.*?)\s+\(ab \d+:\d+\)\s+-\s+.+#", $parts[1]);

            // DepAddress
            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#.*?\s+\(ab (\d+:\d+)\)\s+-\s+.+#", $parts[1]), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#.*?\s+\(ab \d+:\d+\)\s+-\s+(.+)#", $parts[1]);

            // ArrAddress
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // Type
            $itsegment['Type'] = $this->re("#\d+\.\d+\.\d{4}\s+(.+)#", $parts[0]) . ', ' . $parts[4];

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $parts[2];

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $this->re("#Platz\s+(.+)#", $parts[5]);

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

        $this->http->FilterHTML = true;
        $this->http->SetEmailBody($parser->getHTMLBody());
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
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
