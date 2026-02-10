<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class UsingAmadeusSendMail599 extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-12070425.eml, amadeus/it-12070569.eml";

    public $lang = "en";
    private $reFrom = "@amadeus.com";
    private $reSubject = [
        "en"=> "ELECTRONIC TICKET",
    ];
    private $reBody = ['Using Amadeus Send Mail', '.amadeus.com'];
    private $reBody2 = [
        "en"  => "Reservation General Information",
        'en2' => 'PLEASE CHECK WITH THE AIRLINE THE MAXIMUM BAGGAGE ALLOWANCE',
        'en3' => 'Your Travel Information',
    ];

    private static $dictionary = [
        "en" => [
            ' FOR '             => [' FOR ', ' For '],
            'Flight :'          => ['Flight :', 'Flight:'],
            'Aircraft Type :'   => ['Aircraft Type :', 'Aircraft Type:', 'Aircraft Type'],
            'Flight Class :'    => ['Flight Class :', 'Flight Class:', 'Flight Class'],
            'Flight Duration :' => ['Flight Duration :', 'Flight Duration:', 'Flight Duration'],
            'Flight Type :'     => ['Flight Type :', 'Flight Type:', 'Flight Type'],
        ],
    ];
    private $date = null;

    /** @var \PlancakeEmailParser */
    private $parser;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["from"]) && strpos($headers["from"], $this->reFrom) === false) {
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

        foreach ($this->reBody as $reBody) {
            if (strpos($body, $reBody) !== false) {
                return $flag = true;
            }
        }

        if (!isset($flag)) {
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

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

    private function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Pnr Number :") . "]", null, true, "#Pnr Number : (.+)#");

        // TripNumber
        // Passengers
        $rootPax = $this->http->XPath->query("//text()[" . $this->eq("Passenger Name(s) :") . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()]");

        foreach ($rootPax as $root) {
            if ($this->http->XPath->query("./descendant::td[normalize-space()!=''][1][{$this->starts(['Passenger', 'Fight'])}]", $root)->length > 0
            || $this->http->XPath->query("./descendant::td[normalize-space()!='']", $root)->length > 1) {
                break;
            }
            $it['Passengers'][] = $this->http->FindSingleNode("./descendant::td[normalize-space()!='']", $root, false, "#^[\d\s\.]*(.+)$#");
        }

        // TicketNumbers
        $it['TicketNumbers'] = array_unique($this->http->FindNodes("//text()[({$this->contains('/ETKT ')})and({$this->contains($this->t(' FOR '))})]", null, "#\b[A-Z\d]{2}/ETKT (.*?){$this->opt($this->t(' FOR '))}#"));
        // AccountNumbers
        $node = array_filter(array_unique($this->http->FindNodes("//text()[{$this->starts('Frequent Traveller')}]",
            null, "#:\s*(.+)[ ]+For#i")));

        if (!empty($node)) {
            $it['AccountNumbers'] = $node;
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
        $xpath = "//text()[{$this->starts("Departure")}]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");

            return [];
        }
//        $this->logger->info("xpath: {$xpath}");
        foreach ($nodes as $root) {
            // $date = $this->normalizeDate($this->http->FindSingleNode("./tr[1]/descendant::text()[normalize-space(.)][2]", $root));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Flight :'))}]", $root, true, "# \w{2} (\d+)$#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./tr[3]/td[1]", $root)
                . ', ' . $this->http->FindSingleNode("./tr[4]/td[1]", $root);

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[5]/td[1]", $root, true, "#Terminal (.+)#");

            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("./tr[2]/*[name() = 'td' or name() = 'th'][2]", $root));

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[3]/td[normalize-space()!=''][2]", $root)
                . ', ' . $this->http->FindSingleNode("./tr[4]/td[normalize-space()!=''][2]", $root);

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[5]/td[3]", $root, true, "#Terminal (.+)#");

            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("./tr[2]/*[name() = 'td' or name() = 'th'][4]", $root));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Flight :'))}]", $root, true, "# (\w{2}) \d+$#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->nextText($this->t("Aircraft Type :"), $root);

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#(.*?) \([A-Za-z]\)#", $this->nextText($this->t("Flight Class :"), $root));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\(([A-Za-z])\)#", $this->nextText($this->t("Flight Class :"), $root));

            // PendingUpgradeTo
            // Seats
            $node = $this->nextText($this->t("Reserved Seats"), $root);

            if (preg_match_all("#Seat:\s+(\d+[A-z])\b#", $node, $m)) {
                $itsegment['Seats'] = $m[1];
            }
            // Duration
            $itsegment['Duration'] = $this->nextText($this->t("Flight Duration :"), $root);

            // Meal
            if (!empty($node = $this->nextText($this->t("Meals"), $root))) {
                $itsegment['Meal'] = $this->nextText($this->t("Meals"), $root);
            }
            // Smoking
            // Stops
            $node = $this->nextText($this->t("Flight Type :"), $root);

            if (preg_match("#^non[\ ]*stop$#i", $node)) {
                $itsegment['Stops'] = 0;
            }

            if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName']) && !empty($itsegment['DepDate']) && !empty($itsegment['ArrDate'])) {
                $itsegment['DepCode'] = $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
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
        //		$this->logger->info($instr);
        $in = [
            "#^(\d+:\d+)\s*\((\d+) ([^\s\d]+) (\d{4})\)$#", //05:30 (19 Mar 2018)
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', EmailDateHelper::calculateDateRelative(str_replace('%Y%', '', $str), $this, $this->parser)), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
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

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
