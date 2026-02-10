<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class YourFlightDetails extends \TAccountChecker
{
    public $mailFiles = "booking/it-152096529-fr.eml, booking/it-312800379.eml, booking/it-657102854.eml, booking/it-701199241.eml, booking/it-702109836.eml, booking/it-83389270.eml, booking/it-83389506.eml";

    public $lang = '';
    public $travellers;
    public $bookingRefs;

    public static $dictionary = [
        'en' => [
            //            'Hi' => '',
            'statusPhrases'      => ['– thanks for booking', ', thanks for booking'],
            'statusVariants'     => 'confirmed',
            'Booking references' => ['Booking references', 'Booking reference'],
            //            'Customer reference' => '',
            // 'stop'  => '',
            'Your flight details' => ['Your flight details', 'Departing flight', 'Flight to', 'Your new flights', 'Booking summary'],
            //            'Direct' => '',
            'durationRe'       => '(?: ?\d{1,2} ?(?:h|m))+', // 1h 00m    |    2h    |    55m
            'passenger'        => ['traveler', 'passenger'],
            'E-ticket numbers' => ['E-ticket numbers', 'E-ticket number', 'E-Ticket-Nummer '],
            //'to' => '',
            'Seats' => 'Sitzplätze',
        ],
        'de' => [
            'Hi'                  => 'Hallo',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking references'  => ['Buchungsnummern'],
            'Customer reference'  => 'Kundenreferenznummer',
            'stop'                => 'Stopp',
            'Your flight details' => ['Ihre Flugdaten', 'Ihre Flugübersicht', 'Buchungsübersicht', 'Hinflug', 'Rückflug', 'Flug nach'],
            'Direct'              => 'Direkt',
            'durationRe'          => '(?: ?\d{1,2} (?:Std|Min)\.)+', // 3 Std. 55 Min.
            'passenger'           => 'Reisender',
            'E-ticket numbers'    => ['E-ticket numbers', 'E-Ticket-Nummer'],
            'to'                  => 'nach',
            'Seats'               => 'Sitzplätze',
        ],
        'it' => [
            'Hi'                  => 'Ciao',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking references'  => ['Numero di prenotazione'],
            'Customer reference'  => 'Riferimento cliente',
            'stop'                => 'scalo',
            'Your flight details' => ['Dettagli del tuo volo', 'Riepilogo del tuo volo', 'Riepilogo prenotazione', 'Vai ai dati della prenotazione'],
            'Direct'              => 'Diretto',
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+',
            'passenger'           => 'passeggero',
            'E-ticket numbers'    => "Numero dell'e-ticket",
            'to'                  => '-',
            //'Seats' => '',
        ],
        'es' => [
            'Hi'                  => 'Hola,',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            //            'Booking references'  => '',
            'Customer reference'  => 'Referencia del cliente',
            //            'stop'                => 'scalo',
            'Your flight details' => ['Datos de tu vuelo'],
            'Direct'              => 'Directo',
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+', // 3 h 55 min
            //            'passenger'           => '',
            //'E-ticket numbers' => '',
            //'to' => '',
            //'Seats' => '',
        ],
        'da' => [
            'Hi'                  => 'Hej',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            //            'Booking references'  => '',
            'Customer reference'  => 'Kundereference',
            //            'stop'                => 'scalo',
            'Your flight details' => ['Dine flyoplysninger'],
            'Direct'              => 'Direkte ',
            'durationRe'          => '(?: ?\d{1,2} (?:t|min)\.)+', //  2 t. 30 min.
            //            'passenger'           => '',
            //'E-ticket numbers' => '',
            //'to' => '',
            //'Seats' => '',
        ],
        'fr' => [
            'Hi'                  => 'Bonjour',
            'statusPhrases'       => ', merci pour votre réservation',
            'statusVariants'      => 'confirmé',
            'Booking references'  => ['Références de réservation', 'Référence de réservation'],
            'Customer reference'  => 'Référence client',
            'stop'                => 'escale',
            'Your flight details' => ['Détails de votre vol', 'Récapitulatif de la réservation', 'Récapitulatif de votre vol'],
            'Direct'              => 'Vol direct',
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+', // 3 h 05 min
            'passenger'           => ['passager', 'passagers'],
            'E-ticket numbers'    => ["Numéro de l'e-billet", "Numéros des e-billets"],
            //'to' => '',
            //'Seats' => '',
        ],
        'cs' => [
            'Hi'                  => 'Dobrý den',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            //'Booking references'  => '',
            'Customer reference'  => 'Referenční číslo zákazníka',
            'stop'                => 'mezipřistání',
            'Your flight details' => ['Informace o Vašem letu'],
            'Direct'              => 'Vol direct',
            'durationRe'          => '(?: ?\d{1,2} (?:h|min))+', // 3 h 05 min
            //'passenger'           => '',
            'E-ticket numbers' => 'Čísla e-letenek',
            //'to' => '',
            //'Seats' => '',
        ],
        'ro' => [
            'Hi'                  => 'Bună ziua',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            'Booking references'  => 'Referința rezervării:',
            //'Customer reference'  => '',
            'stop'                => 'escală',
            'Your flight details' => ['Zborul de plecare'],
            //'Direct'              => '',
            'durationRe'          => '(?: ?\d{1,2} (?:ore|min))+', // 3 h 05 min
            //'passenger'           => '',
            'E-ticket numbers' => 'Număr bilet electronic',
            'to'               => 'către',
            'Seats'            => 'Locuri',
        ],

        'ar' => [
            'Hi'                  => 'مرحباً',
            //'statusPhrases'       => '',
            //'statusVariants'      => '',
            'Booking references'  => 'الرقم المرجعي للحجز:',
            //'Customer reference'  => '',
            //'stop'                => '',
            'Your flight details' => ['رحلة جوية إلى'],
            //'Direct'              => '',
            'durationRe'          => '(?: ?\d{1,2} (?:ساعات|دقيقة))+', // 3 h 05 min
            //'passenger'           => '',
            'E-ticket numbers' => '',
            'to'               => 'إلى',
            //'Seats' => '',
        ],
    ];

    private $subjects = [
        'en' => [
            'Here are all the details about your flight booking',
            'Check-in for your flight to',
        ],
        'de' => ['Hier sind alle Infos zu Ihrer Flugbuchung',
            'Checken Sie für Ihren Flug nach', ],
        'it' => ['conferma del volo',
            'Check-in del tuo volo per', 'Hai già fatto il check-in del volo per', ],
        'es' => ['Ya puedes hacer la facturación para tu vuelo a'],
        'da' => ['Nu kan du tjekke ind på dit fly til'],
        'ro' => ['Detaliile rezervării de zbor de la'],
        'cs' => ['Zde jsou všechny podrobnosti o Vaší rezervaci letu'],
        'fr' => ['Confirmation de votre vol pour', 'Informations relatives à votre vol',
            'Enregistrez-vous pour votre vol vers',
            'Remboursement émis pour votre réservation de vol', ],
        'ar' => ['حجز الرحلة الجوية إلى'],
    ];

    private $detectors = [
        'en' => ['Here are all the details about your flight booking', ", you're flying to",
            "Check-in for your flight to", "If you need to find your complete flight itinerary",
            "flights have been changed. Please find the summary of your new flights below", ],
        'de' => ['Hier sind alle Infos zu Ihrer Flugbuchung', "Ihre Flugdaten", 'Haben Sie für Ihren Flug nach', "Ihr Flug nach"],
        'it' => ["Dettagli del tuo volo", "La prenotazione del tuo volo"],
        'es' => ["Datos de tu vuelo"],
        'da' => ["Dine flyoplysninger"],
        'cs' => ["Rezervační kódy"],
        'ro' => ["Zborul de plecare"],
        'ar' => ["رحلة جوية إلى"],
        'fr' => ['Informations relatives à votre vol', ', embarquez pour', 'Récapitulatif de la réservation', 'Récapitulatif de votre vol'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".booking.com/") or contains(@href,"flights.booking.com") or contains(@href,"flights-support.booking.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This email was sent by ‌Booking.com") or contains(.,"@booking.com") or contains(normalize-space(), "Booking.com")]')->length === 0
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
        $email->setType('YourFlightDetails' . ucfirst($this->lang));

        $this->parseFlight($email, $parser);

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

    private function parseFlight(Email $email, \PlancakeEmailParser $parser): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $email->obtainTravelAgency();
        $otaConf = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Customer reference'))}]/following::text()[normalize-space()][1])[1]", null, true, "/^\s*([\d\-]{5,})\s*$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $f = $email->add()->flight();

        $tickets = [];
        $travellers = [];
        $ticketsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('E-ticket numbers'))}]/ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('E-ticket numbers'))}]][count(following-sibling::*[normalize-space()]) >2][1]/following-sibling::*[normalize-space()][position() < 25]");

        foreach ($ticketsNodes as $tRoot) {
            $values = $this->http->FindNodes(".//text()[normalize-space()]", $tRoot);

            if (count($values) == 2 && preg_match("/^\s*\d{3}[- ]?\d{3,}\s*$/", $values[1]) && preg_match("/^[[:alpha:] \-]+$/", $values[0])) {
                $travellers[] = $values[0];
                $tickets[] = $values[1];
            } elseif (count($values) == 1 && preg_match("/^\s*[A-Z]{3}\s*\W\s*[A-Z]{3}\s*$/u", $values[0])) {
                continue;
            } else {
                break;
            }
        }
        $tickets = array_unique($tickets);
        $travellers = array_unique($travellers);

        if (count($travellers) === 0) {
            $travellers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('E-ticket numbers'))}]/preceding::text()[normalize-space()][1]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/")));
        }

        if (count($travellers) === 0) {
            $travellers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/preceding::text()[not({$this->contains($this->t('E-ticket numbers'))})][1]/preceding::text()[normalize-space()][1]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/")));
        }

        if (!empty($travellers)) {
            $f->general()->travellers($this->travellers = $travellers, false);
        } else {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null,
                "/^{$this->opt($this->t('Hi'))}[ ]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:[ ]*[,;:!?،]|$)/u"));
            $traveller = count($travellers) === 1 ? array_shift($travellers) : null;

            if (!empty($traveller)) {
                $this->travellers[] = $traveller;
                $f->general()->traveller($traveller);
            }
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/({$this->opt($this->t('statusVariants'))})\s*{$this->opt($this->t('statusPhrases'))}/");

        if ($status) {
            $f->general()->status($status);
        }

        if (empty($tickets)) {
            $tickets = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('E-ticket numbers'))}]/following::tr/descendant::text()[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'ddddddddddddd') or contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'ddd-dddddddddd')]"));
        }

        $ticketArray = [];

        if (count($tickets) > 0) {
            foreach ($tickets as $ticket) {
                $paxs = array_filter($this->http->FindNodes("//text()[{$this->eq($ticket)}]/ancestor::tr[normalize-space()][2]/preceding::text()[normalize-space()][1]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/"));

                if (count($paxs) > 0) {
                    foreach ($paxs as $pax) {
                        if (in_array($ticket, $ticketArray) === false && (in_array($pax, $travellers) === true || (isset($traveller) && stripos($traveller, $pax) !== false))) {
                            $f->addTicketNumber($ticket, false, $pax);
                            $ticketArray[] = $ticket;
                        }
                    }
                } elseif (in_array($ticket, $ticketArray) === false) {
                    $f->setTicketNumbers(array_unique($tickets), false);

                    break;
                }
            }
        }

        $this->bookingRefs = $this->http->XPath->query("//div[ preceding-sibling::div/descendant::text()[normalize-space()][1][{$this->eq($this->t('Booking references'))}] and following-sibling::div[{$this->eq($this->t('Your flight details'))}] ]/descendant::*[ *[normalize-space()][1][contains(.,'→')] and *[normalize-space()][2][not(contains(normalize-space(),' '))] ]");

        if ($this->bookingRefs->length === 0) {
            $this->bookingRefs = $this->http->XPath->query("//text()[{$this->starts($this->t('Booking references'))}]");
        }

        $segments = $this->http->XPath->query("//text()[normalize-space()='Your new flights']/following::*[ tr[normalize-space()][3] and tr[normalize-space()][1][not(.//tr) and ({$this->contains(['→', 'vers', ' to ', ' nach ', ' - ', ' către '])})] and tr[normalize-space()][2][{$xpathTime}] ]");

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//*[ tr[normalize-space()][3] and tr[normalize-space()][1][not(.//tr) and ({$this->contains(['→', 'vers', ' to ', ' nach ', ' - ', ' către ', 'إلى '])})] and tr[normalize-space()][2][{$xpathTime}] ]");
        }

        foreach ($segments as $key => $root) {
            $segmentText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/\s+[·]\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}\,\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}\,\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}$/u", $segmentText)) {
                // · Business Turkish Airlines · TK1036, TK168, TK192
                $email->setIsJunk(true);
            } elseif (preg_match("/\s+[·]\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}\,\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}/u", $segmentText)) {
                //  · Business Turkish Airlines · TK1036, TK168
                $this->twoSegmentsInOne($segments, $key, $root, $f, $parser, $segmentText);
            } else {
                $this->oneSegment($segments, $key, $root, $f, $parser);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//div[{$this->eq($this->t('Your flight details'))}]/following-sibling::div[{$this->contains($this->t('passenger'))}][1]", null, true, "/{$this->opt($this->t('passenger'))}[)(s]*[ ]+·[ ]+(.+)$/ui");

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//div[{$this->eq($this->t('Your flight details'))}]/following-sibling::div[{$this->contains($this->t('passenger'))}][1]", null, true, "/{$this->opt($this->t('passenger'))}[\s\:]*([\d\.\,]+\s*\D{1,3})\s/ui");
        }

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total price for'))}]", null, true, "/{$this->opt($this->t('passenger'))}[\s\:]*((?:\D{1,3})?\s*[\d\.\,]+\s*(?:\D{1,3})?)/ui");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+?)$/', $totalPrice, $matches)
        ) {
            // $458.89    |    5.535,54 €
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()
                ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                ->currency($matches['currency']);
        }

        if ($segments->length) {
            $f->general()->noConfirmation();
        }
    }

    private function oneSegment(\DOMNodeList $segments, $key, \DOMNode $root, Flight $f, \PlancakeEmailParser $parser)
    {
        $this->logger->debug(__METHOD__);
        $s = $f->addSegment();

        if ($segments->length === 1) {
            if ($this->travellers > 0) {
                foreach ($this->travellers as $pax) {
                    $seat = $this->http->FindSingleNode("//text()[{$this->eq($pax)}]/following::text()[normalize-space()][3]/following::text()[normalize-space()][1]", null, true, "/^(\d+[A-Z])$/");

                    if (empty($seat)) {
                        $seat = $this->http->FindSingleNode("//text()[{$this->eq($pax)}]/following::text()[normalize-space()][1][{$this->contains($this->t('Seat'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+[A-Z])$/");
                    }

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat, false, false, $pax);
                    }
                }
            }
        } else {
            $seatText = $this->http->FindSingleNode("./following::text()[{$this->contains($this->travellers)}][1]/following::text()[normalize-space()][3][contains(normalize-space(), 'Seat')]/following::text()[normalize-space()][1]/ancestor::table[1]", $root);

            if (preg_match_all("/\:\s*(\d+[A-Z])/", $seatText, $m)) {
                $s->extra()
                    ->seats($m[1]);
            } elseif (preg_match("/^(\d+[A-Z])$/", $seatText, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }
        }

        $airportsValue = $this->http->FindSingleNode("tr[normalize-space()][1]", $root);
        $airports = preg_split("/\s+(?:→|vers|to|nach|-|către|إلى)\s+/", $airportsValue);

        if (count($airports) !== 2) {
            $this->logger->debug('Wrong airports from segment-' . $key);

            return;
        }

        if ($this->lang == 'de') {
            $airports[0] = preg_replace('/^\s*Von /', '', $airports[0]);
        }
        $s->departure()->name($airports[0]);

        $s->arrival()->name($airports[1]);

        // Apr 29 · 19:25    |    1er août · 15h30
        $patterns['dateTime'] = "/^(?<date>.{3,})[ ]+·[ ]+(?<time>\d{1,2}[:Hh]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/";

        $datesValue = $this->http->FindSingleNode("tr[normalize-space()][2]", $root);
        $dates = preg_split("/\s+-\s+/", $datesValue);

        if (count($dates) !== 2) {
            $this->logger->debug('Wrong dates from segment-' . $key);

            return;
        }

        if (preg_match($patterns['dateTime'], $dates[0], $m)
            //26 أغسطس · 2:35 صباحاً
            || preg_match("/^(?<date>\d+\s+\D+)\s+(?<time>\d+\:\d+)/", $dates[0], $m)) {
            $m['date'] = $this->translateDate($m['date']);
            $m['time'] = $this->normalizeTime($m['time']);
            $dateDep = EmailDateHelper::calculateDateRelative($m['time'] . ' ' . $m['date'], $this, $parser,
                '%D% %Y%');

            if ($dateDep) {
                $s->departure()->date($dateDep);
            }
        }

        if (preg_match($patterns['dateTime'], $dates[1], $m)
            //26 أغسطس · 2:35 صباحاً
            || preg_match("/^(?<date>\d+\s+\D+)\s+(?<time>\d+\:\d+)/", $dates[1], $m)) {
            $m['date'] = $this->translateDate($m['date']);
            $m['time'] = $this->normalizeTime($m['time']);
            $dateArr = EmailDateHelper::calculateDateRelative($m['time'] . ' ' . $m['date'], $this, $parser,
                '%D% %Y%');

            if ($dateArr) {
                $s->arrival()->date($dateArr);
            }
        }

        $extraValue = $this->http->FindSingleNode("tr[normalize-space()][3]", $root);

        if (preg_match("/^{$this->opt($this->t('Direct'))}\b/i", $extraValue)) {
            $s->extra()->stops(0);
        } elseif (preg_match("/^(\d{1,3})\s*{$this->opt($this->t('stop'))}/i", $extraValue, $m)) {
            $s->extra()->stops($m[1]);
        }

        if (preg_match("/ · (" . $this->t("durationRe") . ")$/i", $extraValue, $m)) {
            $s->extra()->duration($m[1]);
        } elseif (preg_match("/(?:^|\W|\s)(" . $this->t("durationRe") . ") · ([[:alpha:] ]+)\s*$/ui", $extraValue, $m)) {
            $s->extra()
                ->duration($m[1])
                ->cabin($m[2])
            ;
        }

        $cabinValue = $this->http->FindSingleNode("tr[normalize-space()][4]", $root);
        // $this->logger->debug('$cabinValue = '.print_r( $cabinValue,true));

        if (preg_match("/^\s*(?<airName>[^·]+)[·]\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fn>\d{1,5})\s*$/u", $cabinValue, $m)) {
            // Air Baltic · BT313
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
        } elseif (preg_match("/(?<airName>.+)[·]\s*(?<cabin>Classe\s*\w+)/u", $cabinValue, $m)) {
            $s->extra()
                ->cabin($m['cabin']);

            $s->airline()
                ->name($m['airName'])
                ->noNumber();
        } else {
            $s->airline()->noName()->noNumber();
        }

        if ($this->bookingRefs->length === $segments->length) {
            $root2 = $this->bookingRefs->item($key);
            $codes = $this->http->FindSingleNode("*[normalize-space()][1]", $root2);

            if (preg_match("/^([A-Z]{3})[ ]+→[ ]+([A-Z]{3})$/", $codes, $m)) {
                // GUA → FRS
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            } elseif (preg_match("/\(([A-Z]{3})\)/u", $s->getDepName())) {
                if (preg_match("/\(([A-Z]{3})\)/u", $s->getDepName(), $m)) {
                    $s->departure()
                        ->code($m[1]);
                }

                if (preg_match("/\(([A-Z]{3})\)/u", $s->getArrName(), $m)) {
                    $s->arrival()
                        ->code($m[1]);
                }
            }
            $confirmation = $this->http->FindSingleNode("*[normalize-space()][2]", $root2, true,
                "/^[-A-Z\d]{5,}$/");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode(".", $root2, true, "/\s([-A-Z\d]{5,})$/");
            }

            if (!empty($confirmation)) {
                $s->airline()->confirmation($confirmation);
            }
        } else {
            if (preg_match("/\(([A-Z]{3})\)\s*$/u", $s->getDepName(), $m)) {
                $s->departure()
                    ->code($m[1]);
            } else {
                $s->departure()->noCode();
            }

            if (preg_match("/\(([A-Z]{3})\)/u", $s->getArrName(), $m)) {
                $s->arrival()
                    ->code($m[1]);
            } else {
                $s->arrival()->noCode();
            }

            $confirmation = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/\s([-A-Z\d]{5,})$/");

            if (!empty($confirmation)) {
                $s->airline()->confirmation($confirmation);
            }
        }
    }

    private function twoSegmentsInOne(\DOMNodeList $segments, $key, \DOMNode $root, Flight $f, \PlancakeEmailParser $parser, string $segmentText)
    {
        $cabin = '';
        $extraValue = $this->http->FindSingleNode("tr[normalize-space()][3]", $root);

        if (preg_match("/(?:^|\W|\s)" . $this->t("durationRe") . " · ([[:alpha:] ]+)\s*$/ui", $extraValue, $m)) {
            $cabin = $m[1];
        }
        $s = $f->addSegment();

        if (preg_match("/^(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s*{$this->opt($this->t('to'))}\s*(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s+(?<depDate>\d+\.?\s*\w+)\.?[\s\·]*(?<depTime>\d+\:\d+)[\s\-]+(?<arrDate>\d+\.?\s*\w+)\.?[\s\·]*(?<arrTime>\d+\:\d+).+[·]\s+(?<firstAName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<firstFNumber>\d{1,4})\,\s+(?<secondAName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<secondFNumber>\d{1,4})/u", $segmentText, $m)
        || preg_match("/^(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s*{$this->opt($this->t('to'))}\s*(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s+(?<depDate>\w+\s+\d+)\.?[\s\·]*(?<depTime>\d+\:\d+\s*A?P?M?)[\s\-]+(?<arrDate>\w+\s+\d+)\.?[\s\·]*(?<arrTime>\d+\:\d+\s*A?P?M?).+[·]\s+(?<firstAName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<firstFNumber>\d{1,4})\,\s+(?<secondAName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<secondFNumber>\d{1,4})/u", $segmentText, $m)) {
            $confs = $this->http->FindSingleNode("//text()[{$this->contains($m['firstAName'] . $m['firstFNumber'])}]/following::text()[normalize-space()][1][{$this->contains($this->t('Booking references'))}]", null, true, "/\:?\s*([A-Z\d]{6})$/");

            if (!empty($confs)) {
                $s->setConfirmation($confs);
            }

            $s->airline()
                ->name($m['firstAName'])
                ->number($m['firstFNumber']);

            $m['depDate'] = $this->translateDate($m['depDate']);
            $m['depTime'] = $this->normalizeTime($m['depTime']);

            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
                ->date(EmailDateHelper::calculateDateRelative($m['depTime'] . ' ' . $m['depDate'], $this, $parser,
                    '%D% %Y%'));

            $s->arrival()
                ->noCode()
                ->noDate();

            if (count($this->travellers) > 0) {
                $seats = $this->getSeatsByDepCode($this->travellers, $s->getDepCode());

                foreach ($seats as $seatText) {
                    $seat = explode('-', $seatText);
                    $s->extra()
                        ->seat($seat[1], false, false, $seat[0]);
                }
            }

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            //--------Second segments---------------

            $s = $f->addSegment();
            $confs = $this->http->FindSingleNode("//text()[{$this->contains($m['secondAName'] . $m['secondFNumber'])}]/following::text()[normalize-space()][1][{$this->contains($this->t('Booking references'))}]", null, true, "/\:?\s*([A-Z\d]{6})$/");

            if (!empty($confs)) {
                $s->setConfirmation($confs);
            }

            $s->airline()
                ->name($m['secondAName'])
                ->number($m['secondFNumber']);

            $s->departure()
                ->noCode()
                ->noDate();

            $m['arrDate'] = $this->translateDate($m['arrDate']);
            $m['arrTime'] = $this->normalizeTime($m['arrTime']);

            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->date(EmailDateHelper::calculateDateRelative($m['arrTime'] . ' ' . $m['arrDate'], $this, $parser,
                    '%D% %Y%'));

            if (count($this->travellers) > 0) {
                $seats = $this->getSeatsByArrCode($this->travellers, $s->getArrCode());

                foreach ($seats as $seatText) {
                    $seat = explode('-', $seatText);
                    $s->extra()
                        ->seat($seat[1], false, false, $seat[0]);
                }
            }

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }
    }

    private function getSeatsByDepCode(array $travellers, string $depCode)
    {
        $seats = [];

        foreach ($travellers as $traveller) {
            $seat = $this->http->FindSingleNode("//text()[{$this->eq($traveller)}]/following::table[{$this->contains($this->t('Seats'))}][1]/descendant::text()[{$this->starts($depCode . ' –')}]/ancestor::tr[1]", null, true, "/\:\s*(\d+[A-Z])/");

            if (!empty($seat)) {
                $seats[] = $traveller . '-' . $seat;
            }
        }

        return $seats;
    }

    private function getSeatsByArrCode(array $travellers, string $arrCode)
    {
        $seats = [];

        foreach ($travellers as $traveller) {
            $seat = $this->http->FindSingleNode("//text()[{$this->eq($traveller)}]/following::table[{$this->contains($this->t('Seats'))}][1]/descendant::text()[{$this->contains('– ' . $arrCode)}]/ancestor::tr[1]", null, true, "/\:\s*(\d+[A-Z])/");

            if (!empty($seat)) {
                $seats[] = $traveller . '-' . $seat;
            }
        }

        return $seats;
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Your flight details'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Your flight details'])}]")->length > 0) {
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    private function translateDate(?string $date)
    {
        //$this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // 17 Jul    |    8 de out    |    1er août
            '/^\s*(\d{1,2})(?:er)?[.\s]+(?:de\s+)?([[:alpha:]]+)[.\s\·]*$/iu',
        ];
        $out = [
            "$1 $2",
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)(?:\s+\d{4}|$)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*h[ ]*(\d)/i', '$1:$2', $s); // 01h55 PM    ->    01:55 PM

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
