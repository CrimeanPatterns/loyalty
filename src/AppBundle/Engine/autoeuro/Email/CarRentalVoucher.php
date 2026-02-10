<?php

namespace AwardWallet\Engine\autoeuro\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRentalVoucher extends \TAccountChecker
{
    public $mailFiles = "autoeuro/it-12528880.eml, autoeuro/it-134846130.eml, autoeuro/it-16484633.eml, autoeuro/it-209023397.eml, autoeuro/it-24674948.eml, autoeuro/it-24699595.eml, autoeuro/it-36173578.eml, autoeuro/it-37390279.eml, autoeuro/it-37868912.eml, autoeuro/it-50861025.eml, autoeuro/it-60203361.eml"; // +2 bcdtravel(html,pdf)[no]

    public $lang = "en";

    public static $dictionary = [
        "nl" => [
            'Confirmed Reservation' => '',
            'Voucher #'             => 'Voucher',
            'Confirmation #'        => 'Bevestigingsnummer',
            'PICK-UP'               => 'OPHALEN',
            'DROP-OFF'              => 'INLEVEREN',
            'Tel:'                  => 'Tel:',
            'OPEN'                  => 'Geopend',
            'Currency'              => 'Valuta',
            'Base Rate'             => 'Basis prijs', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'           => 'Opmerkingen',
            'Driver Information'   => 'Bestuurder informatie',
            'voucher number'       => 'Vouchernummer',
            'reservation number'   => 'Reserveringsnummer',
            'carModelMarker'       => ['of gelijkwaardig'],
            'Rental Period'        => ['Huurperiode'],
            'Less Discount Of'     => 'Minus korting van',
            'Adjusted Total'       => 'Totaalbedrag',
            'Pick-up information'  => 'Informatie voor het ophalen',
            'Rental Company:'      => 'Verhuurder:',
            //'Voucher Cancelled:' => '',
        ],
        "no" => [
            'Confirmed Reservation' => 'Bestillingsbekreftelse',
            'Voucher #'             => 'Voucher',
            'Confirmation #'        => 'Bekreftelse',
            'PICK-UP'               => 'Avhenting',
            'DROP-OFF'              => 'Avlevering',
            'Tel:'                  => 'Tel:',
            'OPEN'                  => 'Åpen',
            'Currency'              => 'Valuta',
            'Base Rate'             => 'basisrate', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'         => 'Kommentarer::',
            'Driver Information' => 'Informasjon til sjfr',
            'voucher number'     => 'Voucher Nummer',
            'reservation number' => 'Reservasjonsnummer',
            //            'carModelMarker' => ['', ''],
            //            'Rental Period'        => [''],
            //            'Less Discount Of' => '',
            'Adjusted Total'      => 'Justert totalpris',
            'Pick-up information' => 'Avhentingsinformasjon',
            //'Rental Company:' => '',
            //'Voucher Cancelled:' => '',
        ],
        "de" => [
            'Confirmed Reservation' => 'Reservierung bestätigt',
            'Voucher #'             => 'Voucher',
            'Confirmation #'        => 'Bestätigung #',
            'PICK-UP'               => ['Abholung', 'ABHOLUNG'],
            'DROP-OFF'              => ['Rückgabe', 'RüCKGABE'],
            'Tel:'                  => 'Tel:',
            'OPEN'                  => 'Geöffnet',
            'Currency'              => 'Währung',
            'Base Rate'             => 'Mietpreis', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'            => 'Anmerkungen::',
            'Driver Information'    => 'Informationen zum Fahrer',
            'voucher number'        => 'Voucher Nummer',
            'reservation number'    => 'Reservierungsnummer',
            'carModelMarker'        => 'o.ä.',
            'Rental Period'         => ['Mietdauer'],
            'Less Discount Of'      => 'Minus Rabatt von',
            'Adjusted Total'        => 'Gesamtpreis',
            'Pick-up information'   => 'Informationen zur Abholung',
            'Rental Company:'       => 'Vermieter:',
            //'Voucher Cancelled:' => '',
        ],
        "es" => [
            'Confirmed Reservation' => 'Confirmación de la reserva',
            'Voucher #'             => 'Bono de reserva #',
            'Confirmation #'        => 'Confirmación #',
            'PICK-UP'               => 'RECOGIDA',
            'DROP-OFF'              => 'DEVOLUCIÓN',
            'Tel:'                  => 'Tel:',
            'OPEN'                  => 'Abierto',
            'Currency'              => 'Moneda',
            'Base Rate'             => 'Tarifa base', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'            => 'Comentarios::',
            'Driver Information'    => 'Información sobre el conductor',
            'voucher number'        => ' Número de voucher',
            'reservation number'    => 'Número de reserva',
            'carInfoTableSplitter'  => ['básico con'],
            'carModelMarker'        => 'o similar',
            //            'Rental Period'        => [''],
            'Less Discount Of'     => 'Descuento',
            'Adjusted Total'       => 'Precio total',
            'Pick-up information'  => 'Información sobre la recogida',
            //'Rental Company:' => '',
        ],
        "en" => [
            'Voucher #'      => ['Voucher #', 'Voucher', 'AUTO EUROPE VOUCHER', 'Reference', 'Auto Europe Voucher', 'Driveaway Voucher:'],
            'Confirmation #' => ['Confirmation #', 'HERTZ Confirmation', 'HERTZ CONFIRMATION', 'EUROPCAR CONFIRMATION', 'EUROPCAR Confirmation', 'GUERIN Confirmation'],
            'PICK-UP'        => ['PICK UP LOCATION', 'PICK-UP', 'Pick-Up', 'PICK UP', 'Pick Up', 'Pick up'],
            'DROP-OFF'       => ['DROP OFF LOCATION', 'DROP-OFF', 'Drop-Off', 'DROP OFF', 'Drop Off', 'Drop off'],
            'Tel:'           => ['Tel:', 'Tel.'],
            'OPEN'           => ['OPEN', 'CLOSED', 'By Appointment Only'],
            'Base Rate'      => ['Base Rate', 'Rate'],
            'noOtaConfirm'   => 'we recommend completing the booking process as soon as possible',
            // pdf
            'voucher number'        => ['voucher number', 'invoice number'],
            'carInfoTableSplitter'  => ['Basic Rental', 'similar'],
            'carModelMarker'        => ['or similar'],
            'Rental Period'         => ['Rental Period'],
            'Adjusted Total'        => ['Adjusted Total', 'Gross Total'],
            'Comments::'            => ['Comments::', 'Comments:'],
            //'Rental Company:' => '',
            //'Voucher Cancelled:' => '',
        ],
        "fr" => [
            'Confirmed Reservation' => 'Réservation confirmée',
            'Voucher #'             => 'Bon de réservation #',
            //'Confirmation #' => 'Confirmación #',
            'PICK-UP'   => 'PRISE EN CHARGE',
            'DROP-OFF'  => 'RESTITUTION',
            'Tel:'      => ['Tel:', 'Tel.', 'Tél.'],
            'OPEN'      => 'Ouvert',
            'Currency'  => 'Devise',
            'Base Rate' => 'Prix de base', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'            => ['Commentaires::::', 'Commentaires :'],
            'Driver Information'    => 'Informations conducteur',
            'voucher number'        => 'Bon de réservation',
            'reservation number'    => 'Numéro de réservation',
            'carModelMarker'        => ['ou similaire'],
            'Rental Period'         => ['Durée totale de la location'],
            'Less Discount Of'      => 'Remise de',
            'Adjusted Total'        => 'Total',
            'Pick-up information'   => 'Informations sur la prise en charge du véhicule',
            'Rental Company:'       => 'Entreprise de location:',
            //'Voucher Cancelled:' => '',
        ],
        "da" => [
            'Confirmed Reservation' => 'Bekræftet reservation',
            'Voucher #'             => 'Voucher #',
            'Confirmation #'        => 'Bekræftelse #',
            'PICK-UP'               => 'AFHENTNING',
            'DROP-OFF'              => 'AFLEVERING',
            //            'Tel:'      => ['Tel:', 'Tel.'],
            'OPEN'      => 'åbent',
            'Currency'  => 'Valuta',
            'Base Rate' => 'Pris', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'            => 'Kommentarer::::',
            'Driver Information'    => 'Førerinformation',
            'voucher number'        => 'Vouchernummer',
            'reservation number'    => 'Reservationsnummer',
            'carModelMarker'        => ['eller lign.'],
            'Rental Period'         => ['Lejeperiode'],
            'Less Discount Of'      => 'Rabat',
            'Adjusted Total'        => 'Justeret pris',
            'Pick-up information'   => 'Afhentningsinformation',
            'Rental Company:'       => 'udlejningsfirma:',
            //'Voucher Cancelled:' => '',
        ],
        "it" => [
            'Confirmed Reservation' => 'Prenotazione Confermata',
            'Voucher #'             => 'Voucher #',
            'Confirmation #'        => 'Conferma #',
            'PICK-UP'               => 'RITIRO',
            'DROP-OFF'              => 'RICONSEGNA',
            //            'Tel:'      => ['Tel:', 'Tel.'],
            'OPEN'      => 'aperto',
            'Currency'  => 'Valuta',
            'Base Rate' => 'Tariffa originale', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'            => 'Commenti::::',
            'Driver Information'    => 'Informazioni sul guidatore',
            'voucher number'        => 'Numero di voucher',
            'reservation number'    => 'Numero di prenotazione',
            'carModelMarker'        => ['o similare'],
            'Rental Period'         => ['Periodo di noleggio'],
            'Less Discount Of'      => 'Meno sconto di',
            'Adjusted Total'        => 'Prezzo totale',
            'Pick-up information'   => 'Informazioni sul ritiro',
            'Rental Company:'       => 'Noleggiatore:',
            //'Voucher Cancelled:' => '',
        ],
        "pt" => [
            'Confirmed Reservation' => 'Confirmação da Reserva',
            'Voucher #'             => 'Voucher #',
            'Confirmation #'        => 'Confirmação #',
            'PICK-UP'               => 'LEVANTAMENTO',
            'DROP-OFF'              => 'DEVOLUÇÃO',
            //            'Tel:'      => ['Tel:', 'Tel.'],
            'OPEN'      => 'aberto',
            'Currency'  => 'Moeda',
            'Base Rate' => 'Tarifa base', // as total in html and as cost in pdf
            //            'noOtaConfirm' => '',
            // pdf
            'Comments::'            => 'Comentarios::::',
            'Driver Information'    => 'Informações do condutor',
            'voucher number'        => 'Numero de Voucher',
            'reservation number'    => 'Número de reserva',
            'carModelMarker'        => ['ou similar'],
            'Rental Period'         => ['Periodo de Aluguer'],
            'Less Discount Of'      => 'Menos disconto de',
            'Adjusted Total'        => 'Soma Total',
            'Pick-up information'   => 'Informações para levantamento',
            'Rental Company:'       => 'Locadora:',
            //'Voucher Cancelled:' => '',
        ],
    ];

