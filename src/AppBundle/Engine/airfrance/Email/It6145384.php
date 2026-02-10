<?php

namespace AwardWallet\Engine\airfrance\Email;

class It6145384 extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@airfrance.fr";
    public $reSubject = [
        "en"=> "Your Air France boarding documents on",
    ];
    public $reBody = 'AIR FRANCE';
    public $reBody2 = [
        "en"=> "BOARDING PASS",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        // echo $text."\n";
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#BOOKING REFERENCE\n(.+)#", $text);

        // TicketNumbers
        $it['TicketNumbers'] = array_filter([$this->re("#TICKET NUMBER\n(.+)#", $text)]);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->re("#NAME\n(.+)#", $text)]);

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
        $count = substr_count($text, "DEPARTURE\n");

        preg_match_all("#(?<DepName>.+)\n" .
                        "(?<ArrName>.+)\n" .
                        "OPERATED BY\s+(?<Operator>.+)\n" .
                        "(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
                        "(?<DepCode>[A-Z]{3})\n" .
                        "(?<ArrCode>[A-Z]{3})\n" .
                        "DATE\n" .
                        "BOARDING\n" .
                        "DEPARTURE\n" .
                        "ARRIVAL\n" .
                        "(?:TERMINAL / GATE|GATE)\n" .
                        "SEAT\n" .
                        "CLASS\n" .
                        "\d+:\d+\n" .
                        "(?:(?<DepartureTerminal>\S+)\s*/\s*\S+|-)\n" .
                        "(?<Seats>\d+\w)\n" .
                        "(?<Date>\d+\s+[^\d\s]+\s+\d{2})\n" .
                        "(?<DepTime>\d+:\d+)\n" .
                        "(?<ArrTime>\d+:\d+)\n" .
                        "(?<Cabin>.+)\n#", $text, $segments, PREG_SET_ORDER);

        if (count($segments) == 0) {
            preg_match_all("#(?<DepName>.+)\n" .
                        "(?<ArrName>.+)\n" .
                        "OPERATED BY\s+(?<Operator>.+)\n" .
                        "(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
                        "(?<DepCode>[A-Z]{3})\n" .
                        "(?<ArrCode>[A-Z]{3})\n" .
                        "DATE\n" .
                        "BOARDING\n" .
                        "DEPARTURE\n" .
                        "ARRIVAL\n" .
                        "(?:TERMINAL / GATE|GATE)\n" .
                        "SEAT\n" .
                        "CLASS\n" .
                        "(?<Date>\d+\s+[^\d\s]+\s+\d{2})\n" .
                        "\d+:\d+\n" .
                        "(?<DepTime>\d+:\d+)\n" .
                        "(?<ArrTime>\d+:\d+)\n" .
                        "(?:(?<DepartureTerminal>\S+)\s*/\s*\S+|-)\n" .
                        "(?<Seats>\d+\w)\n" .
                        "(?<Cabin>.+)\n#", $text, $segments, PREG_SET_ORDER);
        }

        if ($count == count($segments)) {
            foreach ($segments as $segment) {
                $date = strtotime($this->normalizeDate($segment["Date"]));

                $keys = [
                    "AirlineName",
                    "FlightNumber",
                    "DepName",
                    "DepCode",
                    "DepartureTerminal",
                    "ArrName",
                    "ArrCode",
                    "Cabin",
                    "Operator",
                    "Seats",
                ];
                $itsegment = [];

                foreach ($keys as $key) {
                    if (isset($segment[$key])) {
                        $itsegment[$key] = $segment[$key];
                    }
                }

                $itsegment['DepDate'] = strtotime($segment['DepTime'], $date);
                $itsegment['ArrDate'] = strtotime($segment['ArrTime'], $date);

                $it['TripSegments'][] = $itsegment;
            }
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
        $pdfs = $parser->searchAttachmentByName('Boarding-documents.pdf');

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
            "#^(\d+)\s+([^\d\s]+)\s+(\d{2})$#",
        ];
        $out = [
            "$1 $2 20$3",
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

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
}
