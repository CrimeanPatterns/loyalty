<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationLetterPDF extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-191048545.eml, alaskaair/it-191296614.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $travellers = [];

    public static $dictionary = [
        "en" => [
        ],
    ];

    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Thank you for booking with Alaska and we look forward to seeing you on board') !== false && strpos($text, 'Additional information') !== false && strpos($text, 'Summary of airfare charges') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]alaskaair\.com\/$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $this->date = $this->re("/\d+\/\d+\/(\d{4})\,\s*[\d\:]+\s*A?P?M/", $text);

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation code:'))}\s*([A-Z]{6})\s+/", $text));

        $flightText = $this->re("/^\s*View\/Manage\n(\s*Flight.+)Additional information/smu", $text);

        if (!empty($flightText)) {
            $flightParts = preg_split("/\n{2,}/u", $flightText);

            if (preg_match_all("/{$this->opt($this->t('Ticket'))}\s*(\d+)/", $text, $m)) {
                $f->setTicketNumbers($m[1], false);
            }

            $headText = $this->re("/^(\s*Flight.+\n)/mu", $flightText);

            foreach ($flightParts as $flightPart) {
                if (stripos($flightPart, '(') !== false) {
                    $s = $f->addSegment();

                    if (stripos($flightPart, 'Flight') == false) {
                        $flightPart = $headText . $flightPart;
                    }

                    $flightTable = $this->splitCols($flightPart);

                    if (preg_match("/Flight\s*(?<airName>\D+)\s+(?<airNumber>\d{1,4})\s*(?<aircraft>.+)\n/", $flightTable[0], $m)) {
                        $s->airline()
                            ->name($m['airName'])
                            ->number($m['airNumber']);

                        $s->extra()
                            ->aircraft($m['aircraft']);
                    }

                    if (preg_match("/Departs\n.+\(\s*(?<depCode>[A-Z]{3})\)\s*(?<depDate>\w+\,\s*\w+\s*\d+)\s*(?<depTime>[\d\:]+\s*a?p?m)/us", $flightTable[1], $m)) {
                        $s->departure()
                            ->code($m['depCode'])
                            ->date($this->normalizeDate($m['depDate'] . ' ' . $m['depTime']));
                    }

                    if (preg_match("/Arrives\n.+\(\s*(?<arrCode>[A-Z]{3})\)\s*(?<arrDate>\w+\,\s*\w+\s*\d+)\s*(?:(?<bookingCode>[A-Z])\s*\((?<cabin>\w+)\))?\s*(?<arrTime>[\d\:]+\s*a?p?m)/us", $flightTable[2], $m)) {
                        $s->arrival()
                            ->code($m['arrCode'])
                            ->date($this->normalizeDate($m['arrDate'] . ' ' . $m['arrTime']));

                        if (isset($m['bookingCode']) && !empty($m['bookingCode'])) {
                            $s->extra()
                                ->bookingCode($m['bookingCode']);
                        }

                        if (isset($m['cabin']) && !empty($m['cabin'])) {
                            $s->extra()
                                ->cabin($m['cabin']);
                        }
                    }

                    if (preg_match("/\s(?<code>[A-Z])\s*\((?<cabin>.+)\)/us", $flightTable[3], $m)) {
                        $s->extra()
                            ->bookingCode($m['code'])
                            ->cabin($m['cabin']);
                    }

                    if (preg_match("/Seat\(s\)\n\s*(?<seat>\d{1,2}[A-Z])\s*[★]*\s*$/", $flightTable[5], $m)) {
                        $s->extra()
                            ->seat($m['seat']);
                    }
                }
            }

            if (preg_match_all("/\s+(?:[A-Z]|\(\w+\))\s+([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])\s+(\d{1,2}[A-Z])/u", $flightText, $m)) {
                $f->general()
                    ->travellers(array_unique($m[1]), true);
            }

            if (preg_match("/Total charges for additional items\s*(?<currency>\D{3})\s*\D(?<total>[\d\.\,]+)/u", $text, $m)) {
                $currency = $m['currency'];

                $f->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);

                $cost = $this->re("/Base fare and surcharges\s*\D([\d\.\,]+)/", $text);

                if (!empty($cost)) {
                    $f->price()
                        ->cost(PriceHelper::parse($cost, $currency));
                }

                $tax = $this->re("/Taxes and other fees\s*\D([\d\.\,]+)/", $text);

                if (!empty($tax)) {
                    $f->price()
                        ->tax(PriceHelper::parse($tax, $currency));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
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

    private function normalizeDate($str)
    {
        $year = $this->date;

        $in = [
            //Sun, Nov 13 11:30 PM
            "#(\w+)\,\s*(\w+)\s*(\d+)\s*([\d\:]+\s*a?p?m)#u",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
