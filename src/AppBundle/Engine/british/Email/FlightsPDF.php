<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightsPDF extends \TAccountChecker
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

            if (stripos($text, 'Manage My Booking - British Airways') !== false
                && stripos($text, 'This is not a boarding pass') !== false
                && stripos($text, 'Your flight choices') !== false) {
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

        $f->general()
            ->noConfirmation();

        if (preg_match_all("/\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n\s+\d+[A-Z]\s+\d+\s+/", $text, $m)) {
            $f->general()
                ->travellers(array_unique($m[1]));
        }

        $segments = $this->splitText($text, "/(\s+[A-Z\d]{6}\s+.+\s+to\s+.*)/", true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match_all("/^\s*(\d[A-Z])[ ]{10,}/mu", $segment, $m)) {
                $s->setSeats($m[1]);
            }

            $segText = $this->re("/^(.+)\s+Your flight choices/su", $segment);
            $segTable = $this->splitCols($segText, [0, 36]);

            $s->setConfirmation($this->re("/^\s*([A-Z\d]{6})\s+/s", $segText));

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})\,\s*.+\,\s+(?<status>.+)\,\s+(?<aircraft>.+)/", $text, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->setStatus($m['status']);

                $s->extra()
                    ->aircraft($m['aircraft']);
            }

            if (preg_match("/(?<duration>.+[MH])[ ]{5,}OPERATED BY\s*(?<operator>.+)/", $segText, $m)) {
                $s->extra()
                    ->duration($m['duration']);

                $s->airline()
                    ->operator($m['operator']);
            }

            if (preg_match("/Depart\s*at\s*(?<depTime>[\d\:]+)\,\s*\w+\s*(?<depDate>\d+\s*\w+\s*\d{4})\n+(?<depName>.+\))\s*(?:Terminal\s*(?<depTerminal>.+))?/", $segTable[1], $m)
            || preg_match("/Depart\s*at\s*(?<depTime>[\d\:]+)\,\s*\w+\s*(?<depDate>\d+\s*\w+\s*\d{4})\n+(?<depName>.+)\s+Terminal\s*(?<depTerminal>.+)/", $segTable[1], $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
                    ->noCode();

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            if (preg_match("/Arrive\s*at\s*(?<arrTime>[\d\:]+)\,\s*\w+\s*(?<arrDate>\d+\s*\w+\s*\d{4})\n+(?<arrName>.+\))\s*(?:Terminal\s*(?<arrTerminal>.+))?/", $segTable[1], $m)
            || preg_match("/Arrive\s*at\s*(?<arrTime>[\d\:]+)\,\s*\w+\s*(?<arrDate>\d+\s*\w+\s*\d{4})\n+(?<arrName>.+)\s+Terminal\s*(?<arrTerminal>.+)/", $segTable[1], $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']))
                    ->noCode();

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
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

    public function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
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
