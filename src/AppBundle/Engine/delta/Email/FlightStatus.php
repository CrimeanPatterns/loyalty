<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightStatus extends \TAccountChecker
{
    public $mailFiles = "delta/it-106577663.eml, delta/it-106657126.eml";

    private $detectFrom = ".delta.com";
    private $detectSubject = [
        // en
        'Status Update:', // Status Update: DL1822 Departing LAX 14 Nov
        'Gate change ', // Gate change DL1161 Departing LAX 6 Feb
        ' has arrived', // DL1688 has arrived
        ' has taken off', // DL1688 has taken off
        ' Now Boarding', // DL1688 Now Boarding
        'Time Change: ', // Time Change: DL2343 Arriving ATL 5 Aug
    ];

    public $emailDate;
    public $emailSubject;

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'deltaairlines@t.delta.com') === false
            && stripos($headers['from'], 'deltaairlines@e.delta.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailDate = strtotime($parser->getDate());
        $this->emailSubject = $parser->getSubject();

        $this->parseEmail($email);

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
        return 2 * count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        // Segments
        $s = $f->addSegment();

        // Airline
        if (preg_match("/\b(DL) ?(\d{1,5})\b/", $this->emailSubject, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }


        // Departure
        $name = $this->http->FindSingleNode("//*[self::td or self::th][" . $this->eq("Departure") . "]/ancestor::tr[1]/following-sibling::tr[1][*[2][" . $this->eq("Airport") . "]]/following-sibling::tr[1]/*[2]");
        if (preg_match("/(.+?)\s*\(([A-Z]{3})\)(.*)/", $name, $m)) {
            $s->departure()
                ->name($m[1])
                ->code($m[2])
                ->terminal(preg_replace("/\s*terminal\s*/i", '', $m[3]), true, true);
        } else {
            $s->departure()
                ->noCode()
            ;
        }
        $time = implode( ' ', $this->http->FindNodes("//*[self::td or self::th][" . $this->eq("Departure") . "]/ancestor::tr[1]/following-sibling::tr[1][*[3][" . $this->eq("Time") . "]]/following-sibling::tr[1]/*[3]//text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]"));
        if (!empty($time)) {
            $s->departure()
                ->date($this->normalizeDate($time));
        } else {
            $s->departure()
                ->noDate()
            ;
        }

        // Arrival
        $name = $this->http->FindSingleNode("//*[self::td or self::th][" . $this->eq("Arrival") . "]/ancestor::tr[1]/following-sibling::tr[1][*[2][" . $this->eq("Airport") . "]]/following-sibling::tr[1]/*[2]");
        if (preg_match("/(.+?)\s*\(([A-Z]{3})\)(.*)/", $name, $m)) {
            $s->arrival()
                ->name($m[1])
                ->code($m[2])
                ->terminal(preg_replace("/\s*terminal\s*/i", '', $m[3]), true, true);
        } else {
            $s->arrival()
                ->noCode()
            ;
        }
        $time = implode( ' ', $this->http->FindNodes("//*[self::td or self::th][" . $this->eq("Arrival") . "]/ancestor::tr[1]/following-sibling::tr[1][*[3][" . $this->eq("Time") . "]]/following-sibling::tr[1]/*[3]//text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]"));
        if (!empty($time)) {
            $s->arrival()
                ->date($this->normalizeDate($time));
        } else {
            $s->arrival()
                ->noDate()
            ;
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
                return 'contains(' . $text . ',"' . $s . '")';
            }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
                return 'starts-with(normalize-space(.),"' . $s . '")';
            }, $field)) . ')';
    }

    private function normalizeDate(?string $dateText): ?int
    {

        if (empty($dateText)) {
            return null;
        }
        $year = date('Y', $this->emailDate);
        $dateText .= ' '. $year;
        $date = strtotime($dateText);
        if (empty($date)) {
            return null;
        }

        if (abs($date - $this->emailDate) > 60 * 60 * 24 * 30 * 6) {
            if ($date - $this->emailDate > 0) {
                $date = strtotime("-1 year", $date);
            } else {
                $date = strtotime("+1 year", $date);
            }
        }

        if (abs($date - $this->emailDate) < 60 * 60 * 24 * 30 * 2) {
            return $date;
        }
        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }
}