<?php

namespace AwardWallet\Engine\airfrance\Email;

class AcknowledgementOfReceiptVliegtarieven extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-6243276.eml";

    public $reFrom = "@airfrance-klm.com";
    public $reSubject = [
        "en"=> "Flying Blue: Acknowledgement of receipt of your request",
    ];
    public $reBody = 'Vliegtarieven';
    public $reBody2 = [
        "nl"=> "Aankomst",
    ];

    public static $dictionary = [
        "nl" => [],
    ];

    public $lang = "de";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];

        $segments = $this->split("#(\n.*?\s+\([A-Z]{3}\)\s+-\s+.*?\([A-Z]{3}\)\n\w{2}\d+)#", $text);

        if (count($segments) != substr_count($text, 'Arrival/Aankomst')) {
            $this->http->Log("Incorrect count of segments");

            return;
        }
        $airs = [];

        foreach ($segments as $stext) {
            $itsegment = [];

            if (!preg_match("#" .
            "\n(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\s+-\s+(?<ArrName>.*?)\((?<ArrCode>[A-Z]{3})\)\n" .
            "(?<AirlineName>\w{2})(?<FlightNumber>\d+)" .
            "#", $stext, $m)) {
                $this->http->Log("'FlightNumber','AirlineName','DepName','DepCode','ArrName','ArrCode' Not matched");

                return;
            }

            $keys = [
                'FlightNumber', 'AirlineName', 'DepName', 'DepCode', 'ArrName', 'ArrCode',
            ];

            foreach ($keys as $k) {
                if (!isset($m[$k])) {
                    return;
                }
                $itsegment[$k] = $m[$k];
            }

            preg_match_all("#[^\d\s]+\s+\d+\s+\d+\s+\d{4}\n\d+:\d+\s+uur#", $stext, $m);

            foreach (['DepDate', 'ArrDate'] as $n=> $k) {
                if (!isset($m[0][$n])) {
                    $this->http->Log("'{$k}' Not matched");

                    return;
                }
                $itsegment[$k] = strtotime($this->normalizeDate($m[0][$n]));
            }

            if (!preg_match("#" .
            "\nStopover/Tussenstop\n" .
            "(?<Duration>.*?)\n" .
            "(?<Cabin>\w+)\n" .
            "(?<Aircraft>[\dA-Z]+)\n" .
            "#", $stext, $m)) {
                $this->http->Log("'Duration','Cabin','Aircraft' Not matched");

                return;
            }

            $keys = [
                'Duration', 'Cabin', 'AirCraft',
            ];

            foreach ($keys as $k) {
                if (isset($m[$k])) {
                    $itsegment[$k] = $m[$k];
                }
            }

            if (!$rl = $this->re("#Bookings code airline / Boekingscode airline:\s+(\w+)#ms", $stext)) {
                $this->http->Log("RecordLocator Not matched");

                return;
            }
            $airs[$rl]['segments'][] = $itsegment;
            $airs[$rl]['text'][] = $stext;
        }

        foreach ($airs as $rl=>$cont) {
            $text = implode("\n", $cont['text']);
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = [];
            // TicketNumbers
            $it['TicketNumbers'] = [];

            preg_match_all("#\nPassengers/reizigers(.*?)\nTotal journey time#ms", $text, $m);

            foreach ($m[0] as $block) {
                preg_match_all("#\n(?<name>.*?)\n(?<ticket>\d+)\n#", $block, $r, PREG_SET_ORDER);

                foreach ($r as $i) {
                    $it['Passengers'][] = $i['name'];
                    $it['TicketNumbers'][] = $i['ticket'];
                }
            }
            $it['Passengers'] = array_unique($it['Passengers']);
            $it['TicketNumbers'] = array_unique($it['TicketNumbers']);

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

            $it['TripSegments'] = $cont['segments'];
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
            'emailType'  => "AcknowledgementOfReceiptVliegtarieven" . ucfirst($this->lang),
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
            "#^[^\d\s]+\s+(\d+)\s+(\d+)\s+(\d{4})\n(\d+:\d+)\s+uur$#",
        ];
        $out = [
            "$1.$2.$3, $4",
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
