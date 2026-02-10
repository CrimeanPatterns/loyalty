<?php

namespace AwardWallet\Engine\ctrip\Email;

class TripTicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-4576581-pdf.eml, ctrip/it-8439103.eml, ctrip/it-8462769-pdf.eml, ctrip/it-8462774-pdf.eml, ctrip/it-8557135-pdf.eml";

    public $reProvider = "ctrip.com";
    public $reFrom = "ia_rsv@ctrip.com";
    public $reBody = "ctrip.com";
    public $reBody2 = [
        "机票行程确认单",
        "機票行程確認單",
    ];
    public $reBodyPDF = [
        ["ITINERARY", "ORIGIN/DES"],
    ];

    public $reSubject = [
        "机票行程确认单",
    ];

    public $pdfPattern = '.+\.pdf';
    public $total = [
        'BaseFare'    => 0,
        'Tax'         => 0,
        'TotalCharge' => 0,
        'Currency'    => '',
    ];
    public $pdfBody = '';

    public function parsePdf($texts, &$its): void
    {
        $this->logger->debug(__FUNCTION__);
        $docs = $this->split("#\n\s*ITINERARY\s*\n#", $texts);

        foreach ($docs as $text) {
            $pos = strpos($text, "ORIGIN/DES");

            $mainInfo = substr($text, 0, $pos);

            if (preg_match("#AIRLINE PNR:\s*([A-Z\d]{5,})(?:\s{3,}|$)#", $mainInfo, $m)
                || preg_match("#IE\s+PNR:\s*([A-Z\d]{5,})#", $mainInfo, $m)
            ) {
                $RecordLocator = $m[1];
            }

            if (preg_match("#\n\s*NAME:\s*([A-Z\-/ ]+)(?:\s{3,}|$)#", $mainInfo, $m)) {
                $Passengers = trim($m[1]);
            }

            if (preg_match("#ETKT NBR:\s*([\d\-]+)#", $mainInfo, $m)) {
                $TicketNumbers = $m[1];
            }

            if (preg_match("#DATE OF ISSUE:\s*(\d+)([A-Z]+)(\d{2})#", $mainInfo, $m)) {
                $ReservationDate = strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3]);
            }
            $pos2 = strpos($text, "FORM OF PAYMENT");

            if (!empty($pos2)) {
                $flights = substr($text, $pos, $pos2 - $pos);
                $totalInfo = substr($text, $pos2);

                if (preg_match("#FARE:\s*([A-Z]{3})\s*([\d\.]+)#", $totalInfo, $m)) {
                    $this->total['BaseFare'] += (float) $m[2];
                }

                if (preg_match("#TAX:\s*([A-Z]{3})\s*([\d\.]+)#", $totalInfo, $m)) {
                    $this->total['Tax'] += (float) $m[2];
                }

                if (preg_match("#TOTAL:\s*([A-Z]{3})\s*([\d\.]+)#", $totalInfo, $m)) {
                    $this->total['Currency'] = $m[1];
                    $this->total['TotalCharge'] += (float) $m[2];
                }
            } else {
                $flights = substr($text, $pos);
            }

            $flightsTable = $this->splitCols($flights);

            foreach ($flightsTable as $key => $col) {
                $flightsTable[$key] = explode("\n\n", $col);
            }

            for ($i = 1; $i < count($flightsTable[0]) - 1; $i++) {
                if ($flightsTable[0][$i] == '' || ($flightsTable[1][$i] == '' && $flightsTable[3][$i] == '')) {
                    continue;
                }
                $seg = [];

                if (empty($flightsTable[1][$i]) && !empty($flightsTable[3][$i])
                    && preg_match('#([A-Z]{3}\s*-\s*.+)\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+)\s*$#', $flightsTable[0][$i], $m)
                ) {
                    $flightsTable[0][$i] = $m[1];
                    $flightsTable[1][$i] = $m[2];
                }

                if (preg_match('#([A-Z]{3})\s*\-\s*(.+)#', $flightsTable[0][$i], $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepName'] = $m[2];
                }

                if (isset($flightsTable[0][$i + 1])) {
                    if (preg_match('#([A-Z]{3})\s*\-\s*(.+)#', $flightsTable[0][$i + 1], $m)) {
                        $seg['ArrCode'] = $m[1];
                        $seg['ArrName'] = $m[2];
                    }
                }

                if (preg_match('#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#', $flightsTable[1][$i], $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $year = date("Y", $ReservationDate);

                if (preg_match('#(\d+)\s*([A-Z]+)#', $flightsTable[3][$i], $m) && preg_match('#(\d{1,2})(\d{2})(\s*\+1)?#', $flightsTable[4][$i], $m1)) {
                    $seg['DepDate'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $year . ' ' . $m1[1] . ':' . $m1[2]);

                    if ($seg['DepDate'] < $ReservationDate) {
                        $seg['DepDate'] = strtotime('+1 year', $seg['DepDate']);
                    }
                }

                if (preg_match('#(\d+)\s*([A-Z]+)#', $flightsTable[3][$i], $m) && preg_match('#(\d{1,2})(\d{2})(\s*\+1)?#', $flightsTable[5][$i], $m1)) {
                    $seg['ArrDate'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $year . ' ' . $m1[1] . ':' . $m1[2]);

                    if (isset($m1[3])) {
                        $seg['ArrDate'] = strtotime('+1 day', $seg['ArrDate']);
                    }

                    if ($seg['ArrDate'] < $ReservationDate) {
                        $seg['ArrDate'] = strtotime('+1 year', $seg['ArrDate']);
                    }
                }

                if (empty($seg['AirlineName']) || empty($seg['FlightNumber']) || empty($seg['DepDate'])) {
                    return;
                }

                $finded = false;

                foreach ($its as $key => $it) {
                    if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                        if (isset($Passengers)) {
                            $its[$key]['Passengers'][] = $Passengers;
                        }

                        if (isset($TicketNumbers)) {
                            $its[$key]['TicketNumbers'][] = $TicketNumbers;
                        }

                        if (isset($ReservationDate)) {
                            $its[$key]['ReservationDate'] = $ReservationDate;
                        }
                        $finded2 = false;

                        foreach ($it['TripSegments'] as $key2 => $value) {
                            if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                    && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                    && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
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

                    if (isset($Passengers)) {
                        $it['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumbers)) {
                        $it['TicketNumbers'][] = $TicketNumbers;
                    }

                    if (isset($ReservationDate)) {
                        $it['ReservationDate'] = $ReservationDate;
                    }
                    $it['TripSegments'][] = $seg;
                    $its[] = $it;
                }
            }
        }
    }

    public function parseHtml(&$its): void
    {
        $this->logger->debug(__FUNCTION__);
        $it['Kind'] = 'T';
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(.,'订单号：')]", null, true, "#订单号：\s*(\d+)#u");
        $it['Passengers'] = $this->http->FindNodes("//text()[contains(.,'姓名')]/ancestor::thead[1][contains(.,'性别')]/following-sibling::tbody/tr/td[2]");

        if (!empty($this->pdfBody)) {
            if (preg_match_all("#TICKET NUMBER/票号：\s*([\d\-]+)#", $this->pdfBody, $m)) {
                // it-8439103.eml(pdf)
                $it['TicketNumbers'] = $m[1];
            }
        }
        $xpath = "//text()[contains(.,'到达城市')]/ancestor::thead[1][contains(.,'起飞时间')]/following-sibling::tbody/tr";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length == 0) {
            $this->logger->debug('Segments roots not found: ' . $xpath);
        }

        foreach ($segments as $root) {
            $seg = [];
            $flight = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match('#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $name = implode(" ", $this->http->FindNodes("./td[3]//text()", $root));

            if (preg_match('#([^(]+)\s*\(([^\-]+)-?(.*)\)#', $name, $m)) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepName'] = $m[1] . '- ' . $m[2];
                $seg['DepartureTerminal'] = $m[3];
            }
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./td[5]", $root));

            $name = implode(" ", $this->http->FindNodes("./td[4]//text()", $root));

            if (preg_match('#([^(]+)\s*\(([^\-]+)-?(.*)\)#', $name, $m)) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $m[1] . '- ' . $m[2];
                $seg['ArrivalTerminal'] = $m[3];
            }
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./td[6]", $root));

            $seg['Cabin'] = $this->http->FindSingleNode("./td[7]", $root);

            $it['TripSegments'][] = $seg;
        }
        $total = $this->http->FindSingleNode("//text()[contains(.,'总计 ')]", null, true, "#总计\s*(.+)#u");

        if (preg_match("#([A-Z]{3})\s*([\d\.,]+)#", $total, $m)) {
            $this->total['Currency'] = ($m[1] == 'RMB') ? 'CNY' : $m[1];
            $this->total['TotalCharge'] = (float) str_replace(",", '', $m[2]);
        }
        $its[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
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
        $body = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!empty($pdfs[0])) {
            foreach ($pdfs as $pdf) {
                $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
            }

            foreach ($this->reBodyPDF as $reBodyPDF) {
                if (strpos($body, $reBodyPDF[0]) !== false && strpos($body, $reBodyPDF[1]) !== false) {
                    return true;
                }
            }
        } else {
            $body = $parser->getHTMLBody();

            if (stripos($body, $this->reBody) === false) {
                return false;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = '';

        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $this->pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->reBodyPDF as $reBodyPDF) {
                if (stripos($this->pdfBody, $reBodyPDF[0]) !== false && stripos($this->pdfBody, $reBodyPDF[1]) !== false) {
                    $type = 'pdf';
                    $this->parsePdf($this->pdfBody, $its);

                    continue 2;
                }
            }
        }

        if (count($its) === 0 || empty($its[0]['TripSegments'])) {
            $type = 'html';
            $this->parseHtml($its);
        }

        foreach ($its as $key => $it) {
            if (array_key_exists('TripSegments', $it)) {
                foreach ($it['TripSegments'] as $i => $value) {
                    if (isset($its[$key]['Passengers'])) {
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    }

                    if (isset($its[$key]['TicketNumbers'])) {
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    }
                }
            }
        }

        $result = [
            'emailType' => 'TripTicketConfirmation' . ucfirst($type),
        ];

        if (count($its) == 1) {
            if (!empty($this->total['BaseFare'])) {
                $its[0]['BaseFare'] = $this->total['BaseFare'];
            }

            if (!empty($this->total['Tax'])) {
                $its[0]['Tax'] = $this->total['Tax'];
            }

            if (!empty($this->total['TotalCharge'])) {
                $its[0]['TotalCharge'] = $this->total['TotalCharge'];
            }

            if (!empty($this->total['Currency'])) {
                $its[0]['Currency'] = $this->total['Currency'];
            }
        } else {
            $result['TotalCharge']['Amount'] = $this->total['TotalCharge'];
            $result['TotalCharge']['Currency'] = $this->total['Currency'];
        }

        $result['parsedData']['Itineraries'] = $its;

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 2; // html + pdf
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text);

        if (isset($r[0]) && strlen($r[0]) < 100) {
            array_shift($r);
        }

        return $r;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
