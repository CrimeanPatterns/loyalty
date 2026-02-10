<?php

namespace AwardWallet\Engine\avis\Email;

// parsers with similar formats: avis/PlainText2 (object)

class It2160106 extends \TAccountCheckerExtended
{
    public $mailFiles = "avis/it-2160106.eml, avis/it-6711813.eml, avis/it-6885235.eml"; // +2 bcdtravel(plain)[fr,it]

    private $detectsFrom = [
        'Avis Rent-a-Car',
        'AVIS Alquiler de Coches',
        'Avis Autovermietung',
        'Avis Rent a Car',
    ];

    private $langDetectors = [
        'es' => ['Oficina de alquiler:'],
        'de' => ['Ihre Anmietdaten im Überblick'],
        'fr' => ["Le véhicule vous sera livré par l'agence"],
        'it' => ['UFFICIO DI INIZIO NOLEGGIO', 'UFFICIO DI RICONSEGNA'],
        'en' => ['Pick-up station:', 'Drop-off location:', 'RETURN LOCATION:'],
    ];

    private $lang = '';

    private static $dict = [
        'es' => [
            'reservation number is'           => 'Su número de reserva es:',
            'Pickup location'                 => 'Oficina de alquiler',
            'Drop-off location'               => 'Oficina de devolución del vehículo',
            'Rental from/to:'                 => 'Alquiler desde / hasta:',
            'opening hours on day of pick-up' => 'Horario de la oficina el día de la entrega del vehículo',
            'opening hours on day of return'  => 'Horario de la oficina el día de la devolución del vehículo',
            'Telephone'                       => 'Teléfono',
            'Car Group'                       => 'Grupo de coche',
            'Dear'                            => 'Estimado/a',
            'Your quote includes'             => 'Incluye',
            //            'Additional Costs' => '',
        ],
        'de' => [
            'reservation number is'           => 'Ihre Reservierungsnummer lautet',
            'Pickup location'                 => ['Sie zur Abholung bereit: Station', 'Sie zur Abholung bereit:', 'Ihnen zugestellt von: Station', 'Ihnen zugestellt von:', 'Anmietstation'],
            'Drop-off location'               => ['wird abgeholt von: Station', 'wird abgeholt von:', 'Bitte geben Sie Ihr Mietfahrzeug an folgender Station wieder ab: Station', 'Bitte geben Sie Ihr Mietfahrzeug an folgender Station wieder ab', 'Abgabestation'],
            'Rental from/to:'                 => 'Miete von',
            'opening hours on day of pick-up' => ['Öffnungszeiten am Abholtag', 'Öffnungszeiten am Zustelltag'],
            'opening hours on day of return'  => 'Öffnungszeiten am Abgabetag',
            'Telephone'                       => 'Telefon',
            'Car Group'                       => 'Fahrzeuggruppe',
            'Dear'                            => 'Sehr geehrter',
            //            'Your quote includes' => '',
            'Additional Costs' => 'Zusatzgebühren:',
        ],
        'fr' => [
            'reservation number is'           => 'Votre numéro de réservation est',
            'Pickup location'                 => "Le véhicule vous sera livré par l'agence",
            'Drop-off location'               => "Votre véhicule sera repris par l'agence",
            'Rental from/to:'                 => 'Période de location:',
            'opening hours on day of pick-up' => "Horaires d'ouverture le jour de la livraison",
            'opening hours on day of return'  => "Horaires d'ouverture le jour de la reprise",
            'Telephone'                       => 'Téléphone',
            'Car Group'                       => 'Catégorie',
            'Dear'                            => 'Chère',
            //            'Your quote includes' => '',
            //            'Additional Costs' => '',
        ],
        'it' => [
            'reservation number is'           => 'il tuo numero di prenotazione è',
            'Pickup location'                 => 'UFFICIO DI INIZIO NOLEGGIO',
            'Drop-off location'               => 'UFFICIO DI RICONSEGNA',
            'Rental from/to:'                 => 'Il noleggio parte il',
            'opening hours on day of pick-up' => "Orario dell'ufficio",
            'opening hours on day of return'  => "Orario dell'ufficio",
            'Telephone'                       => 'Telefono',
            'Car Group'                       => 'Avis garantisce il gruppo e non il modello',
            'Dear'                            => 'Salve',
            'Your quote includes'             => 'La tua quotazione include',
            'Additional Costs'                => 'Costi aggiuntivi',
        ],
        'en' => [
            'reservation number is'           => ['reservation number is', 'Your reservation number is:'],
            'Pickup location'                 => ['Pickup location', 'RENTAL LOCATION', 'Pick-up station'],
            'Drop-off location'               => ['Drop-off location', 'RETURN LOCATION', 'Return station'],
            'Rental from/to:'                 => ['Rental from/to:', 'Rental from'],
            'opening hours on day of pick-up' => ['opening hours on day of pick-up', 'opening hours on day of collection', 'Pick-up station timetable'],
            'opening hours on day of return'  => ['opening hours on day of return', 'Return station timetable'],
            //            'Telephone' => '',
            //            'Car Group' => '',
            //            'Dear' => '',
            //            'Your quote includes' => '',
            //            'Additional Costs' => '',
        ],
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },
                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'L';
                    },
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("/{$this->opt($this->t('reservation number is'))}\s+([\-A-Z\d]{5,})/ix");
                    },
                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $result = re("/{$this->opt($this->t('Pickup location'))}\s*:?\s*([^\n]+)/ix");
                        $result = preg_replace("/^(.+?)\s*Horaires d'ouverture le jour de la livraison.*/is", '$1', $result); // fr

                        return $result;
                    },
                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        if (re("#RENTAL DATE & TIME:\s*\w+,\D+(.+?\s+\d+:\d+(?:[ap]m)?).+?RETURN DATE & TIME:\s*\w+,\D+(.+?\s+\d+:\d+(?:[ap]m)?)#s")) {
                            return [
                                'PickupDatetime'  => totime(re(1)),
                                'DropoffDatetime' => totime(re(2)),
                            ];
                        } else {
                            return [
                                'PickupDatetime'  => totime(re("/{$this->opt($this->t('Rental from/to:'))} (?:\w+,|du Samedi:)\s(.*?)(?:\s*Uhr)?\s+(?:through|-|bis|e termina)?\s*(?:\w+,|au Mercredi:)\s*(.+\s+\d{1,2}:\d{2})(?:\s*Uhr)?\s*(?:Pickup|)/iu")),
                                'DropoffDatetime' => totime(re(2)),
                            ];
                        }
                    },
                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $result = re("/{$this->opt($this->t('Drop-off location'))}\s*:?\s*([^\n]+)/ix");
                        $result = preg_replace("/^(.+?)\s*Horaires d'ouverture le jour de la reprise.*/is", '$1', $result); // fr

                        return $result;
                    },
                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return trim(str_replace(['(', ')'], ['', ''], re("/{$this->opt($this->t('Pickup location'))}\s*(?:[^\n]+\n){1,3}\s*[^\n]*?{$this->opt($this->t('Telephone'))}\s*:?\s*\(?\s*([+)(\d][-.\s\d)(]{5,}[\d)(])/ims")));
                    },
                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("/\s+{$this->opt($this->t('opening hours on day of pick-up'))}\s*:?\s*([^\n]+)/ix");
                    },
                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return trim(str_replace(['(', ')'], ['', ''], re("/{$this->opt($this->t('Drop-off location'))}.+?{$this->opt($this->t('Telephone'))}\s*:?\s*\(?\s*([+)(\d][-.\s\d)(]{5,}[\d)(])/ims")));
                    },
                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return re("/\s+{$this->opt($this->t('opening hours on day of return'))}\s*:?\s*([^\n]+)/ix");
                    },
                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Thank you for choosing ([^\n.,]+)#ix");
                    },
                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("/\n\s*{$this->opt($this->t('Car Group'))}\s*:?\s*([^\n(]+)/i");
                    },
                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return trim(re("/\n\s*{$this->opt($this->t('Car Group'))}\s*:?\s*[^(\n]+\(([^\n)]+)\)/i"));
                    },
                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("/\n\s*{$this->opt($this->t('Dear'))} ([^\n,]+)/ix");
                    },
                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        if ($total = re("#\n\s*(?:Price|Preis)\s*:\s*([^\n]+)#")) {
                            $result = total($total);
                        } elseif ($total = re("/\)\s+([,.\d]+\s+[A-Z]{3})\s+.+\s*{$this->opt($this->t('Your quote includes'))}\s*:/i")) {
                            $result = total($total);
                        } elseif ($total = re('/^[> ]*Catégorie.+$\s*^[> ]*(\d[,.\d ]*[A-Z]{3})/im')) { // fr
                            $result = total($total);
                        }

                        if ($fees = re("/{$this->opt($this->t('Additional Costs'))}\s+([,.\d]+)\s*([A-Z]{3})\s+-\s+(.+)/")) {
                            $result['Fees'][] = ['Name' => re(3), 'Charge' => $this->normalizeAmount(re(1))];

                            if (empty($result['Currency'])) {
                                $result['Currency'] = re(2);
                            }
                        }

                        return $result;
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, '@avis-mail.com') !== false || stripos($from, '@avis.de') !== false || stripos($from, '@avis.ch') !== false || stripos($from, '@avis-europe.com') !== false) {
            return true;
        }

        foreach ($this->detectsFrom as $phrase) {
            if (stripos($from, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/avis\.reservations\.[a-z]{2}@avis-mail\.com/i', $headers['from'])) {
            return true;
        }

        foreach ($this->detectsFrom as $phrase) {
            if (stripos($headers['from'], $phrase) !== false) {
                return true;
            }
        }

        if (
            stripos($headers['subject'], 'Your Avis Booking Confirmation') !== false // en
            || stripos($headers['subject'], 'Avis Reservation Confirmation') !== false // en
            || stripos($headers['subject'], 'Conferma della prenotazione Avis') !== false // it
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detect Provider
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for booking online with Avis")'
                . ' or contains(normalize-space(.),"Thank you for choosing Avis")'
                . ' or contains(normalize-space(.),"Thank you for booking with Avis")'
                . ' or contains(normalize-space(.),"Please note AVIS is confirming")'
                . ' or contains(normalize-space(.),"Avis Rent A Car")'
                . ' or contains(normalize-space(.),"AVIS Alquiler de Coches")'
                . ' or contains(normalize-space(.),"Ihre Avis Autovermietung")' // de
                . ' or contains(normalize-space(.),"Avis Rent a Car")'
                . ' or contains(normalize-space(.),"Vielen Dank für Ihre Internet-Reservierung bei Avis")'
                . ' or contains(normalize-space(.),"Grazie per aver scelto Avis")' // it
                . ' or contains(.,"www.avis.co.uk")'
                . ']')->length === 0) {
            return false;
        }

        // Detect Language
        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return null;
        }

        $result = parent::ParsePlanEmail($parser);
        $result['emailType'] = 'It2160106' . ucfirst($this->lang);

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
