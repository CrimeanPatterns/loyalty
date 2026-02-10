<?php

// bcdtravel

namespace AwardWallet\Engine\airtransat\Email;

class ReservationHtml2017 extends \TAccountChecker
{
    public $mailFiles = "airtransat/it-10155974.eml, airtransat/it-35211710.eml, airtransat/it-6192292.eml, airtransat/it-6194623.eml, airtransat/it-6358637.eml, airtransat/it-6957724.eml, airtransat/it-7010150.eml, airtransat/it-7192824.eml, airtransat/it-7203461.eml, airtransat/it-7243764.eml, airtransat/it-7252395.eml, airtransat/it-7327889.eml, airtransat/it-7377960.eml, airtransat/it-7383139.eml";

    private $result = [];

    private $provider = 'Air Transat';

    private static $detectBody = [
        'en' => [
            'No electronic ticket will be issued.',
            'No other document will be issued',
            'This is your official Booking Confirmation',
            'This document stands as your booking confirmation',
            'There has been a change to your flight reservation',
        ],
        'fr' => [
            'Ce document constitue votre billet électronique. Aucun autre document ne sera émis',
            'Ce document constitue votre confirmation de réservation. Aucun billet électronique',
        ],
        'it' => [
            'Questo è il tuo itinerario ufficiale',
            'Non sarà emesso nessun altro documento',
        ],
        'pt' => [
            'Esta é a sua confirmação de reserva oficial',
            'Dados do itinerário aéreo',
        ],
        'nl' => [
            'Dit is uw officiële reisroute',
            'Er worden geen andere documenten afgegeven',
            'Vluchtdetails',
        ],
        'es' => [
            'Ha ocurrido un cambio en su itinerario',
            'Este es su itinerario oficial. No se emitirá ningún otro documento',
            'Esta es su confirmación de reserva oficial. No se emitirá ningún billete electrónico',
        ],
    ];

    private $lang = 'en';

