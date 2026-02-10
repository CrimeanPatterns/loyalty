<?php

namespace AwardWallet\Engine\carlson\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It5775645 extends \TAccountChecker
{
    public $mailFiles = "carlson/it-120449392.eml, carlson/it-120671262.eml, carlson/it-34870287.eml, carlson/it-35075258.eml, carlson/it-35100364.eml, carlson/it-469389811.eml, carlson/it-6904321.eml, carlson/it-7060842.eml, carlson/it-7088252.eml, carlson/it-7183962.eml"; // +1 bcdtravel(html)[de]

    public static $dictionary = [
        "pl" => [
            "Reservation Summary/Room Information" => ["Podsumowanie rezerwacji/ informacje o pokoju"],
            // "Your reservation has been cancelled for your stay at" => "",
            "Confirmation:"                                     => ["Potwierdzenie:"],
            //			"Hotel:" => [""],
            "Arrival Date:"      => ["Data przyjazdu:"],
            "Departure Date:"    => ["Data odjazdu:"],
            "Check-In Time:"     => ["Godzina zameldowania:"],
            "Check-Out Time:"    => ["Godzina wymeldowania:"],
            "Guest Name:"        => ["Imię i nazwisko gościa:"],
            //			"Address:" => [""],
            //			"Tel.:" => [""],
            "Cancellation Policy:"   => ["Anulowanie:"],
            "Subtotal:"              => ["Suma częściowa:"],
            // "Total price:" => '',
            // "Estimated Taxes:" => '',
            // "Estimated Additional Fees:" => '',
            "Rate:"                  => ["Stawka:"],
            "Member no"              => ["NUMER CZŁONKOWSKI"],
            // "cancelledPhrases" => "",
            //            "Points" => "",
            //            "Adults:" => "",
            //            "Children:" => "",
            //            "Infants:" => "",
            //            "Room Type:" => "",
        ],
        "de" => [
            "Reservation Summary/Room Information" => [
                "Reservierungszusammenfassung/Zimmerinformationen",
                "Reservierungszusammenfassung/ Zimmerinformationen",
                "RESERVIERUNGSZUSAMMENFASSUNG GARANTIE- UND RESERVIERUNGSRICHTLINIEN DES HOTELS",
                "RESERVIERUNGSZUSAMMENFASSUNGGARANTIE- UND RESERVIERUNGSRICHTLINIEN DES HOTELS",
            ],
            //            "Your reservation has been cancelled for your stay at" => [],
            //            "Hotel:" => "",
            "Confirmation:"              => ["Bestätigung:", "Reservierungsnummer:"],
            "Departure Date:"            => "Abreisedatum:",
            "Arrival Date:"              => "Ankunftsdatum:",
            "Check-In Time:"             => ["Check-in-Zeit:", "Check-in ab:"],
            "Check-Out Time:"            => ["Check-out-Zeit:", "Check-out bis:"],
            "Guest Name:"                => ["Name des Gastes:"],
            "Address:"                   => "Adresse:",
            "Tel.:"                      => ["Tel.:"],
            "Cancellation Policy:"       => ["Stornierung:", "Stornobedingungen:"],
            "Subtotal:"                  => ["Zwischensumme:"],
            "Total price:"               => 'Gesamtpreis:',
            "Estimated Taxes:"           => 'Geschätzte Steuern:',
            "Estimated Additional Fees:" => 'Geschätzte zusätzliche Gebühren:',
            "Rate:"                      => ["Preis:"],
            "Member no"                  => ["Mitgliedsnr."],
            //            "cancelledPhrases" => "",
            "Points"     => "Punkte",
            "Adults:"    => ["Erwachsene:"],
            "Children:"  => "Kinder:",
            "Infants:"   => "Kleinkinder:",
            "Room Type:" => "Zimmerkategorie:",
        ],
        "fr" => [
            "Reservation Summary/Room Information" => [
                "Récapitulatif de la réservation/informations sur la chambre",
                "RÉCAPITULATIF DE LA RÉSERVATION/INFORMATIONS SUR LA CHAMBRE",
                "RÉCAPITULATIF DE RÉSERVATION POLITIQUES DE GARANTIE ET DE RÉSERVATION DE L’HÔTEL",
            ],
            // "Your reservation has been cancelled for your stay at" => "",
            "Confirmation:"              => ["Confirmation :", "Confirmation:", "Numéro de réservation"],
            "Hotel:"                     => ["Hôtel :", "Hôtel:"],
            "Arrival Date:"              => ["Date d’arrivée :", "Date d’arrivée:"],
            "Departure Date:"            => ["Date de départ :", "Date de départ:"],
            "Check-In Time:"             => ["Heure d’arrivée :", "Heure d’arrivée:"],
            "Check-Out Time:"            => ["Heure de départ :", "Heure de départ:"],
            "Guest Name:"                => ["Nom du client :", "Nom du client:", "Nom de l’hôte:"],
            "Address:"                   => ["Adresse :", "Adresse:"],
            "Tel.:"                      => ["Téléphone :", "Téléphone:"],
            "Cancellation Policy:"       => ["Annulation :", "Annulation:", "Politique d’annulation:"],
            "Subtotal:"                  => ["Sous-total :", "Sous-total:"],
            "Total price:"               => 'Prix total :',
            "Estimated Taxes:"           => 'Estimation des taxes :',
            "Estimated Additional Fees:" => 'Estimation des frais supplémentaires :',
            "Rate:"                      => ["Tarif :", "Tarif:"],
            //			"Member no" => [""],
            // "cancelledPhrases" => "",
            //            "Points" => "",
            "Adults:"    => "Adultes:",
            "Children:"  => "Enfants:",
            "Infants:"   => "Nourrissons:",
            "Room Type:" => "Type de chambre:",
        ],
        "da" => [
            "Reservation Summary/Room Information" => [
                "Reservationsoversigt/værelsesoplysninger",
                "RESERVATIONSOVERSIGT/VÆRELSESOPLYSNINGER",
            ],
            // "Your reservation has been cancelled for your stay at" => "",
            "Confirmation:"            => ["Bekræftelse:"],
            "Hotel:"                   => ["Hotel:"],
            "Arrival Date:"            => ["Ankomstdato:"],
            "Departure Date:"          => ["Afrejsedato:"],
            "Check-In Time:"           => ["Indtjekningstidspunkt:"],
            "Check-Out Time:"          => ["Udtjekningstidspunkt:"],
            "Guest Name:"              => ["Gæstens navn:"],
            "Address:"                 => ["Adresse:"],
            "Tel.:"                    => ["Telefon:"],
            "Cancellation Policy:"     => ["Annullering:"],
            "Subtotal:"                => ["Subtotal:"],
            // "Total price:" => '',
            // "Estimated Taxes:" => '',
            // "Estimated Additional Fees:" => '',
            "Rate:"                    => ["Pris:"],
            "Member no"                => ["Medlemsnummer", "MEDLEMSNUMMER"],
            // "cancelledPhrases" => "",
            //            "Points" => "",
            //            "Adults:" => "",
            //            "Children:" => "",
            //            "Infants:" => "",
            //            "Room Type:" => "",
        ],
        "it" => [
            "Reservation Summary/Room Information" => ["RIEPILOGO DELLA PRENOTAZIONE NORME SULLA GARANZIA E PRENOTAZIONE"],
            // "Your reservation has been cancelled for your stay at" => "",
            "Confirmation:"              => ["Conferma:", "Numero di prenotazione:"],
            "Hotel:"                     => ["Hotel:"],
            "Arrival Date:"              => ["Data di arrivo:"],
            "Departure Date:"            => ["Data di partenza:"],
            "Check-In Time:"             => ["Orario check-in:", "Ora di check-in:"],
            "Check-Out Time:"            => ["Orario check-out:", "Ora di check-out:"],
            "Guest Name:"                => ["Nome ospite:", 'Nome dell’ospite:'],
            "Address:"                   => ["Indirizzo:"],
            "Tel.:"                      => ["Telefono:"],
            "Cancellation Policy:"       => ["Cancellazione:", 'Termini di cancellazione:'],
            "Subtotal:"                  => ["Subtotale:"],
            "Total price:"               => 'Prezzo totale:',
            "Estimated Taxes:"           => 'Tasse stimate:',
            "Estimated Additional Fees:" => 'Supplementi stimati:',
            "Rate:"                      => ["Tariffa:"],
            //			"Member no" => [""],
            // "cancelledPhrases" => "",
            //            "Points" => "",
            "Adults:"    => "Adulti:",
            "Children:"  => "Figli:",
            "Infants:"   => "Neonati:",
            "Room Type:" => "Tipo di camera:",
        ],
        "nl" => [
            "Reservation Summary/Room Information"                         => ["RESERVERINGSOVERZICHT GARANTIE- EN RESERVERINGSBELEID HOTEL"],
            "Your reservation has been cancelled for your stay at"         => "Het doet ons genoegen uw reservering in",
            "Confirmation:"                                                => ["Bevestiging:", "Reserveringsnummer:"],
            "Hotel:"                                                       => ["Hotel:"],
            "Arrival Date:"                                                => ["Aankomstdatum:"],
            "Departure Date:"                                              => ["Vertrekdatum:"],
            "Check-In Time:"                                               => ["Tijdstip van inchecken:"],
            "Check-Out Time:"                                              => ["Tijdstip van uitchecken:"],
            "Guest Name:"                                                  => ["Naam gast:", 'Naam van de gast:'],
            "Address:"                                                     => ["Adres:"],
            "Tel.:"                                                        => ["Telefoon:"],
            "Cancellation Policy:"                                         => ["Annulering:", 'Annuleringsbeleid:'],
            "Subtotal:"                                                    => ["Subtotaal:"],
            "Total price:"                                                 => 'Totale prijs:',
            "Estimated Taxes:"                                             => 'Geschatte belasting:',
            "Estimated Additional Fees:"                                   => 'Geschatte toeslagen:',
            "Rate:"                                                        => ["Tarief:"],
            //			"Member no" => [""],
            // "cancelledPhrases" => "",
            //            "Points" => "",
            "Adults:"    => "Volwassenen:",
            "Children:"  => "Kinderen:",
            "Infants:"   => "Zuigelingen:",
            "Room Type:" => "Kamertype:",
        ],
        "en" => [
            "Reservation Summary/Room Information" => [
                "Reservation Summary/Room Information",
                "RESERVATION SUMMARY/ROOM INFORMATION",
                "RESERVATION SUMMARY HOTEL GUARANTEE & RESERVATION POLICIES",
                "RESERVATION SUMMARYHOTEL GUARANTEE & RESERVATION POLICIES",
                "Reservation summary / room information",
            ],
            "Your reservation has been cancelled for your stay at" => [
                "Your reservation has been cancelled for your stay at",
                "We are pleased to confirm your reservation at",
            ],
            "Confirmation:"        => ["Confirmation:", "Reservation Number:", "de miembro"],
            "Hotel:"               => "Hotel:",
            "Arrival Date:"        => ["Arrival Date:", "Arrival Time:"],
            "Departure Date:"      => ["Departure Date:", "Departure Time:"],
            "Check-In Time:"       => ["Check-In Time:", "Check In Time:"],
            "Check-Out Time:"      => ["Check-Out Time:", "Check Out Time:"],
            "Guest Name:"          => ["Guest Name:"],
            "Address:"             => ["Address:"],
            "Tel.:"                => ["Phone:"],
            "Cancellation Policy:" => ["Cancellation Policy:", "Cancellation:"],
            "Subtotal:"            => ["Subtotal:", "Sub Total:", "Sous-total:"],
            // "Total price:" => '',
            // "Estimated Taxes:" => '',
            // "Estimated Additional Fees:" => '',
            "Rate:"                => ["Rate:"],
            "Member no"            => ["MEMBER NO", "Member no", "Member Number"],
            "cancelledPhrases"     => [
                "Your reservation has been cancelled for your stay at",
                "Please find the details of the cancelled reservation below",
            ],
            "Points"     => "Points",
            "Adults:"    => "Adults:",
            "Children:"  => "Children:",
            "Infants:"   => "Infants:",
            "Room Type:" => "Room Type:",

            "You've registered with us as" => ["You've registered with us as", "You’ve registered with us as"],
        ],
        "es" => [
            "Reservation Summary/Room Information" => [
                "Reservation Summary/Room Information",
                "Resumen de la Reserva/Información de la Habitación",
                "RESUMEN DE RESERVA POLÍTICAS DE GARANTÍA Y RESERVAS DEL HOTEL",
            ],
            "Your reservation has been cancelled for your stay at" => "Se ha cancelado su reserva en el",
            "Confirmation:"                                        => ["Confirmation:", "Confirmación:", 'Número de la reserva:'],
            //			"Hotel:" => ["Hotel:"],
            "Arrival Date:"      => ["Arrival Date:", "Fecha de llegada:"],
            "Departure Date:"    => ["Departure Date:", "Fecha de partida:"],
            "Check-In Time:"     => ["Check-In Time:", "Hora de llegada:"],
            "Check-Out Time:"    => ["Check-Out Time:", "Hora de salida:"],
            "Guest Name:"        => ["Guest Name:", "Nombre del huésped:"],
            //			"Address:" => ["Address:"],
            //			"Tel.:" => ["Phone:"],
            "Cancellation Policy:"       => ["Cancelación:", "Política de cancelación:"],
            "Subtotal:"                  => ["Subtotal:"],
            "Total price:"               => 'Precio total:',
            "Estimated Taxes:"           => 'Impuestos estimados:',
            "Estimated Additional Fees:" => 'Cargos adicionales estimados:',
            "Rate:"                      => ["Rate:"],
            "Member no"                  => ["MEMBER NO", "N.º de miembro"],
            "cancelledPhrases"           => "Se ha cancelado su reserva en el",
            "Points"                     => "Puntos",
            "Adults:"                    => "Adultos:",
            "Children:"                  => "Niños:",
            "Infants:"                   => "Infantes:",
            "Room Type:"                 => "Tipo de habitación:",
        ],
        "sv" => [
            "Reservation Summary/Room Information" => [
                "Bokningsöversikt/rumsinformation",
                "BOKNINGSÖVERSIKT/RUMSINFORMATION",
            ],
            // "Your reservation has been cancelled for your stay at" => "",
            "Confirmation:" => ["Bekräftelse:"],
            //			"Hotel:" => [""],
            "Arrival Date:"      => ["Ankomstdatum:"],
            "Departure Date:"    => ["Avresedatum:"],
            "Check-In Time:"     => ["Incheckningstid:"],
            "Check-Out Time:"    => ["Utcheckningstid:"],
            "Guest Name:"        => ["Gästens namn:"],
            //			"Address:" => [""],
            //			"Tel.:" => [""],
            "Cancellation Policy:"   => ["Avbokning:"],
            "Subtotal:"              => ["Delsumma:"],
            // "Total price:" => '',
            // "Estimated Taxes:" => '',
            // "Estimated Additional Fees:" => '',
            "Rate:"                  => ["Pris:"],
            "Member no"              => ["Medlemsnr", "MEDLEMSNR"],
            // "cancelledPhrases" => "",
            "Points" => ["POÄNG", "poäng"],
            //            "Adults:" => "",
            //            "Children:" => "",
            //            "Infants:" => "",
            //            "Room Type:" => "",

            "You've registered with us as" => "Detta e-postmeddelande skickades till dig på",
        ],
        "no" => [
            "Reservation Summary/Room Information" => [
                "RESERVASJONSSAMMENDRAG HOTELLGARANTI OG RESERVASJONSREGLER",
                "RESERVASJONSSAMMENDRAGHOTELLGARANTI OG RESERVASJONSREGLER",
            ],
            // "Your reservation has been cancelled for your stay at" => "",
            "Confirmation:" => ["Reservasjonsnummer:"],
            //			"Hotel:" => [""],
            "Arrival Date:"      => ["Ankomstdato:"],
            "Departure Date:"    => ["Avreisedato:"],
            "Check-In Time:"     => ["Tidspunkt for innsjekk:"],
            "Check-Out Time:"    => ["Tidspunkt for utsjekk:"],
            "Guest Name:"        => ["Gjestens navn:"],
            //			"Address:" => [""],
            //			"Tel.:" => [""],
            "Cancellation Policy:"   => ["Avbestillingsregler:"],
            "Subtotal:"              => ["Delsum:"],
            // "Total price:" => '',
            // "Estimated Taxes:" => '',
            // "Estimated Additional Fees:" => '',
            //            "Rate:"         => ["Pris:"],
            //            "Member no"   => ["Medlemsnr", "MEDLEMSNR"],
            // "cancelledPhrases" => "",
            //            "Points" => ["POÄNG", "poäng"],
            "Adults:"    => "Voksne:",
            "Children:"  => "Barn:",
            "Infants:"   => "Spedbarn:",
            "Room Type:" => "Romtype:",

            //            "You've registered with us as" => "Detta e-postmeddelande skickades till dig på",
        ],
    ];

