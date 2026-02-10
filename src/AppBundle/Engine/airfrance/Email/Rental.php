<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Rental extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-173816231.eml";
    public $subjects = [
        'Confirmation de votre réservation Air France',
    ];

    public $lang = '';

    public $detectLang = [
        'fr' => ['Bonjour'],
    ];

    public static $dictionary = [
        "fr" => [
            'Details of the trip are below'     => 'Veuillez trouver les détails de votre voyage ci-dessous',
            'MANAGE YOUR RESERVATION'           => 'GÉRER VOTRE RÉSERVATION',
            'Your booking confirmation number:' => 'Le numéro de confirmation de votre réservation :',
            'Hi'                                => 'Bonjour',

            'PickUp'         => 'Prise en charge',
            'DropOff'        => 'Retour',
            'or similar'     => 'ou similaire',
            'Change vehicle' => 'Changer de véhicule',
            'Total'          => 'Coût total',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airfrancecarrental.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Air France')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Details of the trip are below'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('MANAGE YOUR RESERVATION'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Changer de véhicule'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airfrancecarrental\.com$/', $from) > 0;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking confirmation number:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{11})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Bonjour'))}\s*(.+)\,/"), false);

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Numéro Hertz Gold Plus Rewards'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }

        $r->car()
            ->image($this->http->FindSingleNode("//text()[{$this->eq($this->t('Change vehicle'))}]/preceding::img[1]/@src"))
            ->model($this->http->FindSingleNode("//text()[{$this->eq($this->t('Change vehicle'))}]/preceding::text()[{$this->contains($this->t('or similar'))}]"))
            ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('Change vehicle'))}]/preceding::text()[{$this->contains($this->t('or similar'))}]/following::text()[normalize-space()][1]"));

        $pickUpText = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('PickUp'))}]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<location>.+)\s(?<date>\d+\/\d+\/\d{4}\D+[\d\:]+)$/", $pickUpText, $m)) {
            $r->pickup()
                ->location($m['location'])
                ->date($this->normalizeDate($m['date']));
        }

        $dropOffText = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('DropOff'))}]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<location>.+)\s(?<date>\d+\/\d+\/\d{4}\D+[\d\:]+)$/", $dropOffText, $m)) {
            $r->dropoff()
                ->location($m['location'])
                ->date($this->normalizeDate($m['date']));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][2]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)/u", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $r->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseRental($email);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})\D+([\d\:]+)$#u", //24/05/2022 at 19:00
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP'   => ['£'],
            'EUR'   => ['€'],
            'THB'   => ['฿'],
            'INR'   => ['Rs.'],
            'BRL'   => ['R$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
