<?php

namespace AwardWallet\Engine\aquire\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Level extends \TAccountChecker
{
    public $mailFiles = "aquire/statements/it-68385140.eml";
    public $subjects = [
        '/your Qantas Points balance with BP Plus$/',
        '/^Don\’t miss out on double Qantas Points$/',
        '/^NEW\: Earn up to [\d\,]+ Qantas Points on a business loan$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@loyalty.qantas.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Sent by Qantas Airways Limited')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('shown are as at'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('ABN'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Qantas Points Balance'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loyalty\.qantas\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ABN'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        $st->setNumber($number);

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Qantas Points Balance'))}]/following::text()[normalize-space()][1]");
        $st->setBalance(str_replace(',', '', $balance));

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Qantas Points Balance'))}]/preceding::img[1]/@alt", null, true, "/^(level\s*\d+)$/i");

        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $dateOfBalance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View this email in your browser.'))}]/following::text()[{$this->contains($this->t('shown are as at'))}]", null, true, "/(\d+\s*\w+\s*\d{4})$/");

        if (!empty($dateOfBalance)) {
            $st->setBalanceDate(strtotime($dateOfBalance));
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