    public $lang = '';

    private $reFrom = ["carlsonhotels@email.carlsonhotels.com", "RadissonHotels@e.radissonhotels.com", 'noreply@email.radissonhotels.com'];

    private $reSubject = [
        "pl" => ["potwierdzenie Twojej rezerwacji"],
        "de" => [
            "Ihre Reservierungsstornierung",
            "die Reservierungsnummer Ihrer Buchung lautet",
            ', Ihre Reservierungsnummer lautet',
        ],
        "fr" => ["Confirmation de votre réservation",
            ', votre numéro de réservation est le',
        ],
        "da" => ["Din reservationsbekræftelse"],
        "it" => ["Conferma della prenotazione"],
        "nl" => ["Uw reserveringsbevestiging",
            ', uw reserveringsnummer is',
            ', uw nieuwe reserveringsnummer is',
        ],
        "en" => [
            "Your Reservation Confirmation",
            "Your Adjusted Reservation",
            "Your Reservation",
            "your cancelled reservation",
            "Your Booking reservation number is",
            "Your Updated reservation number is ",
            ', Your Booking reservation number is ',
            ', Your reservation number is ',
        ],
        "es" => [
            "la confirmación de su reserva", 'su número de reserva es',
            "su número de cancelación de reserva es",
        ],
        "sv" => ["din bokningsbekräftelse"],
        "no" => ["reservasjonsnummeret ditt er"],
    ];

