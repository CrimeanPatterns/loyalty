<?php

namespace AwardWallet\Engine\europcar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "europcar/it-48941168.eml, europcar/it-48967721.eml, europcar/it-70752116.eml, europcar/it-71024136.eml";

    public $reFrom = ['@europcar.com', '@europcar.fr'];
    public $reBody = [
        'de' => ['Rechnung'],
        'it' => ['Questa è la tua fattura', 'Questa è la tua nota credito', 'Questa è la tua rifatturazione'],
        'es' => ['Esta es su nota de abono', 'Esta es su factura'],
        'fr' => ['Votre refacturation', 'Votre avoir', 'Votre facture'],
        'pt' => ['Esta é sua nota de crédito'],
        'en' => ['Invoice'],
    ];
    public $reSubject = [
        // de
        'Ihre Europcar Rechnung',
        // it, es, fr, en
        'Europcar Invoice',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*";
    public static $dict = [
        'de' => [
            'Driver'                  => 'Fahrer',
            'Check-out Station'       => ['Anmietung'],
            'Check-in Station'        => ['Rueckgabe'],
            'Invoice'                 => ['RECHNUNGSNR.BITTE ANGEBEN'],
            'Invoice date'            => ['Rechnungsdatum', 'Rechnungsdatum'],
            'Rental Agreement Number' => [
                'Mietvertragsnummer',
            ],
            'Reservation No'  => 'Reservierungs Nr.',
            'Vehicle'         => ['Fahrzeuggruppe'],
            'Total Charges:'  => ['Rechnungsbetrag:'],
            'Vehicle model'   => ['Fahrzeug'],
            'Price'           => 'Preis/Einh',
            'Your References' => 'Referenzen',
        ],
        'it' => [
            'Driver'                  => 'Conducente',
            'Driver ID'               => 'Identificativo co',
            'Check-out Station'       => ['Stazione Uscita'],
            'Check-in Station'        => ['Stazione Rientro'],
            'Invoice'                 => ['Fattura/FACTURE', 'Fattura/Factura', 'Nota Credito/Nota Abono'],
            'Invoice date'            => ['Data stampa/Date', 'Data stampa/Fecha'],
            'Rental Agreement Number' => ['Contratto/Contrat', 'Contratto/Nº Contrato'],
            'Reservation No'          => 'N.Prenotazione',
            'Vehicle'                 => ['Categoria veicolo'],
            'Total Charges:'          => ['Totale Fattura/Total Facturé:', 'Totale Fattura/Total Cargos:'],
            'Vehicle model'           => ['Categoria modello'],
            'Price'                   => 'Importo',
            'Your References'         => 'I tuoi riferimenti',
        ],
        'es' => [
            'Driver'                  => 'Conductor',
            'Driver ID'               => ['Identificación del conductor', 'Identificación de'],
            'Check-out Station'       => ['Oficina de Salida'],
            'Check-in Station'        => ['Oficina de Entrada'],
            'Invoice'                 => ['Nota de Abono', 'Factura'],
            'Invoice date'            => ['Fecha de Factura'],
            'Rental Agreement Number' => ['Contrato de Alquiler No'],
            'Reservation No'          => 'Nº de Reserva',
            'Vehicle'                 => ['Categoría de vehículo'],
            'Total Charges:'          => ['Total Cargos', 'Total Cargos:'],
            'Vehicle model'           => ['Modelo de vehículo'],
            'Price'                   => 'Precio',
            'Your References'         => 'Su referencia',
        ],
        'fr' => [
            'Driver'                  => 'Conducteur',
            'Driver ID'               => 'Conducteur ID',
            'Check-out Station'       => ['Station Départ'],
            'Check-in Station'        => ['Station Retour'],
            'Invoice'                 => ['FACTURE'],
            'Invoice date'            => ['Date de Facture'],
            'Rental Agreement Number' => [
                'Contrat de Location No',
            ],
            'Reservation No'  => 'Réservation No',
            'Vehicle'         => ['Véhicule - Catégorie'],
            'Total Charges:'  => ['Total Facturé:'],
            'Vehicle model'   => ['Véhicule - Modèle'],
            'Price'           => 'Mt Facturé',
            'Your References' => 'Vos références',
        ],
        'en' => [
            'Driver'                  => 'Driver',
            'Check-out Station'       => ['Check-out Station', 'Pick-up Location'],
            'Check-in Station'        => ['Check-in Station', 'Return Location'],
            'Invoice'                 => ['Invoice', 'Invoice/Fattura', 'Invoice/Fatura', 'Invoice/Factura'],
            'Invoice date'            => ['Invoice date', 'Date/Data stampa', 'Date/Data', 'Invoice Date', 'Date/Fecha'],
            'Rental Agreement Number' => [
                'Rental Agreement Number',
                'Rental Agrmnt No/Contr.noleggio n.',
                'RA/Contrato Nº',
            ],
            'Reservation No' => 'Reservation No',
            'Vehicle'        => ['Vehicle', 'Vehicle category', 'Vehicle Category'],
            'Total Charges:' => ['Total Charges:', 'Total Charges/Totale Fattura:', 'Total Charges/Total Cargos:'],
            'Vehicle model'  => ['Vehicle model', 'Vehicle Model'],
        ],
    ];
    private $keywordProv = 'EUROPCAR';

    private $negativeAmounts = false;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);

        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && !preg_match("/\b{$this->opt($this->keywordProv)}\b/i", $headers['subject'])
        ) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2; // 2 types pdf
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $r = $email->add()->rental();

        if (preg_match("/({$this->opt($this->t('Rental Agreement Number'))})[ ]*(\d+)/", $textPDF, $m)) {
            $r->general()->confirmation($m[2], $this->re("/(.+?)(?:\/|$)/", $m[1]));
        }
        // no collect, because he different in Charged and Refunded
