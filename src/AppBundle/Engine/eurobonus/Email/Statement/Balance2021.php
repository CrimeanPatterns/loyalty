<?php

namespace AwardWallet\Engine\eurobonus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Balance2021 extends \TAccountChecker
{
    public $mailFiles = "eurobonus/statements/it-109897838.eml, eurobonus/statements/it-64033341.eml, eurobonus/statements/it-70202651.eml";
    private $lang = '';

    private $reFrom = ['@flysas.com'];
    private $reProvider = ['SAS EuroBonus'];

    private $reSubject = [
        'Welcome to SAS EuroBonus',
        'Welcome to another year as a SAS EuroBonus member',
    ];
    private static $dictionary = [
        'en' => [
            // header
            'point' => 'point',
            'Last updated' => 'Last updated',
            // body
            'MY EUROBONUS' => ['MY EUROBONUS', 'My EuroBonus'],
            'Your qualification period:' => 'Expiry date:',
            'Expiry date:' => 'Expiry date:',
            'To the next level:' => 'To the next level:',
            'Your SAS EuroBonus team.' => ['Your SAS EuroBonus team.', 'Best wishes from SAS EuroBonus'],
            // footer
            'If you no longer wish to receive emails from SAS to' => 'If you no longer wish to receive emails from SAS to',
        ],
        'sv' => [
            // header
            'point' => 'poäng',
            'Last updated' => 'Uppdaterat',
            // body
//            'MY EUROBONUS' => ['MY EUROBONUS', 'My EuroBonus'],
//            'Your qualification period:' => 'Expiry date:',
//            'Expiry date:' => 'Expiry date:',
//            'To the next level:' => 'To the next level:',
            'Your SAS EuroBonus team.' => '',
            // footer
            'If you no longer wish to receive emails from SAS to' => 'Om du inte längre önskar få dessa mejl från SAS till mejladressen',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $st = $email->add()->statement();

        $logoSrc = ['SAS-logo-header.png', 'SAS-Logo_NEG@2x.png'];
        $name = implode("\n",
            $this->http->FindNodes("//img[".$this->contains($logoSrc, '@src')."]/ancestor::td[1]/preceding-sibling::td[1]//text()[normalize-space()]"));

        if (preg_match('/^([[:alpha:]\s.\-]{3,})\s+([A-Z]{3}\s*\d+)$/', $name, $m)) {
            $st->addProperty('Name', preg_replace('/\n(.+?)$/', '', $m[1]));
            $st->setLogin($m[2]);
            $st->setNumber($m[2]);

            $val = $this->http->FindSingleNode("//img[".$this->contains($logoSrc, '@src')."]/ancestor::td[1]/following-sibling::td[1]//text()[{$this->contains($this->t('point'))}]",
                null, false, '/^([\d\s.,]+) '.$this->opt($this->t('point')).'/');

            if ($val !== null) {
                $st->setBalance(str_replace(' ', '', $val));
            } else {
                $st->setNoBalance(true);
            }

            $dateOfBalance = $this->http->FindSingleNode("//text()[".$this->starts($this->t('Last updated'))."]", null, true, "/{$this->opt($this->t('Last updated'))}\s*(\d+\.\d+\.\d{4})/");

            if (!empty($dateOfBalance)) {
                $st->setBalanceDate(strtotime($dateOfBalance));
            }

            $expInfo = $this->http->FindSingleNode("//text()[".$this->starts($this->t('Expiry date:'))."]/ancestor::tr[1]");

            if (preg_match("/([\d\s]+)\s*".$this->opt($this->t('point'))." [[:alpha:]]+\s*(\d+\.\d+\.\d{4})/u", $expInfo, $m)) {
                $st->addProperty('ExpiringBalance', $m[1]);
                $st->setExpirationDate(strtotime($m[2]));
            }

            $qualPeriod = $this->http->FindSingleNode("//text()[".$this->starts($this->t('Your qualification period:'))."]/ancestor::tr[1]",
                null, true, "/{$this->opt($this->t('Your qualification period:'))}\s*(.+)/");

            if (!empty($qualPeriod)) {
                $st->addProperty('QualifyingPeriod', $qualPeriod);
            }

            $login = $this->http->FindSingleNode("//text()[".$this->starts($this->t('If you no longer wish to receive emails from SAS to'))."]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('If you no longer wish to receive emails from SAS to'))}\s*(\S+[@]\S+\.\S+)/");

            if (!empty($login)) {
                $st->setLogin($login);
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

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['MY EUROBONUS']) && !empty($dict['Expiry date:']) && !empty($dict['To the next level:'])) {
                if ($this->http->XPath->query("//text()[".$this->eq($dict['MY EUROBONUS'])."][following::text()[".$this->starts($dict['Expiry date:'])."] and following::text()[".$this->starts($dict['To the next level:'])."]]")->length > 0) {
                     return true;
                }
            }
            if (!empty($dict['Your SAS EuroBonus team.']) && $this->http->XPath->query("//text()[".$this->eq($dict['Your SAS EuroBonus team.'])."]")->length > 0) {
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
        return 0;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['MY EUROBONUS']) && !empty($dict['Expiry date:']) && !empty($dict['To the next level:'])) {
                if ($this->http->XPath->query("//text()[".$this->eq($dict['MY EUROBONUS'])."][following::text()[".$this->starts($dict['Expiry date:'])."] and following::text()[".$this->starts($dict['To the next level:'])."]]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
            if (!empty($dict['Your SAS EuroBonus team.']) && $this->http->XPath->query("//text()[".$this->eq($dict['Your SAS EuroBonus team.'])."]")->length > 0) {
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

    private function contains($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ",'" . $s . "')";
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
