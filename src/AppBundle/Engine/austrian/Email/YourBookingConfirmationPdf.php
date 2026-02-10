<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "austrian/it-8568865.eml, austrian/it-8637112.eml, austrian/it-98309800.eml";

    public $reFrom = "no-reply@austrian.com";
    public $reSubject = [
        "en" => "Your Austrian booking confirmation",
        "de" => "Ihre Austrian Buchungsbestätigung",
    ];
    public $reBody = 'Austrian';
    public $reBody2 = [
        "en" => "Your Booking code:",
        "de" => "Ihr Buchungscode:",
    ];
    public $pdfPattern = "pdf-[A-Z\d]+-\d+-booking.pdf";

    public static $dictionary = [
        "en" => [],
        "de" => [
            'Your Booking code:'                     => 'Ihr Buchungscode:',
            'Passengers and services'                => 'Passagiere und Services',
            'Taxes and Charges'                      => 'Steuern & Gebühren',
            'Fares'                                  => 'Flugpreis',
            'Total'                                  => 'Summe',
            'charged by Austrian'                    => 'verrechnet von Austrian',
            'Your booked flight'                     => 'Ihr gebuchter Flug',
            'Your Austrian reservation confirmation' => 'Ihre Austrian Buchungsbestätigung',
            'operated by'                            => 'durchgeführt',
        ],
    ];

    public $lang = "en";

    public function parsePdf(Email $email)
    {
        $text = $this->text;
        $mainTable = $this->splitCols($this->re("#\n([^\n\S]*{$this->opt($this->t('Your Booking code:'))}.*?)\n\n\n#ms", $text));

        if (count($mainTable) !== 3) {
            $this->http->log("incorrect parse mainTable");

            return;
        }

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#{$this->opt($this->t('Your Booking code:'))}\s+([^\n]+)#ms", $mainTable[0]))
            ->travellers(array_filter([$this->re("#{$this->opt($this->t('Passengers and services'))}\s+(?:(?:Herr|Frau|Mrs\. \/ Ms\.|Mr)\s+)?([^\n]+)#ms", $text)]));

        $f->price()
            ->tax($this->amount($this->re("#{$this->opt($this->t('Taxes and Charges'))}\s+([\d\.]+)#", $text)))
            ->cost($this->amount($this->re("#{$this->opt($this->t('Fares'))}.*\s{2,}([\d\.]+)#", $text)))
            ->total($this->amount($this->re("#{$this->t('Total')}\s*{$this->opt($this->t('charged by Austrian'))}\s+(.+)#u", $text)))
            ->currency($this->currency($this->re("#{$this->t('Total')}\s*{$this->opt($this->t('charged by Austrian'))}\s+(.+)#u", $text)));

        $flights = "\n" . mb_substr($text,
                $sp = strpos($text, "{$this->t('Your booked flight')}") + strlen("{$this->t('Your booked flight')}"),
                mb_strpos($text, "{$this->t('Passengers and services')}") - $sp, 'UTF-8');

        $flights = preg_replace("#\n\s+\d+/\d+\n\s+{$this->opt($this->t('Your Austrian reservation confirmation'))}.+\n#", "", $flights); //remove headers/footers
        $segments = $this->split("#\n(.*\([A-Z]{3}\))#", $flights);

        foreach ($segments as $stext) {
            $stext = preg_replace("#^\s*\n#", "", $stext);
            $s = $f->addSegment();
            //$itsegment = [];
            // Vienna (VIE)           – Tel Aviv (TLV)         20:25     – 00:50       OS859 operated by
            //												   16.07.2017 17.07.2017   Austrian Airlines
            if (preg_match("#\s*(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\s+–\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)\s+" .
                            "(?<DepTime>\d+:\d+)\s+–\s+(?<ArrTime>\d+:\d+)\s+" .
                "(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s+{$this->opt($this->t('operated by'))}\n" .
                            "\s*(?<DepDate>\d+\.\d+\.\d{4})\s+(?<ArrDate>\d+\.\d+\.\d{4})\s+(?<Operator>.+)#", $stext, $m)) {
                $s->airline()
                    ->name($m['AirlineName'])
                    ->number($m['FlightNumber']);

                $s->departure()
                    ->code($m['DepCode'])
                    ->date(strtotime($this->normalizeDate($m["DepDate"] . ", " . $m["DepTime"])));

                $s->arrival()
                    ->code($m['ArrCode'])
                    ->date(strtotime($this->normalizeDate($m["ArrDate"] . ", " . $m["ArrTime"])));
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($email);

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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\.\d+\.\d{4},\s+\d+:\d+)$#", //03.07.2017, 06:15
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
