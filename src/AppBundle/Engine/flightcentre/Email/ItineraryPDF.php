<?php

namespace AwardWallet\Engine\flightcentre\Email;

class ItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "flightcentre.com";
    public $reBody = [
        'en' => ['FLIGHT CENTRE', 'Please see below your Electronic Ticket and Itinerary'],
    ];
    public $reSubject = [
        'Trip',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = "Itinerary.*pdf";
    public static $dict = [
        'en' => [
        ],
    ];
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    //можно сюда вставить парсимейл и потом мердж делать
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        } else {
            return null;
        }

        $body = text($this->pdf->Response['body']);
        $this->AssignLang($body);

        $its = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'www.flightcentre.') !== false) {
                return $this->AssignLang($text);
            }
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
        }

        return $date;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail($textPDF)
    {
        $textTop = strstr($textPDF, 'Please see below your Electronic Ticket and Itinerary', true);
        $tripNum = $this->re("#Booking ref[:\s]+([A-Z\d]+)#", $textTop);
        $resDate = strtotime($this->re("#Issued date[:\s]+(.+)#", $textTop));
        $traveller = $this->re("#Traveller\s+(.+)#", $textTop);

        $textTicket = $this->findСutSection($textPDF, 'Ticket details', ['General Information', 'Ecological information']);

        if (preg_match_all("#E-ticket\s+([A-Z\d]{2})?\s*(\d+[\-\d]+)\s+for\s+(.+)#", $textTicket, $m)) {
            $cnt = count($m[1]);

            for ($i = 0; $i < $cnt; $i++) {
                $info[$m[1][$i]]['TCN'][] = $m[2][$i];
                $info[$m[1][$i]]['PAX'][] = $m[3][$i];
            }
            //$this->http->Log('[E-ticket]' . var_export($info, true));
        }

        $textRecLocs = strstr($textPDF, 'Airline Booking Reference');

        if (preg_match_all("#^\s*([A-Z\d]{2})\s+\(.+?\)[\s:]+([A-Z\d]+)#m", $textRecLocs, $m)) {
            $cnt = count($m[1]);

            for ($i = 0; $i < $cnt; $i++) {
                $rl[$m[1][$i]][] = $m[2][$i];
            }
            //$this->http->Log('[RecLocs]' . var_export($rl, true));
        }

        $textInfo = '[BlockSegments]' . $this->findСutSection($textPDF, 'Please see below your Electronic Ticket and Itinerary', ['Ticket details', 'General Information', 'Ecological information']);

        $nodes = $this->splitter("#^\s*\w+\s+(\d+\s+\w+\s+\d{4})#m", $textInfo);
        $airs = [];

        foreach ($nodes as $node) {
            if (preg_match("#^\d+\s+\w+\s+\d{4}\s+.+?([A-Z\d]{2})\s*\d+#", $node, $m)) {
                $airs[$m[1]][] = $node;
            } else {
                $airs['UKN'][] = $node;
            }
        }
        //$this->http->Log('[Segments]' . var_export($airs, true));

        $its = [];

        foreach ($airs as $airline => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['TripNumber'] = $tripNum;

            if (isset($rl[$airline])) {
                $it['RecordLocator'] = implode('-', $rl[$airline]);
            } else {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $it['ReservationDate'] = $resDate;

            if (isset($info[$airline])) {
                $it['TicketNumbers'] = $info[$airline]['TCN'];
                $it['Passengers'] = $info[$airline]['PAX'];
            } elseif (preg_match_all("#(?:Piece|confirm).*?for\s+(.+)#", implode("\n", $roots), $m)) {
                $it['Passengers'] = array_unique($m[1]);
            } else {
                $it['Passengers'][] = $traveller;
            }
            $it['Status'] = $this->re("#Booking status\s+(.+)#", implode("\n", $roots));

            foreach ($nodes as $root) {
                $seg = [];
                $date = null;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (preg_match("#^(\d+\s+\w+\s+\d{4})\s+.+?([A-Z\d]{2})\s*(\d+)#", $root, $m)) {
                    $date = strtotime($m[1]);

                    if (!$date) {
                        $this->date = $date;
                    }
                    $seg['AirlineName'] = $m[2];
                    $seg['FlightNumber'] = $m[3];
                }

                if (preg_match("#Departure\s+(\d+.+?\d+:\d+)\n(.+)\n(?:\s*(Terminal.+)\n)?\s*#", $root, $m)) {
                    $seg['DepName'] = $m[2];
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['DepartureTerminal'] = $m[3];
                    }
                }

                if (preg_match("#Arrival\s+(\d+.+?\d+:\d+)\n(.+)\n(?:\s*(Terminal.+)\n)?\s*#", $root, $m)) {
                    $seg['ArrName'] = $m[2];
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['ArrivalTerminal'] = $m[3];
                    }
                }

                if (preg_match("#Duration\s+(\d+:\d+)\s*(?:\((.+)\))?#", $root, $m)) {
                    $seg['Duration'] = $m[1];
                    $seg['Stops'] = stripos($m[2], 'no') !== false ? 0 : $m[2];
                }
                $seg['Cabin'] = $this->re("#Class\s+(.+)#", $root);
                $seg['Aircraft'] = $this->re("#Equipment\s+(.+)#", $root);

                if (preg_match_all("#^(\d+[A-Z])\s+#m", $root, $m)) {
                    $seg['Seats'] = implode(',', $m[1]);
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d+\s+\w+)\s+(\d+:\d+)\s*$#',
        ];
        $out = [
            '$1 ' . $year . '$2',
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
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