    private $dict = [
        'en' => [
            'Aircraft' => '(?:Aircraft|Equipment)',
        ],
        'fr' => [
            'Confirmation Number' => 'Numéro de confirmation',
            'Passengers'          => 'Passagers',
            'Flight'              => 'Vol',
            'Taxes, Fees and'     => 'Taxes aéroportuaires',
            'Fare Breakdown'      => 'Détail du tarif',
            'Payment Summary'     => 'Résumé de paiement',
            'Total Payments'      => 'Paiement Total',
            'Charged to'          => 'Facturé à',
            'TOTAL AIR FARE'      => 'Prix total du billet',
            'Total'               => 'Total',
            'Flight Number'       => 'Vol',
            'Departure'           => 'Départ',
            'Arrival'             => 'Arrivée',
            'Terminal'            => 'Terminal', // to ckeck
            'Class'               => 'Classe',
            'Airline'             => 'Transporteur',
            'Aircraft'            => 'Appareil',
            //			'New Air Itinerary Details' => '',
            'Your Seats' => 'Vos sièges',
            'Seat'       => 'Siège',
        ],
        'it' => [
            'Confirmation Number' => 'Numero di conferma',
            'Passengers'          => 'Passeggeri',
            'Flight'              => 'Volo',
            'Taxes, Fees and'     => 'Imposte, commissioni e',
            'Fare Breakdown'      => 'Dettaglio della tariffa',
            'Payment Summary'     => 'Riepilogo dei pagamenti',
            'Total Payments'      => 'Totale pagamenti',
            'Charged to'          => 'Addebitato su',
            'TOTAL AIR FARE'      => 'Costo totale del biglietto',
            'Total'               => 'Totale', // to check
            'Flight Number'       => 'Numero del volo',
            'Departure'           => 'Partenza',
            'Arrival'             => 'Arrivo',
            'Terminal'            => 'Terminale', // to ckeck
            'Class'               => 'Classe',
            'Airline'             => 'Vettore',
            'Aircraft'            => 'Aereo',
            //			'New Air Itinerary Details' => '',
            'Your Seats' => 'Le tue sedute', // to check
            'Seat'       => 'Sede', // to check
        ],
        'pt' => [
            'Confirmation Number' => 'Número de confirmação',
            'Passengers'          => 'Passageiros',
            'Flight'              => 'Voo',
            'Taxes, Fees and'     => 'Taxas, comissões e',
            'Fare Breakdown'      => 'Decomposição da tarifa',
            'Payment Summary'     => 'Resumo do pagamento',
            'Total Payments'      => 'Pagamentos totais', //to check
            'Charged to'          => 'Débito em',
            'TOTAL AIR FARE'      => 'Total tarifa aérea',
            'Total'               => 'Total', // to check
            'Flight Number'       => 'Número do voo',
            'Departure'           => 'Partida',
            'Arrival'             => 'Chegada',
            //			'Terminal' => '',
            'Class'    => 'Classe',
            'Airline'  => 'Companhia aérea',
            'Aircraft' => 'Aeronave',
            //			'New Air Itinerary Details' => '',
            'Your Seats' => 'Seus assentos', // to check
            'Seat'       => 'Assento', // to check
        ],
        'nl' => [
            'Confirmation Number' => 'Bevestigingsnummer',
            'Passengers'          => 'Passagiers',
            'Flight'              => 'Vlucht',
            'Taxes, Fees and'     => 'Belastingen en toeslagen',
            'Fare Breakdown'      => 'Tariefspecificatie',
            'Payment Summary'     => 'Betalingsoverzicht',
            'Total Payments'      => 'Totaal betalingen',
            'Charged to'          => 'Wordt afgeboekt van',
            'TOTAL AIR FARE'      => 'Totaal vluchttarief',
            'Total'               => 'Totaal',
            'Flight Number'       => 'Vluchtnummer',
            'Departure'           => 'Vertrek',
            'Arrival'             => 'Aankomst',
            'Terminal'            => 'Terminal', // to check
            'Class'               => 'Klasse',
            'Airline'             => 'Vervoerder',
            'Aircraft'            => 'Toestel',
            //			'New Air Itinerary Details' => '',
            'Your Seats' => 'Uw stoelen',
            'Seat'       => 'Stoel',
        ],
        'es' => [
            'Confirmation Number' => 'Número de confirmación',
            'Passengers'          => 'Pasajeros',
            'Flight'              => 'Vuelo',
            'Taxes, Fees and'     => 'Impuestos, tasas y cargos',
            'Fare Breakdown'      => 'Desglose de tarifa',
            'Payment Summary'     => 'Tarifa total',
            'Total Payments'      => 'Tarifa total',
            'Charged to'          => 'Cargado a Visa',
            //			'TOTAL AIR FARE' => 'Cargado a Visa',
            //			'Total' => 'Cargado a Visa',
            'Flight Number' => 'Número de vuelo',
            'Departure'     => 'Salida',
            'Arrival'       => 'Llegada',
            'Terminal'      => 'Terminal',
            'Class'         => 'Clase',
            'Airline'       => 'Número de vuelo',
            'Aircraft'      => 'Avión',
            //			'New Air Itinerary Details' => '',
            'Your Seats' => 'Sus asientos',
            'Seat'       => 'Asiento',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);
        $this->parseEmail();

        return [
            'emailType'  => 'ReservationHtml2017' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@airtransat.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airtransat.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } elseif ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, 'fr')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }

        return false;
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function innerArray(\DOMNodeList $list)
    {
        $array = [];

        foreach ($list as $value) {
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return $array;
    }

    private function tableCell($method, $root)
    {
        return count($this->http->FindNodes(".//text()[$method]/ancestor::*[self::td or self::th][1]/preceding-sibling::*", $root)) + 1;
    }

    private function parseEmail()
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(., "' . $this->t('Confirmation Number') . '")]/ancestor::td[1]/following-sibling::td[1]', null, false, '/[A-Z\d]{5,6}$/');

        $this->result['Passengers'] = $this->http->FindNodes('(//*[self::h1 or self::h2][.="' . $this->t('Passengers') . '"]/following::table[contains(., "' . $this->t('Flight') . '")])[1]//tr/td[1]', null, '/^(.+?)\s*(,|$)/');
        $this->result['Passengers'] = array_diff($this->result['Passengers'], ['']);

        if (count($this->result['Passengers']) === 0) {
            $this->result['Passengers'] = $this->http->FindNodes("//text()[normalize-space()='Passengers']/following::table[1]/descendant::text()[normalize-space()]");
        }

        $this->result['Passengers'] = preg_replace("/^\s*(Mr|Mrs|Ms|Miss|Dr|Mme|M|Mevrouw|De heer)\.?\s+/", '', $this->result['Passengers']);

        $xpath = '//*[contains(text(), "' . $this->t('Fare Breakdown') . '")]/following::table[1]//tr[1]';
        $this->logger->debug($xpath);
        $baseFares = $this->http->XPath->query($xpath);

        foreach ($baseFares as $root) {
            $passengerIndex = $this->tableCell($this->contains((array) $this->t('Passengers')) . ' or ' . $this->contains(array_map('strtolower', (array) $this->t('Passengers'))), $root);
            $taxIndex = $this->tableCell($this->contains($this->t('Taxes, Fees and')), $root);
        }

        if (isset($passengerIndex, $taxIndex)) {
            $xpath = '//*[contains(text(), "' . $this->t('Fare Breakdown') . '")]/following::table[1]//tr[position() > 1]';
            $baseFares = $this->http->XPath->query($xpath);
            $baseFare = [];
            $taxes = [];
            $transFee = [];

            foreach ($baseFares as $root) {
                $passengers = (float) $this->http->FindSingleNode("./*[{$passengerIndex}]", $root, true, "/x\s*(\d+)/");

                $sbaseFare = $this->http->FindSingleNode('./*[2]', $root);
                $baseFare[] = $this->normalizePrice($sbaseFare) * $passengers;

                $staxes = $this->http->FindSingleNode("./*[{$taxIndex}]", $root);
                $taxes[] = $this->normalizePrice($staxes) * $passengers;

                //			$stransFee = $this->http->FindSingleNode('./*[3]',$root);
//			$transFee[] = $this->normalizePrice($stransFee) * $passengers;
            }
            $this->result['BaseFare'] = array_sum($baseFare);
            $this->result['Tax'] = array_sum($taxes);
            //		if (isset($transFeeName) && !empty($transFee)) {
//			$this->result['Fees'][] = ['Name' => $transFeeName, 'Charge' => array_sum($transFee)];
//		}
        }

        if (isset($this->result['BaseFare'])) {
            // with out basefare, it may be only seats price
            if ($total = $this->http->FindSingleNode('//*[contains(text(), "' . $this->t('Payment Summary') . '")]/following::table//tr[contains(., "' . $this->t('Total Payments') . '")]')) {
                $total = preg_replace(['/€/', '/£/', '/$/'], ['EUR', 'GBP', 'USD'], $total);

                if (preg_match("#([A-Z]{3}\s*[\d\s.,]+|[\d\s.,]+\s*[A-Z]{3})\s*$#", $total, $m)) {
                    $this->result['TotalCharge'] = $this->normalizePrice($m[0]);
                    $this->result['Currency'] = preg_replace('/[\d.,\s]+/', '', $m[0]);
                }
            }

            if (empty($this->result['TotalCharge']) && empty($this->result['Currency'])) {
                if ($total = $this->http->FindNodes('//*[contains(text(), "' . $this->t('Payment Summary') . '")]/following::table//tr[contains(., "' . $this->t('Charged to') . '")]')) {
                    $cost = [];

                    foreach ($total as $payment) {
                        $payment = preg_replace(['/\$/', '/\€/', '/\£/'], ['USD', 'EUR', 'GBP'], $payment);

                        if (preg_match("#([A-Z]{3}\s*[\d\s.,]+|[\d\s.,]+\s*[A-Z]{3})\s*$#", $payment, $m)) {
                            $cost[] = $this->normalizePrice($m[0]);
                            $this->result['Currency'] = preg_replace('/[\d.,\s]+/', '', $m[0]);
                        }
                    }
                    $this->result['TotalCharge'] = array_sum($cost);
                }
            }

            $fees = $this->http->FindNodes('//table[contains(., "' . $this->t('TOTAL AIR FARE') . '")][1]/following::*[starts-with(text(), "' . $this->t('Total') . '")]');

            foreach ($fees as $key => $str) {
                if (preg_match('#\s*(' . $this->t('Total') . '[^:\d]+):?[^\d]*(\d[\d\s.,]+)#', $str, $m)) {
                    $this->result['Fees'][] = ['Name' => trim($m[1]), 'Charge' => $this->normalizePrice($m[2])];
                }
            }
        }

        $changed = '//';

        if ($this->http->FindSingleNode('//*[contains(text(), "' . $this->t('New Air Itinerary Details') . '")]')) {
            $changed = '//*[contains(text(), "' . $this->t('New Air Itinerary Details') . '")]/following::';
        }
        $xpath = $changed . '*[contains(text(), "' . $this->t('Flight Number') . '") and contains(text(), ":")]/ancestor::tr[1][ancestor::table[1]/preceding::h2[1][not(contains(., "Información antigua del itinerario de vuelo"))]]';
        $this->logger->info($xpath);

        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            return false;
        }

        foreach ($roots as $root) {
            $i = [];
            $arrayDep = $this->innerArray($this->http->XPath->query('./preceding-sibling::tr[1]//td[contains(., "' . $this->t('Departure') . '")]//text()', $root));

            if (isset($arrayDep) && count($arrayDep) > 2) {
                $i['DepDate'] = $this->normalizeDate($arrayDep[1]);
                $i['DepName'] = $this->match('/^(.+?)\([A-Z]{3}\)/', $arrayDep[2]);
                $i['DepCode'] = $this->match('/^.+?\(([A-Z]{3})\)/', $arrayDep[2]);
            }

            if (isset($arrayDep[4]) && $this->match('/(?:' . $this->t('Terminal') . ')?\s*([A-Z\d]{1,5})/', $arrayDep[4])) {
                $i['DepartureTerminal'] = $arrayDep[4];
            }

            $arrayArr = $this->innerArray($this->http->XPath->query('./preceding-sibling::tr[1]//td[contains(., "' . $this->t('Arrival') . '")]//text()', $root));

            if (isset($arrayArr) && count($arrayArr) > 2) {
                $i['ArrDate'] = $this->normalizeDate($arrayArr[1]);
                $i['ArrName'] = $this->match('/^(.+?)\([A-Z]{3}\)/', $arrayArr[2]);
                $i['ArrCode'] = $this->match('/^.+?\(([A-Z]{3})\)/', $arrayArr[2]);
            }

            if (isset($arrayArr[4]) && $this->match('/[\:]?\s*([A-Z\d]{1,5})/i', $arrayArr[4])) {
                $i['ArrivalTerminal'] = $arrayArr[4];
            }

            $text = join("\n", $this->innerArray($this->http->XPath->query('.//text()[normalize-space(.)]', $root)));
            $text = str_replace('&nbsp;', ' ', $text);

            if (preg_match("#" . $this->t('Class') . "\s*:\s*(.+)\n#i", $text, $m)) {
                $i['Cabin'] = $m[1];
            }

            if (preg_match("#" . $this->t('Aircraft') . "\s*:\s*(.+)\n#i", $text, $m)) {
                $i['Aircraft'] = $m[1];
            }

            if (preg_match("#" . $this->t('Flight Number') . "\s*:\s*([A-Z\d]{2})\s*(\d+)#i", $text, $m)) {
                $i['AirlineName'] = $m[1];
                $i['FlightNumber'] = $m[2];
            }

            if (!empty($i['AirlineName']) && !empty($i['FlightNumber'])) {
                $seats = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Your Seats') . "')]/following::table//tr[contains(., '" . $i['AirlineName'] . ' ' . $i['FlightNumber'] . "')]//*[contains(text(),'" . $this->t('Seat') . "')]", null, "#" . $this->t('Seat') . "\s*(\d+[A-Z])#i");
                $seats = array_diff($seats, ['', null, false]);

                if (!empty($seats)) {
                    $i['Seats'] = $seats;
                }
            }

            $this->result['TripSegments'][] = $i;
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->provider) === false) {
            return false;
        }

        foreach (self::$detectBody as $lang => $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            } elseif (is_array($detect)) {
                foreach ($detect as $item) {
                    if (stripos($body, $item) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //mar., 06 juin 2017, 12:55
            '#^\S+\s+(\d{1,2})\s+(\w+)\.?\s+(\d{4}),\s+(\d{1,2}:\d{2})$#u',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function normalizePrice($price)
    {
        if (preg_match("#([.,])\d{2}($|[^\d])#", $price, $m)) {
            $delimiter = $m[1];
        } else {
            $delimiter = '.';
        }
        //		$price = preg_replace('/[^\d\\'.$this->priceDelimiter[$this->lang].']+/', '', $price);
        $price = preg_replace('/[^\d\\' . $delimiter . ']+/', '', $price);
        $price = (float) str_replace(',', '.', $price);

        return $price;
    }

    private function t($s)
    {
        if (empty($this->dict[$this->lang]) || empty($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
