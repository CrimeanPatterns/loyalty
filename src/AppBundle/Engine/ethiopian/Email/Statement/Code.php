<?php

namespace AwardWallet\Engine\ethiopian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "ethiopian/statements/it-636494958.eml";
    public $subjects = [
        'ShebaMiles login verification',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ethiopianairlines.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Ethiopian Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t("Please use this code to access your ShebaMiles account"))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Verification code:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ethiopianairlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Verification code:')]", null, true, "/^Verification code\:\s*(\d{6})$/");

        if (!empty($code)) {
            $oc = $email->add()->oneTimeCode();
            $oc->setCode($code);
        }

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello')]", null, true, "/Hello\s*(.+)/");

        if (!empty($name)) {
            $st = $email->add()->statement();
            $st->addProperty('Name', trim(preg_replace("/^(?:Mr\.|Mrs\.|Ms\.|MRS|MR|MS)/", "", $name), ','));
            $st->setNoBalance(true);
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
}
