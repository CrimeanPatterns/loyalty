<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class OverviewJourney extends \TAccountChecker
{
    public $mailFiles = "expedia/it-9871571.eml, expedia/it-9976262.eml";
    public $reFrom = "Expedia@uk.expediamail.com";
    public $reSubject = [
        "en"=> "Expedia travel confirmation",
    ];
    public $reBody = 'Expedia';
    public $reBody2 = [
        "en"=> "Journey Overview",
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
        $it['RecordLocator'] = $this->nextText("Itinerary #");

        // TripNumber
        // Passengers
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nexttext("Total"));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->nexttext("Journey"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $xpath = "//div[contains(@id, 'passengerSegmentDetails') and descendant::img[contains(@src, 'rails') and contains(@src, 'train')]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->nextText(["Departure", "Return"], $root)));
            $itsegment = [];
            // FlightNumber
            // DepCode
            $itsegment["DepCode"] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment["DepName"] = $this->nextText("Departure Station", $root);

            // DepAddress
            // DepDate
            $itsegment["DepDate"] = strtotime($this->http->FindSingleNode("./div[2]/ul/li[1]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // ArrCode
            $itsegment["ArrCode"] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment["ArrName"] = $this->nextText("Arrival Station", $root);

            // ArrAddress
            // ArrDate
            $itsegment["ArrDate"] = strtotime($this->http->FindSingleNode("./div[2]/ul/li[3]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // Type
            // Vehicle
            // TraveledMiles
            // Cabin
            $itsegment["Cabin"] = $this->nextText("Ticket type", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment["Duration"] = $this->http->FindSingleNode("./div[2]/ul/li[2]/descendant::text()[normalize-space(.)][2]", $root);

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
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
            //Sun, Dec 9
            '#^(\w+),\s*(\w+)\s+(\d+)\s*$#u',
            //Fri, 24 Nov
            '#^(\w+),\s*(\d+)\s+(\w+)\s*$#u',
        ];
        $out = [
            "$2 $3 $4, $1",
            '$1, $3 $2 ' . $year,
            '$1, $2 $3 ' . $year,
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = date("Y-m-d H:i", EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum));
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
            '€'  => 'EUR',
            'R$' => 'BRL',
            'C$' => 'CAD',
            'SG$'=> 'SGD',
            'HK$'=> 'HKD',
            'AU$'=> 'AUD',
            '$'  => 'USD',
            '£'  => 'GBP',
            'kr' => 'NOK',
            'RM' => 'MYR',
            '฿'  => 'THB',
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
