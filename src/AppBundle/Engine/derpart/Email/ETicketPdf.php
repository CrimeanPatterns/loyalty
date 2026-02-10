<?php

namespace AwardWallet\Engine\derpart\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "derpart/it-2453886.eml, derpart/it-2810746.eml, derpart/it-8562425.eml, derpart/it-8896529.eml";

    public $reFrom = "derpart.com";
    public $reBody = [
        'de' => ['Electronic Ticket - Passenger Itinerary', ['DERPART.COM', '@martinek-reisen.de']],
    ];
    public $reSubject = [
        'Electronic Ticket Receipt für',
    ];
    public $lang = '';
    public $date;
    public $pdf;
    public $pdfNamePattern = "\d{8}_[A-Z]+_[A-Z\d]{5,}.pdf";
    public static $dict = [
        'de' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $cnt = count($pdfs);

        for ($i = 0; $i < $cnt; $i++) {
            $this->date = strtotime($parser->getDate());
            $this->tablePdf($parser, $i);
            $body = $this->pdf->Response['body'];
            $this->AssignLang($body);
            $fl = $this->parseEmail();

            foreach ($fl as $it) {
                $its[] = $it;
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicketPdf' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, 'en')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("(//td[starts-with(.,'Booking reference')]/following-sibling::td[1])[1]", null, true, "#^\s*([A-Z\d]+)#");
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//td[starts-with(.,'Date of issue')]/following-sibling::td[1])[1]")));

        if ($it['ReservationDate'] !== false) {
            $this->date = $it['ReservationDate'];
        }
        $it['TicketNumbers'] = array_values(array_unique($this->pdf->FindNodes("//td[contains(.,'Ticket number')]/following-sibling::td[1]", null, "#([\d\-]+)#")));
        $it['Passengers'] = array_values(array_unique($this->pdf->FindNodes("//td[contains(.,'Passenger')]/following-sibling::td[1]")));

        $xpath = "//td[contains(.,'Flight')]/ancestor::tr[contains(.,'Date')]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['Operator'] = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][2]/td[contains(.,'operated by')]", $root, true, "#:\s*(.+?)\s*(?:,|ARRIVAL TIME|$)#i");

            if (empty($node = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][2]/td[starts-with(normalize-space(.),'Seat')]", $root, true, "#:\s*(\d+[A-Z])\s*$#i"))) {
                $node = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][2]/td[starts-with(normalize-space(.),'Seat')]/following-sibling::td[1]", $root);
            }
            $seg['Seats'] = $node;
            $cnt = $this->pdf->XPath->query("./following-sibling::tr[normalize-space(.)!=''][1]/td", $root)->length;

            if ($cnt == 7) {
                $date = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[1]", $root, true, "#(\d+\s*\w{3})\s*$#");
                $num = 1;
            } else {
                $date = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[2]", $root, true, "#(\d+\s*\w{3})\s*$#");
                $num = 2;
            }

            if (empty($date)) {
                $this->http->Log("need to correct format parsing");
                $it['TripSegments'] = [];

                break;
            }
            $date = EmailDateHelper::parseDateRelative($this->normalizeDate($date), $this->date);
            $num++;
            $node = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[{$num}]", $root);

            if (preg_match("#(.+?)\s*(?:Terminal\s*(.+)|$)#i", $node, $m)) {
                $seg['DepName'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['DepartureTerminal'] = $m[2];
                }
            }
            $num++;
            $seg['DepDate'] = strtotime($this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[{$num}]", $root), $date);
            $num++;
            $node = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[{$num}]", $root);

            if (preg_match("#(.+?)\s*(?:Terminal\s*(.+)|$)#i", $node, $m)) {
                $seg['ArrName'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['ArrivalTerminal'] = $m[2];
                }
            }
            $num++;
            $seg['ArrDate'] = strtotime($this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[{$num}]", $root), $date);
            $seg['BookingClass'] = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[last()]", $root);

            $node = $this->pdf->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[1]", $root, true, "#^\s*([A-Z\d]{2}\s*\d+)#");

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
        }
        $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//tr[starts-with(.,'Fare details')]/following::td[starts-with(.,'Fare') and not(contains(.,'Fare calculation'))]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//tr[starts-with(.,'Fare details')]/following::td[starts-with(.,'Total')]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\s*(\w{3})\s*(\d{2})$#',
            '#^(\d+)\s*(\w{3})\s*$#',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

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
                if (stripos($body, $reBody[0]) !== false) {
                    foreach ($reBody[1] as $re) {
                        if (stripos($body, $re) !== false) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function tablePdf($parser, $num = 0)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[$num])) {
            return false;
        }
        $pdf = $pdfs[$num];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", $this->pdf->FindNodes(".//text()", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row => $c) {
                ksort($c);
                $html .= "<tr>";

                foreach ($c as $col) {
                    $html .= "<td>" . $col . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->setBody($html);

        return true;
    }
}