    protected $pdfPattern = ".*\.pdf";

    private $detectFrom = [
        "autoeuro"  => "@autoeurope.",
        "driveaway" => "@driveawayres.com",
    ];
    private $detectSubject = [
        "no" => ["Din bookingbekreftelse"],
        "en" => ["Car Rental Voucher #", " - Voucher # ", "Your Booking Confirmation"],
        "fr" => ["La confirmation de votre réservation"],
        "nl" => ["Uw reserveringsbevestiging"],
        "de" => ["Ihre Buchungsbestätigung"],
        "da" => ["Din reservationsbekræftelse"],
        "it" => ["La sua conferma di prenotazione"],
        "pt" => ["A confirmação da sua reserva"],
    ];
    private $detectCompany = [
        "autoeuro" => [
            "no" => "Takk for at du valgte Auto Europe",
            //            "de" => "... Auto Europe",
            "en"   => "Thank you for choosing Auto Europe",
            "en2"  => "Thank you for considering Auto Europe",
            'en3'  => 'Thank you for visiting Auto Europe',
            'en4'  => 'Auto Europe voucher number',
            'de2'  => 'Auto Europe Voucher Nummer',
            'de3'  => 'dass Sie sich für Auto Europe entschieden haben',
            'es'   => 'Gracias por elegir los servicios de Auto Europe',
            'fr'   => "Merci d'avoir choisi Auto Europe",
            'nl'   => "Hartelijk dank dat u voor Auto Europe",
            'nl2'  => "Auto Europe Vouchernummer",
            'da'   => "Tak fordi du valgte Auto Europe",
            'it'   => "Grazie per aver scelto Auto Europe",
            'pt'   => "Muito obrigado por ter escolhido a Auto Europe",
        ],
        "driveaway" => [
            "no" => "Takk for at du valgte DriveAway",
            //            "de" => "... DriveAway",
            "en"  => "Thank you for choosing DriveAway",
            "en2" => "Thank you for considering DriveAway",
            'en3' => 'Thank you for visiting DriveAway',
            'en4' => 'DriveAway voucher number',
            'en5' => 'DriveAway invoice number',
            'en6' => 'DriveAway is an independent wholesaler',
            'de2' => 'DriveAway Voucher Nummer',
        ],
    ];
    private $detectBodyHtml = [
        "no" => ["Bekreftelse"],
        "de" => ["Bestätigung #", 'RüCKGABE'],
        'es' => ['Confirmación #'],
        "en" => ["Confirmation #", "Voucher #", "Tips For A Smooth Rental Experience", "DROP OFF LOCATION", "Drop off", 'Driveaway Voucher'],
        "fr" => ["Bon de réservation"],
        "nl" => ["Uw boeking is bevestigd"],
        "da" => ["Bekræftelse #"],
        "it" => ["Conferma #"],
        "pt" => ["Confirmação #"],
    ];
    private $detectBodyPdf = [
        "no" => ["Reservasjonsnummer"],
        "de" => ["Reservierungsnummer"],
        "en" => ["reservation number", "invoice number", " quote number"],
        "fr" => ["Informations conducteur"],
        "nl" => ["Bestuurder informatie"],
        "da" => ["Reservationsnummer"],
        "it" => ["Numero di prenotazione"],
        "pt" => ["Informações do condutor"],
        'es' => ['Número de reserva'], // update or after pt
    ];
    private $provider = '';
    private $otaConfirmations = [];

