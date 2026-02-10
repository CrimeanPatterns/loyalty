<?php

namespace AwardWallet\Engine\condor\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightStatus extends \TAccountChecker
{
    public $mailFiles = "condor/it-381609270.eml";
    public static $dictionary = [
        'en' => [
            'Flight' => 'Flight',
        ],
    ];

    private $detectFrom = 'condor.com';
    private $detectSubject = [
        // en
        'is now ready for check-in',
    ];
    private $detectBody = [
        'en' => [
            'is now ready for check-in ',
        ],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Flight']) && $this->http->XPath->query("//*[" . $this->eq($dict['Flight']) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'condor.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $s = $f->addSegment();

        // Airline
        $s->airline()
            ->name($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Flight'))}]]/following-sibling::tr[1]/*[1]", null, true, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}\s*$/"))
            ->number($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Flight'))}]]/following-sibling::tr[1]/*[1]", null, true, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/"));

        // Departure
        $s->departure()
            ->code($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Flight'))}]]/*[3][{$this->starts($this->t('Departs'))}]", null, true, "/\s*\(([A-Z]{3})\)\s*$/u"))
        ;
        $dateRelative = strtotime($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('As of'))}]", null, true, "/{$this->opt($this->t('As of'))}\s*(.+)/"));

        if (!empty($dateRelative)) {
            $dateRelative = strtotime('- 1 day', $dateRelative);
        }

        $date = $this->http->FindSingleNode("//tr[*[3][{$this->starts($this->t('Departs'))}]]/following-sibling::tr[1]/*[3]");

        if (preg_match("/^(.+)\s+(\d{1,2}:\d{2})\s*$/", $date, $m)) {
            $d = EmailDateHelper::parseDateRelative($m[1] . ' ' . date('Y', $dateRelative), $dateRelative);

            if (!empty($d)) {
                $s->departure()->date(strtotime($m[2], $d));
            }
        }

        // Arrival
        $s->arrival()
            ->code($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Flight'))}]]/*[4][{$this->starts($this->t('Arrives'))}]", null, true, "/\s*\(([A-Z]{3})\)\s*$/u"))
        ;
        $date = $this->http->FindSingleNode("//tr[*[4][{$this->starts($this->t('Arrives'))}]]/following-sibling::tr[1]/*[4]");

        if (preg_match("/^(.+)\s+(\d{1,2}:\d{2})\s*$/", $date, $m)) {
            $d = EmailDateHelper::parseDateRelative($m[1] . ' ' . date('Y', $dateRelative), $dateRelative);

            if (!empty($d)) {
                $s->arrival()->date(strtotime($m[2], $d));
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 13 Dec 2020, 18h30
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(\d{1,2})h(\d{2})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4:$5",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
