<?php

namespace AwardWallet\Engine\aplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NameOnly extends \TAccountChecker
{
    public $mailFiles = "aplus/statements/it-78462116.eml, aplus/statements/it-78469214.eml";
    public $subjects = [
        '/Welcome\s*to\s*ALL\s*\–s\s*Accor\s*Live\s*Limitless/iu',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'detectBody' => [
                'We are delighted to welcome you as a member of',
                'Get ready to make every trip one to remember',
                'Your Status has changed',
            ],

            'Status' => ['Status', 'STATUS', 'tatus', 'TATUS', 'Statut', 'ESTATUS', 'Статусные', 'Accor Plus',
                //zh
                '状态 状态',
                '状态',
                // pl, pt
                'STATUS',
                // it
                'LIVELLO',
                // id
                'Status',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@confirmation\.accor\-mail\.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Accor Live Limitless')]")->length > 0
            && $this->http->XPath->query("//img[contains(@alt, 'ALL - ACCOR LIVE LIMITLESS')]")->length > 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Dear')]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('detectBody'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Status'))}]")->length == 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]confirmation\.accor\-mail\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/ancestor::*[1]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $st->setNoBalance(true);

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
