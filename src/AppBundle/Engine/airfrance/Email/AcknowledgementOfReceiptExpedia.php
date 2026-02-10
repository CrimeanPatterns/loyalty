<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;

class AcknowledgementOfReceiptExpedia extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-6324581.eml";

    public $reFrom = "@airfrance-klm.com";
    public $reSubject = [
        "en"=> "Flying Blue: Acknowledgement of receipt of your request",
    ];
    public $reBody = 'expedia.com';
    public $reBody2 = [
        "en"=> "Direct",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        // echo $text;

        if (!preg_match_all("#\n(?<airline>[\w\s]+)\n(?<rl>[A-Z\d]{6})\n#", $this->re("#COMPLETED(.*?)We hope you had a great trip#ms", $text), $m, PREG_SET_ORDER)) {
            $this->http->Log("[info] RL not mutched", LOG_LEVEL_NORMAL);

            return;
        }
        $rls = [];

        foreach ($m as $item) {
            $rls[$item['airline']] = $item['rl'];
        }

        $segments = $this->split("#(\d+\s+[^\d\s]+\s+\d{4}\n-\s+\w+\s+Direct)#", $text);
        $airs = [];

        foreach ($segments as $stext) {
            if (!preg_match("#\n(" . $this->opt(array_keys($rls)) . ")\s+\d+#", $stext, $m) || !isset($rls[$m[1]])) {
                $this->http->Log("[info] Airline not found", LOG_LEVEL_NORMAL);

                return;
            }
            $airs[$rls[$m[1]]][] = $stext;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            preg_match_all("#\n(M[ris]+\..+)\nNo frequent flyer details#", $this->re("#Traveller Information(.*?)Seat assignments#ms", $text), $Passengers);
            $it['Passengers'] = $Passengers[1];

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

            foreach ($segments as $k=>$stext) {
                // echo $stext;
                // die();
                $date = strtotime($this->normalizeDate($this->re("#(\d+\s+[^\d\s]+\s+\d{4})\n#", $stext)));

                $itsegment = [];

                if (!preg_match("#\n(?<DepCode>[A-Z]{3})\s+(?<DepTime>\d+:\d+\s+[ap]m)\n(?<ArrCode>[A-Z]{3})\s+(?<ArrTime>\d+:\d+\s+[ap]m)\n#", $stext, $m)) {
                    $this->http->Log("[info] Airports,Times not matched rl: " . $rl . " key: " . $k, LOG_LEVEL_NORMAL);

                    return;
                }
                $itsegment['DepCode'] = $m['DepCode'];
                $itsegment['ArrCode'] = $m['ArrCode'];
                $itsegment['DepDate'] = strtotime($m['DepTime'], $date);
                $itsegment['ArrDate'] = strtotime($m['ArrTime'], $date);

                if (!preg_match("#\n(?<AirlineName>" . $this->opt(array_keys($rls)) . ")\s+(?<FlightNumber>\d+)#", $stext, $m)) {
                    $this->http->Log("[info] FlightNumber, AirlineName not matched rl: " . $rl . " key: " . $k, LOG_LEVEL_NORMAL);

                    return;
                }
                $itsegment['FlightNumber'] = $m['FlightNumber'];
                $itsegment['AirlineName'] = $m['AirlineName'];

                // Operator
                $itsegment['Operator'] = $this->re("#Operated by\s+(.*?)\n#", $stext);

                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#\n(.*?)\s+\(\w\)\s+\|\s+Seat\n#", $stext);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#\n.*?\s+\((\w)\)\s+\|\s+Seat\n#", $stext);

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->re("#Confirm or change seats\n(.*?)\n#", $stext);

                // Duration
                // Meal
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

        $result = [
            'emailType'  => "AcknowledgementOfReceiptExpedia" . ucfirst($this->lang),
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})$#",
        ];
        $out = [
            "$1",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
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
