<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3315359 extends \TAccountChecker
{
    use \PriceTools;

    public $mailFiles = "easyjet/it-10037937.eml, easyjet/it-10183542.eml, easyjet/it-11500716.eml, easyjet/it-12382877.eml, easyjet/it-12564171.eml, easyjet/it-12729329.eml, easyjet/it-12747610.eml, easyjet/it-3315359.eml, easyjet/it-3319953.eml, easyjet/it-3321304.eml, easyjet/it-33336238.eml, easyjet/it-3369158.eml, easyjet/it-3384247.eml, easyjet/it-3557332.eml, easyjet/it-3577923.eml, easyjet/it-4197629.eml, easyjet/it-4223430.eml, easyjet/it-4257524.eml, easyjet/it-4307452.eml, easyjet/it-4945101.eml, easyjet/it-4952953.eml, easyjet/it-4961449.eml, easyjet/it-5161645.eml, easyjet/it-5869737.eml, easyjet/it-5879569.eml, easyjet/it-6534789.eml, easyjet/it-6641290.eml, easyjet/it-7770608.eml, easyjet/it-8241184.eml, easyjet/it-8602948.eml, easyjet/it-8757767.eml";

    public $reFrom = 'donotreply@easyjet.com';
    public $reSubject = [
        'pt' => 'easyJet número de referência da reserva',
        'da' => 'easyJet Bookingnummer',
        'es' => 'easyJet referencia de la reserva',
        'pl' => 'easyJet numer rezerwacji',
        'fr' => 'easyJet référence de réservation',
        'de' => 'easyJet Buchungsnummer',
        'nl' => 'easyJet bevestigingsnummer',
        'ca' => 'easyJet número de localitzador',
        'it' => 'easyJet numero di prenotazione',
        'en' => 'easyJet booking reference',
        'el' => 'Κωδικός κράτησης easyJet',
        'cs' => 'easyJet číslo rezervace',
        'hu' => 'easyJet a foglalási szám',
    ];

    public $lang = '';
    public $subject = '';

    public $reBody = 'easyJet';

    public $reBody2 = [
        'pt' => 'Informações do passageiro e do voo',
        'da' => 'Passager og flyoplysninger',
        'es' => ['Información de los pasajeros', 'tienes la informac'],
        'pl' => 'Dane pasażera',
        'fr' => ['Informations détaillées sur les passagers', 'La compagnie aérienne easyJet ne délivre pas de billets', 'LES INFORMATIONS DÉTAILLÉES SUR VOTRE VOL'],
        'de' => 'Angaben zu Passagier',
        'nl' => 'Passagier en vluchtgegevens',
        'ca' => 'Informació de pagament',
        'it' => ['Informazioni sui passeggeri e sul volo', 'easyJet è una compagnia aerea che non emette biglietti'],
        'en' => 'Flight details',
        'el' => 'Λεπτομέρειες επιβάτη & πτήσης',
        'cs' => 'Údaje cestujících',
        'hu' => 'Utas- és járatinformációk',
    ];
    public static $dictionary = [
        'pt' => [
            'thank you for your booking' => ['obrigado pela reserva', 'a tua reserva', 'aqui estão os detalhes da tua reserva'],
            'Seat'                       => 'Lugar',
            'Payment of'                 => ['Pagamento de', 'Pagamento a easyJet no valor de'],
            'by'                         => ['por', 'através'],
            'Refund of'                  => 'Reembolso de',
            'toP'                        => 'para',
            'Check-in'                   => 'O check-in',
            'to'                         => 'a',
            'Departs:'                   => 'Partidas:',
            'Arrives:'                   => 'Chegadas:',
            //			'From' => '',
            'cancelled'         => 'foi cancelada',
            'booking reference' => 'número de referência da reserva',
            //            'Cancelled' => '',
        ],
        'da' => [
            'thank you for your booking' => ['tak for din booking', 'her er detaljerne for booking'],
            'Seat'                       => 'Sæde',
            'Payment of'                 => ['Betaling på', 'Betaling til easyJet på'],
            'by'                         => ['af', 'med'],
            //			'Refund of' => '',
            //			'toP' => '',
            'Check-in' => 'Check-in',
            'to'       => 'til',
            'Departs:' => 'Afgår:',
            'Arrives:' => 'Ankommer:',
            //			'From' => '',
            //			'cancelled' => '',
            'booking reference' => 'Bookingnummer',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'es' => [
            'thank you for your booking' => ['gracias por realizar la reserva', ' de la reserva', 'aquí están los detalles de tu reserva'],
            'Seat'                       => 'Asiento',
            'Payment of'                 => ['Pago de', 'Pago para easyJet de'],
            'by'                         => 'mediante',
            'Refund of'                  => 'Reembolso de',
            'toP'                        => 'a',
            'Check-in'                   => 'Llegada',
            'to'                         => 'a',
            'Departs:'                   => 'Salida:',
            'Arrives:'                   => 'Llegada:',
            //			'From' => '',
            'cancelled'         => 'se ha cancelado',
            'booking reference' => 'referencia de la reserva',
            'Cancelled'         => 'Cancelado',
            //'your booking' => '',
        ],
        'pl' => [
            'thank you for your booking' => ['dziękujemy za rezerwację', 'oto szczegóły rezerwacji'],
            'Seat'                       => 'Miejsce',
            'Payment of'                 => 'Płatność w kwocie:',
            'by'                         => 'płatnik',
            'Refund of'                  => 'Se está procesando el reembolso de',
            'toP'                        => 'en',
            'Check-in'                   => 'Przyjazd',
            'to'                         => 'do',
            'Departs:'                   => 'Odlot:',
            'Arrives:'                   => 'Przylot:',
            //			'From' => '',
            //			'cancelled' => '',
            'booking reference' => 'numer rezerwacji',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'fr' => [
            'thank you for your booking' => 'votre réservation',
            'Seat'                       => 'Siège',
            'Payment of'                 => ['Paiement de', 'Paiement à easyJet de'],
            'by'                         => ['avant', 'par'],
            'Refund of'                  => 'Remboursement de',
            'toP'                        => 'sur',
            //			'Check-in' => '',
            'to'       => 'à',
            'Departs:' => 'Départ',
            'Arrives:' => 'Arrivée',
            //			'From' => '',
            'cancelled'         => 'bien été annulée',
            'booking reference' => 'référence de réservation',
            //            'Cancelled' => '',
            // Car
            'Payment of_Car'                 => ['Paiement à CarTrawler de'],
            //			'Location de voiture' => '',
            //			'Numéro de réservation' => '',
            //			'Type de voiture' => '',
            //			'ou équivalent' => '',
            //			'Conducteur' => '',
            //			'Récupérer' => '',
            //			'Rendre' => '',
            'your booking' => 'Votre réservation',
        ],
        'de' => [
            'thank you for your booking' => ['danke für Ihre Buchung', 'Buchung mit der Nummer', 'hier sind deine Buchungsinformationen für Buchung', 'hier sind die Detailinformationen zu Ihrer Buchung'],
            'Seat'                       => 'Sitzplatz',
            'Payment of'                 => ['Zahlung von', 'Zahlung an easyJet in Höhe von'],
            'by'                         => ['durch', 'mit der', 'über'],
            'Refund of'                  => 'Erstattung von',
            'toP'                        => 'an',
            'Check-in'                   => 'Check-In',
            'to'                         => 'nach',
            'Departs:'                   => 'Abgehende Flüge:',
            'Arrives:'                   => 'Ankommende Flüge:',
            //			'From' => '',
            'cancelled'         => 'wurde storniert',
            'booking reference' => 'Buchungsnummer',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'nl' => [
            'thank you for your booking' => ['hartelijk dank voor uw boeking', 'hier is het overzicht van boeking', 'hier zijn de gegevens voor je boeking'],
            'Seat'                       => 'Stoel',
            'Payment of'                 => ['Betaling van', 'Betaling aan easyJet van', 'Payment of'],
            'by'                         => ['door', 'via', 'by'],
            //			'Refund of' => '',
            //			'toP' => '',
            'Check-in' => 'Inchecken',
            'to'       => 'naar',
            'Departs:' => 'Vertrektijd:',
            'Arrives:' => 'Aankomsttijd:',
            //			'From' => '',
            //			'cancelled' => '',
            'booking reference' => 'bevestigingsnummer',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'ca' => [
            'thank you for your booking' => ['gràcies per la teva reserva', 'aquí tens els detalls per a la reserva amb referència'],
            'Seat'                       => 'Seient',
            'Payment of'                 => 'Pagament de',
            'by'                         => 'per',
            //			'Refund of' => '',
            //			'toP' => '',
            //			'Check-in' => '',
            'to'       => 'a',
            'Departs:' => 'Surt:',
            'Arrives:' => 'Arriba:',
            'Check-in' => 'Entrada',
            //			'From' => '',
            //			'cancelled' => '',
            'booking reference' => 'número de localitzador',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'it' => [
            'thank you for your booking' => ['la tua prenotazione', 'ecco i dettagli relativi alla prenotazione'],
            'Seat'                       => 'Posto',
            'Payment of'                 => ['Pagamento di', 'Pagamento a easyJet di'],
            'by'                         => ['da', 'con'],
            'Refund of'                  => 'Il rimborso di',
            'toP'                        => 'su',
            'Check-in'                   => 'Check-in',
            'to'                         => 'a',
            'Departs:'                   => 'Partenza:',
            'Arrives:'                   => 'Arrivo:',
            'From'                       => 'Da',
            'cancelled'                  => 'è stata annullata',
            'booking reference'          => 'numero di prenotazione',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'el' => [
            'thank you for your booking' => ['σας ευχαριστούμε για την κράτηση', 'ακολουθούν τα στοιχεία της κράτησης'],
            'Seat'                       => 'Θέση',
            'Payment of'                 => 'Πληρωμή ποσού',
            'by'                         => 'από',
            'Refund of'                  => 'Επιστροφή',
            'toP'                        => 'προς',
            //			'Check-in' => '',
            'to'       => ['έως', 'to'],
            'Departs:' => ['Αναχωρήσεις:', 'Departures'],
            'Arrives:' => ['Αφίξεις:', 'Arrivals'],
            //			'From' => '',
            'cancelled'         => 'έχει ακυρωθεί',
            'booking reference' => ['Κωδικός κράτησης'],
            //            'Cancelled' => '',
            'your booking' => 'RESERVATION',
        ],
        'cs' => [
            'thank you for your booking' => ['děkujeme za rezervaci', 'zde jsou rezervační údaje'],
            'Seat'                       => 'Sedadlo',
            'Payment of'                 => 'Platba',
            'by'                         => 'z',
            //			'Refund of' => '',
            //			'toP' => '',
            //			'Check-in' => '',
            'to'       => 'do',
            'Departs:' => 'Odlet:',
            'Arrives:' => 'Přílet:',
            //			'From' => '',
            //			'cancelled' => '',
            'booking reference' => 'číslo rezervace',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'hu' => [
            'thank you for your booking' => ['számú foglalását', 'itt olvashatók'],
            'Seat'                       => 'Ülőhely',
            'Payment of'                 => '', // not error
            'by'                         => 'összegű kifizetés',
            //			'Refund of' => '',
            //			'toP' => '',
            //			'Check-in' => '',
            'to'       => '-',
            'Departs:' => 'Indul:',
            'Arrives:' => 'Érkezik:',
            //			'From' => '',
            //			'cancelled' => '',
            'Terminal'          => ['Terminal', 'Terminál'],
            'booking reference' => 'foglalási szám',
            //            'Cancelled' => '',
            //'your booking' => '',
        ],
        'en' => [
            'thank you for your booking' => ['thank you for your booking', 'here are the details for booking', 'here are the details for your booking', 'here aarethe details for your booking'],
            //            'Seat' => '',
            'Payment of' => ['Payment of', 'Payment to easyJet of'],
            //            'by' => '',
            //			'Refund of' => '',
            'toP' => 'to',
            //			'Check-in' => '',
            //            'to' => '',
            //            'Departs:' => '',
            //            'Arrives:' => '',
            //			'From' => '',
            'cancelled' => 'has been cancelled',
            //            'Terminal' => '',
            //            'booking reference' => '',
            //            'Cancelled' => '', // in flight block
            //'your booking' => '',
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getDate());
        $this->http->SetEmailBody('<?xml version="1.0" encoding="UTF-8"?>' . str_replace('windows-1252', '', html_entity_decode($parser->getHTMLBody())));

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query('//node()[' . $this->contains($re) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseFlight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query('//node()[' . $this->contains($re) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseFlight(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->flight();

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:][…]]*',
        ];

        // RecordLocator
        $rl = $this->http->FindSingleNode("(//*[name() = 'text()' or name() = 'span'][(" . $this->contains($this->t('thank you for your booking')) . ") and not(./ancestor::title)])[1]/ancestor::td[1]", null, true, '/([A-Z\d]{5,7})[^,;]*$/');

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("(//text()[(" . $this->contains($this->t('cancelled')) . ") and not(./ancestor::title)])[1]/ancestor::td[1]", null, true, '/([A-Z\d]{5,7})[^,;]*$/');
        }

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("(//text()[(" . $this->contains($this->t('thank you for your booking')) . ") and not(./ancestor::title)])[2]/ancestor::td[1]", null, true, '/([A-Z\d]{5,7})[^,;]*$/');
        }

        if (empty($rl)) {
            $rls = array_values(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t('thank you for your booking')) . "]", null, '/.+(?: |\()([A-Z\d]{5,7})[^,;]*$/'))));

            if (count($rls) === 1) {
                $rl = $rls[0];
            }
        }

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your booking'))}]", null, true, "/{$this->opt($this->t('your booking'))}[ ]+([A-Z\d]{5,9})/");
        }

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your booking'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('your booking'))}[ ]+([A-Z\d]{5,9})/");
        }

        if (empty($rl)) {
            $rl = $this->re("/{$this->opt($this->t('booking reference'))}\s*\:?\s*([A-Z\d]{5,})/", $this->subject);
        }

        if (empty($rl) && $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'has been cancelled')])[1]")) {
            $f->general()->noConfirmation();
        } else {
            $f->general()->confirmation($rl, 'booking reference');
        }
        // Status
        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(.),'" . $this->t('cancelled') . "')])[1]"))) {
            $f->general()->status("cancelled");
            $f->general()->cancelled(true);
        }

        // TotalCharge
        $total = array_sum(array_map([$this, 'amount'], $this->http->FindNodes("//text()[" . $this->contains($this->t('Payment of')) . " and " . $this->contains($this->t('by')) . "]", null, '/' . $this->preg_implode($this->t('Payment of')) . '\s*(.*?)[;\s]+' . $this->preg_implode($this->t('by')) . '/')));
        $total -= array_sum(array_map([$this, 'amount'], $this->http->FindNodes("//text()[" . $this->contains($this->t('Refund of')) . " and " . $this->contains($this->t('toP')) . "]", null, '/' . $this->preg_implode($this->t('Refund of')) . '\s*(.*?)[;\s]+' . $this->preg_implode($this->t('toP')) . '/')));

        // Currency
        $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Payment of')) . " and " . $this->contains($this->t('by')) . "])[1]", null, true, '/' . $this->preg_implode($this->t('Payment of')) . '\s+(.*?)[;\s]+' . $this->preg_implode($this->t('by')) . '/'));

        if (!empty($total) || !empty($currency)) {
            $f->price()
                ->total($total)
                ->currency($currency, true, true);
        }

        $passCond = "[not(contains(., ' x '))][not(@style[contains(.,'font-weight: bold')]) and not(@style[contains(.,'font-weight:bold')])]";
        $passengers = $this->http->FindNodes("(//*[contains(./text(),'" . $this->t('Seat') . " ')]/ancestor::td[1]/preceding-sibling::td[normalize-space(.)]{$passCond}[1]"
            . "| //*[normalize-space(text())='" . $this->t('Seat') . "']/ancestor-or-self::td[1]/preceding-sibling::td[normalize-space(.)]{$passCond}[1])/descendant::*[normalize-space()][1]", null, "#^\s*[^\s\d]+\s+(.+)#");

        if (!$passengers || count($passengers) < 1) {
            $passengers = [];
        }

        if (empty(array_filter($passengers))) {
            $passengers = $this->http->FindNodes("(//*[contains(./text(),'" . $this->t('Seat') . " ')]/ancestor::td[1]/preceding-sibling::td[normalize-space(.)]{$passCond}[1]"
                . "| //*[normalize-space(text())='" . $this->t('Seat') . "']/ancestor-or-self::td[1]/preceding-sibling::td[normalize-space(.)]{$passCond}[1])");
        }

        if (empty(array_filter($passengers))) {
            $passengers = $this->http->FindNodes("(//*[contains(./text(),'" . $this->t('Seat') . ":')]/ancestor::td[1]/preceding::td[normalize-space(.)]{$passCond}[1] | //*[normalize-space(text())='" . $this->t('Seat') . "']/ancestor-or-self::td[1]/preceding-sibling::td[normalize-space(.)]{$passCond}[1])");
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//table[(normalize-space(.)='PASSAGER(S)' or normalize-space(.)='Passager(s)') and not(.//table)]/following-sibling::table[normalize-space(.)][1]/descendant::td[table][last()]/table[string-length(normalize-space(.))>2][not(.//a)]");
        }

        $xpath = '//img[contains(@src,"//www.easyjet.com/ejcms/cache/medialibrary/Images/Confirmation%20email/flightdetailsplane") or ../following-sibling::td[./hr]]/ancestor::table[2] | //tr[count(./td)=3 and not(.//tr) and ./td[3]//hr]/ancestor::table[2]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = '//img[((@alt="Image removed by sender. flight" or @alt="flight") and @width="19" and @height="20") or contains(@src, "airplane")]/ancestor::table[2]';
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length > 0 && $this->http->FindSingleNode('./tbody[normalize-space(.)]', $segments->item(0))) {
            $nodes = [];

            foreach ($segments as $node) {
                $n2 = $this->http->XPath->query('./tbody[normalize-space(.)]', $node);

                if ($n2->length > 0) {
                    $nodes[] = $n2->item(0);
                }
            }
            $segments = $nodes;
        }

        if (is_object($segments) && 0 === $segments->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        if (is_object($segments) && $segments->length > 0 && !empty($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Cancelled')) . ']')) && $f->getCancelled() == false) {
            $cancelled = 0;

            foreach ($segments as $node) {
                if (!empty($this->http->FindSingleNode('.//text()[' . $this->eq($this->t('Cancelled')) . '][1]', $node))) {
                    $cancelled++;
                }
            }

            if ($segments->length === $cancelled) {
                $f->general()->status("cancelled");
                $f->general()->cancelled(true);
            } else {
                $hasCancelledFlights = true;
            }
        }

        foreach ($segments as $root) {
            if (!empty($hasCancelledFlights) && !empty($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Cancelled')) . ']'))) {
                continue;
            }

            $s = $f->addSegment();

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./descendant::tr[normalize-space(.)][position()>1][normalize-space(.)][1]', $root);

            if (preg_match('/^([A-Z]{2,3})(\d+)$/', $flight, $matches)) {
                // ‘EZY’ are operated by easyJet UK Limited, ‘EJU’ are operated by easyJet Europe Airline GmbH and ‘EZS’ are operated by easyJet Switzerland SA
                if (in_array($matches[1], ['EZY', 'EJU', 'EZS'])) {
//                    $matches[1] = 'EC'; fs ??
                    $matches[1] = 'U2';
                }
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            } elseif (preg_match('/(\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name('U2')
                    ->number($matches[1]);
            }

            if (empty($s->getFlightNumber()) && $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'has been cancelled')])[1]")) {
                $s->airline()->noNumber();
            }

            // DepName
            // DepartureTerminal
            $from = $this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]', $root, true, "/(.*?)\s+{$this->opt($this->t('to'))}\s+/");

            if (preg_match('/^(?:' . $this->t('From') . '\s*)?(.+)\s+\(((?:T|[^)(]*' . $this->preg_implode($this->t("Terminal")) . ')\s*[^)(]*)\)$/i', $from, $matches)) {
                $terminal = preg_replace(["#" . $this->preg_implode($this->t("Terminal")) . '#', '#^T#'], '', $matches[2]);
                $s->departure()
                    ->name($matches[1])
                    ->terminal($terminal);
            } elseif (preg_match('/^(?:' . $this->t('From') . '\s*)?(.+)$/i', $from, $matches)) {
                $s->departure()->name(preg_replace("/\s*\([^)(]*{$this->preg_implode($this->t("check-in"))}[^)(]*\)/", '', $matches[1]));
            }

            if (preg_match('/^[A-Z]{3}$/', $s->getDepName())) {
                $s->departure()->code($s->getDepName());
            }

            $dt = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Departs:'))}]/following::text()[string-length(normalize-space(.))>10][1]", $root);

            if (stripos($dt, $this->t('Desk opens:')) !== false) {
                $dt = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Departs:'))}]/following::p[1]", $root, true, "/^\w+\s*(\d+\s*\w+\s*\d{4}\s*\d+\:\d+)$/");
            }

            $s->departure()
                ->date($this->normalizeDate($dt))
                ->strict();

            $dt = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Arrives:'))}]/following::text()[string-length(normalize-space(.))>10][1]", $root);

            if (stripos($dt, $this->t('Desk opens:')) !== false) {
                $dt = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Arrives:'))}]/following::p[1]", $root, true, "/^\w+\s*(\d+\s*\w+\s*\d{4}\s*\d+\:\d+)$/");
            }
            $s->arrival()
                ->date($this->normalizeDate($dt))
                ->strict();

            // ArrName
            // ArrivalTerminal
            $to = $this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]', $root, true, "/\s+{$this->opt($this->t('to'))}\s+(.+?)(?:\s*{$this->preg_implode($this->t('Cancelled'))}\s*)?$/");

            if (preg_match('/^(.+)\s+\(((?:T|[^)(]*' . $this->preg_implode($this->t("Terminal")) . ')\s*[^)(]*)\)$/i', $to, $matches)) {
                $terminal = preg_replace(["#" . $this->preg_implode($this->t("Terminal")) . '#', '#^T#'], '', $matches[2]);
                $s->arrival()
                    ->name($matches[1])
                    ->terminal($terminal);
            } else {
                $s->arrival()->name(preg_replace("/\s*\([^)(]*{$this->preg_implode($this->t("check-in"))}[^)(]*\)/", '', $to));
            }

            if (preg_match('/^[A-Z]{3}$/', $s->getArrName())) {
                $s->arrival()->code($s->getArrName());
            }

            // Seats
            $seats = $this->http->FindNodes('./ancestor-or-self::table[1]/following-sibling::table[1]//tr/td[2]', $root, '/(?:^|\s)(\d{1,2}[A-z])(?:\s+|$)/');

            if (empty(array_filter($seats))) {
                $seats = $this->http->FindNodes('./ancestor-or-self::table[1]/following::table[1]//tr/td[2]', $root, '/(?:^|\s)(\d{1,2}[A-z])(?:\s+|$)/');
            }
            $seatValues = array_unique(array_values(array_filter($seats)));

            foreach ($seatValues as $seat) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/ancestor::tr[1]/preceding::text()[normalize-space()][1]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/");

                if (!empty($pax)) {
                    $s->extra()
                        ->seat($seat, false, false, $pax);
                } else {
                    $s->extra()
                        ->seat($seat);
                }
            }

            // DepCode
            if (empty($s->getDepCode()) && !empty($s->getDepName())) {
                $s->departure()->noCode();
            }
            // ArrCode
            if (empty($s->getArrCode()) && !empty($s->getArrName())) {
                $s->arrival()->noCode();
            }

            if (!$passengers || count($passengers) < 1) {
                $passengers = array_merge($passengers, $this->http->FindNodes("ancestor-or-self::table[1]/following-sibling::table[normalize-space()][1]/descendant::tr[count(*[normalize-space() and not(.//tr)])=1 or (count(*[normalize-space() and not(.//tr)])=2 and *[normalize-space()][2][{$this->starts($this->t('Seat'))}])]/*[normalize-space()][1]", $root, "/^\s*([[:alpha:] \.\\/]*{$patterns['travellerName']})(?:\s+[[:alpha:]]+ \d [[:alpha:]]+)?\s*$/u"));
            }
        }

        // Passengers
        $passengers = preg_replace("#^\s*" . $this->preg_implode($this->t('title')) . "[.\s]*#", '', $passengers);
        $passengers = array_filter($passengers);

        if (empty($passengers)
            && ($tName = $this->http->FindSingleNode("//text()[contains(normalize-space(),'here are the details for booking')][1]", null, true, "/^({$patterns['travellerName']})\s*, here are the details for booking/"))
        ) {
            $passengers[] = $tName;
        }

        // JULIANA MENEGHELLI MORENO BEVILACQUA plus 1 infant
        $f->general()->travellers(
            preg_replace(['/^(?:Frøken|Srta|Pani|child|Κος|Pan|Mme|Mlle|Enfant|dhr|mw|Criança|Sig\.ra|Sig\.na|Sig|Sra|Bambino\\/a|Sr|Hr|Fr|Mrs|Mr|Miss|Ms|M)[.\sª]+(.{2,})$/iu',
                '/^\s*(.+) (?:plus|più) \d \D+\s*$/ui', ],
                '$1', array_unique($passengers)), true);

        /* CARS */
        $xpath = '//text()[' . $this->eq($this->t('Récupérer')) . ']/ancestor::*[' . $this->starts($this->t('Location de voiture')) . '][1]';
        $conf = $this->http->FindNodes('.//text()[' . $this->starts($this->t('Numéro de réservation')) . ']', null, "#:\s*([A-Z\d]{5,})\s*$#");
        $segments = [];

        if (0 < count($conf)) {
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $root) {
            $r = $email->add()->rental();

            // General
            $conf = $this->http->FindSingleNode('.//text()[' . $this->starts($this->t('Numéro de réservation')) . ']', $root, true, "#:\s*([A-Z\d]{5,})\s*$#");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode('.//text()[' . $this->starts($this->t('Numéro de réservation')) . ']/following::text()[normalize-space()][1]',
                    $root, true, "#^\s*([A-Z\d]{5,})\s*$#");
            }

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Numéro de réservation'))}]/following::text()[normalize-space()][1]",
                    $root, true, "#^\s*([A-Z\d]{5,})\s*$#");
            }
            $r->general()
                ->confirmation($conf);

            $traveller = $this->http->FindSingleNode('.// text()[' . $this->eq($this->t('Conducteur')) . ']/ancestor::td[1]/following::td[1]', $root);

            if (!empty($traveller)) {
                $r->general()
                    ->traveller($traveller);
            }

            //Pick Up
            $r->pickup()
                ->location($this->http->FindSingleNode('.//text()[' . $this->eq($this->t('Récupérer')) . ']/ancestor::td[1]/following::td[normalize-space() and not(.//td)][1]', $root))
                ->date2($this->normalizeDateWithYear($this->http->FindSingleNode('.//text()[' . $this->eq($this->t('Récupérer')) . ']/ancestor::td[1]/following::td[normalize-space() and not(.//td)][2]', $root)));

            // Drop Off
            $r->dropoff()
                ->location($this->http->FindSingleNode('.//text()[' . $this->eq($this->t('Rendre')) . ']/ancestor::td[1]/following::td[normalize-space() and not(.//td)][1]', $root))
                ->date2($this->normalizeDateWithYear($this->http->FindSingleNode('.//text()[' . $this->eq($this->t('Rendre')) . ']/ancestor::td[1]/following::td[normalize-space() and not(.//td)][2]', $root)));

            // Car
            $type = $this->http->FindSingleNode('.//text()[' . $this->eq($this->t('Type de voiture')) . ']/ancestor::td[1]/following::td[1]', $root);

            if (empty($type)) {
                $type = $this->http->FindSingleNode('.//td[not(.//td)][' . $this->eq($this->t('ou équivalent')) . ']/preceding::td[1]', $root);
            }
            $r->car()
                ->type($type);

            $companyImageName = [
                'sixt' => ['/sixt.'],
            ];

            foreach ($companyImageName as $code => $names) {
                if ($this->http->XPath->query("//img[" . $this->contains($names, '@src') . "]", $root)->length === 1) {
                    $r->program()->code($code);

                    break;
                }
            }

            // Payment of_Car
            // TotalCharge
            $total = array_sum(array_map([$this, 'amount'], $this->http->FindNodes("//text()[" . $this->contains($this->t('Payment of_Car')) . " and " . $this->contains($this->t('by')) . "]", null, '/' . $this->preg_implode($this->t('Payment of_Car')) . '\s*(.*?)[;\s]+' . $this->preg_implode($this->t('by')) . '/')));

            // Currency
            $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Payment of_Car')) . " and " . $this->contains($this->t('by')) . "])[1]", null, true, '/' . $this->preg_implode($this->t('Payment of_Car')) . '\s+(.*?)[;\s]+' . $this->preg_implode($this->t('by')) . '/'));

            if (!empty($total) || !empty($currency)) {
                $r->price()
                    ->total($total)
                    ->currency($currency, true, true);
            }
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(?string $str)
    {
        if (preg_match('/\b\d{4}\b/', $str)) {
            return strtotime($this->normalizeDateWithYear($str));
        }

        $year = $this->http->FindSingleNode("//*[{$this->contains($this->t('1997-'), 'text()')}]", null, false, '/1997-(\d{4})/');

        if (empty($year)) {
            // TODO: bug email it-8757767.eml
            // debit on 05/08/2017
            $year = $this->http->FindSingleNode("//*[{$this->contains($this->t(' debit on '), 'text()')}]", null, false, '#\d+/\d+/(\d{4})#');
        }
//        $this->logger->debug("Year: {$year}");
//        $this->logger->debug("Str: {$str}");

        $regs = [
            // Sat 29 Feb 07:20
            "#^(\w+)[.]?\s+(\d+)\s+(\w+)[.]?\s+(\d+:\d+)$#u",
        ];

        foreach ($regs as $reg) {
            if (preg_match($reg, $str, $m)) {
                $dayNumber = WeekTranslate::number1($m[1], $this->lang);
                $month = 'en' !== $this->lang ? MonthTranslate::translate($m[3], $this->lang) : $m[3];

                if (empty($month) && !empty((int) $m[3])) {
                    //when month is number, example for "cs": so 21 4 05:00
                    $month = date('F', mktime(0, 0, 0, (int) $m[3]));
                }
                $date = strtotime($m[2] . ' ' . $month, strtotime('01/01/' . ($year + 1)));
                $str = EmailDateHelper::parseDateUsingWeekDay($date, $dayNumber);
                $str = strtotime($m[4], $str);

                break;
            }
        }

        return $str;
    }

    private function normalizeDateWithYear(?string $str): string
    {
//        $this->logger->debug(__FUNCTION__);
        $in = [
            // sam. 08 août 2020 13:40
            "#^\w+[.]?\s+(\d+)\s+(\w+)[.]?\s+(\d{4})\s+(\d+:\d+)$#u",
            // 6 août 2018 16:30    |    Tue 01 Sep 2020 17:15
            "/^(?:[[:alpha:]]+[,\s]+)?(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})\s+(\d{1,2}[:]+\d{1,2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/u",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map('preg_quote', $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
