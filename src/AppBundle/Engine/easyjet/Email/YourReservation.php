<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-12232083.eml, easyjet/it-12232236.eml, easyjet/it-12232642.eml, easyjet/it-49583100.eml";

    public $lang = "pt";
    private $reFrom = "@email.easyJet.com";
    private $reSubject = [
        "pt" => "A tua reserva",
        "ca" => "La teva reserva",
        "nl" => "er is nog tijd om uw boeking te beheren",
    ];
    private $reBody = 'easyjet.com';
    private $reBody2 = [
        "pt" => "AS TUAS INFORMAÇÕES DE VOO",
        "ca" => "INFORMACIÓ SOBRE EL TEU VOL",
        "nl" => "UW VLUCHTGEGEVENS",
    ];

    private static $dictionary = [
        "pt" => [],
        "ca" => [
            "A tua reserva"             => "La teva reserva",
            "AS TUAS INFORMAÇÕES DE VOO"=> "INFORMACIÓ SOBRE EL TEU VOL",
            "Passageiro(s)"             => "Passatger(s)",
            "Partida às"                => "Surt",
            "Chegada às"                => "Arriba",
            "Terminal"                  => "Term.",
        ],
        "nl" => [
            "A tua reserva"              => "Uw boeking",
            "AS TUAS INFORMAÇÕES DE VOO" => "UW VLUCHTGEGEVENS",
            "Passageiro(s)"              => "Passagier(s)",
            "Partida às"                 => "Vertrekt",
            "Chegada às"                 => "Arriveert",
            //"Terminal" => "",
        ],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("A tua reserva") . " ([A-Z\d]+)#", $this->parser->getSubject());

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//table[" . $this->eq($this->t("Passageiro(s)")) . "]/following-sibling::table[1]/descendant::text()[normalize-space(.)][position()<last()]");

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
        $xpath = "//text()[" . $this->eq($this->t("AS TUAS INFORMAÇÕES DE VOO")) . "]/following::img[contains(@src, '/airplane.png')]/ancestor::tr[2]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[4]", $root, true, "#^\w{3}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./tr[2]//b[1]", $root, true, "#(.*?)(?: " . $this->t("Terminal") . "|$)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[2]//b[1]", $root, true, "#" . $this->t("Terminal") . " (\w+)#");

            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Partida às")) . "]/ancestor::tr[1]", $root, true, "#" . $this->t("Partida às") . " (.+)#"));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]//b[2]", $root, true, "#(.*?)(?: " . $this->t("Terminal") . "|$)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[2]//b[2]", $root, true, "#" . $this->t("Terminal") . " (\w+)#");

            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Chegada às")) . "]/ancestor::tr[1]", $root, true, "#" . $this->t("Chegada às") . " (.+)#"));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[4]", $root, true, "#^(\w{3})\d+$#");

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
        $this->parser = $parser;
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);
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
            "#^(\d+:\d+) (\d+-\d+-\d{4})$#", //15:35 07-01-2017
        ];
        $out = [
            "$2, $1",
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
