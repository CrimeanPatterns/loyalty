<?php

namespace AwardWallet\Engine\airchina\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2631390 extends \TAccountChecker
{
    public $mailFiles = "airchina/it-2631390.eml, airchina/it-2894274.eml, airchina/it-29078366.eml, airchina/it-29523427.eml, airchina/it-29716278.eml, airchina/it-30024745.eml, airchina/it-30079148.eml";

    private $from = 'airchina.com';
    private $detectsSubject = [
        'en' => 'Confirmation Email from Air China',
        'de' => 'Bestätigungs-E-Mail von Air China',
        'es' => 'Correo electrónico de confirmación de Air China',
        'fr' => 'E-mail de confirmation Air China',
        'pt' => 'E-mail de confirmação da Air China',
        'ru' => 'Подтверждение по электронной почте от Air China',
        'it' => 'E-mail di conferma da Air China',
    ];

    private $detectsLang = [
        'de' => 'Vielen Dank, dass Sie sich für Air China',
        'es' => 'Gracias por elegir viajar con Air China',
        'fr' => "Merci d'avoir choisi de voyager avec Air China",
        'pt' => "Agradecemos por viajar com a Air China",
        'ru' => "Благодарим вас за то, что выбрали Air China",
        'it' => "Grazie per aver scelto di viaggiare con Air China",
        'en' => 'Thank you for choosing to travel with Air China', // last
    ];

    private $lang = 'en';

    private $prov = 'Air China';

