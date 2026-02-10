<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GetReadyForTrip extends \TAccountChecker
{
    public $mailFiles = "brussels/it-151400322.eml, brussels/it-96862096.eml, brussels/it-97491940.eml";

    private $detectFrom = ["noreply@notifications.brusselsairlines.com", "noreply@brusselsairlines.com", 'info@notification.brusselsairlines.com'];

    private $detectSubject = [
        // en
        "Get ready for your trip on",
        "Important message - Your flight has been re-scheduled (your response is required)",
        "Check in online for your next flight to",

        // fr
        "Tenez-vous prêt pour votre voyage du",

        // nl
        'Online inchecken voor je volgende vlucht naar',
        "Bereid je voor op je vlucht van",

        // de
        "Online einchecken für Ihren nächsten Flug",
        "Bereiten Sie sich auf Ihren Flug am",

        // es
        'Prepárate para tu vuelo del',
    ];

    private $detectProvider = 'brusselsairlines.com';

    private $detectBody = [
        "en" => ["CHECK YOUR FLIGHT DETAILS", "Rebooked flight:","Your next flight with Brussels Airlines to ", 'your seat reservation has changed', 'we have compiled the most important information'],
        "fr" => ["Vérifier les détails de votre vol", 'Votre vol Brussels Airlines pour '],
        "nl" => ["Online inchecken", "BEKIJK JE VLUCHTGEGEVENS", "uw stoelreservering gewijzigd hebben", 'we de meest interessante informatie'],
        "de" => ["Ihr nächster Flug mit Brussels Airlines fliegt in", "ÜBERPRÜFEN SIE IHRE FLUGDATEN"],
        "es" => ["CONSULTAR LOS DATOS DE TU VUELO"],
    ];

    private $lang = "en";

    private static $dictionary = [
        "en" => [
            //"Your booking reference" => "",
            //"Dear " => "",
            //"guest" => "",
            //"Flight number" => "",
            //"Name:" => "",
            //"New seat:" => "",
            //"Terminal" => "",
            //"Travel class" => "",
            //"Operated by" => "",
            //"Duration" => "",
            "Original flight:" => ['Original flight:', 'Original flight(s):'],
        ],
        "fr" => [
            "Your booking reference" => "Votre numéro de réservation",
            "Dear " => ["Chère/Cher", "Chère"],
            "guest" => ["passagère", "passager"],
            "Flight number" => "Vol",
            //"Name:" => "",
            //"New seat:" => "",
            "Terminal" => "Terminal",
            //"Travel class" => "",
            //"Operated by" => "",
            //"Duration" => "",
        ],
        "nl" => [
            "Your booking reference" => "Uw boekingsreferentie",
            "Dear " => "Beste",
            "guest" => ["gast", "passagier"],
            "Flight number" => ["Vlucht", "VLUCHTNUMMER"],
            //"Name:" => "",
            "New seat:" => "Nieuwe stoel:",
            //"Terminal" => "",
            "Travel class" => "PRODUCT",
            "Operated by" => "VLUCHT DOOR",
            "Duration" => "DUUR",
        ],
        "de" => [
            "Your booking reference" => ["Ihre Buchungsnummer", "Buchungsnummer"],
            "Dear " => "Sehr geehrter ",
            "guest" => ["gast", "Fluggast"],
            "Flight number" => "Flug",
            //"Name:" => "",
            //"New seat:" => "",
            //"Terminal" => "",
            "Travel class" => "REISEKLASSE",
            "Operated by" => "DURCHGEFÜHRT VON",
            "Duration" => "REISEZEIT",
        ],
        "es" => [
            "Your booking reference" => ["Código de reserva", "Tu número de reserva es"],
            "Dear " => "Estimado/a",
            "guest" => ["pasajero"],
            "Flight number" => "Vuelo",
            //"Name:" => "",
            //"New seat:" => "",
            "Terminal" => "Terminal",
//            "Travel class" => "REISEKLASSE",
//            "Operated by" => "DURCHGEFÜHRT VON",
//            "Duration" => "REISEZEIT",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Your booking reference']) && $this->http->XPath->query("//*[" . $this->contains($dict['Your booking reference']) . "]")->length > 0) {
                $this->lang = $lang;
                break;
            }
        }

        $this->parseHtml($email);

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
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if ($this->striposAll($headers['from'], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//a[contains(@href, '" . $this->detectProvider . "')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//img[contains(@src, 'olci_path.png') or contains(@src, '/Others/path.png')]")->length > 0) {
            return true;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your booking reference")) . "]/following::text()[normalize-space(.)][1]"))
        ;
        $travellers = $this->http->FindNodes("//text()[". $this->starts($this->t("New seat:"))."]/preceding::text()[normalize-space()][1]", null, "/: *([[:alpha:] \-]+?)\s*$/u");
        if (!empty($travellers)) {
            $f->general()
                ->travellers($travellers, true);
        } else {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
                "/" . $this->preg_implode($this->t("Dear ")) . "\s*([[:alpha:] \-\\/]+),\s*$/u");

            if (!preg_match("/\b" . $this->preg_implode($this->t("guest")) . "\b/i", $traveller)) {
                $f->general()
                    ->traveller($traveller);
            }
        }

        $xpath = "//tr[count(td) = 2 and count(td[contains(translate(.,'0123456789', 'dddddddddd'),'dd:dd')])]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {

            $info = '';

            $dXpath = "following::text()[normalize-space()][1]/ancestor::td[3][not(.//img)]/descendant::*[count(tr) = 2 and count(.//td[not(.//td)][normalize-space()]) = 2]";
            foreach ($this->http->XPath->query($dXpath, $root) as $r) {
                $info .= "\n" . implode(": ", $this->http->FindNodes(".//td[not(.//td)][normalize-space()]", $r));
            }
            $info .= "\n";

            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("following::text()[normalize-space(.)][1]", $root);
            if (preg_match("/".$this->preg_implode($this->t("Flight number"))." *: *([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})\s*\n/ui", $info, $m)
                || preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }
            if (preg_match("/".$this->preg_implode($this->t("Operated by"))." *: *(.+)/ui", $info, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }


            $airportXpath = "preceding::tr[count(td) = 3 and td[2][.//img]][1]";

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode($airportXpath . "/td[1]/descendant::text()[normalize-space(.)][1]", $root))
                ->name($this->http->FindSingleNode($airportXpath . "/td[1]/descendant::text()[normalize-space(.)][2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("td[1]", $root)))
            ;

            if (preg_match("/".$this->preg_implode($this->t("Terminal"))." *: *(.+)/ui", $info, $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode($airportXpath . "/td[3]/descendant::text()[normalize-space(.)][1]", $root))
                ->name($this->http->FindSingleNode($airportXpath . "/td[3]/descendant::text()[normalize-space(.)][2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("td[2]", $root)))
            ;

            if ($this->http->FindSingleNode("(./preceding::node()[" . $this->contains($this->t("Original flight:")) . "])[1]", $root)) {
                $s->extra()
                    ->cancelled();
            }

            if (preg_match("/".$this->preg_implode($this->t("Travel class"))." *: *(.+)/ui", $info, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }
            if (preg_match("/".$this->preg_implode($this->t("Duration"))." *: *(.+)/ui", $info, $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            if ($nodes->length == 1) {
                $seats = $this->http->FindNodes("//text()[". $this->starts($this->t("New seat:"))."]", null, "/: *(\d{1,3}[A-Z])\s*$/");
                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
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
            //            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*$#",
            // Di, 22 Mrz 2016, 20:50
            //            "#^\s*\w+[,.\s]+(\d+)\s+(\w+)[.]?\s+(\d{4})[, ]+(\d{1,2}:\d{2})\s*$#u",
        ];
        $out = [
            //            "$1.$2.$3",
            //            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
