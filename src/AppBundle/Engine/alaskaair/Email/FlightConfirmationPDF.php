<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-190551865.eml, alaskaair/it-191059337.eml, alaskaair/it-195398661.eml, alaskaair/it-195790725.eml";
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

            if (strpos($text, 'Alaska Airlines') !== false && strpos($text, 'MANAGE TRIP') !== false && strpos($text, 'Summary of airfare charges') !== false
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
        $this->date = $this->re("/\d+\/\d+\/(\d+)\s*[\d\:]+A?P?M/", $text);

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation code:'))}\s*([A-Z]{6})\s+/", $text));

        //	it-195790725.eml
        if (preg_match("/^\s*Traveler\(s\)/mu", $text, $m)) {
            $flightText = $this->re("/^(\s*Alaska\s*.*Traveler\(s\).+)/smu", $text);
        } else {
            //other format
            $flightText = $this->re("/^\n+(\s*\D*Traveler\(s\).+)/smu", $text);
        }

        if (!empty($flightText)) {
            $flightParts = $this->split("/(?:^|\n+)(\s*\w+\s*Traveler\(s\)\n)/u", $flightText);

            if (preg_match_all("/{$this->opt($this->t('Ticket'))}\s*(\d+)/", $text, $m)) {
                $f->setTicketNumbers($m[1], false);
            }

            foreach ($flightParts as $flightPart) {
                $s = $f->addSegment();

                $airlineText = $this->re("/^\n*(.+)\n{2,4}\s+\w+\,/su", $flightPart);
                $airlineTable = $this->splitCols($airlineText);

                if (preg_match("/^(?<aName>.+)\nFlight\s*(?<fNumber>\d{2,4})\n+(?<aircraft>.+)/", $airlineTable[0], $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);

                    $s->extra()
                        ->aircraft(preg_replace("/\)\s*Sea.*/", ")", $m['aircraft']));
                }

                if (isset($airlineTable[1])) {
                    if (preg_match("/Traveler\(s\)\s*.+\s*Seat\:\s*(?<seats>.+)\s*Class\:\s*(?<bookingCode>[A-Z])\s*\((?<cabin>.+)\)/us", $airlineTable[1], $m)) {
                        $s->extra()
                            ->bookingCode($m['bookingCode'])
                            ->cabin($m['cabin']);
                    }
                    //	it-195790725.eml
                } elseif (preg_match("/Traveler\(s\)\s*.+\s*Seat\:\s*(?<seats>.+)\s*Class\:\s*(?<bookingCode>[A-Z])\s*\((?<cabin>.+)\)/us", $airlineTable[0], $m)) {
                    $s->extra()
                        ->bookingCode($m['bookingCode'])
                        ->cabin($m['cabin']);
                }

                if (preg_match_all("/Seat\:\s*(\d{1,2}[A-Z])/u", $flightPart, $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }

                if (preg_match("/A?P?M\n+\s*(?:\d{1,2}\n\s*)?(?<depCode>[A-Z]{3}).+A?P?M\n\n*\s*(?<arrCode>[A-Z]{3})\n/su", $flightPart, $m)
                    || preg_match("/\s+(?<depCode>[A-Z]{3})\s*(?<arrCode>[A-Z]{3}\n)/", $flightPart, $m)) {
                    $s->departure()
                        ->code($m['depCode']);

                    $s->arrival()
                        ->code($m['arrCode']);
                }

                if (stripos($flightPart, 'Page')) {
                    $flightPart = preg_replace("/.+\n.+Page.+/u", "", $flightPart);
                }

                $dateText = $this->re("/Seat.+\n{4,}(.+)\n+\s*[A-Z]{3}\s+[A-Z]{3}\n/su", $flightPart);

                if (empty($dateText)) {
                    $dateText = $this->re("/\n{2,4}(.+)\n{2,4}^\s*[A-Z]{3}\s*[A-Z]{3}/msu", $flightPart);
                }
                $dateTable = $this->splitCols($dateText);

                if (isset($dateTable[0])) {
                    $depDate = str_replace("\n", " ", $dateTable[0]);
                }

                if (empty($depDate)) {
                    if (preg_match("/\s*(\w+\,\s*\w+\s*\d+)\s*([\d\:]+\s*A?P?M)\n+\s*(?:\d{1,2}\n\s*)?{$s->getDepCode()}/us", $flightPart, $m)) {
                        $depDate = $m[1] . ', ' . $m[2];
                    }
                }

                if (isset($dateTable[1])) {
                    $arrDate = str_replace("\n", " ", $dateTable[1]);
                }

                if (empty($arrDate)) {
                    if (preg_match("/\s*(\w+\,\s*\w+\s*\d+)\s*([\d\:]+\s*A?P?M)\n+\s*{$s->getArrCode()}/us", $flightPart, $m)) {
                        $arrDate = $m[1] . ', ' . $m[2];
                    }
                }

                $s->departure()
                    ->date($this->normalizeDate($depDate));

                $s->arrival()
                    ->date($this->normalizeDate($arrDate));
            }

            if (preg_match_all("/(?:[ ]{10,}|\n)([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])\n.*Seat:/u", $flightText, $m)) {
                $f->general()
                    ->travellers(array_unique($m[1]), true);
            }

            if (preg_match("/Total charges for air travel\s*(?<currency>\D)(?<total>[\d\.\,]+)/u", $text, $m)) {
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
        $year = '20' . $this->date;

        $in = [
            //Sun, Nov 13 11:30 PM
            "#^(\w+)\,\s*(\w+)\s*(\d+)\,?\s*([\d\:]+\s*A?P?M)\s*$#u",
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
