<?php

namespace AwardWallet\Engine\china\Email;

class ETReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "china/it-10854324.eml, china/it-10983676.eml, china/it-10983691.eml, china/it-10983718.eml";

    public $reFrom = 'ET-Receipt@email.china-airlines.com';
    public $reSubject = [
        'ET-RECEIPT - TICKET NUMBER',
    ];

    public $reBody = 'CHINA AIRLINES';
    public $reBody2 = [
        'en' => ['FARE/TICKET INFORMATION'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [],
    ];
    protected $emailDate;
    protected $bodyPDF = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');
        $its = [];

        foreach ($pdfs as $pdf) {
            $this->bodyPDF = \PDF::convertToText($parser->getAttachmentBody($pdf));
            //			$this->AssignLang($this->bodyPDF);
            $this->parseEmail($its);
        }

        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');
        $body = '';

        foreach ($pdfs as $pdf) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        if (isset($headers['subject']) && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
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

    private function parseEmail(&$its)
    {
        $text = $this->bodyPDF;
        $it = ['Kind' => 'T'];

        $posEnd = strpos($text, 'ITINERARY');

        if (empty($posEnd)) {
            $this->logger->info("data is not found");

            return [];
        }
        $info = substr($text, 0, $posEnd);

        // RecordLocator
        if (preg_match("#BOOKING\s+REFERENCE:?\s+([A-Z\d]{5,7})(?:\s|$)#", $info, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        // Passengers
        if (preg_match('#(?:\n|^)\s*NAME:?\s+(.+/.+?)\s{2,}#', $info, $m)) {
            $it['Passengers'][] = $m[1];
        }

        // TicketNumbers
        if (preg_match('#TICKET NUMBER:?\s+([\d \-]+)\s{2,}#', $info, $m)) {
            $it['TicketNumbers'][] = trim($m[1]);
        }

        // AccountNumbers
        if (preg_match('#MEMBERSHIP CARD NUMBER:?[ ]+(.+)#', $info, $m)) {
            $it['AccountNumbers'][] = array_filter([trim($m[1])]);
        }

        foreach ($its as $key => $value) {
            if ($value['RecordLocator'] == $it['RecordLocator'] && isset($value['TicketNumbers']) && isset($it['TicketNumbers'])
                    && $value['TicketNumbers'] == $it['TicketNumbers']) {
                return null;
            }
        }
        $posBeginFlight = $posEnd;

        if (!empty($posBeginFlight)) {
            $posEndFlight = strpos($text, 'FARE/TICKET INFORMATION', $posBeginFlight);
        }

        if (empty($posBeginFlight) || empty($posEndFlight)) {
            return [];
        }
        $flightsText = substr($text, $posBeginFlight, $posEndFlight - $posBeginFlight);

        $pos = strpos($text, 'NOTICE :', $posBeginFlight);

        if (empty($pos)) {
            $pos = strpos($text, '注意事項 :', $posBeginFlight);
        }

        if (!empty($pos)) {
            $fare = substr($text, $posEndFlight, $pos - $posEndFlight);
        } else {
            $fare = substr($text, $posEndFlight);
        }

        if (preg_match("#FARE:?\s+([A-Z]{3})\s*(\d[\d,. ]+)\b#", $fare, $m)) {
            $it['BaseFare'] = $this->amount($m[2]);
            $it['Currency'] = $this->currency($m[1]);
        }

        if (preg_match("#(?:TOTAL:?|合計：)\s+([A-Z]{3})\s*(\d[\d,. ]+)\b#", $fare, $m)) {
            $it['TotalCharge'] = $this->amount($m[2]);

            if (empty($it['Currency'])) {
                $it['Currency'] = $this->currency($m[1]);
            }
        }

        if (preg_match("#DATE OF ISSUE:?\s+(\d{1,2}\s*[^\d\s]+\s*\d{2})\b#", $fare, $m)) {
            $this->emailDate = strtotime($this->normalizeDate($m[1]));
            $this->emailDate = strtotime("-1 day", $this->emailDate);
        }

        if (empty($this->emailDate)) {
            $this->logger->info("year not found");

            return null;
        }

        preg_match_all('#DEP:(.+)\n\s*((?:ARR:|[A-Z]{3}\s*-\s*).+)#', $flightsText, $flights);

        foreach ($flights[0] as $key => $flight) {
            $seg = [];

            if (preg_match("#^\s*(?<date>\d+[^\d\s]+/\d+:\d+)\s+(?<al>[A-Z\d]{2})\s*(?<fn>\d{1,5})\s+(?<code>[A-Z]{3})\s*-\s*(?<name>.+?)/(?<term>.+?)\s{2,}(?<class>[A-Z]{1,2})/#", $flights[1][$key], $m)) {
                // FlightNumber
                $seg['FlightNumber'] = $m['fn'];

                // AirlineName
                $seg['AirlineName'] = $m['al'];
                // DepCode
                $seg['DepCode'] = $m['code'];

                // DepName
                $seg['DepName'] = trim($m['name']);

                // DepartureTerminal
                $seg['DepartureTerminal'] = trim($m['term']);

                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($m['date']));

                if ($seg['DepDate'] < $this->emailDate) {
                    $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                }

                // BookingClass
                $seg['BookingClass'] = $m['class'];
            }

            if (preg_match("#^(?:\s*ARR:\s*)?\s*(?:(?<date>\d+[^\d\s]+/\d+:\d+)\s+)?(?<code>[A-Z]{3})\s*-\s*(?<name>.+?)/(?<term>.+?)\s{2,}#", $flights[2][$key], $m)) {
                // ArrCode
                $seg['ArrCode'] = $m['code'];

                // ArrName
                $seg['ArrName'] = trim($m['name']);

                // ArrDate
                if (!empty($m['date'])) {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m['date']));

                    if ($seg['ArrDate'] < $this->emailDate) {
                        $seg['ArrDate'] = strtotime("+1 year", $seg['ArrDate']);
                    }
                } else {
                    $seg['ArrDate'] = MISSING_DATE;
                }

                // ArrivalTerminal
                $seg['ArrivalTerminal'] = trim($m['term']);
            }

            if (preg_match("#" . $seg['AirlineName'] . $seg['FlightNumber'] . "Y" . $flights[1][$key] . "\s+((?:\d+\s*-\s*\d{1,3}[A-Z](?:\s+|$))*)#", $flightsText, $m)) {
                if (preg_match_all("#\b\d+\s*-\s*(\d{1,3}[A-Z])(?:\s+|$)#", $m[1], $mat)) {
                    $seg['Seats'] = $mat[1];
                }
            }

            $it['TripSegments'][] = $seg;
        }
        $its[] = $it;

        return true;
    }

    private function AssignLang($body)
    {
        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $reBodies) {
            foreach ($reBodies as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->emailDate);
        $in = [
            //25 MAY 15
            '#^\s*(\d{1,2})\s*([^\d\s]+)\s*(\d{2})\s*$#i',
            //19SEP/22:10
            '#^\s*(\d{1,2})([^\d\s]+)/(\d{2}:\d{2})\s*$#i',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2 ' . $year . ' $3',
        ];
        $date = preg_replace($in, $out, $date);
        //		if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
        //			$monthNameOriginal = $m[0];
        //			if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
        //				return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
        //			}
        //		}
        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.\s]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
