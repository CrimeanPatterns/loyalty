<?php

namespace AwardWallet\Engine\alaskaair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourStatement extends \TAccountChecker
{
    public $mailFiles = "alaskaair/statements/it-100537633.eml, alaskaair/statements/it-100556332.eml, alaskaair/statements/it-100617265.eml, alaskaair/statements/it-61502984.eml, alaskaair/statements/it-62646373.eml, alaskaair/statements/it-62795448.eml, alaskaair/statements/it-688572429.eml, alaskaair/statements/it-76432030.eml, alaskaair/statements/it-76527050.eml";
    private $lang = '';
    private $reFrom = ['.alaskaair.com'];
    private $reProvider = ['Mileage Plan'];
    private $reSubject = [
        'Statement',
        'summary',
    ];
    private $reBody = [
        'en' => [
            ['Statement:', 'REDEEM MILES', 'If you no longer wish to receive Alaska Airlines promotional communications'],
            ['Statement as of', 'Elite-qualifying miles (EQMs):', 'Sign in'],
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

        $balance = str_replace(',', '', $this->http->FindSingleNode("(//td[{$this->eq($this->t('miles'))}])/ancestor::tr[1]/preceding-sibling::tr[1]",
            null, false, self::BALANCE_REGEXP));

        if ($balance == null) {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//text()[{$this->starts($this->t('You have'))} and {$this->contains($this->t('miles'))}]",
                null, false, "/^\s*You have (\d[,\d]*) miles\.?\s*$/"));
        }

