<?php

namespace AwardWallet\Engine\bondi\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "bondi/it-51033249.eml, bondi/it-52425342.eml, bondi/it-720595659.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'confNumber'     => 'Este é seu número de reserva',
            'flight'         => 'Voo',
            'departure'      => 'Saída',
            'arrival'        => 'Chegada',
            'passengers'     => 'PASSAGEIROS',
            'segmentHeaders' => ['IDA', 'VOLTA'],
        ],
        'es' => [
            'confNumber'     => 'Tu código de reserva es',
            'flight'         => 'Vuelo',
            'departure'      => 'Partida',
            'arrival'        => 'Arribo',
            'passengers'     => 'PASAJEROS',
            'segmentHeaders' => ['IDA', 'VUELTA'],
        ],
        'en' => [
            'confNumber'     => 'Your confirmation number is',
            'flight'         => 'Flight:',
            'departure'      => 'Departure:',
            'arrival'        => 'Arrival:',
            'passengers'     => 'PASSENGERS',
            'segmentHeaders' => ['OUTBOUND'],
        ],
    ];

    private $detectors = [
        'pt' => ['Bem-vindo à liberdade de voar', 'detalhes da sua reserva'],
        'es' => ['Bienvenido a la libertad de volar', 'detalle de tu reserva'],
        'en' => ['Welcome to the freedom of flying', 'detail of your reservation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flybondi.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Flybondi - Reserva') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".flybondi.com/") or contains(@href,"www.flybondi.com") or contains(@href,"booking.flybondi.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($email);
        $email->setType('ReservationDetails' . ucfirst($this->lang));

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $passengers = array_filter($this->http->FindNodes("//div[{$this->eq($this->t('passengers'))}]/following-sibling::div[normalize-space()]", null, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u"));
        $f->general()->travellers($passengers);

        $segments = $this->http->XPath->query("//td[ preceding-sibling::td and descendant::text()[{$this->starts($this->t('departure'))}] and descendant::text()[{$this->starts($this->t('arrival'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $segmentHtml = $this->http->FindHTMLByXpath('.', null, $segment);
            $segmentText = $this->htmlToText($segmentHtml);

            /*
                IDA
                São Paulo (GRU) → Buenos Aires (EPA)
                GRU > EPA
                Voo: FO5801
                Saída: Sexta 24/01/2020 18:45
                Chegada: Sexta 24/01/2020 21:50
            */
            $pattern = "/^\s*{$this->opt($this->t('segmentHeaders'))}"
                . "\s*(?<cityDep>.+?)[ (]+(?<codeDep>[A-Z]{3})[-→) ]+(?<cityArr>.+?)[ (]+(?<codeArr>[A-Z]{3})[) ]*\n+"
                . "(?:.{1,}\n+){0,}"
                . "[ ]*{$this->opt($this->t('flight'))}[: ]+(?<aName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<aNumber>\d+)[ ]*\n+"
                . "[ ]*{$this->opt($this->t('departure'))}[: ]+(?<dateDep>.{6,}?)[ ]*\n+"
                . "[ ]*{$this->opt($this->t('arrival'))}[: ]+(?<dateArr>.{6,}?)[ ]*(?:\n|$)"
                . "/u";

            if (preg_match($pattern, $segmentText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['aNumber']);

                $s->departure()
                    ->name($m['cityDep'])
                    ->code($m['codeDep'])
                    ->date2($this->normalizeDate($m['dateDep']));

                $s->arrival()
                    ->name($m['cityArr'])
                    ->code($m['codeArr'])
                    ->date2($this->normalizeDate($m['dateArr']));
            }
        }

        $paymentHtml = $this->http->FindHTMLByXpath("//td[ preceding-sibling::td and descendant::text()[{$this->starts($this->t('Total:'))}] ]");
        $paymentText = $this->htmlToText($paymentHtml);

        if (preg_match("/^[ ]*{$this->opt($this->t('Total:'))}\s*(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/m", $paymentText, $m)) {
            // ARS$ 2347.98    |    R$774,20
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']));
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['arrival'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Sábado 22/02/2020 07:50
            '/^[-[:alpha:]]{2,}\s+(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+(\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)$/u',
        ];
        $out = [
            '$2/$1/$3 $4',
        ];

        return preg_replace($in, $out, $text);
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
            'ARS' => ['ARS$', 'AR$'],
            'BRL' => ['R$'],
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
