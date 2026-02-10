<?php

namespace AwardWallet\Engine\deltav\Email;

use AwardWallet\Engine\MonthTranslate;

class ScheduleChange2014 extends \TAccountChecker
{
    public $mailFiles = "deltav/it-41964886.eml, deltav/it-7737178.eml, deltav/it-7737346.eml";

    public $reSubject = [
        "en"=> "Schedule Change Notification for Booking",
    ];
    public $reBody = 'Delta Vacations';
    public $reBody2 = [
        "en"=> "NEW FLIGHT ITINERARY",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->eq("Seat") . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);
        $seats = [];

        foreach ($nodes as $root) {
            $seats[$this->http->FindSingleNode("./td[3]", $root)][] = str_replace("-", "", $this->http->FindSingleNode("./td[4]", $root));
        }

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Confirmation:");

        // Passengers
        $it['Passengers'] = array_merge([], array_filter($this->http->FindNodes("//text()[" . $this->eq("Name") . "]/ancestor::tr[1]/following-sibling::tr/td[2]")));

        $xpath = "//text()[" . $this->eq("NEW FLIGHT ITINERARY") . "]/ancestor::tr[1]/following-sibling::tr[" . $this->contains("departs") . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root)), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][2]", $root)), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^(\w{2})\d+$#");

            $cabinValue = $this->http->FindSingleNode("./following-sibling::tr[1]//text()[" . $this->eq("Cabin:") . "]/following::text()[normalize-space(.)][1]", $root);

            // Cabin
            if (preg_match('/^(.*\bCabin\b.*?)(?:\s*\([A-Z]{1,2}\s|$)/i', $cabinValue, $matches)) {
                $itsegment['Cabin'] = preg_replace('/\s*\bCabin\b\s*/i', '', $matches[1]);
            }

            // BookingClass
            if (preg_match('/\(([A-Z]{1,2})\s/', $cabinValue, $matches)) {
                $itsegment['BookingClass'] = $matches[1];
            }

            // Seats
            if (isset($seats[$itsegment['FlightNumber']])) {
                $itsegment['Seats'] = $seats[$itsegment['FlightNumber']];
            }

            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode("./following-sibling::tr[1]//text()[" . $this->eq("Stops:") . "]/following::text()[normalize-space(.)][1]", $root);

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@deltavacations.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Delta Vacations') === false) {
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

        $result = [
            'emailType'  => 'ScheduleChange2014' . ucfirst($this->lang),
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
            "#^[^\d\s]+\s+(\d+)-([^\d\s]+)-(\d{2})$#", //Mon 24-Nov-14
            "#^(?:departs|arrives)\s+(\d+:\d+)\s+([AP])\.(M)\.(?:\s+\+\s*\d+\s+day)?\s*s?$#", // arrives 8:00 A.M. + 1 day  |   arrives 7:10 A.M. + 2 day s
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
