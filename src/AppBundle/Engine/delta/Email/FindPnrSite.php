<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class FindPnrSite extends \TAccountChecker
{
    public $mailFiles = "delta/it-12258191.eml";

    public $lang = "en";
    private $reFrom = "@n.delta.com";
    private $reSubject = [
        "en"=> "Gate Change:",
    ];
    private $reBody = 'delta.com';
    private $reBody2 = [
        "en"=> "MANAGE MY TRIP",
    ];

    private static $dictionary = [
        "en" => [],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];

        if (strpos($this->http->Response['body'], 'For real time updates, please check the') !== false) {
            if ($link = $this->http->FindSingleNode("(//a[contains(@href, '/findPnr.action') and contains(@href, 'mkcpgn') and contains(@href, 'lastName') and contains(@href, 'firstName') and contains(@href, 'confirmationNo')])[1]/@href")) {
                $fields = [];
                parse_str($this->re("#\?(.+)#", $link), $fields);

                $required = ['confirmationNo', 'firstName', 'lastName', 'mkcpgn'];

                foreach ($required as $f) {
                    if (!isset($fields[$f])) {
                        $this->logger->info("not found field " . $f);

                        return;
                    }
                }
                $fields['interstitial'] = 'true';
                $this->http->PostURL('https://www.delta.com/mytrips/findPnr.action?mkcpgn=' . $fields['mkcpgn'], $fields);

                if ($this->http->Response['code'] == '200') {
                    $it = [];

                    $it['Kind'] = "T";

                    // RecordLocator
                    $it['RecordLocator'] = $this->nextText("CONFIRMATION#:");

                    // TripNumber
                    // Passengers
                    $it['Passengers'] = array_map('strtoupper', $this->http->FindNodes("//*[contains(@class, 'passengersName')]"));

                    // TicketNumbers
                    $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->starts("eTicket #") . "]", null, "#eTicket \#(\d+)$#");

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
                    $xpath = "//text()[" . $this->starts("Depart:") . "]/ancestor::*[@class='flightOrigDestination']";
                    $nodes = $this->http->XPath->query($xpath);

                    foreach ($nodes as $root) {
                        $date = $this->normalizeDate($this->http->FindSingleNode(".//*[contains(@class, 'deptdateitin')]", $root));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding::*[contains(@class, 'flightDataHeader OneLinkNoTx')]", $root, true, "#^\w{2} (\d+)$#");

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode(".//img[contains(@src, '/blue-arrow.png')]/preceding::text()[normalize-space(.)][1]", $root);

                        // DepName
                        // DepartureTerminal
                        $itsegment['DepartureTerminal'] = $this->http->FindSingleNode(".//*[@class='boardingtime']/*[1]//text()[" . $this->starts("Terminal ") . "]", $root, true, "#Terminal (.+)#");

                        // DepDate
                        $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts("Depart:") . "]/following::text()[normalize-space(.)][1]", $root), $date);

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode(".//img[contains(@src, '/blue-arrow.png')]/following::text()[normalize-space(.)][1]", $root);

                        // ArrName
                        // ArrivalTerminal
                        $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//*[@class='boardingtime']/*[2]//text()[" . $this->starts("Terminal ") . "]", $root, true, "#Terminal (.+)#");

                        // ArrDate
                        $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts("Arrive:") . "]/following::text()[normalize-space(.)][1]", $root), $itsegment['DepDate']);

                        // AirlineName
                        $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding::*[contains(@class, 'flightDataHeader OneLinkNoTx')]", $root, true, "#^(\w{2}) \d+$#");

                        // Operator
                        // Aircraft
                        $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[5]", $root);

                        // TraveledMiles
                        // AwardMiles
                        // Cabin
                        $itsegment['Cabin'] = $this->http->FindSingleNode(".//*[@class='classCabinPadding']//a", $root, true, "#(.*?) \([A-Z]\)#");

                        // BookingClass
                        $itsegment['BookingClass'] = $this->http->FindSingleNode(".//*[@class='classCabinPadding']//a", $root, true, "#\(([A-Z])\)#");

                        // PendingUpgradeTo
                        // Seats
                        preg_match_all("#\b(\d+[A-Z])\b#", $this->http->FindSingleNode("//text()[" . $this->starts("SEATS:") . "]/following::text()[normalize-space(.)][1]", $root), $m);
                        $itsegment['Seats'] = $m[1];

                        // Duration
                        // Meal
                        // Smoking
                        // Stops

                        $it['TripSegments'][] = $itsegment;
                    }

                    $itineraries[] = $it;
                }
            }
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
        // !!!!! can't parse big tree
        return null;
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
            "#^Wed, (\d+) ([^\s\d]+) (\d{4})$#", //Wed, 04 Apr 2018
        ];
        $out = [
            "$1 $2 $3",
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
