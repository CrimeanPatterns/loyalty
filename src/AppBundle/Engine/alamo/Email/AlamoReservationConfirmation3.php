<?php

namespace AwardWallet\Engine\alamo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class AlamoReservationConfirmation3 extends \TAccountChecker
{
    public $mailFiles = "alamo/it-29046026.eml, alamo/it-290657521.eml, alamo/it-291111702.eml, alamo/it-292981522.eml, alamo/it-30704694.eml, alamo/it-33282195.eml, alamo/it-41543084.eml, alamo/it-45482862.eml, alamo/it-53040413.eml, alamo/it-72471142.eml, alamo/it-72934464.eml";

    public $reFrom = ['@goalamo.com', 'Alamo Reservations', '@enterprise.com'];
    public $reBody = [
        'en'  => ['Your confirmation number is', 'Itinerary'],
        'en1' => ['Confirmation:', 'Itinerary'],
        'en2' => ['Canceled Reservation Details', 'PICK UP'],
        'en3' => ['Cancelled Reservation Details', 'PICK UP'],
        'en4' => ['Get Your Rental Started Now', 'Itinerary'],
        'en5' => ['two minutes to confirm your info', 'Itinerary'],
        'pt'  => ['Sua reserva está', 'RETIRADA'],
        'pt2' => ['O seu número de confirmação é', 'RETIRADA'],
        'pt3' => ['Detalhes sobre a reserva cancelada', 'RETIRADA'],
        'es'  => ['Su reserva está', 'RECOGIDA'],
        'es2' => ['Reservó un vehículo de', 'RECOGIDA'],
        'es3' => ['Se confirmó su reserva', 'RECOGIDA'],
        'es4' => ['Itinerario', 'RECOGIDA'],
        'de'  => ['Ihre Bestätigungsnummer lautet', 'ABHOLUNG'],
        'it'  => ['La tua prenotazione', 'RITIRO'],
        'fr'  => ['Votre Réservation A Été', 'Itinéraire'],
    ];
    public $reSubject = [
        'en' => ['Reservation Confirmation', 'Reservation at', 'Confirmed:', 'Modified:', 'Reservation Cancellation for Reservation'],
        'pt' => ['Confirmado: Reservas da National Car Rental no', 'Cancelado: Reservas da National Car Rental no'],
        'es' => ['Confirmado: Reserva de National Car Rental en', 'Modificado: Enterprise Rent-A-Car Reserva'],
        'de' => ['Bestätigung: National Car Rental-Reservierung am'],
        'it' => ['Confermata: Prenotazione National Car Rental presso'],
        'fr' => ['Réservation modifiée : National Car Rental'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            //            'Dear' => '',
            'Your Reservation is'          => ['Your Reservation is', 'Your reservation is', 'Your Reservation Has Been', 'Your reservation has been', 'Reservation is', 'The Reservation is', 'Your reservation'],
            'Your confirmation number is:' => ['Your confirmation number is:', 'Confirmation:'],
            //            'Itinerary' => '',
            'RETURN'                      => ['RETURN TO ENTERPRISE', 'RETURN', 'COLLECTION INFORMATION', 'RETURN TO NATIONAL'],
            'Emerald Club #:'             => ['Alamo Insiders', 'Emerald Club', 'Enterprise Plus'],
            'PICK UP'                     => ['PICK UP AT NATIONAL', 'DELIVERY INFORMATION', 'PICK UP AT ENTERPRISE', 'PICK UP'],
            'pickupEnd'                   => ['RETURN', 'Hours subject to change', 'VIEW DETAILS OR MODIFY', 'Special Instructions', 'COLLECTION INFORMATION', 'VIEW DETAILS, MODIFY, OR CANCEL', 'Hours subject to change'],
            'dropoffEnd'                  => ['Hours subject to change', 'VIEW DETAILS OR MODIFY', 'Vehicle', 'Hours subject to change'],
            'cancelledVariants'           => ['Cancelled', 'Canceled'],
            'The cancellation number is:' => ['The cancellation number is:', 'Your cancellation number is:', 'Your cancelation number is:'],
        ],
        "es" => [
            //            'Dear' => '',
            'Your Reservation is'          => 'Su reserva está',
            'Your confirmation number is:' => ['Su número de confirmación es:', 'Número de confirmación:'],
            'Itinerary'                    => 'Itinerario',
            'PICK UP'                      => 'RECOGIDA',
            'RETURN'                       => 'DEVOLUCIÓN',
            'Vehicle'                      => 'Vehículo',
            'Driver Name'                  => 'Nombre del conductor',
            //            'Emerald Club #:' => 'N.º de Emerald Club:',
            // 'RATES & CHARGES' => '',
            //            'TIME & DISTANCE' => '',
            //            'EXTRA - TIME & DISTANCE' => '',
            //            'Extras' => '',
            'Taxes and Fees'  => 'Impuestos y cargos',
            // 'Savings' => '',
            'Estimated Total' => 'Total estimado',
            'pickupEnd'       => ['Horario sujeto a cambios. Llame para verificar.', 'Las horas pueden modificarse', 'El horario está sujeto a cambios.'],
            'dropoffEnd'      => ['Horario sujeto a cambios. Llame para verificar.', 'Las horas pueden modificarse', 'Vehículo'],
            //            'cancelledVariants' => '',
        ],
        "pt" => [
            //            'Dear' => '',
            'Your Reservation is'          => ['Sua reserva está', 'Esta reserva foi'],
            'Your confirmation number is:' => ['O seu número de confirmação é:', 'O número de cancelamento é:'],
            //            'Itinerary' => '',
            'PICK UP'         => 'RETIRADA',
            'RETURN'          => 'DEVOLUÇÃO',
            'Vehicle'         => 'Veículo',
            'Driver Name'     => 'Nome do motorista',
            'Emerald Club #:' => 'Nº Alamo Insiders:',
            'RATES & CHARGES' => 'TAXAS E TARIFAS',
            //            'TIME & DISTANCE' => '',
            //            'EXTRA - TIME & DISTANCE' => '',
            'Extras'          => 'Extras',
            'Taxes and Fees'  => 'Impostos e taxas',
            // 'Savings' => '',
            'Estimated Total'             => 'Total estimado',
            'pickupEnd'                   => ['Horas sujeitas a alteração', 'DEVOLUÇÃO', 'VER OU MODIFICAR OS DETALHES'],
            'dropoffEnd'                  => ['Horas sujeitas a alteração', 'VER OU MODIFICAR OS DETALHES', 'Veículo'],
            'cancelledVariants'           => 'cancelada',
            'The cancellation number is:' => 'O número de cancelamento é:',
        ],
        'de' => [ // it-45482862.eml
            'Dear'                         => 'Sehr geehrter',
            'Your Reservation is'          => 'Ihre Reservierung wurde',
            'Your confirmation number is:' => 'Ihre Bestätigungsnummer lautet:',
            'Itinerary'                    => 'Reisedaten',
            'PICK UP'                      => 'ABHOLUNG',
            'RETURN'                       => 'RÜCKGABE',
            'Vehicle'                      => 'Fahrzeug',
            'Driver Name'                  => 'Name des Fahrers',
            'Emerald Club #:'              => 'Emerald-Club-Mitgliedsnummer',
            'RATES & CHARGES'              => 'TARIFE UND GEBÜHREN',
            'TIME & DISTANCE'              => 'ZEIT UND ENTFERNUNG',
            //            'EXTRA - TIME & DISTANCE' => '',
            'Extras'          => 'Zusatzausstattung',
            'Taxes and Fees'  => 'Steuern und Gebühren',
            'Savings'         => 'Ersparnis',
            'Estimated Total' => 'Voraussichtliche Gesamtkosten',
            'pickupEnd'       => ['DETAILS ANZEIGEN ODER ÄNDERN'],
            'dropoffEnd'      => ['DETAILS ANZEIGEN ODER ÄNDERN'],
            //            'cancelledVariants' => '',
        ],
        'it' => [ // it-292981522.eml
            'Dear'                         => 'Sehr geehrter',
            'Your Reservation is'          => 'La tua prenotazione è',
            'Your confirmation number is:' => 'Il tuo numero di conferma è:',
            'Itinerary'                    => 'Itinerario',
            'PICK UP'                      => 'RITIRO',
            'RETURN'                       => 'RICONSEGNA',
            'Vehicle'                      => 'Veicolo',
            //'Driver Name'                  => 'Name des Fahrers',
            //'Emerald Club #:'              => 'Emerald-Club-Mitgliedsnummer',
            'RATES & CHARGES'              => 'TARIFFE E SPESE',
            'TIME & DISTANCE'              => 'ZEIT UND ENTFERNUNG',
            //            'EXTRA - TIME & DISTANCE' => '',
            //'Extras'          => '',
            //'Taxes and Fees'  => '',
            //'Savings'         => '',
            'Estimated Total' => 'Totale stimato',
            //'pickupEnd'       => [''],
            //'dropoffEnd'      => [''],
            //            'cancelledVariants' => '',
        ],
        'fr' => [ // it-.eml
            //'Dear'                         => '',
            'Your Reservation is'          => 'Votre Réservation A Été',
            'Your confirmation number is:' => 'Votre numéro de confirmation est le:',
            'Itinerary'                    => 'Itinéraire',
            'PICK UP'                      => 'DÉPART',
            'RETURN'                       => 'RESTITUTION',
            'Vehicle'                      => 'Véhicule',
            'Driver Name'                  => 'Nom du conducteur:',
            'Emerald Club #:'              => 'N° Club Émeraude:',
            //'RATES & CHARGES'              => '',
            'TIME & DISTANCE'              => 'Temps Et Km',
            'EXTRA - TIME & DISTANCE'      => 'Extra - Temps Et Km',
            'Extras'                       => 'Suppléments',
            'Taxes and Fees'               => 'Taxes et frais',
            //'Savings'         => '',
            'Estimated Total' => 'Coût total estimé',
            //'pickupEnd'       => [''],
            //'dropoffEnd'      => [''],
            //            'cancelledVariants' => '',
        ],
    ];

