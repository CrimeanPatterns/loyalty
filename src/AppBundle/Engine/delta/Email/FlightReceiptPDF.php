<?php

namespace AwardWallet\Engine\delta\Email;

class FlightReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "delta/it-30301616.eml, delta/it-330690481.eml, delta/it-6196187.eml";
    public $reFrom = 'dotcc@delta.com';
    public $reBody = [
        'en'  => ['Your Trip Confirmation #', 'Flight Receipt'],
        'en2' => ['CONFIRMATION #', 'Flight Receipt'],
    ];
    public $lang = '';
    public $date;
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

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
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        } else {
            return null;
        }

        $body = $this->pdf->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "FlightReceiptPDF" . $this->lang,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf[0])));

            if (stripos($text, 'delta') !== false) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
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
        $it['RecordLocator'] = $this->pdf->FindSingleNode("(//text()[contains(.,'" . $this->t('Your Trip Confirmation #') . "')]/ancestor::p[1]/following::p[string-length(normalize-space(.))>4][1])[1]", null, true, "#[A-Z\d]+#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(.,'CONFIRMATION #:')]", null, true, "/[#]\:\s*([A-Z\d]{6})/");
        }
        $it['Passengers'] = array_unique($this->pdf->FindNodes("//text()[contains(.,'NAME')]/ancestor::p[string-length(normalize-space(.))>1][1]/following::p[1]"));

        if (empty($it['Passengers'])) {
            $it['Passengers'] = array_unique($this->pdf->FindNodes("//text()[contains(.,'Name')]/ancestor::p[string-length(normalize-space(.))>1][1]/descendant::text()[normalize-space()][1]", null, "/Name\:?\s*(.+)/iu"));
        }
        $it['TicketNumbers'] = array_unique($this->pdf->FindNodes("//p[contains(.,'Ticket #')]/following::p[1]"));

        $it['AccountNumbers'] = array_filter(array_unique($this->pdf->FindNodes("//p[contains(.,'SkyMiles #')]", null, "#\\#\s*(\*+\d{3,})\b#")));

        if (empty($it['AccountNumbers'])) {
            $it['AccountNumbers'] = array_filter(array_unique($this->pdf->FindNodes("//text()[contains(.,'SkyMiles')]/preceding::text()[normalize-space()][1]", null, "/\#\s*(\d{3,})/")));
        }

        $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[contains(.,'Ticket Issue Date:')])[1]", null, true, "#:\s+(.+)#")));

        if ($it['ReservationDate']) {
            $this->date = $it['ReservationDate'];
        }

        // Base Fare
        $totals = $this->pdf->FindNodes("//p[contains(.,'Base Fare')]/following::p[1]");

        if (!empty($totals)) {
            $it['BaseFare'] = 0.0;

            foreach ($totals as $totals) {
                $tot = $this->getTotalCurrency($totals);

                if (!empty($tot['Total'])) {
                    $it['BaseFare'] += $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
            }
        }
        // Total
        $totals = $this->pdf->FindNodes("//p[contains(.,'TICKET AMOUNT')]/following::p[1]");

        if (!empty($totals)) {
            $it['TotalCharge'] = 0.0;

            foreach ($totals as $totals) {
                $tot = $this->getTotalCurrency($totals);

                if (!empty($tot['Total'])) {
                    $it['TotalCharge'] += $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
            }
        }

        $xpath = "//text()[normalize-space(.)='DEPART']/ancestor::p[1]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./preceding::p[1]", $root)));

            if ($date < strtotime("-2 month", $this->date)) {
                $date = strtotime("+1 year", $date);
            }

            for ($i = 0; $i <= 10; $i++) {
                $position = $i + 2;
                $node = implode(" ", $this->pdf->FindNodes("./following::p[{$position}]//text()", $root));

                if (!preg_match("#(.+?)\s+(\d+)\*?\s+(.*?)\s*\(([A-Z]{1,2})\)#", $node)) {
                    break;
                }

                $seg = [];

                if (preg_match("#(.+?)\s+(\d+)\*?\s+(.*?)\s*\(([A-Z]{1,2})\)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];

                    if (!empty($m[3])) {
                        $seg['Cabin'] = $m[3];
                    }
                    $seg['BookingClass'] = $m[4];

                    $node = $this->pdf->FindSingleNode("(./following::text()[contains(., 'Flight {$seg['FlightNumber']} Operated by ')])[1]", $root);

                    if (preg_match("#Operated by (.+?)\s*(\s+DBA\s+.+|\s+As\s+.+|$)#", $node, $m)) {
                        $seg['Operator'] = $m[1];
                    }
                }

                $position = $i + 3;
                $node = implode(" ", $this->pdf->FindNodes("./following::p[normalize-space()][{$position}]//text()", $root));

                if (preg_match("#(.+?)\s+(\d+:\d+(?:[ap]m)?)#i", $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepDate'] = strtotime($m[2], $date);
                }

                $position = $i + 4;
                $node = implode(" ", $this->pdf->FindNodes("./following::p[normalize-space()][{$position}]//text()", $root));

                if (preg_match("#(.+?)\s+(\d+:\d+(?:[ap]m)?)#i", $node, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrDate'] = strtotime($m[2], $date);
                }

                //			if (strpos($node, "*") !== false) {///in text look's like "*Arrival date is different than departure date"
                //				if ($seg['ArrDate'] < $seg['DepDate'])
                //					$seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
                //				else
                //					$seg['ArrDate'] = strtotime("-1 day", $seg['ArrDate']);
                //			}
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (isset($seg['AirlineName'])) {
                    $seg['Seats'] = $this->pdf->FindNodes("//p[normalize-space(.)='" . $seg['AirlineName'] . ' ' . $seg['FlightNumber'] . "']/following::p[1][not(contains(.,'Select Seat'))]");
                }

                $i = $i + 2;

                foreach ($it['TripSegments'] as $key2 => $value) {
                    if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                        && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                        && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                        continue 2;
                    }
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#(\d+)\s*(\w{3})\s*(\d+)#u',
            '#\w+,\s+(\d+)\s*(\w+)#u',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 ' . $year,
        ];

        return preg_replace($in, $out, $date);
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
                    $this->lang = substr($lang, 0, 2);

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
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

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
