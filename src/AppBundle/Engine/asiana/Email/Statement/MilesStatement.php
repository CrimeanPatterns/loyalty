<?php

namespace AwardWallet\Engine\asiana\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MilesStatement extends \TAccountChecker
{
    public $mailFiles = "asiana/statements/it-63940445.eml";
    private $lang = '';
    private $reFrom = ['iclub@flyasiana.com'];
    private $reProvider = ['Asiana Airlines'];
    private $reSubject = [
        '(Asiana Airlines) This is your Mileage balance as of',
        '마일리지 현황 및 금호리조트 깜짝 할인 혜택 확인하세요!',
    ];
    private $reBody = [
        'en' => [
            ['This e-mail has been sent to Asiana Club members who have', 'Unsubscribe'],
        ],
        'ko' => [
            ['본 메일은 정보통신망 이용촉진 및 정보보호 등에 관한 법률에 의거', '수신거부'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'miles' => 'Mileage Balance',
        ],
        'ko' => [
            'miles'                                                     => '사용가능 마일리지',
            'This e-mail has been sent to Asiana Club members who have' => '본 메일은 정보통신망 이용촉진 및 정보보호 등에 관한 법률에 의거',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->debug("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $text = $this->http->FindNodes("//text()[{$this->eq($this->t('miles'))}]/ancestor::tr[2]/preceding-sibling::tr[1]//text()[normalize-space()]");

        if (!$text) {
            $text = $this->http->FindNodes("//img[{$this->contains($this->t('miles'), '@alt')}]/ancestor::tr[2]/preceding-sibling::tr[1]//text()[normalize-space()]");
        }

        $text = join("\n", $text);
        $this->logger->debug($text);

        /*
        김진용님
        JINRYONG KIM
        OZ 315482***
         */
        if (preg_match("/\s+([[:alpha:]\s.\-]{2,})\s+(OZ\s*\d+\*+)/", $text, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setLogin($m[2])->masked('right');
            $st->setNumber($m[2])->masked('right');
        }

        /*
         사용가능 마일리지
        2020년 7월 31일 기준
        52,688
         */
        $text = $this->http->FindNodes("//text()[{$this->eq($this->t('miles'))}]/ancestor::tr[1]//text()[normalize-space()]");
        $text = join("\n", $text);
        $this->logger->debug($text);

        // ko, en
        if (preg_match("/^{$this->opt($this->t('miles'))}\s+(.+?)\s+([\d,.\s]+)$/u", $text, $m)) {
            $st->setBalanceDate($this->normalizeDate($m[1]));
            $st->setBalance(str_replace(',', '', $m[2]));
            $st->setMembership(true);
        } else {
            // en
            // Mileage Balance (as of Apr. 30, 2020 KST)
            $date = $this->http->FindSingleNode("//img[{$this->contains($this->t('miles'), '@alt')}]/@alt");

            if (preg_match("/{$this->opt($this->t('miles'))}\s+(.+)/u", $date, $m)) {
                $st->setBalanceDate($this->normalizeDate($m[1]));
                $balance = $this->http->FindSingleNode("//img[{$this->contains($this->t('miles'), '@alt')}]/ancestor::tr[1]//text()[normalize-space()]",
                    null, false, self::BALANCE_REGEXP);
                $st->setBalance(str_replace(',', '', $balance));
                $st->setMembership(true);
            }
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
            // (as of Feb.29, 2020 KST)
            // (as of Jul. 31, 2020 KST)
            '#^\(.+?([[:alpha:]]{3,})\.?\s*(\d+), (\d{4}).+?\)$#',
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
