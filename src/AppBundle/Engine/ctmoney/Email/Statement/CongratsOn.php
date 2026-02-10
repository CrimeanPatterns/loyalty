<?php

namespace AwardWallet\Engine\ctmoney\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CongratsOn extends \TAccountChecker
{
    public $mailFiles = "ctmoney/statements/it-85125832.eml, ctmoney/statements/it-85318222.eml, ctmoney/statements/it-85388359.eml, ctmoney/statements/it-85807346.eml, ctmoney/statements/it-85909932.eml, ctmoney/statements/it-85911299.eml";
    public $subjects = [
        '/Congrats on redeeming\!/',
        '/\D+\, your Triangle Rewards offers are here\!/',
        '/Bonus. Party City. /',
        '/Shop the best brands for your best furry friend\./',
    ];

    public $lang;
    public $subject;

    public $detectLang = [
        "en" => ["This email was sent to"],
        "fr" => ["Ce courriel a été envoyé à"],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "fr" => [
            "Triangle Rewards"       => "COMPTE RÉCOMPENSES TRIANGLE",
            'your Triangle Rewards'  => 'vos offres Récompenses',
            'This email was sent to' => 'Ce courriel a été envoyé à',
            'CT Money'               => 'SOLDE',
            'CT Money Balance as of' => 'Votre solde en Argent CT en date du',
            'Canadian Tire Corporation' => ['Canadian Tire Corporation', 'Canadian Tire Limitée'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.triangle.com') !== false) {
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
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Canadian Tire Corporation'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Triangle Rewards'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('CT Money'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.triangle\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $this->logger->warning('ParseEmail');

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]", null, true, "/^{$this->opt($this->t('Hey'))}\s+(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Triangle Rewards Account'))}]", null, true, "/^{$this->opt($this->t('Triangle Rewards Account'))}\s*[#]\:?\s*[X\-]+(\d+)$/");

        if (!empty($number)) {
            $st->setNumber($number)
                ->masked('left');
        } elseif (empty($number)) {
            $number = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Triangle Rewards'))}])[1]/ancestor::tr[1]", null, true, "/\s*[#]\:?\s*(\d{4})$/");
            if (!empty($number)) {
                $st->setNumber($number)
                ->masked('left');
            }
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email was sent to')]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+\@\S+\.\S+)/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email was sent to')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+\@\S+\.\S+)/");
        }

        if (!empty($login)) {
            $st->setLogin(trim($login, '.'));
        }

        $balance = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('CT Money Balance'))}])[1]/ancestor::td[1]", null, true, "/\s*{$this->opt($this->t('CT Money Balance'))}\S[:]\s*\(?[^()\s\d](\d[\d\.]*)\s*\)?\s*$/");

        if (empty($balance)) {
            $balance = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('CT Money'))}])[1]/ancestor::td[1]", null, true, "/\S[:]\s*\S([\d\.]+)/");
        }
        $st->setBalance($balance);

        $dateBalance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Time:')]/following::text()[normalize-space()][1]", null, true, "/^([\d\/]+)$/");

        if (!empty($dateBalance)) {
            $st->setBalanceDate(strtotime($dateBalance));
        }
    }

    public function ParseEmail2(Email $email)
    {
        $this->logger->warning('ParseEmail2');

        $st = $email->add()->statement();

        $name = $this->re("/^(\D+)\,\s*{$this->opt($this->t('your Triangle Rewards'))}/u", $this->subject);

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Triangle Rewards'))}]/ancestor::tr[1]", null, true, "/\s*[#]?\:?\s*(\d{4})$/");

        if (!empty($number)) {
            $st
                ->setNumber($number)
                ->masked('left')
            ;
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+\@\S+\.\S+)/");

        if (!empty($login)) {
            $st->setLogin(trim($login, '.'));
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Triangle Rewards'))}]/following::text()[{$this->starts($this->t('CT Money'))}][1]/ancestor::td[1]", null, true, "/\:\s*(.+)/");
        $balance = preg_replace("/^\s*\(\s*(.+?)\s*\)\s*$/", '$1', $balance);
        if (preg_match("/^\s*\D\s*([\d\,\.]+)/", $balance, $m) || preg_match("/^\s*([\d\,\.]+)\s*\D/", $balance, $m)) {
            $st->setBalance(str_replace(",", ".", $m[1]));
        }

        //it-85909932
        if (preg_match("/^(\-)\D([\d\.\,]+)$/u", $balance, $m)) {
            $st->setBalance($m[1] . str_replace([","], ".", $m[2]));
        }

        $dateBalance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CT Money Balance as of'))}]");

        if (preg_match("/\s*{$this->opt($this->t('CT Money Balance as of'))}\s*(\w+\s*\d+\,\s*\d{4})\./u", $dateBalance, $m)
            || preg_match("/\s*{$this->opt($this->t('CT Money Balance as of'))}\s*(\d+\s*\w+\,\s*\d{4})\./u", $dateBalance, $m)
        ) {
            $st->setBalanceDate(strtotime(str_replace(',', ' ', $m[1])));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->subject = $parser->getSubject();

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Hey'))}]")->length > 0) {
            $this->ParseEmail($email);
        } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('Hey'))}]")->length == 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Valid until'))}]")->length > 0) {
            $this->ParseEmail2($email);
        } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('Hey'))}]")->length == 0) {
            $this->ParseEmail2($email);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
