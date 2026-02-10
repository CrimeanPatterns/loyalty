<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Engine\MonthTranslate;

class TicketHtml2014 extends \TAccountChecker
{
    public $mailFiles = "aegean/it-1.eml, aegean/it-10676413.eml, aegean/it-10684665.eml, aegean/it-10791832.eml, aegean/it-1652891.eml, aegean/it-1828123.eml, aegean/it-3024581.eml, aegean/it-3394436.eml, aegean/it-4602137.eml, aegean/it-4704208.eml, aegean/it-6052234.eml, aegean/it-6332506.eml, aegean/it-6689907.eml, aegean/it-9621329.eml";

    public $body = [
        'de' => ['Reiseverlauf', 'Buchungskode:'],
        'fr' => ['Itinéraire de vol', 'ITINÉRAIRE DE VOL', 'Numéro de réservation:'],
        'it' => ['Codice prenotazione:'],
        'en' => ['Booking Reference'], //en before es !!!
        'es' => ['ITINERARIO DE VUELO'],
        'el' => ['Κωδικός κράτησης'],
    ];
    public $subject = [
        'en' => ['AEGEAN AIRLINES S.A. - E-ticket Confirmation', 'OLYMPIC AIR SCHEDULE CHANGE CONFIRMATION'],
    ];
    protected static $dict = [
        'de' => [],
        'fr' => [
            "Buchungskode:" => "Numéro de réservation:",
            "Gesamt"        => "Total",
            "Preissumme"    => "Récapitulatif du prix",
            "Flug"          => "Vol",
            "Steuern"       => "Taxes",
            "Dauer"         => "Durée",
            "Ticketnummer:" => "Numero de billet:",
            //			"Zwischenlandung" => "",
        ],
        'it' => [
            "Buchungskode:" => "Codice prenotazione:",
            "Gesamt"        => "PREZZO TOTALE",
            "Preissumme"    => "Riepilogo della tariffa",
            "Flug"          => "Volo",
            "Steuern"       => "Tasse",
            "Dauer"         => "Durata",
            "Ticketnummer:" => "Numero del biglietto:",
        ],
        'es' => [
            "Buchungskode:"  => "Código de reserva:",
            "Gesamt"         => "PRECIO TOTAL",
            "Preissumme"     => "Resumen de precios",
            "Flug"           => "Vuelo",
            "Steuern"        => "Impuestos",
            "Dauer"          => "Duración",
            "Ticketnummer:"  => ["Billete electrónico:", "Billete electrónico :"],
            "Nein. Mitglied" => "ID de Usuario",
        ],
        'en' => [
            "Buchungskode:"   => ["Booking Reference", "Booking reference"],
            "Gesamt"          => "TOTAL PRICE",
            "Preissumme"      => "Price Summary",
            "Flug"            => "Flight",
            "Steuern"         => "Taxes",
            "Dauer"           => "Duration",
            "Ticketnummer:"   => ["Ticket Number", "Ticket number"],
            "Zwischenlandung" => "stop",
            "Nein. Mitglied"  => "Member ID",
        ],
        'el' => [
            "Buchungskode:"  => 'Κωδικός κράτησης',
            "Gesamt"         => "ΣΥΝΟΛΟ",
            "Preissumme"     => "Σύνολο",
            "Flug"           => "Πτήση",
            "Steuern"        => "Φόροι",
            "Dauer"          => "Διάρκεια",
            "Ticketnummer:"  => 'Αριθμός Εισιτηρίου :',
            'Nein. Mitglied' => 'Αρ. Μέλους',
        ],
    ];
    private $lang = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@aegeanair.') === false && stripos($headers['from'], 'olympicair.com') === false) {
            return false;
        }

        return $this->detect($headers['subject'], $this->subject);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//www.aegeanair.com") or contains(@href,".aegeanair.com/") or contains(@href,"//www.olympicair.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Aegean Airlines. All Rights Reserved") or contains(.,"aegeanair.com")]')->length === 0;
        $condition3 = $this->http->XPath->query('//node()[contains(normalize-space(.),"to contact Olympic Air") or contains(normalize-space(.),"Olympic Air. All Rights Reserved") or contains(.,"olympicair.com")]')->length === 0;

        if ($condition1 && $condition2 && $condition3) {
            return false;
        }

        return $this->detect($this->http->Response['body'], $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/[@\.]aegeanair\./", $from) || (bool) preg_match("/[@\.]olympicair\./", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        if ($this->lang = $this->detect($this->http->Response['body'], $this->body)) {
            $this->parseEmail($itineraries);
        }

        $result = [
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'emailType' => 'TicketHtml2014' . ucfirst($this->lang),
        ];

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

    protected function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param string $haystack
     * @param array $arrayNeedle
     *
     * @return bool|string
     */
    protected function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }

        return false;
    }

    private function starts($field)
    {
        $f = (array) $field;

        return implode(' or ', array_map(function ($s) {
            return "starts-with(normalize-space(.),'{$s}')";
        }, $f));
    }

    private function parseEmail(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Buchungskode:")) . "]/following::*[normalize-space()!=''][1]");

        $passengers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query("//text()[{$this->starts($this->t("Ticketnummer:"))}]");

        foreach ($passengerRows as $passengerRow) {
            if ($passenger = $this->http->FindSingleNode("./ancestor::td[1]/preceding-sibling::td[normalize-space(.)][1]", $passengerRow)) {
                $passengers[] = $passenger;
            }

            if ($ticketNumber = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $passengerRow, true, '/^([-\d\s]+)$/')) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = $passengers;
        }

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = $ticketNumbers;
        }

        $total = $this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Gesamt") . "']/ancestor-or-self::td[1]/following-sibling::td[1]");

        if (preg_match('/(?<bonus>\d+\s+\w+)\s*\+\s*(\S+)\s+(?<total>[\d\.]+)/u', $total, $m) || preg_match('/(?<total>[\d\.]+)/', $total, $m)) {
            $it['TotalCharge'] = $m['total'];

            if (isset($m['bonus'])) {
                $it['EarnedAwards'] = $m['bonus'];
            }
        }

        $it['BaseFare'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Preissumme") . "']/ancestor::tr[2]/following-sibling::tr[1]//*[normalize-space(text())='" . $this->t("Flug") . "']/ancestor-or-self::td[1]/following-sibling::td[last()]"));

        $it['Currency'] = currency($total);

        $it['Tax'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Steuern") . "']/ancestor-or-self::td[1]/following-sibling::td[1]"));

        $it['AccountNumbers'] = array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Nein. Mitglied")) . "]/following::text()[normalize-space(.)][1]"));

        $xpath = "//*[normalize-space(text())='" . $this->t("Dauer") . "' or normalize-space(text())='Terminal']/ancestor::tr[1][contains(.,'" . $this->t("Flug") . "')]/following-sibling::tr[contains(./td,':')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            // example: aegean/it-1653061.eml
            $nodes = $this->http->XPath->query("//*[normalize-space(text()) = '{$this->t("Dauer")}']/ancestor::table[1]/following-sibling::table[string-length(descendant::text()) > 1][position() mod 2 = 1]");
        }

        if ($nodes->length === 0) {
            $this->logger->info("Segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $date = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[1]", $root);
            // 1 févr. 2016
            if (!empty($date) && preg_match('/(\d{1,2})\s+(\w+)\.?\s+(\d{4})/u', $date, $m)) {
                $date = ($this->lang !== 'en') ? strtotime($m[1] . ' ' . MonthTranslate::translate($m[2], $this->lang) . ' ' . $m[3]) : strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]);
            }

            $i = [];

            if (is_int($date)) {
                $i['DepDate'] = strtotime($this->http->FindSingleNode("descendant::td[1]", $root, true, "#(\d+:\d+)#"), $date);
                $i['ArrDate'] = strtotime($this->http->FindSingleNode("descendant::td[2]", $root, true, "#(\d+:\d+)#"), $date);
            }

            if (isset($i['DepDate']) && ($i['DepDate'] > $i['ArrDate'])) {
                $i['ArrDate'] = strtotime('+1 days', $i['ArrDate']);
            }

            $flight = $this->http->FindSingleNode("descendant::td[3]", $root);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                $i['AirlineName'] = $matches[1];
                $i['FlightNumber'] = $matches[2];
            }

            $cityDep = $this->http->FindSingleNode("descendant::td[1]", $root, true, "#\d+:\d+\s+(.+)#");
            $airportDep = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root);

            if ($cityDep && $airportDep) {
                $i['DepName'] = $airportDep . ', ' . $cityDep;
            } elseif ($cityDep) {
                $i['DepName'] = $cityDep;
            } elseif ($airportDep) {
                $i['DepName'] = $airportDep;
            }

            $cityArr = $this->http->FindSingleNode("descendant::td[2]", $root, true, "#\d+:\d+\s+(.+)#");
            $airportArr = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root);

            if ($cityArr && $airportArr) {
                $i['ArrName'] = $airportArr . ', ' . $cityArr;
            } elseif ($cityArr) {
                $i['ArrName'] = $cityArr;
            } elseif ($airportArr) {
                $i['ArrName'] = $airportArr;
            }

            $stops = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root);

            if (preg_match('/^(\d+)\s+' . $this->t("Zwischenlandung") . '/', $stops, $matches)) {
                $i['Stops'] = $matches[1];
            }

            $i['Cabin'] = $this->http->FindSingleNode("descendant::td[6]", $root);

            $i['BookingClass'] = $this->http->FindSingleNode("descendant::td[7][not(@rowspan=4)]", $root);

            if (empty($i['BookingClass'])) {//it
                $i['BookingClass'] = $this->http->FindSingleNode("descendant::td[7][@rowspan=4]", $root, false,
                    "#^([A-Z]{1,2})[, ]+[A-Z]{1,2}$#");

                if (empty($i['BookingClass'])) {
                    $i['BookingClass'] = $this->http->FindSingleNode("preceding-sibling::tr[contains(./td,':')][1]/descendant::td[7][@rowspan=4]",
                        $root, false, "#^[A-Z]{1,2}[, ]+([A-Z]{1,2})$#");
                }
            }

            $i['Duration'] = $this->http->FindSingleNode("descendant::td[5][not(@rowspan=4)]", $root);

            if (preg_match('/(.+)(\d+)\s+' . $this->t("Zwischenlandung") . '/', $i['Duration'], $m)) {
                $i['Duration'] = trim($m[1]);
                $i['Stops'] = $m[2];
            }

            $i['ArrCode'] = $i['DepCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $i;
        }
        $itineraries[] = $it;
    }
}
