<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryPdf2 extends \TAccountChecker
{
    public $mailFiles = "asia/it-11099848.eml, asia/it-1973321.eml, asia/it-37533054.eml, asia/it-8636538.eml";

    public $reFrom = ["@cathaypacific.com"];
    public $reBody = [
        'en' => ['SERVICE', 'BOOKING REF'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'DEPART' => 'DEPART',
            'ARRIVE' => ['ARRIVE', 'ARRIVAL'],
        ],
    ];
    private $keywordProv = 'CATHAY PACIFIC AIRWAYS LTD';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        if ($this->parseEmailPdf($text, $email) === false) {
                            break;
                        }
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->detectBody($text)
                && $this->assignLang($text)
            ) {
                return true;
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

    public function detectEmailByHeaders(array $headers)
    {
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $info = strstr($textPDF, $this->t('SERVICE'), true);

        if (empty($info)) {
            $this->logger->debug('other format');

            return true;
        }

        $r = $email->add()->flight();

        // accountNumbers
        if (preg_match_all("#FREQUENT FLYER[ ]+([A-Z\d]{7,})#m", $textPDF, $m)) {
            $r->program()
                ->accounts(array_unique($m[1]), false);
        }

        // passengers
        if (preg_match_all("#[ ]{2,}(?:TO\/|WU\/)?((?:\w+\/)?\w+( ?\w+)*) (?:MR|MRS|MS|MISS)$#m", $info, $m)) {
            $r->general()
                ->travellers($m[1], true);
        } elseif (preg_match_all("#(.*?/.*?)\s{2,}TICKET:#", $textPDF, $m)) {
            $r->general()
                ->travellers($m[1], true);
        } elseif (preg_match_all("#CONFIRMED FOR\s+(.+)#", $textPDF, $m)) {
            $r->general()
                ->travellers(array_unique($m[1]), true);
        }

        // ticketNumbers
        if (preg_match_all("#TICKET:\s*[A-Z/]+\s+(.+)#", $textPDF, $m)) {
            $r->issued()
                ->tickets(array_map(function ($s) {
                    return preg_replace("#\s+#", " ", $s);
                }, $m[1]), false);
        }

        // confNo , reservationDate
        $r->general()
            ->confirmation($this->re("#{$this->t('BOOKING REF')}[ ]+([A-Z\d]{5,})#", $info), $this->t('BOOKING REF'))
            ->date($this->normalizeDate($this->re("#[ ]{3,}{$this->t('DATE')}[ ]+(.+)#", $info)));

        // sums
        $sumBlock = $this->re("#{$this->t('INVOICE TOTAL')}\s+(.+)\s+{$this->t('PAYMENT:')}#s", $textPDF);

        if (!empty($sumBlock)) {
            $total = $this->getTotalCurrency(trim($sumBlock));
            $r->price()
                ->total($total['Total']);

            if (!empty($total['Currency'])) {
                $r->price()
                    ->currency($total['Currency']);
            }
        }
        $sumBlock = $this->re("#{$this->t('AIR FARE:')}\s+(.+)\s+{$this->t('TAX:')}#s", $textPDF);

        if (!empty($sumBlock)) {
            $total = $this->getTotalCurrency(trim($sumBlock));
            $r->price()
                ->cost($total['Total']);

            if (!empty($total['Currency'])) {
                $r->price()
                    ->currency($total['Currency']);
            }
        }
        $sumBlock = $this->re("#{$this->t('TAX:')}\s+(.+)\s+{$this->t('TOTAL:')}#s", $textPDF);

        if (!empty($sumBlock)) {
            $total = $this->getTotalCurrency(trim($sumBlock));
            $r->price()
                ->tax($total['Total']);

            if (!empty($total['Currency'])) {
                $r->price()
                    ->currency($total['Currency']);
            }
        }

        $segments = $this->splitter("#(\n.*?\s*-\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}\n)#", $textPDF);

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $table = $this->re("#.*? - \w{2} \d+\n(.*?)DURATION#ms", $segment);
            $pos = $this->colsPos($this->re("#(.+\n.+)#", $table));
            $table = $this->splitCols($table, $pos);

            if (count($table) < 4) {
                $this->http->log("incorrect table parse");

                return false;
            }

            $conf = $this->re("#^REF\.[ ]*(.+)#", $table[0]);

            if (!empty($conf)) {
                if (preg_match("#^[A-Z\d]{5,}$#", trim($conf))) {
                    $s->airline()
                        ->confirmation(trim($conf));
                }
                $date = $this->normalizeDate($this->re("#\n\s*(\S.+)#", $table[0]));
            } else {
                $date = $this->normalizeDate($this->re("#^\s*(\S.+)#", $table[0]));
            }

            if (!empty($date)) {
                $this->date = $date;
            }

            $s->airline()
                ->number($this->re("# \- (?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) (\d+)\n#", $segment))
                ->name($this->re("# \- ([A-Z\d][A-Z]|[A-Z][A-Z\d]) \d+\n#", $segment))
                ->operator($this->re("#CABIN CREW:\s+(\w{2})\s#", $segment), false, true);

            $s->departure()
                ->noCode()
                ->name(trim(str_replace("\n", " ", $this->re("#(.*?)(?:TERMINAL|$)#s", $table[1]))))
                ->terminal($this->re("#TERMINAL\s+(.+)#", $table[1]), false, true)
                ->date(strtotime($this->normalizeTime($this->re("#^\s*(\d{4})\b#", $table[3])), $date));

            $s->arrival()
                ->noCode()
                ->name(trim(str_replace("\n", " ", $this->re("#(.*?)(?:TERMINAL|$)#s", $table[2]))))
                ->terminal($this->re("#TERMINAL\s+(.+)#", $table[2]), false, true);

            if (isset($table[4])) {
                $time = $this->normalizeTime($this->re("#^\s*(\d{4})\b#", $table[4]));

                if (preg_match("#^\s*\d{4}\s+(.+)#", $table[4], $m)) {
                    $date = $this->normalizeDate(trim($m[1]));
                }
            } else {
                $time = $this->normalizeTime($this->re("#^\s*\d{4}\s+(\d{4})#", $table[3]));

                if (preg_match("#^\s*\d{4}\s+\d{4}\s+(.+)#", $table[3], $m)) {
                    $date = $this->normalizeDate(trim($m[1]));
                }
            }
            $s->arrival()
                ->date(strtotime($time, $date));

            $s->extra()
                ->aircraft($this->re("#EQUIPMENT:\s+(.+)#", $segment), false, true)
                ->cabin($this->re("#RESERVATION CONFIRMED\s+-\s+\w\s+(.+)#", $segment))
                ->bookingCode($this->re("#RESERVATION CONFIRMED\s+-\s+(\w)\s+#", $segment))
                ->duration($this->re("#DURATION\s+(.+)#", $segment), false, null)
                ->meal($this->re("#ON BOARD:\s+(.+)#", $segment), false, true);

            if (preg_match_all("#SEAT (\d+\w)#", $segment, $m)) {
                $s->extra()->seats($m[1]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+)(\d{2})$#", //02SEPTEMBER17
            "#^([^\s\d]+) (\d+)([^\s\d]+)$#", //FRI 22DEC
            "#^(\d+)([^\s\d]+)$#", //22DEC
        ];
        $out = [
            "$1 $2 20$3",
            "$2 $3 $year",
            "$1 $2 $year",
        ];
        $outWeek = [
            '',
            '$1',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function normalizeTime($date)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d{1,2})(\d{2})$#", //1245
        ];
        $out = [
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
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

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['DEPART'], $words['ARRIVE'])) {
                if ($this->strposArray($body, $words['DEPART']) && $this->strposArray($body, $words['ARRIVE'])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function strposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        } elseif (preg_match("#\b(?<t>\d[\.\d\, ]*?\d*\b)#", $node, $m)) {
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
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
