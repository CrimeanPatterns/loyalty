<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-11092796.eml, amadeus/it-11105013.eml, amadeus/it-11118113.eml, amadeus/it-11130011.eml, amadeus/it-11213132.eml, amadeus/it-11216393.eml, amadeus/it-11225129.eml, amadeus/it-58200278.eml";
    private $providerCode = '';
    private $reFrom = '@amadeus.com';
    private $reSubject = [
        'en' => 'Booking Confirmation',
        'it' => 'Conferma prenotazione',
        'pt' => 'Confirmação de Reserva',
        'de' => 'Buchungsbestätigung',
        'fr' => 'Confirmation de la réservation',
    ];
    private $reBody = [
        'chinasouthern' => ['China Southern'],
    ];
    private $reBody2 = [
        'en' => ['Confirmation Email'],
        'it' => ['Conferma Email'],
        'pt' => ['Mail de confirmação'],
        'de' => ['Bestätigungs-E-Mail'],
        'fr' => ['E-mail de confirmation'],
    ];
    private $year;
    private $lang = '';
    private static $dict = [
        'en' => [
            //			"Booking reference" => "",
            //			"Given Name" => "",
            "Surname" => ["Surname", "Family name"],
            //			"Document Type" => "", // if not Given Name and Surname
            "titles" => ['Mrs.', 'Mr.', 'Ms.'], // if not Given Name and Surname
            //			"e-Ticket" => "",
            //			"Frequent flyer" => "",
            //			"Total Price" => "",
            //			"Seats" => "",
            //			"Your itinerary" => "",
            //			"Aircraft" => "",
            //			"Cabin" => "",
            //			"Operated By" => "",
        ],
        'it' => [
            "Booking reference" => "Riferimento prenotazione",
            "Given Name"        => "Nome",
            "Surname"           => "Cognome",
            //			"Document Type" => "",
            //			"titles" => ['Mrs.', 'Mr.'],
            "e-Ticket"       => "Biglietto elettronico",
            "Frequent flyer" => "Frequent flyer",
            "Seats"          => "posti a sedere",
            "Total Price"    => "Prezzo Totale",
            "Your itinerary" => "Il tuo itinerario",
            "Aircraft"       => "Velivolo",
            "Cabin"          => "Cabina",
            "Operated By"    => "Operato da",
        ],
        'pt' => [
            "Booking reference" => "Referência da reserva",
            "Given Name"        => "Nome",
            "Surname"           => "Sobrenome",
            "Document Type"     => "Tipo de Documento",
            "titles"            => ['Sr.'],
            "e-Ticket"          => "eTicket",
            "Frequent flyer"    => "Passageiro frequente",
            "Seats"             => "Assentos",
            "Total Price"       => "Preço Total",
            "Your itinerary"    => "Seu itinerário",
            "Aircraft"          => "Aeronave",
            "Cabin"             => "Cabine",
            "Operated By"       => "Operado Pela",
        ],
        'de' => [
            "Booking reference" => "Buchungsreferenz",
            "Given Name"        => "Vorname",
            "Surname"           => "Familien-name",
            "e-Ticket"          => "e-Ticket",
            "Document Type"     => "Ausweistyp",
            "titles"            => ['Frau', 'Herr'],
            //			"Frequent flyer" => "",
            "Seats"          => "Sitzplatz",
            "Total Price"    => "Gesamtpreis",
            "Your itinerary" => "Ihr Reiseplan",
            "Aircraft"       => "Flugzeug",
            "Cabin"          => "Kabine",
            //			"Operated By" => "",
        ],
        'fr' => [
            "Booking reference" => "Référence de la réservation",
            "Given Name"        => "Prénom",
            "Surname"           => "Nom",
            "Document Type"     => "Type de document",
            "titles"            => ['Mme'],
            "e-Ticket"          => "e-billet",
            //			"Frequent flyer" => "",
            "Seats"          => "Sièges",
            "Total Price"    => "Prix total",
            "Your itinerary" => "Votre itinéraire",
            "Aircraft"       => "Appareil",
            "Cabin"          => "Cabine",
            //			"Operated By" => "",
        ],
    ];

    public static function getEmailProviders()
    {
        return ['amadeus', 'chinasouthern'];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date("Y", strtotime($parser->getHeader('date')));

        $its = [];

        if ($this->detect($parser->getHTMLBody()) == true) {
            $its = $this->parseEmail($parser);
        }

        return [
            'emailType'    => 'BookingConfirmation' . ucfirst($this->lang),
            'parsedData'   => ['Itineraries' => $its],
            'providerCode' => $this->providerCode,
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["from"]) && strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return $this->detect($body);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseSegments1(&$it, $segments, \PlancakeEmailParser $parser): void
    {
        /*
            12:45
            ICN
            Seoul
            Incheon International
            Terminal 1
        */

        $pattern = "/^"
            . "\s*(?<time>\d{1,2}[:]+\d{2})\n+"
            . "(?<code>[A-Z]{3})\n+"
            . "(?<name>[\s\S]{3,}?)"
            . "(?:\s*Terminal (?<terminal>.+))?"
            . "$/";

        foreach ($segments as $root) {
            $seg = [];

            $date = 0;
            $dateValue = implode(' ', $this->http->FindNodes("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant::tr[count(*)=2][1]/*[2]/descendant::text()[normalize-space()]", $root));
            $dateValue = $this->normalizeDate($dateValue);

            if (!preg_match("/\b\d{4}$/", $dateValue)) {
                $date = EmailDateHelper::calculateDateRelative($dateValue, $this, $parser, '%D% %Y%');
            }

            $departureText = implode("\n", $this->http->FindNodes("*[1]/descendant::tr[not(.//tr) and normalize-space()]", $root));

            if (preg_match($pattern, $departureText, $m)) {
                if ($date) {
                    $seg['DepDate'] = strtotime($m['time'], $date);
                }

                $seg['DepCode'] = $m['code'];
                $seg['DepName'] = preg_replace('/\s+/', ' ', $m['name']);

                if (!empty($m['terminal'])) {
                    $seg['DepartureTerminal'] = $m['terminal'];
                }
            }

            /*
                CZ682
                1h45m
            */
            $flightText = implode("\n", $this->http->FindNodes("*[2]/descendant::tr[not(.//tr) and normalize-space()]", $root));

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flightText, $m)) {
                $seg['AirlineName'] = $m['name'];
                $seg['FlightNumber'] = $m['number'];
            }

            if (preg_match('/^(\d[\d hm]+)$/im', $flightText, $m)) {
                $seg['Duration'] = $m[1];
            }

            $arrivalText = implode("\n", $this->http->FindNodes("*[3]/descendant::tr[not(.//tr) and normalize-space()]", $root));

            if (preg_match($pattern, $arrivalText, $m)) {
                if ($date) {
                    $seg['ArrDate'] = strtotime($m['time'], $date);
                }

                $seg['ArrCode'] = $m['code'];
                $seg['ArrName'] = preg_replace('/\s+/', ' ', $m['name']);

                if (!empty($m['terminal'])) {
                    $seg['ArrivalTerminal'] = $m['terminal'];
                }
            }

            $cabin = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant::text()[{$this->starts($this->t('Cabin'))}]", $root, null, "/{$this->preg_implode($this->t('Cabin'))}[ ]*[:]+[ ]*(.+)/");

            if (preg_match("/^(.+?)\s*\(\s*([A-Z]{1,2})\s*\)$/", $cabin, $m)) {
                // Business (J)
                $seg['Cabin'] = $m[1];
                $seg['BookingClass'] = $m[2];
            } elseif ($cabin) {
                // Business
                $seg['Cabin'] = $cabin;
            }

            $it['TripSegments'][] = $seg;
        }
    }

    private function parseSegments2(&$it, $segments): void
    {
        foreach ($segments as $root) {
            $seg = [];

            $date = 0;
            $nodeValue = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            // FlightNumber
            // AirlineName
            // Operator
            if (preg_match("#^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*(?:{$this->preg_implode($this->t('Operated By'))}\s+(.+))?\n(.+)#", $nodeValue, $m)) {
                $seg['AirlineName'] = $m['name'];
                $seg['FlightNumber'] = $m['number'];
                $date = $this->calculateDate($m[4]);

                if (!empty($m[3])) {
                    $seg['Operator'] = $m[3];
                }
            }

            // DepCode
            // DepName
            // DepartureTerminal
            // DepDate
            $node = $this->http->FindSingleNode("(.//tr[starts-with(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')])[1]", $root);

            if (preg_match("#^\s*(?<time>\d+:\d{2})\s*(?<date>\|\s*\S+\s+\S+)?\s+(?<name>.+)?\((?<code>[A-Z]{3})\)\s*(?:Terminal\s*(?<term>.+))?#", $node, $m)) {
                $seg['DepCode'] = $m['code'];
                $seg['DepName'] = trim($m['name']);

                if (!empty($m['term'])) {
                    $seg['DepartureTerminal'] = trim($m['term']);
                }
                $depTime = $m['time'];

                if (!empty($date)) {
                    if (!empty($m['date'])) {
                        $seg['DepDate'] = strtotime($this->normalizeDate(trim($m['date'], ' |') . ' ' . date("Y", $date) . ' ' . $m['time']));

                        if (!empty($seg['DepDate']) && $seg['DepDate'] < $date) {
                            $seg['DepDate'] = strtotime("+1year", $seg['DepDate']);
                        }
                    }

                    if (empty($seg['DepDate'])) {
                        $seg['DepDate'] = strtotime($m['time'], $date);
                    }
                }
            }

            // ArrCode
            // ArrName
            // ArrivalTerminal
            // ArrDate
            $node = $this->http->FindSingleNode("(.//tr[starts-with(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd')])[2]", $root);

            if (preg_match("#^\s*(?<time>\d+:\d{2})\s*(?<date>\|\s*\S+\s+\S+)?\s+(?<name>.+)?\((?<code>[A-Z]{3})\)\s*(?:Terminal\s*(?<term>.+))?#", $node, $m)) {
                $seg['ArrCode'] = $m['code'];
                $seg['ArrName'] = trim($m['name']);

                if (!empty($m['term'])) {
                    $seg['ArrivalTerminal'] = trim($m['term']);
                }
                $arrTime = $m['time'];

                if (!empty($date)) {
                    if (!empty($m['date'])) {
                        $seg['ArrDate'] = strtotime($this->normalizeDate(trim($m['date'], ' |') . ' ' . date("Y", $date) . ' ' . $m['time']));

                        if (!empty($seg['ArrDate']) && $seg['ArrDate'] < $date) {
                            $seg['ArrDate'] = strtotime("+1year", $seg['ArrDate']);
                        }
                    }

                    if (empty($seg['ArrDate'])) {
                        $seg['ArrDate'] = strtotime($m['time'], $date);
                    }
                }
            }

            // Aircraft
            if (preg_match("#\s+" . $this->t('Aircraft') . "\s*:\s*(.+)#", $nodeValue, $m)) {
                $seg['Aircraft'] = $m[1];
            }

            // Cabin
            // BookingClass
            if (preg_match("#\s+" . $this->t('Cabin') . "\s*:\s*(.+)?\(([A-Z]{1,2})\)#", $nodeValue, $m)) {
                $seg['Cabin'] = trim($m[1]);
                $seg['BookingClass'] = $m[2];
            }

            // Seats
            if (isset($seg['DepCode']) && isset($seg['ArrCode'])) {
                $Seats = $this->http->XPath->query("//text()[" . $this->eq($this->t('Seats')) . "]/following::table//text()[contains(.,'" . $seg['DepCode'] . "') and contains(.,'" . $seg['ArrCode'] . "')]");

                foreach ($Seats as $sroot) {
                    if (preg_match("#.*\b" . $seg['DepCode'] . "\b.*\b" . $seg['ArrCode'] . "\b#", $sroot->nodeValue)) {
                        $seg['Seats'] = array_filter($this->http->FindNodes("./ancestor::table[./following-sibling::table][1]/following-sibling::table//tr[not(.//tr)]/td[2]", $sroot, "#^\s*(\d{1,3}[A-Z])\s*$#"));

                        if (empty($date) && !empty($depTime) && !empty($arrTime)) {
                            $date = $this->calculateDate($this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td[normalize-space()][1]", $sroot));
                            $seg['DepDate'] = (!empty($date)) ? strtotime($depTime, $date) : false;
                            $seg['ArrDate'] = (!empty($date)) ? strtotime($arrTime, $date) : false;
                        }

                        break;
                    }
                }
            }

            $it['TripSegments'][] = $seg;
        }
    }

    private function parseEmail(\PlancakeEmailParser $parser): array
    {
        $it = ['Kind' => 'T'];
        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Booking reference')) . "])[1]", null, true, "#:\s*([A-Z\d]{5,7})$#");

        // Passengers
        $pass = $this->http->XPath->query("//text()[" . $this->contains($this->t('Given Name')) . "]/ancestor::td[1]");

        foreach ($pass as $root) {
            $it['Passengers'][] = trim($this->http->FindSingleNode(".", $root, true, "#(?:" . $this->preg_implode($this->t('Given Name')) . ")\s*(.+)#")) .
                    ' ' . trim($this->http->FindSingleNode("./following-sibling::td[" . $this->contains($this->t('Surname')) . "]", $root, true, "#(?:" . $this->preg_implode($this->t('Surname')) . ")\s*(.+)#"));
        }

        if (empty($it['Passengers'])) {
            $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->contains($this->t('Document Type')) . "]/ancestor::table[1]/preceding-sibling::table[1][" . $this->starts($this->t('titles')) . "]", null, "#(?:" . $this->preg_implode($this->t('titles')) . ")\s*(\S.+)#");
        }

        if (isset($it['Passengers'])) {
            $it['Passengers'] = array_unique(array_filter($it['Passengers']));
        }

        // AccountNumbers
        $accountNumbers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Frequent flyer'))}]/ancestor::td[1]/following-sibling::td[1]", null, "/^([-A-Z \d]+)$/"));

        if (count($accountNumbers)) {
            $it['AccountNumbers'] = array_unique($accountNumbers);
        }

        // TicketNumbers
        $it['TicketNumbers'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t('e-Ticket')) . "]", null, "#:\s*([\d\- ]{5,})$#")));

        $xpathPrice = "descendant::text()[{$this->eq($this->t('PRICE SUMMARY'))}]";

        // TotalCharge
        // Currency
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Price'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][last()]");

        if ($totalPrice === null) {
            // it-58200278.eml
            $totalPrice = $this->http->FindSingleNode($xpathPrice . "/following::text()[{$this->eq($this->t('TOTAL'))}]/following::text()[normalize-space()][1]");
        }

        if (preg_match('/^(?<currency1>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)(?:[ ]*\([ ]*(?<currency2>[A-Z]{3})[ ]*\))?$/', $totalPrice, $m)) {
            // $ 856.71    |    $ 814.04 (USD)
            $it['TotalCharge'] = $this->normalizeAmount($m['amount']);

            if (empty($m['currency2'])) {
                $it['Currency'] = $m['currency1'];
                $m['currency2'] = '';
            } else {
                $it['Currency'] = $m['currency2'];
            }

            $baseFare = $this->http->FindSingleNode($xpathPrice . "/following::tr[ following-sibling::tr[normalize-space()] and *[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Base Fare'))}] ][1]/following-sibling::tr[normalize-space()][last()]/*[1]");

            if (preg_match('/^(?:' . preg_quote($m['currency1'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)(?:[ ]*\([ ]*' . preg_quote($m['currency2'], '/') . '[ ]*\))?$/', $baseFare, $matches)) {
                $it['BaseFare'] = $this->normalizeAmount($matches['amount']);
            }

            $taxes = $this->http->FindSingleNode($xpathPrice . "/following::tr[ following-sibling::tr[normalize-space()] and *[3]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Taxes & fees'))}] ][1]/following-sibling::tr[normalize-space()][last()]/*[3]");

            if (preg_match('/^(?:' . preg_quote($m['currency1'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)(?:[ ]*\([ ]*' . preg_quote($m['currency2'], '/') . '[ ]*\))?$/', $taxes, $matches)) {
                $it['Tax'] = $this->normalizeAmount($matches['amount']);
            }
        }

        $it['TripSegments'] = [];

        $segments = $this->http->XPath->query("//tr[ *[1]/descendant::tr[string-length(normalize-space())=3] and *[3]/descendant::tr[string-length(normalize-space())=3] and following-sibling::tr[normalize-space()][1][{$this->contains($this->t('Cabin'))}] ]");

        if ($segments->length > 0) {
            // it-58200278.eml
            $this->logger->debug('Segments found: type-1');
            $this->parseSegments1($it, $segments, $parser);
        }

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//text()[{$this->contains($this->t('Your itinerary'))}]/ancestor::table[1]//text()[{$this->contains($this->t('Cabin'))}]/ancestor::table[1]");

            if ($segments->length > 0) {
                $this->logger->debug('Segments found: type-2');
                $this->parseSegments2($it, $segments);
            }
        }

        return [$it];
    }

    private function detect($body)
    {
        $finded = false;

        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->providerCode = $prov;
                    $finded = true;
                }
            }
        }

        if ($finded === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function calculateDate(?string $str): ?int
    {
        $in = [
            "#^\s*([^\d\s\.\,]+)[.,]?\s+(\d{1,2})\s+([^\s\d\.]+)[.]?\s*$#", //Fri 09 Feb
        ];
        $out = [
            "$1, $2 $3 " . $this->year,
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(?<week>[^\d\s]+),\s+(?<date>\d+\s+(?<month>[^\d\s]+)\s+\d{4})#", $str, $m)) {
            $str = $m['date'];

            if ($en = MonthTranslate::translate($m['month'], $this->lang)) {
                $str = str_replace($m['month'], $en, $str);
            }
            $week = WeekTranslate::number1($m[1], $this->lang);

            if (empty($week)) {
                return null;
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($str, $week);

            return $date;
        }

        return null;
    }

    private function normalizeDate(string $str): string
    {
        $in = [
            "#^\s*(\d{1,2})\s+([^\d\s\.]+)[.]?\s+(\d{4})\s*$#", //Fri 09 Feb
            "/^(\d{1,2})\s+[[:alpha:]]{2,}\s+([[:alpha:]]{3,})$/u", // 24 Sun May
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return preg_quote($s); }, $field));
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
}
