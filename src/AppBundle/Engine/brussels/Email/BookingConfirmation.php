<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "brussels/it-1981693.eml, brussels/it-2052387.eml, brussels/it-2159972.eml, brussels/it-2192368.eml, brussels/it-2494749.eml, brussels/it-3275026.eml, brussels/it-4309793.eml, brussels/it-4318289.eml, brussels/it-4324467.eml, brussels/it-4373627.eml";

    public $lang = "en";

    public static $dictionary = [
        "en" => [
            //            "Booking reference:" => "",
            //            "Passengers" => "",
            //            "Frequent flyer" => "",
            //            "Ticket number" => "",
            //            "Seats" => "",
            //            "Flight details" => "",
            "Departure" => ["Departure", "Return"],
            //            "stop" => "",
            //            "Fare breakdown" => "",
            //            "Total fare:" => "",
        ],
        "fr" => [
            "Booking reference:" => "Numéro de réservation",
            "Passengers"         => "Passagers",
            //            "Frequent flyer" => "",
            "Ticket number"  => "Numéro de billet",
            "Seats"          => "Sièges",
            "Flight details" => "Détails du vol",
            "Departure"      => ["Départ", "Retour"],
            "stop"           => "escale",
            "Fare breakdown" => "Détails du tarif",
            "Total fare:"    => "Total du vol",
        ],
        "nl" => [
            "Booking reference:" => "Boekingsreferentie",
            "Passengers"         => "Passagiers",
            "Frequent flyer"     => "Frequent flyer",
            "Ticket number"      => "Ticketnummer",
            "Seats"              => "Stoelen",
            //            "Flight details" => "",
            "Departure"      => ["Vertrek", "Retour"],
            "stop"           => "stop",
            "Fare breakdown" => "Tariefdetails",
            "Total fare:"    => "Totaal vliegtarief",
        ],
        "de" => [
            "Booking reference:" => "Buchungsnummer:",
            "Passengers"         => "Passagiere",
            //            "Frequent flyer" => "",
            "Ticket number"  => "Ticketnummer",
            "Seats"          => "Sitzplätze",
            "Flight details" => "Flugdaten",
            "Departure"      => ["Abflug", "Ankunft"],
            "stop"           => "stop",
            "Fare breakdown" => "Tarifaufschlüsselung",
            "Total fare:"    => "Gesamtflugpreis:",
        ],
        "es" => [
            "Booking reference:" => "Código de reserva:",
            "Passengers"         => "Pasajeros",
            //            "Frequent flyer" => "",
            "Ticket number"  => "Número de billete",
            "Seats"          => "Asientos",
            "Flight details" => "Información de vuelo",
            "Departure"      => ["Ida", "Vuelta"],
            "stop"           => "escalas",
            "Fare breakdown" => "Desglose de tarifa",
            "Total fare:"    => "Tarifa total:",
        ],
        "it" => [
            "Booking reference:" => "Riferimento di prenotazione:",
            "Passengers"         => "Passeggeri",
            //            "Frequent flyer" => "",
            "Ticket number" => "Numero biglietto",
            "Seats"         => "Posti",
            //            "Flight details" => "",
            "Departure"      => ["Departure", "Return", "Partenza", "Ritorno"],
            "stop"           => "stop",
            "Fare breakdown" => "Dettaglio tariffa",
            "Total fare:"    => "Totale tariffa:",
        ],
    ];

    private $detectFrom = "no-reply@brusselsairlines.com";

    private $detectSubject = [
        "en" => "Booking confirmation",
        "fr" => "Confirmation de réservation",
        "nl" => "Bevestiging boeking",
        "de" => "Buchungsbestätigung",
        "es" => "Confirmación de reserva",
        "it" => "Conferma della prenotazione",
    ];

    private $detectProvider = 'brusselsairlines.com';
    private $detectBody = [
        "en" => "Flight details",
        "fr" => "Détails du vol",
        "nl" => "Vluchtdetails",
        "de" => "Flugdaten",
        "es" => "Información de vuelo",
        "it" => "Dettagli del volo",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                $this->lang = $lang;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '" . $this->detectProvider . "')]")->length > 0) {
            foreach ($this->detectBody as $dBody) {
                if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                    return true;
                }
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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference:")) . "]/following::text()[normalize-space(.)][1]"))
            ->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/following::table[1]/descendant::tr[./td[1][normalize-space(.)]]/td[1]"))
        ;

        // Issued
        $f->issued()
            ->tickets($this->http->FindNodes("//text()[" . $this->eq($this->t("Ticket number")) . "]/following::text()[normalize-space(.)][1]"), false);

        // Program
        $accounts = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Frequent flyer")) . "]/following::text()[normalize-space(.)][1]", null, "#Miles & More\s*(\d{5,})\s*$#"));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total fare:")) . "]", null, true, "#" . $this->preg_implode($this->t("Total fare:")) . "\s*(.+)#");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $seats = [];
        $seatsStr = $this->http->FindNodes("//text()[" . $this->eq($this->t("Seats")) . "]/following::text()[normalize-space(.)][1]");

        foreach ($seatsStr as $str) {
            $list = array_map('trim', explode(",", $str));

            foreach ($list as $n => $seat) {
                if (preg_match("#^(\d{1,3}[A-Z])$#", $seat)) {
                    $seats[$n][] = $seat;
                }
            }
        }

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/following::table[1]//tr[count(.//td[normalize-space()]) >1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
//        $xpath = "//*[".$this->eq($this->t("Flight details"))."]/following-sibling::table//tr[count(.//td) > 3 and .//td[1][contains(@width, '30%')]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./td[3]", $root, true, "#(\w{2})\s+\d+#"))
                ->number($this->http->FindSingleNode("./td[3]", $root, true, "#\w{2}\s+(\d+)#"))
            ;

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("(./td[1]//text()[normalize-space(.)])[1]", $root))
                ->date($this->normalizeDate(implode(" ", $this->http->FindNodes("(./td[1]//text()[normalize-space(.)])[position()>1]", $root))))
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("(./td[2]//text()[normalize-space(.)])[1]", $root))
                ->date($this->normalizeDate(implode(" ", $this->http->FindNodes("(./td[2]//text()[normalize-space(.)])[position()>1]", $root))))
            ;

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("./td[4]", $root, true, "#(.+?)\s*\([A-Z]{1,2}\)#"))
                ->bookingCode($this->http->FindSingleNode("./td[4]", $root, true, "#\(([A-Z]{1,2})\)#"));

            if (!empty($seats[$i])) {
                $s->extra()
                    ->seats($seats[$i]);
            }
            $stops = $this->http->FindSingleNode("(./td[4]//text()[normalize-space(.)])[last()][" . $this->contains($this->t("stop")) . "]", $root);

            if (preg_match("#^\D*(\d+)\D*$#", $stops, $m)) {
                $s->extra()->stops($m[1]);
            } else {
                $s->extra()->stops(0);
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 25/02/2020
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*$#",
            // Di, 22 Mrz 2016, 20:50
            "#^\s*\w+[,.\s]+(\d+)\s+(\w+)[.]?\s+(\d{4})[, ]+(\d{1,2}:\d{2})\s*$#u",
        ];
        $out = [
            "$1.$2.$3",
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($en = MonthTranslate::translate($m[1], 'es')) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

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
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
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
}
