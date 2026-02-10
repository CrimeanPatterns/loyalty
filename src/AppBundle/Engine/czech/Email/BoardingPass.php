<?php

namespace AwardWallet\Engine\czech\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/BoardingPass, aviancataca/TicketDetails, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "czech/it-11253431.eml, czech/it-1953585.eml, czech/it-2053169.eml";
    public $reFrom = "@czechairlines.com";
    public $reSubject = [
        "en"=> "Boarding Pass",
    ];
    public $reBody = 'Czech Airlines';
    public $reBody2 = [
        "en"=> "Booking Details",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml()
    {
        $xpath = "//text()[" . $this->eq("Flight:") . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->nextText("Booking Reference:", $root)) {
                $this->logger->info("RL not matched");

                return;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$roots) {
            $itineraries = [];
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq("Passenger:") . "]/following::text()[normalize-space(.)][1]"));

            // TicketNumbers
            if (isset($this->pdf)) {
                if (preg_match_all("#Ticket:\s+(.+)#", $this->pdf, $m)) {
                    $it['TicketNumbers'] = array_unique($m[1]);
                }
            }

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

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $this->nextText("Flight:", $root));

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./following-sibling::tr[1]//text()[" . $this->eq("From:") . "]/following::text()[normalize-space(.)][1]", $root);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[1]//text()[" . $this->eq("From:") . "]/following::text()[normalize-space(.)][2]", $root, true, "#Terminal (.+)#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]//text()[" . $this->eq("From:") . "]/ancestor::p[1]/descendant::text()[normalize-space(.)][last()]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[3]//text()[" . $this->eq("To:") . "]/following::text()[normalize-space(.)][1]", $root);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[3]//text()[" . $this->eq("To:") . "]/following::text()[normalize-space(.)][2]", $root, true, "#Terminal (.+)#");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[3]//text()[" . $this->eq("To:") . "]/ancestor::p[1]/descendant::text()[normalize-space(.)][last()]", $root)));

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $this->nextText("Flight:", $root));

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->nextText("Flight:", $root, 3);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                if (isset($this->pdf)) {
                    if (preg_match_all("#" . $itsegment['AirlineName'] . $itsegment['FlightNumber'] . "\s{2,}(\d+\w)\s{2,}\d+:\d+#", $this->pdf, $m)) {
                        $itsegment['Seats'] = $m[1];
                    }
                }

                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }
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
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        if (isset($pdfs[0])) {
            $this->pdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
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

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+ [^\s\d]+ \d{4}) - (\d+:\d+)$#", //07 Feb 2018 - 05:20
        ];
        $out = [
            "$1, $2",
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
