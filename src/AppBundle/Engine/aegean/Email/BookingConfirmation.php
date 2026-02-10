<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "aegean/it-3925367.eml, aegean/it-3952212.eml, aegean/it-44757922.eml, aegean/it-4583334.eml, aegean/it-4627662.eml, aegean/it-4675036.eml, aegean/it-4704233.eml, aegean/it-4713733.eml, aegean/it-4952492.eml, aegean/it-6003285.eml, aegean/it-6639528.eml, aegean/it-8567762.eml, aegean/it-9835291.eml";

    public $reFrom = ["@aegeanair.com", "noreply@olympicair.com"];
    public $reSubject = [
        'en'  => 'AEGEAN AIRLINES S.A. - E-ticket Confirmation',
        'en2' => 'OLYMPIC AIR - Rebooking ticket confirmation',
        'de'  => 'OLYMPIC AIR - Booking Confirmation',
    ];
    public $reBody = 'Aegean Airlines';
    public $reBody2 = [
        'de' => ['Buchungskode', 'Buchungscode'],
        'fr' => 'Code de réservation',
        'it' => 'Codice prenotazione',
        'es' => 'Código de reserva',
        'ru' => 'Если вы хотите связаться с Aegean',
        'en' => ['is not your boarding pass', 'If you wish to contact Aegean, please'],
        'el' => 'το email δεν εμφανίζεται σωστά, κάντε κλικ',
    ];

    public static $dictionary = [
        "de" => [
            'Booking Reference' => ['Buchungskode', 'Buchungscode'],
            'Booking class'     => 'Buchungsklasse',
            'Operated by'       => 'durchgeführt von',
            'Ticket number'     => 'Ticketnummer',
            'FF number'         => 'Vielfliegernummer',
            'PRICE SUMMARY'     => 'PREISÜBERSICHT',
            'TOTAL'             => 'Gesamt',
        ],
        "fr" => [
            'Booking Reference' => 'Code de réservation',
            'Booking class'     => 'Classe de réservation',
            'Operated by'       => 'opéré par',
            'Ticket number'     => 'Numero de billet',
            'FF number'         => 'Numéro de Passager Fréquent',
            'PRICE SUMMARY'     => 'RÉCAPITULATIF DU PRIX',
            'TOTAL'             => 'TOTAL',
        ],
        "it" => [
            'Booking Reference' => 'Codice prenotazione',
            'Booking class'     => 'Classe di prenotazione',
            'Operated by'       => 'operato da',
            'Ticket number'     => 'Numero del biglietto',
            'FF number'         => 'Numero del biglietto',
            'PRICE SUMMARY'     => 'RIEPILOGO DEL PREZZO',
            'TOTAL'             => 'TOTALE',
        ],
        "es" => [
            'Booking Reference' => 'Código de reserva',
            'Booking class'     => 'Clase de reserva',
            'Operated by'       => 'operado por',
            'Ticket number'     => 'Billete electrónico',
            'FF number'         => 'Numero del biglietto',
            'PRICE SUMMARY'     => 'RESUMEN DE PRECIOS',
            'TOTAL'             => 'TOTAL',
        ],
        "ru" => [
            'Booking Reference' => 'Номер бронирования',
            'Booking class'     => 'Класс бронирования',
            'Operated by'       => 'компанией-оператором является',
            //			'Ticket number' => '',
            'FF number' => 'Номер FF (часто летающего пассажира)',
            //            'PRICE SUMMARY' => '',
            //            'TOTAL' => '',
        ],
        "el" => [
            'Booking Reference' => 'Κωδικός Κράτησης',
            'Booking class'     => 'Κατηγ. Ναύλου',
            'Operated by'       => 'πτήση με',
            'Ticket number'     => 'Αριθμός Εισιτηρίου',
            //            'FF number' => '',
            'PRICE SUMMARY' => 'Πληροφορίες πληρωμής',
            'TOTAL'         => 'ΣΥΝΟΛΟ',
        ],
        "en" => [
            //			'names' => '#^(?:(?:Mr |Ms |Mrs ).+|[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]] \([MF]\))#iu',
        ],
    ];

    public $lang = 'en';
    public $issetPdf = false;

    private $date = 0;

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking Reference"));

        // Passengers
        // AccountNumbers
        // TicketNumbers
        $passengers = [];
        $accountNumbers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query("//img[contains(@src,'ico-passenger.') or contains(@alt,'PASSENGER DETAILS')]/ancestor::h2[1]/following-sibling::table/descendant::table[not(preceding::img[contains(@src,'ico-contact.') or contains(@alt,'CONTACT') or contains(@src,'ico-baggage-MOBILE.') or contains(@alt,'YOUR BAGGAGE')])]"); // warning: it-11137899.eml

        foreach ($passengerRows as $passengerRow) {
//            $names = isset(self::$dictionary[$this->lang]['names']) ? $this->t('names') : '#.+#';
            if ($passenger = $this->http->FindSingleNode('descendant::tr[1]', $passengerRow, true, '/^[[:alpha:]][-.\'[:alpha:])( ]*[[:alpha:]](?: \([MF]\))?$/iu')) {
                // Jacob Klein    |    Mr Jacob Klein    |    Jacob Klein (M)    |    Jacob () Klein (M)
                $passengers[] = $passenger;
            }

            if ($accountNumber = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.),'" . $this->t('FF number') . "')][1]/following::text()[normalize-space(.)][1]", $passengerRow)) {
                $accountNumbers[] = $accountNumber;
            }

            if ($ticketNumber = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.),'" . $this->t('Ticket number') . "')][1]/following::text()[normalize-space(.)][1]", $passengerRow, true, '/^([-\d\s]+)$/')) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (!empty(array_filter($passengers)[0])) {
            $it['Passengers'] = $passengers;
        } else {
            $this->logger->debug('Passengers not found!');

            if ($this->issetPdf) {
                $itineraries = [];

                return null;
            }
        }

        if (!empty($accountNumbers)) {
            $it['AccountNumbers'] = array_filter($accountNumbers);
        }

        if (!empty($ticketNumbers)) {
            $it['TicketNumbers'] = array_filter($ticketNumbers);
        }

        $xpath = "//img[contains(@src,'small-grey-plane.jpg') or contains(@alt,'GO TO')]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        $wh = [];
        $iclass = 1;
        $icabin = 1;

        foreach ($nodes as $k => $root) {
            $root2 = $this->http->XPath->query("./ancestor::table[1]/preceding-sibling::table[1]/tr[1]|./ancestor::table[1]/preceding-sibling::table[1]/tbody/tr[1]",
                $root)->item(0);
            $dateText = implode(' ', $this->http->FindNodes("./descendant::td[not(.//td)][normalize-space(.)!=''][1]//text()", $root2));
            $dateText = preg_replace("/ \d{1,2}:\d{2}.*/", '', $dateText);
            $date = strtotime($this->normalizeDate($dateText));

            $itsegment = [];

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("(./td[1]/table[1]//tr[1]/td[1]//text()[normalize-space(.)!=''])[1]",
                $root), $date);

            if (!empty($node = $this->http->FindSingleNode("(./td[1]/table[1]//tr[1]/td[1]//text()[normalize-space(.)!=''])[2]",
                    $root)) && preg_match("#([\-\+]+)\s*(\d+)#", $node, $m)) {
                $itsegment['DepDate'] = strtotime($m[1] . $m[2] . " days", $itsegment['DepDate']);
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("(./td[1]/table[1]//tr[1]/td[3]//text()[normalize-space(.)!=''])[1]",
                $root), $date);

            if (!empty($node = $this->http->FindSingleNode("(./td[1]/table[1]//tr[1]/td[3]//text()[normalize-space(.)!=''])[2]",
                    $root)) && preg_match("#([\-\+]+)\s*(\d+)#", $node, $m)) {
                $itsegment['ArrDate'] = strtotime($m[1] . $m[2] . " days", $itsegment['ArrDate']);
            }

            // DepName
            $cityDep = $this->http->FindSingleNode("./td[1]/table[1]//tr[2]/td[1]", $root);
            $airportDep = $this->http->FindSingleNode("./td[1]/table[1]//tr[3]/td[1]/descendant::text()[normalize-space()!=''][1]",
                $root);

            if ($airportDep) {
                $airportDep = preg_replace('/\s*terminal {{terminalCode}}/i', '', $airportDep);
            }

            if ($cityDep && $airportDep) {
                $itsegment['DepName'] = $airportDep . ', ' . $cityDep;
            } elseif ($cityDep) {
                $itsegment['DepName'] = $cityDep;
            } elseif ($airportDep) {
                $itsegment['DepName'] = $airportDep;
            }

            // DepartureTerminal
            $terminalDep = $this->http->FindSingleNode("./td[1]/table[1]//tr[3]/td[1]/descendant::text()[normalize-space()!=''][2]",
                $root);

            if ($terminalDep && stripos($terminalDep, '{{terminalCode}}') == false) {
                $itsegment['DepartureTerminal'] = $terminalDep;
            }

            // ArrName
            $cityArr = $this->http->FindSingleNode("./td[1]/table[1]//tr[2]/td[2]", $root);
            $airportArr = $this->http->FindSingleNode("./td[1]/table[1]//tr[3]/td[2]/descendant::text()[normalize-space()!=''][1]",
                $root);

            if ($airportArr) {
                $airportArr = preg_replace('/\s*terminal {{terminalCode}}/i', '', $airportArr);
            }

            if ($cityArr && $airportArr) {
                $itsegment['ArrName'] = $airportArr . ', ' . $cityArr;
            } elseif ($cityArr) {
                $itsegment['ArrName'] = $cityArr;
            } elseif ($airportArr) {
                $itsegment['ArrName'] = $airportArr;
            }

            // ArrivalTerminal
            $terminalArr = $this->http->FindSingleNode("./td[1]/table[1]//tr[3]/td[2]/descendant::text()[normalize-space()!=''][2]",
                $root);

            if ($terminalArr && stripos($terminalArr, '{{terminalCode}}') == false) {
                $itsegment['ArrivalTerminal'] = $terminalArr;
            }

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/table[2]/descendant::text()[string-length(normalize-space(.))>1][1]",
                $root, true, "#^\w{2}(\d+)$#");

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/table[2]/descendant::text()[string-length(normalize-space(.))>1][1]",
                $root, true, "#^(\w{2})\d+$#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./td[1]/table[2]", $root, true,
                "#" . $this->t("Operated by") . "\s+(.+)#");

            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[1]/table[2]/descendant::text()[string-length(normalize-space(.))>1][2]",
                $root);

            // BookingClass
            $bookingClass = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[" . $this->contains($this->t("Booking class")) . "]/following::text()[1]",
                $root); //$this->nextText($this->t("Booking class"));

            //Booking class  Z, Z, E
            if (!empty($bookingClass)) {
                if (strpos($bookingClass, ",")) {
                    $item = explode(",", $bookingClass);

                    if (empty($wh["class"])) {
                        $q = $k;

                        foreach ($item as $v) {
                            $wh["class"][$q++] = trim($v);
                        }
                    }
                    $itsegment['BookingClass'] = $wh["class"][$k];

                    if ($iclass === count($wh["class"])) {
                        $iclass = 1;
                        $wh["class"] = [];
                    } else {
                        $iclass++;
                    }
                } else {
                    $itsegment['BookingClass'] = $bookingClass;
                }
            }
            // BookingCabin
            $cabin = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[" . $this->contains($this->t("Cabin Class")) . "]/following::text()[1]",
                $root);

            //Cabin Class  Business, Business, Economy
            if (!empty($cabin)) {
                if (strpos($cabin, ",")) {
                    $item = explode(",", $cabin);

                    if (empty($wh["cabin"])) {
                        $q = $k;

                        foreach ($item as $v) {
                            $wh["cabin"][$q++] = trim($v);
                        }
                    }
                    $itsegment['Cabin'] = $wh["cabin"][$k];

                    if ($icabin === count($wh["cabin"])) {
                        $icabin = 1;
                        $wh["cabin"] = [];
                    } else {
                        $icabin++;
                    }
                } else {
                    $itsegment['Cabin'] = $cabin;
                }
            }

            // Seats
            if ($cityDep && $cityArr) {
                $routeVariants = [];

                foreach ((array) $this->t('to') as $value) {
                    $routeVariants[] = $cityDep . ' ' . $value . ' ' . $cityArr;
                }
                $seats = $this->http->FindNodes("//img[contains(@src,'ico-seat.') or contains(@alt,'Seats summary')]/ancestor::h2[1]/following-sibling::table/descendant::text()[{$this->eq($routeVariants)}]/following::text()[normalize-space()][1]", null, "/^(\d+[A-Z])(?:\s*\(|$)/");
                $seats = array_filter($seats);

                if (!empty($seats)) {
                    $itsegment['Seats'] = $seats;
                }
            }

            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./td[2]", $root);

            // DepCode
            // ArrCode
            $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $itsegment;
        }

        // Currency
        // TotalCharge
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PRICE SUMMARY'))}]/following::text()[{$this->eq($this->t('TOTAL'))}]/ancestor::tr[1]");

        if (preg_match("/{$this->opt($this->t('TOTAL'))}\s*(?<currency>[^\d)(]+)(?<amount>\d[,.\'\d]*)$/", $totalPrice, $m)) {
            // TOTAL    € 885.88
            $it['Currency'] = $this->normalizeCurrency($m['currency']);
            $it['TotalCharge'] = $this->normalizeAmount($m['amount']);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (strpos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false && $this->http->XPath->query("//a[contains(@href,'aegeanair.com')]  | //img[contains(@src,'olympicair')]")->length < 1) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && stripos($body, $re) !== false) {
                return true;
            }

            if (is_array($re)) {
                foreach ($re as $item) {
                    if (stripos($body, $item) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            $this->issetPdf = true;
        }
        $this->date = strtotime($parser->getDate());
        $this->http->FilterHTML = false;
        $itineraries = [];
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && stripos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }

            if (is_array($re)) {
                foreach ($re as $item) {
                    if (stripos($body, $item) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'BookingConfirmation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        //		 $this->logger->info("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]\n");
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[{$n}]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($string)
    {
        $year = date('Y', $this->date);
        $string = trim($string);
        if (preg_match('/^\w+\s+(\d+)\.(\w+)$/u', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
        } elseif (preg_match('/^[^\d\s]+\s+(\d+)\.([^\d\s]+)\.$/u', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
