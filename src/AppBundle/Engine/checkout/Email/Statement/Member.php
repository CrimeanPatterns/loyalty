<?php

namespace AwardWallet\Engine\checkout\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "checkout/it-102244882.eml, checkout/it-103145807.eml, checkout/it-103146674.eml, checkout/it-103685830.eml";

    private $detectFrom = ['.checkout51.com', '@checkout51.com'];

    private $detectBody = [
        'You are receiving this e-mail because you joined Checkout 51.',
    ];

    private $lang = 'en';

    public static $dictionary = [
        'en' => [
            'detectTextWithLink' => [
                'Visit our Help Desk!',
            ],
            'detectText' => [
                'You are receiving this e-mail because you joined Checkout 51.',
            ],
        ],
        'fr' => [
            'detectTextWithLink' => [
                'Vistez notre centre d’assistance!',
                'Visitez notre centre d’assistance!'
            ],
            'detectText' => [
                'Vous avez reçu ce courriel parce que vous êtes inscrit à Checkout 51.',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }

        }
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            $link = ['.checkout51.com', '//checkout51.com'];
            if (!empty($dict['detectTextWithLink']) && $this->http->XPath->query("//a[" . $this->contains($dict['detectTextWithLink']) . " and" . $this->contains($link,
                        '@href') . "]")->length > 0) {
                return true;
            }

            if (!empty($dict['detectText']) && $this->http->XPath->query("//*[" . $this->contains($dict['detectText']) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st
            ->setMembership(true)
        ;

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your current balance of $')]", null, true,
            "/Your current balance of \\$(\d[\d.]*) Cash Back credits/");
        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You now have $')]", null, true,
                "/You now have \\$(\d[\d.]*) in your account/");
        }
        if ($balance == null) {
            $balance = str_replace(',', '.', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Vous avez maintenant')]", null, true,
                "/Vous avez maintenant (\d[\d,]*)\\$ dans votre compte/u"));
        }
        if (!empty($balance)) {
            $st->setBalance($balance);
        } else {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
