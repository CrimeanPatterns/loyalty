<?php

namespace AwardWallet\Engine\egencia\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Account extends \TAccountChecker
{
    public $mailFiles = "egencia/statements/it-71120332.eml, egencia/statements/it-73287959.eml, egencia/statements/it-75601740.eml, egencia/statements/it-75518968.eml, egencia/statements/it-76999039.eml";

    public $detectFrom = "@mail.egencia.";

    public $detectSubject = [
        // en
        'Egencia - Your request',
        'Welcome to Egencia, your Travel Partner',
        'Egencia – Account Information',
        'Your Passport is expiring soon',
        'Your Passport has expired',
    ];

    public $detectBody = [
        'en' => [
            'Forgotten your password?', 'Forgot a password?',
            'click here to complete your profile and set your travel preferences',
            'Changes to Your Account', // it-76999039.eml
            'We would like to inform you that your Passport is expiring soon',
            'We would like to inform you that your Passport has expired', // it-75518968.eml
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'USERNAME'    => ['USERNAME', 'username', 'ACCOUNT'],
            'EXPIRY DATE' => ['EXPIRY DATE', 'Expiry date'],
            'loginInText' => [
                'You have more than one account associated with', // it-75601740.eml
                'The account below has been linked to your Primary Account', // it-76999039.eml
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $account = null;

        $name = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Dear'))}]", null, true, "/^{$this->preg_implode($this->t('Dear'))}\s+({$patterns['travellerName']})$/u");

        if (!$name) {
            // it-76999039.eml
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->preg_implode($this->t('Dear'))}\s+({$patterns['travellerName']}),$/u");
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $account = $this->http->FindSingleNode("//tr[{$this->eq($this->t('USERNAME'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\S+@\S+\.\S+)\s*$/");

        if (!$account) {
            $account = $this->http->FindSingleNode("//text()[{$this->contains($this->t('loginInText'))}]", null, true, "/{$this->preg_implode($this->t('loginInText'))}\s+(\S+@\S+[^.\s])/");
        }

        if ($account) {
            $st->setLogin($account);
        }

        if ($name || $account) {
            $st->setNoBalance(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getCleanFrom(), $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                foreach (self::$dictionary as $dict) {
                    if (isset($dict['USERNAME']) && $this->http->XPath->query("//tr[{$this->eq($dict['USERNAME'])}]")->length > 0
                        || isset($dict['EXPIRY DATE']) && $this->http->XPath->query("//tr[{$this->eq($dict['EXPIRY DATE'])}]")->length > 0
                    ) {
                        return true;
                    }
                }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->http->log($str);
        $in = [
            //            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu",// Friday, February 9th, 2018
        ];
        $out = [
            //            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
