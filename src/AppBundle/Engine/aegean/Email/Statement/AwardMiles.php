<?php

namespace AwardWallet\Engine\aegean\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AwardMiles extends \TAccountChecker
{
    public $mailFiles = "aegean/statements/it-243886963.eml, aegean/statements/it-244044807.eml, aegean/statements/it-245032412.eml, aegean/statements/it-74835457.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            // блок в конце письма
            'Award Miles' => 'Award Miles',
            'Member ID:'  => 'Member ID:',
            // строка под шапкой
            'FIRST NAME:' => 'FIRST NAME:',
            //            'LAST NAME:' => '',
            'MEMBER ID:' => 'MEMBER ID:',
            // блок под шапкой
            'Award miles' => 'Award miles',
            'Member ID'   => 'Member ID',
            'Tier'        => 'Tier',
        ],
        "el" => [
            //            // блок в конце письма
            //            'Award Miles' => 'Award Miles',
            //            'Member ID:' => 'Member ID:',
            //            // строка под шапкой
            'FIRST NAME:' => 'ΟΝOMA:',
            'LAST NAME:'  => 'ΕΠΙΘΕΤΟ:',
            'MEMBER ID:'  => 'ΑΡ. ΛΟΓΑΡΙΑΣΜΟΥ:',
            //            // блок под шапкой
            //            'Award Miles' => 'Award Miles',
            'Member ID:'   => 'Αριθμός λογαριασμού:',
            'Award miles:' => 'Μίλια εξαργύρωσης:',
            'My account'   => 'Ο λογαριασμός μου',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'miles.bonus@aegeanair.com') !== false || stripos($headers['from'], 'newsletter@news.aegeanair.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.aegeanair.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Award Miles']) && !empty($dict['Member ID:'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Award Miles'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Member ID:'])}]")->length > 0) {
                return true;
            }

            if (!empty($dict['FIRST NAME:']) && !empty($dict['MEMBER ID:'])
                && $this->http->XPath->query("//*[{$this->contains($dict['FIRST NAME:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['MEMBER ID:'])}]")->length > 0) {
                return true;
            }

            if (!empty($dict['Award miles']) && !empty($dict['Tier'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Award miles'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Tier'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aegeanair.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Award Miles']) && !empty($dict['Member ID:'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Award Miles'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Member ID:'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }

            if (!empty($dict['FIRST NAME:']) && !empty($dict['MEMBER ID:'])
                && $this->http->XPath->query("//*[{$this->contains($dict['FIRST NAME:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['MEMBER ID:'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }

            if (!empty($dict['Award miles']) && !empty($dict['Tier'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Award miles'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Tier'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }

            if (!empty($dict['My account']) && !empty($dict['Award miles:'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Award miles:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['My account'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        $text = trim(implode("\n", $this->http->FindNodes("//tr[not(.//tr)][td[1][" . $this->eq($this->t("FIRST NAME:")) . "] and td[5][" . $this->eq($this->t("MEMBER ID:")) . "]]/*")));

        if (!empty($text)) {
            if (preg_match("/" . $this->opt($this->t("FIRST NAME:")) . "\s*(.+?)\s+" . $this->opt($this->t("LAST NAME:")) . "\s*(.+?)\s+" . $this->opt($this->t("MEMBER ID:")) . "\s*(\d{5,})\s*$/", $text, $m)) {
                $st->addProperty('Name', $m[1] . ' ' . $m[2]);
                $st->setNumber($m[3])
                    ->setLogin($m[3]);
                $st->setNoBalance(true);
            }

            return $email;
        }
        $name = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Tier")) . "]/ancestor::tr[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Tier")) . "]]/preceding-sibling::tr[normalize-space()]"));
        $text = trim(implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Tier")) . "]/ancestor::tr[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Tier")) . "]][preceding-sibling::tr[normalize-space()]]//td[not(.//td)][normalize-space()]")));

        if (preg_match("/" . $this->opt($this->t("Tier")) . "\s*(.+?)\s+" . $this->opt($this->t("Member ID")) . "\s*(\d{5,})\s+" . $this->opt($this->t("Award miles")) . "\s*(\d.+)\s*$/", $text, $m)) {
            $st->addProperty('CardLevel', $m[1]);
            $st->addProperty('Name', $name);
            $st->setNumber($m[2])
                ->setLogin($m[2]);
            $st->setBalance(str_replace([",", '.'], "", $m[3]));

            return $email;
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\s+(\D+)\,/");

        if (empty($name)) {
            //                                              Member ID: 155998113
            //            JACOB MCGINTY                     Award miles: 3,500
            //                                              Tier: Blue
            $name = $this->http->FindSingleNode("(//tr[count(.//text()[normalize-space()]) = 7][descendant::text()[normalize-space()][2][{$this->eq($this->t('Member ID:'))}] and descendant::text()[normalize-space()][4][{$this->eq($this->t('Award miles:'))}] and descendant::text()[normalize-space()][6][{$this->eq($this->t('Tier:'))}]])[1]/descendant::text()[normalize-space()][1]");
        }

        if (empty($name)) {
            //         ECE
            //        EYMUR
            //  Member ID: 154799293
            //   Award miles: 1,000
            //       My account
            $name = implode(' ', $this->http->FindNodes("(//tr[count(.//text()[normalize-space()]) = 7][descendant::text()[normalize-space()][3][{$this->eq($this->t('Member ID:'))}] and descendant::text()[normalize-space()][5][{$this->eq($this->t('Award miles:'))}] and descendant::text()[normalize-space()][7][{$this->eq($this->t('My account'))}][ancestor::a[contains(@href, '.aegeanair.com')]]])[1]/descendant::text()[normalize-space()][position() < 3]"));
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $tier = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tier:'))}]/following::text()[normalize-space()][1]", null, true, "/^[\s[:alpha:]]+$/");

        if (!empty($tier)) {
            $st->addProperty('CardLevel', $tier);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member ID:'))}]//ancestor::*[1]", null, true, "/^{$this->opt($this->t('Member ID:'))}\s*(\d{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number)
                ->setLogin($number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Award Miles'))}]/preceding::text()[normalize-space()][1]", null, true, "/.*\d.*/");

        if (empty($balance)) {
            $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Award miles:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*\d[\d,. ]*\s*$/");
        }
        $st->setBalance(str_replace([",", '.'], "", $balance));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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
