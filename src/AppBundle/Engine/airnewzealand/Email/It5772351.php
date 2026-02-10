<?php

namespace AwardWallet\Engine\airnewzealand\Email;

class It5772351 extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-12631391.eml, airnewzealand/it-43287626.eml, airnewzealand/it-5744380.eml, airnewzealand/it-5782506.eml";

    public $reFrom = "@airnz.co.nz";
    public $reSubject = [
        "en"=> "Reservation Price Confirmation", "Booking Reference:",
    ];
    public $reBody = 'Air New Zealand';
    public $reBody2 = [
        "en"=> "Booking reference",
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
        if (!$it['RecordLocator'] = $this->nextText("Booking reference")) {
            $it['RecordLocator'] = $this->nextText("Booking reference:");
        }

        // Passengers
        $travellers = $this->http->FindNodes("//text()[normalize-space()='Passengers on this flight']/ancestor::tr[1]/following-sibling::tr/descendant::td[not(.//td) and string-length(normalize-space())>1][1]");

        if (empty($travellers)) {
            $traveller = $this->http->FindSingleNode('//p[contains(text(),"Here\'s some information to help you plan your trip to")]/preceding-sibling::p', null, false, '/^(.+?),/');

            if ($traveller) {
                $travellers = [$traveller];
            }
        }

        if (empty($travellers)) {
            $traveller = $this->http->FindSingleNode("//td/b[starts-with(text(),'Kia ora ')]", null, false, '/^Kia ora (.+?),/');

            if ($traveller) {
                $travellers = [$traveller];
            }
        }

        if (!empty($travellers)) {
            $it['Passengers'] = array_unique($travellers);
        }

        // AccountNumbers
        $it['AccountNumbers'] = array_filter([$this->nextText("Your Airpoints no.")]);

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

        $xpath = "//text()[normalize-space(.)='Depart' or normalize-space(.)='Departure']/ancestor::tr[1][not(preceding::text()[normalize-space(.)][2][contains(., 'Previous Flight')])]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//tr//img[@alt='itinerary']/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0) {
            $this->logger->debug("segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            if (!($itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)$/"))) {
                if (!($itsegment['FlightNumber'] = $this->http->FindSingleNode("./following::table[1]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/"))) {
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                }
            }

            // DepCode
            if (!$itsegment['DepCode'] = $this->http->FindSingleNode("./following::table[1]", $root, true, "#([A-Z]{3})\s+-\s+[A-Z]{3}#")) {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepName
            if (!($itsegment['DepName'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][2]", $root, true, "#^(.*?)\s+-\s+.*?$#"))) {
                if (!($itsegment['DepName'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "#^(.*?)\s+-\s+.*?$#"))) {
                    if (!$itsegment['DepName'] = $this->http->FindSingleNode("./ancestor::tr[2]/preceding-sibling::tr[1]", $root, true, "#^(.*?)\s+to\s+.*?$#")) {
                        $itsegment['DepName'] = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[2]//h2", $root, true, "#^(.*?)\s+to\s+.*?$#");
                    }
                }
            }

            $xpathLineThrough = "(contains(@style,'text-decoration:line-through') or contains(@style,'text-decoration: line-through'))";

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root) . ', ' . $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root)), false);

            if (empty($itsegment['DepDate'])) {
                $itsegment['DepDate'] = strtotime($this->normalizeDate(join(', ', array_reverse($this->http->FindNodes("./td[1]//span//text()", $root)))));
            }

            if (empty($itsegment['DepDate'])) {
                $dateDep = $this->http->FindSingleNode("following-sibling::tr[normalize-space() and not(descendant-or-self::*[{$xpathLineThrough}])][2]/*[1]", $root);
                $timeDep = $this->http->FindSingleNode("following-sibling::tr[normalize-space() and not(descendant-or-self::*[{$xpathLineThrough}])][1]/*[1]", $root);
                $itsegment['DepDate'] = strtotime($this->normalizeDate($dateDep . ', ' . $timeDep));
            }

            // ArrCode
            if (!$itsegment['ArrCode'] = $this->http->FindSingleNode("./following::table[1]", $root, true, "#[A-Z]{3}\s+-\s+([A-Z]{3})#")) {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrName
            if (!($itsegment['ArrName'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][2]", $root, true, "#^.*?\s+-\s+(.*?)(?:\s*\(Revised Flight\))?$#"))) {
                if (!$itsegment['ArrName'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "#^.*?\s+-\s+(.*?)(?:\s*\(Revised Flight\))?$#")) {
                    if (!$itsegment['ArrName'] = $this->http->FindSingleNode("./ancestor::tr[2]/preceding-sibling::tr[1]", $root, true, "#^.*?\s+to\s+(.*?)(?:\s*\(Revised Flight\))?$#")) {
                        $itsegment['ArrName'] = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[2]//h2", $root, true, "#^.*?\s+to\s+(.*?)(?:\s*\(Revised Flight\))?$#");
                    }
                }
            }

            // ArrDate
            if ($date = $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root)) {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($date . ',' . $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root)), false);
            }

            if (empty($itsegment['ArrDate'])) {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(join(', ', array_reverse($this->http->FindNodes("./td[3]//span//text()", $root)))), false);
            }

            if (empty($itsegment['ArrDate'])) {
                $dateArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space() and not(descendant-or-self::*[{$xpathLineThrough}])][2]/*[3]", $root);
                $timeArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space() and not(descendant-or-self::*[{$xpathLineThrough}])][1]/*[3]", $root);
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($dateArr . ', ' . $timeArr));
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+$/");

            if (!$itsegment['AirlineName'] && strpos($this->http->Response['body'], 'airnewzealand.co') !== false) {
                $itsegment['AirlineName'] = 'NZ';
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            $seats = [];
            $seats = $this->http->FindNodes("following::text()[normalize-space()='Passengers on this flight'][1]/ancestor::tr[1]/following-sibling::tr//tr[not(.//tr)]/td[2]/descendant::text()[normalize-space()][1]", $root, "#^\d+\w$#");
            $seats[] = $this->http->FindSingleNode("following::table[1]", $root, true, "#^[A-Z]{3}\s+-\s+[A-Z]{3}\s+-\s+(\d+\w)#");
            $seats = array_filter($seats);

            if (!empty($seats)) {
                $itsegment['Seats'] = $seats;
            }

            // Duration
            if (!$itsegment['Duration'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root)) {
                $itsegment['Duration'] = $this->http->FindSingleNode("./td[4]", $root);
            }

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
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Air New Zealand') === false
        ) {
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

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //$year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+\s+[^\d\s]+\s+\d{4},\s+\d+:\d+\s+[ap]m)$#",
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);
        // if(preg_match("#[^\d\s-\./:]#", $str)) $str = $this->dateStringToEnglish($str);
        return $str;
    }
}
