<?php

namespace AwardWallet\Engine\airindia\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "airindia/it-6013667.eml";

    public $reFrom = "airindia.in";
    public $reBody = [
        'en' => ['Booking reference no (PNR):', 'AIR INDIA'],
    ];
    public $reSubject = [
        'Air India Confirmation for Booking',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $this->pdf->SetBody($html);
        } else {
            return null;
        }

        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its[] = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Confirmation",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[normalize-space(.) = '" . $this->t('Booking reference no (PNR):') . "']/ancestor::p[1]", null, true, "#:\s+([A-Z\d]+)#");
        $it['ReservationDate'] = strtotime($this->pdf->FindSingleNode("//text()[contains(.,'" . $this->t('Issued date:') . "')]/ancestor::p[1]", null, true, "#:\s+(.+)#"));
        $it['Passengers'] = $this->pdf->FindNodes("//text()[normalize-space(.) = 'ADT']/ancestor::p[1]/following-sibling::p[string-length(normalize-space(.))>0][1]");
        $it['AccountNumbers'] = $this->pdf->FindNodes("//text()[normalize-space(.) = 'ADT']/ancestor::p[1]/following-sibling::p[string-length(normalize-space(.))>0][2]", null, "#([A-Z\d]+)#");
        $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[contains(.,'TOTAL TRIP COST')]/ancestor::p[1]/following::p[1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['TicketNumbers'] = $this->pdf->FindNodes("//text()[normalize-space(.) = 'ADT']/ancestor::p[1]/following-sibling::p[string-length(normalize-space(.))>0][5]", null, "#([A-Z\d]+)#");
        $xpath = "//text()[contains(.,'DATE')]/following::text()[normalize-space(.)][1][contains(.,'FLIGHT')]/ancestor::p[1]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $date = strtotime($this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>0][8]", $root));

            $node = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>0][9]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>0][10]", $root);

            if (preg_match("#\(([A-Z]{3})\)\s*(Terminal.+)?#", $node, $m)) {
                $seg['DepCode'] = $m[1];

                if (isset($m[2])) {
                    $seg['DepartureTerminal'] = $m[2];
                }
            }

            if ($time = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>0][11]", $root, true, "#^(\d+:\d+)$#")) {
                $seg['DepDate'] = strtotime($time, $date);
            }

            $node = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>0][12]", $root);

            if (preg_match("#\(([A-Z]{3})\)\s*(Terminal.+)?#", $node, $m)) {
                $seg['ArrCode'] = $m[1];

                if (isset($m[2])) {
                    $seg['ArrivalTerminal'] = $m[2];
                }
            }

            if ($time = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>0][13]", $root, true, "#^(\d+:\d+)$#")) {
                $seg['ArrDate'] = strtotime($time, $date);
            }

            $seg['Cabin'] = $this->pdf->FindSingleNode("./following-sibling::p[string-length(normalize-space(.))>0][14]", $root);

            $it['TripSegments'][] = $seg;
        }

        return $it;
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
