<?php

namespace AwardWallet\Engine\ana\Email;

class TicketPdf2015En extends \TAccountChecker
{
    public $mailFiles = "";
    protected $result;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();

        $locators = $this->findCutSection($text, 'ANA Reservation Number', 'View your reservation');

        if (preg_match_all('/[A-Z\d]{5,6}/s', $locators, $matches)) {
            if (count(array_unique($matches[0])) > 1) {
                $this->http->Log('RecordLocator > 1', LOG_LEVEL_ERROR);

                return false;
            }
        }

        $pdf = $parser->searchAttachmentByName('(e-Ticket.*?|.*?[A-Z\d]+.*?)\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $this->parseReservations(str_replace(' ', ' ', $pdfText), $text);

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'anaintrsv@121.ana.co.jp') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], '[From ANA] Delivery of e-Ticket') !== false
                || stripos($headers['subject'], '【ANA的通知】电子机票的发送') !== false
                || stripos($headers['subject'], '[來自 ANA] 發出電子機票') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();

        return stripos($body, 'We are sending you your e-Ticket.') !== false
                || stripos($body, '我们将向您发送电子机票') !== false
                || stripos($body, '我們正在發送您的電子機票') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@121.ana.co.jp') !== false;
    }

    protected function parseReservations($pdfText, $text)
    {
        $this->result['Kind'] = 'T';
        $info = $this->findCutSection($pdfText, 'may be required in case of itinerary change', 'ITINERARY');

        if (preg_match('#RESERVATION[：:]\s*([A-Z\d/]+)\s*DATE OF#u', $info, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('/(.*?)\s*Thank you so much for flying with ANA/su', $text, $matches)) {
            $this->result['Passengers'][] = array_map('trim', explode("\n", $matches[1]));
        }

        //		if (preg_match('/TICKET[：:]\s*([\d-]+)\s*RESERVATION/', $info, $matches)) {
        //			$this->result['TicketNumbers'][] = $matches[1];
        //		}

        $payment = $this->findCutSection($pdfText, 'FARE/TICKET INFORMATION', 'TICKET NOTICE');

        if (preg_match('/TAXES\/FEES\s*[：:]\s*(.*?)\s*SERVICE CHARGE/', $payment, $matches)) {
            $this->result['Tax'] = cost($matches[1]);
        }

        if (preg_match('/TOTAL\s*[：:]\s*(.*?)\s*TOUR CODE/', $payment, $matches)) {
            $this->result += total($matches[1]);
        }

        $this->parseSegments(join($this->findCutSectionAll($pdfText, 'DEPARTURE', ['ALL NIPPON AIRWAYS', 'FARE/TICKET INFORMATION'])));
    }

    protected function parseSegments($pdfText)
    {
        foreach (preg_split('#\[\s*\d+\s*\]#u', $pdfText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $segment = $this->parseSegment($value);

            if (!empty($segment)) {
                $this->result['TripSegments'][] = $segment;
            }
        }
    }

    protected function parseSegment($pdfText)
    {
        // SAN FRANCISCO  I  UA875  03MAR17  FRI  1100 X
        // TOKYO(NARITA)  1  18K  10AUG15  MON  1655
        $regex = '\s*(.+?)\s+([A-Z\d]{1,3})?\s+([A-Z\d]{2})?\s*(\d+)\s+(\d+\w{3}\d+)\s+\w+\s+(\d+)\s+([A-Z])';
        $regex .= '.*?REMARKS\s*(.+?)\s+([A-Z\d]{1,3})?\s+([A-Z\d]{1,3})?\s+(\d+\w{3}\d+)\s+\w+\s+(\d+)';

        if (preg_match("/{$regex}/s", $pdfText, $matches)) {
            return [
                'DepName'           => $matches[1],
                'DepartureTerminal' => $matches[2],
                'AirlineName'       => $matches[3],
                'FlightNumber'      => $matches[4],
                'DepDate'           => strtotime($matches[5], $matches[6]),
                'DepCode'           => TRIP_CODE_UNKNOWN,
                'BookingClass'      => $matches[7],
                'ArrName'           => $matches[8],
                'ArrivalTerminal'   => $matches[9],
                'Seats'             => $matches[10],
                'ArrDate'           => strtotime($matches[11], $matches[12]),
                'ArrCode'           => TRIP_CODE_UNKNOWN,
            ];
        }
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * <b>LEFT</b> <i>cut text2</i> <b>RIGHT2</b>.
     */
    protected function findCutSectionAll($input, $searchStart, $searchFinish)
    {
        $array = [];

        while (empty($input) !== true) {
            $right = mb_strstr($input, $searchStart);

            foreach ($searchFinish as $value) {
                $left = mb_strstr($right, $value, true);

                if (!empty($left)) {
                    $input = mb_strstr($right, $value);
                    $array[] = mb_substr($left, mb_strlen($searchStart));

                    break;
                }
            }

            if (empty($left)) {
                $input = false;
            }
        }

        return $array;
    }

    protected function findCutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_strstr(mb_strstr($input, $searchStart), $searchFinish, true);

        return mb_substr($input, mb_strlen($searchStart));
    }
}
