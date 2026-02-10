<?php

namespace AwardWallet\Engine\fluege\Email;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "fluege/it-1.eml, fluege/it-2128738.eml, fluege/it-2423504.eml, fluege/it-2423509.eml, fluege/it-2427272.eml, fluege/it-2430537.eml, fluege/it-2490492.eml, fluege/it-2522129.eml, fluege/it-5661927.eml, fluege/it-5669497.eml, fluege/it-5677915.eml, fluege/it-5686849.eml, fluege/it-5686850.eml, fluege/it-5686851.eml, fluege/it-6160292.eml, fluege/it-6160376.eml, fluege/it-6160492.eml";

    public $reFrom = "@fluege-service.de";
    public $reSubject = [
        "de"=> "Ihr Reiseplan / E-Ticket für",
    ];
    public $reBody = ['Fluege.de', 'flug24.de'];
    public $reBody2 = [
        "de"=> "Flugdaten",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        if (!$it['RecordLocator'] = $this->re("#Buchungsreferenz / Airlinecode\n[A-Z\d]+\n([A-Z\d]+)\n#", $text)) {
            $it['RecordLocator'] = $this->re("#Buchungsreferenz / Airlinecode\n(\w+)#", $text);
        }

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter(explode("\n", $this->re("#Reisende\n(.*?)\n(?:www.|[^\n]*@)#ms", $text)));

        if ($passenger = $this->re("#Reisender\n(.+)#", $text)) {
            $it['Passengers'][] = $passenger;
        }

        // AccountNumbers
        // TicketNumbers
        $it['TicketNumbers'] = array_filter(array_map('trim', explode(",", $this->re("#Ticketnummer\(n\):\s*(.+)\n#", $text))));

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

        preg_match_all("#Von:\s+(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\n" .
                        "(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)(?:\n|\s+)" .
                        "(?<BookingClass>\w)\n" .
                        "(?<Date>\d+[^\d\s]+)\n" .
                        "(?<DepTime>\d+:\d+)\n" .
                        "(?<ArrTime>\d+:\d+)(?:\s+\+\d)?\n" .
                        "(ADT:.*|NIL)\n" .
                        "Nach:\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)\n#", $text, $segments, PREG_SET_ORDER);

        if (count($segments) == 0) {
            preg_match_all("#Von:\s+(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\n" .
                            "Nach:\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)\n" .
                            "(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
                            "(?<BookingClass>\w)\n" .
                            "(?<Date>\d+[^\d\s]+)\n" .
                            "(?<DepTime>\d+:\d+)\n" .
                            "(?<ArrTime>\d+:\d+)(?:\s*\+\d)?(?:\n|\s+)" .
                            "(?:\w{3}\n" .
                            "Operated by\s+(?<Operator>.*?)\n)?#", $text, $segments, PREG_SET_ORDER);
        }

        foreach ($segments as $segment) {
            $date = strtotime($this->normalizeDate($segment["Date"]));

            $keys = [
                "AirlineName",
                "FlightNumber",
                "DepName",
                "DepCode",
                "ArrName",
                "ArrCode",
                "BookingClass",
                "Operator",
            ];
            $itsegment = [];

            foreach ($keys as $key) {
                if (isset($segment[$key])) {
                    $itsegment[$key] = $segment[$key];
                }
            }

            $itsegment['DepDate'] = strtotime($segment['DepTime'], $date);
            $itsegment['ArrDate'] = strtotime($segment['ArrTime'], $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        $checked = false;

        foreach ($this->reBody as $re) {
            if (strpos($text, $re) === false) {
                $checked = true;
            }
        }

        if (!$checked) {
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
