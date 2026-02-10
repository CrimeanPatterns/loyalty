<?php

namespace AwardWallet\Engine\ana\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "ana/it-3.eml";
    private $_emails = [
        'anaintrsv@121.ana.co.jp',
    ];
    private $_subjects = [
        'From ANA [Reservation information]',
        'From ANA [Award e-Ticket Itinerary Receipt]',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]121\.ana\.co\.jp$/ims', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        $from = $this->_checkInHeader($headers, 'from', $this->_emails);
        $subject = $this->_checkInHeader($headers, 'subject', $this->_subjects);

        return $from && $subject;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // If forwarded message
        $body = $parser->getPlainBody();

        if (preg_match("#anaintrsv@121.ana.co.jp#", $body)) {
            return true;
        }

        if (preg_match("#From ANA \[Reservation information\]#", $body)) {
            return true;
        }

        if (preg_match("#From ANA \[Award e\-Ticket Itinerary Receipt\]#", $body)) {
            return true;
        }

        return false;
    }

    public function extractPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function toText($html)
    {
        $nbsp = '&' . 'nbsp;';
        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#&\#160;#ums", " ", $html);
        $html = preg_replace("#$nbsp#ums", " ", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);

        $html = preg_replace("#[^\w\d\s:;,./\(\)\[\]\{\}\-\\\$]#", '', $html);

        return $html;
    }

    public function re($re, $text)
    {
        return preg_match($re, $text, $m) ? $m[1] : null;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));
        $body = $parser->getBody();

        if (preg_match("#Attached please find your|Please print your#", $body)) {
            return $this->ParsePdfEmail($parser);
        }

        $body = preg_replace("/^\>?\s+/im", '', $body);
        $body = preg_replace("/\xC2\xA0/im", ' ', $body);
        $offset = 0;

        $it = [];
        $it['Kind'] = 'T';

        preg_match("/^\<Reservation Number\>\s([\w\d]+)\s/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1])) {
            $it['RecordLocator'] = $m[1][0];
            $offset = $m[1][1];
        }
        preg_match("/^\<Passenger Name\>\s([\w\d\.\s]+)/im", $body, $m, PREG_OFFSET_CAPTURE, $offset);

        if (isset($m[1])) {
            $it['Passengers'] = explode(',', trim($m[1][0]));
            $offset = $m[1][1];
        }

        // ---------------- Trip Segments -------------------

        $segment = function ($flight) {
            $data = [];
            $info = preg_split("/\n/im", trim($flight), -1, PREG_SPLIT_NO_EMPTY);

            preg_match("/^\w+\.\s+(\w+)\.,\s(\d+),\s(\d+)/i", $info[0], $m);

            if (!$m) {
                return;
            }
            $day = $m[3] . '-' . $m[1] . '-' . $m[2];

            preg_match("/[A-Z\d]+$/i", $info[0], $m);

            if (isset($m[0])) {
                $data['FlightNumber'] = $m[0];
            }
            preg_match("/^([\w\s]+)\s?(?:\((.+)\))?\s\-\s/i", $info[1], $m);

            if (isset($m[1])) {
                $data['DepCode'] = isset($m[2]) ? trim($m[2]) : TRIP_CODE_UNKNOWN;
                $data['DepName'] = trim($m[1]);
            }
            preg_match("/DEP.\s*(\d+:\d+)/i", $info[2], $m);

            if (isset($m[1])) {
                $data['DepDate'] = strtotime($day . ' ' . $m[1]);
            }
            preg_match("/\s\-\s([\w\s]+)\s?(?:\((.+)\))?$/i", $info[1], $m);

            if (isset($m[1])) {
                $data['ArrCode'] = isset($m[2]) ? trim($m[2]) : TRIP_CODE_UNKNOWN;
                $data['ArrName'] = trim($m[1]);
            }
            preg_match("/ARR.\s*(\d+:\d+)(\+\d+)?/i", $info[2], $m);

            if (isset($m[1])) {
                $data['ArrDate'] = strtotime($day . (isset($m[2]) ? " +{$m[2]}day " : ' ') . $m[1]);
            }
            preg_match("/Flight time : (.+)$/i", $info[2], $m);

            if (isset($m[1])) {
                $data['Duration'] = trim($m[1]);
            }
            preg_match("/Seat number : (.+)$/im", $flight, $m);

            if (isset($m[1])) {
                $data['Seats'] = trim($m[1]);
            }
            preg_match("/(.+) class - OK/im", $flight, $m);

            if (isset($m[1])) {
                $temp = explode(':', $m[1]);
                $data['Cabin'] = trim($temp[0]);

                if (isset($temp[1])) {
                    $data['BookingClass'] = trim($temp[1]);
                }
            }
            preg_match("/Flight operated by (.+)$/im", $flight, $m);

            if (isset($m[1])) {
                $data['AirlineName'] = trim($m[1]);
            }

            return $data;
        };

        $trip = '';

        if ($start = stripos($body, '<Flight Information>', $offset)) {
            $start = strpos($body, "\n", $start) + 1;
            $end = strpos($body, '*Terminal information', $offset) - 1;
            $trip = trim(substr($body, $start, $end - $start));
        }
        $flights = preg_split("/\[\d+\]/im", $trip, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($flights as $flight) {
            $it['TripSegments'][] = $segment($flight);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function _checkInHeader(&$headers, $field, $source)
    {
        if (isset($headers[$field])) {
            foreach ($source as $key => $temp) {
                if (stripos($headers[$field], $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function ParsePdfEmail(\PlancakeEmailParser $parser)
    {
        $it = ['Kind' => 'T'];

        $text = $this->toText($this->extractPDF($parser));

        if (preg_match("#\n([^\n]+)\s+PASSENGER NAME\s+[\d\-]+\s+([A-Z\d/]+)\s+(\d+\w{3}\d+)#msu", $text, $m)) {
            $it['Passengers'] = $m[1];
            $it['RecordLocator'] = preg_replace("#/.*$#", '', $m[2]);
        }

        $it['TripSegments'] = [];

        $flights = $this->re("#(\n\[\d+\][^\n]+.*?)\nFORM OF PAYMENT#ms", $text);
        $segments = preg_split("#\n\[\d+\]\s*([^\n]+)\n#", $flights, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        for ($i = 0; $i < count($segments) - 1; $i += 2) {
            $seg = [];

            $str = $segments[$i] . "\n" . $segments[$i + 1];

            if (preg_match("#^(.*?)\s+([A-Z]{2}\s\d+)\s+(.*?)\s+(\d+\w{3}\d+)\s+\w{3}\s+(\d{2})(\d{2})\s+.*?REMARKS\n([^\n]+)\s+([^\n]+)\s+(\d+\w{3}\d+)\s+\w{3}\s+(\d{2})(\d{2})\s+#ms", $str, $m)) {
                $seg['FlightNumber'] = $m[2];

                $seg['Cabin'] = $m[3];

                $seg['DepName'] = $m[1];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = strtotime($m[4] . ', ' . $m[5] . ':' . $m[6], $this->date);

                $seg['ArrName'] = $m[7];
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrDate'] = strtotime($m[9] . ', ' . $m[10] . ':' . $m[11], $this->date);

                $seg['AirlineName'] = $m[8];
            }

            $it['TripSegments'][] = $seg;
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }
}
