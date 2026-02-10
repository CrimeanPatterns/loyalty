<?php

namespace AwardWallet\Engine\airasia\Email;

class ETicketPDF extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "airasia.com";
    public $reBody = [
        'en' => ['Booking number', 'Flight'],
    ];
    public $reSubject = [
        'NOTDETERMINESUBJECT',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $NBSP = chr(194) . chr(160);
        $pdfs = $parser->searchAttachmentByName("AK\s*TICKET.*pdf");
        $its = [];

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
                    $body = $this->pdf->Response['body'];
                    $this->AssignLang($body);
                    $it = $this->parseEmail();
                    $its[] = $it;
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $its = array_filter($its);

        if (count($its) > 0) {
            $its = $this->mergeItineraries($its);
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ETicketPDF",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('AK\s*TICKET.*pdf');

        if (isset($pdf[0])) {
            $text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf[0])));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];                //contains(.,'Booking') and contains(.,'number')
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[contains(.,'Booking number')]/ancestor::p[1]/following-sibling::p[1]", null, true, "#[A-Z\d]{5,}#");
        $it['Passengers'] = array_unique($this->pdf->FindNodes("//text()[contains(.,'Flight')]/ancestor::p[1]/following-sibling::p[contains(.,'Checked')][1]/preceding-sibling::p[2]"));
        $xpath = "//text()[contains(.,'Flight')]/ancestor::p[1 and not(preceding::p[contains(.,'local')])]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->pdf->FindSingleNode("./following-sibling::p[1]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,':')][1]", $root);

            if (preg_match("#.+?\(([A-Z]{3})\)\s*(.+?)\s*(?:\((.+?)\))?\s*(\w+\s+\d+\s+\w+\s+\d{4})\s*(\d{2})(\d{2})\s*hrs\s*\((\d+:\d+[ap]m)\)#iu", $node, $m)) {
                $seg['DepName'] = $m[2];
                $seg['DepCode'] = $m[1];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[5] . ':' . $m[6]));

                if (!$seg['DepDate']) {
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[7]));
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $seg['DepartureTerminal'] = $m[3];
                }
            }
            $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,':')][2]", $root);

            if (preg_match("#.+?\(([A-Z]{3})\)\s*(.+?)\s*(?:\((.+?)\))?\s*(\w+\s+\d+\s+\w+\s+\d{4})\s*(\d{2})(\d{2})\s*hrs\s*\((\d+:\d+[ap]m)\)#iu", $node, $m)) {
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = $m[1];
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[5] . ':' . $m[6]));

                if (!$seg['ArrDate']) {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[4] . ' ' . $m[7]));
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $seg['ArrivalTerminal'] = $m[3];
                }
            }
            $it['TripSegments'][] = $seg;
        }

        if (count($it['TripSegments']) === 0) {
            $it = [];
        }

        return $it;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\w+\s+(\d+\s+\w+\s+\d{4})\s+(\d+:\d+)$#u',
            '#^\w+\s+(\d+\s+\w+\s+\d{4})\s+(\d+:\d+[ap]m)$#iu',
        ];
        $out = [
            '$1 $2',
            '$1 $2',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
