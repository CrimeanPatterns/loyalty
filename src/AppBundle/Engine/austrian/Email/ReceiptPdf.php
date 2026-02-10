<?php

namespace AwardWallet\Engine\austrian\Email;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "austrian/it-2315285.eml, austrian/it-4102314.eml, austrian/it-4487695.eml, austrian/it-8668151.eml, austrian/it-8730589.eml";

    public $reFrom = "austrian.com";
    public $reFromH = "AUSTRIAN AIRLINES";
    public $detect = [
        ['Passenger Receipt', 'Flight Data'],
        ['Invoice No', 'Flight Data'],
    ];
    public $reBody = [
        'es' => ['Datos del vuelo', 'Fecha'],
        'de' => ['Flugdaten', 'Datum'],
    ];
    public $reSubject = [
        'Passenger Receipt',
    ];
    public $lang = 'en';
    public $year;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [],
        'es' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $body = text($html);
                    $this->AssignLang($body);
                    $its[] = $this->parseEmail($body);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ReceiptPdf' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->detect as $detect) {
                if (stripos($text, $this->reFromH) !== false && strpos($text, $detect[0]) !== false && strpos($text, $detect[1]) !== false) {
                    return $this->AssignLang($text);
                }
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
            $left = strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return substr($inputResult, strlen($searchStart));
    }

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

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                $its[$j]['TicketNumbers'] = array_merge($its[$j]['TicketNumbers'], $its[$i]['TicketNumbers']);
                $its[$j]['TicketNumbers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['TicketNumbers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function parseEmail($textPDF)
    {
        $textInfo = '';

        if (stripos($textPDF, 'Passenger Receipt') !== false) {
            $textInfo = $this->findСutSection($textPDF, 'Passenger Receipt', 'Flight Data');
        } else {
            $textInfo = $this->findСutSection($textPDF, 'Invoice No', 'Flight Data');
        }
        $textFlight = $this->findСutSection($textPDF, 'Flight Data', 'Tickets are not transferable');

        if (empty($textFlight)) {
            $textFlight = $this->findСutSection($textPDF, 'Flight Data', 'Payment Details');
        }

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#Booking code.+?:\s+(.+)#i", $textInfo);
        $it['Currency'] = $this->re("#^[ ]*Invoice [Aa]mount.+?([A-Z]{3})#sm", $textFlight);
        $it['TotalCharge'] = $this->re("#^[ ]*Invoice [Aa]mount.+?[A-Z]{3}\s+([\d,\.]+)#sm", $textFlight);
        $it['BaseFare'] = $this->re("#^\s*Amount.+?[A-Z]{3}\s+([\d,\.]+)#sm", $textFlight);
        $it['Tax'] = $this->re("#Tax.+?[A-Z]{3}\s+([\d,\.]+)#s", $textFlight);

        if (preg_match_all("#Name.+?:\s+(.+)#", $textPDF, $m)) {
            $it['Passengers'] = preg_replace(["/\s*\(infant\)\s*$/", "/ (\/\s*)?(DR )?(MR|MRS|MS|DR)$/", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"], ["", "", "$2 $1"], $m[1]);
        }

        if (preg_match_all("#Ticket number.+?:\s+(.+)#", $textPDF, $m)) {
            $it['TicketNumbers'] = $m[1];
        }

        $nodes = $this->splitter("#^([A-Z\d]{2}\s*\d+\s+\d+\s+\w+\s+\d+)#m", $textFlight);

        foreach ($nodes as $root) {
            $seg = [];
            $date = null;

            if (preg_match("#^([A-Z\d]{2})\s*(\d+)\s+(\d+\s+\w+\s+\d+\b)\s*(.+?\n?.+?)\s+(\d+:\d+)\s+(\d+:\d+)#m", $root, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $date = $this->normalizeDate($m[3]);

                if (preg_match("/^\s*(.+?)\s*\n\s*(.+?)\s*$/", $m[4], $mat)) {
                    $seg['DepName'] = $mat[1];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrName'] = $mat[2];
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                } elseif (preg_match("/^\s*(.+? Intl) +(.+?)\s*$/", $m[4], $mat)) {
                    $seg['DepName'] = $mat[1];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrName'] = $mat[2];
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                } elseif (preg_match("/^.+$/", $m[4])) {
                    if (!isset($cities)) {
                        $routesText = $this->findСutSection($textPDF, 'Routing/', 'Tax');

                        if (preg_match("/\n\d{3}-\d{10}\n.+\n(?<name1>.+)\n[A-Z]{3}\n\d.+\n([A-Z \/]+\n)?(?<name2>.*)/",
                            $routesText, $mat)) {
                            $cities = array_unique(array_filter(explode('-', preg_replace('/\s+/', '', $mat['name1'] . $mat['name2']))));
                        }
                    }

                    if (!empty($cities) && preg_match("/^\s*(" . $this->opt($cities) . "\b.*?) +(" . $this->opt($cities) . "\b.*?)\s*$/", $m[4], $mat)) {
                        $seg['DepName'] = $mat[1];
                        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                        $seg['ArrName'] = $mat[2];
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                }

                $seg['DepDate'] = strtotime($m[5], $date);
                $seg['ArrDate'] = strtotime($m[6], $date);
            }

            if (preg_match("#\s+\d+:\d+\s+\d+:\d+\n([A-Z]{1,2})\s*\(#", $root, $m)) {
                $seg['BookingClass'] = $m[1];
            } elseif (preg_match("#\s+\d+:\d+\s+\d+:\d+\n([[:alpha:]]+)\s+#", $root, $m)) {
                $seg['Cabin'] = $m[1];
            }

            if (preg_match("#operated by[\s:]+(.+)#", $root, $m)) {
                $seg['Operator'] = $m[1];
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\s+(\w+)\s+(\d{2})$#',
            // '#^(\d+)\s+(\w+)\s+(\d{1})$#'
        ];
        $out = [
            '$1 $2 20$3',
            //'$1 $2 20$30',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
