<?php

namespace AwardWallet\Engine\batikair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: move one format (it-11118606.eml, it-33986345.eml, it-9099930.eml) from parser lionair/ETicketPdf to this parser

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "batikair/it-204830911.eml";
    public $subjects = [
        'Batik air - Booking Confirmation ID -',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@batikair.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Batik Air') !== false && strpos($text, 'Issuing Airline:') !== false && strpos($text, 'Itinerary Details') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]batikair\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Booking reference no.'))}\s*([A-Z\d\_]+)/u", $text))
            ->date(strtotime($this->re("/{$this->opt($this->t('Issued date:'))}\s*(\w+\,\s*\w+\s*\d+\,\s*\d{4})/", $text)));

        $pnr = $this->re("/{$this->opt($this->t('PNR Number'))}\s*([\dA-Z]+)/", $text);

        $paxText = $this->re("/\s*Passenger Details\n(\s*Passenger Name.+Passenger Type.+)\n+\s+Itinerary Details/su", $text);

        if (preg_match_all("/\s*(?<travellers>[[:alpha:]][-.'’[:alpha:] ]*[[:alpha:]])\s*(?<tickets>\d{10,})/", $paxText, $m)) {
            $f->general()
                ->travellers(str_replace(['Mstr.', 'Mrs.', 'Mr.', 'Ms.', 'Miss.'], '', $m['travellers']), true);

            $f->setTicketNumbers($m['tickets'], false);
        }

        $flightText = $this->re("/Departure Flight\n(.+)\n+\s+Fare Rules.+Booking Summary/s", $text);

        if (preg_match_all("/(.+\n\sFlight.+\n.+\n+\s*Status.+)/", $flightText, $match)) {
            foreach ($match[1] as $flightPart) {
                $flightTable = $this->SplitCols($flightPart, $this->rowColsPos($this->inOneRow($this->re("/\n(\s*Flight.+)\n/u", $flightPart))));
                $s = $f->addSegment();

                $s->airline()
                    ->name($this->re("/([A-Z\d]+)\s+\d{2,4}/", $flightTable[1]))
                    ->number($this->re("/[A-Z\d]+\s+(\d{2,4})/", $flightTable[1]));

                $operator = $this->re("/Operating By\s*(.+)\)/", $flightTable[1]);

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }

                $s->departure()
                    ->name($this->re("/(.+)\s*Depart/su", $flightTable[2]))
                    ->noCode()
                    ->date(strtotime($this->re("/Depart\s*(.+)\n/u", $flightTable[2])));

                $s->arrival()
                    ->name($this->re("/(.+)\s*Arrive/su", $flightTable[3]))
                    ->noCode()
                    ->date(strtotime($this->re("/Arrive\s*(.+)/su", $flightTable[3])));

                $s->extra()
                    ->cabin($this->re("/Class\s*(.+)\(/", $flightPart))
                    ->bookingCode($this->re("/Class\s*\D+\(([A-Z])\)/u", $flightPart));

                $s->extra()
                    ->status($this->re("/Status\s*(\w+)/u", $flightPart));

                $s->setConfirmation($pnr);
            }
        }

        if (preg_match("/Total Ticket\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\,\.]+)/", $text, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->re("/Flight\s*[A-Z]{3}\s*(?<total>[\d\,\.]+)/", $text);

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->re("/Total Taxes\s*[A-Z]{3}\s*(?<total>[\d\,\.]+)/", $text);

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
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

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
