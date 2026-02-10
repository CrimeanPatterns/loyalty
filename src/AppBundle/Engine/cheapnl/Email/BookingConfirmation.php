<?php

namespace AwardWallet\Engine\cheapnl\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "cheapnl/it-12171869.eml, cheapnl/it-12181953.eml, cheapnl/it-12209085.eml, cheapnl/it-12336821.eml, cheapnl/it-12636538.eml, cheapnl/it-12717560.eml, cheapnl/it-13646405.eml, cheapnl/it-2995037.eml, cheapnl/it-2995102.eml, cheapnl/it-31052799.eml, cheapnl/it-3712149.eml, cheapnl/it-4423902.eml, cheapnl/it-4480552.eml, cheapnl/it-4484149.eml, cheapnl/it-4487285.eml, cheapnl/it-4528097.eml, cheapnl/it-4553369.eml, cheapnl/it-4553371.eml, cheapnl/it-4566026.eml, cheapnl/it-4618101.eml, cheapnl/it-4687278.eml, cheapnl/it-4711233.eml, cheapnl/it-4714210.eml, cheapnl/it-4814307.eml, cheapnl/it-5106802.eml, cheapnl/it-5106809.eml, cheapnl/it-5106814.eml, cheapnl/it-5126115.eml, cheapnl/it-5225206.eml, cheapnl/it-5262445.eml, cheapnl/it-5739802.eml, cheapnl/it-5869310.eml, cheapnl/it-5872531.eml, cheapnl/it-5883424.eml, cheapnl/it-5943471.eml, cheapnl/it-5947777.eml, cheapnl/it-6040539.eml, cheapnl/it-6052426.eml, cheapnl/it-6053097.eml, cheapnl/it-6066231.eml, cheapnl/it-6262111.eml, cheapnl/it-6940249.eml, cheapnl/it-7483896.eml, cheapnl/it-8159844.eml, cheapnl/it-8183050.eml, cheapnl/it-8222526.eml";

    private $froms = [
        'budgetair' => ['budgetair.'],
        'vayama'    => ['vayama.'],
        'flugladen' => ['flugladen.'],
        'cheapnl'   => ['CheapTickets.'],
    ];

    private $reSubject = [
        "en" => [
            " - Booking confirmation",
            "Your flight schedule has changed",
            "order safely and securely", //Pay your %company% order safely and securely
        ],
        "de" => [
            " - Bestätigung Ihrer Reservierung",
            " - Bestätigung",
        ],
        "nl" => [
            " - Boekingsbevestiging ",
            "Belangrijk! Wijziging van je boeking",
        ],
        "fr" => [
            " - Confirmation de votre réservation",
        ],
        "pt" => [
            " - Confirmação de reserva",
            "A hora prevista do seu voo foi alterada",
        ],
        "pl" => [
            "- Potwierdzenie rezerwacji",
        ],
        "es" => [
            " - Confirmación de la reserva",
        ],
        "it" => [
            " - Conferma Prenotazione",
        ],
    ];

    private $reBody = [
        'budgetair' => ['Budgetair', 'BudgetAir'],
        'vayama'    => ['Vayama'],
        'flugladen' => ['Flugladen'],
        'cheapnl'   => ['CheapTickets'],
    ];

    private $reBody2 = [
        "en" => ['Your flight', 'Your new flight'],
        "de" => ["Fluginformationen", "Ihr neuer Flug"],
        "fr" => ["Votre vol", 'Merci pour votre réservation'],
        "nl" => ["Jouw", "Jouw nieuwe vlucht"],
        "pt" => ["O seu voo", 'O seu novo voo'],
        "pl" => ["Twój lot", 'Twój nowy lot'],
        "es" => ["Tu vuelo"],
        "it" => ["Il tuo volo"],
    ];

    private static $dictionary = [
        "en" => [
            //			"Dear" => "",
            "Booking number:"    => ["Booking number:", "Booking number"],
            "%company% number:"  => 'number:',
            "Airline reference:" => ["Airline reference:", "Airline number:", "Flight number:"],
            //			"Booking date" => "",
            //			"Flight number:" => "",
            //			"Passenger" => "",
            //			'incl. taxes ' => '',
            //			"Total" => "",
            'Customer service:' => 'Customer service:',
        ],
        "de" => [
            "Dear"               => "Sehr geehrte/r Frau/Herr",
            "Booking number:"    => ["Buchungsnummer:"],
            "%company% number:"  => 'Nummer:',
            "Airline reference:" => ["Fluggesellschaftsreferenz:", "Reservierungsnummer:"],
            "Booking date"       => "Buchungsdatum",
            "Flight number:"     => "Flugnummer:",
            "Passenger"          => "Reisende(r)",
            'incl. taxes '       => 'incl. Steuern ',
            "Total"              => ["Insgesamt", "Totaal"],
            'Customer service:'  => 'Service-Center:',
        ],
        "nl" => [
            "Dear"               => "Beste",
            "Booking number:"    => ["Boekingsnummer:"],
            "%company% number:"  => 'nummer:',
            "Airline reference:" => ["Vluchtnummer:", "Airline referentie:", "Airline nummer:"],
            "Booking date"       => "Boekingsdatum",
            "Flight number:"     => ["Vluchtnummer:", "vluchtnummer:", "Airline nummer:", "Airline referentie:"],
            "Passenger"          => "Passagier",
            "Total"              => "Totaal",
            'incl. taxes '       => 'incl. belastingen ',
            'Customer service:'  => 'Klantenservice:',
        ],
        "fr" => [
            "Dear"               => "Cher madame, monsieur",
            "Booking number:"    => ["Numéro de réservation:"],
            "%company% number:"  => 'Référence',
            "Airline reference:" => ["Référence de vol:", 'Référence de la compagnie aérienne:'],
            "Booking date"       => "Booking date",
            "Flight number:"     => "Numéro de vol:",
            "Passenger"          => "Voyageur",
            'incl. taxes '       => 'incl. taxe ',
            "Total"              => "Total",
            'Customer service:'  => 'Le service clientèle:',
        ],
        "pt" => [
            "Dear"               => "Caro(a)",
            "Booking number:"    => ["Número da reserva:"],
            "%company% number:"  => ["Número"],
            "Airline reference:" => ['Número da companhia aérea:', 'Número do vôo:'],
            "Booking date"       => "Booking date",
            "Flight number:"     => ["Número do voo:", "Número do vôo:"],
            "Passenger"          => "Passageiro",
            'incl. taxes '       => 'incl. impostos ',
            "Total"              => "Total",
            'Customer service:'  => 'Atendimento ao Cliente:',
        ],
        "pl" => [
            "Dear"               => "Szanowny(-a) Panie(Pani)",
            "Booking number:"    => ["Numer rezerwacji:"],
            "%company% number:"  => ["numer:"],
            "Airline reference:" => ['Numer linii lotniczej:', 'numer lotu:'],
            "Booking date"       => "Booking date",
            "Flight number:"     => ["numer lotu:"],
            "Passenger"          => "Pasażer",
            'incl. taxes '       => 'zaw. podatki ',
            "Total"              => "Razem",
            'Customer service:'  => 'Obsługa klienta:',
        ],
        "es" => [
            "Dear" => "Estimado/a",
            //			"Booking number:" => [],
            "%company% number:"  => ['Número de'],
            "Airline reference:" => ['Número de aerolínea:', 'Numero de vuelo:', 'Referencia de aerolínea:', 'Referencia de la aerolínea:'],
            "Booking date"       => "Booking date",
            "Flight number:"     => ['Numero de vuelo:', 'Número de vuelo:'],
            "Passenger"          => "Pasajero",
            'incl. taxes '       => 'incl. tasas de aeropuerto ',
            "Total"              => "Total",
            'Customer service:'  => 'Atención al cliente:',
        ],
        "it" => [
            "Dear"            => "Gentile",
            "Booking number:" => ['Numero di prenotazione:'],
            //			"%company% number:" => [],
            "Airline reference:" => ['Codice compagnia aerea:', 'Codice vettore'],
            "Booking date"       => "Booking date",
            "Flight number:"     => ['Numero di volo:'],
            "Passenger"          => "Passeggero",
            'incl. taxes '       => 'incl. tasse ',
            "Total"              => "Totale",
            'Customer service:'  => 'Servizio clienti:',
        ],
    ];

    private $lang = "en";
    private $date = '';
    private $codeProvider = '';
    private $pdf = '';

    public static function getEmailProviders()
    {
        return ['budgetair', 'vayama', 'flugladen', 'cheapnl'];
    }

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*.pdf");

        if (isset($pdfs[0])) {
            $this->pdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
        }

        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);

        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->codeProvider)) {
            $codeProvider = $this->codeProvider;
        } else {
            $this->codeProvider = $codeProvider = $this->getProvider($body);
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        } else {
            $email->ota();
        }

        $tripNumber = $this->nextText($this->t("Booking number:"), null, "#^\s*([A-Z\d\-]+-[A-Z\d\-]+)\s*$#");

        if (empty($tripNumber)) {
            if (!empty($this->codeProvider)) {
                $tripNumber = $this->http->FindSingleNode("(//text()[(" . $this->contains($this->t("%company% number:")) . ") and (" . $this->contains($this->reBody[$this->codeProvider]) . ")]/following::text()[normalize-space(.)][1])[1]", null, true, "#^\s*([A-Z\d\-]{5,})\s*$#");

                if (empty($tripNumber)) {
                    $tripNumber = $this->http->FindSingleNode("(//td[not(.//td) and (" . $this->contains($this->t("%company% number:")) . ") and (" . $this->contains($this->reBody[$this->codeProvider]) . ")][1])[1]", null, true, "#:\s*([A-Z\d\-]{5,})\s*$#");
                }
            }
        }

        if (!empty($tripNumber)) {
            $name = trim($this->http->FindSingleNode("(//text()[(" . $this->eq($this->t($tripNumber)) . ")][1]/ancestor::td[1])[1]", null, true, "#^\s*(.{5,40})[\s:]+" . $tripNumber . "$#u"), ' :');
            $email->ota()->confirmation($tripNumber, !empty($name) ? $name : null);
        }

        $phonesText = implode("\n", $this->http->FindNodes("//tr[" . $this->eq($this->t("Customer service:")) . "]/following-sibling::tr[1]//text()"));

        if (empty($phonesText)) {
            $phonesText = implode("\n", $this->http->FindNodes("//table[" . $this->eq($this->t("Customer service:")) . "]/following-sibling::table[1]//text()"));
        }

        if (preg_match_all("#(.*?):?\s*([+.\-\(\) ]*\d+(?:[+.\-\(\) ]*\d+){6,}?)(?:|[ ]+\([^\(\n]+?\)|[ ]+[^\(\)\n]*)[ ]*(?:\n|$)#", $phonesText, $m, PREG_SET_ORDER)) {
            foreach ($m as $value) {
                if (!empty($value[2]) && !empty($value[1]) && !in_array(trim($value[2]), array_column($email->obtainTravelAgency()->getProviderPhones(), 0))) {
                    $email->ota()->phone(trim($value[2]), trim($value[1]));
                }
            }
        }

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->froms as $froms) {
            foreach ($froms as $value) {
                if (stripos($from, $value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    $finded = true;
                }
            }
        }

        if ($finded === false) {
            foreach ($this->froms as $prov => $froms) {
                foreach ($froms as $value) {
                    if (stripos($headers["from"], $value) !== false || stripos($headers["subject"], $value) !== false) {
                        $this->codeProvider = $prov;

                        return false;
                    }
                }
            }

            return false;
        }

        foreach ($this->froms as $prov => $froms) {
            foreach ($froms as $value) {
                if (stripos($headers["from"], $value) !== false || stripos($headers["subject"], $value) !== false) {
                    $this->codeProvider = $prov;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $head = false;

        foreach ($this->reBody as $prov => $froms) {
            foreach ($froms as $from) {
                if ($this->http->XPath->query('//a[contains(@href, "' . $from . '")]')->length > 0 || stripos($body, $from) !== false) {
                    $head = true;

                    break;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
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

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        //General
        $f->general()->confirmation($this->nextText($this->t("Airline reference:")));

        $passengers = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger")) . " and not(ancestor::a)]/following::text()[normalize-space(.)][1]", null, "#^(\D+?)(?:\s+\(|$)#"));

        if (empty($passengers)) {
            $passengers = array_filter([$this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear")) . "]", null, true, "#" . $this->preg_implode($this->t("Dear")) . "\s*(.+)#")]);
        }

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        }

        if ($date = $this->nextText($this->t("Booking date"))) {
            $date = $this->normalizeDate($date);
            $this->date = $date;
            $f->general()->date($date);
        }

        if (preg_match_all("#\n\s*([A-Z ]+)\s*\n\s*E-ticket number\s+(.+)#", $this->pdf, $m)) {
            $f->issued()->tickets($m[2], false);

            if (empty($passengers)) {
                $f->general()->travellers($m[1], true);
            }
        }

        // Price
        $total = $this->nextText($this->t("Total"));

        if (!empty($total)) {
            $currency = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("incl. taxes ")) . "][1])[1]", null, true, "#" . $this->preg_implode($this->t("incl. taxes ")) . "\s*([A-Z]{3})\s*\d+#");

            if (empty($currency)) {
                $currency = $this->currency($total);
            }
            $f->price()
                ->total($this->amount($total))
                ->currency($currency);
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("Flight number:")) . "]/ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[normalize-space()][1][count(./descendant::td)>5] |
				  //text()[" . $this->starts($this->t("Flight number:")) . "]/ancestor::tr[1]/../tr[2][.//td[5]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0 && $this->date === null) {
            $this->date = $this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][1]", $nodes->item(0)));
        }

        foreach ($nodes as $root) {
            if ($date = $this->http->FindSingleNode("./preceding::img[contains(@src, 'all_icons_airplane1.png')][1]/ancestor::tr[1]", $root)) {
                $this->date = $this->normalizeDate($date);
            } elseif ($date = $this->http->FindSingleNode("./preceding::img[contains(@src, 'airplane')][1]/ancestor::tr[1]/descendant::text()[normalize-space()][1]", $root)) {
                $this->date = $this->normalizeDate($date);
            }

            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1][" . $this->contains($this->t("Flight number:")) . "]", $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][1][" . $this->contains($this->t("Flight number:")) . "]", $root);
            }

            if (preg_match("#(?:" . $this->preg_implode($this->t("Flight number:")) . ")\s+([A-Z\d]{2})(\d+)(?:\s+-\s+[\w ]+:\s*(.+))?#u", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3])) {
                    $s->airline()
                        ->operator($m[3]);
                }
            }

            // Departure
            $s->departure()
                ->noCode()
                ->name(implode(", ",
                    $this->http->FindNodes(".//td[normalize-space()!=''][3]/../td[1]/table[2]/descendant::text()[normalize-space(.)!='']",
                        $root)))
                ->date($depDate = $this->normalizeDate(implode(", ",
                    $this->http->FindNodes(".//td[normalize-space()!=''][3]/../td[1]/table[1]/descendant::text()[normalize-space(.)!='']",
                        $root))));

            if (isset($lastDate) && $lastDate > $depDate) {
                $this->date = strtotime("+1 day", $this->date);
                $s->departure()->date($this->normalizeDate(implode(", ",
                    $this->http->FindNodes(".//td[normalize-space()!=''][3]/../td[1]/table[1]/descendant::text()[normalize-space(.)!='']",
                        $root))));
            }
            // Arrival
            $s->arrival()
                ->noCode()
                ->name(implode(", ",
                    $this->http->FindNodes(".//td[normalize-space()!=''][3]/table[2]/descendant::text()[normalize-space(.)!='']",
                        $root)))
                ->date($lastDate = $this->normalizeDate(implode(", ",
                    $this->http->FindNodes(".//td[normalize-space()!=''][3]/table[1]/descendant::text()[normalize-space(.)!='']",
                        $root))));

            // Extra
            $duration = $this->http->FindSingleNode(".//td[normalize-space()!=''][3]/../td[3]", $root, true, "#^\s*\d{1,2}:\d{2}(?::\d{2})?\s*$#");

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode(".//td[normalize-space()!=''][2]", $root, true, "#^\s*\d{1,2}:\d{2}(?::\d{2})?\s*$#");
            }
            $s->extra()
                ->duration($duration);
        }
    }

    private function getProvider($body)
    {
        foreach ($this->froms as $prov => $reFroms) {
            foreach (self::$dictionary as $dict) {
                if (isset($dict['Customer service:'])
                        && $this->http->XPath->query("//text()[" . $this->eq($dict['Customer service:']) . "]/following::*[" . $this->contains($reFroms, '@href') . " or " . $this->contains($reFroms) . "]")->length > 0) {
                    return $prov;
                }
            }
        }

        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return $prov;
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->logger->debug('$instr = '.print_r( $instr,true));
        $in = [
            "#^(\d+)-(\d+)-(\d{4})$#",
            "#^(\d+:\d+),\s+(\d+)-([^\d\s]+)$#",
            "#^(\d+:\d+(?:\s*[ap]m)?),\s+(\d+) (\w+)\.?$#ui", // 18:05, 20 okt; 23:00, 10 févr.;
            "#^(\d+:\d+(?:\s*[ap]m)?),\s+(\w+)\.? (\d{1,2})\s*$#ui", // 04:29 PM, Dec 18
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //Samstag 08 Oktober 2016
            "#^(\d+) ([^\s\d\.]+)\.?$#u", //04 mei; 20 juil.
            '/^[^\d\s]+,\s+([^\d\s\.]+)\s+(\d{1,2}),\s+(\d{4})$/',
        ];
        $out = [
            "$1.$2.$3",
            "$2 $3 %Y%, $1",
            "$2 $3 %Y%, $1",
            "$3 $2 %Y%, $1",
            "$1 $2 $3",
            "$1 $2 %Y%",
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m)) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);

                if (isset($m['week'])) {
                    $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                    return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
                }

                return strtotime($str);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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

        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            'AU$' => 'AUD',
            '$'   => 'USD',
            '£'   => 'GBP',
            '₹'   => 'INR',
            '฿'   => 'THB',
            'zł'  => 'PLN',
            'ريال'=> 'SAR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = preg_replace("#[\d\,\. ]+#", '', $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regex);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
