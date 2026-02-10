<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHtml2018 extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-229031203.eml, eurobonus/it-284278591-sv.eml, eurobonus/it-29756340.eml, eurobonus/it-30233455.eml, eurobonus/it-30265067.eml, eurobonus/it-30675698.eml, eurobonus/it-31707522.eml, eurobonus/it-32650014.eml, eurobonus/it-32667973.eml, eurobonus/it-32784779.eml, eurobonus/it-354474686.eml";

    public $lang = '';
    public static $dictionary = [
        "en" => [
            "Booking ref:"  => ["Booking ref:", "Booking ref"],
            "direction"     => ["OUTBOUND", "RETURN"],
            //            "Departure Terminal" => "",
            //            "Arrival Terminal" => "",
            //            "Booking class" => "",
            "Select Seat"  => ["Select Seat", "Add lounge"],
            "Seat:"        => ['Seat:', 'Seats:'],
            //            "Frequent flyer program" => "",
            //            "Flights" => "",
            "PTS"          => ["PTS", 'p'],
            "Taxes & fees" => ["Taxes & fees", "Taxes & carrier-imposed fees"],
            //            "E-ticket number" => "",
            "paymentDetails" => ["Payment details", "Payment Details", "PAYMENT DETAILS"],
        ],
        "sv" => [
            "Booking ref:"            => ["Bokningsref.:", "Bokningsref:", "Bokningsref"],
            "direction"               => ["UTRESA", "HEMRESA"],
            "Departure Terminal"      => "Avgångsterminal",
            "Arrival Terminal"        => "Ankomstterminal",
            "Booking class"           => "Bokningsklass",
            "Select Seat"             => ["Välj sittplats", "Lägg till lounge"],
            "Seat:"                   => ["Seat:", "Sittplats:", "Sittplatser:"],
            "Frequent flyer program"  => "Bonusprogram",
            "Flights"                 => ["Flygningar", "Flyg"],
            "PTS"                     => ["POÄNG", 'p'],
            "Taxes & fees"            => ["Skatter och avgifter", "Skatter och serviceavgifter"],
            "E-ticket number"         => ["E-biljettnummer"],
            "paymentDetails"          => ["Betalningsuppgifter", "BETALNINGSUPPGIFTER"],
        ],
        "no" => [
            "Booking ref:"            => ["Bestillingsreferanse:", "Bestillingsreferanse"],
            "direction"               => ["UTREISE", "UTGÅENDE", "HJEMREISE"],
            "Departure Terminal"      => "Avgangsterminal",
            "Arrival Terminal"        => "Ankomstterminal",
            "Booking class"           => "Bestillingsklasse",
            "Select Seat"             => ["Velg sete", "Legg til lounge"],
            "Seat:"                   => ["Sete:", "Seter:"],
            "Frequent flyer program"  => ["Bonusprogram for de som reiser mye", "Bonusprogram"],
            "Flights"                 => "Flygninger",
            "PTS"                     => ["POENG", "p"],
            "Taxes & fees"            => ["Skatter og avgifter", "Skatter og servicegebyr"],
            "E-ticket number"         => "E-billettnummer",
            "paymentDetails"          => ["Betalingsdetaljer", "BETALINGSDETALJER"],
        ],
        "de" => [
            "Booking ref:"            => ["Buchungsref.:", "Buchungsref."],
            "direction"               => ["HINFLUG", "RÜCKFLUG"],
            "Departure Terminal"      => "Abflugterminal",
            "Arrival Terminal"        => "Ankunftsterminal",
            "Booking class"           => "Buchungsklasse",
            // "Select Seat" => "",
            "Seat:"                   => "Sitzplatz:",
            "Frequent flyer program"  => "Vielfliegerprogramm",
            "Flights"                 => "Flüge",
            "PTS"                     => ["PKTE.", 'p'],
            "Taxes & fees"            => ["Steuern und Gebühren", "Steuern und von der Fluggesellschaft erhobene Gebühren"],
            "E-ticket number"         => "E-Ticket-Nummer",
            "paymentDetails"          => ["Zahlungsdetails", "ZAHLUNGSDETAILS"],
        ],
        "fr" => [
            "Booking ref:"        => ["Référence de réservation:", "Référence de réservation"],
            "direction"           => ["ALLER", "RETOUR"],
            "Departure Terminal"  => "Terminal de départ",
            "Arrival Terminal"    => "Terminal d’arrivée",
            "Booking class"       => "Classe de réservation",
            // "Select Seat" => "",
            "Seat:"                  => ["Sièges:", "Siège:"],
            "Frequent flyer program" => "Programme voyageur fréquent",
            "Flights"                => "Vols",
            //            "PTS" => "",
            "Taxes & fees"    => "Taxes et frais imposés par le transporteur",
            "E-ticket number" => "Numéro du billet électronique",
            "paymentDetails"  => ["Informations de paiement", "Informations De Paiement", "INFORMATIONS DE PAIEMENT"],
        ],
        "es" => [
            "Booking ref:"        => ["Código de reserva:", "Código de reserva"],
            "direction"           => ["IDA", "VUELTA"],
            "Departure Terminal"  => "Terminal de salida",
            "Arrival Terminal"    => "Terminal de llegada",
            "Booking class"       => "Clase de reserva",
            // "Select Seat" => "",
            "Seat:"                  => ["Asiento:", "Asientos:"],
            "Frequent flyer program" => "Programa de viajero frecuente",
            "Flights"                => "Vuelos",
            //            "PTS" => "",
            "Taxes & fees"    => "Impuestos y cargos del operador",
            "E-ticket number" => "Número de billete electrónico",
            "paymentDetails"  => ["Datos de pago", "Datos De Pago", "DATOS DE PAGO"],
        ],
        "da" => [
            "Booking ref:"            => ["Bookingref.:", "Bookingref:", 'Bookingref'],
            "direction"               => ["UDREJSE", "RETUR"],
            "Departure Terminal"      => "Afgangsterminal",
            "Arrival Terminal"        => "Ankomstterminal",
            "Booking class"           => ["Bookingklasse", "Reserveringsklasse"],
            // "Select Seat" => "",
            "Seat:"                   => ["Sæde:", "Sæder:"],
            "Frequent flyer program"  => "Bonusprogram",
            "Flights"                 => "Flyvninger",
            "PTS"                     => ["POINT", "p"],
            "Taxes & fees"            => "Skatter og servicegebyrer",
            "E-ticket number"         => "E-billetnummer",
            "paymentDetails"          => ["Betalingsoplysninger", "BETALINGSOPLYSNINGER"],
        ],
    ];

    private $detectFrom = ["@sas.", "flysas.com"];

    private $detectSubject = [
        "en" => "#Your Flight \[[^\]]+\], Booking ?: ?\[[A-Z\d]+\]#",
        "sv" => "#Din flygning \[[^\]]+\], Bokning ?: ?\[[A-Z\d]+\]#",
        "no" => "#Din flygning \[[^\]]+\], Bestilling ?: ?\[[A-Z\d]+\]#",
        "de" => "#Ihr Flug \[[^\]]+\], Buchung ?: ?\[[A-Z\d]+\]#",
        "fr" => "#Votre vol \[[^\]]+\], Réservation ?: ?\[[A-Z\d]+\]#",
        "es" => "#Su vuelo \[[^\]]+\], Reserva ?: ?\[[A-Z\d]+\]#",
        "da" => "#Din flyvning \[[^\]]+\], Booking ?: ?\[[A-Z\d]+\]#",
    ];

    private $detectBody = [
        "en" => ["BOOKING CONFIRMATION", "Here's your booking reference and information about your trip."],
        "sv" => ["BOKNINGSBEKRÄFTELSE", "TACK FÖR ATT DU FLYGER MED OSS"],
        "no" => ["TAKK FOR AT DU FLYR MED OSS"],
        "de" => ["VIELEN DANK, DASS SIE MIT UNS FLIEGEN"],
        "fr" => ["MERCI D’AVOIR CHOISI NOTRE COMPAGNIE ET BON VOL"],
        "es" => ["MUCHAS GRACIAS POR VIAJAR CON NOSOTROS"],
        "da" => ["TAK, FOR AT DU FLYVER MED OS"],
    ];

    public function parseEmail(Email $email): void
    {
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"dd:dd")';

        $f = $email->add()->flight();
