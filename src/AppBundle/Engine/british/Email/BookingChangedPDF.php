<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingChangedPDF extends \TAccountChecker
{
    public $mailFiles = "british/it-493614991.eml";
    public $lang = 'en';

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'booking with British Airways') !== false
                && stripos($text, 'Your Itinerary') !== false
                && stripos($text, 'Baggage allowances') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]britishairways\.com/', $from) > 0;
    }

    public function ParseFlight(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $travellers = array_filter(explode("\n", $this->re("/Passenger\s*([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])(?:\n\n\n|\n+\s*Baggage allowances)/", $text)));

        $f->general()
            ->confirmation($this->re("/Booking reference\:\s*([A-Z\d]{6})/", $text))
            ->travellers(preg_replace("/^\s*(?:MRS|MR|MS)\s+/", "", $travellers));

        if (preg_match_all("/\s+(\d{3}\-\d{5,})\s*\(/", $text, $m)) {
            $f->setTicketNumbers($m[1], false);
        }

        if (preg_match("/Total taxes, fees and\D+[ ]{10,}(?<currency>[A-Z]{3})\s*(?<total>[\d\.,]+)\n/", $text, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $segText = $this->re("/Your Itinerary\n+(.+)\n\s*Passenger.*\s+Baggage allowances\n/su", $text);
        $segments = splitter("/([A-Z]{2}\d{2,4})\n/", $segText);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s+.+\s+\|\s+.+\s+\|\s+(?<status>.+)\n/", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->setStatus($m['status']);

                $segPart = $this->re("/\n+^(\s+\d+\s*\w+\s*\d{4}.+)/msu", $segment);
                $segTable = $this->splitCols($segPart);

                if (preg_match("/(?<depDay>\d+\s*\w+\s*\d{4})\n+(?<depTime>\d+\:\d+)\n+(?<depName>.+)\n*$/", $segTable[0], $m)
                || preg_match("/(?<depDay>\d+\s*\w+\s*\d{4})\n+(?<depTime>\d+\:\d+)\n+(?<depName>.+)\n+Terminal\s*(?<depTerm>.+)/", $segTable[0], $m)) {
                    $s->departure()
                        ->name($m['depName'])
                        ->date(strtotime($m['depDay'] . ', ' . $m['depTime']))
                        ->noCode();

                    if (isset($m['depTerm']) && !empty($m['depTerm'])) {
                        $s->departure()
                            ->terminal($m['depTerm']);
                    }
                }

                if (preg_match("/(?<arrDay>\d+\s*\w+\s*\d{4})\n+(?<arrTime>\d+\:\d+)\n+(?<arrName>.+)\n*$/", $segTable[1], $m)
                || preg_match("/(?<arrDay>\d+\s*\w+\s*\d{4})\n+(?<arrTime>\d+\:\d+)\n+(?<arrName>.+)\n+Terminal\s*(?<arrTerm>.+)/", $segTable[1], $m)) {
                    $s->arrival()
                        ->name($m['arrName'])
                        ->date(strtotime($m['arrDay'] . ', ' . $m['arrTime']))
                        ->noCode();

                    if (isset($m['arrTerm']) && !empty($m['arrTerm'])) {
                        $s->arrival()
                            ->terminal($m['arrTerm']);
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseFlight($email, $text);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
