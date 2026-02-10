<?php

namespace AwardWallet\Engine\blueair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ItineraryConfirmation extends \TAccountChecker
{
    public $mailFiles = "blueair/it-12083580.eml, blueair/it-12083616.eml, blueair/it-12212776.eml, blueair/it-12232657.eml, blueair/it-12232659.eml, blueair/it-12500364.eml";

    public $lang = "en";
    private $reFrom = "@blue-air.ro";
    private $reSubject = [
        "en"=> "Blue Air - Itinerary Confirmation",
        "ro"=> "Blue Air - Confirmare Rezervare",
    ];
    private $reBody = 'Blue Air';
    private $reBody2 = [
        "en"=> "YOUR TICKET(s)",
        "ro"=> "BILET(E)",
    ];

    private static $dictionary = [
        "en" => [],
        "ro" => [
            "Booking"              => "Booking",
            "Name:"                => "Nume:",
            "TOTAL Payment Amount:"=> "TOTAL plata:",
            "Base Fare:"           => "Tarif net:",
            "Date:"                => "Data:",
            "Flight "              => "Zbor ",
            "From:"                => "De la:",
            "To:"                  => "Catre:",
            "Dep."                 => "Plecare",
            "Arr."                 => "Sosire",
            // "Terminal"=>"Terminal",
        ],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts($this->t("Name:")) . "]", null, "#" . $this->t("Name:") . "\s*(.+)#");

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t("TOTAL Payment Amount:")));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->nextText($this->t("Base Fare:")));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("TOTAL Payment Amount:")));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->starts($this->t("Dep.")) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Date:")) . "]", $root, true, "#^" . $this->t("Date:") . "\s*(.+)$#"));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Flight ")) . "]", $root, true, "#^" . $this->t("Flight ") . "(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("From:")) . "]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("From:")) . "]", $root, true, "#" . $this->t("From:") . " (.*?)(?: - " . $this->t("Terminal") . "| \([A-Z]{3}\))#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("From:")) . "]", $root, true, "#" . $this->t("Terminal") . " (\w+)#");

            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Dep.")) . "]", $root, true, "#" . $this->t("Dep.") . " (.+)#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("To:")) . "]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("To:")) . "]", $root, true, "#" . $this->t("To:") . " (.*?)(?: - " . $this->t("Terminal") . "| \([A-Z]{3}\))#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("To:")) . "]", $root, true, "#" . $this->t("Terminal") . " (\w+)#");

            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Arr.")) . "]", $root, true, "#" . $this->t("Arr.") . " (.+)#"), $date);

            // AirlineName
            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
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
        $this->logger->info('Relative date: ' . date('r', $this->date));

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

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
        // $this->http->log($instr);
        $in = [
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //Mon 27 Mar 2017
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
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
