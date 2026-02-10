<?php

namespace AwardWallet\Engine\ctmoney\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "ctmoney/statements/it-106230507.eml, ctmoney/statements/it-162314308.eml, ctmoney/statements/it-85764470.eml, ctmoney/statements/it-85960858.eml";
    public $detectSubjects = [
        // en
        'Triangle ID Verification Code',
        // fr
        'Code de vérification de l\'identifiant Triangle',
        'Code De Vérification De L\'identifiant Triangle',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Here is your Triangle ID verification code:' => [
                'Here is your Triangle ID verification code:',
                'we need to verify your email address before you can access your Triangle ID.',
                'here is your Triangle ID verification code.',
            ],
        ],

        "fr" => [
            'Here is your Triangle ID verification code:' => [
                'Voici votre code de vérification de l\'identifiant Triangle :',
                'nous devons confirmer votre adresse électronique avant que vous puissiez accéder à votre identifiant Triangle',
            ],
            'Hi' => 'Hi',
            'This email was sent to' => 'Le présent courriel a été envoyé à',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'TriangleID@signin.triangle.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '.triangle.com') !== false) {
            foreach ($this->detectSubjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailByHeaders($parser->getHeaders()) !== true) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Here is your Triangle ID verification code:']) && $this->http->XPath->query("//text()[{$this->contains($dict['Here is your Triangle ID verification code:'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Here is your Triangle ID verification code:'])) {
                $this->lang = $lang;
                $code = $this->http->FindSingleNode("//text()[{$this->eq($dict['Here is your Triangle ID verification code:'])}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{6})\s*$/");

                if (empty($code)) {
                    $code = $this->http->FindSingleNode("//text()[{$this->contains($dict['Here is your Triangle ID verification code:'])}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{6})\s*$/");
                }

                if (!empty($code)) {
                    $st = $email->add()->statement();

                    $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^\s*{$this->opt($this->t('Hi'))}\s+(\D+)\,\s*{$this->opt($dict['Here is your Triangle ID verification code:'])}/");

                    if (!empty($name)) {
                        $st->addProperty('Name', trim($name, ','));
                    }

                    $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]/ancestor::tr[1]", null, true, "/(\S+[@]\S+\.\S+)/");

                    if (!empty($login)) {
                        $st->setLogin($login);
                    }

                    $st
                        ->setMembership(true)
                        ->setNoBalance(true)
                    ;

                    if (
                        !empty($this->http->FindSingleNode("(//text()[{$this->contains($dict['Here is your Triangle ID verification code:'])}]/preceding::a[contains(@href, '.canadiantire.ca')]/@href)[1]"))
                        || !empty($this->http->FindSingleNode("(//text()[{$this->contains($dict['Here is your Triangle ID verification code:'])}]/preceding::img[@alt = 'Triangle ID']/@alt)[1]"))
                    ) {
                        // IMPORTANT: собирать код, только если верхний логотип ведет на canadiantire.ca; Если на .marks.com или .sportchek.ca или других партнеров -> собирать код не нужно
                        $ots = $email->add()->oneTimeCode();

                        $ots->setCode($code);
                    }
                    break;
                }
            }
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

    private function assignLang()
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                foreach ($reBody as $word) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
