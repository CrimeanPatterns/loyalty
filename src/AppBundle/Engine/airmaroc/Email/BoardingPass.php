<?php

namespace AwardWallet\Engine\airmaroc\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "airmaroc/it-6875630.eml, airmaroc/it-7122701.eml, airmaroc/it-7129826.eml, airmaroc/it-7192862.eml";

    public $reFrom = [
        "noreply@amadeus.com", "noreply@royalairmaroc.com", ];
    public $reBody = [
        'en' => ['Thank you for using Royal Air Maroc online check-in service', 'Please find enclosed your boarding pass for the flight with the following details'],
    ];
    public $reSubject = [
        'Boarding Pass Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    protected $its;
    protected $flightPdf = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);
        $pdfs = $parser->searchAttachmentByName('Boarding\s*Pass\.pdf');

        if (count($pdfs) > 0) {
            $bodyPDF = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            if (preg_match_all("#Flight\s+Seat#", $bodyPDF, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $finded) {
                    $this->flightPdf[] = substr($bodyPDF, $finded[1], 500);
                }
            }
        }

        $this->its = $this->parseEmail();

        $class = explode('\\', __CLASS__);
        return [
            'parsedData' => ['Itineraries' => $this->its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        if (isset($headers['from']) && isset($this->reFrom)) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $find = true;
                }
            }
        }

        if (!$find) {
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
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
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

    public function findSeats($flightNumber)
    {
        $result = [];

        foreach ($this->flightPdf as $flight) {
            if (preg_match("#(" . $flightNumber . "\s*(\d{2}[A-Z])\s|\n\s*(\d{2}[A-Z])(.*\n){0,3}" . $flightNumber . ")#", $flight, $m)) {
                if (isset($m[2])) {
                    $result[] = $m[2];
                }

                if (isset($m[4])) {
                    $result[] = $m[4];
                }
            }
        }

        return implode(",", $result);
    }

    public function findTerminal($flightNumber)
    {
        foreach ($this->flightPdf as $flight) {
            if (preg_match("#" . $flightNumber . "[\s\S]+terminal\s(.+)#i", $flight, $m)) {
                return $m[1];
            }
        }

        return '';
    }

    private function parseEmail()
    {
        $this->its = [];
        $xpath = "//text()[contains(.,'Flight Arrival')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $seg = [];
            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space(.)]", $root));

            if (preg_match("#Flight[:\s]+([A-Z\d]{2})\s*(\d+)[\s\-]+(.+?)\s+\(([A-Z]{3})\)[\s\-]+(.+?)\s+\(([A-Z]{3})\)[\s\-]+(.+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['DepName'] = $m[3];
                $seg['DepCode'] = $m[4];
                $seg['ArrName'] = $m[5];
                $seg['ArrCode'] = $m[6];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[7]));
                $seg['ArrDate'] = MISSING_DATE;
                $seg['Seats'] = $this->findSeats($seg['FlightNumber']);
                $seg['DepartureTerminal'] = $this->findTerminal($seg['FlightNumber']);
            }

            if (preg_match("#Flight Arrival[:\s]+(\d+:\d+)#", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $seg['DepDate']);
            }

            if (preg_match_all("#(.+)[\s\-]+Ticket Number:\s+(.+)#", $node, $m)) {
                $it['Passengers'] = $m[1];
                $it['TicketNumbers'] = $m[2];
            }
            $it['TripSegments'][] = $seg;
            $this->its[] = $it;
        }

        return $this->its;
    }

    private function normalizeDate($date)
    {
        $in = [
            //10-Jun-2017 - 20:40
            '#^\s*(\d+)[\-\s]+(\w+)[\-\s]+(\d+)[\-\s]+(\d+:\d+)\s*$#',
        ];
        $out = [
            '$1 $2 $3 $4',
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
            foreach ($this->reBody as $lang => $reBodies) {
                foreach ($reBodies as $reBody) {
                    if (stripos($body, $reBody) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
