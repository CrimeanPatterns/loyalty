<?php

namespace AwardWallet\Engine\amazongift\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AllStatement extends \TAccountChecker
{
    public $mailFiles = "amazongift/statements/it-63764852.eml, amazongift/statements/it-64106825.eml, amazongift/statements/it-64119042.eml, amazongift/statements/it-64759726.eml";
    private $lang = '';
    private $reFrom = ['@amazon.com'];
    private $reProvider = ['amazon', 'Amazon'];
    private $reSubject = [
        'Payment Declined: Revise the payment now to complete your Amazon',
        'Your Amazon',
        'Your AmazonSmile order #',
    ];
    private $reBody = [
        'en' => [
            ['View your orders', 'Order #'],
            [' item has shipped.', 'Shipping Confirmation'],
            ['your package will arrive:', 'Your invoice can be accessed'],
            ['Thank you for shopping with us. You ordered', 'Order Confirmation'],
            ['re writing to let you know that the payment for the item listed below has been declined.', 'Order #'],
            ['90 day free trial of Amazon Music Unlimited', 'As part of your recent'],
            [', your package will arrive:', 'Order #'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Hi ' => ['Dear ', 'Hi ', 'Hello '],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            return $email;
        }
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Hi '))}])[1]", null,
            false, "/^{$this->opt($this->t('Hi '))}\s*([[:alpha:]\s.\-]{1,}),/u");

        if (!$name) {
            $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Hi '))}])[1]", null,
                false, "/\s+{$this->opt($this->t('Hi '))}\s*([[:alpha:]\s.\-]{1,}),\s+/u");
        }

        if ($name) {
            $st->addProperty('Name', $name);
            $st->setMembership(true);
            $st->setNoBalance(true);
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
//        if ($this->http->XPath->query("//img[{$this->contains('Mileage Balance', '@alt')}]")->length == 0) {
//            return false;
//        }
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
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
