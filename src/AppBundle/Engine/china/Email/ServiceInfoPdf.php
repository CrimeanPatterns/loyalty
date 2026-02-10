<?php

namespace AwardWallet\Engine\china\Email;

class ServiceInfoPdf extends \TAccountChecker
{
    public $mailFiles = "china/it-10983605.eml, china/it-10984021.eml, china/it-10984039.eml";

    public $reFrom = 'E-Itinerary@china-airlines.com';
    public $reSubject = [
        'China Airlines Passenger Booking Service Information',
    ];

    public $reBody = 'CHINA AIRLINES';
    public $reBody2 = [
        'en' => ['Itinerary Reference'],
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

        foreach ($pdfs as $pdf) {
            $this->bodyPDF = \PDF::convertToText($parser->getAttachmentBody($pdf));
            //			$this->AssignLang($this->bodyPDF);
            $its[] = $this->parseEmail();
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

    private function parseEmail()
    {
        $text = $this->bodyPDF;
        $it = ['Kind' => 'T'];

        $posBegin = strpos($text, 'Record Locator');

        if (!empty($posBegin)) {
            $posEnd = strpos($text, 'Itinerary Reference', $posBegin);
        }

        if (empty($posBegin) || empty($posEnd)) {
            $this->logger->info("data is not found");

            return [];
        }
        $info = substr($text, $posBegin, $posEnd - $posBegin);

        // RecordLocator
        if (preg_match("#Record Locator\s+([A-Z\d]{5,7})\s+#", $info, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        // Passengers
        if (preg_match_all('#^.{10,}  \d+\s*\.\s*(.+/.+)$#m', $info, $m)) {
            $it['Passengers'] = $m[1];
        }

        $posBeginFlight = strpos($text, 'AIRPORT', $posEnd);

        if (!empty($posBeginFlight)) {
            $posEndFlight = strpos($text, 'Notice', $posBeginFlight);
        }

        if (empty($posBeginFlight) || empty($posEndFlight)) {
            return [];
        }
        $flightsText = substr($text, $posBeginFlight, $posEndFlight - $posBeginFlight);

        if (preg_match("#Sent:\s*(\d{1,2}[\D\S]+\s*\d{4})\b#", substr($text, $posEnd, $posBeginFlight - $posEnd), $m)) {
            $this->emailDate = strtotime($this->normalizeDate($m[1]));
            $this->emailDate = strtotime("-1 day", $this->emailDate);
        }

        if (empty($this->emailDate)) {
            $this->logger->info("year not found");

            return null;
        }

        preg_match_all('#\n\s*(\d+[^\d\s]+)\s+([A-Z\d]{2})\s*(\d{1,5})(.+)(?:.*\n){1,2}\s*(\d+[^\d\s]+)\s+(.+)#', $flightsText, $flights);

        foreach ($flights[0] as $key => $flight) {
            $seg = [];
            // FlightNumber
            $seg['FlightNumber'] = $flights[3][$key];

            // AirlineName
            $seg['AirlineName'] = $flights[2][$key];

            if (preg_match("#([A-Z]{3})\s*-\s*(.+)\s+(\d{2})(\d{2})\s+([A-Z]{1,2})\s+.+(\d+)\s*$#", $flights[4][$key], $m)) {
                // DepCode
                $seg['DepCode'] = $m[1];

                // DepName
                $seg['DepName'] = trim($m[2]);

                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($flights[1][$key] . ' ' . $m[3] . ':' . $m[4]));

                if ($seg['DepDate'] < $this->emailDate) {
                    $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                }

                // BookingClass
                $seg['BookingClass'] = $m[5];

                // Stops
                $seg['Stops'] = $m[6];
            }

            if (preg_match("#([A-Z]{3})\s*-\s*(.+)\s+(\d{2})(\d{2})(\s+|$)#", $flights[6][$key], $m)) {
                // ArrCode
                $seg['ArrCode'] = $m[1];

                // ArrName
                $seg['ArrName'] = trim($m[2]);

                // ArrDate
                $seg['ArrDate'] = strtotime($this->normalizeDate($flights[5][$key] . ' ' . $m[3] . ':' . $m[4]));

                if ($seg['ArrDate'] < $this->emailDate) {
                    $seg['ArrDate'] = strtotime("+1 year", $seg['ArrDate']);
                }
            }

            if (preg_match("#" . $seg['AirlineName'] . $seg['FlightNumber'] . "Y" . $flights[1][$key] . "\s+((?:\d+\s*-\s*\d{1,3}[A-Z](?:\s+|$))*)#", $flightsText, $m)) {
                if (preg_match_all("#\b\d+\s*-\s*(\d{1,3}[A-Z])(?:\s+|$)#", $m[1], $mat)) {
                    $seg['Seats'] = $mat[1];
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
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
            //25MAY 2015
            '#^\s*(\d{1,2})([^\d\s]+)\s*(\d{4})\s*$#i',
            //25MAY 20:15
            '#^\s*(\d{1,2})([^\d\s]+)\s*(\d{2}:\d{2})\s*$#i',
        ];
        $out = [
            '$1 $2 $3',
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
}
