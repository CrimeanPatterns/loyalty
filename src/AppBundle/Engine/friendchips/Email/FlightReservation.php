<?php

namespace AwardWallet\Engine\friendchips\Email;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-2114274.eml, friendchips/it-2776139.eml, friendchips/it-42508988.eml, friendchips/it-43044714.eml, friendchips/it-6163005.eml";

    private $lang = '';

    private $detectBody = [
        'en'  => ['We thank you for your booking', 'Your reservation number is'],
        'fr'  => ['TUI fly garantit la confidentialité des dossiers réservés', 'votre réservation que'],
        'fr2' => ['Confirmation de votre réservation', 'TUI fly est une marque déposée de la S.A. Tui Airlines Belgium'],
        'nl'  => ['de volledige gegevens', 'Uw reserveringsnummer is'],
        'en2' => ['find all data', 'Your reservation number is'],
        'es'  => ['Su dirección y datos de contacto', 'Confirmación de su reserva'],
    ];

    private static $dict = [
        'en' => [
            'MrReg' => 'Mr|Mrs|Ms|Child',
            'Taxes' => 'airport tax',
        ],
        'nl' => [
            'Your reservation number is' => 'Uw reserveringsnummer is',
            'Passengers'                 => 'Passagiers',
            'Documents required'         => 'Vereiste documenten',
            'MrReg'                      => 'Mevr|Heer|Mvr|Kind|Miss|Dhr',
            'Total'                      => 'Totaal',
            //			'find all data' => 'de volledige gegevens',
            'Flight' => 'Vlucht',
        ],
        'fr' => [
            'Your reservation number is' => 'votre réservation que a été enregistré sous le numero',
            'Passengers'                 => 'Passagers',
            'Documents required'         => 'Documents requis',
            'MrReg'                      => 'Mr|Mme|Enfant',
            'Total'                      => 'Total',
            //			'find all data' => 'les données complètes',
            'Flight' => 'Vol',
            'Taxes'  => 'Taxes d\'aéroport incluses',
        ],
        'es' => [
            'Your reservation number is' => 'El número de reserva es',
            'Passengers'                 => 'Pasajeros',
            'Documents required'         => 'Documentos requeridos',
            'MrReg'                      => 'Sr|Sra',
            'Total'                      => 'Total',
            //			'find all data' => '',
            'Flight' => 'Vuelo',
            'Taxes'  => 'tasas de aeropuerto',
        ],
    ];

    private $provider = [
        'TUIfly.com',
        'TUIfly.be',
        'jetairfly.com',
    ];

    private $subject = [
        'TUI fly:',
        'Jetairfly',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->AssignLang($this->http->Response['body'])) {
            $its = $this->parseEmail();
        }

        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        $prov = false;

        if (isset($headers['from'])) {
            foreach ($this->provider as $provider) {
                if (stripos($headers['from'], $provider) !== false) {
                    $prov = true;
                }
            }
        }

        if ($prov && isset($headers['subject'])) {
            foreach ($this->subject as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (isset($from)) {
            foreach ($this->provider as $provider) {
                if (stripos($from, $provider) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation number is'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#[A-Z\d]{5,}#");

        if (empty($it['RecordLocator']) && $this->lang === 'fr') {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('/(\D)\s+([\d\,\.]+)/u', $total, $m)) {
            $it['TotalCharge'] = str_replace(',', '.', $m[2]);
            $it['Currency'] = str_replace('€', 'EUR', $m[1]);
        }

        $tax = $this->http->FindSingleNode("//td[{$this->contains($this->t('Taxes'))} and not(.//td)]");

        if (preg_match('/\S\s+([\d\.,]+)/', $tax, $m)) {
            $it['Tax'] = str_replace(',', '.', $m[1]);
        }

        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->contains($this->t('Passengers'))}]/following-sibling::text()[normalize-space(.)!='']");

        $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found be: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            for ($i = 1; $i < 3; $i++) {
                $num = $i + 1;

                if ($this->http->XPath->query("td[string-length(normalize-space(.)) > 2][$num]", $root)->length > 0) {
                    /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                    $seg = [];
                    $names = $this->http->FindSingleNode("td[string-length(.) > 2][$num]", $root);

                    if (preg_match('/(.+)\s+-\s+(.+)/', $names, $m)) {
                        $seg['DepName'] = $m[1];
                        $seg['ArrName'] = $m[2];
                    }

                    foreach ([
                        'DepartureTerminal' => $seg['DepName'],
                        'ArrivalTerminal' => $seg['ArrName'],
                    ] as $key => $value) {
                        if (preg_match('/(.+)\s+\((\w+)\)/', $value, $m)) {
                            $seg[substr($key, 0, 3) . 'Name'] = $m[1];
                            $seg[$key] = $m[2];
                        }
                    }

                    $flight = $this->http->FindSingleNode("following-sibling::tr[1]/td[string-length(normalize-space(.)) > 2][$i]", $root);

                    if (preg_match('/^([A-Z]{2})(\d+)/', $flight, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    } elseif (preg_match('/^(JAF)(\d+)/', $flight, $m)) {
                        $seg['AirlineName'] = 'TB';
                        $seg['FlightNumber'] = $m[2];
                    }

                    $dateStr = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[2]/td[string-length(normalize-space(.)) > 2][$i]", $root));
                    $times = $this->http->FindSingleNode("following-sibling::tr[3]/td[string-length(normalize-space(.)) > 2][$i]", $root);

                    if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $times, $m)) {
                        $seg['DepDate'] = strtotime($dateStr . ', ' . $m[1]);
                        $seg['ArrDate'] = strtotime($dateStr . ', ' . $m[2]);
                    }

                    if (isset($seg['FlightNumber']) && isset($seg['DepDate']) && isset($seg['ArrDate'])) {
                        $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }

                    $it['TripSegments'][] = $seg;
                }
            }
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $patternReplace = [
            '/\w+\.*\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/u' => '$2/$1/$3', //mié. 04/09/2019  | dom. 18/08/2019
        ];

        foreach ($patternReplace as $pattern => $replace) {
            return preg_replace($pattern, $replace, $str);
        }

        return false;
    }

    private function t($str)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$str])) {
            return $str;
        }

        return self::$dict[$this->lang][$str];
    }

    private function AssignLang($body)
    {
        if (isset($this->detectBody)) {
            foreach ($this->detectBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'jetairfly.com')] | //a[contains(@href,'tuifly.')]")->length > 0) {
            $body = $parser->getHTMLBody();
            $body = str_replace('&nbsp;', ' ', $body);

            return $this->AssignLang($body);
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
