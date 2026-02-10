<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;

class InformationRegardingYourFlights extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-10147280.eml, airfrance/it-10311550.eml";
    public $reFrom = "roc@xmedia.airfrance.fr";
    public $reSubject = [
        "en"=> "Information regarding your flights",
    ];
    public $reBody = 'airfrance';
    public $reBody2 = [
        "en"=> "We inform you of the change",
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains("Booking reference") . "]/following::text()[normalize-space(.)][1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Attention")];

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
        if ($this->http->FindSingleNode("//text()[" . $this->contains(["We inform you of the change", "We inform you of a schedule change on your flight"]) . "]")) {
            $it['Status'] = 'changed';
        }

        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->contains(["arrival at"]) . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $stext = $this->http->FindSingleNode(".", $root);
            $itsegment = [];

            // New departure Under flight number 995A on 13DEC from JOHANNESBURG JNB A at 2050,  arrival at PARIS CDG at 0645.
            if (preg_match("#New departure Under flight number (?<FlightNumber>\d+)[A-Z]? on (?<Date>\d+[^\s\d]+) from (?<DepName>.*?) (?<DepCode>[A-Z]{3})(?:\s+(?<DepartureTerminal>\w+))? at (?<DepTime>\d+),\s+arrival at (?<ArrName>.*?) (?<ArrCode>[A-Z]{3})(?:\s+(?<ArrivalTerminal>\w+))? at (?<ArrTime>\d+)\.#", $stext, $m)) {
                $date = strtotime($this->normalizeDate($m['Date']));
                $itsegment['DepDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $m['DepTime']), $date);
                $itsegment['ArrDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $m['ArrTime']), $date);
            // You have then a new connection on flight AF7518 on 14DEC, departure from PARIS CDG 2F at 0955, arrival at TOULOUSE TLS at 1115.
            // You have a new connection on flight AF1048 on 07DEC: departure from PARIS CDG 2F at 1725, arrival at BARCELONA BCN at 1905.
            // You have been rebooked in the same cabin on flight AF 019 on 16DEC, departure from  NEW YORK JFK 1 at 1940, arrival at PARIS ORY at 0900.
              //We inform you of a schedule change on your flight AF7469 on 31AUG from PERPIGNAN to PARIS: departure from PERPIGNAN PGF at 20:40, arrival at PARIS ORY at 22:10.
            } elseif (preg_match("#on (?:your )?flight (?<AirlineName>\w{2})\s*(?<FlightNumber>\d+)[A-Z]? on (?<Date>\d+[^\s\d]+)(?: from [A-Z \-]+? to [A-Z \-]+?)?[,:] departure from (?<DepName>.*?) (?<DepCode>[A-Z]{3})(?:\s+(?<DepartureTerminal>\w+))? at (?<DepTime>\d{1,2}:?\d{2}), arrival at (?<ArrName>.*?) (?<ArrCode>[A-Z]{3})(?:\s+(?<ArrivalTerminal>\w+))? at (?<ArrTime>\d{1,2}:?\d{2})\.#", $stext, $m)) {
                $date = strtotime($this->normalizeDate($m['Date']));
                $itsegment['DepDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $m['DepTime']), $date);
                $itsegment['ArrDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $m['ArrTime']), $date);
            // New departure from MEXICO CITY MEX 1 at 2045, arrival at PARIS CDG at 1430.
            } elseif (preg_match("#New departure from (?<DepName>.*?) (?<DepCode>[A-Z]{3})(?:\s+(?<DepartureTerminal>\w+))? at (?<DepTime>\d+), arrival at (?<ArrName>.*?) (?<ArrCode>[A-Z]{3})(?:\s+(?<ArrivalTerminal>\w+))? at (?<ArrTime>\d+)\.#", $stext, $m)) {
                $prev = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root);

                if (preg_match("#We inform you of a schedule change on your flight (?<AirlineName>\w{2})\s*(?<FlightNumber>\d+)[A-Z]? on (?<Date>\d+[^\s\d]+)\.#", $prev, $pm)) {
                    $date = strtotime($this->normalizeDate($pm['Date']));
                    $itsegment['DepDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $m['DepTime']), $date);
                    $itsegment['ArrDate'] = strtotime(preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $m['ArrTime']), $date);
                    $itsegment['AirlineName'] = $pm['AirlineName'];
                    $itsegment['FlightNumber'] = $pm['FlightNumber'];
                }
            } elseif (preg_match("#New departure from (?<DepName>.*?) (?<DepCode>[A-Z]{3})(?:\s+(?<DepartureTerminal>\w+))? at (?<DepTime>\d+), arrival at (?<ArrName>.*?) (?<ArrCode>[A-Z]{3})(?:\s+(?<ArrivalTerminal>\w+))? at (?<ArrTime>\d+)\.#", $stext, $m)) {
            }

            $keys = ["FlightNumber", "AirlineName", "DepCode", "DepName", "DepartureTerminal", "ArrCode", "ArrName", "ArrivalTerminal"];

            foreach ($keys as $k) {
                if (isset($m[$k])) {
                    $itsegment[$k] = $m[$k];
                }
            }

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
            "#^(\d+)([^\s\d]+)$#", //16DEC
        ];
        $out = [
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
            '₹'=> 'INR',
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
