<?php

namespace AwardWallet\Engine\aeromexico\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Congratulations extends \TAccountChecker
{
    public $mailFiles = "aeromexico/statements/it-103677375.eml";
    public $subjects = [
        '/¡Llegó el momento de utilizar tus Puntos Premier sin salir de casa[!]/',
    ];

    public $lang = 'es';
    public $date;

    public static $dictionary = [
        "es" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@correo.clubpremier.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Club Premier')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Puntos Premier'))}]")->length > 0
            && $this->http->XPath->query("//img[contains(@alt, 'Botón')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('clubpremier.com'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]correo\.clubpremier\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '¡Felicidades ')]", null, true, "/^{$this->opt($this->t('¡Felicidades '))}\s*(\D+)(?:\,|\!)/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Lograste acumular')]", null, true, "/{$this->opt($this->t('Lograste acumular'))}\s*([\d\,]+)\s/");

        if ($balance != null) {
            $st->setBalance(str_replace(',', '', $balance));
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^\s*(\d+)\s*de\s*(\w+)\s*$#", // 6 de marzo
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
