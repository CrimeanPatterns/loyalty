<?php

namespace AwardWallet\Engine\dedge\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "dedge/it-150601878.eml, dedge/it-175199610.eml, dedge/it-176996685.eml, dedge/it-205944502.eml, dedge/it-207052778.eml, dedge/it-78415051.eml, dedge/it-79787197.eml, dedge/it-79863059.eml, dedge/it-80068124.eml, dedge/it-85447221.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "CancelledText"  => 'Your reservation has been cancelled',
            "Reservation N°" => ["Reservation N°", "Reservation No", "Reservation NВ°"], // value  = [A-Za-z\d]{10,}
            //            "Reference" => "", // value  = [A-Z\d]{6}
            "Dear"               => ["Dear", "Hello"],
            "NamePrefix"         => ["Mr.", "Dr.", "Mrs.", "Mstr.", "Miss"],
            "Hotel Information"  => ["Hotel Information", "Hotel information", "Resort information", "Apartment Hotel information"],
            "addressNotContains" => ["The reception"],
            //            "Phone:" => "",
            'Directions' => ['Get directions', 'Directions'],
            "Guest Name" => ["Guest Name", "Guest name"],
            //            "Number of guests" => "",
            //            "adult" => "",
            "child"        => ["infant", "child"],
            "Your booking" => ["Your booking", "Your reservation"],
            //            "room" => "",
            "Check-in"  => ["Check-in", "check-in"],
            "Check-out" => ["Check-out", "check-out"],
            //            "Total price" => '', // before rates
            "Reservation details" => ["Reservation details", "Booking details", "Details of the cancelled reservation"],
            "Room "               => ["Room ", "Apartment "],
            //            "Night " => "",
            "Total price of the reservation" => ['Total price of the reservation', 'Price of the booking', 'Reservation price'],
            "Total amount to be paid"        => ["Total price", "Total amount to be paid"], // on black background, after all rates
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            //            "Cancellation Policy" => "",
            "Traveller(s)" => ["Guest", "Traveller(s)"],
        ],
        "pt" => [
            //            "CancelledText" => '',
            //            "Reservation N°" => [""], // value  = [A-Za-z\d]{10,}
            "Reference"          => "Referência", // value  = [A-Z\d]{6}
            "Dear"               => "Caro",
            "NamePrefix"         => ["Sr."],
            "Hotel Information"  => ["Informações sobre o hotel"],
            "addressNotContains" => ['Recepção '],
            "Phone:"             => "Telefone:",
            'Directions'         => 'Endereço',
            "Guest Name"         => ["Nome do hóspede"],
            "Number of guests"   => "Número de pessoas",
            "adult"              => "adulto",
            //            "child" => "",
            "Your booking"            => ["Sua reserva"],
            "room"                    => "quarto",
            "Check-in"                => ["Data de chegada"],
            "Check-out"               => ["Data de partida"],
            "Total price"             => 'Preço total', // before rates
            "Reservation details"     => ["Detalhes da sua reserva"],
            //            "Room " => "",
            "Night "                         => 'Noite ',
            "Total price of the reservation" => 'Valor da reserva',
            "Total amount to be paid"        => "Valor total a ser pago",
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            "Cancellation Policy" => "Condições de anulação",
            "Traveller(s)"        => ["Viajante(s)", "Guest"],
        ],
        "fr" => [
            //            "CancelledText" => '',
            "Reservation N°"                 => ["Réservation N°"], // value  = [A-Za-z\d]{10,}
            "Reference"                      => "Référence", // value  = [A-Z\d]{6}
            "Dear"                           => ["Cher/ère", "Bonjour"],
            "NamePrefix"                     => ["Mlle", 'Mme', 'M.'],
            "Hotel Information"              => ["Informations sur l'hôtel", 'Information Hotel'],
            "addressNotContains"             => ["La réception est"],
            "Phone:"                         => ["Téléphone:", "Téléphone :"],
            'Directions'                     => 'Directions',
            "Guest Name"                     => ["Nom du client"],
            "Number of guests"               => ["Nombre de personnes", "Nombre de clients"],
            "adult"                          => "adulte",
            "child"                          => ["enfant", "bébé"],
            "Your booking"                   => ["Votre réservation"],
            "room"                           => "chambre",
            "Check-in"                       => ["Date d'arrivée", "Check-in"],
            "Check-out"                      => ["Date de départ", "départ"],
            "Total price"                    => 'Prix total', // before rates
            "Reservation details"            => ["Détails de votre réservation", "Détails de la réservation"],
            "Room "                          => ["Chambre ", "Chambres ", "Suite ", "Douane ", "King "],
            "Night "                         => "Nuit ",
            "Total price of the reservation" => ['Prix de la réservation', 'Montant total de la réservation'],
            "Total amount to be paid"        => ["Montant total à payer", "Montant total à régler", "Prix total"], // on black background, after all rates
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            "Cancellation Policy"     => ["Conditions d'annulation", "Politique d'annulation"],
            "Traveller(s)"            => ["Voyageur(s)", "Client"],
        ],
        "sv" => [
            //            "CancelledText" => '',
            "Reservation N°" => ["Bokningsnr"], // value  = [A-Za-z\d]{10,}
            //            "Reference" => "", // value  = [A-Z\d]{6}
            "Dear" => "Bästa",
            //            "NamePrefix" => [""],
            "Hotel Information"  => ["Hotellinformation"],
            "addressNotContains" => ["Receptionen är öppen"],
            "Phone:"             => "Telefon:",
            //'Directions' => '',
            "Guest Name"        => ["Gästens namn"],
            "Number of guests"  => "Antal gäster",
            "adult"             => ["vuxna", "vuxen"],
            //            "child" => "",
            "Your booking"            => ["Din bokning"],
            "room"                    => "rum",
            "Check-in"                => ["incheckning"],
            "Check-out"               => ["utcheckning"],
            //            "Total price" => '', // before rates
            "Reservation details"            => ["Reservationsdetaljer"],
            "Room "                          => "Rum",
            "Night "                         => "Natt ",
            "Total price of the reservation" => "Totalt pris på bokningen",
            //            "Total amount to be paid" => "",
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            "Cancellation Policy" => "Avbokningspolicy",
            //            "Traveller(s)" => "",
        ],
        "de" => [
            //            "CancelledText" => '',
            "Reservation N°"     => ["Reservierungsnr."], // value  = [A-Za-z\d]{10,}
            "Reference"          => "Referenz", // value  = [A-Z\d]{6}
            "Dear"               => "Sehr geehrte/sehr geehrter",
            "NamePrefix"         => ["Herr", "Frau"],
            "Hotel Information"  => ["Hotelinformationen", "Informationen über das Hotel"],
            "addressNotContains" => ["Die Rezeption ist"],
            "Phone:"             => "Telefon:",
            'Directions'         => 'Lage',
            "Guest Name"         => ["Gästename", "Name des Kunden"],
            "Number of guests"   => ["Anzahl der Gäste", "Personenanzahl"],
            "adult"              => "Erwachsene",
            //            "child" => "",
            "Your booking"                   => ["Ihre Reservierung"],
            "room"                           => "Zimmer",
            "Check-in"                       => ["Check-In", "Anreisedatum"],
            "Check-out"                      => ["Check-Out", "Abreisedatum"],
            "Total price"                    => 'Gesamtpreis', // before rates
            "Reservation details"            => ["Reservierungsangaben", "Detailangaben zu Ihrer Buchung"],
            "Room "                          => "Zimmer ",
            "Night "                         => ["Übernachtung ", "Nacht "],
            "Total price of the reservation" => ['Gesamtpreis der Buchung.', 'Preis der Reservierung'],
            "Total amount to be paid"        => ["Zu zahlender Gesamtbetrag"],
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            "Cancellation Policy" => "Stornierungsbedingungen",
            "Traveller(s)"        => "Reisende(r)",
        ],
        "it" => [
            "CancelledText"                  => ["La prenotazione è stata annullata", 'La prenotazione è stata cancellata'],
            "Reservation N°"                 => "N. prenotazione", // value  = [A-Za-z\d]{10,}
            "Reference"                      => "Numero prenotazione", // value  = [A-Z\d]{6}
            "Dear"                           => "Gentile",
            "NamePrefix"                     => ["Sig."],
            "Hotel Information"              => ["Informazioni hotel", "Informazioni sull’hotel"],
            "addressNotContains"             => ["Il ricevimento è aperto"],
            "Phone:"                         => "Telefono:",
            'Directions'                     => 'Indicazioni stradali',
            "Guest Name"                     => "Nome del cliente",
            "Number of guests"               => ["Numero di ospiti", "Ospiti"],
            "adult"                          => "adulti",
            "child"                          => "bambini",
            "Your booking"                   => "La tua prenotazione",
            "room"                           => "camera",
            "Check-in"                       => ["Arrivo", "Data di arrivo"],
            "Check-out"                      => ["partenza", "Data di partenza"],
            "Total price"                    => 'Totale da pagare', // before rates
            "Reservation details"            => "Dettagli della prenotazione",
            "Room "                          => "Camera ",
            "Night "                         => "Notte ",
            "Total price of the reservation" => ["Prezzo totale della prenotazione", "Reservation price"],
            "Total amount to be paid"        => "Prezzo totale", // on black background, after all rates
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            "Cancellation Policy" => ["Politica di cancellazione", "Condizioni di annullamento"],
            "Traveller(s)"        => ["Guest", 'Ospite'],
        ],
        "zh" => [
            //            "CancelledText" => '',
            "Reservation N°" => ["訂房代號N°"], // value  = [A-Za-z\d]{10,}
            //            "Reference" => "", // value  = [A-Z\d]{6}
            "Dear"              => ["親愛的", "Dear"],
            "NamePrefix"        => ["小姐", '先生'],
            "Hotel Information" => ["飯店資訊", "度假村資訊"],
            "Phone:"            => "電話：",
            //'Directions' => '',
            "Guest Name"        => ["客人姓名"],
            "Number of guests"  => "客人人數",
            "adult"             => "成人",
            //            "child" => "",
            "Your booking"                   => ["預訂日期"],
            "room"                           => "客房",
            "Check-in"                       => ["入住"],
            "Check-out"                      => ["退房"],
            "Total price"                    => '合計價格', // before rates
            "Reservation details"            => ["預訂資訊"],
            "Room "                          => ["客房", "住宿"],
            "Night "                         => "晚",
            "Total price of the reservation" => '預訂合計金額',
            "Total amount to be paid"        => "需支付總額為", // on black background, after all rates
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            "Cancellation Policy" => "取消政策",
            //            "Traveller(s)" => "",
        ],
        "es" => [
            //            "CancelledText" => '',
            "Reservation N°"                 => ["Reserva n.º"], // value  = [A-Za-z\d]{10,}
            "Reference"                      => "Referencia", // value  = [A-Z\d]{6}
            "Dear"                           => ["Estimado/a", "Hola,"],
            "NamePrefix"                     => ["Srta.", "Señor"],
            "Hotel Information"              => ["Información sobre el hotel", "Datos del hotel"],
            "addressNotContains"             => ["La recepción está abierta "],
            "Phone:"                         => "Teléfono:",
            //'Directions' => '',
            "Guest Name"                     => ["Nombre del cliente"],
            "Number of guests"               => ["Número de personas", "Número de huéspedes"],
            "adult"                          => ["adulto", "adultos"],
            "child"                          => "niño",
            "Your booking"                   => ["Su reserva"],
            "room"                           => "habitación",
            "Check-in"                       => ["Fecha de llegada", "registro de entrada"],
            "Check-out"                      => ["Fecha de salida", "salida"],
            "Total price"                    => 'Precio total', // before rates
            "Reservation details"            => ["Información de reserva", "Detalles de la reserva"],
            "Room "                          => "Habitación ",
            "Night "                         => "Noche ",
            "Total price of the reservation" => 'Precio de la reserva',
            "Total amount to be paid"        => "Importe total a pagar", // on black background, after all rates
            //            "eq. to" => "",
            //            "Points information" => '',
            //            "Burnt points" => '',
            //            "Paid by cash" => "",
            "Cancellation Policy" => ["Condiciones de cancelación", "Política de cancelación"],
            "Traveller(s)"        => "Viajero(s)",
        ],
    ];

    private $detectFrom = ["@availpro.com", "@d-edge.com"];
    private $detectSubject = [
        // en
        "Booking confirmation - Reference:",
        "Booking confirmation for",
        "Booking cancellation - Reference:",
        // pt
        "Confirmação de reserva - Referência:",
        // fr
        "Confirmation de réservation - Référence:",
        "Confirmation de réservation pour",
        // sv
        "Bokningsbekräftelse för",
        // de
        "Ihre Buchungsbestätigung für",
        "Reservierungsbestätigung - Referenz:",
        // zh
        '預訂確認',
        // es
        'Confirmación de reserva - Referencia:',
        'Confirmación de reserva para',
        // it
        'Conferma prenotazione - Riferimento:',
        'Annullamento della prenotazione - ',
    ];

    private $detectCompany = [
        'www.book-secure.com',
        '.secure-hotel-booking.com',
        '.availpro.com',
    ];

    private $detectBody = [
        "en" => ["Booking details", "Reservation details", "Details of the cancelled reservation"],
        "pt" => ["Detalhes da sua reserva"],
        "fr" => ["Informations sur l'hôtel", "Détails de la réservation", "Détails de votre réservation"],
        "sv" => ["Reservationsdetaljer"],
        "de" => ["Hotelinformationen", 'Informationen über das Hotel'],
        "it" => ["Ulteriori informazioni", "Informazioni hotel", "Informazioni sull’hotel"],
        "zh" => ["預訂資訊"],
        "es" => ["Detalles de la reserva", "Su reserva está"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->striposAll($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (mb_stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "]")->length === 0
            && $this->http->XPath->query("//*[" . $this->contains($this->detectCompany) . "]")->length === 0
            && $this->http->XPath->query("//img[" . $this->contains($this->detectCompany, '@src') . "]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
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

    public function cost($str, $thousands = ',', $decimals = '.')
    {
        if ($thousands === $decimals) {
            return null;
        }
        $str = trim(preg_replace('/\s+/', '', $str));
        $decimals = preg_quote($decimals);

        if (preg_match("#$decimals\d+$#", $str)) {
            // .7 -> 0.7
            $str = '0' . $str;
        }

        if (!preg_match('/^\d/', $str)) {
            return null;
        }
        $thousands = preg_quote($thousands);
        $thousandsRe = "/$thousands(\d{3})/";
        $str = preg_replace($thousandsRe, '\1', $str);

        $decimalsPresent = preg_match('/[^\d]\d+$/', $str);
        $decimalsRe = "/$decimals(\d+)$/";

        if ($decimalsPresent && !preg_match($decimalsRe, $str)) {
            return null;
        }
        $str = preg_replace($decimalsRe, '.\1', $str);

        if (is_numeric($str)) {
            return (float) $str;
        }

        return null;
    }

    private function parseHotel(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Reservation N°")) . "]", null, true,
            "/" . $this->preg_implode($this->t("Reservation N°")) . "\s(\w{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reference")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\w{5,})\s*$/");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Information")) . "]/preceding::text()[" . $this->starts($this->t("Reservation N°")) . "][1]", null, true,
                "/" . $this->preg_implode($this->t("Reservation N°")) . "\s(\w{5,})\s*$/");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Information")) . "]/preceding::text()[" . $this->eq($this->t("Reference")) . "][1]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\w{5,})\s*$/");
        }
        $email->ota()
            ->confirmation($conf);

        // HOTEL

        $h = $email->add()->hotel();
        // General

        $travellers = array_unique(array_filter(array_merge(
            [$this->http->FindSingleNode("//td[" . $this->eq($this->t("Guest Name")) . "]/following-sibling::td[normalize-space()][1]")],
            $this->http->FindNodes("//tr[not(.//tr) and " . $this->eq($this->t("Traveller(s)")) . "]/following-sibling::tr[position()>1]", null,
                "/^\s*(?:[[:alpha:]]{1,3}\.|{$this->opt($this->t("NamePrefix"))}) (.+?)(?:\s*\(\w+\))?\s*$/u"),
            $this->http->FindNodes("//table[" . $this->eq($this->t("Traveller(s)")) . "]/following-sibling::table[position()>1]", null,
                "/^\s*(?:[[:alpha:]]{1,3}\.|{$this->opt($this->t("NamePrefix"))}) (.+?)(?:\s*\(\w+\))?\s*$/u")
        )));
        $travellers = preg_replace("/^\s*(.+?)\s*\(.*\)$/", '$1', $travellers);

        if (count($travellers) == 0) {
            $travellers = array_filter([$this->http->FindSingleNode("//text()[" . $this->starts($this->t('Dear')) . "]", null, true, "#{$this->opt($this->t('Dear'))}(?: +[[:alpha:]]{1,4}\.| *{$this->opt($this->t("NamePrefix"))})?\s*(.+)[,:，]\s*$#u")]);
        }

        $h->general()
            ->noConfirmation()
            ->travellers($travellers, true);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('CancelledText'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $cancellation = $this->http->FindSingleNode("//td[not(.//td) and {$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()='Special sales and cancellation terms and conditions'][1]/preceding::text()[starts-with(normalize-space(), 'As soon')]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Cancellation Policy")) . "]/following::text()[normalize-space()][1]/ancestor::table[1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Cancellation Policy")) . "]]", null, true, "/^\s*" . $this->preg_implode($this->t("Cancellation Policy")) . "\s*(.+)/");
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Information")) . "]/preceding::text()[normalize-space()][1]");

        $hotelXpath = "//text()[" . $this->eq($this->t("Hotel Information")) . "]/following::tr[1][td[normalize-space()][2][" . $this->contains(['Email', 'email', 'e-mail', 'E-post', 'E-Mail', 'correo electrónico', '飯店電子郵件']) . "]]";

        $hotelInfo = implode(' ', $this->http->FindNodes($hotelXpath . "/td[1]/descendant::text()[normalize-space()][position() < last() or (position() = last() and not(ancestor::a[contains(@href, 'goo')])) ]"));

        if (empty($hotelInfo)) {
            $hotelXpath = "//text()[{$this->eq($this->t("Hotel Information"))}]";
            $hotelInfo = $this->http->FindSingleNode($hotelXpath . "/following::text()[normalize-space()][1]/ancestor::td[1]");
        }

        if ($hotelName && preg_match("/^\s*" . $this->preg_implode($hotelName) . "\s+(.+)$/su", $hotelInfo, $m)) {
            $m[1] = preg_replace("/\s*\b{$this->opt($this->t("addressNotContains"))}.*/s", '', $m[1]);
            $h->hotel()
                ->name($hotelName)
                ->address($m[1]);
        } elseif ($hotelName && preg_match("/^\s*" . $this->preg_implode($hotelName) . "\s*$/su", $hotelInfo, $m)) {
            $hotelInfo = implode(' ', $this->http->FindNodes($hotelXpath . "/td[1]//text()[normalize-space()]"));

            if (preg_match("/^\s*" . $this->preg_implode($hotelName) . "\s+(.+)$/su", $hotelInfo, $m)) {
                $m[1] = preg_replace("/\s*\b{$this->opt($this->t("addressNotContains"))}.*/s", '', $m[1]);
                $h->hotel()
                    ->name($hotelName)
                    ->address($m[1]);
            }
        }
        $phone = $this->http->FindSingleNode($hotelXpath . "//td[normalize-space()][2]//text()[" . $this->eq($this->t("Phone:")) . "]/following::text()[normalize-space()][1]",
            null, true, "/^([\d \-\+\(\)\.]*\d+[\d \-\+\(\)\.]*)$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode($hotelXpath . "/following::text()[normalize-space()='Teléfono:'][1]/following::text()[normalize-space()][1]");
        }

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $notes = $this->http->FindNodes("//p[{$this->eq($this->t('Directions'))}]/following::p[normalize-space()][following::text()[{$this->eq($this->t("Reservation details"))}]]");

        if (count($notes) > 0) {
            $direction = implode(', ', $notes);

            if (strcasecmp(preg_replace("/\W/", '', $h->getHotelName() . $h->getAddress()), preg_replace("/\W/", '', $direction)) !== 0) {
                $h->general()
                    ->notes(str_replace('>', '. ', $direction));
            }
        }

        // Booked
        $checkin = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Check-in")) . "]/ancestor::tr[1][" . $this->starts($this->t("Check-in")) . "])[1]");

        if (preg_match("/" . $this->preg_implode($this->t("Check-in")) . "\s*(?:\([^\d\)]*\b(\d{1,2}:\d{2}(?: *[apAP][mM])?)\D*\s*\))?\s*(.+)/", $checkin, $m)) {
            $h->booked()->checkIn($this->normalizeDate($m[2] . ", " . $m[1]));
        }
        $checkout = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Check-out")) . "]/ancestor::tr[1])[1]");

        if (preg_match("/" . $this->preg_implode($this->t("Check-out")) . "\s*(?:\([^\d\)]*\b(\d{1,2}:\d{2}(?: *[apAP][mM])?)\D*\s*\))?\s*(.+)/", $checkout, $m)) {
            $h->booked()->checkOut($this->normalizeDate($m[2] . ", " . $m[1]));
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//td[" . $this->eq($this->t("Number of guests")) . "]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*(\d+)\s*" . $this->preg_implode($this->t("adult")) . "/"), true, true)
            ->kids($this->http->FindSingleNode("//td[" . $this->eq($this->t("Number of guests")) . "]/following-sibling::td[normalize-space()][1]",
                null, true, "/(?:^|,)\s*(\d+)\s*" . $this->preg_implode($this->t("child")) . "/"), true, true)
            ->rooms($this->http->FindSingleNode("//td[" . $this->eq($this->t("Your booking")) . "]/following-sibling::td[normalize-space()][1]",
                null, true, "/\b(\d+)\s*" . $this->preg_implode($this->t("room")) . "/"), true, true);

        // Rooms

        // Type 1
        //  Room 1          Deluxe Ocean Facing                     $1,243.28
        //  2 adults        Bedding options: Double-double
        //                  Non Refundable Rate with Breakfast
        $rXpath = "//text()[" . $this->eq($this->t("Reservation details")) . "]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/tr[(count(td[normalize-space()])) = 3 and " . $this->starts($this->t("Room ")) . "]";
        $nodes = $this->http->XPath->query($rXpath);

        foreach ($nodes as $root) {
            $room = $h->addRoom();
            $room
                ->setType($this->http->FindSingleNode("td[2]/descendant::text()[normalize-space()][1]", $root))
            ;
        }

        // Type 2
        //  1 x Double Studio           € 60
        // Package
        // Promotional rate

        $totalContains = $this->starts($this->t("Total amount to be paid"))
            . " or " . $this->eq($this->t("Total price"))
            . " or " . $this->eq($this->t("Total price of the reservation"))
            . " or " . $this->eq($this->t("Burnt points"))
        ;

        $rXpath = "//text()[" . $this->eq($this->t("Reservation details")) . "]/following::text()[contains(normalize-space(), 'x')][ancestor::*[contains(@style, 'bold')]][following::text()[{$totalContains}]]/ancestor::tr[1]/ancestor::*[1]/tr[(count(td[normalize-space()])) = 2]";
        $nodes = $this->http->XPath->query($rXpath);

//        $rXpath = "//text()[{$this->eq($this->t("Reservation details"))}]/following::text()[{$this->contains($this->t('Room '))}][1]/ancestor::tr[1]/td[2]";
        if ($nodes->length == 0) {
            $rXpath = "//text()[{$this->eq($this->t("Reservation details"))}]/following::text()[{$this->contains($this->t('Room '))}][1]/ancestor::tr[1]/td[2]";
            $nodes = $this->http->XPath->query($rXpath);
        }

        foreach ($nodes as $root) {
            if ($this->http->FindSingleNode("td[2]", $root, true, "/(?:^\s*\d+|\d+\s*$)/")
                && preg_match("/^(\d+) x (.+)/", $this->http->FindSingleNode("td[1]", $root), $m)) {
                for ($i = 1; $i <= $m[1]; $i++) {
                    $room = $h->addRoom();
                    $room
                        ->setType($m[2])
                    ;
                }
            }
        }

        if ($h->getRoomsCount() == 1) {
            $rates = $this->http->FindNodes("//tr[not(.//tr) and td[1][" . $this->starts($this->t("Night ")) . " and contains(.,':')]]/td[2]");

            if (empty($rates)) {
                $rates = $this->http->FindNodes("//tr[not(.//tr) and td[1][" . $this->starts(preg_replace('/(\s+)$/', '$1#', $this->t("Night ")), 'translate(normalize-space(),"123456789","#########")') . "]]/td[2]");
            }

            if (isset($room)) {
                $room->setRates($rates);
            }
        }

        // Price
        $points = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Burnt points")) . "]/following-sibling::td[normalize-space()][1]");

        if (is_numeric($points)) {
            $account = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Points information")) . "]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/");
            $h->program()
                ->account($account, false);
            $h->price()
                ->spentAwards($points);
            $totalStr = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Paid by cash")) . "]/following-sibling::td[normalize-space()][1]");

            if (preg_match("/^(?<curr>[^\d)(]{1,5}?)\s*(?<amount>\d[,.\'\d ]*)\s*(?:" . $this->preg_implode($this->t("eq. to")) . "|$)/",
                    $totalStr, $m)
                || preg_match("/^(?<amount>\d[,.\'\d ]*)\s*(?<curr>[^\d)(]{1,5})\s*(?:" . $this->preg_implode($this->t("eq. to")) . "|$)/",
                    $totalStr, $m)
            ) {
                $currency = $this->currency($m['curr']);
                $h->price()
                    ->total($this->amount($m['amount'], $currency))
                    ->currency($currency);
            }
        } else {
            $totalStr = $this->http->FindSingleNode("(//td[not(.//td) and " . $this->eq($this->t("Total amount to be paid")) . "])[last()]/following-sibling::td[normalize-space()][1]");

            if (empty($totalStr)) {
                $totalStr = $this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("Total price")) . " or " . $this->starts(preg_replace('/(.+)/', '$1 (', $this->t("Total price"))) . " or " . $this->starts(preg_replace('/(.+)/', '$1(', $this->t("Total price"))) . "]/following-sibling::td[normalize-space()][1]");
            }

            if (empty($totalStr)) {
                $totalStr = $this->http->FindSingleNode("//td[not(.//td)][" . $this->eq($this->t("Total price of the reservation")) . " or " . $this->starts(preg_replace('/(.+)/', '$1 (', $this->t("Total price of the reservation"))) . " or " . $this->starts(preg_replace('/(.+)/', '$1(', $this->t("Total price of the reservation"))) . "]/following-sibling::td[normalize-space()][1]");
            }

            if (preg_match("/^(?<curr>[^\d)(]{1,5}?)\s*(?<amount>\d[,.\'\d ]*)\s*(?:" . $this->preg_implode($this->t("eq. to")) . "|\(|$)/",
                    $totalStr, $m)
                || preg_match("/^(?<amount>\d[,.\'\d ]*)\s*(?<curr>[^\d)(]{1,5})\s*(?:" . $this->preg_implode($this->t("eq. to")) . "|\(|$)/",
                    $totalStr, $m)
            ) {
                $currency = $this->currency($m['curr']);
                $h->price()
                    ->total($this->amount($m['amount'], $currency))
                    ->currency($currency);
            }
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return false;
        }
        //it
        if (preg_match("/Quest'offerta può essere cancellata o modificata gratuitamente fino al (?<date>[\d\-]{6,10})\, (?<time>\d{1,2}\:\d{1,2})/iu", $cancellationText, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        }

        if (
               preg_match("/Free cancellation before (?<date>[\d\/]{6,10})\s*\./i", $cancellationText, $m)
            || preg_match("/This offer can be cancelled or modified free of charge (?:until|before) (?<date>[\d\-]{6,10}, *\d{1,2}:\d{2})\s*\(/i", $cancellationText, $m)
            || preg_match("/Free cancellation before (?<date>\d+\s*\w+\s*\d{4})/i", $cancellationText, $m)
            // pt
            || preg_match("/Cancelamento gratuito antes de (?<date>[\d\/]{6,10}) pelas (?<time>\d{1,2}:\d{1,2})\./i", $cancellationText, $m)
            || preg_match("/Cancelamento gratuito antes de (?<date>[\d\-]{6,10})/i", $cancellationText, $m)
            // de
            || preg_match("/Dieses Angebot kann bis zum (?<date>[\d\-]{6,10}), (?<time>\d{1,2}:\d{1,2})\s*\([^)]+\)\s*kostenlos storniert oder geändert werden\./i", $cancellationText, $m)
            || preg_match("/ Stornierung kostenlos bis zum (?<date>[\d\.]{6,10})\./i", $cancellationText, $m)
            // fr
            || preg_match("/Cette offre est annulable et modifiable sans frais jusqu’au (?<date>[\d\-]{6,10}), (?<time>\d{1,2}:\d{1,2})\s*\([^)]+\)\s*\./i", $cancellationText, $m)
            || preg_match("/Annulation gratuite avant le (?<date>[\d\/]{6,10})\./i", $cancellationText, $m)
            || preg_match("/Annulation gratuite avant le (?<date>[\d\/]{6,10}) à (?<time>\d{1,2}h\d{1,2})/i", $cancellationText, $m)
        ) {
            if (isset($m['time'])) {
                $m['time'] = str_replace('h', ':', $m['time']);
            }

            if (in_array($this->lang, ['pt', 'fr', 'pt'])) {
                $m['date'] = str_replace(['/', '-'], '.', $m['date']);
            }
            $h->booked()
                ->deadline(strtotime($m['date'] . (isset($m['time']) ? ', ' . $m['time'] : '')));

            return true;
        }

        if (
               preg_match("/This reservation cannot be cancelled nor modified\./i", $cancellationText, $m)
            || preg_match("/As soon as (?:the )?booking is made, 100% of the booking amount is charged and non-refundable\./i", $cancellationText, $m)
            || preg_match("/Chambre Deluxe Dès la réservation effectuée\, 100[%] de la réservation sont facturés et non remboursables\./i", $cancellationText, $m)
            || preg_match("/Esta reserva no puede cancelarse ni modificarse/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();

            return true;
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
            // 18 de fevereiro de 2020, 12:00
            // jueves, 18 de agosto de 2022, 14:00
            "#^\s*(?:\D+,\s*)?(\d{1,2}) de ([^\s\d]+) de (\d{4})\s*, \s*(\d+:\d+(?: *[ap]m)?)\s*$#iu",
            // samedi 10 juillet 2021, 14:00
            // Sonntag, 3. Juli 2022, 11:00
            "#^\s*[[:alpha:]]+,?\s+(\d{1,2}).? ([^\s\d]+) (\d{4})\s*, \s*(\d+:\d+(?: *[ap]m)?)\s*$#iu",
            // 2020年4月26日星期日, 11:00
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\D*\b(\d+:\d+)\s*$/u',
            // samedi 10 juillet 2021,
            "#^\s*[[:alpha:]]+,?\s+(\d{1,2}).? ([^\s\d]+) (\d{4})\s*, $#iu",
            //2022-07-16, 12:00
            "#^(\d{4})\-(\d+)\-(\d+)\,\s*([\d\:]+)$#u",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$3-$2-$1, $4",
            "$1 $2 $3",
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return (!empty($str)) ? strtotime($str) : null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($priceText = '', $currency)
    {
        $price = PriceHelper::parse($priceText, $currency);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'NZ$'  => 'NZD',
            'AU$'  => 'AUD',
            'US$'  => 'USD',
            'HK$'  => 'HKD',
            '€'    => 'EUR',
            '$'    => 'USD',
            '£'    => 'GBP',
            'CFPF' => 'XPF',
            '฿'    => 'THB',
            '¥'    => 'JPY',
            'NT$'  => 'TWD',
            'JP¥'  => 'JPY',
            'CA$'  => 'CAD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
