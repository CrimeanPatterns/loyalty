<?php

namespace AwardWallet\Engine\alitalia\Email;

class It4577542 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "alitalia/it-4577542.eml";

    public $reFrom = "noreply@alitalia.it";
    public $reSubject = [
        "it"=> "Alitalia StaffTicketing: Acquisto effettuato correttamente	",
    ];
    public $reBody = 'ALITALIA';
    public $reBody2 = [
        "en"=> "RESERVATION FILE REFERENCE NUMBER:",
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
        $it['RecordLocator'] = $this->re("#RESERVATION FILE REFERENCE NUMBER:\s+\w{2}\s+(\w+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->re("#NAME:\s+([^\n]+)#")];

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

        $flights = $this->re("#FROM/TO\s+FLIGHT\s+DATE\s+TIME\s+STS\s+CLS\s+BAG\s+(.*?)\s+DATE AND PLACE OF ISSUE:#ms");

        preg_match_all("#(?<DepName>\w.*?)\s{2,}(?<DepCode>[A-Z]{3})\s{2,}(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s{2,}(?<Date>\d+\w+)\s{2,}(?<DepTime>\d{2}\d{2})\s{2,}OK\s{2,}(?<BookingClass>\w)\s{2,}\w+\s*\n\s*(?<ArrName>\w.*?)\s{2,}(?<ArrCode>[A-Z]{3})#", $flights, $segments, PREG_SET_ORDER);
        // print_r($segments);

        foreach ($segments as $segment) {
            $itsegment = [];

            $keys = [
                "FlightNumber",
                "AirlineName",
                "DepName",
                "DepCode",
                "ArrName",
                "ArrCode",
                "BookingClass",
            ];

            foreach ($keys as $key) {
                if (isset($segment[$key])) {
                    $itsegment[$key] = $segment[$key];
                }
            }

            $itsegment["DepDate"] = strtotime($this->normalizeDate($segment['Date'] . ',' . $segment['DepTime']));
            $itsegment["ArrDate"] = MISSING_DATE;

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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

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

        $this->text = $parser->getPlainBody();
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)(\w+),(\d{2})(\d{2})$#",
        ];
        $out = [
            "$1 $2 $year, $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str = false, $c = 1)
    {
        if ($str === false) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