    private $keywords = [
        'autoeuro' => [
            'AUTO EUROPE',
        ],
        'alamo' => [
            'ALAMO',
        ],
        'hertz' => [
            'HERTZ',
        ],
        'europcar' => [
            'EUROPCAR',
        ],
        'avis' => [
            'AVIS',
        ],
        'thrifty' => [
            'THRIFTY',
            'THRIFTY 2',
        ],
        'perfectdrive' => [
            'BUDGET',
        ],
    ];

    private $patterns = [
        'time'  => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'phone' => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
    ];

    public static function getEmailProviders()
    {
        return ["autoeuro", "driveaway"];
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $code => $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                $this->provider = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers["subject"], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProvider = false;
        $detectLanguage = false;

        // Detect Provider (HTML)
        if ($this->getProviderHtml()
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing Carrentals.co.uk")]')->length > 0
        ) {
            $detectProvider = true;
        }

        // Detect Language (HTML)
        $detectLanguage = $this->assignLang($this->http->Response['body']);

        if ($detectProvider && $detectLanguage) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            // Detect Provider (PDF)
            if (!$detectProvider && ($this->getProvider($textPdf) || stripos($textPdf, 'Carrentals.co.uk voucher number') !== false)) {
                $detectProvider = true;
            }

            // Detect Language (PDF)
            if (!$detectLanguage) {
                $detectLanguage = $this->assignLang($textPdf, true);
            }

            if ($detectProvider && $detectLanguage) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        // Parse HTML (+ addition from PDF)
        $body = $this->http->Response['body'];

        if ($this->assignLang($body)) {
            if ($this->parseHtml($email)) {
                $type = 'Html';

                if (empty($this->provider)) {
                    $this->provider = $this->getProvider($body);
                }
                $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

                foreach ($pdfs as $pdf) {
                    $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                    $condition1 = !empty($text) && count($email->getItineraries()) > 0;

                    // travellers
                    if (
                        $condition1
                        && strpos($text, $this->t('Driver Information')) !== false
                        && preg_match("#\n(.+" . $this->preg_implode($this->t('Driver Information')) . ".*\n\s*.+)#", $text, $m)
                    ) {
                        $rows = array_filter(explode("\n", $m[1]));

                        if (isset($rows[0])) {
                            $pos = strpos($rows[0], $this->t('Driver Information'));
                        }

                        if (
                            !empty($pos) && isset($rows[1])
                            && preg_match("#.{0," . ($pos - 3) . "}[ ]*(.+)#", $rows[1], $mat)
                        ) {
                            $email->getItineraries()[0]->general()->traveller($mat[1]);
                        }
                    }

                    // p.total
                    if ($condition1) {
                        $this->parsePdfPrice($email->getItineraries()[0], $text);
                    }
                }

                if (!empty($email->getItineraries()[0]) && empty($email->getItineraries()[0]->getTravellers())
                    && ($renterName = $this->http->FindSingleNode("(//*[starts-with(normalize-space(), 'Hi') and contains(normalize-space(), 'Thank you for choosing Auto Europe')])[1]", null, true, "#Hi\s+(.+?)\.\s*Thank you for choosing Auto Europe#"))
                ) {
                    $email->getItineraries()[0]->general()->traveller($renterName);
                }
            }
        }

        // Parse PDF
        if (empty($type)) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (empty($this->provider)) {
                    $this->provider = $this->getProvider($text);
                }

                if ($this->assignLang($text, true)) {
                    $type = 'Pdf';
                    $this->parsePdf($email, $text);
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        // Provider
        if (!empty($this->provider)) {
            $email->setProviderCode($this->provider);
            $email->ota()->code($this->provider);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // Pdf | Html;
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    private function parseHtml(Email $email): bool
    {
        $this->logger->debug(__FUNCTION__);

        $r = $email->add()->rental();

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmed Reservation'))}]") !== null) {
            $r->general()->status('Confirmed');
        } elseif ($cancel = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Cancelled -')][1]", null, true, '/Cancelled - \#(\d+)/')) {
            $r->general()
                ->status('Cancelled')
                ->cancelled()
                ->cancellationNumber($cancel)
                ->confirmation($cancel);
        }

        $rentalCompanyKeyword = '';

        $email->obtainTravelAgency();

        foreach ((array) $this->t("Voucher #") as $title) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($title)} and not(ancestor::title)]", null, true, "#{$this->preg_implode($title)}[\s*]*([A-Z\d][-A-Z\d]+)[*\s]*$#");

            if (!$conf) {
                $conf = $this->http->FindSingleNode("//text()[{$this->starts($title)} and not(ancestor::title)]/following::text()[normalize-space()][1]", null, true, "/^[:#\s*]*([A-Z\d][-A-Z\d]+)[*\s]*$/");
            }

            if ($conf && !in_array($conf, $this->otaConfirmations)) {
                $email->ota()->confirmation($conf, $title);
                $this->otaConfirmations[] = $conf;

                break;
            }
        }

