<?php

namespace AwardWallet\Engine\delta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Weekly extends \TAccountChecker
{
    public $mailFiles = "delta/statements/it-74215810.eml, delta/statements/it-74279004.eml, delta/statements/it-74310889.eml, delta/statements/it-74311650.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'About SkyMiles' => [
                'About SkyMiles',
                'Welcome to SkyMiles',
                'Mileage Balance',
                'EARN MILES',
                'SKYMILES',
                'GO SHOPPING WITH MILES',
                'EARNING MILES',
                'YOUR MILES',
                'Miles Available',
                'NEW CARD BENEFITS',
                'Skymiles',
                'log in with your updated SkyMiles number and password', ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Add to Address Book'))} or {$this->contains($this->t('Manage Account'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('About SkyMiles'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Delta Air Lines'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t\.delta\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//a[{$this->eq($this->t('Add to Address Book'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (empty($info)) {
            $info = $this->http->FindSingleNode("//a[{$this->eq($this->t('Print'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");
        }

        if (empty($info)) {
            $info = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'SkyMiles') and contains(normalize-space(), '#')]");
        }

        if (preg_match("/^(\D+)\:\s*\w+.\s*[#](\d+)$/u", $info, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setNumber($m[2])
                ->setLogin($m[2]);
        } elseif (preg_match("/\s*\w+.\s*[#](\d+)\s*[|]\s*$/u", $info, $m)) {
            $st->setNumber($m[1])
                ->setLogin($m[1]);
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'SkyMiles') and contains(normalize-space(), '#')]/preceding::text()[normalize-space()][1]");

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }
        }

        $balance = $this->http->FindSingleNode("//text()[normalize-space()='Miles Available']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        if (empty($balance) && $balance !== 0) {
            $balance = $this->http->FindSingleNode("//text()[normalize-space()='Miles Available']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        }

        if ($balance !== null) {
            $st->setBalance($balance);
        } else {
            $st->setNoBalance(true);
        }

        $level = $this->http->FindSingleNode("//text()[normalize-space()='Level']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($level)) {
            $st->addProperty('Level', $level);
        }

        $expDate = $this->http->FindSingleNode("//text()[normalize-space()='Mileage Expiration']/ancestor::tr[1]/descendant::td[last()]");
        $this->logger->error($expDate);

        if (!empty($expDate)) {
            $st->setExpirationDate(strtotime($expDate));
        }

        $qm = $this->http->FindSingleNode("//text()[normalize-space()='Medallion® Qualification Miles']/ancestor::tr[1]/preceding::tr[1]/descendant::td[2]");

        if ($qm !== null) {
            $st->addProperty('MedallionMilesYTD', $qm);
        }

        $qs = $this->http->FindSingleNode("//text()[normalize-space()='Medallion® Qualification Segments']/ancestor::tr[1]/preceding::tr[1]/descendant::td[2]");

        if ($qs !== null) {
            $st->addProperty('MedallionSegmentsYTD', $qs);
        }

        $balanceDate = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Account Balance')]/following::text()[contains(normalize-space(), 'As of')][1]", null, true, "/^{$this->opt($this->t('As of'))}\s*(.+)/");

        if (!empty($balanceDate)) {
            $st->setBalanceDate(strtotime($balanceDate));
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
