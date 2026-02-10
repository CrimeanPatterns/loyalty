<?php

namespace AwardWallet\Engine\aeroflot\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Card extends \TAccountChecker
{
    public $mailFiles = "aeroflot/statements/it-83385894.eml";
    public $subjects = [
        '/Your Aeroflot Bonus card delivery/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aeroflot.ru') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Aeroflot Bonus')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This message was generated automatically'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('has been sent to the post address'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aeroflot\.ru$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)\!$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('card #'))}]", null, true, "/{$this->opt($this->t('card #'))}\s*(\d+)/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your'))}]", null, true, "/^{$this->opt($this->t('Your'))}\s+([A-Z]+)/");

        if (!empty($status)) {
            $st->addProperty('Level', $status);
        }

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
}
