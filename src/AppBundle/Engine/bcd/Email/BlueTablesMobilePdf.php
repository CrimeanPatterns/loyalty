<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BlueTablesMobilePdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-134415189.eml, bcd/it-33405760.eml, bcd/it-33405762.eml, bcd/it-35179682.eml, bcd/it-60552983.eml";

    public $reFrom = ["@bcdtravel.com"];
    public $reBody = [
        'en'  => ['Additional trip information', 'Travel Summary'],
        'en2' => ['Remarks', 'Travel Summary'],
        'en3' => ['Total duration', 'Travel Summary'],
        'de'  => ['Zusätzliche Informationen zur Reise', 'Reiseübersicht'],
        'fr'  => ['Renseignements complémentaires sur le voyage', 'Récapitulatif'],
        'pt'  => ['Informações de viagem adicionais', 'Resumo de Viagem'],
        'it'  => ['Ulteriori informazioni sul viaggio', 'Riepilogo Viaggio'],
        'es'  => ['Pasajero', 'Recibo del billete'],
    ];
    public $reSubject = [
        '#Travel Receipt for .+? Travel Date \d+\D{3}$#', // en
        '#Reçu pour voyage de .+? Date du voyage \d+\D{3}$#u', // fr
        '#Ricevuta di viaggio per .+? Data viaggio, \d+\D{3}$#u', // it
    ];
    public $lang = '';
    public $pdf;
    //public $pdfNamePattern = "(?:.*Travel Receipt|Itinerary|Anhang der Reisebelegsmitteilung|Pièce jointe de communication - Reçu pour voyage|Anexo com comunicado de recibo de viagem|Allegata Comunicazione di Ricevuta Viaggio).*pdf";
    public $pdfNamePattern = "*.*pdf";
    public static $dict = [
        'en' => [
            'Ticket Number' => ['Ticket Number', 'ElectronicTicket Number', 'Electronic Ticket Number / Issue Date'],
            'endTicket'     => ['Service Fee Number', 'Travel Summary'],
            'segments'      => ['Flight', 'Layover', 'Rail', 'Hotel', 'Car', 'Limo'],
            'Economy'       => ['Economy', 'BUSINESS'],
            'Tel'           => ['Tel', 'TEL'],
        ],
        'de' => [
            //            'Ticket Number' => [''],
            //            'endTicket' => [''],
            'Passenger'             => 'Reisender/Reisende',
            'Agency Record Locator' => 'Agenturbuchungscode',
            'Ticket Receipt'        => 'Ticketbeleg',
            'Invoice'               => '', // should empty!!!
            'Total Amount'          => 'Gesamtbetrag',
            'segments'              => ['Flugnummer/Leistung', 'Bahn', 'Hotel', 'Mietwagen'],
            'Flight'                => 'Flugnummer/Leistung',
            //            'Loyalty Number' => '',
            'Airline Record Locator' => 'Buchungscode der Fluggesellschaft',
            //            'Operated By:' => '',
            'Departure'     => 'Abfahrt',
            'Seat'          => 'Sitzplatz',
            'Arrival'       => 'Ankunft',
            'Weather'       => 'Wetter',
            'miles'         => 'Meilen',
            'Non-stop'      => 'Non-stop',
            'CO2 Emissions' => 'CO2-Emissionen',
            'Equipment'     => 'Fluggerät',
            //            'Meal' => '',
            'Confirmed' => 'Bestätigt',
            'Remarks'   => 'Bemerkungen',
            // Hotel
            'Confirmation'        => 'Bestätigungsnummer',
            // 'Hotel'               => '',
            'Address'             => 'Adresse',
            'Check In'            => 'Anreise',
            'Number of Rooms'     => 'Anzahl der Zimmer',
            'Cancellation Policy' => 'Stornobedingungen',
            'Number of Persons'   => 'Personenanzahl',
            'plus tax'            => 'ggfs. zzgl. tax',
            // Car
            'Pick Up'         => 'Anmietung',
            'Type'            => 'Fahrzeuggruppe',
            // 'Class'           => '',
            'Drop Off'        => 'Abgabe',
            'Tel'             => 'Telefonnummer',
            // 'Fax'             => '',
            'Estimated Total' => 'Voraussichtlicher Gesamtbetrag',
        ],
        'fr' => [
            'Ticket Number'         => ['Billet électronique n°'],
            'endTicket'             => ['Récapitulatif'],
            'Passenger'             => 'Voyageur',
            'Agency Record Locator' => 'N° de réservation BCD Travel',
            'Ticket Receipt'        => 'Reçu pour billet',
            'Invoice'               => '', // should empty!!!
            'Total Amount'          => 'Montant Total',
            'segments'              => ['Vol', 'Escale', 'Hôtel'], // need rail & car
            'Flight'                => 'Vol',
            'Layover'               => 'Escale',
            //            'Loyalty Number' => '',
            'Airline Record Locator'       => 'Référence dossier de la compagnie aérienne',
            'Airline Record Locator-start' => 'Référence dossier de la compagnie',
            'Airline Record Locator-end'   => ' aérienne',
            'Operated By:'                 => 'Opéré par:',
            'Departure'                    => 'Départ',
            'Seat'                         => 'Siège',
            'Arrival'                      => 'Arrivée',
            'Weather'                      => 'Le temps',
            'miles'                        => 'miles',
            'Non-stop'                     => 'Direct',
            'CO2 Emissions'                => 'Emissions de CO2',
            'Equipment'                    => 'Appareil',
            'Meal'                         => 'Repas',
            'Confirmed'                    => 'Confirmé',
            'Remarks'                      => ' Remarques',
            // Hotel
            // 'Confirmation'                 => '',
            // 'Hotel'                        => '',
            // 'Address'                      => '',
            // 'Check In'                     => '',
            // 'Number of Rooms'              => '',
            // 'Cancellation Policy'          => '',
            // 'Number of Persons'            => '',
            // 'plus tax'                     => '',
            // Car
            // 'Pick Up'         => '',
            // 'Type'            => '',
            // 'Class'           => '',
            // 'Drop Off'        => '',
            // 'Tel'             => '',
            // 'Fax'             => '',
            // 'Estimated Total' => '',
        ],
        'pt' => [
            'Ticket Number'         => ['Número do Bilhete Eletrônico'],
            'endTicket'             => ['Resumo de Viagem'],
            'Passenger'             => 'Passageiro',
            'Agency Record Locator' => 'Localizador do PNR da agência',
            'Ticket Receipt'        => 'Recibo do bilhete',
            'Invoice'               => '', // should empty!!!
            'Total Amount'          => 'Valor Total',
            'segments'              => ['Voo', 'Hotel', 'Car'], // need rail & Layover
            'Flight'                => 'Voo',
            //            'Layover' => '',
            //            'Loyalty Number' => '',
            'Airline Record Locator' => 'Localizador de registro da companhia aérea',
            'Operated By:'           => ['Operado por:', 'Operated By'],
            'Departure'              => 'Partida',
            'Seat'                   => 'Lugar',
            'Arrival'                => 'Chegada',
            'Weather'                => 'Tempo',
            'miles'                  => 'milhas',
            'Non-stop'               => 'Directo',
            'CO2 Emissions'          => 'Emissões de CO2',
            'Equipment'              => 'Equipamento',
            'Meal'                   => 'Refeição',
            'Confirmed'              => 'Confirmed',
            'Remarks'                => 'Notas',
            // Hotel
            'Confirmation' => 'Confirmação',
            'Hotel'        => 'Hotel',
            'Address'      => 'Endereço',
            'Check In'     => 'Check in',
            //            'Preço por noite',
            //            'Check out',
            'Number of Rooms'     => 'Número de quartos',
            'Cancellation Policy' => 'Condições de Cancelamento',
            'Number of Persons'   => 'Número de Pessoas',
            'plus tax'            => 'podem ser',
            // Car
            'Pick Up'         => 'Retirada',
            'Type'            => 'Tipo',
            'Class'           => 'Classe',
            'Drop Off'        => 'Devolução',
            'Tel'             => 'Telefone',
            'Fax'             => 'Fax',
            'Estimated Total' => 'Total estimado',
        ],
        'it' => [
            'Ticket Number'          => ['Numero Biglietto Elettronico'],
            'endTicket'              => ['Riepilogo Viaggio'],
            'Passenger'              => 'Passeggero',
            'Agency Record Locator'  => 'Codice Identificativo Agenzia',
            'Ticket Receipt'         => 'Ricevuta del Biglietto',
            'Invoice'                => '', // should empty!!!
            'Total Amount'           => 'Totale Importo',
            'segments'               => ['Volo', 'Hotel', 'Car', 'Scalo'], // need rail & car
            'Flight'                 => 'Volo',
            'Layover'                => 'Scalo',
            'Loyalty Number'         => 'Numero carta fedeltà',
            'Airline Record Locator' => 'Localizzatore record compagnie aeree',
            'Operated By:'           => ['Operato da:', 'Operated By'],
            'Departure'              => 'Partenza',
            'Seat'                   => 'Posto a Sedere',
            'Arrival'                => 'Arrivo',
            'Weather'                => 'Meteo',
            'miles'                  => 'miglia',
            'Non-stop'               => 'Diretto',
            'CO2 Emissions'          => 'Emissioni di CO2',
            'Equipment'              => 'Tipologia Aeromobile',
            'Meal'                   => 'Pasto',
            'Confirmed'              => 'Confirmed',
            'Remarks'                => 'Note',
            // Hotel
            'Confirmation' => 'Confirmação',
            'Hotel'        => 'Hotel',
            'Address'      => 'Indirizzo',
            'Check In'     => 'Entrata',
            //            'Tariffa per notte',
            //            'Uscita',
            'Number of Rooms'     => 'Numero di camere',
            'Cancellation Policy' => 'Politica di Cancellazione',
            //            'Number of Persons' => '',
            //            'plus tax' => '',
            // Car
            'Pick Up'         => 'Retirada',
            'Type'            => 'Tipo',
            'Class'           => 'Classe',
            'Drop Off'        => 'Devolução',
            'Tel'             => 'Telefone',
            'Fax'             => 'Fax',
            'Estimated Total' => 'Total estimado',
        ],
        'es' => [
            'Ticket Number'         => ['Numero de Boleto (Electrónico)'],
            'endTicket'             => ['Resumen de Viaje'],
            'Passenger'             => 'Pasajero',
            'Agency Record Locator' => 'agencia',
            'Ticket Receipt'        => 'Recibo del billete',
            'Invoice'               => '', // should empty!!!
            'Total Amount'          => 'Importe total:',
            'segments'              => ['Vuelo', 'Hotel'], // need rail & car
            'Flight'                => 'Vuelo',
            //'Layover' => '',
            //'Loyalty Number' => '',
            'Airline Record Locator' => 'Localizador de registros de aerolíneas',
            'Operated By:'           => ['Operado por:', 'Operated By'],
            'Departure'              => 'Salida',
            'Seat'                   => 'Asiento',
            'Arrival'                => 'Llegada',
            'Weather'                => 'Tiempo',
            'miles'                  => 'millas',
            'Non-stop'               => 'Directo',
            'CO2 Emissions'          => 'Emisiones de CO2',
            'Equipment'              => 'Equipo',
            'Meal'                   => 'Comida',
            'Confirmed'              => 'Confirmado',
            'Remarks'                => 'AVISO IMPORTANTE',
            'Economy'                => ['Economy', 'Económica'],
            // Hotel
            'Confirmation' => 'Confirmación',
            'Hotel'        => 'Hotel',
            'Address'      => 'Dirección',
            'Check In'     => 'Check in',
            //            'Tariffa per notte',
            //            'Uscita',
            'Number of Rooms'     => 'N° de Habitaciones',
            'Cancellation Policy' => 'Política de cancelación',
            'Number of Persons'   => 'Núm. de personas',
            //            'plus tax' => '',
            // Car
            // 'Pick Up'         => '',
            // 'Type'            => '',
            // 'Class'           => '',
            // 'Drop Off'        => '',
            // 'Tel'             => '',
            // 'Fax'             => '',
            // 'Estimated Total' => '',
        ],
    ];

    private $patterns = [
        'time'  => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    private $otaPhones = [];
    private $pax = [];
    private $tickets = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language in ' . $i . ' attach-pdf');

                        continue;
                    }
                    $this->parseEmailPdf($text, $email);
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

            if ((strpos($text, 'BCD TRAVEL') !== false
                    || strpos($text, 'BCD Travel') !== false
                    || strpos($text, 'BCDTRAVEL') !== false
                    || strpos($text, 'BCDTravel') !== false
                )
                && $this->assignLang($text)
            ) {
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if ($fromProv && stripos($headers["subject"], $reSubject) !== false) {
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
        $types = 2; // flight | hotel;
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $allText = $textPDF;
        $posLastRemark = $this->str_rpos($textPDF, $this->t('Remarks'));

        if ($posLastRemark !== false) {
            $remarks = substr($textPDF, $posLastRemark);
            $textPDF = substr($textPDF, 0, $posLastRemark);
        } else {
            $remarks = false;
        }
        $info = $this->re("#\n([ ]*{$this->opt($this->t('Passenger'))}\s+{$this->opt($this->t('Agency Record Locator'))}.+?)\n(?:[ ]+{$this->opt($this->t('Ticket Receipt'))}|\n)#s", $textPDF);
        $tablePos = [];

        if (preg_match("#^(([ ]*){$this->opt($this->t('Passenger'))}[ ]{2,}){$this->opt($this->t('Agency Record Locator'))}\n#m", $info, $m)) {
            $tablePos = [mb_strlen($m[2]), mb_strlen($m[1])];
        }
        $tableInfo = $this->splitCols($info, $tablePos);

        if (count($tableInfo) !== 2) {
            $tableInfo = $this->splitCols($info, $this->colsPos($info));
        }

        if (count($tableInfo) !== 2) {
            $this->logger->debug("other format (info)");

            return false;
        }

        if (preg_match_all("#(?:^|\n)[ ]*{$this->opt($this->t('Passenger'))}\s+([[:upper:]][-.\'[:upper:]\s]*[[:upper:]])(?:\n|$)#u", $tableInfo[0], $m)) {
            $this->pax = preg_replace("/\s+/", ' ', $m[1]);
        }

        $otaConfirmationNumbers = [];
        $recLocs = $this->splitText($tableInfo[1], "#{$this->opt($this->t('Agency Record Locator'))}\s+#");

        foreach ($recLocs as $recLoc) {
            $recLocRows = array_values(array_filter(array_map("trim", explode("\n", $recLoc))));

            if (count($recLocRows) === 1) {
                $otaConfirmationNumbers[] = ['number' => $recLocRows[0], 'name' => $this->t('Agency Record Locator')];
            } else {
                if (count($recLocRows) === 3
                    && in_array($recLocRows[1], (array) $this->t('Reference number by traveler'))
                ) {
                    if ($recLocRows[0] === $recLocRows[2]) {
                        $otaConfirmationNumbers[] = ['number' => $recLocRows[0], 'name' => $this->t('Agency Record Locator')];
                    } else {
                        $otaConfirmationNumbers[] = ['number' => $recLocRows[0], 'name' => $this->t('Agency Record Locator')];
                        $otaConfirmationNumbers[] = ['number' => $recLocRows[2], 'name' => $this->t('Reference number by traveler')];
                    }
                } else {
                    $this->logger->debug("other format (Agency Record Locator)");

                    return false;
                }
            }
        }

        foreach ($otaConfirmationNumbers as $ota) {
            $email->ota()->confirmation($ota['number'], $ota['name']);
        }

        $node = $this->re("#\n[ ]*{$this->opt($this->t('Ticket Number'))}\s+{$this->opt($this->t('Invoice'))}(.+?)\n[ ]*{$this->opt($this->t('endTicket'))}#s",
            $textPDF);

        if (preg_match_all("#^[ ]*(\d{5,})#m", $node, $m)) {
            $this->tickets = $m[1];
        }

        if ($email->getPrice()) {
            $total = $email->getPrice()->getTotal();
        }

        $totalAmount = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Ticket Receipt'))}\s+{$this->opt($this->t('Total Amount'))}[ :]+(.+)#", $textPDF));

        if ($totalAmount['Total'] !== '') {
            if (!empty($total)) {
                $totalAmount['Total'] += $total;
            }
            $email->price()
                ->total($totalAmount['Total'])
                ->currency($totalAmount['Currency']);
        }

        if (!empty($remarks)) {
            $phone = trim($this->re("#BCD TRAVEL PHONE NUMBER IS ([\d \-\+\(\)]+)#", $remarks));

            if (!empty($phone) && !in_array($phone, $this->otaPhones)) {
                $this->otaPhones[] = $phone;
                $email->ota()
                    ->phone($phone, 'BCD TRAVEL');
            }
        }

        $reservations = $this->splitText($textPDF, "#\n([ ]{0,15}{$this->opt($this->t('segments'))}(?:[ ]{3,}|\n))#", true);

        foreach ($reservations as $reservation) {
            if (preg_match("#^[ ]*(?:{$this->t('Flight')}|{$this->t('Layover')})#", $reservation) > 0) {
                $flights[] = $reservation;
            } elseif (preg_match("#^[ ]*(?:{$this->t('Hotel')})#", $reservation) > 0) {
                $hotels[] = $reservation;
            } elseif (preg_match("#^[ ]*(?:{$this->t('Car')})#", $reservation) > 0) {
                $rentals[] = $reservation;
            } elseif (preg_match("#^[ ]*(?:{$this->t('Rail')})#", $reservation) > 0) {
                $trains[] = $reservation;
            } elseif (preg_match("#^[ ]*(?:{$this->t('Limo')})#", $reservation) > 0) {
                $transfers[] = $reservation;
            } else {
                $this->logger->debug('Collection of this type of reservation is not described.');

                return false;
            }
        }

        if (isset($flights)) {
            if (!$this->parseFlight($flights, $email, $textPDF)) {
                return false;
            }
        }

        if (isset($trains)) {
            if (!$this->parseTrain($trains, $email)) {
                return false;
            }
        }

        if (isset($hotels)) {
            if (!$this->parseHotels($hotels, $email)) {
                return false;
            }
        }

        if (isset($rentals)) {
            if (!$this->parseRentals($rentals, $email)) {
                return false;
            }
        }

        if (isset($transfers)) {
            if (!$this->parseTransfers($transfers, $email)) {
                return false;
            }
        }

        return true;
    }

    private function parseFlight(array $flights, Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->noConfirmation()
            ->travellers($this->pax);

        if (!empty($this->tickets)) {
            $r->issued()
                ->tickets(array_unique($this->tickets), false);
        }
        $accNums = [];

        foreach ($flights as $segment) {
            $acc = $this->re("#{$this->t('Loyalty Number')} ([A-Z\d]{2}XXXX[A-Z\d]+)#", $segment);

            if (empty($acc)) {
                $acc = $this->re("#{$this->t('Loyalty Number')} (XXXX[A-Z\d]+)#", $segment);
            }

            if (!empty($acc) && !in_array($acc, $accNums)) {
                $accNums[] = $acc;
                $r->program()
                    ->account($acc, true);
            }
            $s = $r->addSegment();
            $confNo = $this->re("#{$this->t('Airline Record Locator')} ([A-Z\d]{5,6})#", $segment);

            if (empty($confNo)) {
                $confNo = $this->re("#{$this->t('Airline Record Locator-start')}\n.+?{$this->t('Airline Record Locator-end')} ([A-Z\d]{5,6})#", $segment);
            }

            if (!empty($confNo)) {
                $s->airline()
                    ->confirmation($confNo);
            }

            if (
                preg_match("# ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)[ ]+{$this->t('Airline Record Locator')}#", $segment, $m)
                || preg_match("# ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)[ ]+{$this->t('Airline Record Locator-start')}#", $segment, $m)
                || preg_match("# ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)[ ]+{$this->t('Airline Record Locator-end')}#", $segment, $m)
                || preg_match("#(?:.*\n){1,3}[ ]{0,10}(?:\S+ )+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})([ ]{5,}|\n)#", $segment, $m)
            ) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            // Operated By: Air Canada Express - Sky Regional Operated By /Air Canada Express - Sky Regional
            // Operated By: Operated By /Latam Airlines Brasil For Latam Airlines
            // Operated By: Latam Airlines
            $node = $this->re("#({$this->opt($this->t('Operated By:'))}[\s]*.+?)\b\s*(?:Dba\b| For |\n)#i", $segment);
            $regOp = array_map(function ($s) {return trim($s, " :"); }, (array) $this->t('Operated By:'));

            if (preg_match("#{$this->opt($this->t('Operated By:'))}\s*(?:{$this->opt($regOp)}[ \/]*)?(.+?)(?:{$this->opt($regOp)}.*|$)#", $node, $op)) {
                $operator = $op[1];
            }

            if (isset($operator) && !empty($operator)) {
                $s->airline()->operator($operator);
            }

            $node = $this->re("#{$this->t('Online check-in')}.*\n([\s\S]+)\n[ ]*{$this->t('Departure')}[ ]+{$this->t('Seat')}#", $segment)
                ?? $this->re("#{$this->t('Airline Record Locator')}.*\n([\s\S]+)\n[ ]*{$this->t('Departure')}[ ]+{$this->t('Seat')}#", $segment);

            if (empty($node)) {
                $node = $this->re("#{$this->t('Airline Record Locator-end')}.*\n([\s\S]+)\n[ ]*{$this->t('Departure')}[ ]+{$this->t('Seat')}#", $segment);
            }

            if (empty($node) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                $node = $this->re("#" . $s->getAirlineName() . $s->getFlightNumber() . ".*\n([\s\S]+)\n[ ]*{$this->t('Departure')}[ ]+{$this->t('Seat')}#", $segment);
            }

            if (preg_match("#^[ ]*([A-Z]{3})\s+([\s\S]+?)\s+([A-Z]{3})$#m", $node, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[3]);

                if (preg_match("#^(\d+[ ]*h(?:\D+)?[ ]*\d+[ ]*min)\s*(\d+ {$this->t('miles')})?$#i", trim($m[2]), $v)) {
                    $s->extra()->duration($v[1]);

                    if (isset($v[2]) && !empty($v[2])) {
                        $s->extra()->miles($v[2]);
                    }
                }
            } elseif (preg_match("#^[ ]*(\D+)\s*(\d+\s*{$this->opt($this->t('miles'))})\s*(\D+)$#m", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->noCode();
                $s->arrival()
                    ->name($m[3])
                    ->noCode();

                if (preg_match("#[ ]{10,}(\d+\s*h\s*\d+\s*min).+\n.+[ ]{10,}(\d+\s*{$this->opt($this->t('miles'))})#i", $node, $v)) {
                    $s->extra()
                        ->duration($v[1])
                        ->miles($v[2]);
                }
            }

            $info = $this->re("#\n([ ]*{$this->t('Departure')}[\s\S]+?)\n[ ]+(?:{$this->t('CO2 Emissions')}:|{$this->t('Non-stop')}|{$this->t('Equipment')}:|{$this->t('Meal')}:|\*{$this->opt($this->t('Operated By:'))}|$|\n\n)#",
                $segment);

            if (preg_match("#^([ ])+{$this->t('Departure')}([ ]+){$this->t('Seat')}([ ]+){$this->t('Arrival')}#", $info, $m)) {
                $len = max([mb_strlen($m[1]), mb_strlen($m[2]), mb_strlen($m[3])]);

                if (preg_match_all("/^(.+?\w\b[ ]{3,{$len}})\b\w/mu", $info, $pos1matches)) {
                    $pos1 = min(array_map("mb_strlen", $pos1matches[1])) - 10;

                    if ($pos1 < 0) {
                        $pos1 = null;
                    }
                }

                if (preg_match_all("/^(.+\w\b[ ]{3,})\b/mu", $info, $pos2matches)) {
                    $pos2all = array_map("mb_strlen", $pos2matches[1]);

                    if (isset($pos1)) {
                        $pos2all = array_filter($pos2all, function ($s) use ($pos1) {
                            return $s > ($pos1 + 20);
                        });
                    }
                    $pos2 = min($pos2all) - 10;

                    if ($pos2 < 0) {
                        $pos2 = null;
                    }
                }

                if (isset($pos1, $pos2) && $pos1 < $pos2) {
                    $pos = [0, $pos1, $pos2];
                }
            }

            if (!isset($pos)) {
                $this->logger->debug('bad segment');

                return false;
            }

            $table = $this->splitCols($info, $this->rowColsPos($this->inOneRow($info)));

            if (preg_match("#{$this->t('Departure')}\s+.+\s+(?<date>.+ \d{4})\s*(?:{$this->t('Weather')}\s*)?(?<time>\b\d+:\d+.*)\s*(?:Terminal\s+(?<terminal>[A-Z\d\s\.\-]+)(?:\n|$))?#u",
                $table[0], $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $term = trim(preg_replace("#\s+#", ' ', str_ireplace("terminal", ' ', $m['terminal'])));

                    if (!empty($term)) {
                        $s->departure()->terminal($term);
                    }
                }
            }

            if (preg_match("#{$this->t('Arrival')}\s+.+\s+(?<date>.+ \d{4})\s*(?:{$this->t('Weather')}\s*)?\D*(?<time>\b\d+:\d+.*)\s*(?:Terminal\s+(?<terminal>[A-Z\d\s\.\-]+)(?:\n|$))?#",
                $table[2], $m)) {
//                if (in_array($this->lang, ['pt'])) {
//                    $m['date'] = preg_replace("/[ ]de[ ]/i", ' ', $m['date']);// 23 De Fevereiro De 2020
//                }
                $m['date'] = $this->re("/.*?\b(\w+\s+(?:de\s+)?\w+\s+(?:de\s+)?\d{4})\s*$/ui", $m['date']);
                $s->arrival()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $term = trim(preg_replace("#\s+#", ' ', str_ireplace("terminal", ' ', $m['terminal'])));

                    if (!empty($term)) {
                        $s->arrival()->terminal($term);
                    }
                }
            }

            if (preg_match_all("#^[ ]*(\d{1,3}[A-z])(?:[ ]?\(\w+\))?(?: {$this->t('Confirmed')})?[- ]*$#mu", $table[1], $m)) {
                $s->extra()->seats($m[1]);
            }

            if (preg_match("#\n[ ]*(?<cabin>{$this->opt($this->t('Economy'))})(?:[ ]*\/[ ]*(?<bCode>[A-Z]{1,2}))?[ ]*\n+[ ]*(?<status>{$this->t('Confirmed')})\s*$#i", $table[1], $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode(empty($m['bCode']) ? null : $m['bCode'], false, true)
                    ->status($m['status']);
            }

            if (preg_match("#^[ ]*{$this->opt($this->t('Non-stop'))}[ ]*$#im", $segment)) {
                $s->extra()->stops(0);
            }

            if (preg_match("#{$this->t('Equipment')}:[ ]*(.+)#", $segment, $m)) {
                $s->extra()->aircraft($m[1]);
            }

            if (preg_match("#{$this->t('Meal')}:[ ]*(.+)#", $segment, $m)) {
                $s->extra()->meal($m[1]);
            }
        }

        return true;
    }

    private function parseTrain(array $rails, Email $email): bool
    {
        foreach ($rails as $segment) {
            $r = $email->add()->train();

            if (!empty($this->tickets)) {
                $r->setTicketNumbers(array_unique($this->tickets), false);
            }
            $acc = $this->re("#{$this->t('Loyalty Number')} ([A-Z\d]{2}XXXX[A-Z\d]+)#", $segment);

            if (!empty($acc)) {
                $r->program()
                    ->account($acc, true);
            }
            $s = $r->addSegment();
            $number = $this->re("#(.+)[ ]+{$this->t('Confirmation')} [A-Z\d]{5,6}#", $segment);

            if (preg_match("/^(.+?)\s+(\d{1,5}|E|N|S)\s*$/", $number, $m)) {
                $s->extra()
                    ->service($m[1])
                    ->number($m[2]);
            }
            $confNo = $this->re("#{$this->t('Confirmation')} ([A-Z\d]{5,6})#", $segment);
            $r->general()
                ->confirmation($confNo)
                ->travellers($this->pax);

            $node = $this->re("#{$this->t('Not working')}.*\n([\s\S]+)\n[ ]*{$this->t('Departure')}[ ]+{$this->t('Seat')}#", $segment)
                ?? $this->re("#{$this->t('Confirmation')}.*\n([\s\S]+)\n[ ]*{$this->t('Departure')}[ ]+{$this->t('Seat')}#", $segment);

            if (preg_match("#^[ ]*([A-Z]{3})\s+([\s\S]+?)\s+([A-Z]{3})$([\s\S]+)#m", $node, $m)) {
                if ($m[1] !== $m[3]) {
                    //it's not a station code
//                    $s->departure()
//                        ->code($m[1]);
//                    $s->arrival()
//                        ->code($m[3]);
                }

                $stations = $this->splitCols($m[4], $this->rowColsPos($this->inOneRow($m[4])));

                if (count($stations) === 2) {
                    $s->departure()->name(preg_replace('/\s+/', ' ', trim($stations[0])));
                    $s->arrival()->name(preg_replace('/\s+/', ' ', trim($stations[1])));
                }

                $pattern = "/^\s*(\d+[ ]*h[ ]*\d+[ ]*min)\s*(\d+ {$this->t('miles')})?\s*$/i";

                if (preg_match($pattern, $m[2], $v)
                    || count($stations) === 3 && preg_match($pattern, $stations[1], $v)
                ) {
                    $s->extra()->duration($v[1]);

                    if (!empty($v[2])) {
                        $s->extra()->miles($v[2]);
                    }
                }
            }

            $info = $this->re("#\n([ ]*{$this->t('Departure')}[\s\S]+?)\n[ ]+(?:{$this->t('CO2 Emissions')}:|{$this->t('Non-stop')}|$|\n\n)#",
                $segment);

            if (preg_match("#^([ ])+{$this->t('Departure')}([ ]+){$this->t('Seat')}([ ]+){$this->t('Arrival')}#", $info, $m)) {
                $len = max([mb_strlen($m[1]), mb_strlen($m[2]), mb_strlen($m[3])]);

                if (preg_match_all("/^(.+?\w\b[ ]{3,{$len}})\b\w/mu", $info, $pos1matches)) {
                    $pos1 = min(array_map("mb_strlen", $pos1matches[1])) - 10;

                    if ($pos1 < 0) {
                        $pos1 = null;
                    }
                }

                if (preg_match_all("/^(.+\w\b[ ]{3,})\b/mu", $info, $pos2matches)) {
                    $pos2all = array_map("mb_strlen", $pos2matches[1]);

                    if (isset($pos1)) {
                        $pos2all = array_filter($pos2all, function ($s) use ($pos1) {
                            return $s > ($pos1 + 20);
                        });
                    }
                    $pos2 = min($pos2all) - 10;

                    if ($pos2 < 0) {
                        $pos2 = null;
                    }
                }

                if (isset($pos1, $pos2) && $pos1 < $pos2) {
                    $pos = [0, $pos1, $pos2];
                }
            }

            if (!isset($pos)) {
                $this->logger->debug('bad segment');

                return false;
            }

            $table = $this->splitCols($info, $pos);

            if (preg_match("#{$this->t('Departure')}\s+.+\s+(?<date>.+ \d{4})\s*(?:{$this->t('Weather')}\s*)?(?<time>\b\d+:\d+.*)(?:\s*$|\n+[ ]*(?<name>.+))#ui",
                $table[0], $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])))
                    ->name($s->getDepName() ?? $m['name'] ?? '');
            }

            if (preg_match("#{$this->t('Arrival')}\s+.+\s+(?<date>.+ \d{4})\s*(?:{$this->t('Weather')}\s*)?\D*(?<time>\b\d+:\d+.*)(?:\s*$|\n+[ ]*(?<name>.+))#ui",
                $table[2], $m)) {
                $m['date'] = $this->re("/.*?\b(\w+\s+(?:de\s+)?\w+\s+(?:de\s+)?\d{4})\s*$/ui", $m['date']);
                $s->arrival()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])))
                    ->name($s->getArrName() ?? $m['name'] ?? '');
            }

            if (($d = $s->getDepDate()) && ($a = $s->getArrDate()) && $a === $d && date("H:i", $d) === "00:00") {
                // open ticket
                $r->removeSegment($s);
                $email->removeItinerary($r);

                continue;
            }

            if (preg_match_all("#^[ ]*(\d+[A-z])(?: {$this->t('Confirmed')})?[- ]*$#m", $table[1], $m)) {
                $s->extra()->seats($m[1]);
            }

            if (preg_match("#\n[ ]*(?<cabin>{$this->opt($this->t('Economy'))})[ ]*\n+[ ]*(?<status>{$this->t('Confirmed')})\s*$#i", $table[1], $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->status($m['status']);
            }
        }

        return true;
    }

    private function parseRentals(array $rentals, Email $email): bool
    {
        foreach ($rentals as $reservation) {
            $r = $email->add()->rental();

            $headerText = $this->re("/^(.+?)\n+[ ]*{$this->opt($this->t('Pick Up'))}(?:[ ]{2}|\n)/s", $reservation);

            if (preg_match_all("/^[ ]{0,40}(\S.+?\S)(?:[ ]{2}|$)/m", $headerText, $firstPhrases)) {
                $phrases = array_reverse($firstPhrases[1]);

                foreach ($phrases as $company) {
                    if (($code = BlueTablesMobile::normalizeProvider($company))) {
                        $r->program()->code($code);

                        break;
                    }
                }
            }

            $confirmation = $this->re("#[ ]{3,}{$this->t('Confirmation')} ([A-Z\d]{5,})#", $reservation);

            if (!empty($confirmation)) {
                $r->general()
                    ->confirmation($confirmation);
            } else {
                $r->general()->noConfirmation();
            }

            if (!empty($acc = $this->re("#[ ]{3,}{$this->opt($this->t('Loyalty Number'))} ([A-Z\d]{5,})#", $reservation))) {
                $r->program()
                    ->account($acc, !empty($this->re("#^\s*[A-Z\d]*(XXXX[A-Z\d]+)\s*$#", $acc)));
            }

            $info = $this->re("#\n([ ]*{$this->t('Pick Up')}.+?)\n[ ]+(?:{$this->t('CO2 Emissions')}:|{$this->t('Type')}:|$|\n\n)#s",
                $reservation);

            if (preg_match("#^([ ])+{$this->t('Pick Up')}([ ]+){$this->t('Class')}([ ]+){$this->t('Drop Off')}#", $info,
                $m)) {
                $len = max([strlen($m[1]), strlen($m[2]), strlen($m[3])]);

                if (preg_match_all("/^(.+?\w\b[ ]{3,{$len}})\b/m", $info, $pos1matches)) {
                    $pos1 = min(array_map("strlen", $pos1matches[1])) - 5;

                    if ($pos1 < 0) {
                        $pos1 = null;
                    }
                }

                if (preg_match_all("/^(.+\w\b[ ]{3,})\b/m", $info, $pos2matches)) {
                    $pos2all = array_map("strlen", $pos2matches[1]);

                    if (isset($pos1)) {
                        $pos2all = array_filter($pos2all, function ($s) use ($pos1) {
                            return $s > ($pos1 + 10);
                        });
                    }
                    $pos2 = min($pos2all) - 3;

                    if ($pos2 < 0) {
                        $pos2 = null;
                    }
                }

                if (isset($pos1, $pos2) && $pos1 < $pos2) {
                    $pos = [0, $pos1, $pos2];
                }
            }

            if (!isset($pos)) {
                $this->logger->debug('bad rental reservation');

                return false;
            }

            $table = $this->splitCols($info, $pos);

            if (preg_match("#{$this->t('Pick Up')}\s+.+\s+(?<date>.+ \d{4})\s+(?:{$this->t('Weather')}\s+)?(?<time>{$this->patterns['time']})#", $table[0], $m)) {
                $r->pickup()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
            }

            if (preg_match("#{$this->t('Pick Up')}\s+.+?\s+{$this->patterns['time']}\s+(.+?)\s+{$this->opt($this->t('Tel'))}:[ ]*({$this->patterns['phone']})(?:\s+{$this->opt($this->t('Fax'))}:[ ]*({$this->patterns['phone']}))?\s*$#s", $table[0], $m)) {
                $r->pickup()
                    ->location(trim(preg_replace("/\s+/", ' ', $m[1])))
                    ->phone($m[2]);

                if (!empty($m[3])) {
                    $r->pickup()->fax($m[3]);
                }
            }

            if (preg_match("#{$this->t('Drop Off')}\n+.+\n+[ ]*(?:.+\S[ ]{2,})?(?<date>.+ \d{4})\s+(?:{$this->t('Weather')}\s+)?.*?(?<time>{$this->patterns['time']})\n#", $table[2], $m)) {
                $r->dropoff()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
            }

            if (preg_match("#{$this->t('Drop Off')}\s+.+?\s+(?:{$this->patterns['time']})\s+(.+?)\s+{$this->opt($this->t('Tel'))}:[ ]*({$this->patterns['phone']})(?:\s+{$this->opt($this->t('Fax'))}:[ ]*({$this->patterns['phone']}))?\s*$#s", $table[2], $m)) {
                $r->dropoff()
                    ->location(trim(preg_replace("/\s+/", ' ', $m[1])))
                    ->phone($m[2]);

                if (!empty($m[3])) {
                    $r->dropoff()->fax($m[3]);
                }
            }

            $table = $this->re("#\n([ ]*{$this->t('Pick Up')}\s*.+?)\n{2,}#s", $reservation);

            if ((empty($r->getPickUpDateTime()) || empty($r->getDropOffDateTime()))
                && preg_match_all("/[ ]{3}([[:alpha:]]+ \d{1,2} \d{4}\b)/u", $table, $dateMatches)
                && count($dateMatches[1]) === 2
            ) {
                $r->pickup()
                    ->date(strtotime($dateMatches[1][0]));
                $r->dropoff()
                    ->date(strtotime($dateMatches[1][1]));
            }

            $total = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Estimated Total')}:[ ]+(.+)#", $reservation));

            if ($total['Total'] !== '') {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }

            if ($type = $this->re("#\n[ ]*{$this->t('Type')}:\s*(.+)#", $reservation)) {
                $r->car()->type($type);
            }
        }

        return true;
    }

    private function parseTransfers(array $transfers, Email $email)
    {
        foreach ($transfers as $reservation) {
            $t = $email->add()->transfer();
            $confirmation = $this->re("#[ ]{3,}{$this->t('Confirmation')} ([A-Z\d\-]{5,})#", $reservation);

            if (!empty($confirmation)) {
                $t->general()
                    ->confirmation($confirmation);
            } else {
                $t->general()->noConfirmation();
            }

            if (!empty($acc = $this->re("#[ ]{3,}{$this->opt($this->t('Loyalty Number'))} ([A-Z\d]{5,})#", $reservation))) {
                $t->program()
                    ->account($acc, !empty($this->re("#^\s*[A-Z\d]*(XXXX[A-Z\d]+)\s*$#", $acc)));
            }

            $s = $t->addSegment();

            $info = $this->re("#\n([ ]*{$this->t('Pick Up')}.+?)\n[ ]+(?:{$this->t('Number of Persons')}:|{$this->t('Cancellation Policy')}:|$|\n\n)#s",
                $reservation);

            if (preg_match("#^([ ])+{$this->t('Pick Up')}([ ]+){$this->t('Class')}([ ]+){$this->t('Drop Off')}#", $info,
                $m)) {
                $len = max([strlen($m[1]), strlen($m[2]), strlen($m[3])]);

                if (preg_match_all("/^(.+?\w\b[ ]{3,{$len}})\b/m", $info, $pos1matches)) {
                    $pos1 = min(array_map("strlen", $pos1matches[1])) - 5;

                    if ($pos1 < 0) {
                        $pos1 = null;
                    }
                }

                if (preg_match_all("/^(.+\w\b[ ]{3,})\b/m", $info, $pos2matches)) {
                    $pos2all = array_map("strlen", $pos2matches[1]);

                    if (isset($pos1)) {
                        $pos2all = array_filter($pos2all, function ($s) use ($pos1) {
                            return $s > ($pos1 + 10);
                        });
                    }
                    $pos2 = min($pos2all) - 5;

                    if ($pos2 < 0) {
                        $pos2 = null;
                    }
                }

                if (isset($pos1, $pos2) && $pos1 < $pos2) {
                    $pos = [0, $pos1, $pos2];
                }
            }

            if (!isset($pos)) {
                $this->logger->debug('bad transfer reservation');

                return false;
            }

            $table = $this->splitCols($info, $pos);

            if (preg_match("#{$this->t('Pick Up')}\s+.+\s+(?<date>.+ \d{4})\s+(?:{$this->t('Weather')}\s+)?(?<time>\d+:\d+.*)#",
                $table[0], $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
            }

            if (preg_match("#{$this->t('Pick Up')}\s+.+?\s+\d+:\d+[^\n]*\s+(.+?)\s*\n\s*{$this->opt($this->t('Tel'))}:?([\+\-\(\)\d \/]+)(?:\s+{$this->opt($this->t('Fax'))}:([\+\-\(\)\d \/]+))?\s*$#s",
                $table[0], $m)) {
                $s->departure()
                    ->name(trim(preg_replace("/\s+/", ' ', $m[1])));
            }

            if (preg_match("#{$this->t('Drop Off')}\s+.+\s+(?<date>.+ \d{4})\s+(?:{$this->t('Weather')}\s+)?\D*(?<time>\b\d+:\d+.*)#",
                $table[2], $m)) {
                $s->arrival()
                    ->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
            }

            if (preg_match("#{$this->t('Drop Off')}\s+.+?\s+(?:\d+:\d+[^\n]*|)\s+(.+?)\s*$#s",
                $table[2], $m)) {
                $s->arrival()
                    ->name(trim(preg_replace("/\s+/", ' ', $m[1])));
            }

            if ($guest = $this->re("#\n[ ]*{$this->t('Number of Persons')}:\s*(\d+)#", $reservation)) {
                $s->extra()->adults($guest);
            }
        }

        return true;
    }

    private function parseHotels(array $hotels, Email $email)
    {
        foreach ($hotels as $reservation) {
            $r = $email->add()->hotel();
            $r->general()
                ->confirmation($this->re("#[ ]{3,}{$this->t('Confirmation')} ([A-Z\d]{5,})#", $reservation));

            if (!empty($acc = $this->re("#[ ]{3,}{$this->opt($this->t('Loyalty Number'))} ([A-Z\d]{5,})#", $reservation))) {
                $r->program()
                    ->account($acc, !empty($this->re("#^\s*[A-Z\d]*(XXXX[A-Z\d]+)\s*$#", $acc)));
            }

            $hotelName = trim($this->re("#^[ ]+{$this->opt($this->t('Hotel'))}[^\n]*\n(.+?)(?:[ ]{3,}|\n)#", $reservation));

            if (empty($hotelName)) {
                $hotelName = trim($this->re("#^[ ]+{$this->opt($this->t('Hotel'))}\n[^\n]*\n\s+(.+?)[ ]{3,}#", $reservation));
            }

            $r->hotel()
                ->name($hotelName);
            $node = $this->re("#\n[ ]*{$this->opt($this->t('Address'))}\s*(.+?)\n\n\n#s", $reservation);

            if (preg_match("#(.+)\n[ ]*([\+\-\(\)\d ]{5,})$#s", $node, $m)) {
                $r->hotel()
                    ->address(trim(preg_replace("#\s+#", ' ', $m[1])));

                if (preg_match("#(^[-+()\dA-Z\s.,\\\/:]+\d+[-+()\dA-Z\s.,\\\/:]+$)#", trim(preg_replace("#\s+#", ' ', $m[2])), $phone)) {
                    $r->hotel()
                        ->phone($phone[1]);
                }
            } else {
                $r->hotel()
                    ->address(trim(preg_replace("#\s+#", ' ', $node)));
            }

            $table = $this->re("#\n([ ]*{$this->opt($this->t('Check In'))}\s*.+?)(?:\n{2,}|{$this->t('CO2 Emissions')})#su", $reservation);

            if (in_array($this->lang, ['pt', 'es'])) {
                $table = preg_replace("/[ ]de[ ]/i", ' ', $table); // 23 De Fevereiro De 2020
            }

            if (preg_match_all("/(?:[ ]{3,}|\n[ ]+)(\w+ \w+ \d{4}\b)/", $table, $dateMatches)
                && count($dateMatches[1]) == 2
            ) {
                $r->booked()
                    ->checkIn($this->normalizeDate($dateMatches[1][0]))
                    ->checkOut($this->normalizeDate($dateMatches[1][1]));
            }

            if (preg_match("#[ ]{3,}([A-Z]{3} \d[\d\.\,]+ {$this->t('plus tax')}.+?)(?:\n[ ]+({$this->t('Confirmed')}))?\s*$#su", $table, $m)) {
                $room = $r->addRoom();
                $room
                    ->setRate(trim(preg_replace("#\s+#", ' ', $m[1])));

                if (isset($m[2])) {
                    $r->general()->status($m[2]);
                }
            }

            $rooms = $this->re("#{$this->t('Number of Rooms')} (\d+)#", $reservation);

            if (!empty($rooms)) {
                $r->booked()
                ->rooms($this->re("#{$this->t('Number of Rooms')} (\d+)#", $reservation));
            }
            $r->booked()
                ->guests($this->re("#{$this->t('Number of Persons')} (\d+)#", $reservation), false, true);
            $total = $this->getTotalCurrency($this->re("#Total Price[ ]+(.+)#", $reservation));

            if ($total['Total'] !== '') {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }

            if ($cancel = $this->re("#{$this->t('Cancellation Policy')}(.+?)\n[ ]*(?:Rate per|{$this->t('Number of Persons')}|Additional Information|Fax\b|Notes)#s", $reservation)) {
                $r->general()->cancellation(trim(preg_replace("#\s+#", ' ', $cancel)));
            }
            $this->detectDeadLine($r);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#Cancel (?<priorDays>\d+) days? prior to arrival local hotel time to avoid any charges#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorDays'] . ' days', '00:00');

            return;
        }

        if (preg_match("#PRECIO DE LA PRIMERA NOCHE SI CANCELAS DURANTE LOS (?<priorDays>\d+) DIAS ANTES#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorDays'] . ' days', '00:00');

            return;
        }

        if (preg_match("#CXL/CANCELLATION POLICY/CANCEL BEFORE (\d+\/\d+\/\d{4} \d+\-\d+ [AP]M) LOCAL#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime(str_replace('-', ':', $m[1])));

            return;
        }

        if (preg_match("#^CANCEL ON (?<date>.+?) BY (?<time>\d+:\d+) LT\.#i",
                $cancellationText, $m)
            || preg_match("#Please cancel by (?<time>\d+[:]*\d+) on (?<date>.+?) Hotel local Time to avoid a cancellation Penalty#i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['daet'] . ', ' . $m['time']));

            return;
        }

        if (preg_match("#Cancel by (\d{1,2})[:]?(\d{2}[ap]m) day of arrival local hotel time to avoid any charges#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1] . ':' . $m[2]);

            return;
        }

        if (preg_match("#CANCELAR (\d+) HORAS ANTES DE (\d+(?::\d+)?[ap]m) DIA DA CHEGADA#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . 'hours', $m[2]);

            return;
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //16Jan2020, 18:00
            '#^(\d+)\s*(\w+?)\s*(\d{4}),\s+(\d+:\d+)$#u',
            //16JAN20, 1800
            '#^(\d+)\s*(\w+?)\s*(\d{2}),\s+(\d+)[:]?(\d+)$#u',
            //23 De Fevereiro De 2020
            '#^\s*(\d+)\s+(?:de\s+)?(\w+)\s+(?:de\s+)?(\d{4})#iu',
            //January 17 2020
            '#^\s*(\w+)\s+(\d+)\s+(\d{4})#iu',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 20$3, $4:$5',
            '$1 $2 $3',
            '$2 $1 $3',
        ];

        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            //2,30,572
            if (preg_match("/^\d+\,\d+\,\d+\s*$/", $m['t'])) {
                $m['t'] = str_replace(',', '', $m['t']);
            }

            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function str_rpos($haystack, $needle, $start = 0)
    {
        $tempPos = mb_strpos($haystack, $needle, $start);

        if ($tempPos === false) {
            if ($start == 0) {
                //Needle not in string at all
                return false;
            } else {
                //No more occurances found
                return $start - mb_strlen($needle);
            }
        } else {
            //Find the next occurance
            return $this->str_rpos($haystack, $needle, $tempPos + mb_strlen($needle));
        }
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