//        if (strpos($this->http->Response['body'], '�') !== false)
//            $this->http->SetBody(str_replace("�", '–', $this->http->Response['body']));
        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking ref:")) . "]/following::text()[normalize-space()])[1]", null, true,
                    "#^\s*([A-Z\d]{5,7})\s*$#"));

        $travellers = array_values(array_filter($this->http->FindNodes("//tr[" . $this->eq($this->t("E-ticket number")) . "]/following-sibling::tr/td[1]", null, "#^\s*(\D+)\s*$#")));

        if (empty($travellers)) {
            $travellers = array_values(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Frequent flyer program")) . "]/preceding::text()[normalize-space()][position() < 3][not(starts-with(normalize-space(), '('))][1]", null, "#^\s*(\D+)\s*$#")));
        }
        $f->general()
            ->travellers($travellers, true);

        // Price
        $cost = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Taxes & fees")) . "]/preceding::td[" . $this->eq($this->t("Flights")) . "]/following-sibling::td[1]");

        if (preg_match("/^\s*(\d[,.‘\'\d ]*\s*{$this->preg_implode($this->t("PTS"))})\s*$/u", $cost, $m)
            || preg_match("/^\s*(\d[,.‘\'\d ]*?)\s*$/u", $cost, $m)
        ) {
            // 40000 PTS
            $f->price()->spentAwards($m[1]);
        } elseif (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*)\s*(?<currency>[A-Z]{3})\s*$/u", $cost, $m)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode))->currency($m['currency']);
        }

        $feeRows = $this->http->XPath->query("//tr[{$this->eq($this->t("paymentDetails"))}]/following::tr[*[1][{$this->eq($this->t('Flights'))}]]/following-sibling::tr[count(*[normalize-space()])=2]");

        foreach ($feeRows as $feeRow) {
            $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $feeCharge, $m)) {
                // 1 958 SEK
                $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode))->currency($m['currency']);
            }
        }

        if ($f->getPrice() && $f->getPrice()->getCurrencyCode()) {
            $root = $this->http->XPath->query("//td[not(.//tr) and {$this->eq($this->t("Taxes & fees"))}]");

            for ($i = 0; $i < 10 && $root->length > 0; $i++) {
                $root = $this->http->XPath->query('./ancestor::tr[1]', $root->item(0));

                if ($total = $this->http->FindSingleNode('./following-sibling::tr[1]', $root->item(0), true, '/^(?:\D*|.*' . $this->preg_implode($this->t("PTS")) . '\D*)(\d[\d\, \.]+)\s*' . $f->getPrice()->getCurrencyCode() . '/')) {
                    $f->price()->total(PriceHelper::parse($total, $f->getPrice()->getCurrencyCode()));

                    break;
                }
            }
        }

        $tickets = array_values(array_filter($this->http->FindNodes("//tr[" . $this->eq($this->t("E-ticket number")) . "]/ancestor::*[1]//tr[not(.//tr) and count(td[normalize-space()])=2]/td[normalize-space()][2]", null, "#^\s*([\d\-]{9,})\s*$#")));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        $accounts = array_values(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Frequent flyer program")) . "]/following::text()[normalize-space()][1]", null, "#^\s*([A-Z]{3}\d{5,})\b#")));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        // Segments

        // it-29756340.eml
        $xpath = "//tr[ *[2][{$xpathTime}] ]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            // it-284278591-sv.eml
            $xpath = "//tr[ count(*)=2 and *[1][not(.//tr) and descendant::text()[{$this->starts($this->t("Departure Terminal"))} or {$this->starts($this->t("Arrival Terminal"))} or {$this->starts($this->t("Booking class"))}]] ]/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr) and {$this->eq($this->t("direction"))}][1]/following::text()[normalize-space()][1]", $root, true, "/(.+ \d{4}).*/"));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[not(.//tr) and {$this->eq($this->t("direction"))}][1]/following::*[1]", $root, true, "/(\d{1,2}\s*[[:alpha:]]+\s*\d{4}).*/u"));
            }

            if (empty($date)) {
                $this->logger->alert("not detect date");

                continue;
            }

            $route = $this->http->FindSingleNode("./tr[1]/td[1]", $root);

            if (empty($route)) {
                $route = $this->http->FindSingleNode(".", $root);
            }

            if (preg_match("#(?<dName>.+?)[ ]*(?<dCode>[A-Z]{3})\s*[\-–]\s*(?<aName>.+?)[ ]*(?<aCode>[A-Z]{3})#u", $route, $m)) {
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                ;
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                ;
            }

            $terminals = $this->http->FindSingleNode("./tr[2]/td[1]", $root);

            if (empty($terminals)) {
                $terminals = $this->http->FindSingleNode(".", $root);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Departure Terminal")) . "\s*(.+?)\s*(?:[\-–]|" . $this->preg_implode($this->t("Arrival Terminal")) . "|[A-Z\d]{2}|$)#u", $terminals, $m)) {
                $s->departure()->terminal($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Arrival Terminal")) . "\s*(.+?)\s*(?:[A-Z\d]{2}|$)#", $terminals, $m)) {
                $s->arrival()->terminal($m[1]);
            }

            $times = $this->http->FindSingleNode("./tr[1]/td[2]", $root);

            if (empty($times)) {
                $times = $this->http->FindSingleNode(".", $root);
            }

            if (preg_match("#(?<dTime>[\d].+?)\s*[\-–]\s*(?<aTime>.+?)\s*\(([^)]+)\)#u", $times, $m)) {
                $s->departure()->date(strtotime($m['dTime'], $date));
                $s->arrival()->date(strtotime($m['aTime'], $date));
                $s->extra()->duration($m[3]);
            }

            /*
                SK4755 | Boeing 737-800

                [OR]

                Departure Terminal 3
                SK 627 | Canadair Regional Jet 900 | Cityjet
                Booking class U
            */
            $extraText = $this->http->FindSingleNode("tr[3]/*[normalize-space()][1]", $root) // it-29756340.eml
                ?? $this->htmlToText($this->http->FindHTMLByXpath("descendant-or-self::tr[ *[2] ][1]/*[1]/descendant::div[normalize-space()][last()]", null, $root)); // it-284278591-sv.eml

            if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*\|\s*(?<aircraft>[^\|]*?)\s*(?:\||$)/", $extraText, $m) // it-29756340.eml
                || preg_match("/^[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)[ ]*(?:\|.*)+$/m", $extraText, $m) // it-284278591-sv.eml
            ) {
                $s->airline()->name($m['name'])->number($m['number']);

                if (!empty($m['aircraft'])) {
                    $s->extra()->aircraft($m['aircraft']);
                }
            }

            if (preg_match("/^[ ]*{$this->preg_implode($this->t("Booking class"))}[ ]+(?<bookingCode>[A-z]{1,2})[ ]*$/m", $extraText, $m)) {
                // it-284278591-sv.eml
                $s->extra()->bookingCode($m['bookingCode']);
            } else {
                // it-29756340.eml
                $bookingCode = $this->http->FindSingleNode("tr[3]/*[normalize-space()][2]", $root, true, "/^{$this->preg_implode($this->t("Booking class"))}\s+([A-z]{1,2})$/i");
                $s->extra()->bookingCode($bookingCode, false, true);
            }

            $seatsText = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Seat:"))}]", $root, true, "/{$this->preg_implode($this->t("Seat:"))}\s*(\d[,A-Z\d\s]*[A-Z])$/");

            if (!empty($seatsText)) {
                $seatsV = preg_split('/(\s*,\s*)+/', $seatsText);
                $seats = [];
                $number = '';

                foreach ($seatsV as $value) {
                    if (preg_match("#^[A-Z]$#", $value) && !empty($number)) {
                        $seats[] = $number . $value;

                        continue;
                    }

                    if (preg_match("#^(\d{1,3})[A-Z]$#", $value, $m)) {
                        $seats[] = $value;
                        $number = $m[1];

                        continue;
                    }
                    $seats = [];
                    $this->logger->debug("parse seat is failed");

                    break;
                }

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $dBody . '")]')->length > 0) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $foundFrom = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $foundFrom = true;
            }
        }

        if ($foundFrom === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($dSubject, "#") === 0) {
                if (preg_match($dSubject, $headers["subject"])) {
                    return true;
                }
            } else {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.flysas.com/")]')->length === 0
            && $this->http->XPath->query('//img[contains(@src,"www.flysas.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"flysas.com")]')->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $dBody . '")]')->length > 0) {
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
            "#^\s*(\d{1,2}) ([^\s\d\.\,]+)[\.]? (\d{4})\s*$#", //29 Nov 2018
            "#^(\d{1,2})\s(\d{1,2})\s(\d{4})#", //13 2 2020
        ];
        $out = [
            '$1 $2 $3',
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
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