        $rl = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Confirmation #"))}]", null, true, "/{$this->preg_implode($this->t("Confirmation #"))}[*\s]*([A-Z\d][-A-Z\d]+)[*\s]*$/");

        if (!$rl) {
            $rl = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Confirmation #"))} and not(ancestor::title)]/following::text()[normalize-space()][1]", null, true, "/^[:#\s*]*([A-Z\d][-A-Z\d]+)[*\s]*$/");
        }

        $tableRight = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Voucher #"))}]/ancestor::table[ ./following-sibling::table ][1]/following-sibling::table[last()]");

        if (!$rl && preg_match('/(?:#|\b)([A-Z\d][-A-Z\d]{4,})[*\s]*$/', $tableRight, $matches)) {
            // 1080923274    |    *1080923274*    |    AEU-1934971-1910999
            $rl = $matches[1];
        }

        if (empty($rl)) {
            $rl = $this->http->FindSingleNode("//span[img[contains(@src, 'suppLogo/EUROPCAR')]]/following-sibling::span[1]", null, true, '/(\d+)/');
        }

        if (empty($rl) && ($tableRight === null || $tableRight === '' || preg_match("#^\s*Pay (\S{0,5}[ ]?\S{1,5}) Today\s*$#", $tableRight)) || preg_match('/^\s*Auto Europe.+Privacy Policy\s*$/', $tableRight)) {
            $r->general()->noConfirmation();
        } elseif (empty($rl)) {
            $this->logger->debug('Empty confirmation HTML!');
            $email->removeItinerary($r);

            return false;
        }

        if ($rl === 'CONFIRMED') {
            $r->general()->noConfirmation();
        } elseif (!empty($rl)) {
            $r->general()->confirmation($rl);
        }

        $actions = '';
        $actionsRows = $this->http->XPath->query("//text()[{$this->eq($this->t("PICK-UP"))} or {$this->eq($this->t("DROP-OFF"))}]/ancestor::table[1]/descendant::tr[not(.//tr) and normalize-space()]");

        foreach ($actionsRows as $row) {
            $actions .= "\n" . $this->htmlToText($this->http->FindHTMLByXpath('.', null, $row));
        }

        /*
            Pick-Up
            EUROPCAR
            Sep 06, 2018 - 10:30
            ORLEANS DOWNTOWN OFFICE
            869 RUE DE BOURGES OLIVET
            ZAC DES AULNAIES - ORLEANS , FR
            Tel: 33 238 638 800
            OPEN Mondays-Fridays 08:00 AM-noon, 02:00 PM-06:30 PM
            OPEN Saturdays 08:00 AM-noon, 02:00 PM-05:00 PM
            CLOSED Sundays
        */
        $pattern1 = "#"
            . "(?:{$this->preg_implode($this->t("PICK-UP"))}|{$this->preg_implode($this->t("DROP-OFF"))})[ ]*$"
            . "\s+^[ ]*(?<company>.+?)[ ]*$"
            . "\s+^[ ]*(?<date>.+\d{4}.+?)[ ]*$"
            . "\s+^[ ]*(?<location>[\s\S]+?)(?:\s+{$this->preg_implode($this->t('SEE BELOW'))}[\s\S]*?)?\s*$"
            . "\s*{$this->preg_implode($this->t("Tel:"))}?[ ]*(?<tel>{$this->patterns['phone']})[ ]*$"
            . "(?<hours>(?:\s+^[ ]*{$this->preg_implode($this->t("OPEN"))}.*$)+)"
            . "#m";

