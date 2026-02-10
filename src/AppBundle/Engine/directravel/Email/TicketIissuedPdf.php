<?php

namespace AwardWallet\Engine\directravel\Email;

class TicketIissuedPdf extends \TAccountChecker
{
    public $mailFiles = "directravel/it-6224016.eml";

    public $reFrom = "@dt.com";
    public $reSubject = [
        //unknown
    ];
    public $reBody = 'dt.com';
    public $reBody2 = [
        "en"=> "Departure",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        // echo $text;

        $segments = $this->split("#(\nConfirmation: \w+\n)#", $text);

        if (count($segments) != substr_count($text, 'Departure:')) {
            $this->http->Log("Incorrect count of segments");

            return;
        }
        $airs = [];

        foreach ($segments as $stext) {
            if (!$rl = $this->re("#Confirmation: (\w+)#", $stext)) {
                $this->http->Log("RecordLocator Not Found");

                return;
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl=>$stext) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = explode("\n", $this->re("#Passenger Names\n(.*?)\n\n#ms", $text));

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

            foreach ($segments as $stext) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flight Number\s+(\d+)#", $stext);

                // DepCode, ArrCode
                preg_match_all("#\(([A-Z]{3})\)#", $stext, $m);

                foreach (['DepCode', 'ArrCode'] as $n=> $k) {
                    if (!isset($m[1][$n])) {
                        $this->http->Log("'{$k}' not matched");

                        return;
                    }
                    $itsegment[$k] = $m[1][$n];
                }

                // DepName
                $itsegment['DepName'] = $this->re("#Departure City:\s+(.+),#", $stext);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#Departing Terminal:\s+(.+)#", $stext);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Departure:\s+(.+)#", $stext)));

                // ArrName
                $itsegment['ArrName'] = $this->re("#Arrival City:\s+(.+),#", $stext);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#Arrival Terminal:\s+(.+)#", $stext);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#Arrival:\s+(.+)#", $stext)));

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Confirmation: \w+\n(.*?)\n#", $stext);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Class of Service:\s+\w\s+-\s+(\w+)#", $stext);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#Class of Service:\s+(\w)\s+-\s+\w+#", $stext);

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->re("#Seat Assignments:.*?\s+-\s+(\d+\w)#", $stext);

                // Duration
                $itsegment['Duration'] = $this->re("#Travel Time:\s+(.+)#", $stext);

                // Meal
                $itsegment['Meal'] = $this->re("#Meal:\s+(.+)#", $stext);

                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('\d+\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
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

        if (!$this->sortedPdf($parser)) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->pdf->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (strpos($parser->getHtmlBody(), "Flying Blue") !== false) {//FlyingBlue
            $result['providerCode'] = 'airfrance';
        }

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

    public static function getEmailProviders()
    {
        return ['directravel', 'airfrance'];
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)$#",
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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

    private function sortedPdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName('\d+\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $res = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", $this->pdf->FindNodes("./descendant::text()[normalize-space(.)]", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            foreach ($grid as $row=>&$c) {
                ksort($c);
                $r = "";

                foreach ($c as $t) {
                    $r .= $t . "\n";
                }
                $c = $r;
            }

            ksort($grid);

            foreach ($grid as $r) {
                $res .= $r;
            }
        }
        $this->pdf->setBody($res);

        return true;
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
