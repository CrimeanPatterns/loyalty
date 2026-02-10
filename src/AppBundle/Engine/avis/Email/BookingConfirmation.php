<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "avis/it-1872292.eml, avis/it-3877545.eml, avis/it-3891188.eml, avis/it-3936111.eml, avis/it-50173593.eml, avis/it-5874714.eml, avis/it-79859731.eml"; // +4 bcdtravel(html)[fr,da,es]

    public $reSubject = [
        "de" => ["Buchungsbestätigung für", "Reservierungsanfrage"],
        "nl" => ["Reserveringsbevestiging voor"],
        "fr" => ["Confirmation de réservation pour", "Mis à jour de réservation pour", 'Confirmation d\'annulation de votre réservation Avis'],
        "no" => ["Bestillingsbekreftelse"],
        "it" => ["Conferma di prenotazione per"],
        "en" => ["Booking Confirmation for", "Cancellation of Booking Notification"],
        "da" => ["Booking-bekræftelse for", 'Annullering af booking'],
        "es" => ["Reserva confirmada para"],
        "tr" => ["Avis Rezervasyon Onayı"],
        "sv" => ["Bokningsbekräftelse"],
    ];

    public $reBody = 'AVIS';

    public $reBody2 = [
        'de' => 'Buchungsdaten',
        'nl' => 'RESERVERINGSGEGEVENS',
        'fr' => ['Détails de votre réservation', 'DÉTAILS DE VOTRE RÉSERVATION', 'INFORMATION DE RÉSERVATION'],
        'no' => 'BESTILLINGSINFORMASJON',
        'it' => 'DETTAGLI DEL NOLEGGIO',
        'en' => ['BOOKING DETAILS', "YOUR RENTAL DETAILS"],
        'da' => ['Tak for dit køb af en forudbetalt voucher og fordi du vælger at leje bil hos Avis', 'BOOKING-DETALJER', 'DINE LEJEOPLYSNINGER'],
        'es' => ['Tu número de reserva', 'Detalles de la reserva'],
        'fi' => ['Ota varausnumerosi mukaan, kun tulet noutamaan autosi'],
        'tr' => 'Kiralama Bilgileri', //Kiralama Voucher'ı yerine geçer. Kiralama esnasında yanınızda bulundurulmalıdır.
        "sv" => ["Bokningsbekräftelse"],
    ];

    public $lang = '';

    public static $dictionary = [
        "de" => [
            "Your AVIS booking Confirmation:" => ["Ihre Buchungsbestätigung von Avis", "Ihre Buchungsnummer:", "Ihre Reservationsanfrage:"],
            "PICK UP DATE"                    => "ABHOLDATUM",
            "PICK UP LOCATION"                => ["ABHOLSTATION", "ABHOLUNGSORT"],
            "RETURN DATE"                     => "RÜCKGABEDATUM",
            "RETURN LOCATION"                 => ["RÜCKGABESTATION", "RÜCKGABEORT"],
            "Show location details"           => "Mehr Informationen anzeigen",
            "TELEPHONE NO"                    => "TELEFONNUMMER",
            "OPENING HOURS"                   => "ÖFFNUNGSZEITEN",
            "Your booking details:"           => "Ihre Buchungsdaten",
            "PRICE OF VEHICLE"                => ["GESAMTPREIS", "MIETWAGEN-PREIS"],
            'Dear'                            => 'Guten Tag',
            'Bestätigung folgt'               => 'Bestätigung folgt',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "nl" => [
            "Your AVIS booking Confirmation:" => ["Avis-reserveringsbevestiging:", "Uw reserveringsbevestiging:"],
            "PICK UP DATE"                    => "OPHAALDATUM",
            "PICK UP LOCATION"                => "OPHAALLOCATIE",
            "RETURN DATE"                     => "INLEVERDATUM",
            "RETURN LOCATION"                 => "INLEVERLOCATIE",
            "Show location details"           => "Toon locatiegegevens",
            "TELEPHONE NO"                    => ["TELEFOONNUMMER", "TELEFOONNUMMMER"],
            "OPENING HOURS"                   => "OPENINGSTIJDEN",
            "Your booking details:"           => "Uw reserveringsgegevens:",
            "PRICE OF VEHICLE"                => ["HUURPRIJS AUTO", "GESCHAT TOTAALBEDRAG"],
            'Dear'                            => 'Beste',
            //			'Bestätigung folgt' => '',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "fr" => [
            "Your AVIS booking Confirmation:" => "Votre confirmation de réservation AVIS",
            "PICK UP DATE"                    => ["DATE DE DÉPART", 'DATE DE RAMASSAGE'],
            "PICK UP LOCATION"                => ["AGENCE DE DÉPART", 'LIEU DE RAMASSAGE'],
            "RETURN DATE"                     => "DATE DE RETOUR",
            "RETURN LOCATION"                 => ["AGENCE DE RETOUR", 'RETOUR LOCATION'],
            "Show location details"           => "Informations de l'agence",
            "TELEPHONE NO"                    => ["N° DE TÉLÉPHONE", 'N ° DE TÉLÉPHONE'],
            "OPENING HOURS"                   => ["HORAIRES D'OUVERTURE", "HEURES D'OUVERTURE"],
            "Your booking details:"           => ["Détails de votre réservation", "Vos informations de réservation"],
            "PRICE OF VEHICLE"                => ["PRIX DE LA LOCATION", "PRIX DU VÉHICULE"],
            'Dear'                            => 'Cher(e)',
            //			'Bestätigung folgt' => '',
            // cancelled
            "Cancellation of your booking (number" => 'Confirmation d\'annulation de votre réservation Avis (n°',
            "CAR" => 'VÉHICULE',
            "Duration" => 'DURÉE DE LOCATION',
        ],
        "no" => [
            "Your AVIS booking Confirmation:" => "Bestillingsbekreftelse",
            "PICK UP DATE"                    => "HENTEDATO",
            "PICK UP LOCATION"                => "HENTE LOKASJON",
            "RETURN DATE"                     => "RETURDATO",
            "RETURN LOCATION"                 => "RETUR LOKASJON",
            // "Show location details" => "Informations de l'agence",
            "TELEPHONE NO"          => "TELEFON NR",
            "OPENING HOURS"         => "ÅPNINGSTIDER",
            "Your booking details:" => "Din bestillingsinformasjon:",
            "PRICE OF VEHICLE"      => ["PRIS FOR BIL", "ESTIMERT TOTALPRIS"],
            'Dear'                  => 'Hei',
            //			'Bestätigung folgt' => '',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "it" => [
            "Your AVIS booking Confirmation:" => ["ll numero della tua prenotazione Avis:", "Conferma di prenotazione:"],
            "PICK UP DATE"                    => "DATA DI RITIRO",
            "PICK UP LOCATION"                => "LUOGO DI RITIRO",
            "RETURN DATE"                     => "DATA DI CONSEGNA",
            "RETURN LOCATION"                 => "LUOGO DI CONSEGNA",
            // "Show location details" => "Informations de l'agence",
            "TELEPHONE NO"          => "NUMERO DI TELEFONO",
            "OPENING HOURS"         => "ORARI DI APERTURA",
            "Your booking details:" => "Dettagli del noleggio:",
            "PRICE OF VEHICLE"      => "PREZZO DEL NOLEGGIO",
            			'Dear' => 'Gentile',
            //			'Bestätigung folgt' => '',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "en" => [
//            "Your AVIS booking Confirmation:" => '',
            "PICK UP DATE"                    => ["PICK UP DATE", "RENTAL START DATE"],
            "PICK UP LOCATION"                => ["PICK UP LOCATION", "The Supplier will deliver the vehicle to the following location:"],
            "RETURN DATE"                     => ["RETURN DATE", "RENTAL END DATE"],
            "RETURN LOCATION"                 => ["RETURN LOCATION", "The Supplier will collect the vehicle from the following location:"],
            "PRICE OF VEHICLE"                => ["PRICE OF VEHICLE", "PRICE OF CAR", "ESTIMATED TOTAL"],
            "Your AVIS booking Confirmation:" => ["Your AVIS booking Confirmation:", "Your AVIS Booking Confirmation:"],
            //			'Bestätigung folgt' => '',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "da" => [
            "Your AVIS booking Confirmation:" => ["Dit bookingnummer er:", "Din AVIS booking-bekræftelse:"],
            "PICK UP DATE"                    => ["AFHENTNINGSDATO"],
            "PICK UP LOCATION"                => ["AFHENTNINGSSTATION"],
            "RETURN DATE"                     => "AFLEVERINGSDATO",
            "RETURN LOCATION"                 => ["AFLEVERINGSSTATION"],
            //			"Show location details" => "",
            "TELEPHONE NO"          => ["TELEFONNUMMER"],
            "OPENING HOURS"         => ["ÅBNINGSTIDER"],
            "Your booking details:" => ["Dine booking-detaljer:"],
            "PRICE OF VEHICLE"      => ["PRIS PÅ BIL", "PRIS PÅ KØRETØJ"],
            'Dear'                  => ['Hej', 'Kære'],
            //			'Bestätigung folgt' => '',
            // cancelled
            "Cancellation of your booking (number" => 'Annullering af din booking (nummer',
            "CAR" => 'BIL',
            "Duration" => 'VARIGHED',
        ],
        "es" => [
            "Your AVIS booking Confirmation:" => "Su reserva AVIS:",
            "PICK UP DATE"                    => ["FECHA DE RECOGIDA"],
            "PICK UP LOCATION"                => ["LUGAR DE RECOGIDA"],
            "RETURN DATE"                     => "FECHA DE REGRESO",
            "RETURN LOCATION"                 => ["LUGAR DE REGRESO"],
            //			"Show location details" => "",
            "TELEPHONE NO"          => ["NÚMERO DE TELÉFONO"],
            "OPENING HOURS"         => ["HORARIOS DE APERTURA"],
            "Your booking details:" => ["Los detalles de tu reserva", "Los detalles de su reserva"],
            "PRICE OF VEHICLE"      => ["PRECIO TOTAL", "PRECIO DEL VEHÍCULO"],
            'Dear'                  => 'Estimado/a',
            //			'Bestätigung folgt' => '',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "fi" => [
            "Your AVIS booking Confirmation:" => "AVIS-varausvahvistuksesi:",
            "PICK UP DATE"                    => ["NOUTOPÄIVÄMÄÄRÄ"],
            "PICK UP LOCATION"                => ["NOUTOPAIKKA"],
            "RETURN DATE"                     => "PALAUTUKSEN PÄIVÄMÄÄRÄ",
            "RETURN LOCATION"                 => ["PALAUTUSPAIKKA"],
            //			"Show location details" => "",
            "TELEPHONE NO"          => ["PUHELIN"],
            "OPENING HOURS"         => ["AUKIOLOAJAT"],
            "Your booking details:" => ["Varauksesi:"],
            "PRICE OF VEHICLE"      => ["AUTON HINTA", "ARVIOITU LOPPUSUMMA"],
            'Dear'                  => 'Hei',
            //			'Bestätigung folgt' => '',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "tr" => [
            "Your AVIS booking Confirmation:" => "Rezervasyon Numarası",
            "PICK UP DATE"                    => "Teslim Alma Tarihi",
            "RETURN DATE"                     => "İade Tarihi",
            "TELEPHONE NO"                    => "Tel :",
            "PRICE OF VEHICLE"                => "Toplam ücret",
            'or'                              => 'ör:',
            "Adress"                          => "Adres :",
            "Name surname"                    => "Ad Soyad",
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
        "sv" => [
            "Your AVIS booking Confirmation:" => "Bokningsbekräftelse:",
            "PICK UP DATE"                    => "HÄMTAS DEN",
            "PICK UP LOCATION"                => "HÄMTAS PÅ",
            "RETURN DATE"                     => "ÅTERLÄMNAS DEN",
            "RETURN LOCATION"                 => "ÅTERLÄMNAS PÅ",
//            "Show location details"           => "",
            "TELEPHONE NO"                    => "TELEFONNUMMER",
            "OPENING HOURS"                   => "ÖPPETTIDER",
            "Your booking details:"           => "BOKAD BILGRUPP:",
            "PRICE OF VEHICLE"                => "SUMMA TOTALT FÖR BOKNING (enligt dagens växelkurs)",
            'Dear'                            => 'Hej',
            'Bestätigung folgt'               => 'Bestätigung folgt',
            // cancelled
//            "Cancellation of your booking (number" => '',
//            "CAR" => '',
//            "Duration" => '',
        ],
    ];

    private $namePrefixes = ['Herr'];

    public function parseHtmlType1(Email $email): void
    {
        $r = $email->add()->rental();

        $rulePickUpLoc = $this->eq($this->t("PICK UP LOCATION"));
        $ruleReturnLoc = $this->eq($this->t("RETURN LOCATION"));

        // Number
        $confNo = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Your AVIS booking Confirmation:"))}]/ancestor::td[1])[1]", null, true, "/(?:{$this->opt($this->t("Your AVIS booking Confirmation:"))})\s*:*\s*([\-A-Z\d]+)\b/u");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Your AVIS booking Confirmation:"))}]/ancestor::td[1])[1]", null, true, "/(?:{$this->opt($this->t("Your AVIS booking Confirmation:"))})\s*:*\s*({$this->opt($this->t("Bestätigung folgt"))})/");
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Cancellation of your booking (number"))}][1]", null, true, "/(?:{$this->opt($this->t("Cancellation of your booking (number"))})\s*([\d\-A-Z]+)\s*\)\s*$/");
            if (!empty($confNo)) {
                $r->general()
                    ->cancelled()
                    ->status('Cancelled');
            }
        }

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo);
        } else {
            $r->general()->noConfirmation();
        }

        // RenterName
        $renterNameTexts = $this->http->FindNodes('//td[not(.//td) and ' . $this->starts($this->t('Dear')) . ']/descendant::text()[normalize-space(.)]');
        $renterNameText = implode("\n", $renterNameTexts);

        if (preg_match('/^' . $this->opt($this->t('Dear')) . '[,]*\s+([A-z][-.\'A-z ]*[.A-z])\s*(?:,|$)/mu', $renterNameText, $m)) {
            $r->general()->traveller(preg_replace("/^{$this->opt($this->namePrefixes)}[,.\s]+(.{2,})$/", '$1', $m[1]));
        }

        // PickupDatetime
        $pickupDate = strtotime($this->normalizeDate($this->getField($this->t("PICK UP DATE"))));

        if (!empty($pickupDate)) {
            $r->pickup()->date($pickupDate);
        }

        // PickupLocation
        $pickupLoc = implode(", ", array_filter($this->http->FindNodes('//text()[' . $rulePickUpLoc . ']/ancestor::td[1]//text()[normalize-space(.)="' . $this->t("Show location details") . '"]/preceding::text()[./preceding::text()[' . $rulePickUpLoc . ']]')));

        if (empty($pickupLoc)) {
            $pickupLoc = implode(", ", array_filter($this->http->FindNodes('//text()[' . $this->getXpath($this->t("PICK UP DATE")) . ']/preceding::text()[./preceding::text()[' . $rulePickUpLoc . ']]')));
        }

        if (empty($pickupLoc)) {
            $pickupLoc = implode(", ", array_filter($this->http->FindNodes("//text()[{$rulePickUpLoc}]/ancestor::td[1]//text()[normalize-space(.)][./preceding::text()[{$rulePickUpLoc}]]")));
        }

        if (!empty($pickupLoc)) {
            $r->pickup()->location($pickupLoc);
        }

        // DropoffDatetime
        $dropoffDate = strtotime($this->normalizeDate($this->getField($this->t("RETURN DATE"))));

        if (empty($dropoffDate)) {
            $str = $this->getField($this->t("DURATION"));

            if (preg_match("#(\d+)\s+{$this->t("day")}+#", $str, $m)) {
                $dropoffDate = strtotime("+" . $m[1] . 'days', $pickupDate);
            }
        }

        if (!empty($dropoffDate)) {
            $r->dropoff()->date($dropoffDate);
        }

        // DropoffLocation
        $dropoffLoc = implode(', ', array_filter($this->http->FindNodes('//text()[' . $ruleReturnLoc . ']/ancestor::td[1]//text()[normalize-space(.)="' . $this->t("Show location details") . '"]/preceding::text()[./preceding::text()[' . $ruleReturnLoc . ']]')));

        if (empty($dropoffLoc)) {
            $dropoffLoc = implode(', ',
                array_filter($this->http->FindNodes('//text()[' . $this->getXpath($this->t("RETURN DATE")) . ']/preceding::text()[./preceding::text()[' . $ruleReturnLoc . ']]')));
        }

        if (empty($dropoffLoc)) {
            $dropoffLoc = implode(", ",
                array_filter($this->http->FindNodes($q = "//text()[{$ruleReturnLoc}]/ancestor::td[1]//text()[normalize-space(.)][./preceding::text()[{$ruleReturnLoc}]]")));
        }

        if (!empty($dropoffLoc)) {
            $r->dropoff()->location($dropoffLoc);
        }

        // PickupPhone
        $pickupPhone = $this->http->FindSingleNode('//text()[' . $rulePickUpLoc . ']/ancestor::td[1]/following-sibling::td[1]//text()[' . $this->getXpath($this->t("TELEPHONE NO")) . ']/following::text()[normalize-space(.)][1]');

        if (!empty($pickupPhone)) {
            $r->pickup()->phone($pickupPhone);
        }
        // PickupHours
        $pickupHours = $this->http->FindSingleNode('//text()[' . $rulePickUpLoc . ']/ancestor::td[1]/following-sibling::td[1]//text()[' . $this->getXpath($this->t("OPENING HOURS")) . ']/following::text()[normalize-space(.)][1]');

        if (!empty($pickupHours)) {
            $r->pickup()->openingHours($pickupHours);
        }
        // DropoffPhone
        $dropoffPhone = $this->http->FindSingleNode('//text()[' . $ruleReturnLoc . ']/ancestor::td[1]/following-sibling::td[1]//text()[' . $this->getXpath($this->t("TELEPHONE NO")) . ']/following::text()[normalize-space(.)][1]');

        if (!empty($dropoffPhone)) {
            $r->dropoff()->phone($dropoffPhone);
        }
        // DropoffHours
        $dropoffHours = $this->http->FindSingleNode("//text()[{$ruleReturnLoc}]/ancestor::td[1]/following-sibling::td[1]//text()[" . $this->getXpath($this->t("OPENING HOURS")) . "]/following::text()[normalize-space(.)][1]");

        if (!empty($dropoffHours)) {
            $r->dropoff()->openingHours($dropoffHours);
        }

        if ($pickupLoc == $dropoffLoc) {
            $r->setSameLocation(true);
        }

        $xpathFragment1 = '//text()[' . $this->contains($this->t("Your booking details:")) . ']/following::text()[string-length(normalize-space(.))>1]';

        // CarType
        $carType = $this->http->FindSingleNode($xpathFragment1 . '[1]');

        if (!empty($carType)) {
            $r->car()->type($carType);
        }

        // CarModel
        $carModel = $this->http->FindSingleNode($xpathFragment1 . '[2]');

        if (empty($carModel)) {
            $carModel = $this->http->FindSingleNode("//text()[".$this->eq($this->t("CAR"))."]/ancestor::td[1][following-sibling::td[".$this->contains($this->t("Duration"))."]]");
        }

        if (!empty($carModel)) {
            $r->car()->model($carModel);
        }

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("PRICE OF VEHICLE")) . "])[last()]/following::text()[string-length(normalize-space(.))>1][1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[* ]*$/', $payment, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+?)[* ]*$/', $payment, $matches)
        ) {
            // SEK15 511,69    |    1.506,97 USD*
            $currencyCode = preg_match('/^[A-Z]{3}$/', $this->normalizeCurrency($matches['currency']), $m) ? $m[0] : null;
            $r->price()->currency($currencyCode ?? $matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    public function parseHtmlType2(Email $email): void
    {
        $r = $email->add()->rental();

        // Number
        $confNo = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Your AVIS booking Confirmation:"))}]/ancestor::td[1])[1]", null, true, "/(?:{$this->opt($this->t("Your AVIS booking Confirmation:"))})\s*:*\s*([\-A-Z\d]+)\b/u");

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo);
        }

        $traveller = $this->http->FindSingleNode("//text()[(normalize-space(.)='" . $this->t('Name surname') . "')]/following::td[position() <= 2 and not(normalize-space(.)=':')]");

        if (!empty($traveller)) {
            $r->general()->traveller(preg_replace("/^{$this->opt($this->namePrefixes)}[,.\s]+(.{2,})$/", '$1', $traveller), true);
        }

        // PickupDatetime
        $pickupDate = strtotime($this->normalizeDate($this->getField($this->t("PICK UP DATE"))));

        if (!empty($pickupDate)) {
            $r->pickup()->date($pickupDate);
        }

        // PickupLocation
        $pickupLoc = $this->http->FindSingleNode("(//text()[(normalize-space(.)='" . $this->t("Adress") . "')]/ancestor::td[1]/text()[not(not(contains(normalize-space(.),' ')))])[1]");

        if (!empty($pickupLoc)) {
            $r->pickup()->location($pickupLoc);
        }
        // DropoffDatetime
        $dropoffDate = strtotime($this->normalizeDate($this->getField($this->t("RETURN DATE"))));

        if (!empty($dropoffDate)) {
            $r->dropoff()->date($dropoffDate);
        }

        // DropoffLocation
        $dropoffLoc = $this->http->FindSingleNode("(//text()[(normalize-space(.)='" . $this->t("Adress") . "')]/ancestor::td[1]/text()[not(not(contains(normalize-space(.),' ')))])[2]");

        if (!empty($dropoffLoc)) {
            $r->dropoff()->location($dropoffLoc);
        }

        // PickupPhone
        $pickupPhone = $this->http->FindSingleNode('(//text()[(normalize-space(.)="' . $this->t('TELEPHONE NO') . '")]/following::text()[1])[1]');

        if (!empty($pickupPhone)) {
            $r->pickup()->phone($pickupPhone);
        }

        // DropoffPhone
        $dropoffPhone = $this->http->FindSingleNode('(//text()[(normalize-space(.)="' . $this->t('TELEPHONE NO') . '")]/following::text()[1])[2]');

        if (!empty($dropoffPhone)) {
            $r->dropoff()->phone($dropoffPhone);
        }

        // CarType and CarModel
        $car = $this->http->FindSingleNode('//img[contains(@src, "Content/Car")]/ancestor::tr[1]');

        if (!empty($car)) {
            if (preg_match('/(.+)\s\(' . $this->opt($this->t('or')) . '(.+)\)/', $car, $m)) {
                $r->car()
                ->type($m[1])
                ->model($m[2]);
            }
        }

        $carImg = $this->http->FindSingleNode('//img[contains(@src, "Content/Car")]/@src');

        if (!empty($carImg)) {
            $r->car()->image($carImg);
        }

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('PRICE OF VEHICLE')) . "]/following::td[1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[* ]*$/', $payment, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+?)[* ]*$/', $payment, $matches)
        ) {
            // SEK15 511,69    |    1.506,97 USD*
            $currencyCode = preg_match('/^[A-Z]{3}$/', $this->normalizeCurrency($matches['currency']), $m) ? $m[0] : null;
            $r->price()->currency($currencyCode ?? $matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Avis Bookings') !== false
            || strpos($from, 'Reservas Avis') !== false
            || stripos($from, '@avis-europe.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody2) {
            if (is_array($reBody2)) {
                foreach ($reBody2 as $re) {
                    if (stripos($body, $re) !== false) {
                        return true;
                    }
                }
            } else {
                if (stripos($body, $reBody2) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $reBody2) {
            if (is_array($reBody2)) {
                foreach ($reBody2 as $re) {
                    if (stripos($this->http->Response["body"], $re) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            } else {
                if (stripos($this->http->Response["body"], $reBody2) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $email->setType('BookingConfirmation');

        if ($this->http->FindSingleNode("(//text()[(normalize-space(.)='" . $this->t("Adress") . "')]/ancestor::td[1]/text()[not(not(contains(normalize-space(.),' ')))])[1]")) {
            $this->parseHtmlType2($email);
        } else {
            $this->parseHtmlType1($email);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getXpath($str, $node = "normalize-space(.)")
    {
        $res = "";

        if (is_array($str)) {
            $contains = array_map(function ($str) use ($node) {
                return $node . ' = "' . $str . '"';
            }, $str);
            $res .= implode(' or ', $contains);
        } elseif (is_string($str)) {
            $res .= $node . ' = "' . $str . '"';
        }

        return $res;
    }

    private function getField($field, $n = 1)
    {
        $rule = $this->eq($field);

        return trim($this->http->FindSingleNode("(//text()[{$rule}])[{$n}]/following::text()[string-length(normalize-space(.))>1][1]"));
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
        $year = date("Y", $this->date);
        $in = [
            "#^\s*[^\d\s]+,\s+(\d+\s+[^\d\s]+\s+\d{4})\s+(\d+:\d+)\s*$#",
            "#(\d{1,2})\/(\d{1,2})\/(\d{4})[\s]?,[\s]?(\d{1,2}:\d{1,2})[\d:]+$#",
        ];
        $out = [
            "$1, $2",
            "$1-$2-$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'EUR' => ['€'],
            'GBP' => ['£'],
            'TRY' => ['TL'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