        /*
            PICK UP - Jun 24, 2019 - 10:00

            HERTZ - FLORENCE PERETOLA AIRPORT
            MUST HAVE FLIGHT INFO
            SHUTTLE-FRONT OF MAIN TERMINAL
            500 METERS TO RENTAL COUNTER
            FLORENCE, IT
            Tel: 39 055 307370

            OPEN Mondays-Saturdays 08:30 AM-11:30 PM
            OPEN Sundays 09:30 AM-11:30 PM
        */
        $pattern2 = "#"
            . "(?:{$this->preg_implode($this->t("PICK-UP"))}|{$this->preg_implode($this->t("DROP-OFF"))}) - (?<date>.+\d{4}.+?)[ ]*$"
            . "\s+^[ ]*(?<company>.+?) - (?<location>[\s\S]+?)"
            . "\s+^[ ]*{$this->preg_implode($this->t("Tel:"))}[ ]*(?<tel>{$this->patterns['phone']})[ ]*$"
            . "(?<hours>(?:\s+^[ ]*{$this->preg_implode($this->t("OPEN"))}.*$)+)"
            . "#m";

        /*
            PICK UP LOCATION
            Lisbon Downtown Office
            Avda Antonio Augusto
            Lisbon PT
            07/08/2019 10:00 AM
        */
        // it-37868912.eml
        $pattern3 = "/"
            . "^[ ]*(?:{$this->preg_implode($this->t('PICK-UP'))}|{$this->preg_implode($this->t('DROP-OFF'))})[ ]*\s+"
            . "[ ]*(?<location>[\s\S]+?)[ ]*\n+"
            . "[ ]*(?<date>.+\b\d{4}\b.+?)[ ]*$"
            . "/m";

        if ((preg_match_all($pattern1, $actions, $actMatches, PREG_SET_ORDER)
                || preg_match_all($pattern2, $actions, $actMatches, PREG_SET_ORDER))
            && count($actMatches) === 2
        ) {
            $rentalCompanyKeyword = $actMatches[0]['company'];

            $r->pickup()
                ->date($this->normalizeDate($actMatches[0]['date']))
                ->location(preg_replace(["/\s*[*]* ?(?:MUST|DESK IN) [^*]+[*]*[\s,]+/", "#\s*\n+\s*#"], [' ', ', '], trim($actMatches[0]['location'])))
                ->phone(preg_replace("#\s+#", '', $actMatches[0]['tel']))
                ->openingHoursFullList(preg_split("#([ ]*\n+[ ]*)+#", trim($actMatches[0]['hours'])));

            $r->dropoff()
                ->date($this->normalizeDate($actMatches[1]['date']))
                ->location(preg_replace(["/\s*[*]* ?(?:MUST|DESK IN) [^*]+[*]*[\s,]+/", "#\s*\n+\s*#"], [' ', ', '], trim($actMatches[1]['location'])))
                ->phone(preg_replace("#\s+#", '', $actMatches[1]['tel']))
                ->openingHoursFullList(preg_split("#([ ]*\n+[ ]*)+#", trim($actMatches[1]['hours'])));
        } elseif (preg_match_all($pattern3, $actions, $actMatches, PREG_SET_ORDER) && count($actMatches) === 2) {
            $r->pickup()
                ->location(preg_replace('#\s*\n+\s*#', ', ', trim($actMatches[0]['location'])))
                ->date($this->normalizeDate($actMatches[0]['date']));

            $r->dropoff()
                ->location(preg_replace('#\s*\n+\s*#', ', ', trim($actMatches[1]['location'])))
                ->date($this->normalizeDate($actMatches[1]['date']));
        }

        $xpathFragmentImage = "//img[contains(@src,'/images/cars/')]";

        $carModel = $this->http->FindSingleNode($xpathFragmentImage . "/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()] | " . $xpathFragmentImage . "/ancestor::p[1]/preceding-sibling::p[normalize-space()][last()]");

        if (!$carModel) {
            $carModel = $this->http->FindSingleNode($xpathFragmentImage . "/ancestor::*[ not(self::td or self::th) and following-sibling::*[normalize-space()] ][1]/following::tr[not(.//tr) and string-length(normalize-space())>1][1][ not(.//img) and following-sibling::tr[normalize-space()] ]/descendant::text()[normalize-space()][1]");
        }

        if (!$carModel) {
            $carModel = $this->http->FindSingleNode("//text()[contains(normalize-space(), ' or similar') or contains(normalize-space(.), 'OR SIMILAR')]/ancestor::tr[1][count(following-sibling::tr[2]//img) = 1 and count(following-sibling::tr[3]//img) > 2]");

            if (!empty($carModel)) {
                $carType = $this->http->FindSingleNode("//text()[contains(normalize-space(), ' or similar')]/ancestor::tr[1][count(following-sibling::tr[2]//img) = 1 and count(following-sibling::tr[3]//img) > 2]/following-sibling::tr[1]");
            }
        }

        if (empty($carType)) {
            $carTypeTexts = $this->http->FindNodes("//text()[contains(normalize-space(),' or similar') or contains(normalize-space(),'OR SIMILAR')]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/descendant::text()[string-length(normalize-space())>2 or normalize-space()='-']");

            if (count($carTypeTexts)) {
                $carType = implode(' ', $carTypeTexts);
            }
        }

