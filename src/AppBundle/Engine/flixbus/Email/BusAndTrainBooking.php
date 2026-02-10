<?php

namespace AwardWallet\Engine\flixbus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BusAndTrainBooking extends \TAccountChecker
{
    public $mailFiles = "flixbus/it-280998655.eml, flixbus/it-281636133.eml, flixbus/it-281738953.eml, flixbus/it-277945330.eml, flixbus/it-290719231-greyhound.eml, flixbus/it-292789996-greyhound.eml, flixbus/it-292132179-greyhound.eml, flixbus/it-410893178-de.eml, flixbus/it-409324988-fr.eml, flixbus/it-406218122-es.eml, flixbus/it-407511133-pt.eml, flixbus/it-400108632-it.eml, flixbus/it-407487568-ru.eml, flixbus/it-633656310-ro.eml, flixbus/it-636211666-pl.eml, flixbus/it-634161941-hr.eml";

    public $lang = '';

    public static $dictionary = [
        'ru' => [
            'confNumber'          => ['Номер бронирования'],
            'statusPhrases'       => ['Бронирование'],
            'statusVariants'      => ['подтверждено'],
            'Passengers & Extras' => ['Пассажиры и дополнительные услуги'],
            'travellerHeaders'    => ['Взрослых', 'Взрослые', 'Детей', 'Дети'],
            // 'Route' => '',
            'Bus'                 => ['Автобус'],
            // 'Train' => [''],
            'seat'             => ['Места'],
            // 'Coach' => [''],
            'badRows'          => ['Отправление', 'Прибытие', 'Время', 'Станция'],
            'Total price'      => ['Всего'],
        ],
        'it' => [
            'confNumber'          => ['Prenotazione n.'],
            'statusPhrases'       => ['Il viaggio è stato', 'La tua prenotazione è', 'Le modifiche sono state apportate con'],
            'statusVariants'      => ['confermata', 'successo', 'modificato'],
            'Passengers & Extras' => ['Passeggeri/e ed extra'],
            'travellerHeaders'    => ['Adulti', 'Adulto'],
            'Route'               => 'Tratta',
            'Bus'                 => ['Autobus'],
            // 'Train' => [''],
            'seat'             => ['Posti a sedere'],
            // 'Coach' => [''],
            'badRows'          => ['Partenza', 'Arrivo', 'Orario', 'Stazione'],
            'Total price'      => ['Tariffa totale'],
        ],
        'pt' => [
            'confNumber'          => ['Nº de reserva'],
            'statusPhrases'       => ['Alterações feitas com', 'Sua viagem foi', 'Reserva'],
            'statusVariants'      => ['confirmada', 'modificada'],
            'Passengers & Extras' => ['Passageiros e Extras'],
            'travellerHeaders'    => ['Adultos', 'Adulto', 'Crianças', 'Criança'],
            'Route'               => 'Rota',
            'Bus'                 => ['Autocarro', 'Ônibus'],
            // 'Train' => [''],
            'seat'             => ['Lugares', 'Assentos'],
            // 'Coach' => [''],
            'badRows'          => ['Partida', 'Chegada', 'Hora', 'Estação'],
            'Total price'      => ['Preço total'],
        ],
        'es' => [
            'confNumber'          => ['Nº de reserva'],
            'statusPhrases'       => ['Tu reserva está'],
            'statusVariants'      => ['confirmada'],
            'Passengers & Extras' => ['Pasajeros y Extras'],
            'travellerHeaders'    => ['Adultos', 'Adulto', 'Niños'],
            'Route'               => 'Ruta',
            'Bus'                 => ['Autobús'],
            // 'Train' => [''],
            'seat'             => ['Asientos'],
            // 'Coach' => [''],
            'badRows'          => ['Salida', 'Llegada', 'Hora', 'Estación', 'Nueva estación:'],
            'Total price'      => ['Precio total'],
        ],
        'fr' => [
            'confNumber'          => ['N° de réservation'],
            'statusPhrases'       => ['Votre réservation est'],
            'statusVariants'      => ['confirmée'],
            'Passengers & Extras' => ['Passagers et extras'],
            'travellerHeaders'    => ['Adultes', 'Adulte', 'Enfants'],
            'Route'               => 'Ligne',
            // 'Bus' => [''],
            // 'Train' => [''],
            'seat'             => ['Sièges'],
            'Coach'            => ['Car'],
            'badRows'          => ['Départ', 'Arrivée', 'Heure', 'Station'],
            'Total price'      => ['Prix total'],
        ],
        'de' => [
            'confNumber'          => ['Buchungsnummer'],
            'statusPhrases'       => ['Deine Buchung ist', 'Deine Fahrt wurde', 'Änderungen'],
            'statusVariants'      => ['bestätigt', 'geändert'],
            'Passengers & Extras' => ['Fahrgäste & Extras'],
            'travellerHeaders'    => ['Erwachsene', 'Erwachsener', 'Kinder', 'Kind'],
            'Route'               => 'Strecke',
            // 'Bus' => [''],
            'Train'            => ['Zug'],
            'seat'             => ['Sitze'],
            'Coach'            => ['Wagen'],
            'badRows'          => ['Abfahrt', 'Ankunft', 'Uhrzeit', 'Haltestelle'],
            'Total price'      => ['Gesamtpreis'],
        ],
        'ro' => [
            'confNumber'          => ['Numărul rezervării'],
            'statusPhrases'       => ['Rezervarea este'],
            'statusVariants'      => ['confirmată'],
            'Passengers & Extras' => ['Pasageri și suplimente'],
            'travellerHeaders'    => ['Tineri'],
            'Route'               => 'Traseu',
            // 'Bus' => [''],
            // 'Train' => [''],
            'seat'             => ['Locuri'],
            // 'Coach' => [''],
            'badRows'          => ['Plecare', 'Sosire', 'Oră', 'Stație'],
            'Total price'      => ['Preț total', 'Pre total'],
        ],
        'pl' => [
            'confNumber'          => ['Numer rezerwacji'],
            'statusPhrases'       => ['Twoja rezerwacja została'],
            'statusVariants'      => ['potwierdzona'],
            'Passengers & Extras' => ['Pasażerowie i dodatkowe opcje'],
            'travellerHeaders'    => ['Dorosły'],
            'Route'               => 'Linia',
            // 'Bus' => [''],
            // 'Train' => [''],
            'seat'             => ['Miejsca'],
            // 'Coach' => [''],
            'badRows'          => ['Odjazd', 'Przyjazd', 'Godziny', 'Przystanek'],
            'Total price'      => ['Łącznie', 'Ł cznie'],
        ],
        'hr' => [
            'confNumber'          => ['Broj rezervacije'],
            'statusPhrases'       => ['Tvoja rezervacija je'],
            'statusVariants'      => ['potvrđena'],
            'Passengers & Extras' => ['Putnici i dodaci'],
            'travellerHeaders'    => ['Odrasla osoba', 'Odrasli'],
            'Route'               => 'Ruta',
            'Bus'                 => ['Autobus'],
            // 'Train' => [''],
            'seat'             => ['Sjedala'],
            // 'Coach' => [''],
            'badRows'          => ['Polazak', 'Dolazak', 'Vrijeme', 'Stanica'],
            'Total price'      => ['Ukupna cijena'],
        ],
        'en' => [
            'confNumber'       => ['Booking Number'],
            'statusPhrases'    => ['Your booking is', 'Your trip has', 'Your changes were'],
            'statusVariants'   => ['confirmed', 'changed'],
            // 'Passengers & Extras' => [''],
            'travellerHeaders' => ['Adults', 'Adult', 'Child', 'Children'],
            // 'Route' => '',
            'Bus'              => ['Bus'],
            // 'Train' => [''],
            'seat'             => ['Seat', 'Seats'],
            // 'Coach' => [''],
            'badRows'          => ['Boarding', 'Departure', 'Arrival', 'Time', 'Station', 'New station:', 'Bus stop'],
            // 'Total price' => [''],
        ],
    ];