        if ($balance == null) {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//text()[{$this->starts($this->t('It looks like you have'))}]/ancestor::*[position()<4][{$this->contains($this->t('miles in your account right now'))}][1]",
                null, false, "/^\s*It looks like you have (\d[,\d]*) miles in your account/"));
        }

        if ($balance == null) {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//text()[{$this->contains($this->t('Statement:'))}]/following::text()[normalize-space()][1][{$this->contains($this->t('miles'))}]",
                null, false, "/^\s*(\d[,\d]*) miles\s*$/"));
        }

        if ($balance == null && !empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('It looks like you don\'t have any miles in your account right now.'))}]"))) {
            $balance = 0;
        }

        if ($balance == null) {
            $balance = str_replace(',', '', $this->http->FindSingleNode("(//text()[{$this->contains(['you have', 'earned'])}]/ancestor::*[position() < 3][{$this->contains(['you have', 'earned'])} and {$this->contains($this->t('miles'))}])[1]",
                null, false, "/^\s*[[:alpha:] \-]+,.*?\s*you(?: have|’ve earned) (\d[,\d]*) miles[.?]?\s*$/"));
        }

        if ($balance !== null && $balance !== '') {
            $st->setBalance($balance);
            $st->setMembership(true);
        }

        $str = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('xxxx'))} and {$this->contains($this->t('|'))}])[1]/ancestor::td[1]");

        // Hi, Leigh | Member | xxxx6823
        if (preg_match('/Hi, (.+?) \| (\w{3,}) \| \w{5,}$/', $str, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->addProperty('Status', $m[2]);
            $st->setLogin($m[1])->masked();
            $st->setNumber($m[1])->masked();
        }

        $str = $this->http->FindSingleNode("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/following-sibling::tr[contains(.,'|')][count(.//text()[normalize-space()]) = 1]");

        if (preg_match('/^\s*x{3,}(\d{4}) +\| +([\w ]+)$/', $str, $m)) {
            $st->addProperty('Name', $this->http->FindSingleNode("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/descendant::text()[normalize-space()][1]"));
            $st->addProperty('Status', $m[2]);
            $st->setLogin($m[1])->masked();
            $st->setNumber($m[1])->masked();
        }

        $str = $this->http->FindSingleNode("(//text()[normalize-space() = 'Sign In']/ancestor::tr[1][starts-with(normalize-space(), 'Hi')])[1]");

        if (preg_match('/Hi, ([[:alpha:] \-]+?)\s*\| Sign In\s*(?:Mileage|MVP).*?: x{3,}(\d{4})$/', $str, $m)) {
            // Hi, Angela | Sign In  Mileage Plan™ Member: xxxx2491
            $st->addProperty('Name', $m[1]);
            $st->setLogin($m[2])->masked();
            $st->setNumber($m[2])->masked();
        }
        $str = $this->http->FindSingleNode("(//tr[starts-with(normalize-space(), 'Hi,') and not(.//tr) and (contains(., 'Mileage Plan™ Member:') or contains(., 'MVP Gold 75K'))])[1]");

        if (preg_match('/Hi, ([[:alpha:] \-]+?)\s*(?:Mileage|MVP).*?: x{3,}(\d{4})$/', $str, $m)) {
            // Hi, Jaesik Mileage Plan™ Member: xxxx2685
            $st->addProperty('Name', $m[1]);
            $st->setLogin($m[2])->masked();
            $st->setNumber($m[2])->masked();
        }

        if (!empty($st->getProperties()['Name']) && !empty($st->getNumber()) && $st->getBalance() == null) {
            $xpath = "//text()[contains(., '" . $st->getNumber() . "') and not(ancestor::*[contains(@style, 'display:none') or contains(@style, 'display: none')] )]/following::text()[normalize-space()][ not(ancestor::*[contains(@style, 'display:none') or contains(@style, 'display:none')] )][position() < 10]";
            $regexp = "/.*(?:\d ?(?:\w+ ?)mile|mile( ?\w+) ?\d+|^\D{0,4}mile\D{0,4}\s*$).*/";
            $cond = array_filter($this->http->FindNodes($xpath, null, $regexp));
            $cond = array_filter($cond, function ($v) { if (preg_match("/Get [^.!]miles/", $v)) { return true; } else { return false; }});

            if (empty($cond)) {
                $st->setNoBalance(true);
            }
        }

        $str = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi, ')]/ancestor::tr[1]");

        if (preg_match("/Hi\,\s*(?<name>\D+)\s*\|\s*(?<balance>[\d\.\,]+)\s*miles/", $str, $m)) {
            $st->addProperty('Name', $m['name']);
            $st->setBalance(str_replace(',', '', $m['balance']));
        } elseif (preg_match("/Hi\,\s*(?<name>\D+)\s+\|\s+(?<status>.+)\s+\|\s+oneworld/", $str, $m)) {
            $st->addProperty('Name', $m['name']);
            $st->addProperty('Status', $m['status']);
            $miles = $this->http->FindSingleNode("//text()[contains(normalize-space(), '| Miles:')]/ancestor::tr[1]", null, true, "/\|\s+{$this->opt($this->t('Miles:'))}\s*([\d\,\.]+)/");

            if ($miles !== null) {
                $st->setBalance(str_replace(',', '', $miles));
            }
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'MP#:')]/ancestor::tr[1]", null, true, "/^MP[#]\:\s*([x\d]+)\s+\|/");

        if (preg_match("/^[x]{3,4}\d+$/", $login, $m)) {
            $st->setLogin($login)->masked();
        }

        $dateBalance = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Statement as of')]", null, true, "/{$this->opt($this->t('Statement as of'))}\s*([\d\/]+)\./");

        if (!empty($dateBalance)) {
            $st->setBalanceDate(strtotime($dateBalance));
        }

        $partnersMiles = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Elite-qualifying miles (EQMs):')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Elite-qualifying miles (EQMs):'))}\s*([\d\.\,]+)$/");

        if ($partnersMiles !== null) {
            $st->addProperty('PartnerMiles', $partnersMiles);
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
        foreach ($this->reBody as $lang => $value) {
            foreach ($value as $val) {
                if ($this->http->XPath->query("//text()[{$this->contains($val[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($val[1])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($val[2])}]")->length > 0) {
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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