    private $reBody = ['Carlson', "Radisson"];

    private $langDetectors = [
        "pl" => ["Mamy przyjemność potwierdzić Twoją rezerwację w"],
        "de" => ["Reservierungsrichtlinien des Hotels"],
        "fr" => [
            "Politiques de garantie et de réservation de l’hôtel",
            "Nous vous remercions d’avoir choisi notre hôte",
            'Nous avons le plaisir de vous confirmer votre séjour',
        ],
        "da" => [
            "Opsummering af reservation",
            "Indtjekningstidspunkt",
        ],
        "it" => [
            "Riepilogo della prenotazione",
            ', il numero della prenotazione è ',
        ],
        "nl" => [
            "Met plezier bevestigen wij uw reservering",
            "Het is ons een genoegen uw reservering",
            "Het doet ons genoegen uw reservering in",
        ],
        "en" => [
            "We are pleased to confirm your reservation at",
            "We are happy to confirm your reservation at",
            "We look forward to seeing you at",
            "Your reservation has been adjusted for your stay at",
            "reservation has been confirmed for your stay at",
            "We look forward to welcoming you to the Radisson",
            "Your reservation has been cancelled for your stay at",
            "Please find the details of the cancelled reservation below",
            "We are pleased to confirm the modification to your reservation at",
            "Your reservation at Radisson ",
            "happy to confirm your reservation at",
            "We want you to feel the difference with Radisson Blu",
            "Your stay at ",
            "Your reservation at",
        ],
        "es" => [
            "Nos complace confirmar su reserva en",
            "Se ha cancelado su reserva en el",
        ],
        "sv" => [
            "Vi är glada att kunna bekräfta din bokning på",
        ],
        "no" => [
            "Vi kan nå bekrefte din reservasjon på ",
            "Vi vil at du skal kjenne forskjellen med Radisson Blu",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if ($this->striposAll($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            foreach ($re as $subject) {
                if (strpos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }
        $foundReBody = false;

        foreach ($this->reBody as $reBody) {
            if (strpos($body, $reBody) !== false) {
                $foundReBody = true;
            }
        }

        if ($foundReBody == false) {
            return false;
        }

        return $this->assignLang($body);
    }

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"
        $htmlBody = $parser->getHTMLBody();

        if (empty($htmlBody)) {
            $htmlBody = $parser->getPlainBody();
            $this->http->SetEmailBody($htmlBody);
        }
        $this->assignLang($htmlBody);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseHtml($email);

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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseHtml(Email $email): Email
    {
        $patterns = [
            // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    18:00:00
            'time'  => '(?:\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?|\d{2}:\d{2}:\d{2})',
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]',
        ];

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//tr[ *[1][{$this->eq('Confirmation:')}] ]/*[2]", null, true, '/^[#\s]*([A-Z\d]{5,})$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[ *[2] ]/*[1][{$this->eq('Confirmation:')}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $hotelName = $address = $phone = null;

        // Address
        $address = implode(', ', $this->http->FindNodes("//text()[{$this->eq('Address:')}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()]"));

        if (empty($address)) {
            $hotelInfo = $this->htmlToText($this->http->FindHTMLByXpath("//*[{$this->eq("Reservation Summary/Room Information")}]/preceding::table[normalize-space()][1]"));
            /*
                PARK INN BY RADISSON STOCKHOLM HAMMARBY SJOSTAD,
                Midskeppsgatan 6
                Stockholm SE-12066
                +46 8 50507000
            */
            if (preg_match("/^\s*(?<name>.{2,}?)[, ]*(?<address>(?:\n+.{2,}){1,3})\n+[ ]*(?<phone>{$patterns['phone']}|0)\s*$/", $hotelInfo, $m)
                || preg_match("/^\s*(?<name>.{2,}?),\s+(?<address>.{2,}),\s+(?<phone>{$patterns['phone']}|0)\s*$/s", $hotelInfo, $m)
            ) {
                $hotelName = $m['name'];
                $address = preg_replace('/[, ]*\n+[, ]*/', ', ', trim($m['address']));
                $phone = empty($m['phone']) ? null : $m['phone'];
            } elseif (preg_match("/^\s*(?<name>.{2,}?)[, ]*(?<address>(?:\n+.+[A-Za-z]+.+){1,3})\s*$/", $hotelInfo, $m)) {
                $hotelName = $m['name'];
                $address = preg_replace('/[ ]*\n+[ ]*/', ', ', trim($m['address']));
            }
        }

        // Hotel Name
        if (empty($hotelName)) {
            $hotelName = $this->nextText("Hotel:");
        }

        if (empty($hotelName)) {
            $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains("Your reservation has been cancelled for your stay at")}]",
                null, false, "/{$this->opt("Your reservation has been cancelled for your stay at")}(?:\s*\bthe\s|\s)+([^.]{3,})\./");

            if (!$hotelName_temp) {
                $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains("Your reservation has been cancelled for your stay at")}]", null, false, "/{$this->opt("Your reservation has been cancelled for your stay at")}(.+?)(?:te kunnen)/");
            }

