<?php

namespace AwardWallet\Engine\atrapalo\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "atrapalo/it-26173329.eml, atrapalo/it-26248477.eml, atrapalo/it-26539650.eml, atrapalo/it-26761458.eml, atrapalo/it-26977310.eml";
    private $subjects = [
        'es' => ['Confirmación de emision de billete'],
    ];
    private $langDetectors = [
        'es' => ['Detalle de vuelos y pasajeros'],
    ];
    private $lang = '';
    private static $dict = [
        'es' => [
            'Terminal'     => ['Terminal', 'terminal'],
            'Billete nº'   => ['Billete nº', 'Tiquete nº'],
            'Precio total' => ['Precio total', 'Precio Total'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@atrapalo.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(.,"www.viewtrip.com/atrapalo/") or contains(.,"@atrapalo.com")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"//www.viewtrip.com/atrapalo/") or contains(@href,"//www.atrapalo.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('TicketConfirmation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $email->ota(); // because Atrapalo is travel agency

        // ta.confirmationNumbers
        $locator = '';
        $locatorTitle = '';
        $locatorText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Localizador Atrápalo:'))}]");

        if (preg_match("/({$this->opt($this->t('Localizador Atrápalo:'))})\s*([A-Z\d]{5,})$/", $locatorText, $matches)) {
            $locator = $matches[2];
            $locatorTitle = preg_replace('/\s*:\s*$/', '', $matches[1]);
        }
        $email->ota()->confirmation($locator, $locatorTitle);

        $flights = $this->http->XPath->query("//text()[{$this->eq($this->t('Compañía'))}]/following::text()[normalize-space(.)][1][{$this->eq($this->t('Nº Vuelo'))}]/following::tr[normalize-space(.)][1]");

        foreach ($flights as $root) {
            $f = $email->add()->flight();

            // confirmation number
            $locatorRow = $this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)][1]", $root);

            if (preg_match("/({$this->opt($this->t('Localizador:'))})\s*([A-Z\d]{5,})$/", $locatorRow, $matches)) {
                $f->general()->confirmation($matches[2], preg_replace('/\s*:\s*$/', '', $matches[1]));
            }

            $s = $f->addSegment();

            // operatedBy
            $operator = $this->http->FindSingleNode('./*[1]', $root, true, "/{$this->opt($this->t('Operada por'))}\s*(.+)\)/");
            $s->airline()->operator($operator, false, true);

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode('./*[2]', $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            // duration
            $duration = $this->http->FindSingleNode('./*[3]', $root, true, '/^(\d[\d\sHM]+)$/i');
            $s->extra()->duration($duration);

            // depDate
            $dateDep = $this->http->FindSingleNode('./*[4]', $root);
            $s->departure()->date2($this->normalizeDate($dateDep));

            $patterns['airport'] = '/^'
                . '(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)' // Aerop. Barcelona (BCN)
                . '.*?' // Barcelona, España
                . "(?:\(\s*{$this->opt($this->t('Terminal'))}[:\s]+(?<terminal>[^)(]+)\s*\))?" // (Terminal: 2B)
                . '$/';

            // depName
            // depCode
            // depTerminal
            $departure = $this->http->FindSingleNode('./*[5]', $root);

            if (preg_match($patterns['airport'], $departure, $matches)) {
                $s->departure()
                    ->name($matches['name'])
                    ->code($matches['code']);

                if (!empty($matches['terminal'])) {
                    $s->departure()->terminal($matches['terminal']);
                }
            }

            // arrDate
            $dateArr = $this->http->FindSingleNode('./*[6]', $root);
            $s->arrival()->date2($this->normalizeDate($dateArr));

            // arrName
            // arrCode
            // arrTerminal
            $arrival = $this->http->FindSingleNode('./*[7]', $root);

            if (preg_match($patterns['airport'], $arrival, $matches)) {
                $s->arrival()
                    ->name($matches['name'])
                    ->code($matches['code']);

                if (!empty($matches['terminal'])) {
                    $s->arrival()->terminal($matches['terminal']);
                }
            }

            // travellers
            // ticketNumbers
            $passengerRows = $this->http->XPath->query("./ancestor::table[1]/following::tr[normalize-space(.)][1]/descendant::tr[not(.//tr) and {$this->contains($this->t('Información de los pasajeros'))}]/following-sibling::tr[ ./descendant::text()[{$this->starts($this->t('Adulto'))}] ]", $root);

            foreach ($passengerRows as $passengerRow) {
                $passenger = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1][ ./ancestor::*[self::b or self::strong] ]", $passengerRow, true, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u');
                $f->addTraveller($passenger);

                if (preg_match_all("/{$this->opt($this->t('Billete nº'))}\s*(\d[-\/A-Z\d]{7,}\d)\b/u", $passengerRow->nodeValue, $ticketMatches)) {
                    // 0162678889850C1/850-851
                    foreach ($ticketMatches[1] as $ticketNumber) {
                        $f->addTicketNumber($ticketNumber, false);
                    }
                }
            }
        }

        // p.total
        // p.currencyCode
        $payment = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Precio total'))}]", null, true, "/{$this->opt($this->t('Precio total'))}\s*(.+)/");

        if (
            preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[^\d)(]+)/', $payment, $matches) // 319,11€
            || preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches) // $ 133.040,20
        ) {
            $email->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($this->normalizeCurrency($matches['currency']));
        }
    }

    private function normalizeDate(string $string)
    {
        $in = [
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)$/', // 17/04/2017 08:10
        ];
        $out = [
            '$2/$1/$3 $4',
        ];

        return preg_replace($in, $out, $string);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            // do not add unused currency!
            'USD' => ['US$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
