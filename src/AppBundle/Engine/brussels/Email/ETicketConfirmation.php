<?php

namespace AwardWallet\Engine\brussels\Email;

// HTML-version: brussels/YourTicket

class ETicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "brussels/it-245835411.eml, brussels/it-27355391.eml, brussels/it-4245539.eml, brussels/it-4270122.eml, brussels/it-4359858.eml, brussels/it-4373624.eml, brussels/it-4424068.eml, brussels/it-4432476.eml, brussels/it-4451034.eml, brussels/it-4493009.eml, brussels/it-5035235.eml, brussels/it-5038453.eml, brussels/it-5038456.eml";

    public $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        'it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
        'nl' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
    ];

    public $reBody = [
        'es' => ["Referencia de reserva", "Código de reserva"],
        'it' => ["Referenza prenotazione", "Numero di prenotazione"],
        'en' => ["Reservation number", "Booking reference"],
        'nl' => ["Reservatienummer", "Boekingsreferentie"],
        'fr' => ["Référence de réservation", "NOM DU PASSAGER"],
        'de' => ["Buchungsreferenz", "Buchungsnummer"],
    ];

    public $reSubject = [
        'E-TICKET CONFIRMATION',
        'Confirmación de la reserva',        // es
        'Confirmation of your booking',      // en
        'Conferma della prenotazione',       // it
        'Bevestiging van jouw boeking',      // nl
        'Confirmation de votre réservation', // fr
        'Bestätigung der Buchung',			 // de
    ];
    public $lang = 'en';
    /** @var \HttpBrowser */
    public $pdf;
    public static $dict = [ // need add word "Via" (it's in it-4245539.eml[De]; In others languages is missed)
        'es' => [
            "Referencia de reserva" => ["Referencia de reserva", "Código de reserva"],
            "Fecha de llegada"      => "Fecha de llegada",
            "Hora de llegada"       => "Hora de llegada",
        ],
        'en' => [
            'Referencia de reserva' => ['Reservation number', "Booking reference"],
            'Passager Fréquent'     => 'Frequent flyer number',
            'Número de billet'      => 'Ticket number',
            'Emitido'               => 'Issued',
            //			'Posición' => '',
            'Tarifa'                                      => 'Fare',
            'Suma total'                                  => 'Grand total',
            'Vuelo'                                       => 'Flight',
            'VUELO'                                       => 'FLIGHT',
            'Veuillez noter que les voyageurs se rendant' => 'Please note that travellers visiting',
            "Terminal"                                    => 'Terminal',
            "Fecha de llegada"                            => 'Arrival date',
            "Hora de llegada"                             => 'Arrival time',
            'Estatus'                                     => 'Status',
        ],
        'it' => [
            'Referencia de reserva' => ['Referenza prenotazione', "Numero di prenotazione"],
            //			'Passager Fréquent' => '',
            'Número de billet' => 'Numero di biglietto',
            'Emitido'          => 'Emesso il',
            'Posición'         => 'Voce',
            //			'Tarifa' => '',
            'Suma total' => 'Totale',
            'Vuelo'      => 'Volo',
            'VUELO'      => 'VOLO',
            //			'Veuillez noter que les voyageurs se rendant' => '',
            "Terminal"         => 'Terminal',
            "Fecha de llegada" => 'Data di arrivo',
            "Hora de llegada"  => 'Ora di arrivo',
            'Estatus'          => 'Confermato',
        ],
        'de' => [
            'Referencia de reserva' => ['Buchungsreferenz', 'Buchungsnummer'],
            //			'Passager Fréquent' => '',
            'Número de billet' => 'Ticketnummer',
            'Emitido'          => 'Ausgestellt',
            'Posición'         => 'Element',
            'Tarifa'           => 'Tarif',
            'Suma total'       => 'Gesamtbetrag',
            'Vuelo'            => 'Flug',
            'VUELO'            => 'FLUG',
            'Via'              => 'Via', // it-4245539.eml
            //			'Veuillez noter que les voyageurs se rendant' => '',
            "Terminal"         => 'Terminal',
            "Fecha de llegada" => 'Ankunftsdatum',
            "Hora de llegada"  => 'Ankunftszeit',
            'Estatus'          => 'Status',
        ],
        'nl' => [
            'Referencia de reserva' => ['Reservatienummer', 'Boekingsreferentie'],
            'Passager Fréquent'     => 'Frequent flyer number',
            'Número de billet'      => 'Ticketnummer',
            'Emitido'               => 'Uitgegeven op',
            'Posición'              => 'Element',
            'Tarifa'                => 'Tarief',
            'Suma total'            => 'Totaal tarief',
            'Vuelo'                 => 'Vlucht',
            'VUELO'                 => 'VLUCHT',
            //			'Veuillez noter que les voyageurs se rendant' => '',
            "Terminal"         => 'Terminal',
            "Fecha de llegada" => 'Aankomstdatum',
            "Hora de llegada"  => 'Aankomsttijd',
            'Estatus'          => 'Status',
        ],
        'fr' => [
            'Referencia de reserva' => ['Référence de réservation', 'Numéro de réservation'],
            'Passager Fréquent'     => 'Nº Passager Fréquent',
            'Número de billet'      => 'Numéro de billet',
            'Emitido'               => 'Emis le',
            'Posición'              => 'Élément',
            'Tarifa'                => 'Tarif',
            'Suma total'            => 'Montant total',
            'Vuelo'                 => 'Vol',
            'VUELO'                 => 'VOL',
            //			'Veuillez noter que les voyageurs se rendant' => '',
            "Terminal"         => 'Terminal',
            "Fecha de llegada" => 'Date d\'arrivée',
            "Hora de llegada"  => 'Heure d\'arrivée',
            'Estatus'          => 'Statut',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';
            $NBSP = chr(194) . chr(160);
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                if ($text !== null) {
                    $text = str_replace($NBSP, ' ', html_entity_decode($text));
                    if ($this->assignLang($text)) {
                        $html .= $text;
                    }
                } else {
                    continue;
                }

            }
        } else {
            return null;
        }
        $this->pdf->SetEmailBody($html);
        $this->assignLang($html);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicketConfirmation' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('e-ticket.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'brusselsairlines.com') === false) {
                return false;
            }

            foreach ($this->reBody as $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($text, $phrase) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Brussels Airline') !== false
            || stripos($from, 'brusselsairlines.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function getDate($nodeForDate)
    {
        $month = $this->monthNames['en'];
        $monthLang = $this->monthNames[$this->lang];
        $done = false;
        preg_match("#(?<day>[\d]+)\s+(?<month>.+)\s+(?<year>\d{4})\s+(?<time>.+)#", $nodeForDate, $chek);
        $res = $nodeForDate;

        for ($i = 0; $i < 12; $i++) {
            if (mb_strtolower($monthLang[$i]) == mb_strtolower(trim($chek['month']))) {
                $res = $chek['day'] . ' ' . $month[$i] . ' ' . $chek['year'] . ' ' . $chek['time'];
                $done = true;

                break;
            }
        }

        if (!$done && isset($this->monthNames[$this->lang . '2'])) {
            $monthLang = $this->monthNames[$this->lang . '2'];

            for ($i = 0; $i < 12; $i++) {
                if (mb_strtolower($monthLang[$i]) == mb_strtolower(trim($chek['month']))) {
                    $res = $chek['day'] . ' ' . $month[$i] . ' ' . $chek['year'] . ' ' . $chek['time'];

                    break;
                }
            }
        }

        return $res;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $recLoc = array_unique($this->pdf->FindNodes("//p[" . $this->contains($this->t('Referencia de reserva')) . "]/following::p[1]"));

        if (isset($recLoc[0])) {
            $it['RecordLocator'] = $recLoc[0];
        }

        $it['AccountNumbers'] = array_unique($this->pdf->FindNodes("//p[{$this->contains($this->t('Passager Fréquent'))}]/following::p[1]"));

        $it['TicketNumbers'] = array_unique($this->pdf->FindNodes("//p[contains(.,'" . $this->t('Número de billet') . "')]/following::p[1]"));

        $it['Passengers'] = array_unique($this->pdf->FindNodes("//p[1]"));
        //		$it['Passengers'] = array_unique($this->pdf->FindNodes("//p[contains(.,'Nº de pasajero frecuente')]/preceding::p[1]"));

        $nodes = array_unique($this->pdf->FindNodes("//p[contains(.,'" . $this->t('Emitido') . "')]/following::p[1]"));

        if (isset($nodes[0])) {
            $it['ReservationDate'] = strtotime(str_replace('/', '.', $nodes[0]));
        }

        // Currency
        $it['Currency'] = $this->pdf->FindSingleNode("(//p[text()='" . $this->t('Posición') . "']/following::p[1])[1]", null, true, "#[A-Z]{3}#");

        if (empty($it['Currency'])) {
            $it['Currency'] = $this->pdf->FindSingleNode("(//p[text()='" . $this->t('Tarifa') . "']/preceding::p[1])[1]", null, true, "#[A-Z]{3}#");
        }

        // TotalCharge
        $totals = array_filter($this->pdf->FindNodes("//p[text()='" . $this->t('Suma total') . "']/following::p[1]", null, "#^\s*\d[\d\,\.\s]+\s*$#"));

        if (!empty($totals)) {
            foreach ($totals as $total) {
                if (!isset($it['TotalCharge'])) {
                    $it['TotalCharge'] = $this->amount($total);
                } else {
                    $it['TotalCharge'] += $this->amount($total);
                }
            }
        }

        // BaseFare
        $fares = array_filter($this->pdf->FindNodes("//p[text()='" . $this->t('Tarifa') . "']/following::p[1]", null, "#^\s*\d[\d\,\.\s]+\s*$#"));

        if (!empty($fares)) {
            foreach ($fares as $fare) {
                if (!isset($it['BaseFare'])) {
                    $it['BaseFare'] = $this->amount($fare);
                } else {
                    $it['BaseFare'] += $this->amount($fare);
                }
            }
        }

        $flights = $this->pdf->XPath->query("//p[translate(text(),' ','')='" . $this->t('Vuelo') . "' or translate(text(),' ','')='" . $this->t('VUELO') . "']/following::p[position()=1 and not(contains(.,'-'))]");

        foreach ($flights as $root) {
            $seg = [];
            $addI = 0;

            $flight = $this->pdf->FindSingleNode(".", $root);
            if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->getNode(2, null, $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $airport = $this->getNode(3, null, $root);
                $seg['DepName'] = $m[1] . ' - ' . $airport;
                $seg['DepCode'] = $m[2];
            }
            $node = $this->getNode(5, null, $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $airport = $this->getNode(6, null, $root);
                $seg['ArrName'] = $m[1] . ' - ' . $airport;
                $seg['ArrCode'] = $m[2];
            }
            $num = $addI + 7;

            if (
                ($node = $this->getNode($num, null, $root))
                && (
                    strcasecmp($node, $this->t('Via')) === 0 // it-4245539.eml
                    || preg_match("/{$this->opt($this->t('Veuillez noter que les voyageurs se rendant'))}/", $node) // it-27355391.eml
                )
            ) {
                $addI += 3;
            }
            $num = $addI + 8;
            $dnode = $this->getNode($num, null, $root);

            $num = $addI + 10;
            $tnode = $this->getNode($num, null, $root);

            if (preg_match("#(\d+\s*\S+\s*\d+)#", $dnode, $date) && preg_match("#(\d+:\d+)#", $tnode, $time)) {
                $seg['DepDate'] = strtotime($this->getDate($date[1] . ' ' . $time[1]));
            }

            if ($this->getNode(11, "/{$this->t('Terminal')}/", $root) || $this->getNode(14, "/{$this->t('Terminal')}/", $root)) {
                $seg['DepartureTerminal'] = orval($this->getNode(12, '/\b([A-Z\d]{1,3})\b/', $root), $this->getNode(15, '/\b([A-Z\d]{1,3})\b/', $root));
                $addI += 2;
            }
            $num = $addI + 13;

            if ($this->getNode($num, null, $root) === $this->t('Fecha de llegada')) {
                $addI++;
            }

            if (stripos($this->getNode($num, null, $root), 'Heure limite d\'enregistrement') !== false) {
                $addI += 3;
            }
            $num = $addI + 13;
            $dnode = $this->getNode($num, null, $root);

            $num = $addI + 15;

            if ($this->getNode($num, null, $root) === $this->t('Hora de llegada')) {
                $addI++;
            }
            $num = $addI + 15;
            $tnode = $this->getNode($num, null, $root);

            if (preg_match("#(\d+\s*\S+\s*\d+)#", $dnode, $date) && preg_match("#(\d+:\d+)#", $tnode, $time)) {
                $seg['ArrDate'] = strtotime($this->getDate($date[1] . ' ' . $time[1]));
            }

            $num = $addI + 18;
            $node = $this->getNode($num, null, $root);

            if ($node === $this->t('Estatus')) {
                $num = $addI + 19;
                $it['Status'] = $this->getNode($num, null, $root);
                $num = $addI + 17;
                $node = $this->getNode($num, null, $root);

                if (preg_match('/(.+?)\s*\(([A-Z]{1,2})\)/', $node, $m)) {
                    // Economy (E)
                    $seg['Cabin'] = $m[1];
                    $seg['BookingClass'] = $m[2];
                } elseif (preg_match('/^\s*\(([A-Z]{1,2})\)\s*$/', $node, $m)) {
                    // (M)
                    $seg['BookingClass'] = $m[1];
                } elseif ($node) {
                    $seg['Cabin'] = $node;
                }
            } else {
                $num = $addI + 17;
                $cabin = $this->getNode($num, null, $root);

                if (preg_match('/(.+?)\s*\(([A-Z]{1,2})\)/', $cabin, $m)) {
                    // Economy (E)
                    $seg['Cabin'] = $m[1];
                    $seg['BookingClass'] = $m[2];
                } elseif (preg_match('/^\s*\(([A-Z]{1,2})\)\s*$/', $cabin, $m)) {
                    // (M)
                    $seg['BookingClass'] = $m[1];
                } elseif ($cabin) {
                    $seg['Cabin'] = $cabin;
                }

                $num = $addI + 19;

                if (preg_match('/^\s*\(([A-Z]{1,2})\)\s*$/', $this->getNode($num, null, $root), $m)) {
                    // (M)
                    $seg['BookingClass'] = $this->getNode($num, null, $root);
                }
            }
            $num = $addI + 21;
            $seg['Operator'] = $this->getNode($num, null, $root);
            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }

        $it['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $it['TripSegments'])));

        usort($it['TripSegments'], function ($el1, $el2) {
            if ($el1['DepDate'] === $el2['DepDate']) {
                return 0;
            }

            return ($el1['DepDate'] < $el2['DepDate']) ? -1 : 1;
        });

        return [$it];
    }

    private function getNode($num = 1, $re = null, $root = null)
    {
        return $this->pdf->FindSingleNode("(./following::p[{$num}])[1]", $root, true, $re);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $reBody) {
            if (
                stripos($body, $reBody['Fecha de llegada']) !== false || stripos($body, $reBody['Hora de llegada']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", trim($s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }
}
