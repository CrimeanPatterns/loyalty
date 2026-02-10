<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationOfReservation extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-91398666.eml, aeromexico/it-92532350.eml";
    public $subjects = [
        'Confirmación de su reserva Hertz-Aeromexico',
    ];

    public $lang = '';

    public static $dictionary = [
        "es" => [
            'Información de la reservación' => 'Información de la reservación',
            'Lugar de entrega' => 'Lugar de entrega',
        ],
        "pt" => [
            'Información de la reservación' => 'Informações da reserva',
//            'Tu número de reservación con es:' => '',
            'Lugar de entrega' => 'Lugar de entrega',
            'Confirmación'                     => 'Confirmação',
            'Tu número de reservación con es:' => 'O seu número de reserva é:',
            'Lugar de devolución'              => 'Lugar de devolução',
            'O similar'                        => 'Similar',
            'Información del conductor'        => 'Informações do motorista',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aeromexico.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, '/aeromexico.com') or contains(@href, '.aeromexico.com')]")->length === 0) {
            return false;
        }
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Información de la reservación'], $dict['Lugar de entrega'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Información de la reservación'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Lugar de entrega'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aeromexico\.com$/', $from) > 0;
    }

    public function ParseCar(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->eq($this->t("Información de la reservación"))."]/preceding::text()[{$this->starts($this->t('Confirmación'))}][1]", null, true, "/{$this->opt($this->t('Confirmación'))}\s*([A-Z\d]+)/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Información del conductor'))}]/following::text()[normalize-space()][1]"));

        $pickUpDate = str_replace('/', '.', $this->http->FindSingleNode("//text()[{$this->starts($this->t('Lugar de entrega'))}]/following::text()[normalize-space()][1]", null, true, "/^\D+\s*([\d\/]+)$/u"));
        $pickUpTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Lugar de entrega'))}]/following::text()[normalize-space()][2]", null, true, "/^([\d\:]+)$/");

        $dropOffDate = str_replace('/', '.', $this->http->FindSingleNode("//text()[{$this->starts($this->t('Lugar de devolución'))}]/following::text()[normalize-space()][1]", null, true, "/^\D+\s*([\d\/]+)$/u"));
        $dropOffTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Lugar de devolución'))}]/following::text()[normalize-space()][2]", null, true, "/^([\d\:]+)$/");

        $r->pickup()
            ->date(strtotime($pickUpDate . ', ' . $pickUpTime))
            ->location(implode(', ', $this->http->FindNodes("//text()[{$this->starts($this->t('Lugar de entrega'))}]/following::text()[normalize-space()][position() = 3 or position() = 4][ancestor::td[1][{$this->starts($this->t('Lugar de entrega'))}]]")));

        $r->dropoff()
            ->date(strtotime($dropOffDate . ', ' . $dropOffTime))
            ->location(implode(', ', $this->http->FindNodes("//text()[{$this->starts($this->t('Lugar de devolución'))}]/following::text()[normalize-space()][position() = 3 or position() = 4][ancestor::td[1][{$this->starts($this->t('Lugar de devolución'))}]]")));

        $r->car()
            ->model($this->http->FindSingleNode("//text()[{$this->contains($this->t('O similar'))}]/ancestor::td[1]"))
            ->image($this->http->FindSingleNode("//text()[{$this->contains($this->t('O similar'))}]/following::img[contains(@src, 'car')][1]/@src"));

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]", null, true, "/^[^\s\d]{1,5}\s*(\d[\d\.\, ]*)$/u");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]", null, true, "/^([^\s\d]{1,5})\s*\d[\d\.\, ]*$/u");

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(cost($total))
                ->currency($this->currency($currency));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $otaConf = $this->http->FindSingleNode("//text()[".$this->starts($this->t("Tu número de reservación con es:"))."]", null, true, "/{$this->opt($this->t('Tu número de reservación con es:'))}\s*(\d+)/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->ParseCar($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (!empty($words['Información de la reservación']) && $this->http->XPath->query("//*[".$this->contains($words['Información de la reservación'])."]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);
        if(isset($m[$c])) return $m[$c];
        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) return $code;
        $sym = [
            'US$' => 'USD',
        ];
        foreach($sym as $f => $r)
            if ($s == $f) return $r;
        return null;
    }
}
