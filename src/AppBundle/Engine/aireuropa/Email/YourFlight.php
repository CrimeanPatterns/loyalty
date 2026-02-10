<?php

namespace AwardWallet\Engine\aireuropa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "aireuropa/it-409706345.eml, aireuropa/it-695172900.eml, aireuropa/it-696517588.eml, aireuropa/it-697170331.eml, aireuropa/it-697389855.eml, aireuropa/it-699422883.eml";
    public $subjects = [
        'Check-in is now open for your flight to',
    ];

    public $lang = '';
    public $detectLang = [
        'pt' => ['Segue-nos nas redes sociais'],
        'es' => ['Tus vuelos', 'Voos'],
        'en' => ['Flights', 'your flight'],
    ];

    public static $dictionary = [
        "en" => [
            'LOCATOR'         => ['LOCATOR', 'Confirmed reservation'],
            'Flight details:' => ['Flights', 'Flight details:'],
        ],

        "es" => [
            'Flight details:'                    => ['Tus vuelos', 'Voos'],
            'View flights and included services' => 'Ver vuelos y servicios incluidos',
            'LOCATOR'                            => 'Reserva confirmada',
            'Passengers'                         => 'Información de pasajeros',
            'Flight'                             => ['Vuelo', 'Voo'],
        ],

        "pt" => [
            'Flight details:'                    => ['Tus vuelos', 'Voos'],
            'View flights and included services' => 'Ver vuelos y servicios incluidos',
            'LOCATOR'                            => 'Reserva confirmada',
            'Passengers'                         => 'Información de pasajeros',
            'Flight'                             => ['Vuelo', 'Voo'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aireuropanews.com') !== false) {
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

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'AIR EUROPA')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Flight details:'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('View flights and included services'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('LOCATOR'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aireuropanews\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('LOCATOR'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/");

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('LOCATOR'))}]/following::text()[normalize-space()][2]/ancestor::b[1]");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/following::table[1]/descendant::text()[string-length()>5]");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//img[contains(@src, 'user_ico')]/following::text()[normalize-space()][1]");
        }

        $f->general()
            ->confirmation($conf)
            ->traveller($traveller);

        $account = $this->http->FindSingleNode("//text()[{$this->eq($traveller)}]/following::text()[normalize-space()][1]", null, true, "/^\s*(?:Suma|Frequent Flyer)\s*(\d{5,})$/");

        if (!empty($account)) {
            $f->addAccountNumber($account, false, $traveller);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight details:'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Flight'))}]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight details:'))}]/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), '+'))]/descendant::text()[normalize-space()]");
        }

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//img[contains(@src, 'avion_ida_ico') or contains(@src, 'avion_vuelta_ico')]/ancestor::tr[1]/following::table[1]/descendant::tr/descendant::text()[normalize-space()]");
        }

        foreach ($nodes as $root) {
            $segText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^\s*(?<date>[\d\/]+)\s*\|\s*(?<depName>.+)\s+\((?<depTime>[\d\:]+)\)\s+\-\s+(?<arrName>.+)\s+\((?<arrTime>[\d\:]+)\)\s*\|\s*(?<name>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<number>\d+)\s*$/u", $segText, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                $s->departure()
                    ->noCode()
                    ->name($m['depName'])
                    ->date($this->normalizeDate(str_replace('/', '.', $m['date']) . ' ' . $m['depTime']));

                $s->arrival()
                    ->noCode()
                    ->name($m['arrName'])
                    ->date($this->normalizeDate(str_replace('/', '.', $m['date']) . ' ' . $m['arrTime']));
            } elseif (preg_match("/^(?<depName>.+)\s*\-\s*(?<arrName>.+)\s*{$this->opt($this->t('Flight'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s*(?<depDate>\d+\s*\w+\s*\d{4})\s*(?<arrDate>\d+\s*\w+\s*\d{4})\s*(?<depTime>\d+\:\d+)\s*(?<arrTime>\d+\:\d+)\s*(?<depCode>[A-Z]{3})\s*(?<arrCode>[A-Z]{3})/u", $segText, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

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

    public function assignLang()
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

    private function normalizeDate($str)
    {
        $in = [
            "#^([\d\.]+)\.(\d{2})\s*([\d\:]+)$#u", //16.06.23 10:50
        ];
        $out = [
            "$1.20$2, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
