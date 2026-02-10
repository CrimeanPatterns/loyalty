<?php

namespace AwardWallet\Engine\airbus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PricedTravelPlan extends \TAccountChecker
{
    public $pdfNamePattern = ".*pdf";
    public $reSubject = [
        'Priced Travel Plan pour',
        'PTP for',
        'Approuve: Priced Travel Plan pour',
        'PTP für',
        // fr
        'PTP pour',
    ];

    public $mailFiles = "airbus/it-218898810.eml, airbus/it-2545941.eml, airbus/it-2546501.eml, airbus/it-2567977.eml, airbus/it-261344811.eml, airbus/it-2616612.eml, airbus/it-282090358.eml, airbus/it-282090754.eml, airbus/it-327532992.eml";

    public $lang = '';

    public $detectLang = [
        'en' => ['Itinerary'],
        'fr' => ['Itinéraire'],
        'de' => ['Reiseverlauf'],
        'es' => ['Información del Viajero'],
    ];

    public static $dictionary = [
        "en" => [
            //'Itinerary' => '',
            //'Entity' => '',
            //'TOTAL' => '',
            //Booking fee' => '',
            //Flights
            //'Flight:' => '',
            //'Airline Booking Ref:' => '',
            //'Flight duration:' => '',
            //'Class:' => '',
            //'E-ticket number:' => '',
            //'Seat Reservation:' => '',
            //'Booking Status:' => '',
            //'Operated by:' => '',

            //Hotels
            //'hotel' => '',
            'N° confirmation:' => ['Confirmation number.:', 'N° confirmation:'],
            //'CANCELLATION POLICY:' => '',
            //'HOLD POLICY:' => '',
            //'Hotel address:' => '',
            'Telephone:' => ['Tel.:', 'Telephone:'],
            //'Fax:' => '',
            //'Rate' => ''
            //'Room Description:' => '',
        ],
        "fr" => [
            'Itinerary'   => 'Itinéraire',
            'Entity'      => 'Entité',
            'TOTAL'       => 'TOTAL',
            'Booking fee' => 'Frais de reservation',
            //Flights
            'Flight:'              => ['Vol:', 'Vol Charter:'],
            'Airline Booking Ref:' => 'Airline Booking Ref:',
            'Flight duration:'     => 'Durée de vol:',
            'Class:'               => 'Classe:',
            //'E-ticket number:' => '',
            //'Seat Reservation:' => '',
            'Booking Status:' => 'Status de la réservation:',
            'Operated by:'    => 'opéré par:',
            //Hotels
            'hotel'                => 'l’hôtel:',
            'N° confirmation:'     => 'N° de confirmation:',
            'CANCELLATION POLICY:' => ['POLITIQUE D\'ANNULATION:', 'Annulation possible jusqu’au:'],
            'HOLD POLICY:'         => ['POLITIQUE DE MISE EN ATTENTE:', 'Petit déjeuner:'],
            'Hotel address:'       => 'Adresse de l’hôtel:',
            'Telephone:'           => 'Telephone:',
            'Fax:'                 => 'Fax:',
            'Rate'                 => 'Tarif journalier:',
            //'Room Description:' => '',

            // Train
            'Train'             => 'Train',
            'Seat reservation:' => 'Réservation de siège:',
            'Number of order:'  => 'Référence de dossier:',
            'Ticket number:'    => 'Numéro ticket:',
            'Duration:'         => 'Durée:',
            'Car'               => 'VOITURE',
            'Seat'              => 'Siège',
            'Hours'             => 'Heures',
        ],
        "de" => [
            'Itinerary'   => 'Reiseverlauf',
            'Entity'      => 'Firma',
            'TOTAL'       => 'SUMME',
            'Booking fee' => 'Buchungsgebühren',
            //Flights
            'Flight:'              => ['Flug:', 'Charterflug:'],
            'Airline Booking Ref:' => 'Airline Booking Ref:',
            'Flight duration:'     => 'Flugdauer:',
            'Class:'               => 'Klasse:',
            //'E-ticket number:' => '',
            'Seat Reservation:' => 'Sitzplatzreservierung:',
            'Booking Status:'   => 'Buchungsstatus:',
            'Operated by:'      => 'durchgeführt von:',
            //Hotels
            'hotel'                => ['Hotel', 'Hôtel'],
            'N° confirmation:'     => 'Reservierungsnr.:',
            'CANCELLATION POLICY:' => 'Stornierung möglich bis:',
            'HOLD POLICY:'         => 'Frühstück:',
            'Hotel address:'       => 'Hoteladresse:',
            'Telephone:'           => 'Tel.:',
            'Fax:'                 => 'Fax:',
            'Rate'                 => 'Tagesrate:',
            'Room Description:'    => 'Zimmer:',
            //Train
            'Train'             => 'Bahn',
            //'Seat reservation:' => '',
            'Number of order:'  => 'Auftragsnummer:',
            'Ticket number:'    => 'Ticketnummer:',
            'Dauer:'            => 'Duración:',
            //'Car'               => '',
            //'Seat'              => '',
            'Hours' => 'Uhr',
        ],

        "es" => [
            'Itinerary'   => 'Itinerario',
            'Entity'      => 'Entidad',
            //'TOTAL'       => '',
            'Booking fee' => 'Gasto de reserva',
            //Flights
            'Flight:'              => 'Vuelo:',
            'Airline Booking Ref:' => 'Localizador Aereo:',
            'Flight duration:'     => 'Duración de vuelo:',
            'Class:'               => 'Clase:',
            //'E-ticket number:' => '',
            'Seat Reservation:' => '',
            'Booking Status:'   => 'Estatus de la Reserva:',
            'Operated by:'      => 'Operado por:',
            //Hotels
            //'hotel'                => [''],
            'N° confirmation:'     => ['Número de confirmación:', 'Número de Confirmación:'],
            'CANCELLATION POLICY:' => 'Cancelación posible hasta:',
            //'HOLD POLICY:'         => '',
            'Hotel address:'       => 'Dirección del Hotel:',
            'Telephone:'           => 'Tel.:',
            'Fax:'                 => 'Fax:',
            'Rate'                 => 'Tarifa diaria:',
            //'Room Description:'    => '',
            'Free Cancellation:' => 'Cancelación posible hasta:',
            // Train
            'Train'             => 'Tren',
            'Seat reservation:' => 'Reserva de asiento:',
            'Number of order:'  => 'Número de orden:',
            'Ticket number:'    => 'Número del billete:',
            'Duration:'         => 'Duración:',
            'Car'               => 'Vagón',
            'Seat'              => 'Asiento',
            'Hours'             => 'Horas',

            //Rental
            'Vehicle Company' => 'Compañía de Vehículos',
            'Category:'       => 'Categoria:',
            'Rent:'           => 'Alquiler:',
            //'OPENING HOURS PICK-UP:' => '',
            //'OPENING HOURS DROP-OFF:' => '',
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, '@airbus.com') !== false && strpos($text, $this->t('Itinerary')) !== false && strpos($text, $this->t('Entity')) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airbus.com') !== false) {
            foreach ($this->reSubject as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airbus\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf), false);

