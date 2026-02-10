<?php

namespace AwardWallet\Engine\astana\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

// TODO: rewrote to objects

class ItReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "astana/it-11107890.eml, astana/it-5502357.eml, astana/it-5528776.eml";

    public $reBody = [
        'ru' => ['www.airastana.com', 'Маршрутная квитанция'],
    ];
    public $reSubject = [
        'Ваш электронный билет',
        'Әуебилет/ Авиабилет/ Ticket',
    ];
    public $lang = '';
    public $pdf;
    public $dateRelative = 0;
    public $text;
    public static $dict = [
        'ru' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parseEmail($its, $textPdf);
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ItReceiptPDF' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airastana.com') !== false || stripos($from, '@info.airastana.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

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

    protected function parseSegments(&$it, $text): void
    {
        $patterns = [
            'date' => '\d{2}[[:alpha:]]{3}', // 28Sep
        ];

        /*
            KC 898    P    28Sep 1355    28Sep 2015    2PC    PFFO
        */
        $patterns['segTd3'] = "/^[ ]*(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z]) (?<FlightNumber>\d+)"
            . "[ ]+(?<BookingClass>[A-Z]{1,2})[ ]+(?<dateDep>{$patterns['date']}) (?<hrsDep>\d{2})(?<minDep>\d{2})"
            . "[ ]+(?:(?<dateArr>{$patterns['date']}) )?(?<hrsArr>\d{2})(?<minArr>\d{2})(?:[ ]+(?<Status>OK))?(?: |$)/u"
        ;

        $segments = [];
        $statuses = [];

        $tablePos = [0];

        if (preg_match("/^(.+ )TO \/ ҚАЙДА \/ КУДА /m", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+) FLIGHT /m", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (count($tablePos) !== 3) {
            $this->logger->debug('Wrong flight segments!');

            return;
        }

        $segRows = $this->splitText($text, "/(^.+ {$patterns['date']} \d{4}[ ]+(?:{$patterns['date']} )?\d{4}(?: .+|$))/m", true);

        foreach ($segRows as $sText) {
            $seg = [];

            $table = $this->splitCols($sText, $tablePos);

            if (preg_match("/^\s*(?<name>[\s\S]{3,}?)[\s,]+TERMINAL\s*:\s*(?<terminal>[\s\S]+?)\s*$/i", $table[0], $m)) {
                $seg['DepName'] = preg_replace('/\s+/', ' ', $m['name']);
                $seg['DepartureTerminal'] = preg_replace('/\s+/', ' ', $m['terminal']);
            } else {
                $seg['DepName'] = preg_replace('/\s+/', ' ', trim($table[0]));
            }

            if (preg_match("/^\s*(?<name>[\s\S]{3,}?)[\s,]+TERMINAL\s*:\s*(?<terminal>[\s\S]+?)\s*$/i", $table[1], $m)) {
                $seg['ArrName'] = preg_replace('/\s+/', ' ', $m['name']);
                $seg['ArrivalTerminal'] = preg_replace('/\s+/', ' ', $m['terminal']);
            } else {
                $seg['ArrName'] = preg_replace('/\s+/', ' ', trim($table[1]));
            }

            if (preg_match($patterns['segTd3'], $table[2], $m)) {
                $seg['AirlineName'] = $m['AirlineName'];
                $seg['FlightNumber'] = $m['FlightNumber'];
            }

            $dateDep = $dateArr = 0;

            if ($this->dateRelative) {
                $dateDep = EmailDateHelper::parseDateRelative($this->normalizeDate($m['dateDep']), $this->dateRelative, true, '%D% %Y%');
            }

            if (empty($m['dateArr'])) {
                $dateArr = $dateDep;
            } elseif ($this->dateRelative) {
                $dateArr = EmailDateHelper::parseDateRelative($this->normalizeDate($m['dateArr']), $this->dateRelative, true, '%D% %Y%');
            }

            $seg['DepDate'] = strtotime($m['hrsDep'] . ':' . $m['minDep'], $dateDep);
            $seg['ArrDate'] = strtotime($m['hrsArr'] . ':' . $m['minArr'], $dateArr);
            $seg['BookingClass'] = $m['BookingClass'];

            if (!empty($m['Status'])) {
                $statuses[] = $m['Status'];
            }
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            $segments[] = $seg;
        }

        $it['TripSegments'] = $segments;

        if (count(array_unique($statuses)) === 1) {
            $it['Status'] = $statuses[0];
        }
    }

    private function parseEmail(array &$its, $text): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]]+(?: [[:alpha:]]+)*[ ]*\/[ ]*(?:[[:alpha:]]+ )*?[[:alpha:]]+', // Parrish/Cody
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $itineraries = $this->splitText($text, "/(?:^|.+ )BIN\/БИН:(?: .+|$)/m");

        foreach ($itineraries as $itText) {
            $it = ['Kind' => 'T', 'TripSegments' => []];

            if (preg_match("/^[ ]*PASSENGER NAME\/ЖОЛАУШЫНЫҢ[ ]{1,45}(\S.*?\S)(?:[ ]+ISSUED BY|[ ]{2}|$)/m", $itText, $m)
                && preg_match("/^({$patterns['travellerName']})(?:\s+(?i)(?:MISS|MRS|MR|MS))?(?:\s*\(\s*[[:alpha:]]+\s*\))?$/u", $m[1], $m2)
            ) {
                $it['Passengers'] = [$m2[1]];
            }

            if (preg_match("/^[ ]*FREQUENT FLYER\/ҚАТЫСУШЫ НӨМIРI\/[ ]{1,45}(\S.*?\S)(?:[ ]+PHONE|[ ]{2}|$)/m", $itText, $m)
                && preg_match("/^\d[\d ]{3,}\d$/", $m[1])
            ) {
                $it['AccountNumbers'] = [str_replace(' ', '', $m[1])];
            }

            if (preg_match("/^[ ]*TICKET NUMBER\/БИЛЕТ НӨМIРI\/НОМЕР БИЛЕТА:[ ]{1,45}(\S.*?\S)(?:[ ]+БЕРIЛГЕН КҮНI|[ ]{2}|$)/m", $itText, $m)
                && preg_match("/^{$patterns['eTicket']}$/", $m[1])
            ) {
                $it['TicketNumbers'] = [str_replace(' ', '', $m[1])];
            }

            if (preg_match("/^[ ]*BOOKING\/ТАПСЫРЫС НӨМIРI\/НОМЕР БРОНИ:[ ]{1,45}(\S.*?\S)(?:[ ]+ДАТА ОФОРМЛЕНИЯ|[ ]{2}|$)/m", $itText, $m)
                && preg_match("/^[A-Z\d]{5,}$/", $m[1])
            ) {
                $it['RecordLocator'] = $m[1];
            }

            if (preg_match("/^.+ ISSUE DATE\/[ ]+(\S.*?\S)$/m", $itText, $m)
                && ($issueDate = strtotime($this->normalizeDate($m[1])))
            ) {
                $it['ReservationDate'] = $issueDate;
                $this->dateRelative = $issueDate;
            }

            $segmentsText = $this->findСutSection($itText, $this->t('DEPARTURE'), $this->t('RECEIPT DETAILS'));
            $this->parseSegments($it, $segmentsText);

            if (preg_match("/^[ ]*(?:TOTAL\/БАРЛЫҒЫ\/ИТОГО:|TOTAL INCLUDING VAT \/ БАРЛЫҒЫ ҚҚС ҚОСА\/)[ ]{1,45}(\S.*?\S)$/m", $itText, $m)
                && preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $m[1], $matches) // USD 324.70
            ) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $it['Currency'] = $matches['currency'];
                $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
            }

            $its[] = $it;
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{2})\s*([[:alpha:]]+)$/u', $text, $m)) {
            // 07NOV
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{2})\s*([[:alpha:]]+)\s*(\d{4})$/u', $text, $m)) {
            // 07 NOV 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{2})\s*([[:alpha:]]+)\s*(\d{2})$/u', $text, $m)) {
            // 07 NOV 22
            $day = $m[1];
            $month = $m[2];
            $year = '20' . $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function assignLang(?string $text): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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
