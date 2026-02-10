<?php

namespace AwardWallet\Engine\flybe\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightPaymentConfirmationPlain extends \TAccountChecker
{
    public $mailFiles = "flybe/it-8488448.eml, flybe/it-8593081.eml";
    public $reFrom = "do_not_reply@bookings.flybe.com";
    public $reSubject = [
        "en"=> "Flight payment confirmation of your Flybe flight(s)",
    ];
    public $reBody = 'Flybe';
    public $reBody2 = [
        "en"=> "Flight Payment Confirmation",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = strip_tags($this->text);
        // echo $text;
        // die();
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Booking reference:\s+([^\n]+)#ms", $text);

        // TripNumber
        // Passengers
        preg_match_all("#\n([^\n]+)\nFlight\s+From\s+To#", $text, $passengers);
        $it['Passengers'] = $passengers[1];

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->re("#TRANSACTION AMOUNT INCLUDING ALL TAXES AND CHARGES:\s+[A-Z]{3}\s+([\d\.,]+)#", $text);

        // BaseFare
        // Currency
        $it['Currency'] = $this->re("#TRANSACTION AMOUNT INCLUDING ALL TAXES AND CHARGES:\s+([A-Z]{3})\s+[\d\.,]+#", $text);
        $it['TotalCharge'] = $this->re("#TRANSACTION AMOUNT INCLUDING ALL TAXES AND CHARGES:\s+[A-Z]{3}\s+([\d\.,]+)#", $text);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $segments = $this->split("#\n([^\s\d]+ \d+ [^\s\d]+ \d{4}\s+\w{2}\d+\s+)#", $this->re("#Date\s+Flight No\s+[^\n]+(.*?)\n\s*\n\s*\n#ms", $text));

        foreach ($segments as $stext) {
            $itsegment = [];

            if (preg_match("#[^\s\d]+ (?<Date>\d+ [^\s\d]+ \d{4})\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s+(?<DepName>.*?) to (?<ArrName>.*?)\s+(?<DepTime>\d+:\d+)\s+(?<ArrTime>\d+:\d+)#ms", $stext, $m)) {
                $keys = ["AirlineName", "FlightNumber", "DepName", "ArrName"];

                foreach ($keys as $k) {
                    $itsegment[$k] = $m[$k];
                }

                $date = strtotime($this->normalizeDate($m["Date"]));
                $itsegment["DepDate"] = strtotime($m["DepTime"], $date);
                $itsegment["ArrDate"] = strtotime($m["ArrTime"], $date);

                if (preg_match("#" . $itsegment["AirlineName"] . $itsegment["FlightNumber"] . "\s+(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s+#", $text, $m)) {
                    $itsegment["DepCode"] = $m['DepCode'];
                    $itsegment["ArrCode"] = $m['ArrCode'];
                }

                preg_match_all("#" . $itsegment["AirlineName"] . $itsegment["FlightNumber"] . "\s+[A-Z]{3}\s+[A-Z]{3}\s+(\d+\w)#", $text, $m);
                $itsegment["Seats"] = $m[1];
            }
            $itsegment["Operator"] = $this->re("#Operated by (.+)#", $stext);

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

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
            "#^(\d+ [^\s\d]+ \d{4})$#", //04 Jan 2018
        ];
        $out = [
            "$1",
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