            //$this->logger->debug('Hotel Name Temp: ' . $hotelName_temp);

            if ($hotelName_temp && $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
                $hotelName = $hotelName_temp;
            }
        }

        // Phone
        if (empty($phone)) {
            $phone = $this->nextText("Tel.:");
        }

        if (empty($phone) && !empty($hotelName)) {
            $phone = $this->http->FindSingleNode("//td//a[contains(text(),'{$hotelName}')]/following::a[normalize-space()]/following::a[contains(@href,'tel:')]");
        }

        if ($this->http->XPath->query("//*[{$this->contains("cancelledPhrases")}]")->length > 0) {
            // it-34870287.eml, it-120671262.eml
            $h->general()
                ->status('cancelled')
                ->cancelled();

            if ($cancellationNubmber = $this->re("/#\s*([A-Z\d]{5,})\b/", $this->nextText("Cancellation Policy:"))) {
                $h->general()->cancellationNumber($cancellationNubmber);
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->phone($phone, false, true);

        if (empty($address) && $h->getCancelled()) {
            $h->hotel()->noAddress();
        } else {
            $h->hotel()->address($address);
        }

        // CheckInDate
        $time = $this->nextText("Check-In Time:");
        $time = str_replace(["Pomeriggio", "Mattino"], ["PM", "AM"], $time);
        $checkinDate = strtotime($this->normalizeDate($this->nextText("Arrival Date:") . ', ' . $this->re("#(\d+:\d+(\s*[AMP]{2})?)#",
                $time)));

        if ($checkinDate) {
            $h->booked()->checkIn($checkinDate);
        }

        // CheckOutDate
        $time = $this->nextText("Check-Out Time:");
        $time = str_replace(["Pomeriggio", "Mattino"], ["PM", "AM"], $time);
        $checkOutDate = strtotime($this->normalizeDate($this->nextText("Departure Date:") . ', ' . $this->re("#(\d+:\d+(\s*[AMP]{2})?)#",
                $time)));

        if ($checkOutDate) {
            $h->booked()->checkOut($checkOutDate);
        }

        // GuestNames
        $guestNames = array_filter([$this->nextText("Guest Name:")]);

        if (0 < count($guestNames)) {
            foreach ($guestNames as $guestName) {
                $h->addTraveller($guestName);
            }
        }

        $adults = $this->http->FindSingleNode("//tr[ *[1][{$this->eq("Adults:")}] ]/*[2]", null, true, "/^\d{1,3}$/");
        $h->booked()->guests($adults, false, true);

        $kids = [];
        $kidsTitle = array_unique(array_merge((array) $this->t("Children:"),
            preg_replace('/([^\*]):\s*$/', '$1*:', (array) $this->t("Children:"))
        ));
        $infantsTitle = array_unique(array_merge((array) $this->t("Infants:"),
            preg_replace('/([^\*]):\s*$/', '$1*:', (array) $this->t("Infants:"))
        ));
        $kids[] = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($kidsTitle, false, false)}] ]/*[2]", null, true, "/^\d{1,3}$/");
        $kids[] = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($infantsTitle, false, false)}] ]/*[2]", null, true, "/^\d{1,3}$/");

        if (count(array_filter($kids, function ($item) { return $item !== null; })) > 0) {
            $h->booked()->kids(array_sum($kids));
        }

        // Rate
        // RateType
//        $rateText = '';
        $roomTypes = [];
        $xpathFragmentRate1 = "./*[1][{$this->eq('Rate:')}]";
        $xpathFragmentRate2 = "/*[last()]/descendant::text()[normalize-space(.)]";
        $rateRows = $this->http->FindNodes("//tr[{$xpathFragmentRate1}]{$xpathFragmentRate2} | //tr[ ./preceding-sibling::tr[{$xpathFragmentRate1}] and ./following-sibling::tr[./*[1][{$this->eq('Subtotal:')}]] ]{$xpathFragmentRate2}");
        $rates = [];

        foreach ($rateRows as $rateRow) {
            if (preg_match('/^(?<date>.+?\D\d{4}) +(?<amount>\d[,.\'\d ]*?) *(?<currency>[A-Z]{3}) +(?<type>.+)$/',
                $rateRow, $matches)) {
                // Oct 12, 2018 49.00 EUR 1 King Bed-Non-Smoking-Standard Room
                $this->logger->debug('$rateMatches = ' . print_r($matches['currency'] . ' ' . $matches['amount'] . ' from ' . $matches['date'], true));

//                $rateText .= "\n" . $matches['currency'] . ' ' . $matches['amount'] . ' from ' . $matches['date'];
                $roomTypes[] = $matches['type'];
                $rates[] = $matches['amount'] . ' ' . $matches['currency'];
            }
        }

        if (count($roomTypes) === 0
            && ($roomType = $this->http->FindSingleNode("//tr[ *[1][{$this->eq("Room Type:")}] ]/*[2]"))
        ) {
            $roomTypes[] = $roomType;
        }

        if (!empty($rates) || count($roomTypes)) {
            $room = $h->addRoom();

            if (!empty($rates)) {
                $room->setRates($rates);
            }

            if (count($roomTypes)) {
                $room->setType(implode('; ', array_unique($roomTypes)));
            }
        }

        // cancellation
        // deadline
        $cancellationPolicy = $this->http->FindSingleNode("(//text()[{$this->eq('Cancellation Policy:')}])[last()]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1][not(starts-with(normalize-space(), '#'))]");

        if ($cancellationPolicy) {
            $h->general()->cancellation($cancellationPolicy);
        }

        if (preg_match("/^Cancel by\s+(?<time>{$patterns['time']})\s+hotel time on (?<date>[A-z]{3,} \d{1,2} \d{2,4}|\d{1,2} [A-z]{3,} \d{2,4})\s*(?:[.;!]|=\s*no penalty)/i", $cancellationPolicy, $m)) {
            // en (it-6904321.eml)
            $h->booked()->deadline2($this->normalizeDate($m['date'] . ', ' . $m['time']));
        } elseif (preg_match('/Cancellation (\d+ hours?) prior to (' . $patterns['time'] . ') hotel time, ([a-z]+) (\d{1,2}) (\d{2,4})\s+\=\s*no penalty/i',
            $cancellationPolicy, $m)) {
            // en (it-7183962.eml)
            $h->booked()->deadlineRelative($m[1], $m[2] . ' ' . $m[4] . ' ' . $m[3] . ' ' . $m[5]);
        } elseif (preg_match('/Anulowanie o godzinie (' . $patterns['time'] . ') czasu hotelowego ([[:alpha:]]{3,}) (\d{1,2}) (\d{2,4}) = brak kary/iu',
            $cancellationPolicy, $m)) {
            // pl
            $h->booked()->deadline2($this->normalizeDate($m[2] . ' ' . $m[3] . ', ' . $m[4] . ', ' . $m[1]));
        } elseif (preg_match('/Stornierung erfolgt bis (' . $patterns['time'] . ').* am ([^\d\W]{3,}) (\d{1,2}) (\d{2,4}) = Keine Stornierungsgebühr/iu',
            $cancellationPolicy, $m)) {
            // de
            $h->booked()->deadline2($this->normalizeDate($m[2] . ' ' . $m[3] . ', ' . $m[4] . ', ' . $m[1]));
        } elseif (preg_match('/Cancelación de hoy a ([^\d\W]{3,}) (\d{1,2}) (\d{4}) = sin penalizaciones/iu',
            $cancellationPolicy, $m)) {
            // es
            $h->booked()->deadline2($this->normalizeDate($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', 00:00'));
        } elseif (preg_match('/Cancelación antes de 48 horas con antelación a la hora del hotel (\d{1,2}:\d{2}[\s]?(?:A|P)M), ene (\d{1,2})\s(\d{2,4})/iu',
            $cancellationPolicy, $m)) {
            $date = $this->normalizeDate($this->nextText("Arrival Date:") . ', ' . $this->re("#(\d+:\d+(\s*[AMP]{2})?)#", $m[1]));
            // es
            $h->booked()->deadline(strtotime(date('d.m.Y  h:i', strtotime($date . ' -2 day'))));
        } elseif (preg_match('/Annullering den (?<time>' . $patterns['time'] . ') hoteltid den (?<date>.+?\b\d{4}) = opkræves der ikke gebyr\./iu',
                $cancellationPolicy, $m)
            || preg_match("/Une annulation faite à (?<time>{$patterns['time']}), heure de l'hôtel, (?<date>.+?\b\d{4}) entraînera sans pénalité\./iu",
                $cancellationPolicy, $m)
        ) {
            // da
            // fr
            $h->booked()->deadline2($this->normalizeDate($m['date'] . ', ' . $m['time']));
        } elseif (preg_match('/Cancel today thru (.+?\b\d{4}) = no penalty\./iu', $cancellationPolicy, $m)) {
            // da
            $h->booked()->deadline2($this->normalizeDate($m[1]));
        }

        if (preg_match('/Avbokningsavgift tillämpas vid alla avbokningar/iu', $cancellationPolicy, $m)
         || preg_match('/Reservation is non-refundable/iu', $cancellationPolicy, $m)
         || preg_match('/De reservering is niet terugbetaalbaar\./iu', $cancellationPolicy, $m) // nl
        ) {
            $h->booked()->nonRefundable();
        }

        // Total
        // Currency
        if (!$h->getCancelled()) {
            $currency = null;
            $totalText = $this->nextText("Subtotal:");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?) *(?<currency>[A-Z]{3})\b/', $totalText, $matches)
                || preg_match("/^(?<amount>\d[,.\'\d ]*)$/", $totalText, $matches)
            ) {
                $currency = $currency ?? $matches['currency'] ?? null;
                $h->price()->cost($this->normalizeAmount($matches['amount'], $currency));

                if (!empty($matches['currency'])) {
                    $h->price()->currency($matches['currency']);
                }
            }
            $totalText = $this->nextText("Total price:");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?) *(?<currency>[A-Z]{3})\b/', $totalText, $matches)
                || preg_match("/^(?<amount>\d[,.\'\d ]*)$/", $totalText, $matches)
            ) {
                $currency = $currency ?? $matches['currency'] ?? null;
                $h->price()->total($this->normalizeAmount($matches['amount'], $currency));
            }
            $tax = $this->nextText("Estimated Taxes:");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?) *(?<currency>[A-Z]{3})\b/', $tax, $matches)
                || preg_match("/^(?<amount>\d[,.\'\d ]*)$/", $tax, $matches)
            ) {
                $currency = $currency ?? $matches['currency'] ?? null;
                $h->price()->tax($this->normalizeAmount($matches['amount'], $currency));
            }

            $fee = $this->nextText("Estimated Additional Fees:");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?) *(?<currency>[A-Z]{3})\b/', $fee, $matches)
                || preg_match("/^(?<amount>\d[,.\'\d ]*)$/", $fee, $matches)
            ) {
                $currency = $currency ?? $matches['currency'] ?? null;
                $h->price()->fee(trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimated Additional Fees:")) . "]"),
                    ': '),
                    $this->normalizeAmount($matches['amount'], $currency));
            }

            // SpentAwards
            $spentAwards = $this->re("#(\d[\d,.]+\s+(\w+ )?" . $this->opt('Points') . ")#u", $totalText);

//        if (empty($spentAwards)) {
//            $spentAwards = $this->http->FindSingleNode("//text()[" . $this->starts("Points") . "]/following::td[1]",
//                null, true, '/^(\d[\d,.]+)$/');
//        }

            if ($spentAwards) {
                $h->price()->spentAwards($spentAwards);
            }
        }
        // Program
        $account = $this->http->FindSingleNode("//tr[{$this->eq('Member no')}]/following-sibling::tr[normalize-space(.)][1]",
            null, true, '/^\s*X*\d+\s*$/');

        if (!empty($account)) {
            $st = $email->add()->statement();

            if (preg_match("/XX+/", $account)) {
                $st->setNumber($account)->masked();
            } else {
                $st->setNumber($account);
            }

            $points = $this->http->FindSingleNode("//td[" . $this->eq("Points", true) . "]/following::td[1][following::td[normalize-space()][{$this->eq('Member no', true)}]]",
                null, true, '/^\s*(\d[\d,.]*)\s*$/');

            if ($points == null) {
                $points = $this->http->FindSingleNode("//td[" . $this->eq("Points", true) . "]/following::text()[normalize-space()][1]",
                    null, true, '/^\s*(\d[\d,.]*)\s*$/');
            }

            if ($points !== null) {
                $st->setBalance(str_replace([',', '.'], '', $points));
            }

            $statuses = ['CLUB', 'SILVER', 'GOLD', 'PLATINUM'];

            $status = $this->http->FindSingleNode("//td[{$this->eq('Member no')}]/following::td[not(.//td)][normalize-space(.)][3][{$this->eq($statuses, true)}]");

            if ($status) {
                $st->addProperty('Status', $status);
            }

            $login = $this->http->FindSingleNode("//tr[not(.//tr)][" . $this->starts("You've registered with us as") . "]", null, true, "/^\s*" . $this->opt("You've registered with us as") . "[* ]*:\s*(\S{3,}@\S{3,})(?: .*)?$/");

            if (empty($login)) {
                $login = $this->http->FindSingleNode("//text()[" . $this->starts("You've registered with us as") . "]", null, true, "/^\s*" . $this->opt("You've registered with us as") . "[* ]*:\s*(\S{3,}@\S{3,})(?: .*)?$/");
            }

            if (!empty($login)) {
                $st->setLogin($login);
            }
        }

        return $email;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)][1]",
            $root);
    }

    private function eq($field, $differentCase = false, $translate = true)
    {
        if (is_string($field) && $translate) {
            $field = (array) $this->t($field);
        } else {
            $field = (array) $field;
        }

        if (count($field) == 0) {
            return 'false()';
        }

        if ($differentCase === true) {
            $field2 = array_map('strtolower', $field);
            $field = array_merge($field2, array_map('strtoupper', $field2), array_map('ucfirst', $field2));
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $this->t($field);

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $this->t($field);

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text,
                        $phrase) !== false || $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Reservation Summary/Room Information'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Reservation Summary/Room Information'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function normalizeDate($str)
    {
        $in = [
            // Dec 10, 2018, 2:00 PM  |  maj 06 2019, 2:00 PM
            "/^([^\d\s]+)\s+(\d{1,2}),?\s+(\d{4}),\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

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

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $value, ?string $currency): string
    {
        $value = preg_replace('/\s+/', '', $value);             // 11 507.00  ->  11507.00

        $value = PriceHelper::parse($value, $currency);

        if (is_numeric($value)) {
            $value = (float) $value;
        } else {
            $value = null;
        }

        return $value;
    }

    private function parseRateRange($string = '')
    {
        if (
        preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string,
            $rateMatches) // $239.20 from August 15
        ) {
            //$this->logger->debug('$rateMatches = '.print_r( $rateMatches,true));
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $currency = $rateMatches['currency'][0];
                $rateMatches['amount'] = array_map(function ($item) use ($currency) {
                    return (float) $this->normalizeAmount($item, $currency);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.',
                            '') . ' ' . $rateMatches['currency'][0] . ' / day';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.',
                            '') . ' ' . $rateMatches['currency'][0] . ' / day';
                }
            }
        }

        return null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\room", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
