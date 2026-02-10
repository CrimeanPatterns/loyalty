<?php

namespace AwardWallet\Engine\brex\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "brex/statements/it-107472753.eml, brex/statements/it-176118856.eml";
    public $subjects = [
        'Your Brex Cash statement for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@brex.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your Brex Cash statement'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your statement from'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View statement'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, '/[@.]brex\.com$/') !== false) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\w+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));

            if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Your statement from') and contains(normalize-space(), 'is ready for you to review and download')]")->length > 0) {
                $st->setNoBalance(true);
            }
        } else {
            $st->setMembership(true);
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
}
