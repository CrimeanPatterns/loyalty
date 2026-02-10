<?php

namespace AwardWallet\Engine\fbusiness\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class TravelDocument extends \TAccountChecker
{
    public $mailFiles = "fbusiness/it-12103328.eml, fbusiness/it-12103353.eml, fbusiness/it-12129976.eml, fbusiness/it-12142581.eml, fbusiness/it-12142582.eml, fbusiness/it-12142591.eml, fbusiness/it-12142605.eml, fbusiness/it-12370057.eml";

    public $lang = "de";
    private $reFrom = "first-business-travel.de";
    private $reSubject = [
        "de"=> "Travel Document - E-Ticket and Itinerary Receipt für",
    ];
    private $reBody = 'www.first-business-travel.de';
    private $reBody2 = [
        "de" => "Bestätigung",
        "de2"=> "Angebot",
    ];

    private static $dictionary = [
        "de" => [
            "Reiseplan"     => ["Reiseplan", "Travel Document - E-Ticket and Itinerary Receipt"],
            "Ticket number:"=> ["Ticket number:", "E-Ticketnummer:"],
        ],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];

        if ($this->date === null
        && (
            ($date = $this->http->FindSingleNode("(//text()[" . $this->starts("Datum:") . "])[last()]", null, true, "#\d+\.\d+\.\d{4}#"))
            || ($date = $this->http->FindSingleNode("(//text()[" . $this->starts("Datum:") . "])[last()]/following::text()[normalize-space(.)][1]", null, true, "#\d+\.\d+\.\d{4}#"))
        )) {
            $this->date = strtotime($date);
        }

        $nodes = $this->http->FindNodes("//text()[" . $this->eq("Airline-Buchungsnr.:") . "]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]");
        $rls = [];

        foreach ($nodes as $str) {
            if (preg_match("#^([A-Z\d]{2})/([A-Z\d]{6})$#", $str, $m)) {
                $rls[$m[1]] = $m[2];
            }
        }

        $xpath = "//text()[" . $this->eq("Flugzeug:") . "]/ancestor::td[.//img][1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if (!$airline = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root, true, "#Flug (\w{2}) \d+$#")) {
                return;
            }

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->eq("Buchungsnummer:") . "]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]")) {
                $airs[$rl][] = $root;
            } else {
                return;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Reiseplan")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]/descendant::text()[normalize-space(.)][1]"));

            // TicketNumbers
            if (empty($tickets = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Ticket number:")) . "]/following::text()[normalize-space(.)][1]", null, "#^([\d-]+)(?:\b|$)#")))) {
                $tickets = array_filter(array_map('trim', explode(",", $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Ticket number:")) . "]", null, true, "#:\s*(.+)#"))));
            }

            if (!empty($tickets)) {
                $it['TicketNumbers'] = $tickets;
            }

            // AccountNumbers
            // Cancelled
            if (count($airs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->nextText("Total:"));

                // BaseFare
                // Currency
                $it['Currency'] = $this->currency($this->nextText("Total:"));
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            if ($date = $this->http->FindSingleNode("//td[" . $this->starts($this->t("Datum:")) . " and " . $this->contains($this->t("Zeit:")) . "]", null, true, "#" . $this->t("Datum:") . "\s*(.*?)\s*" . $this->t("Zeit:") . "#")) {
                if ($this->normalizeDate($date) != null) {
                    $it['ReservationDate'] = $this->date = $this->normalizeDate($date);
                }
            }

            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root, true, "#Flug \w{2} (\d+)$#");

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $dep = implode(" ", $this->http->FindNodes("./table[2]/descendant::table[1]/descendant::tr[1]/../tr[normalize-space(.)][1]/td[3]/descendant::text()[normalize-space(.)]", $root));
                $itsegment['DepName'] = $this->re("#(.*?)(?: TERMINAL|$)#", $dep);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#TERMINAL (.+)#", $dep);

                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate(implode(" ", $this->http->FindNodes("./table[2]/descendant::table[1]/descendant::tr[1]/../tr[normalize-space(.)][1]/td[2]/descendant::text()[normalize-space(.)]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $arr = implode(" ", $this->http->FindNodes("./table[2]/descendant::table[1]/descendant::tr[1]/../tr[normalize-space(.)][2]/td[3]/descendant::text()[normalize-space(.)]", $root));
                $itsegment['ArrName'] = $this->re("#(.*?)(?: TERMINAL|$)#", $arr);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#TERMINAL (.+)#", $arr);

                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate(implode(" ", $this->http->FindNodes("./table[2]/descendant::table[1]/descendant::tr[1]/../tr[normalize-space(.)][2]/td[2]/descendant::text()[normalize-space(.)]", $root)), $itsegment['DepDate']);

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root, true, "#Flug (\w{2}) \d+$#");

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->contains("durchgeführt von:") . "][1]", $root, true, "#durchgeführt von: (.+)#");

                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode(".//td[" . $this->eq("Flugzeug:") . "]/following-sibling::td[1]", $root);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode(".//td[" . $this->eq("Klasse:") . "]/following-sibling::td[1]", $root, true, "# - (.+)#");

                // BookingClass
                $itsegment['BookingClass'] = $this->http->FindSingleNode(".//td[" . $this->eq("Klasse:") . "]/following-sibling::td[1]", $root, true, "#^([A-Z]) - #");

                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode(".//td[" . $this->eq("Flugdauer:") . "]/following-sibling::td[1]", $root);

                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        return $itineraries;
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
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);
        // $this->logger->info('Relative date: '.date('r', $this->date));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = trim($lang, '1234567890');

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $this->http->log('relDate: ' . date('r', $relDate));
        $instr = str_replace(chr(226) . chr(128) . chr(140), "", $instr);

        $in = [
            "#^(?<week>[^\s\d]+) (\d+)\. ([^\s\d]+) (\d+:\d+) Uhr$#", //Fr 23. Mrz 17:00 Uhr
            "#^(\d+:\d+) Uhr$#", //17:00 Uhr
        ];
        $out = [
            "$2 $3 %Y%, $4",
            "$1",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
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