    private $subjects = [
        'ru' => ['Подтверждение бронирования #'],
        'it' => ['Conferma della prenotazione #', 'Il tuo nuovo numero di prenotazione #'],
        'pt' => ['Confirmação de reserva:', 'Sua nova reserva:'],
        'es' => ['Confirmación de reserva:'],
        'fr' => ['Confirmation de réservation #'],
        'de' => ['Buchungsbestätigung #', 'Deine neue Buchung #'],
        'ro' => ['Confirmarea rezervării #'],
        'pl' => ['Potwierdzenie rezerwacji #'],
        'hr' => ['Potvrda rezervacije #'],
        'en' => ['Booking Confirmation #', 'Your New Booking #'],
    ];

    private $detectors = [
        'ru' => ['Ниже приведено краткое описание предстоящих поездок'],
        'it' => [
            'I dettagli del nuovo viaggio sono riportati di seguito',
            'Di seguito sono riportate le modifiche',
            'Di seguito puoi trovare un riepilogo del/dei tuo/tuoi prossimo/i viaggio',
        ],
        'pt' => [
            'Encontre abaixo os detalhes de sua nova viagem',
            'Segue um resumo da(s) tua(s) próxima(s) viagem',
            'Segue um breve resumo de sua(s) próxima(s) viagem',
            'Abaixo, você verá a(s) modificação(ões) e a nova passagem',
        ],
        'es' => ['A continuación encontrarás un breve resumen de tu'],
        'fr' => ['Vous trouverez ci-dessous un bref aperçu de votre/vos prochain'],
        'de' => [
            'Unten findest Du die Änderung(en) und das neue Ticket',
            'Hier ist eine Übersicht Deiner bevorstehenden Fahrt',
            'Unten findest du neue Fahrtdetails',
        ],
        'ro' => ['Mai jos ai un rezumat rapid al călătoriilor următoare'],
        'pl' => ['Poniżej znajduje się krótkie podsumowanie Twojej podróży'],
        'hr' => ['Ispod je kratak sažetak tvojih nadolazećih putovanja'],
        'en' => [
            'You can find your new trip details below',
            'Below is a quick summary of your upcoming trip',
            'Below you will see the modification(s) and the new ticket',
        ],
    ];

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.flixbus.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers)) {
            $headers['from'] = '';
        }

        if (!array_key_exists('subject', $headers)) {
            $headers['subject'] = '';
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Greyhound') === false
            && strpos($headers['subject'], 'FlixBus') === false
        ) {
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
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format and Language
        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BusAndTrainBooking' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $this->parseHtml($email);

        if (count($email->getItineraries()) === 0) {
            return $email;
        }

        /* price (from PDF) */

        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (preg_match("/^[ ]*[^\w\s]?[ ]*{$this->opt($this->t('Total price'))}[ ]*:/imu", $textPdf) > 0) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        if (preg_match_all("/^[ ]*[^\w\s]?[ ]*{$this->opt($this->t('Total price'))}[ ]*[:]+[ ]{0,6}(\S.*?)(?:[ ]{2}|$)/imu", $textPdfFull, $totalPriceMatches)
            && count($totalPriceMatches[1]) === 1
            && (
                preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPriceMatches[1][0], $matches)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPriceMatches[1][0], $matches)
            )
        ) {
            // USD 89.96    |    49,98 EUR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

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
        return ['greyhound', 'flixbus'];
    }

    private function parseHtml(Email $email): void
    {
        $xpathTime = 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';
        $patterns = [
            // Dienstag, 15. Aug. 2023    |    Tuesday, Feb 07, 2023    |    lunes, 19 de jun de 2023
            // пятница, 14 апр. 2023 г.    |    subota, 24. lip 2023.
            'date' => '[-[:alpha:]]+[,.\s]+(?:\d{1,2}[,.\s]+(?:de\s+)?[[:alpha:]]+|[[:alpha:]]+[,.\s]+\d{1,2})[,.\s]+(?:de\s+)?\d{4}(?:\s*г)?[\s.]*',
            // 4:19PM    |    2:00 p. m.    |    17 h 15
            'time' => '\d{1,2}(?:[ ]{0,2}[Hh][ ]{0,2}|[:：])\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        ];

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]", null, true, '/^[A-Z\d][-A-Z\d\s]{3,}[A-Z\d]$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation(str_replace(' ', '', $confirmation), $confirmationTitle);
        }

        $travellers = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Passengers & Extras'))}]/following::tr[count(*)=2 and *[1]/descendant::img]/*[2][normalize-space()]/descendant::tr[not(.//tr) and normalize-space() and not({$this->starts($this->t('travellerHeaders'))})]", null, "/^(?:\([^)(]+\)\s*)?([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[-+\s\d]*$/u"));

        $dateVal = '';
        $dateLast = 0;

        $segments = $this->http->XPath->query($log = "//tr[ count(*)=2 and *[1]/descendant::img and *[2][{$this->starts($this->t('Route'))} or starts-with(normalize-space(),'Bus') or {$this->starts($this->t('Bus'))} or starts-with(normalize-space(),'Train') or {$this->starts($this->t('Train'))}] ]");
        $this->logger->debug($log);

        foreach ($segments as $key => $root) {
            $segType = null;

            $iconUrl = $this->http->FindSingleNode("*[1]/descendant::img[@src]/@src", $root);
            $content = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?:Bus|{$this->opt($this->t('Bus'))})\s.+/", $content, $m)
                || preg_match("/\bBUS\b[^\/]*$/i", $iconUrl)
            ) {
                $segType = 'bus';

                if (isset($bus)) {
                    $s = $bus->addSegment();
                } else {
                    $bus = $email->add()->bus();
                    $bus->general()->noConfirmation();

                    if ($status) {
                        $bus->general()->status($status);
                    }

                    $bus->general()->travellers($travellers, true);
                    $s = $bus->addSegment();
                }
            } elseif (preg_match("/^(?:Train|{$this->opt($this->t('Train'))})\s.+/", $content, $m)
                || preg_match("/\bTRAIN\b[^\/]*$/i", $iconUrl)
            ) {
                $segType = 'train';

                if (isset($train)) {
                    $s = $train->addSegment();
                } else {
                    $train = $email->add()->train();
                    $train->general()->noConfirmation();

                    if ($status) {
                        $train->general()->status($status);
                    }

                    $train->general()->travellers($travellers, true);
                    $s = $train->addSegment();
                }
            }

            if ($segType === null) {
                $this->logger->debug("Unknown segment-{$key} type!");
                $email->add()->hotel(); // for 100% fail

                return;
            }

            $number = $this->re("/^(?:{$this->opt($this->t('Route'))}|Bus|{$this->opt($this->t('Bus'))}|Train|{$this->opt($this->t('Train'))})\s+([A-z\d]+)(?: |$)/i", $content);
            $s->extra()->number($number);

            $seatsText = implode("\n", $this->http->FindNodes("ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if ($segType === 'bus'
                && preg_match("/^{$this->opt($this->t('seat'))}\s*[:]+\s*(\S.*)/", $seatsText, $m)
                && $m[1] !== '-'
            ) {
                $s->extra()->seats(preg_split('/(\s*,\s*)+/', $m[1]));
            }

            if ($segType === 'train'
                && preg_match("/^{$this->opt($this->t('Coach'))}\s*[:]+\s*(\S.*)/", $seatsText, $m)
                && $m[1] !== '-'
            ) {
                $s->extra()->car($m[1]);
            }

            if ($segType === 'train'
                && preg_match("/(?:^|\n){$this->opt($this->t('seat'))}\s*[:]+\s*(\S.*)/", $seatsText, $m)
                && $m[1] !== '-'
            ) {
                $s->extra()->seats(preg_split('/(\s*,\s*)+/', $m[1]));
            }

            $xpathDep = "ancestor::tr[ preceding-sibling::tr[{$xpathTime}] ][1]/preceding-sibling::tr[{$xpathTime}][1]";

            $dateNew = $this->http->FindSingleNode($xpathDep . "/ancestor::table[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]", $root, true, "/^{$patterns['date']}$/iu");

            if ($dateNew) {
                $dateVal = $dateNew;
                $dateLast = strtotime($dateVal . ' -1 days');
            }

            $xpathBadRows = "{$this->eq($this->t('badRows'))}"; // it-292132179-greyhound.eml

            $departureRows = $this->http->FindNodes($xpathDep . "/descendant-or-self::*[ node()[normalize-space() and not({$xpathBadRows})][2] ][1]/node()[normalize-space() and not({$xpathBadRows})]", $root);
            $arrivalRows = $this->http->FindNodes("ancestor::tr[ following-sibling::tr[{$xpathTime}] ][1]/following-sibling::tr[{$xpathTime}][1]/descendant-or-self::*[ node()[normalize-space() and not({$xpathBadRows})][2] ][1]/node()[normalize-space() and not({$xpathBadRows})]", $root);

            $depTimePosition = count($departureRows) > 1 && preg_match("/^{$patterns['time']}/", $departureRows[1]) > 0 ? 1 : 0;
            $arrTimePosition = count($arrivalRows) > 1 && preg_match("/^{$patterns['time']}/", $arrivalRows[1]) > 0 ? 1 : 0;

            if ($dateVal && count($departureRows) > 0 && preg_match("/^({$patterns['time']})/", $departureRows[$depTimePosition], $m) > 0) {
                $dateDepVal = $this->normalizeDate($depTimePosition > 0 ? $departureRows[$depTimePosition - 1] : $dateVal);
                $dateDep = preg_match('/\b\d{4}\s*$/', $dateDepVal) > 0 ? strtotime($dateDepVal) : EmailDateHelper::parseDateRelative($dateDepVal, $dateLast, true, '%D% %Y%');
                $s->departure()->date(strtotime($this->normalizeTime($m[1]), $dateDep));
                $dateLast = strtotime('-1 days', $dateDep);
            }

            if ($dateVal && count($arrivalRows) > 0 && preg_match("/^({$patterns['time']})/", $arrivalRows[$arrTimePosition], $m) > 0) {
                $dateArrVal = $this->normalizeDate($arrTimePosition > 0 ? $arrivalRows[$arrTimePosition - 1] : $dateVal);
                $dateArr = preg_match('/\b\d{4}\s*$/', $dateArrVal) > 0 ? strtotime($dateArrVal) : EmailDateHelper::parseDateRelative($dateArrVal, $dateLast, true, '%D% %Y%');
                $s->arrival()->date(strtotime($this->normalizeTime($m[1]), $dateArr));
                $dateLast = strtotime('-1 days', $dateArr);
            }

            if (count($departureRows) > $depTimePosition + 2) {
                $nameStationDep = $departureRows[$depTimePosition + 1];
                $addressDep = $departureRows[$depTimePosition + 2];
                // 'Name + Address' for Geoapify
                $s->departure()
                    ->name($nameStationDep)
                    ->address(implode(', ', array_unique([$nameStationDep, $addressDep])));
            }

            if (count($arrivalRows) > $arrTimePosition + 2) {
                $nameStationArr = $arrivalRows[$arrTimePosition + 1];
                $addressArr = $arrivalRows[$arrTimePosition + 2];
                $s->arrival()
                    ->name($nameStationArr)
                    ->address(implode(', ', array_unique([$nameStationArr, $addressArr])));
            }
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignProvider($headers): bool
    {
        if (!array_key_exists('from', $headers)) {
            $headers['from'] = '';
        }

        if (!array_key_exists('subject', $headers)) {
            $headers['subject'] = '';
        }

        if (stripos($headers['from'], '@booking.greyhound.com') !== false
            || strpos($headers['subject'], 'Greyhound') !== false
            || $this->http->XPath->query('//a[contains(@href,".greyhound.com/") or contains(@href,"www.greyhound.com") or contains(@href,"shop.greyhound.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Get the Greyhound App") or contains(normalize-space(),"© Greyhound Lines, Inc")]')->length > 0
        ) {
            $this->providerCode = 'greyhound';

            return true;
        }

        if (stripos($headers['from'], '@booking.flixbus.com') !== false
            || strpos($headers['subject'], 'FlixBus') !== false
            || $this->http->XPath->query('//a[contains(@href,".flixbus.com/") or contains(@href,"help.flixbus.com") or contains(@href,"global.flixbus.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Get the FlixBus App") or contains(normalize-space(),"Copyright | Flix SE | All rights reserved")]')->length > 0
        ) {
            $this->providerCode = 'flixbus';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['statusPhrases'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['statusPhrases'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})[,.\s]+(?:de\s+)?([[:alpha:]]+)[,.\s]+(?:de\s+)?(\d{4})(?:\s*г)?[.\s]*$/iu', $text, $m)) {
            // Tuesday, 15. Feb. 2023    |    lunes, 19 de jun de 2023    |    15. Feb. 2023
            // вторник, 15 фев. 2023 г.    |    subota, 24. lip 2023.
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?([[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // Tuesday, Feb 15, 2023    |    Feb 15, 2023
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})[,.\s]+(?:de\s+)?([[:alpha:]]+)[,.\s]*$/u', $text, $m)) {
            // 15 Feb    |    17 de jun.
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^([[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]*$/u', $text, $m)) {
            // Feb 15
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/([AaPp])\.[ ]*([Mm])\.?/', // 2:04 p. m.    ->    2:04 pm
            '/(\d)[ ]*[Hh][ ]*(\d)/', // 17 h 15    ->    17:15
        ], [
            '$1$2',
            '$1:$2',
        ], $s);

        return $s;
    }
}
