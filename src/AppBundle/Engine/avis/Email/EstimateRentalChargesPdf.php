<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Schema\Parser\Email\Email;

class EstimateRentalChargesPdf extends \TAccountChecker
{
    public $mailFiles = "avis/it-132325587.eml, avis/it-132338604.eml, avis/it-132704015.eml, avis/it-58386835.eml, avis/it-58386894.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'Start Location'      => ['Agence départ'],
            'End Location'        => ['Agence Retour'],
            'travellerStart'      => 'Estimation des frais de location',
            'travellerEnd'        => 'Informations de location',
            'Document Number'     => 'Numéro du document',
            'Reg. No.'            => "Plaque d'immatriculation",
            'Start Date / Time'   => ['Date / Heure départ', 'Date/Heure départ'],
            'End Date / Time'     => ['Fin Date / Heure', 'Fin Date/Heure'],
            'Days Charged'        => 'Jours facturés',
            'Rental Charges'      => 'Frais de location',
            'Total Charges'       => 'Coût total',
            'Deductions'          => 'Déductions',
            'Included'          => 'Inclus',
            'Taxable' => 'Soumis à TVA',
        ],
        'es' => [
            'travellerStart'      => 'Presupuesto de gastos del alquiler',
            'travellerEnd'        => 'Información del cliente',
            'Document Number'     => 'Número de documento',
            'Reg. No.'            => "N.º de matrícula",
            'Start Location'      => ['Lugar de Inicio'],
            'Start Date / Time'   => ['Inicio Fecha/Hora'],
            'End Location'        => ['Oficina de devolución'],
            'End Date / Time'     => ['Final Fecha/Hora'],
            'Days Charged'        => 'Días cargados',
            'Rental Charges'      => 'Gastos del alquiler',
            'Total Charges'       => 'Cargos totales',
            'Deductions'          => 'Déductions',
            'Taxable' => 'Imponible',
        ],
        'en' => [
            'Start Location'    => ['Start Location'],
            'End Location'      => ['End Location'],
            'travellerStart'    => 'Estimate of Rental Charges',
            'travellerEnd'      => 'Rental Information',
            'Start Date / Time' => ['Start Date / Time', 'Start Date/Time'],
            'End Date / Time'   => ['End Date / Time', 'End Date/Time'],
//            'Taxable' => '',
        ],
        'no' => [
            'travellerStart'      => 'Estimert leiekostnad',
            'travellerEnd'        => 'Informasjon om leieforholdet',
            'Document Number'     => 'Leiekontraktsnummer',
            'Reg. No.'            => "Reg. No.",
            'Start Location'      => ['Utleiestasjon'],
            'Start Date / Time'   => ['Startdato/-klokkeslett'],
            'End Location'        => ['Returstasjon'],
            'End Date / Time'     => ['Returdato/-klokkeslett'],
            'Days Charged'        => 'Dager belastet',
            'Rental Charges'      => 'Leiekostnad',
            'Total Charges'       => 'Totalsum',
//            'Deductions'          => '',
            'Taxable' => 'Skattepliktig',
        ],
        'da' => [
            'travellerStart'      => 'Estimeret lejebeløb',
            'travellerEnd'        => 'Lejeoplysninger',
            'Document Number'     => 'Dokumentnummer',
            'Reg. No.'            => "Nummerplade",
            'Start Location'      => ['Startsted'],
            'Start Date / Time'   => ['Start Dato/Tid'],
            'End Location'        => ['Slutsted'],
            'End Date / Time'     => ['Slutdato/-klokkeslæt'],
            'Days Charged'        => 'Fakturerede dage',
            'Rental Charges'      => 'Lejeopkrævninger',
            'Total Charges'       => 'Gebyrer i alt',
//            'Deductions'          => '',
            'Taxable' => 'Momspligtig',
        ],
        'it' => [
            'travellerStart'      => 'Stima dei costi di noleggio',
            'travellerEnd'        => 'Informazioni sul noleggio',
            'Document Number'     => 'Numero documento',
            'Reg. No.'            => "Targa",
            'Start Location'      => ['Località di Partenza'],
            'Start Date / Time'   => ['Inizio Data/Ora'],
            'End Location'        => ['Località di rientro'],
            'End Date / Time'     => ['Fine Data/Ora'],
            'Days Charged'        => 'Giorni addebitati',
            'Rental Charges'      => 'Tariffe di noleggio',
            'Total Charges'       => 'Costi totali',
//            'Deductions'          => '',
            'Taxable' => 'Imponibile',
        ],
        'de' => [
            'travellerStart'      => 'Voraussichtliche Mietkosten',
            'travellerEnd'        => 'Daten der Anmietung',
            'Document Number'     => 'Mietvertragsnummer',
            'Reg. No.'            => "Kennzeichen",
            'Start Location'      => ['Anmietstation'],
            'Start Date / Time'   => ['Datum/Zeit'],
            'End Location'        => ['Rückgabestation'],
            'End Date / Time'     => ['Datum/Zeit'],
            'Days Charged'        => 'Berechnete Tage',
            'Rental Charges'      => 'Mietkosten',
            'Total Charges'       => 'Gesamtbetrag',
//            'Deductions'          => '',
            'Taxable' => 'Steuerpflichtig',
        ],
    ];

    private $subjects = [
        'fr' => ['Reçu de location (numéro du contrat de location'],
        'en' => ['Vehicle Rental Receipt (Rental Agreement Number'],
        'es' => ['Factura del alquiler del vehículo (Número de contrato de alquiler'],
        'no' => ['Kvittering for leie av kjøretøy (Leiekontraktsnummer:'],
        'da' => ['Kvittering for leje af køretøj (lejeaftalenummer:'],
        'it' => ['Ricevuta noleggio veicolo (Contratto di noleggio n.:'],
        'de' => ['Quittung zur Fahrzeuganmietung (Mietvertragsnr.:'],
    ];

    private $detectors = [
        'fr' => ['Estimation des frais de location'],
        'en' => ['Estimate of Rental Charges'],
        'es' => ['Presupuesto de gastos del alquiler'],
        'no' => ['Estimert leiekostnad'],
        'da' => ['Estimeret lejebeløb'],
        'it' => ['Stima dei costi di noleggio'],
        'de' => ['Voraussichtliche Mietkosten'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@avis-europe.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = self::detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && strpos($textPdf, 'www.avis.co.uk') === false) {
                continue;
            }

            if ($this->detectBody($textPdf) && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parseCar($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('EstimateRentalChargesPdf' . ucfirst($this->lang));

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

    private function parseCar(Email $email, $text): void
    {
        $car = $email->add()->rental();

        $traveller = preg_match("/{$this->opt($this->t('travellerStart'))}\n+([[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]])\n+{$this->opt($this->t('travellerEnd'))}/u", $text, $m)
            ? $m[1] : null;
        $car->general()->traveller($traveller);

        if (preg_match("/^[ ]*({$this->opt($this->t('Document Number'))})[ ]+([-A-Z\d]{5,})$/m", $text, $m)) {
            $car->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Reg. No.'))})[ ]+([-A-Z\d]{5,})$/m", $text, $m)) {
            $car->general()->confirmation($m[2], $m[1], true);
        }

        $locationPickup = preg_match("/{$this->opt($this->t('Start Location'))}[ ]+([\s\S]+?)\n+{$this->opt($this->t('Start Date / Time'))}/", $text, $m)
            ? $m[1] : null;

        $datePickup = preg_match("/{$this->opt($this->t('Start Date / Time'))}[ ]+([\s\S]+?)\n\n/", $text, $m)
            ? $m[1] : null;

        $car->pickup()
            ->location(preg_replace('/\s+/', ' ', $locationPickup))
            ->date2(preg_replace('/\s+/', ' ', $datePickup));

        $locationDropoff = preg_match("/{$this->opt($this->t('End Location'))}[ ]+([\s\S]+?)\n+{$this->opt($this->t('End Date / Time'))}/u", $text, $m)
            ? $m[1] : null;

        $dateDropoff = preg_match("/{$this->opt($this->t('End Location'))}[\s\S]+?\s+{$this->opt($this->t('End Date / Time'))}[ ]+([\s\S]+?)\n(?:\n|{$this->opt($this->t('Days Charged'))})/u", $text, $m)
            ? $m[1] : null;

        $car->dropoff()
            ->location(preg_replace('/\s+/', ' ', $locationDropoff))
            ->date2(preg_replace('/\s+/', ' ', $dateDropoff));

        $paymentText = preg_match("/\n{3,}([\s\S]+?\n[= ]{5,})\n/", $text, $m)
            ? $m[1] : null;

        if (preg_match("/\n[_ ]{5,}\n.+?[ ]{2,}(?<currency>[^\d)(\n\s][^\d)(\n\s]{0,5}?)[ ]*(?<amount>\d[,.\'\d ]*)\n[= ]{5,}$/", $paymentText, $m)) {
            $car->price()
                ->currency($m['currency'])
            ;
            if (preg_match("/\n\s*{$this->opt($this->t('Deductions'))}\s+/u", $paymentText)) {
                $car->price()
                    ->total($this->normalizeAmount($m['amount']))
                ;
                if (preg_match("/\n\s*{$this->opt($this->t('Deductions'))}[ ]{2,}(?<currency>[^\d)(\n\s][^\d)(\n\s]{0,5}?)[ ]*[\-\-](?<amount>\d[,.\'\d ]*)\n/", $paymentText, $matches)) {
                    $car->price()
                        ->discount($this->normalizeAmount($matches['amount']));
                }
            } elseif (preg_match("/\n\s*{$this->opt($this->t('Total Charges'))}[ ]{2,}(?<currency>[^\d)(\n\s][^\d)(\n\s]{0,5}?)[ ]*(?<amount>\d[,.\'\d ]*)\n/u", $paymentText, $matches)) {
                $car->price()
                    ->total($this->normalizeAmount($matches['amount']))
                ;
            }
            $rows = $this->split("/(.+ ".$m['currency']." *\d.*|.+ Inclus(?:\n|$))/", preg_replace("/\n\s*{$this->opt($this->t('Total Charges'))}\s+[\s\S]+/", '', $paymentText));
            $rows = preg_replace("/^ *([\W_])\\1{3,} *$/m", '', $rows);
            $cost = 0.0;
            foreach ($rows as $row) {
                if (preg_match("/ ({$this->opt($this->t('Included'))}|Inc[[:alpha:]]*)(?:\n|$)/", $row)) {
                    continue;
                }
                if (preg_match("/^\s*{$this->opt($this->t('Taxable'))}/", $row)) {
                    continue;
                }
                if (preg_match("/^\s*{$this->opt($this->t('Rental Charges'))}[*]*[ ]+" . preg_quote($m['currency'], '/') . "[ ]*(?<amount>\d[,.\'\d ]*)\n/", $row, $matches)) {
                    $cost += $this->normalizeAmount($matches['amount']);
                    continue;
                }
                if (preg_match("/^\s*(.+) +" . preg_quote($m['currency'], '/') . "[ ]*(?<amount>\d[,.\'\d ]*)\n([\s\S]*)/u", $row, $matches)) {
                    $car->price()->fee(preg_replace("/\s*\n\s*/", ' ', $matches[1]."\n".$matches[3]), $this->normalizeAmount($matches['amount']));
                    continue;
                }
            }

            if (!empty($cost)) {
                $car->price()->cost($cost);
            }
        }
    }

    private function detectBody(?string $text): bool
    {
        if (empty($text) || !isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if (strpos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Start Location']) || empty($phrases['End Location'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Start Location']) !== false
                && $this->strposArray($text, $phrases['End Location']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
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
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
