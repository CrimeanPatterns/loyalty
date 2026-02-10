<?php

namespace AwardWallet\Engine\bjspremier\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LookWhatWeFound extends \TAccountChecker
{
    public $mailFiles = "bjspremier/statements/it-75513580.eml, bjspremier/statements/it-76204425.eml";
    public $subjects = [
        "/Look what we found$/u",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@emails.bjs.com') !== false) {
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
        return $this->http->XPath->query('//text()[contains(normalize-space(), "BJ\'s Wholesale Club")]')->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hello'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('My Membership'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emails\.bjs\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]", null, true, "/^{$this->opt($this->t('Hello,'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
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
