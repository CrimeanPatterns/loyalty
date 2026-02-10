<?php

namespace AwardWallet\Engine\aerolineas\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountState extends \TAccountChecker
{
    public $mailFiles = "aerolineas/statements/it-76962722.eml, aerolineas/statements/it-76631384.eml, aerolineas/statements/it-77117804.eml, aerolineas/statements/it-77239925.eml";

    public $lang = 'es';

    public static $dictionary = [
        'es' => [
            'Movimientos de tu cuenta realizados hasta el' => [
                'Movimientos de tu cuenta realizados hasta el',
                'Movimientos de tu cuenta realizados desde el',
                'Movimientos de tu cuenta confirmados hasta el',
            ],
        ],
    ];

    private $format = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aerolineas.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"aerolineas.com.ar/") or contains(@href,"contenido.aerolineas.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Este email fue enviado por: Aerolineas Argentinas")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $email->setType('AccountState' . $this->format . ucfirst($this->lang));
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = null;

        if ($this->format === 1) {
            $name = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^{$patterns['travellerName']}$/u");
        } elseif ($this->format === 2) {
            $name = $this->http->FindSingleNode("*[1]", $root, true, "/^Hola\s+({$patterns['travellerName']})$/u");
        }
        $st->addProperty('Name', $name);

        if ($this->format === 1) {
            $number = $this->http->FindSingleNode("*[starts-with(normalize-space(),'Socio Aerolíneas Plus:')]", $root, true, "/^Socio Aerolíneas Plus:\s*([-A-Z\d]{5,})$/i");
        } elseif ($this->format === 2) {
            $number = $this->http->FindSingleNode("*[2]", $root, true, "/^Socio Nº\s*([-A-Z\d]{5,})$/i");
        }
        $st->addProperty('AccountNumber', $number)
            ->setNumber($number)
            ->setLogin($number);

        $balance = $this->http->FindSingleNode("*[starts-with(normalize-space(),'Tus millas:')]", $root, true, "/^Tus millas:\s*(\d[,.\'\d ]*|Saldo)$/i");

        if (
            (preg_match("/^Saldo$/i", $balance) || $this->format === 2)
            && ($name || $number)
        ) {
            $st->setNoBalance(true);
        } else {
            $st->setBalance($this->normalizeAmount($balance));
        }

        if ($st->getBalance() !== null) {
            $balanceExpiration = $this->normalizeDate($this->http->FindSingleNode("*[starts-with(normalize-space(),'Tus millas vencen el:')]", $root, true, "/^Tus millas vencen el:\s*(.{6,})$/i"));

            if (!$balanceExpiration) {
                // it-76962722.eml
                $dateValue = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Movimientos de tu cuenta realizados hasta el'))}]", null, true, "/^{$this->opt($this->t('Movimientos de tu cuenta realizados hasta el'))}\s*(.{6,}?)(?:[ ]*[|]|$)/i");

                if ($dateValue) {
                    $dates = preg_split("/\s+al\s+/i", $dateValue);

                    if (count($dates) === 2) {
                        // it-77117804.eml
                        $dateValue = $dates[1];
                    }
                }
                $balanceExpiration = $this->normalizeDate($dateValue);
            }
            $st->parseExpirationDate($balanceExpiration);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1;
        $nodes = $this->http->XPath->query("//*[ *[normalize-space()][2][starts-with(normalize-space(),'Socio Aerolíneas Plus:')] ]");

        if ($nodes->length === 0) {
            // it-77239925.eml
            $this->format = 2;
            $nodes = $this->http->XPath->query("//tr[ count(*)=2 and *[1][starts-with(normalize-space(),'Hola')] and *[2][starts-with(normalize-space(),'Socio Nº')] ]");
        }

        return $nodes;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text) || !preg_match('/\d/', $text)) {
            return '';
        }
        $in = [
            // 31/05/22
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/',
            // 31/05/2022
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
        ];
        $out = [
            '$1.$2.20$3',
            '$1.$2.$3',
        ];

        return preg_replace($in, $out, $text);
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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
}
