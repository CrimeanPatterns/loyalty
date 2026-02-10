<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-16820949.eml";

    public $reFrom = ["aircanada.com"];
    public $reBody = [
        'en'  => ['Booking Details', 'Passengers'],
        'en2' => ['Booking Confirmation', 'Passengers'],
    ];
    public $lang = '';
//    public $pdfNamePattern = ".*Booking Details.*pdf";
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Depart'                         => ['Depart', 'Return'],
            'Travel booked/ticket issued on' => ['Travel booked/ticket issued on', 'Date of issue'],
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }

                    if (!$this->parseEmail($text, $email)) {
                        return null;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Air Canada') !== false) && (stripos($text, 'aircanada') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && strpos($headers['from'], 'aircanada.com') !== false) {
            return true;
        }

        if (isset($headers['subject']) && strpos($headers['subject'], 'Air Canada') !== false) {
            return true;
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

    private function parseEmail($textPDF, Email $email)
    {
        //to delete garbage symbols
        $textPDF = str_replace(['', '', '', '', '', '', '', '', '', '', ''], ' ', $textPDF);
        $textPDF = preg_replace("#\n( *https:\/\/www.aircanada.com.+\n.+Booking Details)#", '', $textPDF);

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#{$this->opt($this->t('Booking Reference'))}[ :]+([A-Z\d]{5,})#", $textPDF))
            ->date($this->normalizeDate($this->re("#{$this->opt($this->t('Travel booked/ticket issued on'))}[ :]+(.+)#",
                $textPDF)));

        if ($status = $this->re("#{$this->opt($this->t('YOUR BOOKING IS'))} +(.+)#i", $textPDF)) {
            $f->general()
                ->status($status);
        }

        $infoBlock = trim($this->findСutSection($textPDF, $this->t('Passengers'), $this->t('Depart')));

        if (empty($infoBlock)) {
            $this->logger->debug('other format infoBlock');

            return false;
        }

        if (preg_match_all("#^ *(.+?)\s+(?:{$this->t('Travel Options')}|{$this->t('Seats')})#", $infoBlock, $m)) {
            $f->general()
                ->travellers($m[1]);
        }
        $flSeats = [];

        if (preg_match_all("#((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+) +(-|\d[A-z])#", $infoBlock, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $flSeats[$v[1]][] = $v[2];
            }
        }
        $flSeats = array_filter($flSeats, function ($s) {
            return preg_match("#^\d[A-z]$#", $s[0]);
        });

        if (preg_match_all("#{$this->t('Ticket Number')}[^\n]+\n(?:.+\n)? *([\d\-]{7,})#", $infoBlock, $m)) {
            $f->issued()
                ->tickets($m[1], false);
        }
        $paymentBlock = $this->findСutSection($textPDF, $this->t('Purchase summary'), $this->t('Baggage allowance'));

        $sum = $this->nice($this->re("#{$this->t('GRAND TOTAL')}\s+(.+?)\n\n#s", $paymentBlock));

        if (empty($sum)) {
            $sum = $this->nice($this->re("#{$this->t('GRAND TOTAL')}\s+(.+?)(?:Canadian dollars|Goods and Services Tax))#s",
                $paymentBlock));
        }
        $tot = $this->getTotalCurrency($sum);

        if (!empty($tot['Total'])) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);

            if (stripos($paymentBlock, 'Canadian dollars') !== false) {
                $f->price()
                    ->currency('CAD');
            }
        }

        $mainBlock = strstr($textPDF, $this->t('Purchase summary'), true);

        if (empty($mainBlock)) {
            $mainBlock = strstr($textPDF, 'Baggage allowance', true);
        }

        $arr = $this->splitter("#\n( *{$this->opt($this->t('Depart'))})#", $mainBlock);

        foreach ($arr as $a) {
            $date = $this->normalizeDate($this->re("#\n *(.+? \d{4})\s{4,}#", $a));

            $segs = $this->splitter("#([^\n]+\d+:\d+ +\d+:\d+)#", $a);

            foreach ($segs as $seg) {
                $s = $f->addSegment();
                $table = $this->re("#(.+?)\n\n\n#s", $seg);

                $extraBlockSeg = $this->re("#.+?\n\n\n?(.+)#s", $seg);

                $pos = [
                    0,
                    strlen($this->re("#(.+?)\d+:\d+ +\d+:\d+#", $table)),
                    strlen($this->re("#(.+?\d+:\d+ +)\d+:\d+#", $table)),
                ];
                $table = $this->splitCols($table, $pos);

                if (count($table) !== 3) {
                    $this->logger->debug('other format segments');

                    return false;
                }
                array_shift($table);

                if (preg_match("#(\d+ *[hours]+ *\d+)\s+((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+)\s+([^\n]+)\s+{$this->t('Operated by')}\s+([^\n]+?) +([\w \-]+?)(?: *\||$)#m",
                    $extraBlockSeg, $m)) {
                    $s->extra()
                        ->duration($m[1])
                        ->aircraft($m[5]);

                    if (preg_match("#(.*?)\s*\(([A-Z]{1,2})\)#", $m[3], $v)) {
                        $s->extra()
                            ->cabin($v[1], true)
                            ->bookingCode($v[2]);
                    } else {
                        $s->extra()->cabin($m[3]);
                    }

                    if (preg_match("#([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)#", $m[2], $v)) {
                        $s->airline()
                            ->name($v[1])
                            ->number($v[2])
                            ->operator($m[4]);
                    }

                    if (isset($flSeats[$m[2]]) && !empty($flSeats[$m[2]])) {
                        $s->extra()
                            ->seats($flSeats[$m[2]]);
                    }
                } elseif (preg_match("#(\d+ *[hours]+ *\d+)\s+([^\n]+)\s+((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+)\s+{$this->t('Operated by')}\:?\s*([^\n]+?) +([\w \-]+?)(?: *\||$)#m",
                    $extraBlockSeg, $m)) {
                    $s->extra()
                        ->duration($m[1])
                        ->aircraft($m[5]);

                    if (preg_match("#(.*?)\s*\(([A-Z]{1,2})\)#", $m[2], $v)) {
                        $s->extra()
                            ->cabin($v[1], true)
                            ->bookingCode($v[2]);
                    } else {
                        $s->extra()->cabin($m[2]);
                    }

                    if (preg_match("#([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)#", $m[3], $v)) {
                        $s->airline()
                            ->name($v[1])
                            ->number($v[2])
                            ->operator($m[4]);
                    }

                    if (isset($flSeats[$m[3]]) && !empty($flSeats[$m[3]])) {
                        $s->extra()
                            ->seats($flSeats[$m[3]]);
                    }
                }

                if (preg_match("#(\d+:\d+)([^\n]*)\s+(.+?)\(([A-Z]{3})\)#s", $table[0], $m)) {
                    $s->departure()
                        ->date(strtotime($m[1], $date))
                        ->name($this->nice($m[3]))
                        ->code($m[4]);
                }

                if (!empty($terminal = $this->re("#Terminal\s+(.+)#", $table[0]))) {
                    $s->departure()->terminal($terminal);
                }

                if (preg_match("#(\d+:\d+)([^\n]*)\s+(.+?)\(([A-Z]{3})\)#s", $table[1], $m)) {
                    $s->arrival()
                        ->date(strtotime($m[1], $date))
                        ->name($this->nice($m[3]))
                        ->code($m[4]);

                    if (!empty($m[2]) && preg_match("#([\+\-])\s*(\d+)#", $m[2], $v)) {
                        $s->arrival()
                            ->date(strtotime($v[1] . ' ' . $v[2] . ' days', $s->getArrDate()));
                    }
                }

                if (!empty($terminal = $this->re("#Terminal\s+(.+)#", $table[1]))) {
                    $s->arrival()->terminal($terminal);
                }
            }
        }

        return true;
    }

    private function normalizeDate($strDate)
    {
        $in = [
            //8 Mar, 2018
            '#^(\d+)\s+(\w+),\s+(\d{4})$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $str = $this->dateStringToEnglish(preg_replace($in, $out, $strDate));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
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

    private function findСutSection($input, $searchStart, $searchFinish)
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
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
