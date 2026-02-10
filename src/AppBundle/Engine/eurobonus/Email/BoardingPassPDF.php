<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-6451954.eml, eurobonus/it-7411040.eml, eurobonus/it-7502119.eml, eurobonus/it-8682959.eml";

    public $reFrom = "flysas.com";
    public $reBodyPDF = [
        'en' => ['Boarding Pass', 'flysas.com'],
    ];
    public $reSubject = [
        'false',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if (!$this->tablePdf($parser)) {
            return null;
        }
        $its = $this->parseEmail();
        $name = explode('\\', __CLASS__);

        return [
            'emailType'  => end($name),
            'parsedData' => ['Itineraries' => $its],
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

    private function parseEmail()
    {
        $bps = $this->pdf->XPath->query("//table");
        $its = [];

        foreach ($bps as $i => $root) {
            $parent = "//table[" . ($i + 1) . "]";
            $rows = $this->pdf->XPath->query($parent . "/tr");

            $titleRowNum = count($this->pdf->FindNodes($parent . "//tr[normalize-space(./td)='FLIGHT' or normalize-space(./td)='Flight']/preceding-sibling::tr")) + 1;

            $RecordLocator = $this->pdf->FindSingleNode(".//td[contains(text(),'PNR:')]", $root, true, "#PNR:\s*([A-Z\d]+)#");
            $passangers = $this->pdf->FindSingleNode(".//td[contains(text(),'PNR:')]/ancestor::tr/td[1]", $root);
            $TicketNumber = explode(',', $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='Ticket#']")->item(0), +1, 0));

            foreach ($rows as $rownum => $row) {
                $seg = [];

                if (empty($this->pdf->FindSingleNode("./td[1]", $row, true, "#^\s*([A-Z\d]{2}\d+)\s*$#"))
                        && empty($this->pdf->FindSingleNode("./td[2]", $row, true, "#\d{1,2}\s*\[a-z]+\s*\d{4}#i"))) {
                    continue;
                }
                $rowDiff = $rownum + 1 - $titleRowNum;

                $flight = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='FLIGHT' or normalize-space(.)='Flight']")->item(0), 0, $rowDiff);

                if (preg_match("#([A-Z\d]{2})(\d+)#", $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['flightName'] = $m[1] . $m[2];
                }
                $date = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='DATE' or normalize-space(.)='Date']")->item(0), 0, $rowDiff);
                $time = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='TIME' or normalize-space(.)='Time']")->item(0), 0, $rowDiff);
                $seg['DepDate'] = strtotime($date . ' ' . $time);

                if (empty($seg['DepDate'])) {
                    $seg['DepDate'] = strtotime($this->translateDate($date . ' ' . $time));
                }
                $seg['ArrDate'] = MISSING_DATE;

                $depart = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='FROM' or normalize-space(.)='From']")->item(0), 0, $rowDiff);

                if (preg_match("#(.*)\s+([A-Z]{3})(?:\s+Terminal\s(.*))?#", $depart, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];

                    if (!empty($m[3])) {
                        $seg['DepartureTerminal'] = $m[3];
                    }
                } else {
                    $seg['DepName'] = $depart;
                    $depart2 = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='FROM' or normalize-space(.)='From']")->item(0), 0, $rowDiff + 1);

                    if (preg_match("#([A-Z]{3})(?:,?\s*T\s*(.+))?#", $depart2, $m)) {
                        $seg['DepCode'] = $m[1];

                        if (!empty($m[2])) {
                            $seg['DepartureTerminal'] = $m[2];
                        }
                    }
                }
                $arrive = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='TO' or normalize-space(.)='To']")->item(0), 0, $rowDiff);

                if (preg_match("#(.*)\s+([A-Z]{3})(?:\s+Terminal\s(.*))?#", $arrive, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];

                    if (!empty($m[3])) {
                        $seg['ArrivalTerminal'] = $m[3];
                    }
                } else {
                    $seg['ArrName'] = $arrive;
                    $arrive2 = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='TO' or normalize-space(.)='To']")->item(0), 0, $rowDiff + 1);

                    if (preg_match("#([A-Z]{3})(?:,?\s*T\s*(.+))?#", $arrive2, $m)) {
                        $seg['ArrCode'] = $m[1];

                        if (!empty($m[2])) {
                            $seg['ArrivalTerminal'] = $m[2];
                        }
                    }
                }
                $BookingClass = $this->cell($this->pdf->XPath->query($parent . "//td[normalize-space(.)='CLASS' or normalize-space(.)='Class']")->item(0), 0, $rowDiff);

                if (preg_match("#^\s*([A-Z]{1,2})#", $BookingClass, $m)) {
                    $seg['BookingClass'] = $m[1];
                }
                $seg['Seats'] = [];
                $Seat = $this->cell($this->pdf->XPath->query($parent . "//td[starts-with(. ,'SEAT') or starts-with(. ,'Seat')]")->item(0), 0, $rowDiff);

                if (preg_match("#^\s*(\d{1,3}[A-Z])#", $Seat, $m)) {
                    $seg['Seats'][] = $m[1];
                }

                $finded = false;

                foreach ($its as $key => $it) {
                    if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                        if (isset($passangers)) {
                            $its[$key]['Passengers'][] = $passangers;
                        }

                        if (isset($ReservationDate) && !isset($it['ReservationDate'])) {
                            $its[$key]['ReservationDate'] = $ReservationDate;
                        }

                        if (isset($TicketNumber)) {
                            if (isset($it['TicketNumbers'])) {
                                $its[$key]['TicketNumbers'] = array_merge($it['TicketNumbers'], $TicketNumber);
                            } else {
                                $its[$key]['TicketNumbers'] = $TicketNumber;
                            }
                        }
                        $finded2 = false;

                        foreach ($it['TripSegments'] as $key2 => $value) {
                            if (isset($seg['flightName']) && $seg['flightName'] == $value['flightName'] && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                                $its[$key]['TripSegments'][$key2]['Seats'] = array_values(array_unique(array_merge($value['Seats'], $seg['Seats'])));
                                $finded2 = true;
                            }
                        }

                        if ($finded2 == false) {
                            $its[$key]['TripSegments'][] = $seg;
                        }
                        $finded = true;
                    }
                }

                unset($it);

                if ($finded == false) {
                    $it['Kind'] = 'T';

                    if (isset($RecordLocator)) {
                        $it['RecordLocator'] = $RecordLocator;
                    }

                    if (isset($passangers)) {
                        $it['Passengers'][] = $passangers;
                    }

                    if (isset($ReservationDate)) {
                        $it['ReservationDate'] = $ReservationDate;
                    }

                    if (isset($TicketNumber)) {
                        $it['TicketNumbers'] = $TicketNumber;
                    }
                    $it['TripSegments'][] = $seg;
                    $its[] = $it;
                }
            }
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                unset($its[$key]['TripSegments'][$i]['flightName']);
            }

            if (isset($its[$key]['Passengers'])) {
                $its[$key]['Passengers'] = array_values(array_unique(array_unique($its[$key]['Passengers'])));
            }

            if (isset($its[$key]['TicketNumbers'])) {
                $its[$key]['TicketNumbers'] = array_values(array_unique(array_unique(array_filter($its[$key]['TicketNumbers']))));
            }
        }

        return $its;
    }

    private function AssignLang($body)
    {
        if (isset($this->reBodyPDF)) {
            foreach ($this->reBodyPDF as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function tablePdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

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

                if ($top < 100 && $top > 400) {
                    continue;
                }
                $cols[round($left / 5)] = round($left / 5);
                $grid[$top][round($left / 5)] = $text;
            }
            ksort($grid);

            foreach ($grid as $row => $c) {
                for ($i = $row - 10; $i < $row; $i++) {
                    if (isset($grid[$i])) {
                        foreach ($grid[$row] as $k => $v) {
                            $grid[$i][$k] = $v;
                        }
                        unset($grid[$row]);

                        break;
                    }
                }
            }
            ksort($cols);
            $translate = [];

            foreach ($cols as $left) {
                for ($i = $left - 8; $i < $left; $i++) {
                    if (isset($cols[$i])) {
                        $translate[$left] = $cols[$i];
                        unset($cols[$left]);

                        break;
                    }
                }
            }

            foreach ($grid as $row=>&$c) {
                foreach ($translate as $from=>$to) {
                    if (isset($c[$from])) {
                        $c[$to] = $c[$from];
                        unset($c[$from]);
                    }
                }
                ksort($c);
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row=>$c) {
                $html .= "<tr>";

                foreach ($cols as $col) {
                    $html .= "<td>" . ($c[$col] ?? "&nbsp;") . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->setBody($html);

        return true;
    }

    private function cell($node, $x = 0, $y = 0)
    {
        if (!$node) {
            return null;
        }
        $n = count($this->pdf->FindNodes("./preceding-sibling::td", $node)) + 1;

        if ($y > 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]", $node);
        } elseif ($y < 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]", $node);
        } else {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/td[" . ($n + $x) . "]", $node);
        }
    }

    private function translateDate($str)
    {
        $str = str_replace('.', '', $str);

        if (preg_match("#\d+\s*([^\d\s]+)\s*\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], "sv")) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], "no")) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
