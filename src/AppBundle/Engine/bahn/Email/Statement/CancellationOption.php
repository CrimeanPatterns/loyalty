<?php

namespace AwardWallet\Engine\bahn\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancellationOption extends \TAccountChecker
{
    public $mailFiles = "bahn/statements/it-65652647.eml, bahn/statements/it-65652662.eml, bahn/statements/it-65652663.eml, bahn/statements/it-65654219.eml";
    public $lang = '';

    public $detectLang = [
        'en' => 'Edit your profile',
        'de' => 'Ihre Einstellungen',
    ];

    public static $dictionary = [
        "en" => [
            //'This email has been sent to' => '',
            //'Your Settings' => '',
            //'Edit your profile' => '',
            //'Unsubscribe' => '',
            //'Dear' => '',
        ],
        "de" => [
            'This email has been sent to' => 'Sie sind mit folgender E-Mail-Adresse registriert',
            'Your Settings'               => 'Ihre Einstellungen',
            'Edit your profile'           => 'E-Mail-Adresse ändern',
            'Unsubscribe'                 => 'Newsletter abbestellen',
            'Dear'                        => ['Herr', 'Frau'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Deutsche Bahn AG')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This email has been sent to'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Settings'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Edit your profile'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Unsubscribe'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Dear'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mailing\.bahn\.de$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setNoBalance(true);

        $login = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('This email has been sent to'))}]/following::text()[{$this->contains($this->t('@'))}][1]"));

        if (count($login) == 1) {
            $st->setLogin($login[0]);
        }

        $text = array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ihre Einstellungen')]/following::b[1]"));

        if (count($text) == 1) {
            if (preg_match("/^\,\s*{$this->opt($this->t('Dear'))}\s+(\D+)\s*(\d{6})?\:$/", $text[0], $m)) {
                $st->addProperty('Name', $m[1]);

                if (isset($m[2])) {
                    $st->setNumber($m[2]);
                }
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
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

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detect) {
            if (is_array($detect)) {
                foreach ($detect as $word) {
                    if (stripos($body, $word) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
