<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightCancellation extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-92742645.eml, aeromexico/it-92796614.eml";
    public $subjects = [
        '/Flight cancellation/u',
        '/Información Importante sobre tu vuelo/u',
    ];

    public $lang = '';

    public $langDetect = [
        'en' => ['Your flight'],
        'es' => ['El vuelo'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "es" => [
            'Your flight'       => 'El vuelo',
            'has been canceled' => 'ha sido cancelado',
            'Reservation'       => 'Reservación',
            'to'                => 'con destino a',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aeromexico.com') !== false) {
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
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Aeroméxico')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight'))}]")->length > 0
                && $this->http->XPath->query("//td[{$this->contains($this->t('has been canceled'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aeromexico\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation'))}]/following::text()[normalize-space()][1]", null, true, "/([A-Z]{4,})/"));

        if ($this->http->XPath->query("//td[{$this->contains($this->t('has been canceled'))}]")->length > 0) {
            $s = $f->addSegment();

            $s->extra()
                ->cancelled()
                ->status('cancelled');


            $s->airline()
                ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your flight'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z]{2,})\s*\d{2,4}/"))
                ->number($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your flight'))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z]{2,}\s*(\d{2,4})/"));

            $s->departure()
                ->noCode()
                ->noDate();

            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your flight'))}]/following::text()[{$this->contains($this->t('to'))}][1]/following::text()[normalize-space()][1]"))
                ->noDate();
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
        return count(self::$dictionary);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function assignLang()
    {
        if (isset($this->langDetect)) {
            foreach ($this->langDetect as $lang => $words) {
                foreach ($words as $word) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
