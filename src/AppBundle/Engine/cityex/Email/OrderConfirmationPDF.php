<?php

namespace AwardWallet\Engine\cityex\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "cityex/it-159832118.eml, cityex/it-159986220.eml, cityex/it-159986221.eml";
    public $subjects = [
        'City Experiences Order Confirmation',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Confirmation No.:' => ['Confirmation No.:', 'Confirmation Number:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@cityexperiences.com') !== false) {
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

            if (strpos($text, 'customerservice@cityexperiences.com') !== false && strpos($text, 'Lead Traveler:') !== false && strpos($text, 'Important Information') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cityexperiences\.com$/', $from) > 0;
    }

    public function ParseEventPDF(Email $email, $text)
    {
        $event = $email->add()->event();

        $event->setEventType(4);

        $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation No.:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (empty($confNumber)) {
            $confNumber = $this->re("/Confirmation Number\:\n\s+.+[ ]{10,}(\d{5,})\n/", $text);
        }

        $event->general()
            ->confirmation($confNumber)
            ->traveller($this->re("/{$this->opt($this->t('Lead Traveler:'))}\s*(\D+)\n\s*Email/", $text));

        if (preg_match("#{$this->opt($this->t('Confirmation Number:'))}\n\s*(.+)[ ]{10,}\d{6,}\s*\n#u", $text, $m)) {
            $event->setName($m[1]);
        } elseif (preg_match("/Purchase Reference\:\n+(?:(.+\n.+)\n+|(.+)\n+)Entrance\/Meeting Place/u", $text, $m)) {
            $event->setName(preg_replace("/\n\s*/", " ", $m[1]));
        } elseif (preg_match("/^[A-Z\d]+\n+\s*(.+)\n+\s+ADULT/", $text, $m)) {
            $event->setName(preg_replace("/\n\s*/", " ", $m[1]));
        }

        $eventText = $this->re("/(Entrance\/Meeting Place\s*Important Information\n.+QUESTIONS[?] GET IN TOUCH)/su", $text);

        $eventTable = $this->SplitCols($eventText);

        if (preg_match("#{$this->opt($this->t('Entrance/Meeting Place'))}\n+(?:(.+\n.+)\n+|(.+)\n+)QUESTIONS#u", $eventTable[0], $m)) {
            $address = !empty($m[1]) ? $m[1] : $m[2];
            $event->setAddress(preg_replace("/\n\s*/", " ", $address));
        }

        $event->setStartDate(strtotime($this->re("/^\s*\w+\,\s*(\w+\s*\d+\,\s*\d{4}.*)\n+\s*{$this->opt($this->t('Lead Traveler:'))}/mu", $text)));
        $event->setNoEndDate(true);

        if (preg_match_all("/ADULT\s*STANDARD.+\s+\((\d{10,})\)/u", $text, $m)
        || preg_match_all("/(Experience Adult)/u", $text, $m)
        || preg_match_all("/\n\s+(ADUL)T\n/u", $text, $m)
        ) {
            $event->setGuestCount(count($m[1]));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseEventPDF($email, $text);
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

    public function SplitCols($text, $pos = null)
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

    public function TableHeadPos($row)
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
}
