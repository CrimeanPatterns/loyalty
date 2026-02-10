<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationSummary extends \TAccountCheckerExtended
{
    public $mailFiles = "alitalia/it-12234065.eml, alitalia/it-3652224.eml, alitalia/it-3678575.eml, alitalia/it-3679293.eml, alitalia/it-4146251.eml, alitalia/it-4834711.eml, alitalia/it-4843339.eml, alitalia/it-5213195.eml, alitalia/it-9752132.eml, alitalia/it-9786025.eml, alitalia/it-9842144.eml";

    public $reBody = ['alitalia.com', 'itaspa.com'];
    public $reBody2 = [
        "he"  => "מושב",
        "pl"  => "RACHUNEK ZA E-BILET LINII",
        "it"  => "RICEVUTA BIGLIETTO",
        "es"  => "RECIBO DE BILLETE",
        "de"  => "ALITALIA-TICKETBELEG",
        "fr"  => "U DU BILLET",
        "en"  => "TICKET RECEIPT",
        "nl"  => "ONTVANGSTBEWIJS ALITALIA TICKET",
        "nl2" => "ONTVANGSTBEWIJS ITA SPA TICKET",
        "nl3" => "ONTVANGSTBEWIJS TICKET",
        "pt"  => "RECIBO DE BILHETE",
        "el"  => "ΑΠΟΔΕΙΞΗ ΕΙΣΙΤΗΡΙΟΥ",
        "bg"  => "РАЗПИСКА ЗА БИЛЕТ",
    ];
    public $reSubject = [
        // en
        "Summary of your booking",
        // it
        "Riepilogo della tua prenotazione",
        // es
        "Resumen de su reserva",
        // nl
        "Uw selectie",
        // pt
        "Resumo de sua reserva",
        // el
        "Περίληψη της κράτησής σας",
        // bg
        "Podsumowanie rezerwacji",
        // fr
        'Récapitulatif de votre réservation',
    ];

    public static $dictionary = [
        "en" => [
            "Total"              => ["Total Paid", "TOTAL PAID"],
            "Adult Fare"         => ["Adult Fare", "ADULT FARE"],
            "Child Fare"         => ["Child Fare", "Child Fare"],
            "Additional charges" => "Additional charges",
            "Taxes"              => ["Taxes", "TAXES"],
            "Outbound"           => ["Outbound", "OUTBOUND"],
            "Inbound"            => ["Inbound", "INBOUND"],
            "Stopover duration"  => ["Stopover duration", "STOPOVER DURATION"],
        ],
        "it" => [
            "Your booking number"=> "PNR",
            "Total"              => ["Totale Pagato", "TOTALE PAGATO"],
            "Adult Fare"         => ["Tariffa Adulti", "TARIFFA ADULTI"],
            //"Child Fare"       => "",
            "Additional charges" => "Supplementi",
            "Taxes"              => ["Tasse", "TASSE"],
            "Outbound"           => ["andata", "ANDATA"],
            "Inbound"            => ["ritorno", "RITORNO"],
            "Stopover duration"  => "NOTTRANSLATED",
            "Ticket No.:"        => "N. biglietto:",
            "Seat"               => "Posto",
        ],
        "es" => [
            "Your booking number"=> "PNR",
            "Total"              => ["Total pagado", "TOTAL PAGADO"],
            "Adult Fare"         => ["Tarifa de adulto", "TARIFA DE ADULTO"],
            //"Child Fare"       => "",
            "Additional charges" => "Cargos adicionales",
            "Taxes"              => ["Tasas", "TASAS"],
            "Outbound"           => ["Ida", "IDA"],
            "Inbound"            => ["Vuelta", "VUELTA"],
            "Stopover duration"  => ["Duración de la escala", "DURACIÓN DE LA ESCALA"],
            "Ticket No.:"        => "Núm. de billete:",
            //			"Seat" => "",
        ],
        "bg" => [
            "Your booking number"=> "PNR",
            "Total"              => ["Обща заплатена сума", "ОБЩА ЗАПЛАТЕНА СУМА"],
            "Adult Fare"         => ["Тарифа за възрастни", "ТАРИФА ЗА ВЪЗРАСТНИ"],
            //"Child Fare"       => "",
            //"Additional charges" => "",
            "Taxes"              => ["Данъци", "ДАНЪЦИ"],
            "Outbound"           => ["Полет на отиване", "ПОЛЕТ НА ОТИВАНЕ"],
            "Inbound"            => ["Полет на връщане", "ПОЛЕТ НА ВРЪЩАНЕ"],
            //			"Stopover duration"=>[""],
            "Ticket No.:" => "Номер на билет:",
            //			"Seat" => "",
        ],
        "de" => [
            "Your booking number"=> "PNR",
            "Total"              => ["Gezahlter Gesamtbetrag", "GEZAHLTER GESAMTBETRAG"],
            "Adult Fare"         => ["Erwachsenentarif", "ERWACHSENENTARIF"],
            //"Child Fare"       => "",
            "Additional charges" => "Zusätzliche Gebühren",
            "Taxes"              => ["Steuern", "STEUERN"],
            "Outbound"           => ["Direkt", "DIREKT", "Hinflug", "HINFLUG"],
            "Inbound"            => ["Rückflug", "RÜCKFLUG"],
            "Stopover duration"  => ["Duración de la escala", "DURACIÓN DE LA ESCALA", "Dauer Zwischenstopp", "DAUER ZWISCHENSTOPP"],
            "Ticket No.:"        => "Ticket-Nr.:",
            //			"Seat" => "",
        ],
        "fr" => [
            "Your booking number"=> "PNR",
            "Total"              => ["Montant payé", "MONTANT PAYÉ"],
            "Adult Fare"         => ["Tarif adulte", "TARIF ADULTE"],
            //"Child Fare"       => "",
            "Additional charges" => "Frais supplémentaires",
            "Taxes"              => ["Taxes", "TAXES"],
            "Outbound"           => ["Aller", "ALLER"],
            "Inbound"            => ["Retour", "RETOUR"],
            "Stopover duration"  => ["Durée de l'escale", "DURÉE DE L'ESCALE"],
            "Ticket No.:"        => "Billet n°:",
            //			"Seat" => "",
        ],
        "he" => [
            "Your booking number"=> "הוא",
            "Total"              => "הסכום ששולם",
            "Adult Fare"         => "מחיר מבוגר",
            //"Child Fare"       => "",
            //"Additional charges" => "",
            "Taxes"              => "מסים",
            "Outbound"           => "טיסה יוצאת",
            "Inbound"            => "טיסה נכנסת",
            "Stopover duration"  => "משך חניית הביניים",
            //			"Ticket No.:" => "",
            //			"Seat" => "",
        ],
        "nl" => [
            "Your booking number"=> "PNR",
            "Total"              => ["In totaal betaald", "IN TOTAAL BETAALD"],
            "Adult Fare"         => ["Tarief voor volwassenen", "TARIEF VOOR VOLWASSENEN"],
            //"Child Fare"       => "",
            "Additional charges" => "Extra toeslagen",
            "Taxes"              => ["Heffingen", "HEFFINGEN"],
            "Outbound"           => ["Uitgaand", "UITGAAND"],
            "Inbound"            => ["Inkomend", "INKOMEND"],
            "Stopover duration"  => ["Duur tussenstop", "DUUR TUSSENSTOP"],
            "Ticket No.:"        => "Ticketnummer:",
            //			"Seat" => "",
        ],
        "pt" => [
            "Your booking number"=> "PNR",
            "Total"              => ["Total pago", "TOTAL PAGO"],
            "Adult Fare"         => ["Tarifa para adulto", "TARIFA PARA ADULTO"],
            //"Child Fare"       => "",
            "Additional charges" => "Taxas adicionais",
            "Taxes"              => ["Impostos", "IMPOSTOS"],
            "Outbound"           => ["Ida", "IDA"],
            "Inbound"            => ["Volta", "VOLTA"],
            "Stopover duration"  => ["Duração da escala", "DURAÇÃO DA ESCALA"],
            "Ticket No.:"        => "Nº do bilhete:",
            //			"Seat" => "",
        ],
        "el" => [
            "Your booking number"=> "PNR",
            "Total"              => ["Συνολικό καταβληθέν ποσό", "ΣΥΝΟΛΙΚΌ ΚΑΤΑΒΛΗΘΈΝ ΠΟΣΌ"],
            "Adult Fare"         => ["Ναύλος ενηλίκου", "ΝΑΎΛΟΣ ΕΝΗΛΊΚΟΥ"],
            //"Child Fare"       => "",
            "Additional charges" => "Πρόσθετες χρεώσεις",
            "Taxes"              => ["Φόροι", "ΦΌΡΟΙ"],
            "Outbound"           => ["Επιστροφής", "ΕΠΙΣΤΡΟΦΉΣ"],
            "Inbound"            => ["Αναχώρηση", "ΑΝΑΧΏΡΗΣΗ"],
            "Stopover duration"  => ["Διάρκεια ενδιάμεσης στάσης", "ΔΙΆΡΚΕΙΑ ΕΝΔΙΆΜΕΣΗΣ ΣΤΆΣΗΣ"],
            "Ticket No.:"        => "Αριθμός εισιτηρίου:",
            //			"Seat" => "",
        ],
        "pl" => [
            "Your booking number" => "PNR",
            "Total"               => ["Zapłacono razem", "ZAPŁACONO RAZEM"],
            "Adult Fare"          => ["Taryfa dla dorosłych", "TARYFA DLA DOROSŁYCH"],
            //"Child Fare"       => "",
            "Additional charges"  => "Dodatkowe opłaty",
            "Taxes"               => ["Podatki", "PODATKI"],
            "Outbound"            => ["Lot wychodzący", "LOT WYCHODZĄCY"],
            "Inbound"             => ["Lot przechodzący", "LOT PRZYCHODZĄCY"],
            "Stopover duration"   => ["Długość trwania przesiadki", "DŁUGOŚĆ TRWANIA PRZESIADKI"],
            "Ticket No.:"         => "Numer biletu:",
            //			"Seat" => "",
        ],
    ];

    public $lang = "en";

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // RecordLocator
        $confirmation = $this->getField($this->t("Your booking number"));

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        } else {
            $f->general()
                ->noConfirmation();
        }

        // Passengers
        // TicketNumbers
        // AccountNumbers
        $it['TicketNumbers'] = [];
        $it['Passengers'] = [];
        $it['AccountNumbers'] = [];
        $passInfoXPath = "//text()[" . $this->contains($this->t("Ticket No.:")) . "]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($passInfoXPath);

        foreach ($nodes as $root) {
            $info = implode("\n", $this->http->FindNodes(".//text()", $root));
            if (preg_match("#\s*(?<name>.+)\n+(?<AN>[\w\s\.]+\n)?\d{1,2}\s+[^\d\s]+[\s\S]*" . $this->t("Ticket No.:") . "\s*(?<ticket>[\d ]+)#u", $info, $m)) {
                $it['Passengers'][] = trim($m['name']);
                $it['AccountNumbers'][] = preg_replace('/\b(?:NULL|programma\.loyalty\.tier\.0)\b\s*/i', '', trim($m['AN']));
                $it['TicketNumbers'] = array_merge($it['TicketNumbers'], preg_split("#\s+#", $m['ticket']));
            }
        }

        $f->general()
            ->travellers(array_unique(array_filter($it['Passengers'])), true);

        $f->setTicketNumbers(array_unique(array_filter($it['TicketNumbers'])), false);
        $f->setAccountNumbers(array_unique(array_filter($it['AccountNumbers'])), false);

        $adultFare = $this->amount($this->getField($this->t("Adult Fare")));
        $childFare = $this->amount($this->getField($this->t("Child Fare")));

        if (!empty($childFare)) {
            $f->price()
                ->cost($adultFare + $childFare);
        } else {
            $f->price()
                ->cost($adultFare);
        }

        $f->price()
            ->total($this->amount($this->http->FindSingleNode("//text()[{$this->contains($this->t('Total'))}]/ancestor::td[1]", null, true, "#{$this->opt($this->t("Total"))}\s*(.+)#")));

        $f->price()
            ->currency($this->currency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Total'))}]/following::text()[normalize-space(.)!=''][1]")));

        $f->price()
            ->tax($this->amount($this->getField($this->t("Taxes"))));

        $nodesFees = $this->http->XPath->query("//text()[{$this->eq($this->t('Additional charges'))}]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[normalize-space()]");

        if ($nodesFees->length > 0) {
            foreach ($nodesFees as $rootFee) {
                $feeName = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $rootFee);
                $feeSum = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $rootFee, true, "/\s([\d\.\,]+)$/");
                $feeSum = preg_replace("/^(\d+)\,(\d+)$/", "$1.$2", $feeSum);
                if (!empty($feeName) && !empty($feeSum)) {
                    // 3Seats 	USD 37.800003
                    $f->price()
                        ->fee($feeName, round($feeSum, 5));
                }
            }
        }

        $xpath = "//text()[" . $this->contains($this->t("Outbound")) . " or " . $this->contains($this->t("Inbound")) . "]/ancestor::tr[1]/following-sibling::tr[2]//tr[./td[5]]"
            . " | //text()[" . $this->contains($this->t("Stopover duration")) . "]/ancestor::table[1]/following::table[1]//tr[./td[5]]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("segments root not found: $xpath");
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            $flight = $this->http->FindSingleNode('./td[1]', $root);

            if (preg_match('/\d{4}[^\w\d]+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)/', $flight, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date(strtotime($this->http->FindSingleNode("./td[3]", $root, true, "#\d+[:.]\d+(?:\s+[AP]M\b)?#i"), $date));

            $s->arrival()
                ->code($this->http->FindSingleNode("./td[5]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date(strtotime($this->http->FindSingleNode("./td[5]", $root, true, "#\d+[:.]\d+(?:\s+[AP]M\b)?#i"), $date));

            $s->extra()
                ->cabin($this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]", $root, true, "#\w+#"));

            $operator = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]", $root, true, "#-.*:(.+)#");

            if ($operator) {
                $s->airline()
                    ->operator(trim($operator));
            }

            $seatTexts = $this->http->FindNodes("./ancestor::table[2]//img[contains(@src, 'icon-seat.png')]/ancestor::td[1]/following-sibling::td[1]", $root, "#(\d{1,3}[A-Z])#");
            $seatValues = array_values(array_filter($seatTexts));

            if (empty($seatValues[0])) {
                $seatTexts = $this->http->FindNodes("./ancestor::table[2]//text()[{$this->eq($this->t('Seat'))}]/ancestor::td[1]", $root, "#(\d{1,3}[A-Z])#");
                $seatValues = array_values(array_filter($seatTexts));
            }

            if (!empty($seatValues[0])) {
                $s->setSeats($seatValues);
            }
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'], $headers['subject'])) {
            return false;
        }
        if (   stripos($headers['from'], 'confirmation@alitalia.com') === false
            && stripos($headers['from'], 'confirmation@ita-airways.com') === false
        ) {
            return false;
        }
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody[0]) === false && strpos($body, $this->reBody[1]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (
                strpos($body, $re) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (
                strpos(html_entity_decode($this->http->Response["body"]), $re) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'itaspa.com')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'ITA SPA')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), '@ita-airways.com')]")->length > 0
            || strpos(implode('', $parser->getFrom()), 'confirmation@ita-airways.com') !== false
        ) {
            $email->setProviderCode('itaairways');
        }

        $this->ParseFlight($email);

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

    public static function getEmailProviders()
    {
        return ['alitalia', 'itaairways'];
    }

    protected function amount($cost)
    {
        $cost = $this->re("#([\d\,\. ]+)#", $cost);

        if (empty($cost)) {
            return 0.0;
        }
        $cost = preg_replace('/\s+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("(//text()[{$this->contains($field)}]/following::text()[string-length(normalize-space(.))>1][1])[1]");
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
        //		 $this->http->log($str);
        $em = chr(226) . chr(128) . chr(140) . chr(32);
        //		$year = date("Y", $this->date);
        $in = [
            "#^[{$em}]*(\d{1,2})[{$em}]*([^\d]{3,}?)\.?[{$em}]*(\d{4})[{$em}]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+[{$em}]*$#u", //05 Jul 2017 AZ107
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate(trim($m[1]), $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //		 $this->http->log($str);
        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
