<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// PDF parse in BookingConfirmationPDF
class TicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "aegean/it-118446166.eml, aegean/it-118465830.eml, aegean/it-118467913.eml, aegean/it-118908480.eml, aegean/it-119837785.eml, aegean/it-120026062.eml, aegean/it-120026063.eml, aegean/it-123234204.eml, aegean/it-124904352.eml, aegean/it-127160744.eml, aegean/it-137531950.eml";
    public $subjects = [
        '- E-ticket Confirmation',
        '- Award ticket confirmation',
        // ro
        '- Confirmare bilet electronic',
    ];

    public $lang = '';
    public $detectLang = [
        'fr' => ['Vol Aller', 'Code de réservation'],
        'el' => ['Αναχώρηση', 'Κωδικός Κράτησης'],
        'it' => ['Volo Di Andata', 'Passeggeri'],
        'de' => ['Hinflug'],
        'es' => ['este mensaje'],
        'ru' => ['Рейстуда'],
        'ro' => ['Numărul de referință al rezervării'],
        'en' => ['Outbound', 'Bound'], // Bound - the last
    ];

    public $date;

    public static $dictionary = [
        "en" => [
            'Outbound' => ['Outbound', 'Bound 1'],
            'Inbound'  => ['Inbound', 'Bound 2'],
            'feeNames' => ['Airport charges'],
        ],
        "es" => [
            'online booking confirmation' => 'online booking confirmation',
            'Booking Reference'           => 'Código de reserva',
            'Outbound'                    => 'Vuelo De Ida',
            'Inbound'                     => 'Vuelo de vuelta',
            'Operated by'                 => ['operado por', 'Operado por'],
            'Aircraft:'                   => 'Aeronave:',
            'Ticket number'               => 'Billete electrónico',
            'TOTAL'                       => 'TOTAL',
            'Flight'                      => 'Vuelo',
            'Seats'                       => 'Asientos',
            // 'Extra baggage' => '',
            // 'INSURANCE' => '',
            // 'Miles' => '',
            'feeNames'                             => ['Tasas aeroportuarias'],
            'FF number'                            => 'Número de Viajero Frecuente',
            'Miles+Bonus award miles'              => 'Miles+Bonus millas de canje',
            'Please find below the selected seats' => 'A continuación encontrará los asientos seleccionados',
            //             'Cabin Class' => '',
            'Booking class'                        => 'Clase de reserva',
            'terminal'                             => 'terminal',
            'to'                                   => 'Hacia',
        ],
        "de" => [ // it-118908480.eml
            'online booking confirmation' => 'online booking confirmation',
            'Booking Reference'           => 'Buchungscode',
            'Outbound'                    => 'Hinflug',
            'Inbound'                     => 'Rückflug',
            'Operated by'                 => ['durchgeführt von', 'Durchgeführt von'],
            'Aircraft:'                   => 'Flugzeug:',
            'Ticket number'               => 'Ticketnummer',
            'TOTAL'                       => 'Gesamt',
            'Flight'                      => 'Flug',
            'Seats'                       => 'Sitzplätze',
            // 'Extra baggage' => '',
            // 'INSURANCE' => '',
            // 'Miles' => '',
            // 'feeNames' => [''],
            'FF number' => 'Vielfliegernummer',
            //'Miles+Bonus award miles' => '',
            'Please find below the selected seats' => 'Nachstehend sehen Sie die gewählten Sitzplätze',
            // 'Cabin Class' => '',
            'Booking class'                        => 'Buchungsklasse',
            'terminal'                             => 'Terminal',
            'to'                                   => 'nach',
        ],
        "el" => [
            'online booking confirmation' => 'online booking confirmation',
            'Booking Reference'           => 'Κωδικός Κράτησης',
            'Outbound'                    => ['Αναχώρηση', 'Bound 1'],
            'Inbound'                     => ['Επιστροφή', 'Bound 2'],
            'Operated by'                 => ['Πτήση με', 'πτήση με'],
            'Aircraft:'                   => 'Αεροσκάφος:',
            'Ticket number'               => 'Αριθμός Εισιτηρίου',
            'TOTAL'                       => 'Σύνολο',
            'Flight'                      => 'Πτήση',
            'Seats'                       => 'Θέσεις',
            // 'Extra baggage' => '',
            // 'INSURANCE' => '',
            'Miles'                                => 'Μίλια',
            'feeNames'                             => ['Χρεώσεις αεροδρομίου'],
            'FF number'                            => 'Αριθμός Τακτικού Επιβάτη',
            'Miles+Bonus award miles'              => 'Miles+Bonus μίλια εξαργύρωσης',
            'Please find below the selected seats' => 'Παρακάτω εμφανίζονται οι επιλεγμένες θέσεις',
            // 'Cabin Class' => '',
            'Booking class'                        => 'Ναύλος',
            //'terminal' => '',
            'to' => 'προς',
        ],
        "fr" => [
            'online booking confirmation' => 'online booking confirmation',
            'Booking Reference'           => 'Code de réservation',
            'Outbound'                    => ['Vol Aller', 'Bound 1'],
            'Inbound'                     => ['Bound 2'],
            'Operated by'                 => ['opéré par', 'Opéré par'],
            'Aircraft:'                   => 'Appareil:',
            'Ticket number'               => 'Numero de billet',
            'TOTAL'                       => 'TOTAL',
            // 'Flight' => '',
            'Seats' => 'Places',
            // 'Extra baggage' => '',
            // 'INSURANCE' => '',
            // 'Miles' => '',
            // 'feeNames' => [''],
            'FF number'                            => 'Numéro de fidélité',
            'Miles+Bonus award miles'              => 'Miles+Bonus échanger des miles',
            'Please find below the selected seats' => '',
            // 'Cabin Class'                          => '',
            'Booking class'                        => 'Classe de réservation',
            'terminal'                             => 'terminal',
            'to'                                   => 'à',
        ],
        "it" => [
            'online booking confirmation' => 'online booking confirmation',
            'Booking Reference'           => 'Codice prenotazione',
            'Outbound'                    => ['Volo Di Andata', 'Bound 1'],
            'Inbound'                     => 'Bound 2',
            'Operated by'                 => ['Operato da'],
            'Aircraft:'                   => 'Aircraft:',
            'Ticket number'               => 'Numero del biglietto',
            'TOTAL'                       => 'TOTALE',
            'Flight'                      => 'Volo',
            'Seats'                       => 'Asientos',
            // 'Extra baggage' => '',
            // 'INSURANCE' => '',
            // 'Miles' => '',
            'feeNames'                             => ['Tasse aeroportuali'],
            'FF number'                            => 'Numero FF',
            'Miles+Bonus award miles'              => 'Miles+Bonus miglia convertibili',
            'Please find below the selected seats' => 'A continuación encontrará los asientos seleccionados',
            'Cabin Class'                          => 'Cabin Class',
            'Booking class'                        => 'Classe di prenotazione',
            //'terminal'                             => 'terminal',
            //'to'                                   => 'Hacia',
        ],
        "ru" => [
            'online booking confirmation' => 'online booking confirmation',
            'Booking Reference'           => 'Номер бронирования',
            'Outbound'                    => 'Рейстуда',
            //            'Inbound'                     => '',
            'Operated by'                 => ['Компанией-оператором является'],
            'Aircraft:'                   => 'Воздуш.судно:',
            'Ticket number'               => 'Номер билета',
            'TOTAL'                       => 'ИТОГО',
            'Flight'                      => 'Рейс',
            'Seats'                       => 'Места',
            // 'Extra baggage' => '',
            // 'INSURANCE' => '',
            // 'Miles' => '',
            // 'feeNames' => [''],
            'FF number'                            => 'Номер FF (часто летающего пассажира)',
            //            'Miles+Bonus award miles'              => 'Miles+Bonus miglia convertibili',
            'Please find below the selected seats' => 'A continuación encontrará los asientos seleccionados',
            'Cabin Class'                          => 'Cabin Class',
            'Booking class'                        => 'Cabin Class',
            'terminal'                             => 'терминал',
            'to'                                   => 'в',
        ],
        "ro" => [
            'online booking confirmation' => 'confirmare rezervare online',
            'Booking Reference'           => 'Numărul de referință al rezervării',
            'Outbound'                    => 'Plecare',
            //            'Inbound'                     => '',
            'Operated by'                 => ['Operat de'],
            'Aircraft:'                   => 'Aeronavă:',
            'Ticket number'               => 'Număr de bilet',
            'TOTAL'                       => 'TOTAL',
            'Flight'                      => 'Zbor',
            //            'Seats' => '',
            // 'Extra baggage' => '',
            // 'INSURANCE' => '',
            // 'Miles' => '',
            'feeNames'                             => ['Taxe de aeroport'],
            'FF number'                            => 'Numărul FF',
            'Miles+Bonus award miles'              => 'Mile premiu prin programul Miles+Bonus',
            //            'Please find below the selected seats' => 'A continuación encontrará los asientos seleccionados',
            'Cabin Class'                          => 'Clasa cabinei',
            'Booking class'                        => 'Clasa rezervării',
            //            'terminal'                             => '',
            //            'to'                                   => 'в',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'AEGEAN AIRLINES') === false) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Booking Reference'))} and (preceding::text()[contains(normalize-space(),'online booking confirmation') or contains(normalize-space(),'service booking confirmation') or contains(normalize-space(),'Award booking confirmation')]/preceding::img[contains(@src,e-ticket.aegeanair.com)] or preceding::*[{$this->contains($this->t("If you don't see correctly this email please click here"))} and descendant::a[contains(@href,'.aegeanair.com/ConfirmationEmail')]])]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Outbound'))} or {$this->contains($this->t('Inbound'))} or translate(normalize-space(),'0123456789','%%%%%%%%%%')= 'Bound %']")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aegeanair\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $f = $email->add()->flight();

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Ticket number'))}]/preceding::text()[normalize-space()][1]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*\(|$)/u"));
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Reference'))}\s*([A-Z\d]+)/"))
            ->travellers(preg_replace("/^(?:Herr|Δρ|Señor|Sra|Г-н|Г-жа|Dl|Sig\.ra|Sig|Κος|Κα|Άρρεν)[.\s]+(.{2,})$/u", '$1', $travellers));

        $f->setTicketNumbers($this->http->FindNodes("//text()[{$this->eq($this->t('Ticket number'))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('Ticket number'))}\s*([\d\-]+)/"), false);

        $accounts = $this->http->FindNodes("//text()[{$this->eq($this->t('Ticket number'))}]/following::text()[{$this->eq($this->t('FF number'))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('FF number'))}\s*(.+)/");

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $xpathTotalPrice = "*[normalize-space()][1][{$this->eq($this->t('TOTAL'))}] and count(*[normalize-space()])=2";

        $totalMiles = $this->http->FindSingleNode("//tr[ {$xpathTotalPrice} and following::tr[{$xpathTotalPrice}] ]/*[normalize-space()][2][{$this->contains($this->t('Miles'))}]", null, true, "/^.*\d.*$/");

        if ($totalMiles !== null) {
            // 8,376 Miles
            $f->price()->spentAwards($totalMiles);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ {$xpathTotalPrice} and not(following::tr[{$xpathTotalPrice}]) ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*|[,.][ ]*\d+)$/', $totalPrice, $matches)) {
            // € 180.45    |    € 0,00    |    € .74
            $matches['amount'] = preg_replace("/^([,.])[ ]*(\d+)$/", '0$1$2', $matches['amount']);
            $currencyNormal = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currencyNormal) ? $currencyNormal : null;
            $totalAmount = PriceHelper::parse($matches['amount'], $currencyCode);

            $costAmounts = [];
            $costRows = $this->http->XPath->query("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('Flight'))}][1]/following-sibling::tr[ *[2] ]");

            foreach ($costRows as $costRow) {
                if ($this->http->XPath->query("*[normalize-space()][1]/following-sibling::*", $costRow)->length === 0) {
                    break;
                } elseif ($this->http->XPath->query("*[normalize-space()][1]/following-sibling::*[{$this->contains($this->t('Miles'))}]", $costRow)->length > 0) {
                    continue;
                }
                $costValue = $this->http->FindSingleNode("*[normalize-space()][2]", $costRow, true, "/^.*\d.*$/");

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*|[,.][ ]*\d+)$/', $costValue, $m)) {
                    $m['amount'] = preg_replace("/^([,.])[ ]*(\d+)$/", '0$1$2', $m['amount']);
                    $costAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                } else {
                    $costAmounts = [];

                    break;
                }
            }

            if (count($costAmounts) > 0) {
                $f->price()->cost(array_sum($costAmounts));
            }

            if (count($costAmounts) > 0 || ($f->getPrice() && $f->getPrice()->getSpentAwards())) {
                // if there is no cost, then the price is only for additional services
                $f->price()
                    ->currency($currencyNormal)
                    ->total($totalAmount);

                $seatsHeader = $this->http->FindSingleNode("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('Seats'))}][1]");
                $seatsRows = $this->http->XPath->query("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('Seats'))}][1]/following-sibling::tr[ *[2] ]");
//            $this->logger->warning("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('Seats'))}][1]/following-sibling::tr[ *[2] ]");

                foreach ($seatsRows as $seatsRow) {
                    if ($this->http->XPath->query("*[normalize-space()][1]/following-sibling::*", $seatsRow)->length === 0) {
                        break;
                    }
                    $seatsValue = $this->http->FindSingleNode("*[normalize-space()][2]", $seatsRow, true, "/^.*\d.*$/");

                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*|[,.][ ]*\d+)$/', $seatsValue, $m)) {
                        $m['amount'] = preg_replace("/^([,.])[ ]*(\d+)$/", '0$1$2', $m['amount']);
                        $seatsName = $this->http->FindSingleNode('*[normalize-space()][1]', $seatsRow, true, '/^(.+?)[\s:：]*$/u');
                        $f->price()->fee($seatsHeader . ' (' . $seatsName . ')', PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }

                $baggageHeader = $this->http->FindSingleNode("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('Extra baggage'))}][1]");
                $baggageRows = $this->http->XPath->query("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('Extra baggage'))}][1]/following-sibling::tr[ *[2] ]");

                foreach ($baggageRows as $baggageRow) {
                    if ($this->http->XPath->query("*[normalize-space()][1]/following-sibling::*", $baggageRow)->length === 0) {
                        break;
                    }
                    $baggageValue = $this->http->FindSingleNode("*[normalize-space()][2]", $baggageRow, true, "/^.*\d.*$/");

                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*|[,.][ ]*\d+)$/', $baggageValue, $m)) {
                        $m['amount'] = preg_replace("/^([,.])[ ]*(\d+)$/", '0$1$2', $m['amount']);
                        $baggageName = $this->http->FindSingleNode('*[normalize-space()][1]', $baggageRow, true, '/^(.+?)[\s:：]*$/u');
                        $f->price()->fee($baggageHeader . ' (' . $baggageName . ')', PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }

                $insuranceHeader = $this->http->FindSingleNode("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('INSURANCE'))}][1]");
                $insuranceRows = $this->http->XPath->query("//tr[{$xpathTotalPrice}]/preceding::tr[{$this->eq($this->t('INSURANCE'))}][1]/following-sibling::tr[ *[2] ]");

                foreach ($insuranceRows as $insuranceRow) {
                    if ($this->http->XPath->query("*[normalize-space()][1]/following-sibling::*", $insuranceRow)->length === 0) {
                        break;
                    }
                    $insuranceValue = $this->http->FindSingleNode("*[normalize-space()][2]", $insuranceRow, true, "/^.*\d.*$/");

                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*|[,.][ ]*\d+)$/', $insuranceValue, $m)) {
                        $m['amount'] = preg_replace("/^([,.])[ ]*(\d+)$/", '0$1$2', $m['amount']);
                        $insuranceName = $this->http->FindSingleNode('*[normalize-space()][1]', $insuranceRow, true, '/^(.+?)[\s:：]*$/u');
                        $f->price()->fee($insuranceHeader . ' (' . $insuranceName . ')', PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }

                $feeRows = $this->http->XPath->query("//tr[{$xpathTotalPrice}]/preceding::tr[ *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] and count(*[normalize-space()])=2 ]");

                foreach ($feeRows as $feeRow) {
                    $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*|[,.][ ]*\d+)$/', $feeCharge, $m)) {
                        $m['amount'] = preg_replace("/^([,.])[ ]*(\d+)$/", '0$1$2', $m['amount']);
                        $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                        $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
        }

        $earned = array_sum(str_replace(',', '', $this->http->FindNodes("//text()[{$this->eq($this->t('Miles+Bonus award miles'))}]/following::text()[normalize-space()][1]")));

        if (!empty($earned)) {
            $f->setEarnedAwards($earned);
        }

        $seats = [];

        foreach ($f->getTravellers() as $pax) {
            $seats[] = implode(',', $this->http->FindNodes("//text()[{$this->eq($this->t('Please find below the selected seats'))}]/following::text()[{$this->contains($pax[0])}]//ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), '(')]"));
        }

        $seats = explode(',', implode(',', $seats));

        $cabin = explode(',', implode(',', $this->http->FindNodes("//text()[{$this->eq($this->t('Cabin Class'))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('Cabin Class'))}\s*(.+)/")));

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Operated by'))}]");

        foreach ($nodes as $key => $root) {
            $flights = $this->http->FindNodes(".//preceding::text()[starts-with(normalize-space(), 'Bound')]/following::text()[contains(normalize-space(), ':')][1]", $root);

            if ($this->http->XPath->query("//text()[normalize-space()='Bound 1']")->length > 0 && $key > 0) {
                if (count($flights) !== count(array_unique($flights))) {
                    continue;
                }
            }

            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Outbound'))} or {$this->eq($this->t('Inbound'))} or translate(normalize-space(),'0123456789','%%%%%%%%%%')= 'Bound %'][1]/following::*[{$xpathBold} and normalize-space()][1]", $root, true, "/^.*\d.*$/");

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)\s*,\s*{$this->opt($this->t('Operated by'))}\s*(.+)$/", $this->http->FindSingleNode(".", $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->operator($m[3]);
            }

            $departInfo = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[contains(normalize-space(), ':')][1]", $root);

            if (preg_match("/^\s*(?<depTime>[\d\:]+)\s*(?<depName>\D+?)(?:\s+{$this->opt($this->t('terminal'))}\s*(?<depTerminal>.+))?$/", $departInfo, $m)
                || preg_match("/^\s*(?<depTime>[\d\:]+)\s*(?<nextDay>[+]\d+)\s*(?<depName>\D+?)(?:\s+{$this->opt($this->t('terminal'))}\s*(?<depTerminal>[A-Z\d]+))?$/", $departInfo, $m)
            ) {
                $s->departure()
                    ->name($m['depName'])
                    ->noCode();

                $s->departure()
                    ->date($this->normalizeDate($date . ' ' . $m['depTime']));

                if (!empty($m['nextDay'])) {
                    $s->departure()
                        ->date(strtotime("+1 day", $s->getDepDate()));
                }

                if (!empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrInfo = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[contains(normalize-space(), ':')][last()]", $root);

            if (preg_match("/^\s*(?<arrTime>[\d\:]+)\s*(?<arrName>\D+?)(?:\s+{$this->opt($this->t('terminal'))}\s*(?<arrTerminal>.+))?$/u", $arrInfo, $m)
                || preg_match("/^\s*(?<arrTime>[\d\:]+)\s*(?<nextDay>[+]\d+)\s*(?<arrName>\D+?)(?:\s+{$this->opt($this->t('terminal'))}\s*(?<arrTerminal>[A-Z\d]+))?$/u", $arrInfo, $m)
            ) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->noCode();

                $s->arrival()
                    ->date($this->normalizeDate($date . ' ' . $m['arrTime']));

                if (!empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime("+1 day", $s->getArrDate()));
                }

                if (!empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $duration = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $aircraft = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Aircraft:'))}\s*(.+)/");

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            if ($this->http->XPath->query("//text()[normalize-space()='Bound 1']")->length > 0) {
                if (array_unique($cabin) == 1) {
                    $s->extra()
                        ->cabin((implode($cabin[0])));
                }

                $bookingCode = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Booking class'))}][1]/ancestor::tr[1]", $root, "/{$this->opt($this->t('Booking class'))}\s*([A-Z])\s*$/"));

                if (count($bookingCode) == 1) {
                    $s->extra()
                        ->bookingCode($bookingCode[0]);
                }
            } else {
                if (!empty($cabin[0])) {
                    if (count($cabin) > 1) {
                        $s->extra()
                            ->cabin($cabin[$key]);
                    } else {
                        $s->extra()
                            ->cabin($cabin[0]);
                    }
                }

                $bookingCode = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Booking class'))}][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Booking class'))}\s*([A-Z])\s*$/");

                if (!empty($bookingCode)) {
                    $s->extra()
                        ->bookingCode($bookingCode);
                }
            }

            foreach ($seats as $seat) {
                if (preg_match("/^(?<dep>\w+)\s*{$this->opt($this->t('to'))}\s*(?<arr>\w+)\s*(?<seat>\d+[A-Z])\s*\(.+\)$/u", $seat, $m)) {
                    if (stripos($s->getDepName(), $m['dep']) !== false && stripos($s->getArrName(), $m['arr']) !== false) {
                        $s->extra()
                            ->seat($m['seat']);
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->date = strtotime($parser->getDate());

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date-' . $date);

        $year = date('Y', $this->date);
        $in = [
            '/^(\w+)\s*(\d+)\.(\w+)\.?\s*([\d\:]+)$/u', // Sunday 12.Dec 16:15
        ];
        $out = [
            '$1, $2 $3 ' . $year . ' $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            if ($this->lang == 'el') {
                $m['week'] = preg_replace("/Τρίτη/", "τριτη", $m['week']);
            }
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$word}')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
