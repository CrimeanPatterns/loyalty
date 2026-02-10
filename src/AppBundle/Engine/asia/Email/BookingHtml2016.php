<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHtml2016 extends \TAccountChecker
{
    public $mailFiles = "asia/it-24277809.eml, asia/it-24292086.eml, asia/it-26253092.eml, asia/it-26611933.eml, asia/it-26684405.eml, asia/it-35992029.eml, asia/it-364154981.eml, asia/it-366872699.eml, asia/it-50298986.eml, asia/it-5069074.eml, asia/it-51428650.eml, asia/it-5931654.eml, asia/it-6172333.eml, asia/it-6173574.eml, asia/it-6203696.eml, asia/it-8441776.eml";
    public $lang = '';

    public $reFrom = '@cathaypacific.com';
    public $reBody = [
        'en' => ['Booking reference'],
        'it' => ['Riferimento di prenotazione'],
        'fr' => ['Récapitulatif de voyage'],
        'es' => ['Referencia de reserva'],
        'nl' => ['Boekingreferentie'],
        'de' => ['Buchungsnummer'],
        'zh' => ['預訂參考編號'],
        'ja' => ['予約番号'],
    ];
    public $reSubject = [
        'Your confirmed booking for ', // en
        'Your confirmed booking from ', // en
        'La tua prenotazione da', // it
        'Prenotazione confermata da', // it
        'Su reserva confirmada del', //es
        'Votre réservation confirmée entre', //fr
        'Uw bevestigde boeking vanaf', //nl
        'Uw boeking van', //nl
        'Ihre bestätigte Buchung von', //de
        'Ihre Buchung für', //de
        '您已確認由', //zh
        '経路の確定済みのご予約', //ja
    ];
    public static $dict = [
        'en' => [
            'Mr'    => ['Mr', 'Miss', 'Mrs', 'Ms', 'Mstr'],
            'Total' => ['Total', 'Amount due'],
            //			'TotalExt' => '',
            'Fare'           => ['Fare', 'Flight Fare'],
            'Your itinerary' => ['Your itinerary', 'Updated itinerary'],
            // 'Terminal' => '',
            // 'Passenger details' => '',
            // 'Class' => '',
        ],
        'it' => [
            'Class'             => 'Classe',
            'Requested seats'   => 'Posti richiesti',
            'Booking reference' => 'Riferimento di prenotazione',
            'Mr'                => ['Sig', 'Sig.ra'],
            'Total'             => 'Totale',
            'Fare'              => 'Tariffa',
            'TotalExt'          => 'Effettua il pagamento per assicurarti la prenotazione a',
            'Departing'         => 'Partenza',
            'Returning'         => 'Ritorno',
            'Duration'          => 'Durata:',
            "Operated by"       => "Operato da",
            "\d+hr \d+min"      => "\d+h \d+m",
            //			"Miles earned" => "",
            //			"Flight " => "",
            // 'Your itinerary' => '',
            // 'Terminal' => '',
            // 'Passenger details' => '',
        ],
        'fr' => [
            'Class'             => 'Classe',
            'Requested seats'   => 'Sièges demandés',
            'Booking reference' => 'Référence de réservation',
            'Mr'                => ['Mlle', 'M.', 'Me'],
            'Total'             => 'Total',
            'Fare'              => 'Tarif',
            //			'TotalExt' => '',
            'Departing'   => 'Départ',
            'Returning'   => 'Retour',
            'Duration'    => 'Durée',
            "Operated by" => "Opéré par",
            "\d+hr \d+min"=> "\d+ h \d+ m",
            //			"Miles earned" => "",
            //			"Flight " => "",
            // 'Your itinerary' => '',
            // 'Terminal' => '',
            // 'Passenger details' => '',
        ],
        'es' => [
            'Class'             => 'Clase',
            'Requested seats'   => 'Asientos solicitados',
            'Booking reference' => 'Referencia de reserva',
            'Mr'                => ['Srta.', 'Sra.', 'Sr.'],
            'Total'             => 'Total',
            //			'Fare' => '',
            //			'TotalExt' => '',
            'Departing'   => 'Salida',
            'Returning'   => 'Regreso',
            'Duration'    => 'Duración',
            "Operated by" => "Operado por",
            "\d+hr \d+min"=> "\d+ *h \d+ *m",
            //			"Miles earned" => "",
            //			"Flight " => "",
            'Your itinerary' => 'Su itinerario',
            // 'Terminal' => '',
            'Passenger details' => 'Información del pasajero',
            'Class'             => 'Clase',
        ],
        'nl' => [
            'Class'             => 'Reisklasse',
            'Requested seats'   => 'Aangevraagde stoelen',
            'Booking reference' => 'Boekingreferentie',
            'Mr'                => ['Heer', 'Mevr'],
            'Total'             => 'Totaal',
            'Fare'              => 'Vluchttarief',
            //			'TotalExt' => '',
            'Departing'   => 'Heenvlucht',
            'Returning'   => 'Terugvlucht',
            'Duration'    => 'Duur',
            "Operated by" => "Uitgevoerd door",
            "\d+hr \d+min"=> "\d+ *h \d+ *m",
            //			"Miles earned" => "",
            //			"Flight " => "",
            // 'Your itinerary' => '',
            // 'Terminal' => '',
            // 'Passenger details' => '',
        ],
        'de' => [
            'Class'             => 'Reiseklasse',
            'Booking reference' => 'Buchungsnummer',
            'Requested seats'   => 'Angeforderte Sitzplätze',
            'Mr'                => ['Herr', 'Frau'],
            'Total'             => 'Gesamt',
            'Fare'              => 'Tarif',
            'TotalExt'          => 'Wickeln Sie Ihre Zahlung ab, um Ihre Buchung zu sichern',
            'Departing'         => 'Hinflug',
            'Returning'         => 'Rückflug',
            'Duration'          => 'Dauer',
            "Operated by"       => "Durchgeführt von",
            "\d+hr \d+min"      => "\d+ *Std\. \d+ *Min\.", // 15 Std. 10 Min.
            //			"Miles earned" => "",
            "Flight "        => "Flug ",
            'Your itinerary' => 'Ihr Reiseverlauf',
            // 'Terminal' => '',
            'Passenger details' => 'Fluggastdaten',
            'Class'             => 'Reiseklasse',
        ],
        'zh' => [
            'Class'                 => '客位級別',
            'Booking reference'     => '預訂參考編號',
            'Requested seats'       => '已要求座位',
            'Mr'                    => ['先生', '太太', '女士'],
            'Total'                 => ['總付款額:', '總計'],
            'Fare'                  => '票價',
            'Subtotal'              => '小計',
            //			'TotalExt' => '',
            'Departing'   => '出發',
            'Returning'   => '回程',
            'Duration'    => '航行時間',
            "Operated by" => "營運航空公司：",
            "\d+hr \d+min"=> "\d+ *h \d+ *m",
            //			"Miles earned" => "",
            //			"Flight " => "",
            'Your itinerary'                 => '你的行程',
            'Terminal'                       => '客運大樓',
            'Passenger details'              => '旅客詳情',
            'Miles Plus Cash payment option' => '「里數加現金」付款方式',
        ],
        'ja' => [
            'Class'             => 'クラス',
            'Booking reference' => '予約番号',
            'Requested seats'   => 'ご希望の座席',
            'Mr'                => ['Mr', 'Miss', 'Mrs', 'Ms', 'Mstr'],
            'Total'             => '合計',
            'Fare'              => '運賃',
            //			'TotalExt' => '',
            //			'Departing' => '',
            //			'Returning' => '',
            'Duration'    => '所要時間',
            "Operated by" => "運航航空会社",
            "\d+hr \d+min"=> "\d+ *h \d+ *m", // 17h 35m
            //			"Miles earned" => "",
            "Flight " => "フライト ",
            // 'Your itinerary' => '',
            // 'Terminal' => '',
            // 'Passenger details' => '',
        ],
    ];

    protected $result = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang($this->http->Response['body']);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $f = $email->add()->flight();

        $bookingReference = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference'))}]/ancestor::tr[1]/following-sibling::tr[1]", null, true, '/^[A-Z\d]{5,6}$/');

        if (!$bookingReference) {
            $bookingReference = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]/following::text()[normalize-space()][1]", null, true, '/^[:\s]*([A-Z\d]{5,6})$/');
        }

        if (empty($bookingReference)) {
            $bookingReference = $this->re("/{$this->opt($this->t('Booking reference'))}[:\s]*([A-Z\d]{5,6})$/", $parser->getSubject());
        }
        $f->general()
            ->confirmation($bookingReference);

        $milesEarned = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Miles earned'))}]/following::text()[normalize-space()][1]");

        if ($milesEarned !== null) {
            $f->setEarnedAwards($milesEarned);
        }

        $accounts = $this->http->FindNodes("//img[contains(@src, 'icon-passenger')]/following::td[3]/descendant::text()[contains(normalize-space(), 'member') or (contains(., '綠卡會員'))]/following::text()[normalize-space()][1]", null, "/^([\d\*]+)$/");

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, true);
        }

        $travellers = $this->http->FindNodes("//strong[{$this->contains($this->t('Mr'))}][string-length()>2]");

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//img[contains(@src, 'icon-passenger')]/following::td[2]/descendant::text()[{$this->contains($this->t('Mr'))}]"));
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger details'))}]/ancestor::table[1]/following::table[1]/descendant::tr", null, "/\d+\s*\w+\s*(\D+)\s+[+]/"));
        }

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger details'))}]/following::table[1]/descendant::tr[*[1][not(normalize-space()) and .//img[contains(@src, 'icon-header-pax-')]]]/*[2][count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]",
                null, "/^[[:alpha:]\- \.]+$/u");
        }

        $travellers = array_values(preg_replace("#(?:^\s*" . $this->opt($this->t("Mr")) . "\.?[ ]|\s+" . $this->opt($this->t("Mr")) . "\s*$)#ui", '', array_unique($travellers)));

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers, true);
        }

        $totalRule = is_array($this->t('Total')) ? array_map(function ($v) {return $v . ' '; }, $this->t('Total')) : $this->t('Total') . ' ';
        $total = $this->http->FindSingleNode("//td[{$this->starts($totalRule)} and not({$this->contains($this->t('Duration'))}) and not(.//td)]");

        if (!$total) {
            $total = $this->http->FindSingleNode("//td[{$this->starts($this->t('Subtotal'))} and not(.//td)]");
        }

        if (!$total) {
            $total = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('TotalExt')) . ']/following::td[1]');
        }

        $tot = $this->getTotalCurrency($total);

        if ($tot['Total'] !== null) {
            $f->price()
                ->currency($tot['Currency'])
                ->total($tot['Total']);

            $fareValue = $this->http->FindSingleNode("//td[{$this->eq($this->t('Fare'))}]/following-sibling::td[normalize-space()][1]");
            $fare = $this->getTotalCurrency($fareValue);

            if ($fare['Total'] !== null && $fare['Currency'] === $tot['Currency']) {
                $f->price()
                    ->cost($fare['Total']);
            }

            $feeRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Fare'))}] and *[2] ]/following-sibling::tr[ *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeChargeValue = $this->http->FindSingleNode('*[2]', $feeRow);
                $feeCharge = $this->getTotalCurrency($feeChargeValue);

                if ($feeCharge['Total'] !== null && $feeCharge['Currency'] === $tot['Currency']) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);

                    if (!empty($feeName) && $feeCharge['Total'] !== null) {
                        $f->price()
                            ->fee($feeName, $feeCharge['Total']);
                    }
                }
            }

            $spentAwards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Miles Plus Cash payment option'))}]/following::img[contains(@src, 'icon-aml-dark')][1]/following::text()[normalize-space()][1]");

            if (!empty($spentAwards)) {
                $f->price()
                    ->spentAwards($spentAwards);
            }
        }

        $segmentsType = '';

        if ($this->parseSegments1($f)) {
            $segmentsType = '1';
        } elseif ($this->parseSegments3($f)) {
            $segmentsType = '3';
        } elseif ($this->parseSegments4($f)) {
            $segmentsType = '4';
        } elseif ($this->parseSegments2($f)) {
            $segmentsType = '2';
        }

        $this->logger->debug('segmentsType - ' . $segmentsType);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . '_' . $segmentsType . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'onlinebooking-NO-REPLY@cathaypacific.com') !== false
            && isset($headers['subject'])
        ) {
            foreach ($this->reSubject as $re) {
                if (stripos($headers['subject'], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'cathaypacific')]")->length > 0) {
            $body = $this->http->Response['body'];

            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 4 * count(self::$dict);
    }

    public function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    protected function parseSegments1(Flight $f): bool
    {
        $this->result = [];

        $xpath = '//img[contains(@src, "/icon-plane")]/ancestor::table[2]/following-sibling::table[descendant::text()[string-length(normalize-space(.))=5 and contains(.,":")]]';

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//table[(contains(., '" . $this->t('Departing') . "') or contains(., '" . $this->t('Returning') . "')) and contains(., '" . $this->t('Duration') . "') and not(.//table)]/ancestor::table[1]/following-sibling::table[contains(., ':') and not(contains(., '" . $this->t('Durata') . "'))]";
        }

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = '//img[contains(@src, "/icon-flight-")]/ancestor::table[2]/following-sibling::table[descendant::text()[string-length(normalize-space(.))=5 and contains(.,":")]]';
        }

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = '//text()[starts-with(normalize-space(.), "' . $this->t('Flight ') . '")]/ancestor::table[2]/following-sibling::table[descendant::text()[string-length(normalize-space(.))=5 and contains(.,":")]]';
        }
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->debug('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $dateFormat = implode('|', [
                // mar 18 apr 2017
                "\w+[ \.]+\d+ \w+[ \.]+\d+",
                // 2018年12月13日(星期四)    |    2019 年 5 月 3 日 ( 金 )
                "\d{4}[ ]*年[ ]*\d{1,2}[ ]*月[ ]*\d{1,2}[ ]*日(?:[ ]*\([ ]*\w+[ ]*\))?",
            ]);
            $regular = '/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+(' . $dateFormat . ')\s+([A-Z]{3})\s+(\d+:\d+)\s+([A-Z]{3})\s+(\d+:\d+)\s*(?<overnight>(?:\+|\-)\d+)?.*?\s+/u';
            $nodes = implode(' ', $this->http->FindNodes(".//text()", $root));

            if (preg_match($regular, $nodes, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);

                $s->departure()
                    ->code($matches[4])
                    ->date(strtotime($this->normalizeDate($matches[3] . ' ' . $matches[5])));

                $s->arrival()
                    ->code($matches[6])
                    ->date((empty($matches['overnight'])) ? strtotime($this->normalizeDate($matches[3] . ' ' . $matches[7])) : strtotime($matches['overnight'] . ' day', strtotime($this->normalizeDate($matches[3] . ' ' . $matches[7]))));
            }
            $node = implode(' ', $this->http->FindNodes('following-sibling::table[1]//text()', $root));

            if (preg_match('/(\w+)\s+' . $this->t('Class') . '\s*([A-Z])\s+' . $this->t('Requested seats') . '\s+([A-Z\d\s,]*)/u', $node, $matches)) {
                $s->extra()
                    ->cabin($matches[1])
                    ->bookingCode($matches[2]);

                if (!empty($matches[3])) {
                    $s->extra()
                        ->seats(array_filter(array_map(function ($v) { if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $v, $m)) {return $m[1]; } else {return null; }}, explode(",", $matches[3]))));
                }
            } elseif (preg_match('/(\w+)\s+' . $this->t('Class') . '\s*([A-Z])/u', $node, $matches)) {
                $s->extra()
                    ->cabin($matches[1])
                    ->bookingCode($matches[2]);
            }

            if ($operator = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Operated by") . "']/following::text()[normalize-space(.)][1]", $root)) {
                $s->airline()
                    ->operator($operator);
            }

            if (preg_match("#" . $this->t("\d+hr \d+min") . "#", $root->nodeValue, $m)) {
                $s->extra()
                    ->duration($m[0]);
            }
        }

        return true;
    }

    private function parseSegments2(Flight $f): bool
    {
        $this->result = [];

        $xpathTrTable = '(self::tr or self::table)';
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"dd:dd")';

        $segments = $this->http->XPath->query("//tr[ *[1]/descendant::tr[normalize-space()][1][{$xpathTime}] and *[3]/descendant::tr[normalize-space()][1][{$xpathTime}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $seg = [];

            $date = 0;

            $flight = implode(' ', $this->http->FindNodes("ancestor-or-self::*[ {$xpathTrTable} and preceding-sibling::*[{$xpathTrTable} and normalize-space()] ][1]/preceding-sibling::*[{$xpathTrTable} and normalize-space()][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^(?<date>.{6,}?)\s*\|\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/", $flight, $m)) {
                // Monday 30 December 2019 | CX714 Singapore to Hong Kong
                $date = strtotime($m['date']);
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $timeDep = $this->http->FindSingleNode("*[1]/descendant::tr[normalize-space()][1]", $segment, true, '/^\d{2}[:]+\d{2}.*/');

            if ($date && $timeDep) {
                $s->departure()
                    ->date(strtotime($timeDep, $date));
            }

            $timeArr = $this->http->FindSingleNode("*[3]/descendant::tr[normalize-space()][1]", $segment);

            if ($date && (preg_match('/^(?<time>\d{2}[:]+\d{2}.*)[+]\s*(?<overnight>\d{1,3})\b/', $timeArr, $m) // 07:05 + 1
                || preg_match('/^(?<time>\d{2}[:]+\d{2}.*)/', $timeArr, $m))
            ) {
                $s->arrival()
                    ->date(strtotime($m['time'], $date));

                if (!empty($seg['ArrDate']) && !empty($m['overnight'])) {
                    $s->arrival()
                        ->date(strtotime('+' . $m['overnight'] . ' days', $seg['ArrDate']));
                }
            }

            $codeDep = $this->http->FindSingleNode("*[1]/descendant::tr[normalize-space()][2]", $segment, true, '/^[A-Z]{3}$/');
            $s->departure()
                ->code($codeDep);

            $codeArr = $this->http->FindSingleNode("*[3]/descendant::tr[normalize-space()][2]", $segment, true, '/^[A-Z]{3}$/');
            $s->arrival()
                ->code($codeArr);

            $nameDep = $this->http->FindSingleNode("*[1]/descendant::tr[normalize-space()][3]", $segment, true, '/^.{3,}$/');
            $s->departure()
                ->name($nameDep);
            $nameArr = $this->http->FindSingleNode("*[3]/descendant::tr[normalize-space()][3]", $segment, true, '/^.{3,}$/');
            $s->arrival()
                ->name($nameArr);

            $terminalDep = $this->http->FindSingleNode("*[1]/descendant::tr[normalize-space()][4]", $segment, true, '/^.*Terminal.*$/i');

            if ($terminalDep) {
                $s->departure()
                    ->terminal(preg_replace('/^Terminal\s*/i', '', $terminalDep));
            }
            $terminalArr = $this->http->FindSingleNode("*[3]/descendant::tr[normalize-space()][4]", $segment, true, '/^.*Terminal.*$/i');

            if ($terminalArr) {
                $s->arrival()
                    ->terminal(preg_replace('/^Terminal\s*/i', '', $terminalArr));
            }

            $duration = $this->http->FindSingleNode("*[2]/descendant::tr[normalize-space()][1]", $segment, true, "/^{$this->opt($this->t('Duration'))}\s*(\d.+)/");
            $s->extra()
                ->duration($duration);

            $xpathExtra = "ancestor::table[ following-sibling::table[normalize-space()] ][1]/following-sibling::table[normalize-space()][1]/descendant::td[count(table)=3][1]";

            $class = implode(' ', $this->http->FindNodes($xpathExtra . "/table[1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^(.+?)\s*\|\s*{$this->opt($this->t('Class'))}\s*([A-Z]{1,2})$/", $class, $m)) {
                // Economy | Class O
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            }

            $operator = $this->http->FindSingleNode($xpathExtra . "/table[2]", $segment, true, "/^{$this->opt($this->t('Operated by'))}\s*(.+)/");
            $s->airline()
                ->operator($operator);

            $aircraft = $this->http->FindSingleNode($xpathExtra . "/table[3]", $segment);
            $s->extra()
                ->aircraft($aircraft);

            $seatsValue = $this->http->FindSingleNode($xpathExtra . "/following::table[normalize-space()][1]/descendant::tr[{$this->starts($this->t('Requested seats'))}][1]", $segment, true, "/^{$this->opt($this->t('Requested seats'))}\s*(\d+[A-Z](?:\s*[,]+\s*\d+[A-Z])*)$/");

            if ($seatsValue) {
                // 17K, 17G
                $s->extra()
                    ->seats(preg_split('/\s*[,]+\s*/', $seatsValue));
            }
        }

        return true;
    }

    private function parseSegments3(Flight $f): bool
    {
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"dd:dd")';

        if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your itinerary'))}]/following::tr[{$xpathTime}][1]/ancestor::tr[normalize-space()][1]/descendant::tr[normalize-space()][1]", null, true, "/^(.*\b\d{4}\b.*)/"))) {
            $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Your itinerary'))}]/following::tr[{$xpathTime}]/ancestor::tr[normalize-space()][1]");

            foreach ($segments as $segment) {
                $s = $f->addSegment();

                $flight = implode("\n", $this->http->FindNodes("./following::tr[1]/descendant::text()[normalize-space()]", $segment));

                if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{2,4})\n{$this->opt($this->t('Operated by'))}\n(?<operator>.+)\n(?<aircraft>.+)\n(?<cabin>.+\n*.*)\|*\s*(?:{$this->opt($this->t('Class'))})?\s*(?<bookingCode>[A-Z]{1,2})?$/", $flight, $m)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number'])
                        ->operator($m['operator']);

                    $s->extra()
                        ->aircraft($m['aircraft'])
                        ->cabin(str_replace(["\n", "|"], " ", $m['cabin']));

                    if (isset($m['bookingCode']) && !empty($m['bookingCode'])) {
                        $s->extra()
                            ->bookingCode($m['bookingCode']);
                    }
                }

                $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $segment));

                if (preg_match("/^(?<date>.*\b\d{4}(?:\b|年).*)\n(?<time>[\d\:]+)\n(?<code>[A-Z]{3})\n.*(?:\n{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+))?$/u", $depInfo, $m)) {
                    $s->departure()
                        ->date(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])))
                        ->code($m['code']);

                    if (isset($m['terminal']) && !empty($m['terminal'])) {
                        $s->departure()
                            ->terminal($m['terminal']);
                    }
                }

                $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/following::td[1]/following::td[1]/descendant::text()[normalize-space()]", $segment));

                if (preg_match("/^(?<date>.*\b\d{4}(?:\b|年).*)\n(?<time>[\d\:]+)\n(?:(?<nextDay>\D{1,2}\d)\n*)?(?<code>[A-Z]{3})\n.*(?:\n{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+))?$/", $arrInfo, $m)) {
                    $s->arrival()
                        ->date(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])))
                        ->code($m['code']);

                    if (isset($m['terminal']) && !empty($m['terminal'])) {
                        $s->arrival()
                            ->terminal($m['terminal']);
                    }
                }

                $seatText = $this->http->FindSingleNode("./ancestor::tr[3]/descendant::text()[{$this->eq($this->t('Requested seats'))}]/ancestor::tr[1]/following-sibling::tr[1]", $segment);

                if (preg_match_all("/\s*(\d{1,2}[A-Z])/", $seatText, $match)) {
                    $s->extra()
                        ->seats($match[1]);
                }
            }

            return true;
        }

        return false;
    }

    private function parseSegments4(Flight $f): bool
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789","dddddddddd"),"dd:dd")';
        $xpath = "//text()[{$this->eq($this->t('Your itinerary'))}]/following::tr[*[1][{$xpathTime}] and *[3][{$xpathTime}] and *[2]//img]/ancestor::*[position() < 5][count(*[normalize-space()]) = 3][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            return false;
        }

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $flight = implode("\n", $this->http->FindNodes("following::text()[normalize-space()][1]/ancestor::*[count(.//td[not(.//td)]) > 2][1]//text()[normalize-space()]", $segment));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{1,5})\n(?<aircraft>.+)(?:\n{$this->opt($this->t('Operated by'))}\s+.+)?\n(?<cabin>.+\n*.*)\n(?:{$this->opt($this->t('Class'))})?\s*(?<bookingCode>[A-Z]{1,2})$/", $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number'])
                ;

                $s->extra()
                    ->aircraft($m['aircraft'])
                    ->cabin(str_replace(["\n", "|"], " ", $m['cabin']));

                if (isset($m['bookingCode']) && !empty($m['bookingCode'])) {
                    $s->extra()
                        ->bookingCode($m['bookingCode']);
                }
            }

            $depInfo = implode("\n", $this->http->FindNodes("./*/*[normalize-space()][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^(?<date>.*\b\d{4}(?:\b|年).*)\n(?<code>[A-Z]{3})\n(?<time>[\d\:]+)\n(?<name>.*)(?:\n{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+))?$/", $depInfo, $m)) {
                $s->departure()
                    ->date(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])))
                    ->code($m['code'])
                    ->name($m['name'])
                ;

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./*/*[normalize-space()][last()]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^(?<date>.*\b\d{4}(?:\b|年).*)\n(?<code>[A-Z]{3})\n(?<time>[\d\:]+)\n(?<name>.*)(?:\n{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+))?$/", $arrInfo, $m)) {
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])))
                    ->code($m['code'])
                    ->name($m['name'])
                ;

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (!preg_match("/cathaypacific/u", $body) && !preg_match("/asia/u", $body)) {
            return false;
        }
        /*if (stripos($body, 'cathaypacific') === false || stripos($body, 'asia') === false) {
            return false;
        }*/

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//node()[{$this->contains($reBody)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            '#\w+\s+(\d+\s+\w+\s+\d+)[\s,]+(\d+:\d+)#u',
            '#^\w+[\. ]+(\d+) (\w+)[\. ]+(\d{4}) (\d+:\d+)$#u',
            // 2018年12月11日(星期二)    |    2019 年 5 月 3 日 ( 金 ) 07:20
            // 2023年11月8日 (星期三), 08:10
            '#^\s*(\d{4})[ ]*年[ ]*(\d{1,2})[ ]*月[ ]*(\d{1,2})[ ]*日(?:[ ]*\([ ]*\w+[ ]*\))?[ ,]+(\d{1,2}:\d{2})$#u',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $3 $4',
            '$3.$2.$1, $4',
        ];
        // $this->logger->debug('$date = '.print_r( preg_replace($in, $out, $date),true));
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function getTotalCurrency($node)
    {
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            $tot = preg_replace('#\s+#', '', $m['t']);

            switch ($this->lang) {
                case 'es':
                case 'it':
                case 'fr':
                case 'de':
                case 'nl':
                    $tot = str_replace(['.', ','], ['', '.'], $tot);

                    break;

                case 'en':
                    $tot = $this->normalizeAmount($tot);

                    break;

                case 'zh':
                    $tot = str_replace([','], [''], $tot);

                    break;

                case 'ja':
                    $tot = str_replace([','], [''], $tot);

                    break;
            }

            if (!is_numeric($tot)) {
                $tot = null;
            } else {
                $tot = (float) $tot;
            }
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
}
