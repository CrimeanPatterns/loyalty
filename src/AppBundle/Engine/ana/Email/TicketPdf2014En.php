<?php

namespace AwardWallet\Engine\ana\Email;

class TicketPdf2014En extends TicketPdf2015En
{
    public $mailFiles = "ana/it-4735172.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'anaintrsv@121.ana.co.jp') !== false
                && isset($headers['subject'])
                && stripos($headers['subject'], 'ANAからのお知らせ【特典お客様控え】') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();

        return stripos($body, '特典航空券の「eチケットお客様控え」') !== false;
    }

    protected function parseReservations($pdfText, $text)
    {
        $this->result['Kind'] = 'T';
        $info = $this->findCutSection($pdfText, 'may be required in case of itinerary change', 'ITINERARY');

        if (preg_match('#੍⚂⇟\s*([A-Z\d/]+)\s*⊒ⴕᣣ㩷㪑#u', $info, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('#([A-Z/\s]+)\s*PASSENGER#su', $info, $matches)) {
            $this->result['Passengers'][] = trim($matches[1]);
        }

        $payment = $this->findCutSection($pdfText, 'FARE/TICKET INFORMATION', 'TOUR CODE');

        if (preg_match('/税金・料金等合計\s*[：:]\s*([A-Z]{3}\d+)/u', $payment, $matches)) {
            $this->result['Tax'] = cost($matches[1]);
        }

        if (preg_match('/合計\s*[：:]\s*([A-Z]{3}\d+)/u', $payment, $matches)) {
            $this->result += total($matches[1]);
        }

        $this->parseSegments(join($this->findCutSectionAll($pdfText, 'DEPARTURE', ['ALL NIPPON AIRWAYS', 'FARE/TICKET INFORMATION'])));
    }

    protected function parseSegment($pdfText)
    {
        //NEW YORK/NEWARK I SK 902                   I(C)         16JUL15        THU 2330 OK                               2PC                                      /19SEP
        // 䍞䍎䍮䍣䍷 TERMINAL B
        //೔⌕ ARRIVAL                 ㆇ⥶⥶ⓨળ␠ OPERATING CARRIER     ೔⌕ ARRIVAL                    ㆇ⾓⒳೎ FARE BASIS            ᚲⷐᤨ㑆 FLIGHT TIME                ஻⠨ REMARKS
        //COPENHAGEN                 SCANDINAVIAN AL              17JUL15        FRI 1310 IBP00                             7.40                           FFP
        // 䍞䍎䍮䍣䍷 TERMINAL 3
        $regex = '\s*(.+?)\s+([A-Z\d]{2})?\s*(\d+)\s+(.*?)\s+(\d+\w+\d+)\s+\w+\s+(\d+)\s+([A-Z]+)\s+([A-Z\d]+)';
        $regex .= '.*?REMARKS\s*(.+?)(?:\s{2,}|\s+[A-Z]\s+)(.+?)\s+(\d+\w{3}\d+)\s+\w+\s+(\d+).*?\s+([\d.]+)';

        if (preg_match("/{$regex}/us", $pdfText, $matches)) {
            return [
                'DepName'      => $matches[1],
                'AirlineName'  => $matches[2],
                'FlightNumber' => $matches[3],
                'BookingClass' => $matches[4],
                'DepDate'      => strtotime($matches[6], strtotime($matches[5])),
                'DepCode'      => TRIP_CODE_UNKNOWN,
                'Seats'        => $matches[8],
                'ArrName'      => $matches[9],
                'Operator'     => $matches[10],
                'ArrDate'      => strtotime($matches[12], strtotime($matches[11])),
                'ArrCode'      => TRIP_CODE_UNKNOWN,
                'Duration'     => $matches[13],
            ];
        }
    }
}
