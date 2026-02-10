<?php

namespace AwardWallet\Engine\airfrance\Email;

class AcknowledgementOfReceiptTuiReisecenter extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-6244370.eml";

    public $reFrom = "@airfrance-klm.com";
    public $reSubject = [
        "en"=> "Flying Blue: Acknowledgement of receipt of your request",
    ];
    public $reBody = 'tui-reisecenter';
    public $reBody2 = [
        "de"=> "Reservierungsnummer",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        // echo $text;
        $this->date = strtotime($this->normalizeDate($this->re("#Datum:\n(.*?)\n#", $text)));

        $rows = explode("\n", $this->re("#Airline-Buchungsnr.:\n(.*?)\nFlug#ms", $text));
        $rls = [];

        foreach ($rows as $row) {
            if (preg_match("#^(?<airline>\w{2})/(?<rl>\w+)$#", $row, $m)) {
                $rls[$m['airline']] = $m['rl'];
            }
        }

        preg_match_all("#Flug\n" .
                        "Datum\n" .
                        "Von\n" .
                        "Nach\n" .
                        "Abflug\n" .
                        "Ankunft\n\n" .
                        "(?<Date>.*?)\n" .
                        "(?<DepName>.*?\n" .
                        ".*?)\n" .
                        "(?:(?<DepartureTerminal>TERMINAL \w+)\n)?" .
                        "(?<ArrName>.*?\n" .
                        ".*?)\n" .
                        "(?:(?<ArrivalTerminal>TERMINAL \w+)\n)?" .
                        "(?<DepTime>\d+:\d+)\s+Uhr\n" .
                        "(?<ArrTime>\d+:\d+)\s+Uhr\n" .
                        "Flugdauer:\n" .
                        "(?<Duration>\d+:\d+)\s+Std\.\n" .
                        "(-\n" .
                        "(?<Date2>.*?)\n)?" .
                        "(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
                        "durchgeführt von\n" .
                        "(?<Operator>(?:.*?\n){1,3})" .
                        "Buchung\n" .
                        "Klasse:\s+(?<BookingClass>\w+)\s+-\s+(?<Cabin>\w+),\s+\w+\n#", $text, $segments, PREG_SET_ORDER);

        preg_match_all("#Flug\n" .
                        "Datum\n" .
                        "Von\n" .
                        "Nach\n" .
                        "Abflug\n" .
                        "Ankunft\n\n" .
                        "(?<Date>.*?)\n" .
                        "(?<DepName>.*?\n" .
                        ".*?)\n" .
                        "(?:(?<DepartureTerminal>TERMINAL \w+)\n)?" .
                        "(?<ArrName>.*?\n" .
                        ".*?)\n" .
                        "(?:(?<ArrivalTerminal>TERMINAL \w+)\n)?" .
                        "(?<DepTime>\d+:\d+)\s+Uhr\n" .
                        "(?<ArrTime>\d+:\d+)\s+Uhr\n" .
                        "Flugdauer:\n" .
                        "(?<Duration>\d+:\d+)\s+Std\.\n" .
                        "Buchung\n" .
                        "Klasse:\s+(?<BookingClass>\w+)\s+-\s+(?<Cabin>\w+),\s+\w+\n" .
                        "(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
                        "durchgeführt von\n" .
                        "(?<Operator>.*?)\n" .
                        "#", $text, $s, PREG_SET_ORDER);

        $segments = array_merge($segments, $s);

        if (count($segments) != substr_count($text, 'Flugdauer:')) {
            $this->http->Log("Incorrect count of segments");

            return;
        }

        $airs = [];

        foreach ($segments as $segment) {
            if (!isset($rls[$segment['AirlineName']])) {
                $this->http->Log("RecordLocator Not Found");

                return;
            }
            $airs[$rls[$segment['AirlineName']]][] = $segment;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter([$this->re("#Reisedaten für:\n(.*?)\n#", $text)]);

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
            $it['ReservationDate'] = $this->date;
            // NoItineraries
            // TripCategory

            foreach ($segments as $segment) {
                $date = strtotime($this->normalizeDate($segment["Date"]));
                $date2 = isset($segment["Date2"]) ? strtotime($this->normalizeDate($segment["Date"])) : false;

                $keys = [
                    "AirlineName",
                    "FlightNumber",
                    "DepName",
                    "ArrName",
                    "DepartureTerminal",
                    "ArrivalTerminal",
                    "Operator",
                    "BookingClass",
                    "Cabin",
                    "Duration",
                ];
                $itsegment = [];

                foreach ($keys as $key) {
                    if (isset($segment[$key])) {
                        $itsegment[$key] = str_replace("\n", " ", $segment[$key]);
                    }
                }

                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                $itsegment['DepDate'] = strtotime($segment['DepTime'], $date);
                $itsegment['ArrDate'] = strtotime($segment['ArrTime'], $date2 != false ? $date2 : $date);

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
            'emailType'  => "AcknowledgementOfReceiptTuiReisecenter" . ucfirst($this->lang),
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
            "#^(\d+\.\d+\.\d{4})$#", //24.07.2015
            "#^[^\d\s]+,\s+(\d+)\.\s+([^\d\s]+)$#", //Fr, 24. Jul
        ];
        $out = [
            "$1",
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
}
