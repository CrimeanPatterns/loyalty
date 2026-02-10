<?php

namespace AwardWallet\Engine\eurotunnel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "eurotunnel/it-180998995.eml, eurotunnel/it-669632197.eml";
    public $subjects = [
        'Eurotunnel Booking Confirmation',
        'LeShuttle Booking Confirmation',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Boarding pass"],
        "fr" => ["Carte d'embarquement"],
    ];

    public static $dictionary = [
        "en" => [
            'Lead name:' => ['Lead name:', 'Customer name:'],
        ],
        "fr" => [
            'Times shown are local times' => 'Les horaires affichés sont en heure locale',
            'Details of your booking'     => 'Détails de votre réservation',

            'Booking reference:' => 'Numéro de réservation :',
            'Lead name:'         => 'Nom :',
            'Departs'            => 'Départ de',
            ' at'                => ' à',
            'Arrives in'         => 'Arrivée à',
            'Total paid'         => 'Reçu :',
            'Please arrive'      => 'Nous vous',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.eurotunnel.com') !== false) {
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
        $this->AssignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'The Channel Tunnel Group Limited')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Times shown are local times'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Details of your booking'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Eurotunnel'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.eurotunnel\.com$/', $from) > 0;
    }

    public function ParseFerry(Email $email)
    {
        $f = $email->add()->transfer();

        $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space()][1]");

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][1]");
        }
        $f->general()
            ->notes($this->http->FindSingleNode("//img[contains(@alt, 'Arrival time')]/following::text()[{$this->starts($this->t('Please arrive'))}][1]"))
            ->confirmation(str_replace(' ', '', $confNumber))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Lead name:'))}]/following::text()[normalize-space()][1]"));

        $f->setAllowTzCross(true);

        $xpath = "//text()[{$this->starts($this->t('Departs'))}]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $depDate = $this->http->FindSingleNode("./descendant::img[1]/following::text()[normalize-space()][1]", $root);
            $depTime = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' at'))}][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+\:\d+)$/");

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Departs'))}][1]", $root, true, "/{$this->opt($this->t('Departs'))}\s*(.+){$this->opt($this->t(' at'))}/") . ', Europe')
                ->date($this->normalizeDate($depDate . ', ' . $depTime));

            $arrTime = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' at'))}][2]/following::text()[normalize-space()][1]", $root, true, "/^(\d+\:\d+)$/");

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Arrives in'))}][1]", $root, true, "/{$this->opt($this->t('Arrives in'))}\s*(.+){$this->opt($this->t(' at'))}/") . ', Europe')
                ->date($this->normalizeDate($depDate . ', ' . $arrTime));

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departs'))}]/ancestor::tr[1]/descendant::table[1]", $root));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total paid'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d\.\,]+)$/u", $price, $m)
        || preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D)$/u", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $this->normalizeCurrency($m['currency'])))
                ->currency($this->normalizeCurrency($m['currency']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $this->ParseFerry($email);

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

    public function AssignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\w+\s*(\d+)\s*(\w+)\s+(\d{4})\,\s*([\d\:]+)$#u", //Mercredi 08 Mai 2024, 13:48
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
