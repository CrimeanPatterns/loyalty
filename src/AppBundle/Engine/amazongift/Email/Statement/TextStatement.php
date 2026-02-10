<?php

namespace AwardWallet\Engine\amazongift\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TextStatement extends \TAccountChecker
{
    public $mailFiles = "amazongift/statements/it-64770041.eml, amazongift/statements/it-64777876.eml";
    private $lang = '';
    private $reFrom = ['@amazon.com'];
    private $reProvider = ['amazon', 'Amazon'];
    private $reSubject = [
        'Your AmazonSmile order #',
    ];
    private $reBody = [
        'en' => [
            [', your package will arrive:', 'Track your package:'],
            ['Your order of 1 item has shipped.', 'Track your package at:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Hi ' => ['Dear ', 'Hi ', 'Hello '],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang($parser)) {
            return $email;
        }
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $name = $this->http->FindPreg("/\s+{$this->opt($this->t('Hi '))}\s*([[:alpha:]\s.\-]{1,}),\s+/u", false, $parser->getBodyStr());

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
        $html = $parser->getHTMLBody();

        if (!empty($html)) {
            return false;
        }

        if ($this->arrikey($parser->getBodyStr(), $this->reProvider) === false) {
            return false;
        }

        if ($this->assignLang($parser)) {
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

    private function assignLang(PlancakeEmailParser $parser)
    {
//        if ($this->http->XPath->query("//img[{$this->contains('Mileage Balance', '@alt')}]")->length == 0) {
//            return false;
//        }
        $this->logger->debug($parser->getHTMLBody());

        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if (stripos($parser->getBodyStr(), $value[0]) !== false
                    && stripos($parser->getBodyStr(), $value[1]) !== false) {
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
