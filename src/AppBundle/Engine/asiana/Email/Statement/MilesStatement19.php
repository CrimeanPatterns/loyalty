<?php

namespace AwardWallet\Engine\asiana\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MilesStatement19 extends \TAccountChecker
{
    public $mailFiles = "asiana/statements/it-63957035.eml";
    private $lang = '';
    private $reFrom = ['iclub@flyasiana.com'];
    private $reProvider = ['Asiana Airlines'];
    private $reSubject = [
        '(Asiana Airlines) This is your Mileage balance as of',
    ];
    private $reBody = [
        'en' => [
            ['This e-mail has been sent to Asiana Club members who have', 'Unsubscribe'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $str = $this->http->FindSingleNode("//img[{$this->eq('To.', '@alt')}]/ancestor::td[1]/following-sibling::td[1]");
        // JUHEE KIM(606319***)
        if (preg_match('/^(.+?)\s*\((\d+\*+)\)/', $str, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setLogin($m[2])->masked('right');
            $st->setNumber($m[2])->masked('right');
        }
        $st->addProperty('EliteStatus', $this->http->FindSingleNode("//img[{$this->eq('Your current Asiana Club membership tier is', '@alt')}]/ancestor::td[1]/following-sibling::td[1]"));
        // Mileage Balance (as of June. 30, 2019 KST)
        $date = $this->http->FindSingleNode("//img[{$this->contains('Mileage Balance', '@alt')}]/@alt");

        if ($date = $this->http->FindPreg("/of (.+?)\)/", false, $date)) {
            $st->setBalanceDate($this->normalizeDate($date));

            $balance = $this->http->FindSingleNode("//img[{$this->contains('Mileage Balance', '@alt')}]/ancestor::td[1]/following-sibling::td[1]", null, false, self::BALANCE_REGEXP);
            $st->setBalance(str_replace(',', '', $balance));
            $st->setMembership(true);
        }

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
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
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
        if ($this->http->XPath->query("//img[{$this->contains('Mileage Balance', '@alt')}]")->length == 0) {
            return false;
        }

        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                $this->logger->debug($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length);

                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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
            // 2020년 7월 31일 기준
            '#^(\d{4})년\s*(\d+)월\s*(\d+)일 기준$#',
            // Apr. 30, 2020 KST
            '#^(\w+)\. (\d+), (\d{4}).+?$#',
        ];
        $out = [
            "$1-$2-$3",
            "$1 $2, $3",
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
