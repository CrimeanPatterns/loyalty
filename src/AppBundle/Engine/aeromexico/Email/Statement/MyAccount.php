<?php

namespace AwardWallet\Engine\aeromexico\Email\Statement;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MyAccount extends \TAccountChecker
{
    public $mailFiles = "aeromexico/statements/it-103676923.eml, aeromexico/statements/it-103678244.eml, aeromexico/statements/it-195535706.eml";
    public $detectSubjects = [
        // es
        ', tu ESTADO DE CUENTA de', // Jose Arturo, tu ESTADO DE CUENTA de MARZO 2020
        // en
        ', your STATEMENT for'
    ];

    public $lang = 'es';
    public $date;

    public static $dictionary = [
        "es" => [
            'Mi Cuenta' => ['Mi Cuenta', 'Mi cuenta'],
            'Número de Cuenta Club Premier' => 'Número de Cuenta Club Premier',
//            'Hola' => '',
//            'Saldo al' => '',
//            'Te invitamos a seguir acumulando para evitar que tus Puntos Premier expiren el día:' => '',
        ],
        "en" => [
            'Mi Cuenta' => ['My Account'],
            'Número de Cuenta Club Premier' => 'Club Premier Account Number',
            'Hola' => 'Hello',
            'Saldo al' => 'Balance as of',
            'Te invitamos a seguir acumulando para evitar que tus Puntos Premier expiren el día:' => 'Keep earning Premier Points to prevent them from expiring on the following date:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@correo.clubpremier.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'correo.clubpremier.com')]")->length === 0) {
            return false;
        }
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Número de Cuenta Club Premier']) && !empty($dict['Mi Cuenta'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Número de Cuenta Club Premier'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Mi Cuenta'])}]")->length > 0
                && $this->http->XPath->query("//text()[contains(., '@clubpremier.com')]")->length > 0

            ) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]correo\.clubpremier\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Número de Cuenta Club Premier'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Número de Cuenta Club Premier'])}]")->length > 0
            ) {
                $this->lang = $lang;
                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime("+1 day", strtotime($parser->getHeader('date')));

        $number = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Club Premier.')]/preceding::text()[{$this->eq($this->t("Número de Cuenta Club Premier"))}][1]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{7,})$/");

        if (empty($number)) {

            return $email;
        }

        $st = $email->add()->statement();

        $st->setNumber($number);
        $st->setLogin($number);

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Club Premier.')]/preceding::text()[{$this->starts($this->t("Hola"))}][1]", null, true, "/^{$this->opt($this->t('Hola'))}\s+(\D+)(?:\,|$)/");
        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $balance = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Club Premier.')]/preceding::text()[{$this->starts($this->t("Saldo al"))}][1]/following::text()[normalize-space()][1]",
            null, true, "/^\s*\d[\d, ]*\s*$/");

        if ($balance != null) {
            $st->setBalance(str_replace(',', '', $balance));

            $expireDate = $this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Club Premier.')]/preceding::text()[{$this->eq($this->t("Te invitamos a seguir acumulando para evitar que tus Puntos Premier expiren el día:"))}][1]/following::text()[normalize-space()][1]",
                null, true, "/^\s*\d+\\/\w+\\/\d{4}\s*$/"));
            if (!empty($expireDate)) {
                $st->setExpirationDate($expireDate);
                $this->date = $expireDate;

            }
            $dateOfBalance = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Club Premier.')]/preceding::text()[{$this->starts($this->t("Saldo al"))}][1]",
                null, true, "/{$this->opt($this->t('Saldo al'))}\s*(.+)\:/");

            if (!empty($dateOfBalance)) {
                $st->setBalanceDate($this->normalizeDate($dateOfBalance, true));
            }
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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

    private function normalizeDate($str, $relative = false)
    {
        if (empty($str)) {
            return null;
        }
        $year = date("Y", $this->date);

        $in = [
            // 6 de marzo
            "#^\s*(\d+)\s*de\s*(\w+)\s*$#",
            // 31/AGO/2023
            "#^\s*(\d+)\\/(\w+)\\/(\d{4})\s*$#",
        ];
        $out = [
            "$1 $2",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#^\s*\d+\s+([^\d\s]+)(?:\s+\d{4}|\s*$)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'es')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if ($relative === true && !preg_match("/\b\d{4}\b/", $str)) {
            $str = EmailDateHelper::parseDateRelative($str, $this->date, false);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