//        if (preg_match("/({$this->opt($this->t('Invoice'))})[ ]*(\d+)/", $textPDF, $m)) {
//            $r->general()->confirmation($m[2], $this->re("/(.+?)(?:\/|$)/", $m[1]));
//        }
        if (preg_match("/({$this->opt($this->t('Reservation No'))})[ ]*(\d+)/", $textPDF, $m)) {
            $r->general()->confirmation($m[2], $this->re("/(.+?)(?:\/|$)/", $m[1]), true);
        }

        // not booking date
//        $r->general()->date(strtotime($this->re("/{$this->opt($this->t('Invoice date'))}[ ]*(.+)/", $textPDF)));

        if ($this->stripos($textPDF, $this->t('Your References'))) {
            $this->parseDetails_1($textPDF, $r);
        } else {
            $this->parseDetails_2($textPDF, $r);
        }
        $this->parseSums($textPDF, $r);

        if (preg_match("/\b{$this->opt($this->t('Days'))}[ ]+({$this->opt($this->t('Refunded'))}).+\d+\b/", $textPDF, $m)) {
            // it-71024136.eml
            $r->general()->status($m[1]);

            if ($this->negativeAmounts) {
                $r->general()->cancelled();
            }
        }

        return true;
    }

    private function parseDetails_1($textPDF, Rental $r): void
    {
        $this->logger->notice(__METHOD__);
        $account = $this->re("/{$this->opt($this->t('Driver ID'))}[ ]*(\w+)/u", $textPDF);

        if (empty($account)) {
            $account = $this->re("/{$this->t('Driver ID')}\s*\n[ ]{5,}(\d{5,12})\n/u", $textPDF);
        }

        if (!empty($account)) {
            $r->program()->account($account, false);
        }
        $r->general()->traveller($this->re("/{$this->opt($this->t('Driver'))}[ ]*(\S.+?)(?:[ ]{3,}|\n)/", $textPDF), true);

        if (preg_match("/{$this->opt($this->t('Check-out Station'))}[ ]+(?<loc>.+)[ ]{1,}(?<dateActual>\d+\.\d+\.\d+[ ]+\d+:\d+)[ ]+(?<dateCharged>\d+\.\d+\.\d+[ ]+\d+:\d+)/u",
            $textPDF, $m)) {
            $r->pickup()
                ->date(strtotime($m['dateActual']))
                ->location($m['loc']);
        } elseif (preg_match("/{$this->opt($this->t('Check-out Station'))}[ ]+(?<loc>.+?)[ ]{1,}(?<dateActual>\d+\.\d+\.\d+[ ]+\d+:\d+)\n/u",
            $textPDF, $m)) {
            $r->pickup()
                ->date(strtotime($m['dateActual']))
                ->location($m['loc']);
        }

        if (preg_match("/{$this->opt($this->t('Check-in Station'))}[ ]+(?<loc>.+)[ ]{1,}(?<dateActual>\d+\.\d+\.\d+[ ]+\d+:\d+)[ ]+(?<dateCharged>\d+\.\d+\.\d+[ ]+\d+:\d+)/",
            $textPDF, $m)) {
            $r->dropoff()
                ->date(strtotime($m['dateActual']))
                ->location($m['loc']);
        } elseif (preg_match("/{$this->opt($this->t('Check-in Station'))}[ ]+(?<loc>.+?)[ ]{1,}(?<dateActual>\d+\.\d+\.\d+[ ]+\d+:\d+)\n/",
            $textPDF, $m)) {
            $r->dropoff()
                ->date(strtotime($m['dateActual']))
                ->location($m['loc']);
        }
        $r->car()
            ->type($this->re("/{$this->opt($this->t('Vehicle'))}[ ]{2,}(.+?)(?:[ ]{2,}|\n)/", $textPDF))
            ->model($this->re("/{$this->opt($this->t('Vehicle model'))}[ ]{2,}(.+?)(?:[ ]{2,}|\n)/", $textPDF));
    }

    private function parseDetails_2($textPDF, Rental $r): void
    {
        $this->logger->notice(__METHOD__);

        if (preg_match("/{$this->opt($this->t('Driver'))}[ ]*(?:(\d+)[ ]{2,})?(\w+.*)/", $textPDF, $m)) {
            if (isset($m[1]) && !empty($m[1])) {
                $r->program()->account($m[1], false);
            }
            $r->general()->traveller($m[2], true);
        }

        if (preg_match("/{$this->opt($this->t('Check-out Station'))}[ ]{2,}(?<dateActual>\d+\.\d+\.\d+[ ]+\d+:\d+)[ ]+(?<dateCharged>\d+\.\d+\.\d+[ ]+\d+:\d+)[ ]+(?<loc>.+)/",
            $textPDF, $m)) {
            $r->pickup()
                ->date(strtotime($m['dateActual']))
                ->location($m['loc']);
        }

        if (preg_match("/{$this->opt($this->t('Check-in Station'))}[ ]{2,}(?<dateActual>\d+\.\d+\.\d+[ ]+\d+:\d+)[ ]+(?<dateCharged>\d+\.\d+\.\d+[ ]+\d+:\d+)[ ]+(?<loc>.+)/",
            $textPDF, $m)) {
            $r->dropoff()
                ->date(strtotime($m['dateActual']))
                ->location($m['loc']);
        }
        $r->car()->type($this->re("/{$this->opt($this->t('Vehicle'))}[ ]{2,}(.+?)[ ]{2,}/", $textPDF));
    }

    private function parseSums($textPDF, Rental $r): void
    {
        $total = $cost = $tax = null;
        $currency = $this->re("/{$this->opt($this->t('Price'))}[ ]+([A-Z]{3})[ ]{2,}/", $textPDF);

        if (preg_match("/{$this->opt($this->t('Total Charges:'))}.+[ ]{2,}(?<minus>-)?[ ]*(?<amount>\d[,.\'\d]*)[ ]*\n/", $textPDF, $m)) {
            $total = PriceHelper::cost($m['amount']);

            if (!empty($m['minus'])) {
                $this->negativeAmounts = true;
            }
        }

        if ($total === null
            && preg_match("/{$this->opt($this->t('Total Charges:'))}[ ]{2,}(?:-)?(?<cost>\d[,.\'\d]*)[ ]+[A-Z]{3}[ ]{2,}(?:-)?(?<tax>\d[,.\'\d]*)[ ]+[A-Z]{3}[ ]{2,}(?<minus>-)?(?<total>\d[,.\'\d]*)[ ]+[A-Z]{3}/", $textPDF, $m)
        ) {
            $total = PriceHelper::cost($m['total']);
            $cost = PriceHelper::cost($m['cost']);
            $tax = PriceHelper::cost($m['tax']);

            if (!empty($m['minus'])) {
                $this->negativeAmounts = true;
            }
        } elseif ($total === null
            && preg_match("/{$this->opt($this->t('Total Charges:'))}[ ]{2,}.+[ ]{2,}(?<minus>-)?(?<total>\d[,.\'\d]*)[ ]+[A-Z]{3}\s*\n/", $textPDF, $m)
        ) {
            $total = PriceHelper::cost($m['total']);

            if (!empty($m['minus'])) {
                $this->negativeAmounts = true;
            }
        } elseif (preg_match("/{$this->opt($this->t('Value Added Tax'))}.*?[ ]{2,}(?:-)?(?<cost>\d[,.\'\d]*)[ ]{2,}(?:-)?(?<tax>\d[,.\'\d]*)/",
            $textPDF, $m)) {
            $cost = PriceHelper::cost($m['cost']);
            $tax = PriceHelper::cost($m['tax']);
        }

        if ($tax !== null && $cost !== null) {
            $r->price()
                ->total($total)
                ->cost($cost)
                ->tax($tax)
                ->currency($currency);
        } elseif ($total !== null) {
            $r->price()
                ->total($total)
                ->currency($currency);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body): bool
    {
        if (isset($this->reBody)) {
            if ($this->stripos($body, $this->keywordProv)) {
                foreach ($this->reBody as $lang => $reBody) {
                    if ($this->stripos($body, $reBody)) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Driver"], $words["Check-out Station"])) {
                if ($this->stripos($body, $words["Driver"]) && $this->stripos($body, $words["Check-out Station"])) {
                    $this->lang = $lang;
                    $this->logger->debug('Lang ' . $this->lang);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
