<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GetStatusNow extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-558965577.eml, aa/statements/it-69181263.eml";
    public $subjects = [
        '/^(?:Get|Secure)\D+status now$/u',
        '/^(You can still| Don\'t forget to) boost to .+ status for \d{4}$/u',
    ];

    public $lang = 'en';
    public $detect = [
        'en' => [
            ['Your', 'status is set to expire on'],
            ['limited-time offer to boost to', 'status through'],
            ['this opportunity to boost to', 'status at a lower price'],
        ],
    ];

    public static $dictionary = [
        "en" => [
            'Elite status:' => ['Elite status:', 'Status:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@loyalty.ms.aa.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AAdvantage')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Award miles:'))}]/following::text()[normalize-space()][2][{$this->contains($this->t('Elite status:'))}]")->length > 0
        ) {
            foreach ($this->detect as $lang => $detects) {
                foreach ($detects as $d) {
                    if (is_array($d) && count($d) == 2) {
                        if ($this->http->XPath->query("//text()[{$this->contains($d[0])}]/ancestor::*[position() < 3][{$this->contains($d[1])}]")->length > 0) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loyalty\.ms\.aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\s*(\w+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name));
        }

        $number = $this->http->FindSingleNode("//text()[normalize-space()='Award miles:']/preceding::text()[starts-with(normalize-space(), '#')]");

        if (preg_match('/^\s*#\s*([A-Z\d]{5,10})$/', $number, $m)) {
            // #23CWT10
            $st
                ->setNumber($m[1])
                ->setLogin($m[1]);
        } elseif (preg_match('/^\s*#\s*([A-Z\d]{1,9})[*]+$/', $number, $m)) {
            // #23C****
            $st
                ->setNumber($m[1])->masked('right')
                ->setLogin($m[1])->masked('right');
        } elseif (preg_match('/^\s*#\s*[*]+([A-Z\d]{1,9})$/', $number, $m)) {
            // #****23C
            $st
                ->setNumber($m[1])->masked()
                ->setLogin($m[1])->masked();
        } else {
            $st->setNumber(null); // for 100% fail
        }

        // $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+[@]\S+\.[a-z]+)/s");
        //
        // if (!empty($login)) {
        //     $st->setLogin($login);
        // }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Elite status:'))}]/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $balance = $this->http->FindSingleNode("//text()[normalize-space()='Award miles:']/following::text()[normalize-space()][1]");
        $st->setBalance(str_replace(",", "", $balance));

        /* this is status exp date
        $expDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Expires')]", null, true, "/{$this->opt($this->t('Expires'))}\s*(\w+\s+\d+\,\s+\d{4})/");

        if (!empty($expDate)) {
            $st->setExpirationDate(strtotime($expDate));
        }
        */

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

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+\s+\w+\s+\d{4})\s*$#u',
        ];
        $out = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }
}
