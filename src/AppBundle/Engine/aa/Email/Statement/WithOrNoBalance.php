<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WithOrNoBalance extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-215033296.eml, aa/statements/it-69205784.eml, aa/statements/it-69205921.eml";
    public $lang = 'en';

    public $langDetect = [
        'en' => ['This email was sent to'],
        'pt' => ['Este email foi enviado para'],
        'es' => ['Este e-mail ha sido enviado en nombre de American Airlines'],
    ];

    public static $dictionary = [
        "en" => [
            'Thank you for buying miles' => ['Thank you for buying miles', 'Your transaction is being processed'],
            'balance'                    => 'Don\'t let go of',
        ],
        "pt" => [
            'Hello,'                 => 'Olá,',
            'This email was sent to' => 'Este email foi enviado para',
            'Connect with us'        => 'Conecte-se conosco',
        ],
        "es" => [
            'Hello,'                 => 'Hola,',
            'This email was sent to' => 'Este email foi enviado para',
            'Connect with us'        => 'Conéctese con nosotros',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectLang() === true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'AAdvantage')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for buying miles'))}]")->count() == 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Award miles'))}]")->count() == 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Miles earned'))}]")->count() == 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Hello,'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Connect with us'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loyalty\.ms\.aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]/ancestor::td[1]", null, true,
            "/^{$this->opt($this->t('Hello,'))}\s*(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, '!'));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]/following::text()[{$this->starts($this->t('AAdvantage'))}]/ancestor::td[1]/descendant::text()[last()]",
            null, true, "/^\s*([A-Z\d]{7,})\s*$/");
        if (empty($number)) {
            $number = $this->http->FindSingleNode("//*[self::table or self::tr][{$this->starts($this->t('Hello,'))}]/preceding-sibling::*[self::table or self::tr][1][count(.//text()[normalize-space()]) = 1 and .//img]",
                null, true, "/^\s*([A-Z\d]{7,})\s*$/");
        }
        $st->setNumber($number);

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+[@]\S+\.[a-z]+)/s");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $statusVariant = ['Member', 'Gold', 'Platinum', 'Executive Platinum', 'Platinum Pro', 'ConciergeKey'];
        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]/following::text()[{$this->starts($this->t('AAdvantage'))}]/ancestor::td[1]/descendant::text()[normalize-space()][3]",
            null, true, "/^\s*(" . implode('|', $statusVariant).")\W*/i");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]/preceding::img[1]/@alt",
                null, true, "/^\s*{$this->opt($this->t('AAdvantage'))}\W{1,3}({$this->opt($statusVariant)})\W{0,3}/iu");
        }
        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('balance'))}]", null, true, "/([\d\,]+)\s*miles/");

        if (!empty($balance)) {
            $st->setBalance(str_replace(",", "", $balance));
        } elseif (empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('balance'))}]"))) {
            $st->setNoBalance(true);
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

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->langDetect as $lang => $detects) {
            foreach ($detects as $word) {
                if (stripos($body, $word) !== false) {
                    $this->lang = $lang;
                    $this->logger->warning($lang);

                    return true;
                }
            }
        }

        return false;
    }
}
