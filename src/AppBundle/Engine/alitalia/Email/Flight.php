<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Flight extends \TAccountCheckerExtended
{
    public $mailFiles = "alitalia/it-12009747.eml, alitalia/it-12012444.eml, alitalia/it-1702372.eml, alitalia/it-1837826.eml, alitalia/it-1837826.eml, alitalia/it-2032414.eml, alitalia/it-2032414.eml, alitalia/it-2453804.eml, alitalia/it-2453804.eml, alitalia/it-2963063.eml, alitalia/it-2963063.eml, alitalia/it-2963364.eml, alitalia/it-2963364.eml, alitalia/it-2997269.eml, alitalia/it-2997269.eml, alitalia/it-3078410.eml, alitalia/it-3078410.eml, alitalia/it-3117645.eml, alitalia/it-3263366.eml, alitalia/it-3308725.eml, alitalia/it-3315231.eml, alitalia/it-3328760.eml, alitalia/it-3328760.eml, alitalia/it-4069108.eml, alitalia/it-4069108.eml, alitalia/it-4108838.eml, alitalia/it-4181386.eml, alitalia/it-4181386.eml, alitalia/it-4189761.eml, alitalia/it-7502320.eml, alitalia/it-7544522.eml, alitalia/it-7600587.eml, alitalia/it-7681310.eml, alitalia/it-8288070.eml, alitalia/it-8446699.eml, alitalia/it-8500476.eml, alitalia/it-9655164.eml, alitalia/it-9912372.eml, it-1702372.eml";

    public $reBody = 'alitalia.com';
    public $reBody2 = [
        "en" => "YOUR PAYMENT HAS BEEN SUCCESSFUL!",
        "ru" => "ОПЛАТА ВЫПОЛНЕНА УСПЕШНО!",
        "it" => "IL TUO PAGAMENTO È STATO EFFETTUATO CON SUCCESSO!",
        "pt" => "SEU PAGAMENTO FOI BEM-SUCEDIDO!",
        "es" => "EL PAGO SE HA REALIZADO CORRECTAMENTE",
        'nl' => 'UW BETALING IS GESLAAGD',
        'de' => 'IHRE ZAHLUNG WAR ERFOLGREICH',
        'pl' => 'TWOJA PŁATNOŚĆ ZOSTAŁA ZREALIZOWANA',
        'ro' => 'PLATA DVS. A FOST EFECTUATĂ CU SUCCES',
        'el' => 'Η ΠΛΗΡΩΜΗ ΣΑΣ ΗΤΑΝ ΕΠΙΤΥΧΗΣ',
    ];

    public $reFrom = ['confirmation@alitalia.com'];
    public $reSubject = [
        "en"  => "E-TICKET RECEIPT",
        "ru"  => "E-TICKET RECEIPT",
        "it"  => "Riepilogo dell’acquisto",
        "pt"  => "RECIBO DO E-TICKET",
        "pt1" => "Recibo do Bilhete Eletrônico",
        "es"  => "RECIBO DEL BILLETE ELECTRÓNICO",
    ];

    public static $dictionary = [
        "en" => [
            'Your booking number' => 'Your booking number',
            'Adult'               => ['Adult', 'Child', 'Infant'],
            'Ticket number:'      => "Ticket number:",
            'Fare'                => 'fare',
            'flight_type'         => ['Departure -', 'Outbound -', 'Inbound -'],
        ],
        "ru" => [
            'Your booking number' => 'Ваш код бронирования',
            'Adult'               => 'Взрослый',
            'TOTAL'               => 'ИТОГО',
            'FARE'                => 'ТАРИФ',
            'DETAILS'             => 'NOTTRANSLATED',
            'Fare'                => 'Тариф',
            'Taxes'               => 'Налоги',
            'flight_type'         => 'Вылетающий рейс',
            'Ticket number:'      => "Номер билета:",
        ],
        "it" => [
            'Your booking number' => 'Il tuo codice di prenotazione',
            'Adult'               => 'Adulto',
            'TOTAL'               => 'TOTALE',
            'FARE'                => 'NOTTRANSLATED',
            'DETAILS'             => 'NOTTRANSLATED',
            'Fare'                => 'Tariffa',
            'Taxes'               => 'Tasse',
            'flight_type'         => ['Andata', 'Ritorno'],
            'Transit'             => 'Transito',
            'Ticket number:'      => "Numero biglietto:",
            'MilleMiglia number:' => 'Codice Millemiglia:',
        ],
        "pt" => [
            'Your booking number' => 'mero de reserva (PNR)',
            'Adult'               => 'NOTTRANSLATED',
            'TOTAL'               => 'TOTAL',
            'FARE'                => 'NOTTRANSLATED',
            'DETAILS'             => 'NOTTRANSLATED',
            'Fare'                => 'Tarifa',
            'Taxes'               => 'Impostos',
            'flight_type'         => 'Ida',
            'Ticket number:'      => "Número do bilhete:",
        ],
        "es" => [
            'Your booking number' => 'mero de la reserva (PNR) es',
            'Adult'               => 'Adulto',
            'TOTAL'               => 'TOTAL',
            'FARE'                => 'NOTTRANSLATED',
            'DETAILS'             => 'NOTTRANSLATED',
            'Fare'                => 'Tarifa',
            'Taxes'               => 'Tasas',
            'flight_type'         => 'Salida',
            'Ticket number:'      => "Número de billete: ",
        ],
        'nl' => [
            'Your booking number' => ['mero de la reserva (PNR) es', 'Uw boekingsnummer (PNR) is'],
            'Adult'               => 'Volwassene',
            //			'FARE DETAILS' => '',
            'TOTAL'          => 'TOTAAL',
            'Taxes'          => 'Heffingen',
            'Fare'           => 'Tarief',
            'flight_type'    => 'Heenreis -',
            'Ticket number:' => 'Ticketnummer:',
            'operated by'    => 'uitgevoerd door',
            //			'MilleMiglia number:' => '',
        ],
        'de' => [
            'Your booking number' => 'Ihre Buchungsnummer (PNR) lautet',
            'Adult'               => 'Erwachsener',
            //			'Child' => '',
            //			'Infant' => '',
            //			'FARE DETAILS' => '',
            'TOTAL'               => 'GESAMTBETRAG',
            'Taxes'               => 'Steuern',
            'Fare'                => 'Tarif',
            'flight_type'         => ['Hinflug -', 'Rückflug -'],
            'Ticket number:'      => 'Ticketnummer:',
            'operated by'         => 'Flug durchgeführt von',
            'MilleMiglia number:' => 'MilleMiglia-Nummer:',
        ],
        'pl' => [
            'Your booking number' => 'Numer twojej rezerwacji (PNR) to',
            'Adult'               => 'Dorosły',
            //			'Child' => '',
            //			'Infant' => '',
            //			'FARE DETAILS' => '',
            'TOTAL'          => 'RAZEM',
            'Taxes'          => 'Podatki',
            'Fare'           => 'Taryfa',
            'flight_type'    => ['Wylot -', 'Powrót -'],
            'Ticket number:' => 'Numer biletu:',
            'operated by'    => 'NOTTRANSLATED',
            //			'MilleMiglia number:' => '',
        ],
        'ro' => [
            'Your booking number' => 'Numărul rezervării dvs. (PNR) este',
            'Taxes'               => 'Taxe',
            'Fare'                => 'Tarif',
            'flight_type'         => ['Zbor dus -', 'Zbor de întoarcere -'],
            'Ticket number:'      => 'Numărul biletului:',
            //			'operated by' => '',
            'MilleMiglia number:' => 'Cod MilleMiglia:',
        ],
        'el' => [
            'Your booking number' => 'Ο αριθμός κράτησής (PNR) σας είναι',
            'Adult'               => 'Ενήλικος',
            //			'Child' => '',
            //			'Infant' => '',
            //			'FARE DETAILS' => '',
            'TOTAL'          => 'ΣΥΝΟΛΟ',
            'Taxes'          => 'Φόροι',
            'Fare'           => 'Ναύλος',
            'flight_type'    => ['Αναχώρηση -', 'Επιστροφή -'],
            'Ticket number:' => 'Αριθμός εισιτηρίου:',
            //			'operated by' => '',
            //			'MilleMiglia number:' => '',
        ],
    ];
    private $lang = "";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $is = false;

        foreach ($this->reSubject as $rule) {
            if (isset($headers['subject']) && strpos($headers['subject'], $rule) !== false) {
                $is = true;

                break;
            }
        }

        if ($is) {
            foreach ($this->reFrom as $rule) {
                if (isset($headers['from']) && strpos($headers['from'], $rule) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if (!$this->assignLang()) {
            $this->logger->debug('Can\'t determine a language');

            return $email;
        }
        $email->setType('Flight' . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t("Your booking number"))}]/ancestor::tr[1]",
                null, false, '/[A-Z\d]{5,6}/'));
        $pax = $this->http->FindNodes("//*[{$this->eq($this->t("Adult"))}]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]");

        if (empty($pax)) {
            $pax = $this->http->FindNodes("//*[{$this->eq($this->t("Adult"))}]/ancestor::tr[1]/preceding-sibling::tr[1]/td[1]",
                null, "#^\d{1,2}\s*(.+)$#s");
        }

        if (empty($pax)) {
            $pax = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket number:'))}]/ancestor::tr[1]/preceding-sibling::tr[last()]",
                null, "#^\d{1,2}\s*(.+)$#s");
        }

        if (!empty($pax)) {
            $r->general()
                ->travellers($pax);
        }

        $r->issued()
            ->tickets($this->http->FindNodes("//text()[{$this->starts($this->t('Ticket number:'))}]/ancestor::tr[1]",
                null, "#:\s+(.+)#"), false);

        $accountNumberTexts = $this->http->FindNodes("//text()[{$this->starts($this->t("MilleMiglia number:")) }]",
            null, '/:\s+(.+)/');
        $accountNumberValues = array_values(array_filter($accountNumberTexts));

        if (!empty($accountNumberValues[0])) {
            $r->program()->accounts($accountNumberValues, false);
        }

        $total = $this->getTotalCurrency($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("TOTAL") . "']/ancestor::tr[1]/following-sibling::tr[1]"));

        if (!empty($total['Total'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        if (!$baseFare = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t("FARE") . "') and contains(text(),'" . $this->t("DETAILS") . "')]/ancestor::table[1]/following-sibling::table[2]//*[contains(text(), '" . $this->t("Fare") . "')]/ancestor::tr[1]/td[3]")) {
            $baseFare = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Fare'))}]/ancestor::tr[1]/td[3]");
        }
        $baseFare = $this->getTotalCurrency($baseFare);

        if (!empty($baseFare['Total'])) {
            $r->price()
                ->cost($baseFare['Total']);
        }

        $xpath = "//*[normalize-space(text())='" . $this->t("Taxes") . "']/ancestor::tr[1]/following-sibling::tr";
        $feesNodes = $this->http->XPath->query($xpath);

        foreach ($feesNodes as $root) {
            $name = $this->http->FindSingleNode("./td[1]", $root);
            $amount = $this->getTotalCurrency($this->http->FindSingleNode("./td[3]", $root));

            if (!empty($name) && !empty($amount['Total'])) {
                $r->price()
                    ->fee($name, $amount['Total']);
            }
        }

        $tax = $this->getTotalCurrency($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Taxes") . "']/ancestor-or-self::td[1]/following-sibling::td[2]"));

        if (!empty($tax['Total'])) {
            $r->price()
                ->tax($tax['Total']);
        }

        $xpath = "//*[{$this->contains($this->t('flight_type'))}]/ancestor::tr[1]/following-sibling::tr[./following-sibling::tr[3]//img[contains(@src, 'seat.jpg')]]|
						//*[{$this->contains($this->t('flight_type'))}]/ancestor::tr[1]/following-sibling::tr//tr[2][.//img[contains(@src, 'place.png')]]/preceding-sibling::tr[1]|
						//img[contains(@src, 'fly_icon_medium.png')]/ancestor::tr[1][string-length(normalize-space(.))>5]";
        $nodes = $this->http->XPath->query($xpath);
        $finded = true;

        foreach ($nodes as $key => $value) {
            if (strlen($value->nodeValue) > 5) {
                $finded = false;
            }
        }

        if ($finded || $nodes->length == 0) {
            $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
            $xpath = "//*[{$this->contains($this->t('flight_type'))}]/ancestor::tr[1]/following-sibling::tr[count(./td//table)>3][count(.//text()[{$ruleTime}])=2]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $cabin = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::td[1]/descendant::text()[normalize-space()!=''][2]",
                $root);

            if (empty($cabin)) {
                $cabin = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1][count(.//img)=0]/descendant::text()[normalize-space()!=''][1]",
                    $root);
                $operator = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1][count(.//img)=0]",
                    $root, false, "#{$this->t('operated by')}\s*(.+)#");

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }
            }

            if (!empty($cabin)) {
                $s->extra()->cabin($cabin);
            }

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::td[1]/following::text()[normalize-space()!=''][1]",
                $root)));

            $s->departure()
                ->noCode()
                ->date(strtotime($this->normalizeTime($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::td[1]/following::text()[normalize-space()!=''][2]",
                    $root)), $date))
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::td[1]/following::text()[normalize-space()!=''][3]",
                    $root));

            $s->arrival()
                ->noCode()
                ->date(strtotime($this->normalizeTime($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::td[1]/following::text()[normalize-space()!=''][4]",
                    $root)), $date))
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][last()]", $root));

            if ($day = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][last()-1]", $root,
                false, "#^([\+\-]\s*\d+)\s*\w+$#")
            ) {
                $s->arrival()
                    ->date(strtotime($day . ' days', $s->getArrDate()));
            }

            $seats =
                array_filter($this->http->FindNodes("./following-sibling::tr[normalize-space()!=''][position()<5][count(.//img)>0][1]//text()[normalize-space()!='']",
                    $root, "#^\s*\d{1,3}[A-Z]\s*$#"));

            if (count($seats) > 0) {
                $s->extra()->seats($seats);
            }
        }

        return true;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words['Your booking number'], $words['Ticket number:']) && $this->http->XPath->query("//*[{$this->contains($words['Your booking number'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($words['Ticket number:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
        // $this->http->log($str);
        $in = [
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'he')) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'es')) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'it')) {
                $str = str_replace($m[1], $en, $str);
            }
        }
//         $this->http->log($str);
        return $str;
    }

    private function normalizeTime($str)
    {
        // $this->http->log($str);
        $in = [
            "#^(\d+:\d+) μμ$#u", //8:05 μμ
        ];
        $out = [
            "$1PM",
        ];
        $str = preg_replace($in, $out, $str);
//         $this->http->log($str);
        return $str;
    }
}
