<?php

namespace AwardWallet\Engine\bestbuy\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "bestbuy/statements/it-70621487.eml, bestbuy/statements/it-71039136.eml, bestbuy/statements/it-72792154.eml";

    public static $dictionary = [
        'en' => [
            'Member ID:'     => 'Member ID:',
            'View Account >' => ['View Account >', 'View Certificates >'],
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bestbuy.') !== false || stripos($from, '.bestbuy.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict["View Account >"]) && $this->http->XPath->query("//a[position() < 10][" . $this->eq($dict["View Account >"]) . "]")->length > 0) {
                return true;
            }

            if (!empty($dict["Member ID:"]) && $this->http->XPath->query("//text()[" . $this->starts($dict["Member ID:"]) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//a[position()<10][" . $this->eq($this->t("View Account >")) . "]/ancestor::tr[1]");

        if (preg_match("/^\s*(?<name>[A-Z][a-z\-]*(?: [A-Z][a-z\-]*)+\.)\s*\|\s*(?<status>.+)" . $this->preg_implode($this->t("View Account >")) . "/", $info, $m)) {
            $st->setMembership(true);
            $st->addProperty('Name', $m['name']);

            if (stripos($m['status'], 'Elite Plus') !== false) {
                $st->addProperty('Status', 'Elite Plus');
            } elseif (stripos($m['status'], 'Elite') !== false) {
                $st->addProperty('Status', 'Elite');
            }
        }

        $member = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Member ID:")) . "]", null, true, "/:\s*(\d{5,})\s*$/");

        if (!empty($member)) {
            $st->addProperty('Number', $member);
            $account = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Member ID:")) . "]/ancestor::tr[following-sibling::tr[1]//img[contains(@alt, 'Barcode')]]/following-sibling::tr[2]",
                null, true, "/^\s*(\d{10,})\s*$/");
            $st->addProperty('AccountNumber', $account);
            $st->setNumber($account);
        }

        $balance = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Points")) . "]/ancestor::*[" . $this->eq($this->t("PointsAvailable")) . "][1]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d+)\s*$/");

        if (is_numeric($balance)) {
            $st->setBalance($balance);
        } else {
            $balanceinfo = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("points expire on")) . "]");

            if (preg_match("/, your (\d{1,3}(?:[,. ]?\d{3})*)\s*points expire on ([\d\/]{6,})\./", $balanceinfo, $m)) {
                $st->setBalance($m[1]);
                $st->setExpirationDate(strtotime($m[2]));
            } else {
                $st->setNoBalance(true);
            }
        }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
