<?php

namespace AwardWallet\Engine\fluege\Email;

class ItineraryPdfCombinedCols extends \TAccountChecker
{
    public $mailFiles = "fluege/it-2158476.eml, fluege/it-2158482.eml";

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
        $it['RecordLocator'] = $this->re("#Buchungsreferenz / Airlinecode\n(\w+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter(explode("\n", $this->re("#Reisende\n(.*?)\n(?:www.fluege.de/service/faq|www.flug24.de/service/faq)#ms", $text)));

        if ($passenger = $this->re("#Reisender\n(.+)#", $text)) {
            $it['Passengers'][] = $passenger;
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
        preg_match("#von\nnach\nFlug\nKlasse\nDatum\nAbflug\nAnkunft\nGepäck\n" .
        "(?<Directions>(?:.*?\s+\([A-Z]{3}\)\n)+)" .
        "(?<Flights>(?:\w{2}\s*\d+\n)+)" .
        "(?<Classes>(?:\w\n)+)" .
        "(?<Dates>(?:\d+[^\d\s]+\n)+)" .
        "(?<Times>(?:\d+:\d+(?:\s*\+\d)?\n)+)" .
        "#", $text, $data);

        if (!isset($data['Flights'])) {
            return;
        }
        $data['Flights'] = array_filter(explode("\n", $data['Flights']));
        $data['Classes'] = array_filter(explode("\n", $data['Classes']));
        $data['Dates'] = array_filter(explode("\n", $data['Dates']));

        $data['DepDirs'] = array_slice(array_filter(explode("\n", $data['Directions'])), 0, count($data['Flights']));
        $data['ArrDirs'] = array_slice(array_filter(explode("\n", $data['Directions'])), count($data['Flights']));
        $data['DepTimes'] = array_slice(array_filter(explode("\n", $data['Times'])), 0, count($data['Flights']));
        $data['ArrTimes'] = array_slice(array_filter(explode("\n", $data['Times'])), count($data['Flights']));

        $keys = ['Classes', 'Dates', 'DepDirs', 'ArrDirs', 'DepTimes', 'ArrTimes'];

        foreach ($keys as $key) {
            if (count($data[$key]) != count($data['Flights'])) {
                return;
            }
        }

        $segments = [];

        foreach ($data['Flights'] as $k=>$fl) {
            $date = strtotime($this->normalizeDate($data['Dates'][$k]));
            $segments[$k]['FlightNumber'] = $this->re("#^\w{2}\s*(\d+)$#", $fl);
            $segments[$k]['AirlineName'] = $this->re("#^(\w{2})\s*\d+$#", $fl);
            $segments[$k]['DepCode'] = $this->re("#\(([A-Z]{3})\)$#", $data['DepDirs'][$k]);
            $segments[$k]['DepName'] = $this->re("#^(.*?)\s+\([A-Z]{3}\)$#", $data['DepDirs'][$k]);
            $segments[$k]['ArrCode'] = $this->re("#\(([A-Z]{3})\)$#", $data['ArrDirs'][$k]);
            $segments[$k]['ArrName'] = $this->re("#^(.*?)\s+\([A-Z]{3}\)$#", $data['ArrDirs'][$k]);
            $segments[$k]['DepDate'] = strtotime($data['DepTimes'][$k], $date);
            $segments[$k]['ArrDate'] = strtotime($data['ArrTimes'][$k], $date);
            $segments[$k]['BookingClass'] = $data['Classes'][$k];
        }
        $it['TripSegments'] = $segments;
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