    private $patterns = [
        'date' => '[-[:alpha:]]+[,.\s]+(?:[[:alpha:]]+[.\s]+\d{1,2}|\d{1,2}[.\s]+[[:alpha:]]+)[,.\s]+\d{2,4}', // Tue, September 27, 2022    |    Mi,25. September 2019
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $bodyHtml = $parser->getHTMLBody();

        if (empty($bodyHtml)) {
            $bodyHtml = $parser->getPlainBody();
            $this->http->SetEmailBody($bodyHtml);
        }

        //$this->lang = 'en';

        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $bodyHtml = $parser->getHTMLBody();

        if (empty($bodyHtml)) {
            $bodyHtml = $parser->getPlainBody();
            $this->http->SetEmailBody($bodyHtml);
        }

        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Alamo') === false

            && stripos($headers['from'], '@nationalcar.com') === false
            && strpos($headers['from'], 'National Car Reservations') === false
            && strpos($headers['subject'], 'National Car Rental') === false

            && stripos($headers['from'], '@enterprise.com') === false
            && strpos($headers['from'], 'Enterprise Rent-A-Car') === false
            && strpos($headers['subject'], 'Enterprise Rent-A-Car') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
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

    public static function getEmailProviders()
    {
        return ['alamo', 'national', 'rentacar'];
    }

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->rental();

        $confirmationText = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Your confirmation number is:'))}][1]");

        if (preg_match("/^({$this->opt($this->t('Your confirmation number is:'))})\s*([A-Z\d]{5,})$/", $confirmationText, $m)) {
            $confirmationTitle = preg_replace("/^(.+?)(?:\s+is)?[\s:：]*$/u", '$1', $m[1]);
            $confirmation = $m[2];
        } else {
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Your confirmation number is:'))}][1]", null, true, "/^(.+?)(?:\s+is)?[\s:：]*$/u");
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Your confirmation number is:'))}][1]/following::text()[string-length(normalize-space())>1][1]", null, true, '/^[A-Z\d]{5,}$/');
        }

        if ($confirmation) {
            $r->general()->confirmation($confirmation, $confirmationTitle);
        }

        if (empty($confirmation) && !empty($this->http->FindSingleNode("//text()[" . $this->eq(["Get Your Rental Started Now", "Please confirm your information."]) . "]"))) {
            $r->general()->noConfirmation();
        }

        $driverName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Driver Name'))}]/ancestor::tr[1]",
            null, true, "/{$this->opt($this->t('Driver Name'))}[\s:]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u");

