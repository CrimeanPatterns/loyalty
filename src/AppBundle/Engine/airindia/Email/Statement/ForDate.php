<?php

namespace AwardWallet\Engine\airindia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ForDate extends \TAccountChecker
{
    public $mailFiles = "airindia/statements/it-103826960.eml";
    public $subjects = [
        '/Statement for date ending\s*\d+\/\d+\/\d{4}$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@loyaltyplus.aero') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Air India')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Valid points'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Account Status'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loyaltyplus\.aero$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date of Birth'))}]/ancestor::tr[1]/preceding::tr[1]/descendant::td[1]", null, true, "/^[A-Z]+\s*(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $lapsedPoints = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Expired Points'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\d+)\s*[@]/");

        if (!empty($lapsedPoints)) {
            $st->addProperty('LapsedPoints', $lapsedPoints);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date of Birth'))}]/ancestor::tr[1]/preceding::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $tier = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Account Status'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($tier)) {
            $st->addProperty('Tier', preg_replace("/\(.+\)/", "", $tier));
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Valid points'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        if ($balance != null) {
            $st->setBalance($balance);

            $dateOfBalance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Statement Period'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/\s(\d+\/\d+\/\d{4})/");

            if (!empty($dateOfBalance)) {
                $st->setBalanceDate(strtotime(str_replace('/', '.', $dateOfBalance)));
            }
        } else {
            $st->setNoBalance(true);
        }

        $cardValidity = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flying Returns Member')]/ancestor::tr[1]/descendant::td[2]", null, true, "/{$this->opt($this->t('to'))}\s*([\d\/]+)/");

        if (!empty($cardValidity)) {
            $st->addProperty('Cardvalidity', $cardValidity);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Points Expiring']/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $points = $this->http->FindNodes("./descendant::td[not(contains(normalize-space(), 'Points Expiring'))]", $root);
            $i = 2;

            foreach ($points as $point) {
                if ($point != '0') {
                    $st->addProperty('ExpiringBalance', $point);

                    $dateExpiring = $this->http->FindSingleNode("./preceding::tr[1]/descendant::td[$i]", $root);
                    $st->setExpirationDate(strtotime(str_replace('/', '.', $dateExpiring)));
                }
                $i++;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }
}
