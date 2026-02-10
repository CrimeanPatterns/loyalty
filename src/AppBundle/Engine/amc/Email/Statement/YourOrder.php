<?php

namespace AwardWallet\Engine\amc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "amc/statements/it-76303731.eml";
    public $subjects = [
        '/our AMC Order Number \d+ from \d+\/\d+\/\d{4}$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'AMC Stubs Insider Number:' => ['AMC Stubs Insider Number:', 'AMC Stubs Premiere Number:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.amctheatres.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Keep your AMC Stubs')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank You For Your Order'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Order Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.amctheatres\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Member Name:'))}]/following::text()[normalize-space()][1]");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('AMC Stubs Insider Number:'))}]/following::text()[normalize-space()][1]");
        $st->setNumber($number);

        $expDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership Expiration Date:'))}]/following::text()[normalize-space()][1]");

        if (!empty($expDate) && !empty($st->getBalance() > 0)) {
            $st->setExpirationDate(strtotime($expDate));
        }

        /*$info = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Thank You For Your Order'))}]/preceding::text()[normalize-space()][string-length() > 2][1]");
        $this->logger->error($info);
        if (preg_match("/^(\D+)\s\-\s\#(\d+)$/u", $info, $m)){
            $st->addProperty('Name', trim($m[1], ','));
            $st->setNumber($m[2]);
        }*/

        $st->setNoBalance(true);

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

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
