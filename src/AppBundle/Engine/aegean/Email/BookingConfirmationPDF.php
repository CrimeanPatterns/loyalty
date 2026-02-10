<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "aegean/it-11137899.eml, aegean/it-167562708.eml, aegean/it-168881560.eml, aegean/it-170121560.eml, aegean/it-6891784.eml, aegean/it-8036347.eml";

    protected $langDetectors = [
        'en' => ['Booking Reference'],
        'de' => ['Buchungskode'],
        'fr' => ['Code de réservation'],
        'it' => ['Codice prenotazione'],
        'es' => ['Código de reserva'],
        'el' => ['Κωδικός Κράτησης'],
        'ru' => ['Номер бронирования'],
    ];

    protected $lang = '';

    protected static $dict = [
        'en' => [
            //			"Booking Reference" => "",
            "OUTBOUND"     => ["OUTBOUND", "INBOUND"],
            "splitSegment" => "/^[ ]*[^\d ]{2,} \d{1,2}[. ]*[^\d ]{3,} \d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi",
            "Nonstop"      => "Non[- ]*stop",
            //			"Operated by" => "",
            //            "Aircraft:"       => "",
            //			"terminal" => "",
            //			"Booking class" => "",
            //			"Cabin Class" => "",
            "PASSENGERS" => ["PASSENGERS", "Passengers"],
            "nameRe"     => "(?:Mr |Ms |Mrs )",
            //			"Ticket number" => "",
            //			"FF number" => "",
            "CONTACT" => ["CONTACT", "Contact"],
            //			"PRICE SUMMARY" => "",
            //			"FLIGHT" => "",
            //			"Taxes" => "",
            //			"TOTAL" => "",
            //			"Useful links" => "",
            //			"Please find below the selected seats" => "",
            //			"to" => "",
        ],
        'de' => [
            "Booking Reference" => "Buchungskode",
            "OUTBOUND"          => "Hinflug",
            "splitSegment"      => "/^[ ]*[^\d ]{2,} \d{1,2}[. ]*[^\d ]{3,} \d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi",
            "Nonstop"           => "Nonstopp",
            "Operated by"       => ["durchgeführt von", "Durchgeführt von"],
            "Aircraft:"         => ["Flugzeug:", "Aircraft:"],
            //			"terminal" => "",
            "Booking class" => "Buchungsklasse",
            //			"Cabin Class" => "",
            "PASSENGERS"                           => "PASSAGIERE",
            "nameRe"                               => "(?:Herr |Frau )",
            "Ticket number"                        => "Ticketnummer",
            "FF number"                            => "Vielfliegernummer",
            "CONTACT"                              => "KONTAKT",
            "PRICE SUMMARY"                        => "PREISÜBERSICHT",
            "FLIGHT"                               => "Flug",
            "Taxes"                                => "Steuern",
            "TOTAL"                                => "Gesamt",
            "Useful links"                         => "Nützliche Links",
            "Please find below the selected seats" => "Nachstehend sehen Sie die gewählten Sitzplätze",
            "to"                                   => "nach",
            //			"" => "",
        ],
        'fr' => [
            "Booking Reference" => "Code de réservation",
            "OUTBOUND"          => "Vol aller",
            "splitSegment"      => "/^[ ]*[^\d ]{2,} \d{1,2}[. ]*[^\d ]{3,}[. ]*\d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi",
            //			"Nonstop" => "",
            "Operated by" => "opéré par",
            //            "Aircraft:"       => "",
            //			"terminal" => "",
            "Booking class" => "Classe de réservation",
            //			"Cabin Class" => "",
            "PASSENGERS"    => "PASSAGERS",
            "nameRe"        => "(?:M\. )",
            "Ticket number" => "Numero de billet",
            "FF number"     => "Numéro de Passager Fréquent",
            "CONTACT"       => "CONTACTER",
            "PRICE SUMMARY" => "RÉCAPITULATIF DU PRIX",
            //			"FLIGHT" => "",
            "Taxes"                                => "Taxes",
            "TOTAL"                                => "TOTAL",
            "Useful links"                         => "Liens utiles",
            "Please find below the selected seats" => "Ci-dessous, vous trouverez les places sélectionnées.",
            //			"to" => "à",
            //			"" => "",
        ],
        'it' => [
            "Booking Reference" => "Codice prenotazione",
            "OUTBOUND"          => "VOLO DI ANDATA",
            "splitSegment"      => "/^[ ]*[^\d ]{2,} \d{1,2}[. ]*[^\d ]{3,}[. ]*\d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi",
            //			"Nonstop" => "",
            "Operated by"     => "operato da",
            "Aircraft:"       => "Aircraft:",
            //			"terminal" => "",
            "Booking class" => "Classe di prenotazione",
            //			"Cabin Class" => "",
            "PASSENGERS"    => "PASSEGGERI",
            "nameRe"        => "(?:Sig\.ra |Sig\.)",
            "Ticket number" => "Numero del biglietto",
            "FF number"     => "Numero FF",
            "CONTACT"       => "CONTATTA",
            "PRICE SUMMARY" => "RIEPILOGO DEL PREZZO",
            "FLIGHT"        => "VOLO",
            "Taxes"         => "Tassa",
            "TOTAL"         => "TOTALE",
            "Useful links"  => "Link utili",
            //			"Please find below the selected seats" => "",
            //			"to" => "",
            //			"" => "",
        ],
        'es' => [
            "Booking Reference" => "Código de reserva",
            "OUTBOUND"          => "Vuelo de ida",
            "splitSegment"      => "/^[ ]*[^\d ]{2,} \d{1,2}[. ]*[^\d ]{3,}[. ]*\d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi",
            "Nonstop"           => "Sin escalas",
            "Operated by"       => "operado por",
            "Aircraft:"         => "Aeronave:",
            //			"terminal" => "",
            "Booking class" => "Clase de reserva",
            //			"Cabin Class" => "",
            "PASSENGERS"                           => "PASAJEROS",
            "nameRe"                               => "(?:Sra\. |Señor )",
            "Ticket number"                        => "Billete electrónico",
            "FF number"                            => "Número de Viajero Frecuente",
            "CONTACT"                              => "CONTACTO",
            "PRICE SUMMARY"                        => ["RESUMEN DE PRECIOS", "Resumen de precios"],
            "FLIGHT"                               => "VUELO",
            "Taxes"                                => "Impuestos",
            "TOTAL"                                => "TOTAL",
            "Useful links"                         => "Enlaces de interés",
            "Please find below the selected seats" => "A continuación encontrará los asientos seleccionados",
            "to"                                   => "Hacia",
            //			"" => "",
        ],
        'el' => [
            "Booking Reference" => "Κωδικός Κράτησης",
            "OUTBOUND"          => ["Αναχώρηση", "Επιστροφή"],
            "splitSegment"      => "/^[ ]*[^\d ]{2,} \d{1,2}[. ]*[^\d ]{3,}[. ]*\d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi",
            "Nonstop"           => "Απευθείας",
            "Operated by"       => " Πτήση με",
            "Aircraft:"         => "Αεροσκάφος:",
            //			"terminal" => "",
            "Booking class" => "Ναύλος",
            "Cabin Class"   => "Cabin Class",
            "PASSENGERS"    => ["ΕΠΙΒΆΤΕΣ", "Επιβάτες"],
            "nameRe"        => "(?:Κα |Κος |Άρρεν )",
            "Ticket number" => "Αριθμός Εισιτηρίου",
            "FF number"     => "Αριθμός Τακτικού Επιβάτη",
            "CONTACT"       => "ΕΠΙΚΟΙΝΩΝΊΑ",
            "PRICE SUMMARY" => ["ΠΛΗΡΟΦΟΡΊΕΣ ΠΛΗΡΩΜΉΣ", "Πληροφορίες πληρωμής"],
            "FLIGHT"        => "ΠΤΉΣΗ",
            "Taxes"         => "Impuestos",
            "TOTAL"         => "Σύνολο",
            "Useful links"  => "Χρήσιμοι Σύνδεσμοι",
            //			"Please find below the selected seats" => "",
            //			"to" => "",
            //			"" => "",
        ],
        'ru' => [
            "Booking Reference" => "Номер бронирования",
            "OUTBOUND"          => "Рейстуда",
            "splitSegment"      => "/^[ ]*[^\d ]{2,} \d{1,2}[. ]*[^\d ]{3,}[. ]*\d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi",
            "Nonstop"           => "Без посадок",
            "Operated by"       => "Компанией-оператором является",
            "Aircraft:"         => "Воздуш.судно:",
            "terminal"          => "терминал",
            "Booking class"     => "Класс бронирования",
            //			"Cabin Class" => "",
            "PASSENGERS"    => "Пассажиры",
            "nameRe"        => "(?:Г-жа |Г-н )",
            "Ticket number" => "Номер билета",
            "FF number"     => "Номер FF (часто летающего пассажира)",
            "CONTACT"       => "Контакты",
            "PRICE SUMMARY" => "Сводка стоимости",
            "FLIGHT"        => "Рейс",
            //            "Taxes"         => "",
            "TOTAL"                                => "ИТОГО",
            "Useful links"                         => "Полезные ссылки",
            "Please find below the selected seats" => "Ниже приведена информация о выбранных местах",
            "to"                                   => "в",
            //			"" => "",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Skip BookingConfirmation.php
        if ($this->http->XPath->query("//img[contains(@alt,'Aegean Logo') or contains(@alt,'Olympic Logo')]")->length > 0
            // it-11137899.eml
            && $this->http->XPath->query("//img[contains(@src,'ico-passenger.jpg') or contains(@alt,'PASSENGER DETAILS')]/ancestor::h2[1]/following-sibling::strong[contains(text(),'Ticket number')]")->length === 0) {
            return false;
        }
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'Aegean Airlines') === false
                && stripos($textPdf, 'contact Aegean') === false
                && stripos($textPdf, 'Olympic Air') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

        foreach ($parser->searchAttachmentByName('.*') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);
            $this->assignLang($textPdf);

            $this->parsePdf($email, $textPdf);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);
            $this->assignLang($textPdf);
            $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

            if ($it = $this->parsePdf($textPdf)) {
                return [
                    'emailType'  => 'BookingConfirmationPDF' . ucfirst($this->lang),
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                ];
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
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    public function mb_ucfirst($str)
    {
        $result = [];

        foreach ((array) $str as $s) {
            $s = mb_strtolower($s);
            $fc = mb_strtoupper(mb_substr($s, 0, 1));
            $result[] = $fc . mb_substr($s, 1);
        }

        return $result;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parsePdf(Email $email, $textPdf)
    {
        $f = $email->add()->flight();

        // General
        if (preg_match('/' . $this->opt($this->t('Booking Reference')) . '\s*([A-Z\d]{5,})$/m', $textPdf, $matches)) {
            $f->general()
                ->confirmation($matches[1]);
        }

        $textPassengers = $this->sliceText($textPdf, $this->t('PASSENGERS'), $this->t('CONTACT'));

        if (empty($textPassengers)) {
            $textPassengers = $this->sliceText($textPdf, $this->mb_ucfirst($this->t('PASSENGERS')), $this->mb_ucfirst($this->t('CONTACT')));
        }

        preg_match_all('/^[ ]*(' . $this->t("nameRe") . '[-.[:alpha:] ]+)$/mu', $textPassengers, $passengerRowMatches);

        if (empty($passengerRowMatches[1])) {
            preg_match_all('/^[ ]*([-.\w)( ]+\([MF]\)[-.\w)( ]*)$/mu', $textPassengers, $passengerRowMatches);
        }

        if (empty($passengerRowMatches[1])) {
            preg_match_all('/^[ ]*([A-Z][a-z]+(?:mrs|ms|mr|miss|mstr) [A-Z][a-z]+(?: {2,}[A-Z][a-z]+(?:mrs|ms|mr|miss|mstr) [A-Z][a-z]+)?)$/mu', $textPassengers, $passengerRowMatches);
        }

        if (!empty($passengerRowMatches[1])) {
            $passengers = [];

            foreach ($passengerRowMatches[1] as $passengerRow) {
                $passengerRowFragments = preg_split('/[ ]{2,}/', $passengerRow, null, PREG_SPLIT_NO_EMPTY);

                foreach ($passengerRowFragments as $passengerRowFragment) {
                    $passengers[] = trim($passengerRowFragment);
                }
            }
            $passengers = preg_replace("/^\s*" . $this->t("nameRe") . "\s*/", '', $passengers);
            $passengers = preg_replace("/\s*\([A-Z]\)\s*$/", '', $passengers);
            $f->general()
                ->travellers(array_unique($passengers));
        }

        // Issued
        if (preg_match_all('/' . $this->opt($this->t('Ticket number')) . '[ ]+([-A-Z\d\/ ]+\d{6}[-\d\/ ]+)/', $textPassengers, $ticketNumberMatches)) {
            $f->issued()
                ->tickets(array_map('trim', $ticketNumberMatches[1]), false);
        }

        // Program
        if (preg_match_all('/' . $this->opt($this->t('FF number')) . '[ ]+([-A-Z\d\/ ]+\d{6}[-\d\/ ]+)/', $textPassengers, $accountNumberMatches)) {
            $f->program()
                ->accounts(array_map('trim', $accountNumberMatches[1]), false);
        }

        // Segments
        $textDirections = $this->sliceText($textPdf, $this->t('Booking Reference'), $this->t('PASSENGERS'));

        if (empty($textDirections)) {
            $textDirections = $this->sliceText($textPdf, $this->t('Booking Reference'), $this->mb_ucfirst($this->t('PASSENGERS')));
        }

        $directionTexts = $this->splitText($textDirections, '/^[ ]*(?:' . $this->opt($this->t('OUTBOUND')) . '|Bound \d+)\s*$/mi');

        foreach ($directionTexts as $directionText) {
            if (preg_match($this->t('splitSegment'), $directionText)) {
                if (preg_match("/\n\s*\d{1,2}:\d{2}.*\d{1,2}:\d{2}/", $directionText)) {
                    // departure and arrival in different columns
                    $this->parseDirection($f, $directionText);
                } else {
                    // departure and arrival in different rows
                    $this->parseDirection2($f, $directionText);
                }
            }
        }

        $seatsPart = $this->sliceText($textPdf, $this->t('Please find below the selected seats'), $this->t('PRICE SUMMARY'));

        if (empty($seatsPart)) {
            $seatsPart = $this->sliceText($textPdf, $this->t('Please find below the selected seats'), $this->mb_ucfirst($this->t('PRICE SUMMARY')));
        }

        if (empty($seatsPart)) {
            $seatsPart = $this->sliceText($textPdf, $this->t('Please find below the selected seats'), $this->t('Useful links'));
        }

        if (!empty($seatsPart) && count($f->getSegments()) > 0) {
            if (preg_match_all("/(?:\n|\s{2})(?<dep>\S+(?: \S+)*) " . $this->opt($this->t('to')) . " (?<arr>\S+(?: \S+)*) (?<seat>\d{1,3}[A-Z]) *\([[:alpha:] ]+\)/", $seatsPart, $m)) {
                $seats = [];

                foreach ($m['seat'] as $i => $value) {
                    $seats[$m['dep'][$i] . '%-%' . $m['arr'][$i]][] = $value;
                }

                foreach ($f->getSegments() as $s) {
                    foreach ($seats as $r => $seat) {
                        $route = explode('%-%', $r);

                        if (stripos($s->getDepName(), $route[0] ?? 'DepartureName') === 0 && stripos($s->getArrName(), $route[1] ?? 'ArrivalName') === 0) {
                            $s->extra()
                                ->seats($seat);
                        }
                    }
                }
            }
        }

        $priceSummary = $this->sliceText($textPdf, $this->t('PRICE SUMMARY'), $this->t('Useful links'));

        if (empty($priceSummary)) {
            $priceSummary = $this->sliceText($textPdf, $this->mb_ucfirst($this->t('PRICE SUMMARY')), $this->mb_ucfirst($this->t('Useful links')));
        }

        if (preg_match('/^[ ]*' . $this->opt($this->t('PRICE SUMMARY')) . '.*\n(?: {15,}.*\n){0,4} {0,15}' . $this->opt($this->t('FLIGHT')) . '(?:[ ]{2,}|\s*\n)/ui', $priceSummary, $matches)) {
            // if "flight" is not contains, then the price can be only for seats
            if (preg_match('/^[ ]*' . $this->opt($this->t('TOTAL')) . '[ ]{2,}(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)/m',
                $priceSummary, $matches)) {
                $f->price()
                    ->currency($this->currency($matches['currency']))
                    ->total($this->normalizeAmount($matches['amount']));

                if (preg_match('/^[ ]*' . $this->opt($this->t('Taxes')) . '[ ]{2,}' . preg_quote($matches['currency'],
                        '/') . '[ ]*(\d[,.\'\d]*)/m', $priceSummary, $m)) {
                    $f->price()
                        ->tax($this->normalizeAmount($m[1]));
                }
            }
        }

        return $email;
    }

    protected function parseDirection(Flight $f, $directionText)
    {
        $tripSegments = [];

        if (preg_match('/^[ ]*[^\d ]{2,} (\d{1,2}[. ]*[^\d ]{3,})[. ]*\d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi', $directionText, $matches)) {
            $date = $this->normalizeDate($matches[1]);
        }
        $segmentTexts = $this->splitText($directionText, '/^\s*(\d{1,2}:\d{2}(?:[ ]*[ap]m)?\s{2,}\d{1,2}:\d{2}(?:\s*[ap]m)?(?:\s*[+]\s*\d+\s*)?\s{2,}[A-Z\d]{2}\d+)/m', true);

        foreach ($segmentTexts as $segmentText) {
            $s = $f->addSegment();

            if (preg_match('/^[ ]*(\d+:\d+(?:\s*[ap]m)?)\s{2,}(\d+:\d+(?:\s*[ap]m)?)(?:\s*[+]+\s*(\d+)\s*)?\s{2,}([A-Z\d]{2})(\d+)\s*(.*)/m', $segmentText, $matches)) {
                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($date . ', ' . $matches[1]));
                    $s->arrival()
                        ->date(strtotime($date . ', ' . $matches[2]));

                    if (!empty($matches[3]) && !empty($s->getArrDate())) {
                        $s->arrival()
                            ->date(strtotime('+' . $matches[3] . ' days', $s->getArrDate()));
                    }
                }

                $s->airline()
                    ->name($matches[4])
                    ->number($matches[5]);

                if (!empty($matches[6])) {
                    $s->extra()
                        ->aircraft($matches[6]);
                    $seg['Aircraft'] = $matches[6];
                }
            }

            if (preg_match('/\d+:\d+.+?[A-Z\d]{2}\d+\s*.+?\n\s*(\S.+?)\s{3,}(\S.+?)\s{3,}Operated by/ms', $segmentText, $matches)) {
                $s->departure()
                    ->noCode()
                    ->name(trim($matches[1]));
                $s->arrival()
                    ->noCode()
                    ->name(trim($matches[2]));
            }

            // TODO: Parsing airports - unreliable!

            //			$thirdRowFragments = explode('  ', $segmentRowValues[2]);
            //			$thirdRowValues = array_values( array_filter($thirdRowFragments) );
            //			$thirdRowValues = array_map('trim', $thirdRowValues);
            //			if ( preg_match('/^[-)(,.\w\s]{3,}$/u', $thirdRowValues[0]) )
            //				$seg['DepName'] .= ' ' . $thirdRowValues[0];
            //			if ( preg_match('/^[-)(,.\w\s]{2,}$/u', $thirdRowValues[1]) )
            //				$seg['ArrName'] .= ' ' . $thirdRowValues[1];

            if (preg_match('/^[ ]*(' . $this->opt($this->t('terminal')) . ' [\w ]+?)[ ]{2,}(' . $this->opt($this->t('terminal')) . ' \w+)/mi', $segmentText, $matches)) {
                $s->departure()
                    ->terminal($matches[1]);
                $s->arrival()
                    ->terminal($matches[2]);
            }

            if (preg_match('/[ ]{2,}' . $this->opt($this->t('Operated by')) . '[ ]+(.+?)(?:[ ]{2,}|$)/m', $segmentText, $matches)) {
                $s->airline()
                    ->operator($matches[1]);
            }

            // 0h 35 min
            if (preg_match('/[ ]{2,}(\d{1,2}[ ]*h \d{1,3}[ ]*min)$/mi', $segmentText, $matches)) {
                $s->extra()
                    ->duration($matches[1]);
            }

            if (preg_match('/^[ ]*' . $this->opt($this->t('Booking class')) . '[ ]+([A-Z]{1,2})(?:[ ]{2,}|$)/m', $segmentText, $matches)) {
                $s->extra()
                    ->bookingCode($matches[1]);
            }

            if (preg_match('/^[ ]*' . $this->opt($this->t('Cabin Class')) . '[ ]+([A-Z]{1,2})(?:[ ]{2,}|$)/m', $segmentText, $matches)) {
                $s->extra()
                    ->cabin($matches[1]);
            }
        }

        return $tripSegments;
    }

    protected function parseDirection2(Flight $f, $directionText)
    {
        if (preg_match('/^[ ]*[^\d ]{2,} (\d{1,2}[. ]*[^\d ]{3,})[. ]*\d{1,2}:\d{2}(?:[ ]*[ap]m)?/mi', $directionText, $matches)) {
            $date = $this->normalizeDate($matches[1]);
        }
        $segmentTexts = $this->splitText($directionText, '/^\s*(\d{1,2}:\d{2}(?:[ ]*[ap]m)? +.+\n(?:.*\n){2,10}?\n *\d{1,2}:\d{2}(?:\s*[ap]m)? +)/m', true);

        foreach ($segmentTexts as $segmentText) {
            $s = $f->addSegment();
            //   11:10           Athens El. Venizelos
            //
            //                   A3356, Operated by Aegean Airlines
            //    0h 45 min
            //                   Aircraft: Airbus A320
            //
            //   11:55           Santorini
            if (preg_match('/^[ ]*(?<depTime>\d+:\d+(?:\s*[ap]m)?) +(?<depName>.+(?:\n.*)+?)\n\s*(?<dopInfo>(?:(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,5}\b|\d+ ?h *\d+ ?m\s+)(?:.*\n)+?)\s*(?<arrTime>\d+:\d+(?:\s*[ap]m)?) +(?<arrName>.+(?:\n.+)*?)\n\n/', $segmentText, $matches)) {
                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($date . ', ' . $matches['depTime']));
                    $s->arrival()
                        ->date(strtotime($date . ', ' . $matches['arrTime']));
                }

                if (preg_match("/^(.+) " . $this->opt($this->t('terminal')) . " (.+)/i", $matches['depName'], $m)) {
                    $s->departure()
                        ->terminal($m[2]);
                }
                $s->departure()
                    ->noCode()
                    ->name(trim($matches['depName']));

                if (preg_match("/^(.+) " . $this->opt($this->t('terminal')) . " (.+)/i", $matches['arrName'], $m)) {
                    $matches['arrName'] = $m[1];
                    $s->arrival()
                        ->terminal($m[2]);
                }
                $s->arrival()
                    ->noCode()
                    ->name(trim($matches['arrName']));

                if (preg_match('/(?:^|\s{2,})([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5}) *, *(?i)' . $this->opt($this->t('Operated by')) . '[ ]+(.+)/m', $matches['dopInfo'], $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2])
                        ->operator($m[3])
                    ;
                }

                if (preg_match('/\n *(\d{1,2}[ ]*h +\d{1,3}[ ]*min)\s+/', $matches['dopInfo'], $m)) {
                    $s->extra()
                        ->duration($m[1]);
                }

                if (preg_match('/\b' . $this->opt($this->t('Aircraft:')) . '[ ]+(.+)/', $matches['dopInfo'], $m)) {
                    $s->extra()
                        ->aircraft($m[1]);
                }
            }

            if (preg_match('/^[ ]*' . $this->opt($this->t('Booking class')) . '[ ]+([A-Z]{1,2})(?:[ ]{2,}|$)/m', $segmentText, $matches)) {
                $s->extra()
                    ->bookingCode($matches[1]);
            }

            if (preg_match('/^[ ]*' . $this->opt($this->t('Cabin Class')) . '[ ]+([[:alpha:] ]+)(?:[ ]{2,}|$)/m', $segmentText, $matches)) {
                $s->extra()
                    ->cabin($matches[1]);
            }
        }

        return $f;
    }

    protected function sliceText($textSource = '', $textStart = '', $textEnd = '')
    {
        if (empty($textSource) || empty($textStart)) {
            return false;
        }

        if (is_array($textStart)) {
            foreach ($textStart as $ts) {
                $start = mb_strpos($textSource, $ts);

                if ($start !== false) {
                    break;
                }
            }
        } else {
            $start = mb_strpos($textSource, $textStart);
        }

        if ($start === false) {
            return false;
        }

        if (empty($textEnd)) {
            return mb_substr($textSource, $start);
        }

        if (is_array($textEnd)) {
            foreach ($textEnd as $te) {
                $end = mb_strpos($textSource, $te, $start);

                if ($end !== false) {
                    break;
                }
            }
        } else {
            $end = mb_strpos($textSource, $textEnd, $start);
        }

        if ($end === false) {
            return false;
        }

        return mb_substr($textSource, $start, $end - $start);
    }

    protected function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
        }

        return $result;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})[.\s]+([^.\d\s]{3,})[.]*$/', $string, $matches)) { // 11.Oct
            $day = $matches[1];
            $month = $matches[2];
            $year = $this->year;
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }

    protected function assignLang($textPdf)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($textPdf, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
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
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
