<?php

namespace AwardWallet\Engine\europcar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "europcar/it-1.eml, europcar/it-12499408.eml, europcar/it-14804666.eml, europcar/it-1907293.eml, europcar/it-1945706.eml, europcar/it-1994644.eml, europcar/it-2.eml, europcar/it-2195953.eml, europcar/it-2195954.eml, europcar/it-3889359.eml, europcar/it-3891609.eml, europcar/it-3902685.eml, europcar/it-6827529.eml, europcar/it-80628350.eml";

    public $reBody = [
        'en' => ['Thank you for choosing Europcar', 'Your booking or reservation number', 'Go to My Europcar'],
        'de' => ['Danke, dass Sie sich für Europcar entschieden haben', 'Reservierungsnummer lautet'],
        'es' => ['Gracias por elegir EUROPCAR', 'Gracias por elegir Europcar'],
        'fr' => ["Merci d'avoir choisi Europcar", "Europcar est heureux de vous"],
        'it' => ['Grazie per aver scelto Europcar'],
        'sv' => ['Tack för att du valt Europcar', 'Tack för att du har valt Europcar'],
        'nl' => ['Dank u voor uw keuze Europcar'],
        'pt' => ['Obrigado por preferir Europcar'],
    ];
    public $reSubject = [
        "/Europcar Booking Confirmation Email$/i",
        "/Ihre Reservierung$/i",
        "/Europcar Confirmation Email$/i",
        "/Reserva confirmada.?, El número de tu reserva es:/",
        "/Conferma ricezione prenotazione, numero:/",
        "/Uw reservatie is bevestigd., Uw reservatienummer is/",
        "/A sua reserva está confirmada., O seu número de reserva/",
    ];

    public $lang = '';

    public static $dict = [
        'en' => [
            "youChoosing"                        => "Thank you for choosing",
            "Your booking or reservation number" => [
                "Your booking or reservation number",
                "Your booking reservation number",
                "your reservation number",
                "Your reservation number is",
            ],
            "Total price"             => ["Total price", "Price paid", 'Total', 'Total Amount'],
            "Price to pay at pick-up" => ["Price to pay at pick-up", "Updated price to pay at pick-up"],
            "Extras also selected"    => ["Extras also selected", "Extras included", "Price includes"],
            "equipmentName"           => ["Child Seat (1-3 years)", "Infant safety seat"],
            "StatusReg"               => "/Your\sbooking\sis\s(\S*)\.$/",
            "Picking up your vehicle" => ["Picking up your vehicle", "Delivering your vehicle at"],
            "Returning your vehicle"  => ["Returning your vehicle", "Collecting your vehicle at", 'return it to'],
            "Return"                  => ["Return", "Collecting station"],
            "Pick-up"                 => ["Pick-up", "Pick up", "Delivering station"],
            "endAddress"              => ['Phone', 'Email', 'Opening hours'],
            //            "Duration:" => "",
            //            "Vehicle details" => "",
        ],
        'de' => [
            "youChoosing"                        => "Danke, dass Sie sich für",
            "Your booking or reservation number" => "Reservierungsnummer lautet",
            "Drivers:"                           => "Fahrer:",
            "Duration:"                          => ["Mietdauer:", "Dauer:"],
            "Vehicle details"                    => ["Fahrerinformationen", "Angaben zu Ihrem Fahrzeug"],
            "Pick-up"                            => "Abholung",
            "Address"                            => ["Straße und Hausnummer", "Strasse und Hausnummer"],
            "Phone"                              => "Telefon",
            "Return"                             => ["Rückgabe", "Rueckgabe"],

            "Picking up your vehicle" => ["Wir freuen uns auf Ihren Besuch in", "Wir freuen uns Sie bald zu begruessen in", "Wir freuen uns Sie bald zu begrüssen in :"],
            "Returning your vehicle"  => ["Bitte geben Sie das Fahrzeug", "Bitte bringen Sie Ihr Fahrzeug an folgende Adresse zurueck", "Bitte bringen Sie Ihr Fahrzeug an folgende Adresse zurück:"],
            "Opening hours"           => ["Öffnungszeiten", "Oeffnungszeiten"],

            "Drivers"      => "Fahrer",
            "Total price"  => "Ankunft zu zahlender Preis",
            "is confirmed" => "wurde bestätigt",
            "StatusReg"    => "/Ihre\s+Reservierung\s+wurde\s+(\S*)\.$/",
            "or similar"   => ["oder ähnlich", "oder aehnlich"],
            "Number:"      => "Vielfliegernummer:",
            "endAddress"   => ["Telefon", "Fax", "Öffnungszeiten", "Oeffnungszeiten", "Strasse und Hausnummer", "Anmietstation"],
            // "Europcar ID" => '',
        ],
        'nl' => [
            "youChoosing"                        => "Dank u voor uw keuze",
            "Your booking or reservation number" => "Uw reservatienummer is",
            "Drivers:"                           => "Bestuurders:",
            "Duration:"                          => ["Periode:"],
            //            "Vehicle details" => [""],
            "Pick-up" => ["Ophaalkantoor:", "Ophalen"],
            "Address" => ["Adres"],
            "Phone"   => "Telefoon",
            "Return"  => ["Inleverkantoor:", "Inleveren"],

            "Picking up your vehicle" => ["Uw voertuig ophalen"],
            "Returning your vehicle"  => ["Wanneer u uw voertuig terugbrengt, levert u het in te:"],
            "Opening hours"           => ["Openingsuren"],

            "Drivers"      => "Bestuurders",
            "Total price"  => "Te betalen bedrag aan de balie",
            "is confirmed" => "is bevestigd",
            "StatusReg"    => "/Uw\s+reservatie\s+(\S*)\.$/",
            "or similar"   => ["of gelijkwaardig"],
            //            "Number:" => ":",
            "endAddress" => ["Telefoon", "Fax", "Openingsuren"],
            // "Europcar ID" => '',
        ],
        'fr' => [
            "youChoosing"                        => "Merci d'avoir choisi",
            "Your booking or reservation number" => ["Numéro de réservation", "Votre numéro de réservation", "N° de réservation"],
            "Drivers:"                           => ["Nom:", "Conducteurs:", "Nom"],
            "Duration:"                          => "Durée:",
            //            "Vehicle details" => "",
            "Pick-up" => "Départ",
            "Address" => "Adresse",
            "Phone"   => "Téléphone",
            "Return"  => "Retour",

            "Picking up your vehicle" => ["Agence de départ", "Agence de retrait"],
            "Returning your vehicle"  => "Agence de retour",
            "Opening hours"           => "Horaires d'ouverture",

            "Drivers"      => "Nom",
            "Total price"  => ["Prix total à payer en agence", "Prix à payer en agence", "Prix payé"],
            "is confirmed" => "est confirmée",
            "StatusReg"    => "/Votre\s+réservation\s+est\s+(\S*)\.$/",
            "or similar"   => ["ou équivalent", "ou similaire"],
            //			"Number:" => ":",
            "endAddress"            => ['Téléphone', 'Email', 'Horaires d\'ouverture'],
            "Europcar ID"           => ['N° de conducteur', 'Identifiant Europcar'],
            'Rental Agency Details' => "Détails de l'agence de location",
        ],
        'es' => [
            "youChoosing"                        => "Gracias por elegir",
            "Your booking or reservation number" => "El número de tu reserva es",
            "Drivers:"                           => "Conductores:",
            "Duration:"                          => "Duración:",
            "Vehicle details"                    => "Detalles del vehículo",
            "Pick-up"                            => "Recogida",
            "Address"                            => "Dirección",
            "Phone"                              => "Teléfono",
            "Return"                             => "Devolución",

            "Picking up your vehicle" => "Esperamos verte en",
            "Returning your vehicle"  => "La devolución del vehículo deber hacerse en",
            "Opening hours"           => "Horario de apertura",

            "Drivers"      => "Conductores",
            "Total price"  => "Precio total",
            "is confirmed" => "confirmada",
            "StatusReg"    => "/Reserva\s+\s+(\S*)\.$/",
            "or similar"   => "o similar",
            //			"Number:" => ":",
            "endAddress" => ['Teléfono', 'Email', 'Horario de apertura'],
            // "Europcar ID" => '',
        ],
        // TODO: need examples for translate
        'it' => [
            "youChoosing"                        => "Grazie per aver scelto",
            "Your booking or reservation number" => ["Il numero della tua prenotazione è", "Questa prenotazione cancella e sostituisce la tua prenotazione precedente.", "numero:"],
            "Drivers:"                           => ["Conductores:", "Guidatore:"],
            //            "Duration:" => "",
            //            "Vehicle details" => "",
            "Pick-up" => "Ritiro",
            //			"Address" => "",
            //			"Phone" => "",
            "Return" => "Riconsegna",

            //			"Picking up your vehicle" => "",
            //			"Returning your vehicle" => "",
            //			"Opening hours" => "",

            "Drivers"     => ["Conductores", "Guidatore"],
            "Total price" => "Prezzo totale",
            //			"is confirmed" => "",
            "StatusReg"  => "/La\s+tua\s+prenotazione\s+è\s+stata\s+(\S*)\.$/",
            "or similar" => "o altro modello",
            "Number:"    => "Numero:",
            //            "endAddress" => ['Teléfono','Email', 'Horario de apertura']
            "Europcar ID" => ['Europcar ID', 'ID Europcar'],
        ],
        'sv' => [
            "youChoosing"                        => "Tack för att du valt",
            "Your booking or reservation number" => "Ditt bokningsnummer är",
            "Drivers:"                           => "Förare:",
            //            "Duration:" => "",
            //            "Vehicle details" => "",
            "Pick-up" => "Uthämtning",
            //			"Address" => "",
            //			"Phone" => "",
            "Return" => "Återlämna",

            //			"Picking up your vehicle" => "",
            //			"Returning your vehicle" => "",
            //			"Opening hours" => "",

            "Drivers"     => "Förare",
            "Total price" => "Att betala på plats",
            //			"is confirmed" => "",
            "StatusReg"  => "/Din\s+bokning\s+är\s+(\S*)\.$/",
            "or similar" => "eller liknande",
            //			"Number:" => "",
            //            "endAddress" => ['Teléfono','Email', 'Horario de apertura']
            // "Europcar ID" => '',
        ],
        'pt' => [
            "youChoosing"                        => "Obrigado por preferir",
            "Your booking or reservation number" => "O seu número de reserva é",
            "Drivers:"                           => "Condutor:",
            "Duration:"                          => "Duração:",
            //            "Vehicle details" => [""],
            "Pick-up" => "Levantar:",
            "Address" => "Morada:",
            "Phone"   => "Telefone",
            "Return"  => "Devolver:",

            "Picking up your vehicle" => ["Estação de Levantamento:"],
            "Returning your vehicle"  => ["Estação de devolução:"],
            "Opening hours"           => ["Horário de funcionamento"],

            //            "Drivers" => "Bestuurders",
            "Total price"  => "Valor pago",
            "is confirmed" => "está confirmada",
            //            "StatusReg" => "/Uw\s+reservatie\s+(\S*)\.$/",
            "or similar" => ["ou similar"],
            //            "Number:" => ":",
            "endAddress" => ['Telefone', 'Fax', 'Horário de funcionamento'],
            // "Europcar ID" => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["from"]) && stripos($headers["from"], 'europcar.') !== false) {
            return true;
        }

        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'europcar.') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHTMLBody(), 'europcar.com') === false) {
            return false;
        }

        foreach ($this->reBody as $reBody) {
            foreach ($reBody as $text) {
                if ($this->http->XPath->query("//text()[{$this->contains($text)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = '';
        $body = $parser->getHTMLBody();
        $nbsp = chr(194) . chr(160);
        $body = str_replace(['&nbsp;', $nbsp], [' ', ' '], $body);
        $this->http->SetEmailBody($body);

        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $text) {
                if ($this->http->XPath->query("//*[{$this->contains($text)}]")->length > 0) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $r = $email->add()->rental();

        $number = trim($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Your booking or reservation number'))}]/following::text()[normalize-space(.)!=''][1])[1]",
            null, true, "#(\d{6,})#"));

        if (empty($number)) {
            $number = trim($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Your booking or reservation number'))}]/ancestor::td[1])[1]",
                null, true, "#(\d{6,})#"));
        }
        $r->general()
            ->confirmation($number)
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Drivers:')) . "]/following::text()[normalize-space(.)][1]"));

        $accountNumbers = array_unique(array_filter(array_merge(
            $this->http->FindNodes("//text()[contains(., '{$this->t('Number:')}')]/following::text()[normalize-space(.)!=''][1]",
                null, "# (\d+)$#"),
            $this->http->FindNodes("//text()[{$this->contains($this->t('Europcar ID'))}]", null, "# (\d+)$#"),
            $this->http->FindNodes("//text()[{$this->contains($this->t('Europcar ID'))}]/ancestor::td[1]", null, "# (\d+)$#")
        )));

        if (!empty($accountNumbers)) {
            $r->program()
                ->accounts($accountNumbers, false);
        }

        $totalPriceHtml = $this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('Total price'))}]/ancestor::td[1]/following::td[normalize-space()][1]");
        $totalPrice = $this->htmlToText($totalPriceHtml);

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Price to pay at pick-up'))}]/following::text()[string-length(normalize-space())>2 and not({$this->contains($this->t('Estimated based on exchange rate'))})][1]");
        }

        if (preg_match("/^\s*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)/", $totalPrice, $m)
            || preg_match("/^\s*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})\b/", $totalPrice, $m)
        ) {
            // AUD 1,704.29 Guaranteed price    |    138,67 EUR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $r->price()->total(PriceHelper::parse($m['amount'], $currencyCode))->currency($m['currency']);

            $extrasRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Extras also selected'))}]/following-sibling::tr[normalize-space()]");

            foreach ($extrasRows as $eRow) {
                $liText = implode(' ', $this->http->FindNodes("descendant::li/descendant::text()[normalize-space()]", $eRow));

                if (empty($liText)) {
                    break;
                }

                if (preg_match("/^(?<name>{$this->opt($this->t('equipmentName'))})[:\s]*" . preg_quote($m['currency'], '/') . "[ ]*(?<amount>\d[,.\'\d]*)/i", $liText, $matches)
                    || preg_match("/^(?<name>{$this->opt($this->t('equipmentName'))})[:\s]*(?<amount>\d[,.\'\d]*)[ ]*" . preg_quote($m['currency'], '/') . "\b/i", $liText, $matches)
                ) {
                    $r->extra()->equip($matches['name'], PriceHelper::parse($matches['amount'], $currencyCode));
                }
            }
        }

        $xpath = "//text()[{$this->contains($this->t('Picking up your vehicle'))}]/following::text()[contains(., '{$this->t('Phone')}')][1]/ancestor-or-self::tr[{$this->contains($this->t('Address'))} and {$this->contains($this->t('Opening hours'))}][1]";

        foreach ($this->http->XPath->query($xpath) as $root) {
            $res['PickupLocation'] = [];
            $i = 1;

            while ($i < 10) {
                $s = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t('Address'))}])[1]/following::text()[normalize-space()][" . $i . "]",
                    $root);
                $this->logger->debug('$s = ' . print_r($s, true));

                if (preg_match("#^\s*({$this->opt($this->t('endAddress'))})\s*$#", $s)) {
                    break;
                } else {
                    if (!empty(trim($s))) {
                        $res['PickupLocation'][] = $s;
                    }
                }
                $i++;
            }

            if ($i == 10) {
                $res['PickupLocation'] = array_slice($res['PickupLocation'], 0, 3);
            }

            $res['PickupPhone'] = $this->http->FindSingleNode("./descendant::text()[contains(., '{$this->t('Phone')}')]/following::*[normalize-space(.)!=''][1]",
                $root, false, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
            $res['PickupFax'] = $this->http->FindSingleNode("./descendant::text()[contains(., '{$this->t('Fax')}')]/following::*[normalize-space(.)!=''][1]",
                $root, false, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
            $res['PickupHours'] = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Opening hours'))}][1]/ancestor::td[1]",
                $root, true, '/' . $this->opt($this->t('Opening hours')) . '[:\s]*(.+?)(?:$|' . $this->opt($this->t('Opening hours')) . ')/');
            $node = implode("\n",
                $this->http->FindNodes(".//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[normalize-space(.)!=''][1]/ancestor::*[{$this->contains($this->t('Pick-up'))}][1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#{$this->opt($this->t('Pick-up'))}\s+(.+)\s+(.+)#", $node, $m)) {
                $pickupLocation = $m[1];
                $pickupDatetime = $m[2];
            }

            if (!empty($res['PickupLocation'])) {
                $pickupLocation = implode(' ', $res['PickupLocation']);
            }

            if (!empty($res['PickupPhone'])) {
                $pickupPhone = $res['PickupPhone'];
            }

            if (!empty($res['PickupFax'])) {
                $pickupFax = $res['PickupFax'];
            }

            if (!empty($res['PickupHours'])) {
                $pickupHours = $res['PickupHours'];
            }
        }

        $xpath = "//text()[{$this->contains($this->t('Returning your vehicle'))}]/following::text()[contains(., '{$this->t('Phone')}')][1]/ancestor-or-self::tr[{$this->contains($this->t('Address'))} and {$this->contains($this->t('Opening hours'))}][1]";

        foreach ($this->http->XPath->query($xpath) as $root) {
            $res['DropoffLocation'] = [];
            $i = 1;

            while ($i < 10) {
                $s = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t('Address'))}])[1]/following::text()[normalize-space()][" . $i . "]",
                    $root);
                $this->logger->debug('$s = ' . print_r($s, true));

                if (preg_match("#^\s*({$this->opt($this->t('endAddress'))})\s*$#", $s)) {
                    break;
                } else {
                    if (!empty(trim($s))) {
                        $res['DropoffLocation'][] = $s;
                    }
                }
                $i++;
            }

            if ($i == 10) {
                $res['DropoffLocation'] = array_slice($res['DropoffLocation'], 0, 3);
            }
            $res['DropoffPhone'] = $this->http->FindSingleNode("./descendant::text()[contains(., '{$this->t('Phone')}')]/following::*[normalize-space(.)!=''][1]",
                $root, false, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
            $res['DropoffFax'] = $this->http->FindSingleNode("./descendant::text()[contains(., '{$this->t('Fax')}')]/following::*[normalize-space(.)!=''][1]",
                $root, false, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
            $res['DropoffHours'] = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Opening hours'))}]/ancestor::td[1]",
                $root, true, '/' . $this->opt($this->t('Opening hours')) . '[:\s]*(.+?)(?:$|' . $this->opt($this->t('Opening hours')) . ')/');
            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Return'))}]/following::text()[normalize-space(.)!=''][1]/ancestor::*[{$this->contains($this->t('Return'))}][1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#{$this->opt($this->t('Return'))}\s+(.+)\s+(.+)#", $node, $m)) {
                $dropoffLocation = $m[1];
                $dropoffDatetime = $m[2];
            }

            if (!empty($res['DropoffLocation'])) {
                $dropoffLocation = implode(' ', $res['DropoffLocation']);
            }

            if (!empty($res['DropoffPhone'])) {
                $dropoffPhone = $res['DropoffPhone'];
            }

            if (!empty($res['DropoffFax'])) {
                $dropoffFax = $res['DropoffFax'];
            }

            if (!empty($res['DropoffHours'])) {
                $dropoffHours = $res['DropoffHours'];
            }
        }
        // Date
        $patterns['locationDate'] = '#^(.+?)\s*(\d+[./-]\d+[./-]\d+,?\s*[\d:\.]+(?:\s*[ap]m)?)#i';

        foreach (['td[3]', 'td[2]'] as $datePath) {
            $pickupHtml = $this->http->FindHTMLByXpath("(//text()[{$this->contains($this->t('Pick-up'))}]/ancestor::tr[1]/" . $datePath . "[normalize-space()])[1]");
            $pickupText = $this->htmlToText($pickupHtml);

            if (preg_match($patterns['locationDate'], $pickupText, $matches)) {
                $pickupDatetime = $matches[2];

                if (empty($pickupLocation)) {
                    $pickupLocation = $matches[1];
                }
            }

            $dropoffHtml = $this->http->FindHTMLByXpath("(//text()[{$this->contains($this->t('Return'))}]/ancestor::tr[1]/" . $datePath . "[normalize-space()])[1]");
            $dropoffText = $this->htmlToText($dropoffHtml);

            if (preg_match($patterns['locationDate'], $dropoffText, $matches)) {
                $dropoffDatetime = $matches[2];

                if (empty($dropoffLocation)) {
                    $dropoffLocation = $matches[1];
                }
            }

            if (isset($pickupDatetime) or isset($dropoffDatetime)) {
                break;
            }
        }

        if (!isset($pickupLocation) && !isset($dropoffLocation)) {
            $node = implode("\n",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[normalize-space(.)!=''][1]/ancestor::*[{$this->contains($this->t('Pick-up'))}][1]//text()[normalize-space(.)!='']"));

            if (preg_match("#{$this->opt($this->t('Pick-up'))}\s+(.+)\s+(.+)#", $node, $m)) {
                $pickupLocation = $m[1];
                $pickupDatetime = $m[2];
            }
            $node = implode("\n",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/following::text()[normalize-space(.)!=''][1]/ancestor::*[{$this->contains($this->t('Return'))}][1]//text()[normalize-space(.)!='']"));

            if (preg_match("#{$this->opt($this->t('Return'))}\s+(.+)\s+(.+)#", $node, $m)) {
                $dropoffLocation = $m[1];
                $dropoffDatetime = $m[2];
            }
        }

        if (isset($pickupDatetime) && isset($dropoffDatetime)) {
            $patterns['dateTime'] = "#^(.{6,}?)[, ]+(\d{1,2}[:.]\d{2}.*)$#";

            if (preg_match($patterns['dateTime'], $pickupDatetime, $m)) {
                $datePickup = $m[1];
                $timePickup = str_replace(':', '.', $m[2]);
            }

            if (preg_match($patterns['dateTime'], $dropoffDatetime, $m)) {
                $dateDropoff = $m[1];
                $timeDropoff = str_replace(':', '.', $m[2]);
            }

            if (isset($datePickup) && isset($dateDropoff) && isset($timePickup) && isset($timeDropoff)) {
                $df = $this->DateFormat($datePickup, $dateDropoff);
                $pickupDatetime = strtotime($timePickup, strtotime($df[0]));
                $dropoffDatetime = strtotime($timeDropoff, strtotime($df[1]));
            } else {
                $pickupDatetime = strtotime($pickupDatetime, false);
                $dropoffDatetime = strtotime($dropoffDatetime, false);
            }
        }

        if (empty($pickupHours) && empty($dropoffHours)) {
            $hours = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rental Agency Details'))}]/following::text()[{$this->eq($this->t('Opening hours'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

            if (!empty($hours)) {
                $pickupHours = $dropoffHours = preg_replace("/{$this->opt($this->t('Opening hours'))}/", "", $hours);
            }
        }

        if ($pickupLocation === $dropoffLocation) {
            $location = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rental Agency Details'))}]/following::text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");
            $pickupLocation = $dropoffLocation = preg_replace("/{$this->opt($this->t('Address'))}/", "", $pickupLocation . ' ' . $location);
        }

        if (isset($pickupLocation) && isset($pickupDatetime)) {
            $r->pickup()
                ->location($pickupLocation)
                ->date($pickupDatetime)
                ->phone($pickupPhone ?? null, false, true)
                ->fax($pickupFax ?? null, false, true)
                ->openingHours($pickupHours ?? null, false, true)
            ;
        }

        if (isset($dropoffLocation) && isset($dropoffDatetime)) {
            $r->dropoff()
                ->location($dropoffLocation)
                ->date($dropoffDatetime)
                ->phone($dropoffPhone ?? null, false, true)
                ->fax($dropoffFax ?? null, false, true)
                ->openingHours($dropoffHours ?? null, false, true)
            ;
        }

        $model = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("or similar"))}]/ancestor::*[self::td or self::div][1][count(descendant::text()[normalize-space()])<=2])[1]");

        if (empty($model) && empty($this->http->FindSingleNode("(//*[{$this->contains($this->t("or similar"))}])[1]"))) {
            $model = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Duration:"))}]/ancestor::tr[1]/following::text()[normalize-space()][not({$this->contains($this->t("Vehicle details"))})][1]/ancestor::tr[1]");
        }

        if (empty($model)) {
            $model = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("or similar"))}]/ancestor::*[1][self::p][count(descendant::text()[normalize-space()])<=2])[1]");
        }
        $r->car()
            ->model($model)
            ->type($this->http->FindSingleNode("(//text()[{$this->contains($this->t("or similar"))}]/ancestor::*[self::td or self::div][1][count(descendant::text()[normalize-space()])<=2]/following::tr[string-length(normalize-space())>2][1]/descendant::td[not(.//td) and normalize-space()][not(contains(normalize-space(), '•'))][1])[1]"),
                false, true);

        $carImageUrl = $this->http->FindSingleNode("//img[@width='230' and contains(@src,'.europcar.')]/@src", null,
            false, "#^http.+#");

        if (empty($carImageUrl)) {
            $carImageUrl = $this->http->FindSingleNode("//text()[{$this->contains($this->t("or similar"))}]/following::tr[normalize-space()!=''][1][count(.//img)=1]/descendant::img/@src",
                null, false, "#^http.+#");
        }

        if (empty($carImageUrl)) {
            $carImageUrl = $this->http->FindSingleNode("//text()[{$this->contains($this->t("or similar"))}]/ancestor::*[self::td or self::div][1][count(descendant::text()[normalize-space()])<=2]/following::tr[string-length(normalize-space())>2][1]/ancestor::table[1]/descendant::img/@src",
                null, false, "#^http.+#");
        }

        $r->car()
            ->image($carImageUrl, false, true);

        // collect at the request of the partner `trottr`
        $company = $this->http->FindSingleNode("//*[(self::tr or self::div) and not(.//tr) and {$this->starts($this->t("youChoosing"))}]", null, true, "/{$this->opt($this->t("youChoosing"))}\s*(Europcar)(?:\s*[,.:;!?]|\s*entschieden|$)/i");

        if ($company !== null) {
            $r->program()->code('europcar');
            $r->extra()->company($company);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function DateFormat($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "",
                preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "",
                preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
        }

        if ($this->identifyDateFormat($dateIN, $dateOut) === 1) {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateOut);
        } else {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateOut);
        }

        return [$dateIN, $dateOut];
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m)
                && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)
        ) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempDate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempDate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempDate1)) !== false && ($tstd2 = strtotime($tempDate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }

                if (count($diff) > 0) {
                    $min = min($diff);
                } else {
                    return -1;
                }

                return array_flip($diff)[$min];
            }
        }

        return -1;
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
}