        if (empty($carModel)) {
            $carModel = $this->http->FindSingleNode("//text()[contains(normalize-space(), ' or similar') or contains(normalize-space(.), 'OR SIMILAR')]");
        }

        if (empty($carModel)) {
            $carModel = $this->http->FindSingleNode("//text()[contains(normalize-space(), ' or similar') or contains(normalize-space(.), 'OR SIMILAR')]/ancestor::tr[1]/following-sibling::tr[1]");
        }

        if (empty($carModel) && ($data = implode(', ', $this->http->FindNodes("//tr[starts-with(normalize-space(.), 'PRICING SUMMARY') and not(.//tr)]/preceding::tr[normalize-space(.)][contains(., '|')][1]/descendant::text()[normalize-space(.)]")))) {
            $carModel = $data;
        }

        if (!empty($carModel)) {
            $r->car()
                ->model($carModel);
        }

        $r->car()
            ->type($carType ?? null, false, true)
            ->image($this->http->FindSingleNode($xpathFragmentImage . "/@src[contains(., '//www')]"), true, true);

        $baseRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Base Rate"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^[-\s]*(\d[,.\'\d\s]*)$/');
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Currency"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^[A-Z]{3}$/');

        if ($baseRate !== null) {
            $r->price()
                ->cost($this->normalizeAmount($baseRate))
                ->currency($currency, false, true);
        }

        if (empty($r->getPrice()) || empty($r->getPrice()->getCost())) {
            $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pay') and contains(normalize-space(), 'Today')]", null, true, "#Pay (.+) Today#");

            if (!empty($total) && (preg_match("#^\s*(?<curr>[^\d\s]+)\s*(?<total>\d[\d\,\. ]*)\s*$#", $total, $m) || preg_match("#^\s*(?<total>\d[\d\,\. ]*)\s*(?<curr>[^\d\s]+)\s*$#", $total, $m))) {
                $r->price()
                    ->total($m['total'])
                    ->currency($this->currency($m['curr']));
            }
        }

        // it-37868912.eml
        $totalRental = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total Rental'))}]/following-sibling::*[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match('/^[$ ]*(?<amount>\d[,.\'\d ]*?)[( ]*(?<currency>[A-Z]{3})[ )]*$/', $totalRental, $m)) {
            // $708.72 USD    |    $1340.01 (AUD)
            $r->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency'])
            ;
        }

        if (empty($rentalCompanyKeyword) && !empty($carModel)) {
            // it-37868912.eml
            $rentalCompanyKeyword = $this->http->FindSingleNode("//*[{$this->eq($carModel)}]/preceding::*[descendant::text()[normalize-space()] or descendant::img][1][not(descendant::text()[normalize-space()])]/descendant::img[normalize-space(@src)][last()]/@src", null, true, '/suppLogo\/(EUROPCAR)\./i') ?? '';
        }

        $rentalProvider = $this->getRentalProviderByKeyword($rentalCompanyKeyword);

        if (!empty($rentalProvider)) {
            $r->program()->code($rentalProvider);
        } elseif (!empty($rentalCompanyKeyword)) {
            $r->extra()->company($rentalCompanyKeyword);
            // EZI - no in DB provider, so comment setKeyword
//            $r->program()->keyword($keyword);
        }

        if (empty($r->getPickUpDateTime()) || empty($r->getDropOffDateTime()) || empty($r->getPickUpLocation()) || empty($r->getDropOffLocation())) {
            $email->removeItinerary($r);

            return false;
        }