    private static $dict = [
        'en' => [
            //            'Class:' => '',
        ],
        'de' => [
            'Class:' => 'Klasse:',
        ],
        'es' => [
            'Class:' => 'Clase:',
        ],
        'fr' => [
            'Class:' => ['Classe de\s+service:', 'Classe de'],
        ],
        'pt' => [
            'Class:' => ['Cabine:'],
        ],
        'ru' => [
            'Class:' => ['Салон:'],
        ],
        'it' => [
            'Class:' => ['Classe:'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('(?:airchina|air china).*pdf');

        if (0 < count($pdfs)) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            foreach ($this->detectsLang as $lang => $detect) {
                if (false !== stripos($text, $detect)) {
                    $this->lang = $lang;
                    $this->parseEmail($email, $text);

                    break;
                }
            }
        }
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject']) || !isset($headers['from'])) {
            return false;
        }

        if (stripos($headers['from'], $this->from) !== false) {
            return true;
        }

        foreach ($this->detectsSubject as $dSubject) {
            if (stripos($headers['subject'], $this->from) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('(?:airchina|air china).*pdf');

        if (0 < count($pdfs)) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            if (false === stripos($text, $this->prov)) {
                return false;
            }

            foreach ($this->detectsLang as $detect) {
                if (false !== stripos($text, $detect)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email, string $text): void
    {
        $f = $email->add()->flight();

        // CONFIRMATION NUMBER
        $confText = $this->cutText('Booking code', 'Passenger Information', $text);

        if (empty($confText)) {
            $confText = $this->cutText(' Confirmation numbe', 'Passenger Information', $text);
        }

        if ($conf = $this->re('/:\s*([A-Z\d]{5,9})/', $confText)) {
            $f->general()
                ->confirmation($conf);
        }

        // STATUS
        if (
            false !== stripos($text, 'your reservation has been ticketed')
            || false !== stripos($text, 'your reservation has been electronically ticketed')
        ) {
            $f->setStatus('Confirmed');
        }

        // PASSENGERS AND TICKET NUMBERS
        $paxInfo = $this->cutText('Passenger Information', 'Itinerary', $text);
        $tablePos = [0];
        $m = [];

        if (preg_match('/^(.+?[ ]{2,})Passenger Type\b/m', $paxInfo, $matches)) {
            $m = $matches;
        } elseif (preg_match('/^(.+?[ ]{2,})Passenger\b/m', $paxInfo, $matches)) {
            $m = $matches;
        }
        unset($m[0]);
        asort($m);

        foreach ($m as $textHeaders) {
            $tablePos[] = mb_strlen($textHeaders);
        }
        $table = $this->splitCols($paxInfo, $tablePos, false);

        if (2 <= count($table)) {
            $table[0] = preg_replace('/^((?:.*(?:\(|\)|name|Passenger|（|）| — | - |пассажир|:).*|\s*)\n)*/', '', $table[0]);
            $table[0] = preg_replace('/^[ ]{20,}.*/m', '', $table[0]);
            $paxs = preg_split("/\n{3}/", $table[0]);
            $paxs = array_values(array_filter(array_unique(array_map('trim', $paxs))));

            if (0 === count($paxs) && preg_match_all('/\n[ ]*([a-z\/]+)[ ]*\n/i', $table[0], $m)) {
                $paxs = $m[1];
            }

            $tableWithTicketNumbers = array_map(function ($e) {
                if (false !== stripos($e, 'Ticket Number')) {
                    return $e;
                }

                return null;
            }, $table);
            $tableWithTicketNumbers = array_filter($tableWithTicketNumbers);
            $t = '';

            if (is_array($tableWithTicketNumbers) && 0 < count($tableWithTicketNumbers)) {
                $t = array_shift($tableWithTicketNumbers);
            }

            if (preg_match_all('/\b(\d{3}-\d{10})\b/', $t, $m)) {
                $tickets = $m[1];
            }

            if ((count($paxs) < count($tickets))) {
                $paxs = array_filter(array_map('trim', preg_split("/\n{2}/", $table[0])));
            }

            if ((count($paxs) < count($tickets))) {
                $paxs = array_filter(array_map('trim', preg_split("/\n/", $table[0])));
            }

            if (count($paxs) == count($tickets)) {
                $paxs = array_map(function ($e) { return trim(preg_replace('/\s+/', ' ', $e)); }, $paxs);
                $f->general()->travellers($paxs, true);
                $f->issued()->tickets($tickets, false);
            }
        }

        // TOTAL
        $totalText = $this->cutText('Fare detail', 'Fare Rules', $text);
        $tablePos = [0];

        if (!preg_match('/^(((.+?)' . implode('[ ]{2,})', ['Base Fare', 'The price of your ticket includes the following levy.*?', 'Grand total']) . '/m', $totalText, $matches)) {
            if (preg_match('/^((.+?)' . implode('[ ]{2,})', ['Base Fare', 'The price of your ticket includes the following levy.*?']) . '/m', $totalText, $matches)
                    && preg_match('/^(.{50,}[ ]{2,})Grand total/m', $totalText, $matches1)) {
                $matches[] = $matches1[1];
            } else {
                $this->logger->debug('Table headers for total did not found!');
            }
        }
        unset($matches[0]);

        foreach ($matches as $textHeaders) {
            $tablePos[] = mb_strlen($textHeaders);
        }
        sort($tablePos);
        $table = $this->splitCols($totalText, $tablePos, true, true);

        if (4 <= count($table) && !empty($f->getTravellers())) {
            $totalText = end($table);
            $totalText = preg_replace("#^.*?Grand total.*?\n([^\n]*\d)#s", '$1', $totalText);
            $totalTextRow = array_values(array_filter(explode("\n", $totalText)));

            if (count($totalTextRow) >= count($f->getTravellers())) {
                $success = true;
                $total = 0.0;
                $currency = null;

                for ($i = 0; $i < count($f->getTravellers()); $i++) {
                    if (preg_match('/^\s*([\d\.]+)\s*([A-Z]{3})\s*$/', $totalTextRow[$i], $m)
                            && is_numeric($m[1])
                            ) {
                        $total += (float) $m[1];
                        $currency = $m[2];
                    } else {
                        $success = false;
                    }
                }

                if ($success === true && !empty($total)) {
                    $f->price()
                        ->total($total)
                        ->currency($currency);
                }
            }
        }

        // SEATS INFORMATION
        $seats = [];
        $seatsText = $this->cutText('Special Meal Reservation, Advanced Seat Reservation(ASR) Result', 'Fare detail', $text);

        if (empty($seatsText)) {
            $seatsText = $this->cutText('Special Meal Reservation, Advance Seat Reservation(ASR) Result', 'Receipt', $text);
        }

        if (preg_match_all('/((?:[A-Z]{2}\d+|[A-Z]\d{1,3}\d+))\s+([A-Z\d]{1,4})\b/', $seatsText, $m)) {
            $fnums = $m[1];
            $s = $m[2];
            $seats = [];

            foreach ($fnums as $i => $fnum) {
                $seats[$fnum][] = $s[$i];
            }
        }

        // SEGMENTS

        $segText = $this->cutText('Itinerary', 'Special Meal Reservation', $text);
        $sText = $this->splitter('/(.+' . $this->opt($this->t("Class:")) . '\s*(?:.*\n){0,5}?\s*[A-Z\d]{2}\s*\d+)/', $segText);
        $correctColumn = true;

        if (empty($sText)) {
            $sText = $this->splitter('/(.+\s*\n\s*.+\([A-Z]{3}\)\s+.+\([A-Z]{3}\)\s+[A-Z\d]{2}\s*\d+\s+[A-Z])/', $segText);
            $correctColumn = false;
        }

        $tablePos = [0];

        if (!preg_match('/^(((((\s*)' . implode('[ ]{2,})', ['Flight No\.?', 'From', 'To', 'Class', 'Departure Date']) . '/m', $segText, $matches)) {
            if (preg_match('/^((((\s*)' . implode('[ ]+)', ['Flight No\.?', 'From', 'To', 'Class']) . '/m', $segText, $matches)
                    && preg_match('/^(.{50,}[ ]{2,})Departure/m', $segText, $matches1)) {
                $matches[] = $matches1[1];
            } else {
                $this->logger->debug('Table headers not found!');

                return;
            }
        }
        unset($matches[0]);

        foreach ($matches as $textHeaders) {
            $tablePos[] = mb_strlen($textHeaders);
        }
        sort($tablePos);

        if (count($tablePos) < 6) {
            $this->logger->debug('Table headers columns not detected successful!');

            return;
        }

        foreach ($sText as $seg) {
            $s = $f->addSegment();

            $seg = preg_replace("#(\([A-Z]{3}\) )-#", '$1 ', $seg);

            $table = $this->splitCols($seg, $tablePos, true, $correctColumn);
            $table = array_map("trim", $table);
            $table = array_values(array_filter(array_unique($table)));

            $date = null;

            if (isset($table[4]) && preg_match('/([a-z]+)\s+(\d{1,2})\s+(\d{2,4})/i', $table[4], $m)) {
                $date = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3]);
            } elseif (isset($table[4]) && preg_match('/(\d{1,2})[\s\-]{1}(\w+)\.?[\s\-]{1}(\d{2,4})/u', $table[4], $m)) {
                $date = $this->normalizeDate($m[1] . ' ' . $m[2] . ' ' . $m[3]);
            } elseif (isset($table[4]) && preg_match('/(?:^|\n)[^\d\s]{2,5}\.?[ ]*(\d{1,2}) (\w+)\b(?:.*\n)*?(\d{4})/u', $table[4], $m)) {
                $date = $this->normalizeDate($m[1] . ' ' . $m[2] . ' ' . $m[3]);
            }

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber()) && !empty($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
                $s->extra()
                    ->seats($seats[$s->getAirlineName() . $s->getFlightNumber()]);
            }

            if ($dCode = $this->re('/\(([A-Z]{3})\)/', $table[1])) {
                $s->departure()
                    ->code($dCode);
            }

            if ($aCode = $this->re('/\(([A-Z]{3})\)/', $table[2])) {
                $s->arrival()
                    ->code($aCode);
            }

            if (empty($s->getDepCode()) || empty($s->getArrCode())) {
                if (preg_match("#\(([A-Z]{3})\).*([A-Z]{3})#", $seg, $m)) {
                    $s->departure()->code($m[1]);
                    $s->arrival()->code($m[2]);
                } elseif (preg_match_all("#(.+)\(([A-Z]{3})\)#", $seg, $m)) {
                    if (count($m[0]) == 2) {
                        if (mb_strlen($m[1][0]) > mb_strlen($m[1][1])) {
                            $s->departure()->code($m[2][1]);
                            $s->arrival()->code($m[2][0]);
                        } elseif (mb_strlen($m[1][0]) < mb_strlen($m[1][1])) {
                            $s->departure()->code($m[2][0]);
                            $s->arrival()->code($m[2][1]);
                        }
                    }
                }
            }

            if ($date && ($dt = $this->re('/(\d{1,2}:\d{2})/', $table[1]))) {
                $s->departure()
                    ->date(strtotime($dt, $date));
            }

            if ($date && ($at = $this->re('/(\d{1,2}:\d{2})/', $table[2]))) {
                $s->arrival()
                    ->date(strtotime($at, $date));
            }

            if ($date && (empty($s->getDepDate()) || empty($s->getArrDate()))) {
                if (preg_match("# (\d{2}:\d{2}) .* (\d{2}:\d{2})#", $seg, $m)) {
                    $s->departure()->date(strtotime($m[1], $date));
                    $s->arrival()->date(strtotime($m[2], $date));
                } elseif (preg_match_all("#(.+) (\d{2}:\d{2})\b#", $seg, $m)) {
                    if (count($m[0]) == 2) {
                        if (preg_match("/^\d+:\d+$/", $m[2][0]) && preg_match("/^\d+:\d+$/", $m[2][1])) {
                            $s->departure()->date(strtotime($m[2][0], $date));
                            $s->arrival()->date(strtotime($m[2][1], $date));
                        } elseif (mb_strlen($m[1][0]) > mb_strlen($m[1][1])) {
                            $s->departure()->date(strtotime($m[2][1]));
                            $s->arrival()->date(strtotime($m[2][0]));
                        } elseif (mb_strlen($m[1][0]) < mb_strlen($m[1][1])) {
                            $s->departure()->date(strtotime($m[2][0]));
                            $s->arrival()->date(strtotime($m[2][1]));
                        }
                    }
                }
            }

            if (!empty($s->getArrDate()) && $nextDay = $this->re('/\n([\+\-][ ]*\d)[ ]*\w{3,6}(\n|$)/u', $table[2]) && !empty($s->getArrDate())) {
                $s->arrival()
                    ->date(strtotime($nextDay . 'day', $s->getArrDate()));
            }

            $term = '/terminal\s+(?:Not Assigned|([A-Z\d]{1,5}|International))\b/i';

            if ($d = $this->re($term, $table[1])) {
                $s->departure()
                    ->terminal($d);
            }

            if ($a = $this->re($term, $table[2])) {
                $s->arrival()
                    ->terminal($a);
            }

            if ($cabin = $this->re('/' . $this->opt($this->t("Class:")) . '\s*(.+?)($|\()/is', $table[3])) {
                $s->extra()
                    ->cabin(trim(preg_replace("/\s+/", ' ', $cabin)));
            }

            if (($bc = $this->re('/\(([A-Z])\)/', $table[3])) || ($bc = $this->re('/^\s*([A-Z])\s*$/', $table[3]))) {
                $s->extra()
                    ->bookingCode($bc);
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
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

    private function SplitCols($text, $pos = false, $trim = true, $correct = false)
    {
        $ds = 8;
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($correct == true) {
                    if ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p + 1, 1))))) {
                        $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                        if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                            $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                            $pos[$k] = $p - strlen($m[2]) - 1;

                            continue;
                        } else {
                            $str = mb_substr($row, $p, $ds, 'UTF-8');

                            if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
                                $pos[$k] = $p + strlen($m[1]) + 1;

                                continue;
                            } elseif (preg_match("#^\s+(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[1] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8');

                                continue;
                            } elseif (!empty($str)) {
                                $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                                if (preg_match("#(\S*)\s+(\S*)$#", $str, $m)) {
                                    $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                                    $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                                    $pos[$k] = $p - strlen($m[2]) - 1;

                                    continue;
                                }
                            }
                        }
                    }
                }

                if ($trim === true) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
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

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", $field) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->http->log('normalizeDate = '.print_r( $str,true));
        $in = [
            "#^(\d+) ([^\s\d\,\.]+)[.,]* (\d+:\d+)$#", //05 Jan 08:30
        ];
        $out = [
            "$1 $2 %Y%, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
