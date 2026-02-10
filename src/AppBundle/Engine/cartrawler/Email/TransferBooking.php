<?php

namespace AwardWallet\Engine\cartrawler\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TransferBooking extends \TAccountChecker
{
    public $mailFiles = "cartrawler/it-373686944.eml, cartrawler/it-383058417.eml, cartrawler/it-388781314.eml, cartrawler/it-390517518.eml, cartrawler/it-390715714.eml";

    public static $detectProvider = [
        'skywards' => [
            'subject'       => [' Emirates Transfers'],
            'imgLogoLink'   => ['emirates/emirates-transfers-poweredby-logo'],
            'body'          => ['Emirates Transfers.'],
        ],
        'ryanair' => [
            'subject'       => ['Ryanair Transfers'],
            'imgLogoLink'   => ['ryanair/ryanair-transfers-logo'],
            'body'          => ['Ryanair Transfers.'],
        ],
        'klm' => [
            'subject'       => ['KLM Transfers'],
            'imgLogoLink'   => ['klm/klm-transfers-logo'],
            'body'          => ['KLM Transfers.'],
        ],
    ];

    public $emailSubject = '';
    public $detectSubjects = [
        // en
        ' - Booking confirmed',
        ' - Booking cancelled',
        // pt
        ' - Reserva confirmada',
        // fr
        ' - Réservation confirmée',
        // de
        ' - Buchung bestätigt',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Please review the details below.' => 'Please review the details below.',
            // 'Hi ' => '',
            // 'Status:' => '',
            'CancelledStatuses' => 'Cancelled',
            'CancelledPhrases'  => 'we have now cancelled your transfer booking ',
            'Booking ID:'       => 'Booking ID:',
            // 'Route' => '',
            // 'Distance' => '',
            // 'Vehicle type' => '',
            // 'Provider' => '',
            // 'Flight Number' => '',
            // 'Payment' => '',
            // 'Paid' => '',
            // 'Provider number' => '',
        ],
        'pt' => [
            'Please review the details below.' => 'Reveja os detalhes abaixo.',
            'Hi '                              => 'Olá,',
            'Status:'                          => 'Estado:',
            // 'CancelledStatuses' => '',
            // 'CancelledPhrases' => '',
            'Booking ID:'     => 'ID da reserva:',
            'Route'           => 'Destino',
            'Distance'        => 'Distância',
            'Vehicle type'    => 'Tipo de veículo',
            'Provider'        => 'Fornecedor',
            'Flight Number'   => 'Número do voo',
            'Payment'         => 'Pagamento',
            'Paid'            => 'Pago',
            'Provider number' => 'Número do fornecedor',
        ],
        'fr' => [
            'Please review the details below.' => 'Veuillez vérifier les informations ci-dessous.',
            'Hi '                              => 'Bonjour ',
            'Status:'                          => 'Statut:',
            // 'CancelledStatuses' => '',
            // 'CancelledPhrases' => '',
            'Booking ID:'     => 'Identifiant de réservation:',
            'Route'           => 'Itinéraire',
            'Distance'        => 'Distance',
            'Vehicle type'    => 'Type de véhicule',
            'Provider'        => 'Fournisseur',
            'Flight Number'   => 'Número do voo',
            'Payment'         => 'Paiement',
            'Paid'            => 'Payé',
            'Provider number' => 'Numéro du fournisseur',
        ],
        'de' => [
            'Please review the details below.' => 'Bitte prüfen Sie nachfolgend die Details.',
            'Hi '                              => 'Hallo ',
            'Status:'                          => 'Status:',
            // 'CancelledStatuses' => '',
            // 'CancelledPhrases' => '',
            'Booking ID:'     => 'Buchungs-ID:',
            'Route'           => 'Strecke',
            'Distance'        => 'Entfernung',
            'Vehicle type'    => 'Fahrzeugtyp',
            'Provider'        => 'Anbieter',
            'Flight Number'   => 'Número do voo',
            'Payment'         => 'Bezahlung',
            'Paid'            => 'Bezahlt',
            'Provider number' => 'Nummer des Anbieters',
        ],
    ];

    public $rentalCompanies = [
        'dollar'       => ['DOLLAR'],
        'perfectdrive' => ['BUDGET'],
        'avis'         => ['AVIS'],
        'alamo'        => ['ALAMO'],
        'sixt'         => ['SIXT'],
        'europcar'     => ['EUROPCAR'],
        'rentacar'     => ['ENTERPRISE'],
        'foxrewards'   => ['FOX'],
        'hertz'        => ['HERTZ'],
        'thrifty'      => ['THRIFTY'],
        'mozio'        => ['MOZIO'],
        //        '' => ['ABBYCAR'],
        //        '' => ['CIRCULAR'],
        //        '' => ['DELPASO'],
        //        '' => ['ILHA VERDE'],
        //        '' => ['TALIXO'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cartrawler.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@cartrawler.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if ($this->striposAll($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->emailSubject = $parser->getSubject();

        if ($this->http->XPath->query('//a[contains(@href,".cartrawler.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"@cartrawler.com")]')->length > 0
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['subject']) && $this->striposAll($this->emailSubject, $params['subject']) === true) {
                $providerCode = $code;

                break;
            }
        }

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['body']) && $this->http->XPath->query('//*[' . $this->contains($params['body']) . ']')->length > 0) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['imgLogoLink']) && $this->http->XPath->query('//img[' . $this->contains($params['imgLogoLink'], '@src') . ']')->length > 0) {
                $providerCode = $code;

                break;
            }
        }

        if (is_numeric($providerCode)) {
            $providerCode = null;
        }

        if (!empty($providerCode)) {
            $email->setProviderCode($providerCode);
        }

        $this->parseCar($email);

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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$detectProvider), function ($v) {
            return (is_numeric($v)) ? false : true;
        });
    }

    private function parseCar(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Booking ID:'))}]]/*[2]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/"));

        $t = $email->add()->transfer();

        // General
        $t->general()
            ->noConfirmation()
            ->status($this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Status:'))}]]/*[2]"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]",
                null, true, "/^\s*{$this->opt($this->t('Hi '))}\s*([[:alpha:] \-]{3,})[.,]+\s*$/"), false);

        if (preg_match("/^\s*{$this->opt($this->t('CancelledStatuses'))}\s*$/", $t->getStatus())
            || $this->http->XPath->query("(//*[{$this->contains($this->t('CancelledPhrases'))}])[1]")->length > 0
            || $this->http->XPath->query("//tr[count(*) = 2][*[1][{$this->eq($this->t('Status:'))}]]/*[2]//*[contains(@style, '#C91911')]")->length > 0
        ) {
            $t->general()
                ->cancelled(true);

            if (empty($t->getStatus())) {
                $t->general()
                    ->status('Cancelled');
            }
        }

        // Segment

        $s = $t->addSegment();

        // Departure
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Route'))}]/following::td[not(.//td)][normalize-space()][1][not({$this->contains($this->t('Flight Number'))})]");

        if (!preg_match("/\d+/", $name)) {
            $s->departure()
                ->name($name);
        } else {
            $s->departure()
                ->address($name);
        }
        $s->departure()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Route'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]")))
        ;

        // Arrival
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Route'))}]/following::td[not(.//td)][normalize-space()][2][not({$this->contains($this->t('Flight Number'))})]");

        if (!preg_match("/\d+/", $name)) {
            $s->arrival()
                ->name($name);
        } else {
            $s->arrival()
                ->address($name);
        }
        $s->arrival()
            ->noDate()
        ;

        $xpath = "//tr[count(*) = 3][*[1][{$this->eq($this->t('Distance'))}] and *[2][{$this->eq($this->t('Vehicle type'))}] and *[3][{$this->eq($this->t('Provider'))}]]/following-sibling::tr";
        // Extra
        $s->extra()
            ->type($this->http->FindSingleNode($xpath . "[normalize-space()][1]/*[2]"))
            ->image(preg_replace('/(\.png)\?auto=.+/', '$1', $this->http->FindSingleNode($xpath . "/*[2][.//img][1]//img/@src",
                null, true, "/^\s*http.+/")), true, true)
            ->miles($this->http->FindSingleNode($xpath . "[normalize-space()][1]/*[1]"))
        ;

        // Company

        $transferCompany = $this->http->FindSingleNode($xpath . "[normalize-space()][1]/*[3]");

        if (!empty($transferCompany)) {
            foreach ($this->rentalCompanies as $code => $companyNames) {
                foreach ($companyNames as $name) {
                    if ($name === $transferCompany) {
                        $t->program()->code($code);

                        break 2;
                    }
                }
            }
        }
        $companyPhoneText = $this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Provider number'))}]][not({$this->eq($this->t('Provider number'))})]",
            null, true, "/{$this->opt($this->t('Provider number'))}\s*(.+)/");
        $phones = preg_split("/(?: or |\s*,\s*)/", $companyPhoneText);

        foreach ($phones as $phone) {
            // +48 606 148 173 (International) or 060 614 8173 (Local)
            $phone = preg_replace("/\s*\(\D+\)\s*$/", '', $phone);

            if (preg_match("/^[\d \+\-\(\)]+$/", $phone) && strlen(preg_replace('/\D+/', '', $phone)) > 5) {
                $t->program()->phone($phone);
            }
        }

        // Price
        if (!$t->getCancelled()) {
            $total = $this->getTotal($this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment'))}]/following::td[not(.//td)][{$this->starts($this->t('Paid'))}]",
                null, true, "/{$this->opt($this->t('Paid'))}\s*(.+)/"));

            $t->price()
                ->currency($total['currency'])
                ->total($total['amount']);
        }
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'CA$' => 'CAD',
            'A$'  => 'AUD',
            '€'   => 'EUR',
            'US$' => 'USD',
            '$'   => '$',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (empty($dict['Please review the details below.']) || empty($dict['Booking ID:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($dict['Please review the details below.'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Booking ID:'])}]")->length > 0
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Friday, 23 June 2023, 12:10
            // Sábado, 27 de maio de 2023, 23:40
            // Montag, 29. Mai 2023, 15:00
            '/^\s*[[:alpha:]\-]+\s*[\s,]\s*(\d{1,2})[.]?\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\s*[,\s]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
            // Monday, September 18, 2023, 10:00 AM
            '/^\s*[[:alpha:]\-]+\s*[\s,]\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*[,\s]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/^\s*(\d{1,2}\s+)([[:alpha:]]+)(\s+\d{4},\s*\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                return strtotime($m[1] . $en . $m[3]);
            }
        }

        return null;
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