        return true;
    }

    private function parsePdf(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);

        /*$text = strstr($text, $this->t('Comments::'), true);
        if (empty(trim($text))){
            $this->logger->error($text);
        }*/
        $text = $this->re("#^(.+){$this->preg_implode($this->t('Comments::'))}#su", $text);

        $table = $this->re("#{$this->preg_implode($this->t('Pick-up information'))}.+?\n(.+?{$this->preg_implode($this->t('OPEN'))}.+?(?:\n\n[^\n]*{$this->preg_implode($this->t('OPEN'))}.+?)?)\n\n#s",
            $text);
        $table = preg_replace('/(\n *OPEN \S+.+?\S) (OPEN \S.+)/', '$1        $2', $table);
        $table = $this->splitCols($table, $this->colsPos($table, 10));
        /*
          Rental Company:                            US                Rental Company:                             US
           AUTO EUROPE                               GPS                AUTO EUROPE                                GPS
                                                     13-Aug-18                                                     31-Aug-18
          GPS RENTAL                                 10:00 AM          GPS RENTAL                                  10:00 AM
          Tel: 207 842 2000                                            Tel: 207 842 2000
          Fax: 207 842 2238                                            Fax: 207 842 2238
          OPEN Monday-Sunday all day                                   OPEN Monday-Sunday all day
        */
        if (count($table) !== 4) {
            $this->logger->debug('Other PDF-format!');

            return;
        }

        /*
            Auto Europe voucher number    US5490439-1    EUROPCAR reservation number    *1093960942*
                [OR]
            Bon de réservation Auto Europe    US5490439-1    Numéro de réservation HERTZ    K5842136233
        */
        $confNbrsRow = $this->re("#(?:^[ ]*| )({$this->preg_implode($this->t('voucher number'))} .*\b[-A-Z\d]{5,}\b.*)#m", $text) ?? '';
        $confNbrsTablePos = [0];

        if (preg_match("#^({$this->preg_implode($this->t('voucher number'))}(?:[ ]+Auto Europe|[ ]+DriveAway)?[ ]{1,30}[-A-Z\d]{5,}[ ]+)\S#", $confNbrsRow, $matches)) {
            $confNbrsTablePos[] = mb_strlen($matches[1]);
        }

        $confNbrsTable = $this->splitCols($confNbrsRow, $confNbrsTablePos);

        if (preg_match("#^(?<otaDesc>{$this->preg_implode($this->t('voucher number'))})(?:[ ]+Auto Europe|[ ]+DriveAway)?[ ]+(?<otaNum>[-A-Z\d]{5,})$#", $confNbrsTable[0], $matches)) {
            $r = $email->add()->rental();

            if (!in_array($matches['otaNum'], $this->otaConfirmations)) {
                $email->ota()->confirmation($matches['otaNum'], $matches['otaDesc']);
                $this->otaConfirmations[] = $matches['otaNum'];
            }

            if (count($confNbrsTable) > 1) {
                $confNbrParts = preg_split("#(?:[ ]{2,}|[ ]+[*]+)#", $confNbrsTable[1]);

                if (count($confNbrParts) === 1) {
                    $confNbrDesc = $confNbrParts[0];
                    $confNbr = null;
                } elseif (count($confNbrParts) === 2) {
                    $confNbrDesc = $confNbrParts[0];
                    $confNbr = trim($confNbrParts[1], '* ');
                } else {
                    $confNbrDesc = $confNbr = null;
                }

                if (preg_match("#^(?<Prov>.*?\S)[ ]+(?<Desc>{$this->preg_implode($this->t('reservation number'))})$#", $confNbrDesc, $m)
                    || preg_match("#^(?<Desc>{$this->preg_implode($this->t('reservation number'))})[ ]+(?<Prov>\S.*)$#", $confNbrDesc, $m)
                ) {
                    $confNbrDesc = $m['Desc'];
                    $keyword = $m['Prov'];
                    $rentalProvider = $this->getRentalProviderByKeyword($keyword);

                    if (!empty($rentalProvider)) {
                        $r->program()->code($rentalProvider);
                    } else {
                        $r->extra()->company($keyword);
                        // BUCHBINDER - no in DB provider, so comment setKeyword
                        // $r->program()->keyword($keyword);
                    }
                }

                if ($confNbr === null || strcasecmp($confNbr, 'CONFIRMED') === 0) {
                    $r->general()->noConfirmation();
                } else {
                    $r->general()->confirmation($confNbr, $confNbrDesc);
                }
            } else {
                $r->general()->noConfirmation();
            }
        } else {
            $this->logger->debug('Wrong PDF-format!');

            return;
        }

        if (preg_match("#[ ]{5,}{$this->preg_implode($this->t('Voucher Cancelled:'))}.+#", $text)) {
            $r->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        $r->general()->traveller($this->re("#{$this->preg_implode($this->t('Driver Information'))}.*\n.+? {5,}(.+)#",
            $text));

        $carInfo = preg_replace("#^.+?\n([^\n]+{$this->preg_implode($this->t('Currency'))} +)#s", '$1', $text);
        $carInfo = preg_replace("#\n[^\n]+{$this->preg_implode($this->t('Driver Information'))}.+#s", '', $carInfo);
        $header = $this->inOneRow($carInfo);
        $carInfoTable = $this->splitCols($carInfo, $this->ColsPos($header));

        if (isset($carInfoTable[1])) {
            if (preg_match("/(.+\s+" . str_replace(' ', '\s+', $this->preg_implode($this->t('carModelMarker'))) . ")\n\s*(\S[\s\S]+?)(?:\s+{$this->preg_implode($this->t('Driver Information'))}|\n\n)/", $carInfoTable[1], $m)) {
                $r->car()->type(preg_replace('/\s+/', ' ', $m[2]), false, true)
                    ->model(preg_replace('/\s+/', ' ', $m[1]));
            }
        }

        $this->parsePdfPrice($r, $text);

        if (!empty(trim($this->re("#^\d+\-.+?\d+:\d+[^\n]*(.+)#ms", $table[1])))) {
            $hTable = $this->re("#{$this->preg_implode($this->t('Pick-up information'))}.+?\n( *{$this->preg_implode($this->t('OPEN'))}.+)#s",
                $text);
            $rows = explode("\n", $hTable);
            $pos1 = $this->rowColsPos($rows[0]);
            $pos2 = $this->rowColsPos($rows[1]);
            $pos = array_values(array_unique(array_merge($pos1, $pos2)));
            $hTable = $this->splitCols($hTable, $pos);

            if (count($hTable) == 2) {
                $hoursPU = $hTable[0];
                $hoursDO = $hTable[1];
            }
        }

        if (!isset($hoursPU, $hoursDO)) {
            $hoursPU = $this->re("#({$this->preg_implode($this->t('OPEN'))}(?:\s+.+|$))#s", $this->unionColumns($table[0], $table[1]));
            $hoursDO = $this->re("#({$this->preg_implode($this->t('OPEN'))}(?:\s+.+|$))#s", $this->unionColumns($table[2], $table[3]));
        }

        $pickupHours = $this->splitText($hoursPU, "#^[ ]*({$this->preg_implode($this->t('OPEN'))})#m", true);
        $dropoffHours = $this->splitText($hoursDO, "#^[ ]*({$this->preg_implode($this->t('OPEN'))})#m", true);

        if (!isset($keyword)) {
            $keyword = $this->re("#^{$this->t('Rental Company:')}\s+(.+)#m", $table[0]);

            if (!empty($keyword)) {
                $rentalProvider = $this->getRentalProviderByKeyword($keyword);

                if (!empty($rentalProvider)) {
                    $r->program()->code($rentalProvider);
                } else {
                    $r->extra()->company($keyword);
                }
            }
        }

        $r->pickup()
            ->openingHoursFullList(array_map(function ($item) { return $this->nice($item); }, $pickupHours))
            ->date($this->normalizeDate($this->nice($this->re("#^(\d+\-.+?\d+:\d+[^\n]*)#ms", $table[1]))))
            ->location($this->nice(preg_replace("/\s*\n *[*]* *(?:MUST|DESK IN) .*?(?:[*]+|$)/s", ' ', $this->re("#{$this->preg_implode($keyword)}\s+(.+?)\s+(?:{$this->preg_implode($this->t('Tel:'))})#s", $table[0]))))
            ->phone($this->re("#{$this->preg_implode($this->t('Tel:'))}[:\s]+({$this->patterns['phone']})#", $table[0]), false, true)
            ->fax($this->re("#{$this->preg_implode($this->t('Fax'))}[:\s]+({$this->patterns['phone']})#", $table[0]), false, true)
        ;

        $r->dropoff()
            ->openingHoursFullList(array_map(function ($item) { return $this->nice($item); }, $dropoffHours))
            ->date($this->normalizeDate($this->nice($this->re("#^(\d+\-.+?\d+:\d+[^\n]*)#ms", $table[3]))))
            ->location($this->nice(preg_replace("/\s*\n *[*]* *(?:MUST|DESK IN) .*?(?:[*]+\s*|$)/s", ' ', $this->re("#{$this->preg_implode($keyword)}\s+(.+?)\s+{$this->preg_implode($this->t('Tel:'))}#s", $table[2]))))
            ->phone($this->re("#{$this->preg_implode($this->t('Tel:'))}[:\s]+({$this->patterns['phone']})#", $table[2]), false, true)
            ->fax($this->re("#{$this->preg_implode($this->t('Fax'))}[:\s]+({$this->patterns['phone']})#", $table[2]), false, true)
        ;
    }

    private function parsePdfPrice($it, $text)
    {
        $currency = $this->re("#{$this->preg_implode($this->t('Currency'))}\s+([A-Z]{3})#", $text);

        if (empty($currency)) {
            $currency = $this->re("#.{20,} {10,}([A-Z]{3})\n+.* {2,}{$this->preg_implode($this->t('Base Rate'))}\s+#", $text);
        }

        if (!empty($currency)) {
            $it->price()
                ->discount($this->re("#{$this->preg_implode($this->t('Less Discount Of'))}\s+(\d[\d\.]*)\s#", $text), false, true)
                ->cost($this->re("#{$this->preg_implode($this->t('Base Rate'))}\s+(\d[\d\.]*)\s*#", $text), false, true)
                ->total($this->re("#{$this->preg_implode($this->t('Adjusted Total'))}\s+(\d[\d\.]*)\s#", $text), false, true)
                ->currency($currency, false, true)
            ;
        }
    }

    private function getProviderHtml()
    {
        foreach ($this->detectCompany as $providerCode => $keywords) {
            foreach ($keywords as $keyword) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $keyword . '")]')->length > 0) {
                    return $providerCode;
                }
            }
        }

        return null;
    }

    private function getProvider(string $text)
    {
        foreach ($this->detectCompany as $providerCode => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    return $providerCode;
                }
            }
        }

        return null;
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
            "/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*-\s*({$this->patterns['time']})\s*$/u", // Apr 25, 2018 - 05:00
            "/^\s*(\d{1,2})-([[:alpha:]]+)\s+(\d{4})\s+({$this->patterns['time']})\s*$/u",
            "/^\s*(\d{1,2})-([[:alpha:]]+)-(\d{2})\s+({$this->patterns['time']})\s*$/u",
            "/^\s*(\d{1,2})-(\S+)\s+(\d{4})\s+({$this->patterns['time']})\s*$/u",
        ];
        $out = [
            "$2 $1 $3 $4",
            "$1 $2 $3 $4",
            "$1 $2 20$3 $4",
            "$1 $2 $3 $4",
        ];

        $str = $this->nice(preg_replace($in, $out, $str));

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {
            return preg_quote($v, '#');
        }, $field)) . ')';
    }

    private function assignLang($body, $isPdf = false)
    {
        //		$finded = false;
        //		foreach ($this->detectCompany as $code => $dCompany) {
//            if (strpos($body, $dCompany) !== false && $this->http->XPath->query("//a[contains(@href,'".$this->detectFrom[$code]."')]")->length > 2) {
//                $finded = true;
        //				$this->provider = $code;
        //				break;
//            }
//        }
        //		if (!$finded) {
        //			return false;
        //		}
//
        //		foreach($this->detectBody as $detectBody){
        //			if (strpos($body, $detectBody) !== false)
        //				return true;
        //		}

        if ($isPdf) {
            $reBody = $this->detectBodyPdf;
        } else {
            $reBody = $this->detectBodyHtml;
        }

        foreach ($reBody as $lang => $re) {
            foreach ($re as $value) {
                if (strpos($body, $value) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        $current = $pos[0] ?? 0;

        foreach ($pos as $i => $p) {
            if ($i !== 0 && isset($pos[$i])) {
                if ($pos[$i] - $current < $correct) {
                    unset($pos[$i]);

                    continue;
                }
            }
            $current = $pos[$i];
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function unionColumns($col1, $col2)
    {
        $col1Rows = explode("\n", $col1);
        $col2Rows = explode("\n", $col2);
        $newCol = '';

        for ($c = 0; $c < max(count($col1Rows), count($col2Rows)); $c++) {
            $newCol .= ($col1Rows[$c] ?? '') . ' ' . ($col2Rows[$c] ?? '') . "\n";
        }

        return $newCol;
    }
}
