<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "despegar/it-56594826.eml, despegar/it-56672727.eml, despegar/it-57869480.eml";
    private $lang = '';
    private $reFrom = [
        '@despegar.com',
    ];
    private $reProvider = ['Despegar'];
    private $reSubject = [
        'Estamos trabajando en el pedido de cambio de tu vuelo',
    ];
    private $reBody = [
        'es' => [
            'Recibimos correctamente el pedido de cambio para tu vuelo y lo estamos procesando.',
        ],
    ];

    private static $dictionary = [
        'es' => [
            'otaConf'      => 'Pedido nro.',
            'conf'         => 'Reserva de vuelo nro.',
            'flight'       => 'Vuelo:',
            'again'        => ['Ida', 'Vuelta', 'Tramo'],
            'from'         => 'Sale de',
            'to'           => 'Llega a',
            'at'           => 'a las',
            'class'        => 'Clase:',
            'notSpecified' => 'No Especificada',
            'passenger'    => 'Pasajero',
            'total'        => 'Total a pagar por el cambio',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseFlight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('otaConf'))}]", null, true,
            "/{$this->opt($this->t('otaConf'))}\s+([\w\-]{6,})$/");
        $f->ota()->confirmation($conf);
        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('conf'))}]", null, true,
            "/{$this->opt($this->t('conf'))}\s+([\w\-]{6,})$/");
        $f->general()->confirmation($conf);

        $travellers = $this->http->XPath->query($xpath = "//td[{$this->starts($this->t('passenger'))}]/following-sibling::td//ul");
        $this->logger->notice($xpath);

        foreach ($travellers as $traveller) {
            $f->general()->traveller(join(" ", $this->http->FindNodes('li/strong/following-sibling::text()', $traveller)), true);
        }

        $xpath = "//text()[{$this->starts($this->t('flight'))}]/ancestor::td[2]";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->notice($xpath);

        foreach ($segments as $segment) {
            $s = $f->addSegment();
            $date = $this->http->FindSingleNode('table[1]', $segment, false,
                "/{$this->opt($this->t('again'))}(?:\s*\d)?\s*(.+)/");
            $date = $this->normalizeDate($date);
            $s->airline()->operator($this->http->FindSingleNode('table[2]', $segment));
            $str = join("\n", array_filter($this->http->FindNodes('table[3]//text()', $segment)));

            // Vuelo: 772 - 4h 02m
            if (preg_match("/{$this->t('flight')}\s*(.+?) - (.+?)\n/", $str, $m)) {
                $s->airline()->number($m[1]);
                $s->airline()->noName();
                $s->extra()->duration($m[2]);
            }
            // Sale de Santiago de Chile a las 05:33hs.
            if (preg_match("/{$this->opt($this->t('from'))}\s+(.+?)\s+{$this->opt($this->t('at'))}\s+(\d+:\d+(?:[ap]m)?)/iu", $str, $m)) {
                $s->departure()->name($m[1]);
                $s->departure()->date2(!empty($date)? "{$date} {$m[2]}" : null);
                $s->departure()->noCode();
            }
            // Llega a Rio de Janeiro a las 09:35hs.
            if (preg_match("/{$this->opt($this->t('to'))}\s+(.+?)\s+{$this->opt($this->t('at'))}\s+(\d+:\d+(?:[ap]m)?)/iu", $str, $m)) {
                $s->arrival()->name($m[1]);
                $s->arrival()->date2(!empty($date)? "{$date} {$m[2]}" : null);
                $s->arrival()->noCode();
            }

            if (preg_match("/{$this->t('class')}\s*(.+?)\n/", $str, $m)) {
                if (trim($m[1]) == $this->t('notSpecified')) {
                    $s->extra()->cabin($m[2]);
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('total'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (
            preg_match('/^\s*(?<amount>\d[,.\'\d]*) ?(?<currency>\D+)\b/', $totalPrice, $m)
            || preg_match('/^(?<currency>\D+)\s?(?<amount>\d[,.\'\d]*)/', $totalPrice, $m)
        ) {
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->currency(trim($m['currency'])))
            ;
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function normalizeDate($str)
    {
        $in = [
            // Miércoles 14 de octubre de 2020
            '/^\w+ (\d+) de (\w+) de (\d{4})$/u',
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        if (preg_match($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            // don't add '$' as USD
            //'$'=>'ARS',
            'U$S' => 'USD',
            'US$' => 'USD',
            'MXN$'=> 'MXN',
            '€'   => 'EUR',
            '£'   => 'GBP',
            '₹'   => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
