<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItinerarySummary extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-136642372.eml, aa/it-6706765.eml, aa/it-67542051.eml, aa/it-6935292.eml, aa/it-96141131.eml, aa/it-96610060.eml";
    public $reFrom = ["@aa.com", "American.Airlines@info.email.aa.com"];
    public $reSubject = [
        "en" => "AA.com Itinerary Summary On Hold",
        "es" => "Resumen del itinerario puesto en espera en aa.com",
        "pt" => "Resumo de itinerário em lista de espera do aa.com",
        "de" => "Your trip confirmation", // + fr,nl, it
    ];
    public $reBody = 'www.aa.com';
    public $reBody2 = [
        "es"   => "Código de reserva de",
        "es2"  => "Código de la reservación",
        "es3"  => "Localizador de la Reserva",
        "en"   => "Thank you for making your travel arrangements on",
        "en2"  => "For information regarding American Airlines checked baggage policies, please visit",
        "en3"  => "AA Record Locator:",
        "en4"  => 'American does not guarantee to provide any particular seat on the aircraft.',
        "en5"  => 'Record Locator:',
        "pt"   => 'Obrigado por planejar sua viagem no AA.com',
        "pt2"  => 'Informações sobre o Passageiro',
        "pt3"  => 'Obrigado por escolher a American Airlines',
        "de"   => 'Wir bedanken uns für Ihre Buchung bei American Airlines',
        "de2"  => 'Eine Gesichtsbedeckung ist Pflicht, wenn Sie mit American Airlines fliegen',
        "de3"  => 'Danke, dass Sie sich für American Airlines entschieden haben',
        "fr"   => 'Merci d\'avoir choisi American Airlines, une compagnie membre',
        "nl"   => 'Datum van uitgifte:',
        "it"   => 'Codice prenotazione:',
    ];

    public static $dictionary = [
        "en" => [
            "Record Locator:" => ["Record Locator:", "AA Record Locator:"],
            // "Record Locator" => "",
            "Passenger" => ["Passenger", "PASSENGER"],
        ],
        "es" => [
            //            "Date of Issue:" => "",
            "Record Locator:"     => ["Código de reserva de American Airlines:", "Código de la reservación:", "Localizador de la Reserva:"],
            "Record Locator"      => ["Código de reserva", "Código de la reservación"],
            "Passenger"           => ["Passenger", "Pasajeros", "PASAJERO"],
            "TICKET NUMBER"       => ["NÚMERO DE BILLETE"],
            "FF#:"                => ["Viajero Frecuente AAdvantage:"],
            "Total Price"         => ["Total Price", "Precio Total"],
            // "Status:" => ["Status:", ""],
            "Departing"   => ["Departing", "Saliendo de", "Salida"],
            "OPERATED BY" => ["OPERATED BY"],
            // others are the same as 'en'
        ],
        "pt" => [
            //            "Date of Issue:" => "",
            "Record Locator:" => ["Código da reserva:", 'Código de reserva da American Airlines:'],
            "Record Locator"  => ["Código da reserva"],
            "Passenger"       => ["Passageiros", 'Passageiro'],
            //            "TICKET NUMBER"       => [""],
            //            "FF#:"       => [""],
            "Total Price"     => ["Preço Total"],
            // "Status:" => ["Status:", ""],
            "Departing" => ["Partida", "Partindo"],
            // "OPERATED BY" => ["OPERATED BY", ""],
        ],
        "de" => [
            "Date of Issue:"      => "Ausstellungsdatum:",
            "Record Locator:"     => ["Buchungsreferenz:"],
            "Record Locator"      => ["Buchungsreferenz"],
            "Passenger"           => ["PASSAGIERNAMEN"],
            "TICKET NUMBER"       => ["TICKETNUMMER"],
            "FF#:"                => ["Vielflieger-nummer:"],
            //            "Total Price"     => [""],
            "Total:"     => ["Endpreis:"],
            "FARE-"      => ["TARIF-"],
            // "Status:" => ["Status:", ""],
            "Departing" => ["Abflug"],
            // "OPERATED BY" => ["OPERATED BY", ""],
        ],
        "fr" => [
            "Date of Issue:"      => "Date d'émission:",
            "Record Locator:"     => ["Référence de dossier:"],
            "Record Locator"      => ["Référence de dossier"],
            "Passenger"           => ["PASSAGER"],
            "TICKET NUMBER"       => ["NUMÉRO DE BILLET"],
            //            "FF#:"                => ["Vielflieger-nummer:"],
            //            "Total Price"     => [""],
            "Total:"     => ["Total:"],
            "FARE-"      => ["TARIF-"],
            // "Status:" => ["Status:", ""],
            "Departing" => ["Départ"],
            // "OPERATED BY" => ["OPERATED BY", ""],
        ],
        "nl" => [
            "Date of Issue:"      => "Datum van uitgifte:",
            "Record Locator:"     => ["Record-locator:"],
            "Record Locator"      => ["Record-locator"],
            "Passenger"           => ["PASSAGIER"],
            "TICKET NUMBER"       => ["TICKETNUMMER"],
            "FF#:"                => ["FF nr:"],
            //            "Total Price"     => [""],
            "Total:"     => ["Totaal:"],
            "FARE-"      => ["PRIJS-"],
            // "Status:" => ["Status:", ""],
            "Departing" => ["Vertrek"],
            // "OPERATED BY" => ["OPERATED BY", ""],
        ],
        "it" => [
            "Date of Issue:"      => "Data di emissione:",
            "Record Locator:"     => ["Codice prenotazione:"],
            "Record Locator"      => ["Codice prenotazione"],
            "Passenger"           => ["PASSEGGERO"],
            "TICKET NUMBER"       => ["NUMERO DI BIGLIETTO"],
            // "FF#:"                => ["FF nr:"],
            //            "Total Price"     => [""],
            "Total:"     => ["Totale:"],
            "FARE-"      => ["TARIFFA-"],
            // "Status:" => ["Status:", ""],
            "Departing" => ["Partenza"],
            // "OPERATED BY" => ["OPERATED BY", ""],
        ],
    ];

    public $lang = "en";
    private $date;

    public function parseHtml(Email $email)
    {
        $flight = $email->add()->flight();

        $issuedDate = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Date of Issue:")) . "]", null, true, "/:\s*(.+)/"));

        if (!empty($issuedDate)) {
            $flight->general()
                ->date($issuedDate);
            $this->date = $issuedDate;
        }

        $confNumber = $this->nextText($this->t("Record Locator:"));

        if (!empty($confNumber)) {
            $confDescription = $this->http->FindSingleNode('//text()[' . $this->eq($this->t("Record Locator:")) . ']', null, true, '/^([\w\s]+)\:?$/');
            $flight->general()
                ->confirmation($confNumber, $confDescription);
        }

        if (empty($confNumber)) {
            $confText = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("Record Locator:")) . ']');

            if (preg_match("/^\s*(" . $this->opt($this->t("Record Locator:")) . ")\s*([A-Z\d]{5,7})\s*$/", $confText, $m)) {
                $confNumber = $m[2];
                $flight->general()
                    ->confirmation($m[2], trim($m[1], ':'));
            }
        }

        if (empty($confNumber) && empty($this->http->FindSingleNode('//text()[' . $this->contains($this->t("Record Locator")) . ']'))) {
            $flight->general()
                ->noConfirmation();
        }

        $travellers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::*[self::td or self::th][1]/descendant::text()[normalize-space(.)][position()>1]"));

        if (!$travellers) {
            $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::tr[1][.//*[self::td or self::th][{$this->eq($this->t('Passenger'))}]]/following-sibling::tr/td[1]");
        }

        if (!$travellers) {
            $travellers = $this->http->FindNodes("//tr[{$this->starts($this->t('Passenger'))}]/ancestor::table[1][.//*[self::td or self::th][not(.//td) and not(.//th) and {$this->starts($this->t('Passenger'))} and count(.//descendant::text()[normalize-space()]) = 1]]/descendant::tr[normalize-space()][not (contains(normalize-space(.), 'Traveler Information'))][not ({$this->contains($this->t('Passenger'))})]/descendant::td[1]");
        }

        if (!$travellers) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}][following::text()[{$this->eq($this->t("Departing"))}]]/ancestor::td[1][descendant::text()[normalize-space()][{$this->eq($this->t('Passenger'))}]]/descendant::text()[normalize-space()][position() > 1]");
        }

        if (empty($travellers) && empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('Passenger'))}])[1]"))) {
        } else {
            $flight->general()
                ->travellers(array_unique($travellers), true);
        }

        // Issuied
        $ticket = array_filter($this->http->FindNodes("//tr[" . $this->eq($this->t("TICKET NUMBER")) . "]/following-sibling::tr"));

        $filterTicket = array_filter($ticket, function ($v) {
            if (preg_match("/^\s*\d{10,13}\s*$/", $v)) {
                return true;
            }

            return false;
        });

        if (count($ticket) == count($filterTicket)) {
            $flight->issued()
                ->tickets($filterTicket, false);
        }

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes("//td[not(.//td) and " . $this->starts($this->t("FF#:")) . "]",
            null, "/:\s*([A-Z\d]{5,})\s*$/")));

        if (!empty($accounts)) {
            $flight->program()
                ->accounts($accounts, false);
        }

        $seats = [];
        $cabins = [];

        $psngrs = $this->http->XPath->query("//tr[.//text()[" . $this->eq($this->t('Passenger')) . "] and not(.//tr)]/ancestor::*[1]/following-sibling::*[1]/tr");

        if ($psngrs->length === 0) {
            $psngrs = $this->http->XPath->query("//tr[.//text()[" . $this->eq($this->t('Passenger')) . "] and not(.//tr)]/following::tr[1]/ancestor::*[1]/tr");
        }

        if ($psngrs->length > 0) {
            $forSeats = [];
            $forCabins = [];

            foreach ($psngrs as $psngr) {
                $forSeats[] = $this->http->FindSingleNode('td[3]', $psngr);
                $forCabins[] = $this->http->FindSingleNode('td[2]', $psngr);
            }
            $countPsngrs = count($flight->getTravellers());

            if (!empty($countPsngrs)) {
                $seats = array_chunk(array_filter($forSeats), $countPsngrs);
                $cabins = array_chunk($forCabins, $countPsngrs);
            }
        }

        $total = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Price")) . "]/ancestor::td[1]/following-sibling::td[1]"));
        $currency = $this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Price")) . "]/ancestor::td[1]/following-sibling::td[1]"));

        if (empty($total) && empty($currency)) {
            $total = $this->amount($this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Total:")) . "]", null, true, "/:\s*(.+)/"));
            $currency = $this->currency($this->http->FindSingleNode("//*[{$this->eq($this->t('Passenger'))}]/following::td[not(.//td) and " . $this->starts($this->t("FARE-")) . "]", null, true, "/-([A-Z]{3})\s*$/"));

            if (empty($currency)) {
                $currency = $this->currency($this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Total:")) . "]", null, true, "/:\s*(\D)\s*[\d\.\,+]/u"));
            }
        }

        if (!empty($total)) {
            $flight->price()
                ->total($total)
                ->currency($currency);
        }

        $status = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Status:']/following::text()[string-length(normalize-space(.)) > 2][1]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Status:')) . "]", null, true, "/^\s*" . $this->opt($this->t("Status:")) . "\s*(.+)\s*$/");
        }

        if ($status) {
            $flight->general()->status($status);
        }

        $xpath = "//text()[{$this->eq($this->t("Departing"))}]/ancestor::table[1]//tr[count(td)>5 and not(.//td[{$this->eq($this->t("Departing"))}])]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (strlen($this->http->FindSingleNode("./td[1]", $root)) == 0) {
                continue;
            }
            $segment = $flight->addSegment();

            $segment->airline()
                ->name($this->http->FindSingleNode("./td[1]", $root, true, "#^(.*?)(?: OPERATED BY|$)#"))
                ->number($this->http->FindSingleNode("./td[2]", $root));

            $operator = $this->http->FindSingleNode("./td[1]", $root, true, "# OPERATED BY (.+)#");

            if (empty($operator)) {
                $operatorText = $this->http->FindSingleNode("./following::tr[1]/td[contains(., 'OPERATED BY')]", $root);

                if (preg_match("/OPERATED BY\s*(.+)\/[A-Z\d]{2}\s/", $operatorText, $m)
                    || preg_match("/OPERATED BY\s*(.+)\d{4}\s+[A-Z]{3}\s/", $operatorText, $m)
                    || preg_match("/OPERATED BY\s*(.+)$/", $operatorText, $m)) {
                    $operator = $m[1];
                }
            }

            if (preg_match("/\/\s*(\S.+?) FOR /", $operator, $mat)) {
                $operator = $mat[1];
            }

            if (!empty($operator)) {
                $segment->airline()
                    ->operator($operator);
            }

            $segment->departure()
                ->name($this->http->FindSingleNode("./td[3]", $root, true, "#^(.{4,})#"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

            if ($code = $this->http->FindSingleNode("./td[3]", $root, true, "#^([A-Z]{3}) #")) {
                $segment->departure()->code($this->http->FindSingleNode("./td[3]", $root, true, "#^([A-Z]{3}) #"));
            } else {
                $segment->departure()->noCode();
            }

            $segment->arrival()
                ->name($this->http->FindSingleNode("./td[5]", $root, true, "#^(.{4,})#"))
            ;
            $date = $this->http->FindSingleNode("./td[6]", $root);

            if (preg_match("/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu", $date) && !empty($segment->getDepDate())) {
                $segment->arrival()
                    ->date(strtotime($date, $segment->getDepDate()));
            } else {
                $segment->arrival()
                    ->date($this->normalizeDate($date));
            }

            if ($code = $this->http->FindSingleNode("./td[5]", $root, true, "#^([A-Z]{3}) #")) {
                $segment->arrival()->code($this->http->FindSingleNode("./td[5]", $root, true, "#^([A-Z]{3}) #"));
            } else {
                $segment->arrival()->noCode();
            }

            $cabin = $this->http->FindSingleNode("./td[7]", $root, true, "#^(.*?) [A-Z]$#");

            if (!empty($cabin)) {
                $segment->extra()
                    ->cabin($cabin);
            }

            $segment->extra()
                ->bookingCode($this->http->FindSingleNode("./td[7]", $root, true, "#\b([A-Z])\b#"));

//            проверить места, код пересекается с cabin
//            $seatsSeg = $this->http->FindSingleNode("./td[7]", $root, true, "#^(.*?) [A-Z]$#");

            $seatsSegText = $this->http->FindSingleNode("./td[8]", $root, true, "#^\s*(?: ?\b\d{1,3}[A-Z]\b)+\s*$#");

            $seatsSeg = null;

            if (!empty($seatsSegText) && preg_match_all("/(\b\d{1,3}[A-Z]\b)/", $seatsSegText, $m)) {
                $seatsSeg = $m[1];
            }

            if (!empty($seats) && empty($seatsSeg)) {
                $seatsSeg = array_shift($seats);
                $cab = array_filter(array_unique(array_shift($cabins)));

                if (empty($segment->getCabin()) && count($cab) === 1) {
                    $segment->extra()
                        ->cabin(array_shift($cab));
                }
            }

            if (empty($segment->getCabin())) {
                $cabin = $this->http->FindSingleNode("./td[7]/ancestor::tr[1]/following::tr[not(contains(normalize-space(), 'OPERATED BY'))][1]/descendant::td[3]", $root, true, "/^(\D+)$/");

                if (!empty($cabin)) {
                    $segment->extra()
                        ->cabin($cabin);
                }
            }

            if (!empty($seatsSeg)) {
                $segment->extra()
                    ->seats(array_filter($seatsSeg));
            }

            $meal = trim($this->http->FindSingleNode("./td[last()]", $root, false, '/^.{5,}$/'));

            if (empty($meal)) {
                $meal = $this->http->FindSingleNode("./td[7]/ancestor::tr[1]/following::tr[not(contains(normalize-space(), 'OPERATED BY'))][1]/descendant::td[last()]", $root);
            }

            if ($meal && $meal !== 'N/A') {
                $segment->extra()
                    ->meal($meal);
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $fFrom) {
            if (stripos($from, $fFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
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

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false || $this->http->XPath->query("//text()[{$this->contains($re)}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);
                $xpath = "//text()[{$this->eq($this->t("Departing"))}]/ancestor::table[1]//tr";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos(html_entity_decode($this->http->Response["body"]), $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
//         $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //ene 10, 2019 11:15 AM
            "#^\s*([^\d\s]+)\s*(\d+)\s*,\s*(\d{4})\s*(\d+:\d+(\s*[ap]m)?)\s*$#i",
            // 23 de junio de 2021 05:18 AM; 06 jun 2021 Ã s 19:50;
            "#^\s*(\d+)(?:\s+de)?\s+([^\d\s]+)(?:\s+de)?\s+(\d{4})(?:\s*Ã s\s*)?\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#iu",
            // THU 29AUG 6:40 PM; SA 08DEZ3:29 PM
            '/^(\D{2,3})\s+(\d+)(\D{3})\s*(\d+:\d+.+)$/iu',
            // 17AUG19
            '/^\s*(\d+)\s*([A-Z]{3})\s*(\d{2})\s*$/',
            //jun 11, 2022 11:55 a.m.
            "#^\s*([^\d\s]+)\s*(\d+)\s*,\s*(\d{4})\s*(\d+:\d+)\s*([ap])\.m\.\s*$#i",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1, $2 $3 $year, $4",
            "$1 $2 20$3",
            "$2 $1 $3, $4 $5m",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
