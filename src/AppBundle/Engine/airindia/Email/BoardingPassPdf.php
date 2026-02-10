<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "airindia/it-11712188.eml, airindia/it-11817275.eml, airindia/it-9871997.eml, airindia/it-9901046.eml, airindia/it-9916291.eml";

    public $reFrom = "airindia.in";
    public $reBody = [
        'en'  => ['BOARDING PASS', 'Web Check-in Boarding pass'],
        'en2' => ['BOARDING PASS', 'Mobile Check-in Boarding Pass'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= "\n" . $textPdf;
            }
        }

        if (empty($textPdfFull)) {
            return null;
        }

        $its = $this->parseEmail($textPdfFull, $parser);
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (stripos($textPdf, 'Air India') === false && stripos($textPdf, 'airindia') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                            && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                        ) {
                            $new = [];

                            if (isset($tsJ['Seats'])) {
                                $new = array_merge($new, (array) $tsJ['Seats']);
                            }

                            if (isset($tsI['Seats'])) {
                                $new = array_merge($new, (array) $tsI['Seats']);
                            }
                            $its[$j]['TripSegments'][$flJ]['Seats'] = array_values(array_filter(array_unique($new)));
                            $its[$i]['TripSegments'][$flI]['Seats'] = array_values(array_filter(array_unique($new)));
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize',
                    array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = [];

                    if (isset($its[$j]['Passengers'])) {
                        $new = array_merge($new, $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new = array_merge($new, $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = [];

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new = array_merge($new, $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new = array_merge($new, $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = [];

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new = array_merge($new, $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new = array_merge($new, $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['TicketNumbers'] = $new;
                }

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

    private function parseEmail(string $textPDF, \PlancakeEmailParser $parser): ?array
    {
        $its = [];

        if (preg_match_all("#([^\n]+\s+BOARDING PASS\s+Name.+?E-Ticket(?: Number)?.+?(?-i)[A-Z\d]{6,})#is", $textPDF, $m)) {
            $nodes = $m[1];

            foreach ($nodes as $text) {
                if (strpos($text, 'PNR') === false && strpos($text, $this->t('Flight Number')) === false) {
                    continue;
                }
                /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
                $it = ['Kind' => 'T', 'TripSegments' => []];

                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $seg['Cabin'] = trim(str_ireplace('Class', '', $this->re("#^\s*([^\n]+?)\s+BOARDING PASS#i", $text)));

                $table = array_values(array_filter(array_map("trim",
                    $this->SplitCols($this->re("#([^\n]*{$this->opt($this->t('Name'))}\s+.+?)\s+From\s+#si", $text), $this->colsPos($text)))));

                if (count($table) !== 4) {
                    $this->http->Log('incorrect parse a table 1');
                    $this->http->Log(var_export($table, true));

                    return null;
                }
                $it['Passengers'][] = preg_replace("#\s+#", ' ', $this->re("#{$this->opt($this->t('Name'))}\s+(.+)#is", $table[0]));
                $seg['Seats'][] = $this->re("#Seat\s+(\d+\w)#i", $table[1]);
                $ffNo = $this->re("#FF\s+No.*\s+(.+)#", $table[2]);

                if ($ffNo) {
                    $it['AccountNumbers'][] = $ffNo;
                }

                $table = array_values(array_filter(array_map("trim",
                    $this->SplitCols($this->re("#([^\n]*From\s+.+?)\s+Gate\s+#si", $text), $this->colsPos($text)))));

                if (count($table) !== 6) {
                    $this->http->Log('incorrect parse a table 2');
                    $this->http->Log(var_export($table, true));

                    return null;
                }
                $seg['DepName'] = $this->re("#From\s+(.+)#i", $table[0]);
                $seg['ArrName'] = $this->re("#To\s+(.+)#i", $table[1]);
                $seg['FlightNumber'] = $this->re("#Flight Number\s+(?-i)(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#i", $table[3]);
                $seg['AirlineName'] = $this->re("#Flight Number\s+(?-i)([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+#i", $table[3]);
                $date = $this->normalizeDate($this->re("#Date\s+(.+)#i", $table[4]));
                $date = EmailDateHelper::calculateDateRelative($date, $this, $parser);
                $time = $this->re("#Departure Time\s+(.+)#i", $table[5]);
                $seg['DepDate'] = strtotime($time, $date);
                $seg['ArrDate'] = MISSING_DATE;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                $table = array_values(array_filter(array_map("trim",
                    $this->SplitCols($this->re("#([^\n]*Gate\s+.+)#is", $text), $this->colsPos($text)))));

                if (count($table) !== 6) {
                    $this->http->Log('incorrect parse a table 3');
                    $this->http->Log(var_export($table, true));

                    return null;
                }
                $seg['BookingClass'] = $this->re("#Class\s+\b([A-Z]{1,2})\b#i", $table[2]);
                $it['RecordLocator'] = $this->re("#PNR\s+(?-i)([A-Z\d]{5,})#i", $table[3]);
                $it['TicketNumbers'][] = $this->re("#E-Ticket(?: Number)?\s+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2}|[-A-Z\d]{5,})[ ]*$#im", $table[5]);

                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
            $its = array_values($this->mergeItineraries($its));
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            //03MAR
            '#^\s*(\d+)\s*(\w+)\s*$#',
        ];
        $out = [
            '$1 $2',
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

    private function assignLang($body): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{3,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