            $this->assignLang($text);

            $text = preg_replace("#\d\/\d#", "", $text);

            $segmentText = $this->re("/{$this->opt($this->t('Itinerary'))}(\n+.+)/su", $text);

            $segments = $this->splitText($segmentText, "/\n{2,}(?:(\s*\d+\.\d+\.\d+\s*\d+\:\d+\s*.+|\s*\d+\.\d+\.\d{4}\D+\n\s*\d+\.\d+\.\d{4}.*))/u", true);
            $flightArray = [];

            foreach ($segments as $segment) {
                if (preg_match("/{$this->opt($this->t('Flight:'))}\s*[A-Z\d]{2}\s*\d{2,4}/u", $segment)) {
                    $flightArray[] = $segment;
                }

                if (preg_match("/{$this->opt($this->t('Hotel address:'))}/iu", $segment) && stripos($segment, $this->t('Flight duration:')) == false) {
                    $this->ParseHotel($email, $segment);
                }

                if (preg_match("/\n *{$this->opt($this->t('Train'))}/iu", $segment)) {
                    $this->ParseTrain($email, $segment);
                }

                if (preg_match("/{$this->opt($this->t('Vehicle Company'))}/iu", $segment)) {
                    $this->ParseRental($email, $segment);
                }
            }

            $this->ParseFlight($email, $flightArray);
        }

        $travellers = [];

        $this->logger->debug('$text = ' . print_r($text, true));

        if (preg_match_all("/(?:^|\n).* {5,}([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n(?: {0,10}\S( ?\S)*\n){0,2}.*[ ]{5,}Priced Travel Plan N/u", $text, $m)
        ) {
            $travellers = array_filter(array_unique($m[1]));
        }

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->travellers($travellers);
        }

        $priceText = $this->re("/{$this->opt($this->t('TOTAL'))}\s*(.+)/", $text);

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $priceText, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->re("/{$this->opt($this->t('Booking fee'))}\s*([\d\.\,]+)/", $text);

            if (!empty($tax)) {
                $email->price()
                    ->fee('fee', PriceHelper::parse($tax, $m['currency']));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseRental(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation(str_replace(' ', '', $this->re("/{$this->opt($this->t('N° confirmation:'))}\s*([A-Z\d]{5,})/u", $text)));

        if (preg_match("/^\s*(?<dDate>\d+\.\d+\.\d{4})\s+(?<dTime>[\d\:]+)\s*Horas\s*(?<dName>.+)\n\s*(?<aDate>\d+\.\d+\.\d{4})\s+(?<aTime>[\d\:]+)\s*Horas\s*(?<aName>.+)[ ]{10,}/", $text, $m)) {
            $r->pickup()
                ->location($m['dName'])
                ->date(strtotime($m['dDate'] . ', ' . $m['dTime']));

            $pHours = $this->re("/{$this->opt($this->t('OPENING HOURS PICK-UP:'))}\s*(.+)/", $text);

            if (!empty($pHours)) {
                $r->pickup()
                    ->openingHours($pHours);
            }

            $r->dropoff()
                ->location($m['aName'])
                ->date(strtotime($m['aDate'] . ', ' . $m['aTime']));

            $dHours = $this->re("/{$this->opt($this->t('OPENING HOURS DROP-OFF:'))}\s*(.+)/", $text);

            if (!empty($dHours)) {
                $r->dropoff()
                    ->openingHours($dHours);
            }
        }

        if (preg_match("/\s+(?<total>[\d\,\.]+)\s*(?<currency>[A-Z]{3})\n/", $text, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $tableSeg = $this->SplitCols($text, [0, 25]);

        if (preg_match("/{$this->opt($this->t('Rent:'))}\s*\n(?<company>\D+)/", $tableSeg[0], $m)) {
            $r->setCompany($m['company']);
        }

        if (preg_match("/{$this->opt($this->t('Category:'))}(?<type>.+\n*.*)\-(?<model>.+OR SIMILAR)/", $tableSeg[1], $m)) {
            $r->car()
                ->type(str_replace("\n", "", $m['type']))
                ->model($m['model']);
        }
    }

    public function ParseTrain(Email $email, $text)
    {
        if (!isset($t)) {
            $t = $email->add()->train();
        }

        $t->general()
            ->confirmation(str_replace(' ', '', $this->re("/{$this->opt($this->t('Number of order:'))}\s*([A-Z\d]{5,})/u", $text)));

        $s = $t->addSegment();

        $s->extra()
            ->number($this->re("/{$this->opt($this->t('Train'))}.*\n\s+(\d{2,})\s+/", $text));

        if (preg_match("/^\s+(?<dDate>\d+\.\d+\.\d{4})\s+(?<dTime>[\d\:]+)\s*{$this->opt($this->t('Hours'))}\s*(?<dName>.+)\n\s+(?<aDate>\d+\.\d+\.\d{4})\s+(?<aTime>[\d\:]+)\s*{$this->opt($this->t('Hours'))}\s*(?<aName>\D+)(?:[ ]{10,})?.*\n\n\s*{$this->opt($this->t('Train'))}/", $text, $m)) {
            $s->departure()
                ->name($m['dName'])
                ->date(strtotime($m['dDate'] . ', ' . $m['dTime']));

            $s->arrival()
                ->name($m['aName'])
                ->date(strtotime($m['aDate'] . ', ' . $m['aTime']));
        }

        $ticket = $this->re("/{$this->opt($this->t('Ticket number:'))}\s*([A-Z\d]{2,})/", $text);

        if (!empty($ticket) && $ticket !== $s->getNumber()) {
            $t->setTicketNumbers([$ticket], false);
        }

        if (preg_match("/{$this->opt($this->t('Seat reservation:'))}\s*{$this->opt($this->t('Car'))}\s*(?<carNumber>\d+)\,?\s*{$this->opt($this->t('Seat'))}\s*(?<seat>[\dA-Z]+)/u", $text, $m)) {
            $s->extra()
                ->car($m['carNumber'])
                ->seat($m['seat']);
        }

        $cabin = $this->re("/{$this->opt($this->t('Class:'))}\s*(.+)/", $text);

        if (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }
        $duration = $this->re("/{$this->opt($this->t('Duration:'))}\s*(.+)/", $text);

        if (!empty($duration)) {
            $s->extra()
                ->duration($duration);
        }
    }

    public function ParseHotel(Email $email, $text)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation(str_replace(' ', '', $this->re("/{$this->opt($this->t('N° confirmation:'))}\s*([A-Z\d]{5,}(?:\s*\d{1,5})?)/u", $text)));

        $cancellation = $this->re("/{$this->opt($this->t('CANCELLATION POLICY:'))}(.+){$this->opt($this->t('HOLD POLICY:'))}/su", $text);

        if (empty($cancellation)) {
            $cancellation = $this->re("/({$this->opt($this->t('Free Cancellation:'))}.+)/u", $text);
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation(preg_replace("/(?:\n|\s+)/", " ", $cancellation));
        }

        if (preg_match("/^\s*(?<checkIn>\d+\.\d+\.\d{4})\s*(?<hotelName>.+)\n+\s*(?<checkOut>\d+\.\d+\.\d{4})\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $text, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address(preg_replace("/\s+/", " ", $this->re("/{$this->opt($this->t('Hotel address:'))}\n(.+(?:.*\n){1,3})\s*{$this->opt($this->t('Telephone:'))}/iu", $text)));

            $phone = $this->re("/{$this->opt($this->t('Telephone:'))}\s*([\d\-\(\)\+\s\/]+)/", $text);

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);
            }

            $fax = $this->re("/{$this->opt($this->t('Fax:'))}\s*([\d\-\(\)\+\s\/]+)/", $text);

            if (!empty($fax)) {
                $h->hotel()
                    ->fax($fax);
            }

            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']));

            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }
        $this->detectDeadLine($h);

        $rate = $this->re("/{$this->opt($this->t('Rate'))}\s*(.+)/", $text);
        $roomDescription = $this->re("/{$this->opt($this->t('Room Description:'))}\s*(.+)/", $text);

        if (!empty($rate) || !empty($roomDescription)) {
            $room = $h->addRoom();

            if (!empty($rate)) {
                $room->setRate(trim($rate, ':') . '/night');
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }
        }
    }

    public function ParseFlight(Email $email, $flightArray)
    {
        $tickets = [];

        foreach ($flightArray as $text) {
            if (!isset($f)) {
                $f = $email->add()->flight();
            }

            $priceText = $this->re("/^.+\n.+\s([\d\,\.]+\s*[A-Z]{3})\n/u", $text);

            if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/u", $priceText, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);

                $tax = $this->re("/^.+\n.+\s[\d\,\.]+\s*[A-Z]{3}\n.+\s+[A-Z]{3}\s*([\d\.\,]+)\n/u", $text);

                if (empty($tax)) {
                    $tax = $this->re("/^.+\n.+\s[\d\,\.]+\s*[A-Z]{3}\n.+\s+([\d\.\,]+)\s*[A-Z]{3}\n/u", $text);
                }

                if (!empty($tax)) {
                    $f->price()
                        ->fee('fee', PriceHelper::parse($tax, $m['currency']));
                }
            }

            $s = $f->addSegment();

            $s->airline()
                ->name($this->re("/{$this->opt($this->t('Flight:'))}\s*([A-Z\d]{2})/", $text))
                ->number($this->re("/{$this->opt($this->t('Flight:'))}\s*[A-Z\d]{2}\s+(\d{2,4})/", $text));

            $status = trim($this->re("/{$this->opt($this->t('Booking Status:'))}.*\s*\n {0,10}([A-Z](?: ?[A-Z\d])+?)(?:[ ]{10,}|\n)/", $text));

            if (!empty($status)) {
                $s->setStatus($status);
            }

            $operator = trim($this->re("/{$this->opt($this->t('Operated by:'))}.+\n\s*([A-Z\d\s]+)[ ]{10,}/", $text));

            if (!empty($operator) && stripos($operator, 'UNDEFINED') !== 0) {
                $s->airline()
                    ->operator($operator);
            }

            $duration = $this->re("/{$this->opt($this->t('Flight duration:'))}\s*([\d\:]+\s*\S+)/", $text);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $classText = $this->re("/{$this->opt($this->t('Class:'))}\s*(.+)/", $text);

            if (preg_match("/^\s*(?<bookingCode>[A-Z])[\s\-]+(?<cabin>\w+)/u", $classText, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }

            if (preg_match("/\s*(?<depDate>\d+\.\d+\.\d{4}\s*[\d\:]+).*\((?<depCode>[A-Z]{3})\)(?:[\s\-]+{$this->opt($this->t('TERMINAL'))}\:?\s*(?<depTerminal>.+))?\n\s*(?<arrDate>\d+\.\d+\.\d{4}\s*[\d\:]+)\s.*\n*.*\((?<arrCode>[A-Z]{3})\)[\-\s]+(?:{$this->opt($this->t('TERMINAL'))}\:?\s*(?<arrTerminal>[A-Z\d\s\-]+))?\s*(?:[ ]{2,}\d+[\.\,]+|\-\-|\(|\n)/iu", $text, $m)) {
                $s->departure()
                    ->date(strtotime(preg_replace("/\s+/", " ", $m['depDate'])))
                    ->code($m['depCode']);

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }

                $s->arrival()
                    ->date(strtotime(preg_replace("/\s+/", " ", $m['arrDate'])))
                    ->code($m['arrCode']);

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $seat = $this->re("/{$this->opt($this->t('Seat Reservation:'))}\s*(\d{1,2}[A-Z])(?: {5,}|\n)/", $text);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }
            $ticket = $this->re("/{$this->opt($this->t('E-ticket number:'))}\s*(?<ticket>[\d\-]+)\n/", $text);

            if (!empty($ticket)) {
                $tickets[] = $ticket;
            }
        }

        if (isset($f)) {
            if (isset($tickets) && count($tickets) > 0) {
                $f->setTicketNumbers(array_filter(array_unique($tickets)), false);
            }

            $conf = $this->re("/{$this->opt($this->t('Airline Booking Ref:'))}\s*([A-Z\d]{5,})/", $text);

            if (empty($conf)) {
                $conf = $this->re("/(?:\n *| {3,}).*?{$this->opt($this->t('booking reference'))} ([A-Z\d]{5,7})\n/", $text);
            }
            $this->logger->debug('$text = ' . print_r($text, true));
            $f->general()
                ->confirmation($conf);
        }
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['bcd', 'amextravel', 'airbus'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    public function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false, $deleteFirst = true): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($deleteFirst === false) {
                $result[] = array_shift($textFragments);
            } else {
                array_shift($textFragments);
            }

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
        // you can cancel or modify your booking free of charge by 3PM, 24 hours prior to your arrival
        if (preg_match('/ANNULATION SANS FRAIS JUSQU\'AU JOUR DE L\'ARRI  VEE\, (?<hours>[\d\:]+) \(HEURE LOCALE\)/',
                $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('1 day', $m['hours']);
        }

        if (preg_match('/^(\d+\.\d+\.\d{4}\s*[\d\:]+)$/',
            $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Free Cancellation:'))}\s*(\d+\.\d+\.\d{4}\s*[\d\:]+)/",
            $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        }
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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
}
