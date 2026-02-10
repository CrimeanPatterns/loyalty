<?php

namespace AwardWallet\Engine\copaair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MilesBalance extends \TAccountChecker
{
    public $mailFiles = "copaair/statements/it-210110528.eml, copaair/statements/it-63825544.eml";
    public $subjects = [
        '/Your miles balance through/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Copa Airlines' => ['Copa Airlines', 'ConnectMiles'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.connectmiles.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Copa Airlines'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('AWARD MILES:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('QUALIFYING MILES:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('QUALIFYING SEGMENTS:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.connectmiles\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, '!'));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/following::text()[{$this->starts($this->t('ConnectMiles'))}][1]", null, true, "/{$this->opt($this->t('ConnectMiles'))}\s*[#]([\dA-Z]+)/u");
        $number = preg_replace("/^[X]+/", "", $number);
        $st->setNumber($number)->masked('left')
            ->setLogin($number)->masked('left');

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('AWARD MILES:'))}]//ancestor::td[1]", null, true, "/{$this->opt($this->t('AWARD MILES:'))}\s*([\d\,]+)/");
        $st->setBalance(str_replace(",", "", $balance));

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Status:'))}]/following::text()[normalize-space()][1]");
        $st->addProperty('Status', $status);

        $balanceDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Summary'))}]", null, true, "/\d+\/\d+\/\d{4}/");
        $st->setBalanceDate(strtotime($balanceDate));

        $expDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Miles expire:'))}]", null, true, "/\s*\d+\/\d+\/\d{4}/");

        if (!empty($expDate)) {
            $st->setExpirationDate(strtotime($expDate));
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
}
