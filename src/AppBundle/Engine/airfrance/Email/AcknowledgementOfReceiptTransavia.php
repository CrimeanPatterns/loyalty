<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;

class AcknowledgementOfReceiptTransavia extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-6334083.eml";

    public $reFrom = "@airfrance-klm.com";
    public $reSubject = [
        "en"=> "Flying Blue: Acknowledgement of receipt of your request",
    ];
    public $reBody = 'www.transavia.com';
    public $reBody2 = [
        "fr"=> "données de réservation",
    ];

    public static $dictionary = [
        "fr" => [],
    ];

    public $lang = "fr";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        // echo $text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#numéro de confirmation de réservation :\s+(\w+)\n#", $text);

        // TripNumber
        // Passengers
        if (preg_match_all("#\n(M[RIS]+\..+)\s+\(\d+/\d+/\d{4}\)#",
            $this->re("#passagers\n(.*?)\nréservation de siège#ms", $text), $Passengers)) {
            $it['Passengers'] = $Passengers[1];
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

        $segments = $this->split("#([^\d\s]+\s+\d+\s+[^\d\s]+,\s+\d{4}\n\nnuméro de vol:)#", $text);

        foreach ($segments as $k=> $stext) {
            $date = strtotime($this->normalizeDate($this->re("#([^\d\s]+\s+\d+\s+[^\d\s]+,\s+\d{4})\n#", $stext)));

            $itsegment = [];

            if (!preg_match("#\nde:\n(?<DepName>.*?)\n\(\s*(?<DepCode>[A-Z]{3})\s*\)#", $stext, $m)) {
                $this->http->Log("[info] DepName,DepCode not matched key: " . $k, LOG_LEVEL_NORMAL);

                return;
            }
            $itsegment['DepCode'] = $m['DepCode'];
            $itsegment['DepName'] = $m['DepName'];

            if (!preg_match("#\nà:\n(?<ArrName>.*?)\n\(\s*(?<ArrCode>[A-Z]{3})\s*\)#", $stext, $m)) {
                $this->http->Log("[info] ArrName,ArrCode not matched key: " . $k, LOG_LEVEL_NORMAL);

                return;
            }
            $itsegment['ArrCode'] = $m['ArrCode'];
            $itsegment['ArrName'] = $m['ArrName'];

            if (!preg_match("#\nnuméro de vol:\s+(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)#", $stext, $m)) {
                $this->http->Log("[info] FlightNumber, AirlineName not matched key: " . $k, LOG_LEVEL_NORMAL);

                return;
            }
            $itsegment['FlightNumber'] = $m['FlightNumber'];
            $itsegment['AirlineName'] = $m['AirlineName'];

            $itsegment['DepDate'] = strtotime($this->re("#(\d+:\d+)\s+h\n(?:\(heure locale\)\n)?à:#", $stext), $date);
            $itsegment['ArrDate'] = strtotime($this->re("#arrivée à\s+(\d+:\d+)\s+h#", $stext), $date);

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
            'emailType'  => "AcknowledgementOfReceiptTransavia" . ucfirst($this->lang),
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
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+),\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
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