        if (!$driverName) {
            $driverName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}][1]/ancestor::*[not({$this->eq($this->t('Dear'))})][1]", null, true, "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*,/u");
        }

        if (!empty($driverName)) {
            $r->general()->traveller($driverName);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Reservation is'))}]",
            null, true, "/{$this->opt($this->t('Your Reservation is'))}\s+(.+?)[!.\s]*$/");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This reservation has been'))}]",
                null, true, "/{$this->opt($this->t('This reservation has been'))}\s+(.+?)[!.\s]*$/");
        }

        if ($status !== null) {
            $r->general()->status($status);
        }

        if (preg_match("/{$this->opt($this->t('cancelledVariants'))}/iu", $r->getStatus())) {
            $r->setCancelled(true);
            $conf = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('The cancellation number is:'))}][1]/following::text()[string-length(normalize-space())>1][1]");

            if (!in_array($conf, array_column($r->getConfirmationNumbers(), 0))) {
                $r->general()
                    ->confirmation($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('The cancellation number is:'))}][1]/following::text()[string-length(normalize-space())>1][1]"));
            }
        }

        $acc = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Emerald Club #:'))}]/following::text()[string-length(normalize-space())>2][1]", null, false, '/^[A-Z\d]{7,}$/');

        if (!empty($acc)) {
            $r->program()
                ->account($acc, false);
        }

        $locationType = 1;
        $this->parseLocations1($r); // it-41543084.eml

        if (empty($r->getPickUpLocation())) {
            // it-29046026.eml
            $locationType = 2;
            $this->parseLocations2($r);
        }
        $this->logger->debug('Locations type: ' . $locationType);

        $xpathVehicle = "//text()[{$this->eq($this->t('Vehicle'))}][ancestor::tr[1][count(td)=2]]";

        if ($this->http->XPath->query($xpathVehicle)->length > 0) {
            $carType = $this->http->FindSingleNode($xpathVehicle . '/ancestor::*[following-sibling::*[normalize-space()]][1]/following-sibling::*[normalize-space()][1]');
            $carModel = $this->http->FindSingleNode($xpathVehicle . '/ancestor::*[following-sibling::*[normalize-space()]][1]/following-sibling::*[normalize-space()][2]');

            if (empty($carType) || empty($carModel)) {
                $carType = $this->http->FindSingleNode($xpathVehicle . '/ancestor::table[1]/descendant::text()[normalize-space()][2]');
                $carModel = $this->http->FindSingleNode($xpathVehicle . '/ancestor::table[1]/descendant::text()[normalize-space()][3]');
            }

            $r->car()
                ->type($carType)
                ->model($carModel, false, true)
                ->image($this->http->FindSingleNode($xpathVehicle . '/ancestor::tr[1]/td[2]//img/@src', null, false, "#^https?:\/\/\S+$#"), false, true);
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Total'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)\s*(?<currency>[^\d)(]+?)$/', $totalPrice, $matches)) {
            // $201.33
            $currencyNormal = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currencyNormal) > 0 ? $currencyNormal : null;
            $r->price()->currency($currencyNormal)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = [];
            $baseFareRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Vehicle'))} and preceding::text()[{$this->eq($this->t('RATES & CHARGES'))}]]/following-sibling::tr[normalize-space()]");

            if ($baseFareRows->length === 0) {
                // it-41543084.eml
                $baseFareRows = $this->http->XPath->query("//node()[{$this->eq($this->t('Vehicle'))} and preceding::text()[{$this->eq($this->t('RATES & CHARGES'))}]]/following-sibling::*[normalize-space()][1][self::table]/descendant::tr[ not(.//tr) and descendant::text()[normalize-space()][2] ][1]/../*[normalize-space()]");
            }

            foreach ($baseFareRows as $bfRow) {
                $bfAmount = $this->http->FindSingleNode('*[position()>1][last()]', $bfRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $bfAmount, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*?)\s*(?:' . preg_quote($matches['currency'], '/') . ')$/', $bfAmount, $m)) {
                    $baseFare[] = PriceHelper::parse($m['amount'], $currencyCode);
                } else {
                    $baseFare = [];

                    break;
                }
            }

            if (count($baseFare) > 0) {
                $r->price()->cost(array_sum($baseFare));
            }

            $feeRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Extras'))} or {$this->eq($this->t('Taxes and Fees'))}]/following-sibling::tr[normalize-space()]");

            if ($feeRows->length === 0) {
                $feeRows = $this->http->XPath->query("//node()[{$this->eq($this->t('Extras'))} or {$this->eq($this->t('Taxes and Fees'))}]/following-sibling::*[normalize-space()][1][self::table]/descendant::tr[ not(.//tr) and descendant::text()[normalize-space()][2] ][1]/../*[normalize-space()]");
            }

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[position()>1][last()]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)
                || preg_match('/^[ ]*(?<amount>\d[,.\'\d ]*)\s*(?:' . preg_quote($matches['currency'], '/') . ')$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $discount = [];
            $discountRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Savings'))}]/following-sibling::tr[normalize-space()]");

            if ($discountRows->length === 0) {
                $discountRows = $this->http->XPath->query("//node()[{$this->eq($this->t('Savings'))}]/following-sibling::*[normalize-space()][1][self::table]/descendant::tr[ not(.//tr) and descendant::text()[normalize-space()][2] ][1]/../*[normalize-space()]");
            }

            foreach ($discountRows as $dRow) {
                $dAmount = $this->http->FindSingleNode('*[position()>1][last()]', $dRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (strpos($dAmount, '-') !== false
                    && preg_match('/^[- ]*(?:' . preg_quote($matches['currency'], '/') . ')?[- ]*(?<amount>\d[,.\'\d ]*?)$/', $dAmount, $m)
                ) {
                    // -$9.24    |    $-12.37
                    $discount[] = PriceHelper::parse($m['amount'], $currencyCode);
                } else {
                    $discount = [];

                    break;
                }
            }

            if (count($discount) > 0) {
                $r->price()->discount(array_sum($discount));
            }
        }
    }

    private function parseLocations1(Rental $r): void
    {
        $locationsText = implode("\n", $this->http->FindNodes("//*[{$this->eq($this->t('PICK UP'))} and not(.//tr)]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        /*
            Wed, July 10, 2019
            6:00 PM
            KINGSTON RT 9W
            1051 ULSTER AVE
            KINGSTON
            NY
            12401-1337
            USA
            (845) 336-4700
            Wed 7:30 AM-6:00 PM
         */

        /*$this->logger->error($locationsText);
        $this->logger->error('------------------------------------');*/

        $pattern1 = "/^"
            . "(?<dateTime>.{6,}\s+\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)"
            . "\n+(?<location>[\s\S]{3,})"
            . "\n+(?<phone>[+(\d][-.\s\d)(]{6,}[\d)])"
            . "(?:\n+(?<hours>[\s\S]{2,}))?"
            . "$/";

        /*
        Tue ,November 26, 2019
        10:00 AM
        SAN DIEGO INTL ARPT
        (
        SAN
        )
        3355 ADMIRAL BOLAND WAY
        Sun-Sat
        4:00 AM-11:59 PM
        Hours subject to change. Please call to verify.
        SAN DIEGO CA 92101 US
        (888) 826-6893
         */

        $pattern4 = "/(?<dateTime>.{6,}\s+\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)" //pick-up from $locationsText
            . "\n+(?<location>[\s\S]{3,})"
            . "\n+(?<hours>\D{3}[-]\D{3}\n.+)"
            . "\n+Hours.+"
            . "\n+(?<location2>.+)"
            . "\n+(?<phone>[+(\d][-.\s\d)(]{6,}[\d)])"
            . "\n+RETURN/";

        $pattern5 = "/RETURN\n+"
            . "(?<dateTime>.{6,}\s+\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)" //drop-off from $locationsText
            . "\n+(?<location>[\s\S]{3,})"
            . "\n+(?<hours>\D{3}[-]\D{3}\n.+)"
            . "\n+Hours.+"
            . "\n+(?<location2>.+)"
            . "\n+(?<phone>[+(\d][-.\s\d)(]{6,}[\d)])"
            . "/";

        /*
            Sat, July 20, 2019
            6:00 PM
            KINGSTON RT 9W
         */
        $pattern2 = "/^"
            . "(?<dateTime>.{6,}\s+\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)"
            . "\n+(?<location>[\s\S]{3,})"
            . "$/";

        /*
            Fri, December 20, 2019
            6:00 PM
            ,
            SERVICING LOCATION
            RAHWAY
            503 SAINT GEORGES AVE
            RAHWAY
            ,
            NJ
            07065-2857
            (732) 388-2665
            Mon 7:30 AM-6:00 PM
         */

        $pattern3 = "/^"
            . "(?<dateTime>.{6,}\s+\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)"
            . "?\D+(?<location>\d+.+\D+.+)"
            . "\n+(?<phone>[+(\d][-.\s\d)(]{6,}[\d)])"
            . "(?:\n+(?<hours>[\s\S]{2,}))?"
            . "$/";

        /*
        Itinerary
        PICK UP
        ROISSY CDG TERM 1 ET 2
        (CDG)
        Mon, March 9, 2020
        8:00 AM
        PARIS CH DE GAULLE APT T1 T2
        Sun-Sat
        6:00 AM-11:59 PM
        Hours subject to change. Please call to verify.
        ROISSY APT BP 332 CEDEX CDG
        95716
        ROISSY
        FR
        01 48 62 65 81
        ROISSY CDG TERM 1 ET 2
        (CDG)
        Arrival Instructions
        */
        $pattern6 = "/" . $this->opt($this->t("Itinerary")) . "\s*" . $this->opt($this->t("PICK UP")) . "\s*(?<location>.+[(]\D+[)])\s*(?<dateTime>\w+[,]\s*\w+\s*\d{1,2}[,]\s*\d{4}\s*[\d\:]+\s*(?:AM|PM)).+(?<hours>\D{3}[-]\D{3}.+)\s*Hours.+(?<phone>[\d\s]{15,})/s";

        /*
         RETURN
        ROISSY CDG TERM 1 ET 2
        (CDG)
        Tue,March 17, 2020
        1:00 PM
         * */

        $pattern7 = "/RETURN\s*(?<location>.+[(]\D+[)])\s*(?<dateTime>.+\d{4}\s*[\d\:]+\s*(?:AM|PM))/s";

        // pickUp
        $pickup = preg_match("/(?:^|\n){$this->opt($this->t('PICK UP'))}\s+([\s\S]+?)\s+{$this->opt($this->t('pickupEnd'))}/i", $locationsText, $m) ? $m[1] : null;
        $this->logger->debug($locationsText);

        if (preg_match($pattern1, $pickup, $m)) {
            $r->pickup()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location']))
                ->phone($m['phone']);

            if (!empty($m['hours'])) {
                $r->pickup()->openingHours($this->nice($m['hours']));
            }
        } /*elseif (preg_match($pattern6, $locationsText, $m)) {
            $r->pickup()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location']))
                ->phone($m['phone']);

            if (!empty($m['hours'])) {
                $r->pickup()->hours($this->nice($m['hours']));
            }
        }*/ elseif (preg_match($pattern4, $locationsText, $m)) {
            $r->pickup()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location'] . ' ' . $m['location2']))
                ->phone($m['phone']);

            if (!empty($m['hours'])) {
                $r->pickup()->openingHours($this->nice($m['hours']));
            }
        } elseif (preg_match($pattern2, $pickup, $m)) {
            $r->pickup()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location']));
        }

        // dropOff

        $dropoff = preg_match("/\n{$this->opt($this->t('RETURN'))}\s*\n\s*(\S[\s\S]+?)\s+{$this->opt($this->t('dropoffEnd'))}/i", $locationsText, $m) ? $m[1] : null;

        if (empty($dropoff)) {
            $dropoff = trim(preg_match("/\n{$this->opt($this->t('RETURN'))}\s*(.*(?:\n.*){2,10})$/i", $locationsText, $m) ? $m[1] : null, ',');
        }

        if (preg_match($pattern1, $dropoff, $m)) {
            $r->dropoff()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location']))
                ->phone($m['phone']);

            if (!empty($m['hours'])) {
                $r->dropoff()->openingHours($this->nice($m['hours']));
            }
        } elseif (preg_match($pattern5, $locationsText, $m)) {
            $r->dropoff()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location'] . ' ' . $m['location2']))
                ->phone($m['phone']);

            if (!empty($m['hours'])) {
                $r->pickup()->openingHours($this->nice($m['hours']));
            }
        } elseif (preg_match($pattern7, $locationsText, $m)) {
            $r->dropoff()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location']));
        } elseif (preg_match($pattern2, $dropoff, $m)) {
            $r->dropoff()
                ->date2($this->normalizeDate($m['dateTime']))
                ->location($this->nice($m['location']));
        } elseif (preg_match("/^\s*({$this->patterns['date']}\s+{$this->patterns['time']})\s*$/u", $dropoff, $m)) {
            $r->dropoff()
                ->date2($this->normalizeDate($m[1]))
                ->same();
        } elseif (!preg_match("/\n{$this->opt($this->t('RETURN'))}\s/i", $locationsText)) {
            $r->dropoff()
                ->noDate()
                ->noLocation();
        }

        if (empty($dropoff)) {
            $locationsText = implode("\n", $this->http->FindNodes("//*[normalize-space(.)=\"Itinerary\"]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

            $dropoff = preg_match("/\n{$this->opt($this->t('RETURN'))}\s+([\s\S]+?)\s+{$this->opt($this->t('dropoffEnd'))}/i", $locationsText, $m) ? $m[1] : null;

            if (preg_match($pattern3, $dropoff, $m)) {
                $r->dropoff()
                    ->date2($this->normalizeDate($m['dateTime']))
                    ->location($this->nice($m['location']))
                    ->phone($m['phone']);

                if (!empty($m['hours'])) {
                    $r->dropoff()->openingHours($this->nice($m['hours']));
                }
            }
        }
    }

    private function parseLocations2(Rental $r): void
    {
        $locationsText = implode("\n", $this->http->FindNodes("//*[{$this->eq($this->t('PICK UP'))}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        /*
            FT LAUDERDALE INTL ARPT (FLL)
            Wed, November 21, 2018
            12:00 PM
         */
        $patterns['locationDate'] = "/^"
            . "(?<location>[\s\S]{3,}?)"
            . "\n+(?<dateTime>.{6,}\s+\d{1,2}(?:\.\d{2}|\:\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)"
            . "\n/";

        /*
            3355 ADMIRAL BOLAND WAY
            SAN DIEGO CA 92101 US
            (888) 826-6890
         */
        $patterns['addressPhone'] = "/^"
            . "\s*(?<address>[\s\S]{3,})"
            . "\n+[ ]*(?<phone>[+(\d][-. \d)(]{8,}[\d)])"
            . "\s*$/";

        // pickUp
        $pickup = preg_match("/(?:^|\n){$this->opt($this->t('PICK UP'))}\s+([\s\S]+)/i", $locationsText, $m) ? $m[1] : null;

        if (preg_match($patterns['locationDate'], $pickup, $m)) {
            $r->pickup()
                ->location($this->nice($m['location']))
                ->date2($this->normalizeDate($m['dateTime']));
            $pickupLocationName = $this->nice($m['location']);
        }
        $xpathAddressPickup = "//text()[{$this->eq($this->t('PICK UP'))}]/following::table[1]/descendant::tr[ following-sibling::tr[normalize-space()] ][1]";
        $contactsPickupParts = $this->http->FindNodes($xpathAddressPickup . '/td[1] | ' . $xpathAddressPickup . '/following-sibling::tr[normalize-space()]/td[1]');
        $contactsPickup = implode("\n", $contactsPickupParts);

        if (preg_match($patterns['addressPhone'], $contactsPickup, $m)) {
            if (!empty($r->getPickUpLocation())) {
                // example: AEROPORT ST EXUPERY NAVETTE SHUTTLE TERMINAL>CARS 69125 SATOLAS FR
                $m['address'] = str_replace('>', ' ', $m['address']);
                $r->pickup()->location($r->getPickUpLocation() . ', ' . $this->nice($m['address']));
            }
            $r->pickup()->phone($m['phone']);
        }

        if (empty($r->getPickUpPhone())) {
            $r->pickup()->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('PICK UP'))}]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Hours subject to change')]/ancestor::p[1]/preceding::p[2]", null, true, "/^([\(\)\d\s\-]+)$/"));
        }

        if (empty($r->getPickUpPhone())) {
            $r->pickup()->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('PICK UP'))}]/following::table[1]/descendant::text()[normalize-space()][last()]"));
        }
        $pickupHours = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('PICK UP'))}]/following::table[1]/descendant::tr[1]/td[2][string-length(normalize-space())>1]/descendant::text()[normalize-space()]"));

        if (empty($pickupHours)) {
            $pickupHours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PICK UP'))}]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Hours subject to change')]/ancestor::p[1]/preceding::p[1]");
        }

        if ($pickupHours) {
            $r->pickup()->openingHours($pickupHours);
        }

        // dropOff
        $dropoff = preg_match("/\n{$this->opt($this->t('RETURN'))}\s+([\s\S]+)/i", $locationsText, $m) ? $m[1] : null;

        if (preg_match($patterns['locationDate'], $dropoff, $m)) {
            $r->dropoff()
                ->location($this->nice($m['location']))
                ->date2($this->normalizeDate($m['dateTime']));
        } elseif (!preg_match("/\n{$this->opt($this->t('RETURN'))}\s/i", $locationsText)) {
            $r->dropoff()
                ->noDate()
                ->noLocation();
        }
        $xpathAddressDropoff = "//text()[{$this->eq($this->t('RETURN'))}]/following::table[1]/descendant::tr[ following-sibling::tr[normalize-space()] ][1]";
        $contactsDropoffParts = $this->http->FindNodes($xpathAddressDropoff . '/td[1] | ' . $xpathAddressDropoff . '/following-sibling::tr[normalize-space()]/td[1]');
        $contactsDropoff = implode("\n", $contactsDropoffParts);

        if (preg_match($patterns['addressPhone'], $contactsDropoff, $m)) {
            if (!empty($r->getDropOffLocation())) {
                $r->dropoff()->location($r->getDropOffLocation() . ', ' . $this->nice($m['address']));
            }
            $r->dropoff()->phone($m['phone']);
        } elseif (!empty($pickupLocationName) && $pickupLocationName === $r->getDropOffLocation()) {
            $r->dropoff()->same();
        }

        if (empty($r->getDropOffPhone())) {
            $r->dropoff()->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('RETURN'))}]/following::table[normalize-space()][1][not(descendant::text()[{$this->eq($this->t('Vehicle'))}])]/descendant::text()[normalize-space()][last()]"), false, true);
        }
        $dropoffHours = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('RETURN'))}]/following::table[normalize-space()][1][not(descendant::text()[{$this->eq($this->t('Vehicle'))}])]/descendant::tr[1]/td[2][string-length(normalize-space())>1]/descendant::text()[normalize-space()]"));

        if ($dropoffHours) {
            $r->dropoff()->openingHours($dropoffHours);
        }
    }

    private function assignProvider($headers): bool
    {
        if ($this->http->XPath->query('//a[contains(@href,".alamo.com")]')->length > 0
            || strpos($headers['subject'], 'Alamo') !== false
        ) {
            $this->providerCode = 'alamo';

            return true;
        }

        if (stripos($headers['from'], '@nationalcar.com') !== false
            || strpos($headers['subject'], 'National Car Rental') !== false
            || $this->http->XPath->query('//a[contains(@href,"www.nationalcar.com") or contains(@href,"nationalcar.com/")]')->length > 0
        ) {
            $this->providerCode = 'national';

            return true;
        }

        if (stripos($headers['from'], '@enterprise.com') !== false
            || strpos($headers['subject'], 'Enterprise Rent-A-Car') !== false
            || $this->http->XPath->query('//a[contains(@href,"www.enterprise.com") or contains(@href,"www.enterprise.co.uk")]')->length > 0
        ) {
            $this->providerCode = 'rentacar';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        $in = [
            // Mi,25. September 2019 13:30
            "#^[[:alpha:]]{2,}\s*,\s*(\d{1,2})[.\s]+([[:alpha:]]{3,})\s+(\d{2,4})[,\s]+(\d{1,2}:\d{2}(\s*[AP]M)?)$#iu",
            // Wednesday, June 21, 2017, 8:00 AM
            "#^[^\d\s]{2,}[,\s]+([^\d\s]{3,})\s+(\d{1,2})[,\s]+(\d{4})[,\s]+(\d{1,2}:\d{2}\s+[AP]M)$#i",
            // Ter-feira, 23 de Julho de 2019 10:00
            // Jue 1 de agosto de 2019, 12:00
            // Dom 29 de diciembre de 2019 7:00 p.m.
            "#^[^\d\s]{2,}[,\s]+(\d{1,2})\s+de\s+([^\d\s]{3,})\s+de\s+(\d{4})[,\s]+(\d{1,2}:\d{2}(\s*[AP]\.?M\.?)?)$#si",
            // Lun 27 febbraio 2023 16.00
            "#^\w+\s*(\d+\s*\w+\s*\d{4})\s*(\d+)(?:\.|\:)(\d+)$#su",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1, $2:$3",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'BRL' => ['R$'],
            'EUR' => ['€'],
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }
}
