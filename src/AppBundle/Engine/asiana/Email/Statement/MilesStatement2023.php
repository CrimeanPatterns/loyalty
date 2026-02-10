<?php

namespace AwardWallet\Engine\asiana\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MilesStatement2023 extends \TAccountChecker
{
    public $mailFiles = "asiana/statements/it-63957035.eml";
    private $lang = '';
    private $reFrom = ['iclub@flyasiana.com'];
    private $reSubject = [
        '[Asiana Airlines] This is your Mileage balance as of',
    ];
    private $reBody = [
        'en' => [
            'Mileage subject to expire',
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $st = $email->add()->statement();
        $login = $this->http->FindSingleNode("//img[{$this->eq('View Mileage', '@alt')}]/ancestor::tr[1][count(.//text()[normalize-space()]) = 1]",
            null, true, "/^\s*(\d{4,}\*+)\s*$/");
        $st->setLogin($login)->masked('right');
        $st->setNumber($login)->masked('right');
        $st->addProperty('Name', $this->http->FindSingleNode("//img[{$this->eq('View Mileage', '@alt')}]/ancestor::tr[1][count(.//text()[normalize-space()]) = 1]/preceding::tr[normalize-space()][1]",
            null, true, "/^\s*([A-Z \-]+)\s*$/"));
        $date = $this->http->FindSingleNode("//text()[{$this->eq('Mileage Balance')}]/preceding::text()[normalize-space()][1]", null, true, "/of (.+)/");
        $st->setBalanceDate($this->normalizeDate($date));

        $balance = $this->http->FindSingleNode("//text()[{$this->eq('Mileage Balance')}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+[, \.\d]*)\s*$/");
        $st->setBalance(str_replace(',', '', $balance));

        $exDate = $this->http->FindSingleNode("//text()[{$this->eq('Mileage subject to expire')}]/following::text()[normalize-space()][1]", null, true, "/of (.+)/");
        $st->setExpirationDate($this->normalizeDate($exDate));
        $exbalance = $this->http->FindSingleNode("//text()[{$this->eq('Mileage subject to expire')}]/following::text()[normalize-space()][2]", null, true, "/^\s*(\d+[, \.\d]*)\s*$/");
        $st->addProperty('ExpiringBalance', str_replace(',', '', $exbalance));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains('sent to Asiana Club members')}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            // January 1, 2024 (KST)
            // January 8, 2024 KST (Korea Standard Time)
            '#^\s*(\w+) (\d{1,2}), (\d{4}) (?:\([A-Z]{3,4}\)|[A-Z]{3,4} \([[:alpha:] ]+\))\s*$#',
            // January 1, 2024 00:00 (KST)
            '#^\s*(\w+) (\d{1,2}), (\d{4}) (\d{1,2}:\d{2})\s*\([A-Z]{3,4}\)\s*$#',
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
