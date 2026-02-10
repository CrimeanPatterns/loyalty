<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalSummary extends \TAccountChecker
{
    public $mailFiles = "avis/it-687515256.eml, avis/it-688514410.eml";
    public $subjects = [
        'Avis Car Rental',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Your Rental Summary"],
        "es" => ["Resumen de tu Alquiler"],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "es" => [
            'Your Rental Summary' => 'Resumen de tu Alquiler',
            'Download Voucher'    => 'Descargar Voucher',

            'Reservation Number:'       => 'Número de Reserva:',
            'Confirmation Number Avis:' => 'Número de Confirmación de Avis:',
            'Dear'                      => 'Estimado(a)',
            'Pick Up Date'              => 'Fecha de Retiro',
            'Pick Up Location'          => 'Localidad de Retiro',
            'at'                        => 'a las',
            'Return Date'               => 'Fecha de Devolución',
            'Return Location'           => 'Localidad de Devolución',
            'Phone:'                    => 'Teléfono:',
            'Your Vehicle'              => 'Tu Auto',
            'Total Price'               => 'Precio Final',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@avislac.com') !== false) {
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

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Avis Car Rental')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Rental Summary'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Download Voucher'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]@avislac.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Number:'))}]/following::text()[normalize-space()][1]"));

        $this->parseRental($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number Avis:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{10,})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Dear'))}]/following::text()[normalize-space()][1]"));

        $pickUpDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick Up Date'))}]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Pick Up Date'))}\s*(.+)/");
        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick Up Location'))}]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Pick Up Location'))}\s*(.+)/"))
            ->date($this->normalizeDate($pickUpDate))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick Up Location'))}]/following::text()[{$this->eq($this->t('Phone:'))}][1]/following::text()[normalize-space()][1]", null, true, "/^([+][\d\s]+)$/"))
            ->openingHours(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pick Up Location'))}]/following::img[1][contains(@src, 'clock')]/following::td[1]/descendant::text()[normalize-space()]")));

        $dropOffDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Return Date'))}]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Return Date'))}\s*(.+)/");
        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Return Location'))}]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Return Location'))}\s*(.+)/"))
            ->date($this->normalizeDate($dropOffDate))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Return Location'))}]/following::text()[{$this->eq($this->t('Phone:'))}][1]/following::text()[normalize-space()][1]", null, true, "/^([+][\d\s]+)$/"))
            ->openingHours(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Return Location'))}]/following::img[1][contains(@src, 'clock')]/following::td[1]/descendant::text()[normalize-space()]")));

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Vehicle'))}]/following::text()[normalize-space()][1]"))
            ->image($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Vehicle'))}]/following::img[1]/@src"));

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,]+)$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $r->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
            'BRL' => ['R$'],
            'MXN' => ['MX$'],
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug($str);
        $in = [
            "#^\w+\,\s*(\d+)\s+(\w+)\s*(\d{4})\s*{$this->opt($this->t('at'))}\s*([\d\:]+\s*a?p?m)$#us", //Mié, 19 Jun 2024a las 03:30 pm
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        $this->logger->debug($str);

        return strtotime($str);
    }
}
