<?php

namespace AwardWallet\Engine\airmalta\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "airmalta/it-11656290.eml, airmalta/it-11666586.eml, airmalta/it-3129142.eml, airmalta/it-3693385.eml, airmalta/it-4627978.eml, airmalta/it-5026202.eml, airmalta/it-5069985.eml, airmalta/it-5113357.eml, airmalta/it-7248394.eml, airmalta/it-7294650.eml, airmalta/it-7338958.eml, airmalta/it-7348776.eml, airmalta/it-7477882.eml, airmalta/it-7496815.eml, airmalta/it-68858725.eml";

    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Booking Confirmation'  => 'Booking Confirmation',
            'Air Itinerary Details' => 'Air Itinerary Details',
        ],
        'it' => [
            'Booking Confirmation'  => 'Conferma di prenotazione',
            'Fare Breakdown'        => 'Disaggregazione tariffa',
            'GRAND TOTAL'           => 'TOTALE COMPLESSIVO',
            'TOTAL:'                => 'TOTALE:',
            'Flight'                => 'Volo',
            'Departure: TERMINAL'   => 'Partenza: TERMINAL',
            'Arrival: TERMINAL'     => 'Arrivo: TERMINAL',
            'Fare Family'           => 'Tariffa famiglia',
            'Duration'              => 'Durata',
            'Miles'                 => 'Miglia',
            'Stop'                  => 'Scalo',
            'Air Itinerary Details' => 'Dettagli itinerario aereo',
            'Ticket Number'         => 'Numero biglietto',
            'Passengers'            => 'Passeggeri',
            'Issue Date'            => 'Data emissione',
            'Reference'             => 'Riferimento',
        ],
        'fr' => [
            'Booking Confirmation'  => 'Confirmation de réservation',
            'Fare Breakdown'        => 'Décomposition du tarif',
            'GRAND TOTAL'           => 'TOTAL FINAL',
            'TOTAL:'                => 'TOTAL :',
            'Flight'                => 'Vol',
            'Departure: TERMINAL'   => ['Depart : TERMINAL', 'Depart : AEROGARE'],
            'Arrival: TERMINAL'     => ['Arrivee : TERMINAL', 'Arrivee : AEROGARE'],
            'Fare Family'           => 'Famille de tarifs',
            'Duration'              => 'Durée',
            'Miles'                 => 'Miles',
            'Stop'                  => 'Escale',
            'Air Itinerary Details' => "Informations sur l'itinéraire aérien",
            'Ticket Number'         => 'Numéro de billet',
            'Passengers'            => 'Passagers',
            'Issue Date'            => "Date d'émission",
            'Reference'             => 'Référence',
        ],
        'ru' => [
            'Booking Confirmation'  => 'Подтверждение бронирования',
            'Fare Breakdown'        => 'Разбивка тарифа',
            'GRAND TOTAL'           => 'ОБЩАЯ СУММА',
            'TOTAL:'                => 'ИТОГО:',
            'Flight'                => 'Рейс',
            'Departure: TERMINAL'   => '',
            'Arrival: TERMINAL'     => 'Прибытие: TERMINAL',
            'Fare Family'           => 'Тариф семейный',
            'Duration'              => 'Продолжительность',
            'Miles'                 => 'Мили',
            'Stop'                  => 'Остановка',
            'Air Itinerary Details' => 'Перелет детали маршрута',
            'Ticket Number'         => 'Номер билета',
            'Passengers'            => 'Пассажиры',
            'Issue Date'            => 'Дата оформления',
            'Reference'             => 'Справка',
        ],
        'de' => [
            'Booking Confirmation'  => 'Buchungsbestätigung',
            'Fare Breakdown'        => 'Tarifaufschlüsselung',
            'GRAND TOTAL'           => 'GESAMTPREIS:',
            'TOTAL:'                => 'GESAMT:',
            'Flight'                => 'Flug',
            'Departure: TERMINAL'   => '',
            'Arrival: TERMINAL'     => '',
            'Fare Family'           => 'Tarifgruppe',
            'Duration'              => 'Dauer',
            'Miles'                 => 'Flugmeilen',
            'Stop'                  => 'Saver Zwischenlandung',
            'Air Itinerary Details' => 'Angaben zum Reiseplan',
            'Ticket Number'         => 'Flugticketnummer',
            'Passengers'            => 'Passagiere',
            'Issue Date'            => 'Ausstellungsdatum',
            'Reference'             => 'Referenz',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airmalta.com.mt') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (stripos($headers['from'], 'ibe-enquiries@airmalta.com.mt') !== false
            || stripos($headers['from'], 'Air Malta') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'Air Malta plc') !== false
        && $this->http->XPath->query('//a[contains(@href,"//www.airmalta.com/information/about/contact-us")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->assignLang($body);
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ReservationConfirmation2',
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function ParseEmail(): ?array
    {
        $it = [];
        $it['Kind'] = 'T';
        $nodes = $this->http->XPath->query('//h1[starts-with(normalize-space(.),"' . $this->t('Booking Confirmation') . '")]/following::table[1]');

        if ($nodes->length > 0) {
            $booking = $nodes->item(0);                                                                                                               //'/Reference: ([A-Z\d]+)\s*/'
            $it['RecordLocator'] = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"' . $this->t('Reference') . '") and not(.//tr)]', $booking, true, '/' . $this->t('Reference') . '\s*:\s*([A-Z\d]+)\s*/');
            //			Issue Date:	Wed, 14 May 2014
            //			$res_date = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"'.$this->t('Issue Date').':") and not(.//tr)]', $booking, true, '/'.$this->t('Issue Date').':\s+(\S+\s+\d{1,2}\s+[A-Z][a-z]{2}\s+\d{4})/');
            $res_date = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"' . $this->t('Issue Date') . '") and not(.//tr)]', $booking);
            $it['ReservationDate'] = strtotime($this->NormalizeDate($res_date));
        }
        $it['Passengers'] = [];
        $passengers = $this->http->XPath->query('//h2[normalize-space(.)="' . $this->t('Passengers') . '"]/following::table[contains(.,"' . $this->t('Ticket Number') . '")]//tr');

        foreach ($passengers as $p) {
            $name = $this->http->FindSingleNode('./td[1]', $p);

            if (!empty($name)) {
                $it['Passengers'][] = $name;
            }

            if (($ticket = $this->http->FindSingleNode('./td[normalize-space(.)="' . $this->t('Ticket Number') . '"]/following-sibling::td[1]', $p, true, '/^\d+$/'))) {
                $it['TicketNumbers'][] = $ticket;
            }
        }
        $it['TripSegments'] = [];

        $rows = $this->http->XPath->query('//h2[normalize-space(.)="' . $this->t('Air Itinerary Details') . '"]/following-sibling::table[count(.//tr[1]/td)=4]//tr[not(.//tr) and contains(., "' . $this->t('Stop') . '") and following-sibling::tr[1][contains(., "' . $this->t('Duration') . '")]] |
											//h2[normalize-space(.)="' . $this->t('Air Itinerary Details') . '"]/parent::div/following-sibling::table[count(.//tr[1]/td)=4]//tr[not(.//tr) and contains(., "' . $this->t('Stop') . '") and following-sibling::tr[1][contains(., "' . $this->t('Duration') . '")]]');

        foreach ($rows as $row) {
            $rows2 = $this->http->XPath->query('following-sibling::tr[1]', $row);
            $row2 = $rows2->length > 0 ? $rows2->item(0) : null;
            $seg = [];
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['DepName'] = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)][1]', $row);
            $seg['ArrName'] = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][1]', $row);
            $flight = $this->http->FindSingleNode('td[3]/descendant::text()[normalize-space()][1]', $row);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flight, $m)) {
                $seg['FlightNumber'] = $m['number'];
                $seg['AirlineName'] = $m['name'];
            }
            $seg['Stops'] = $this->http->FindSingleNode('./td[4]/descendant::text()[normalize-space(.)][2]', $row, true, '/' . $this->t('Stop') . '\s*:\s+([\d]+)/');
            $seg['Aircraft'] = $this->http->FindSingleNode('./td[1]', $row2, true, "([^,]+)");
            $seg['TraveledMiles'] = $this->http->FindSingleNode('./td[2]', $row2, true, '/' . $this->t('Miles') . '\s*:\s+([\d]+)/');
            $seg['Duration'] = $this->http->FindSingleNode('./td[3]', $row2, true, '/' . $this->t('Duration') . '\s*:\s+([:\d]+)/');
            $seg['Cabin'] = $this->http->FindSingleNode('./td[contains(.,"' . $this->t('Fare Family') . '")]/descendant::text()[normalize-space(.)][1]', $row, true, '/' . $this->t('Fare Family') . '\s*:\s+(.+)/');

            $terminalsHtml = $this->http->FindHTMLByXpath('td[4]', null, $row2);
            $terminalsText = $this->htmlToText($terminalsHtml);

            // DepartureTerminal
            if (is_array($this->t('Departure: TERMINAL'))) {
                $termPattern = implode('|', $this->t('Departure: TERMINAL'));
            } else {
                $termPattern = $this->t('Departure: TERMINAL');
            }

            if (preg_match("/^[ ]*{$termPattern}[ ]+([-\w ]+?)[ ]*$/m", $terminalsText, $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }

            // ArrivalTerminal
            if (is_array($this->t('Arrival: TERMINAL'))) {
                $termPattern = '(?:' . implode('|', $this->t('Arrival: TERMINAL')) . ')';
            } else {
                $termPattern = $this->t('Arrival: TERMINAL');
            }

            if (preg_match("/^[ ]*{$termPattern}[ ]+([-\w ]+?)[ ]*$/m", $terminalsText, $m)) {
                $seg['ArrivalTerminal'] = $m[1];
            }

            $dateDep = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)][2]', $row);
            $dateArr = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][2]', $row);
            $timeDep = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)][3]', $row);
            $timeArr = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][3]', $row);

            if ($dateDep && $dateArr && $timeDep && $timeArr) {
                $dateDep = str_replace(',', '', $dateDep);
                $dateArr = str_replace(',', '', $dateArr);
                $seg['DepDate'] = strtotime($this->NormalizeDate($dateDep . ' ' . $timeDep));
                $seg['ArrDate'] = strtotime($this->NormalizeDate($dateArr . ' ' . $timeArr));
            }
            $it['TripSegments'][] = $seg;
        }

        $directions = explode(',', $this->http->FindSingleNode('(//td[normalize-space(.)="' . $this->t('Flight') . '"]/following-sibling::td[1])[1]'));
        $legs = [];

        foreach ($directions as $direction) {
            $codes = explode('-', $direction);

            for ($i = 0; $i < count($codes) - 1; $i++) {
                $legs[] = [trim($codes[$i]), trim($codes[$i + 1])];
            }
        }

        $names = [];

        if (count($legs) == count($it['TripSegments'])) {
            foreach ($it['TripSegments'] as $i => $v) {
                $it['TripSegments'][$i]['DepCode'] = $depCode = $legs[$i][0];
                $it['TripSegments'][$i]['ArrCode'] = $arrCode = $legs[$i][1];

                if (!array_key_exists(($name = $it['TripSegments'][$i]['DepName']), $names)) {
                    $names[$name] = $depCode;
                } elseif ($depCode != $names[$name]) {
                    $error = true;
                }

                if (!array_key_exists(($name = $it['TripSegments'][$i]['ArrName']), $names)) {
                    $names[$name] = $arrCode;
                } elseif ($arrCode != $names[$name]) {
                    $error = true;
                }

                if (isset($error)) {
                    $this->logger->error('codes mismatch');

                    return null;
                }
            }
        }

        $nodes = $this->http->XPath->query('//h2[normalize-space()="' . $this->t('GRAND TOTAL') . '"]/following::table[1]/descendant-or-self::*[tr][1]/tr');
        $grandtotal = 0.0;

        foreach ($nodes as $root) {
            $grandtotal += $this->normalizePrice($this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"' . $this->t('TOTAL:') . '") and not(.//td)]', $root, true, '/' . $this->t('TOTAL:') . '\s+([\.\,\d]+)/'));
            $it['Currency'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"' . $this->t('TOTAL:') . '") and not(.//td)]', $root, true, '/' . $this->t('TOTAL:') . '\s+[\.\,\d]+\s+([A-Z]{3})/');
        }

        if ($grandtotal != 0) {
            $it['TotalCharge'] = $grandtotal;
        }

        $baseFarexpath = '//*[contains(text(), "' . $this->t('Fare Breakdown') . '")]/following::table[1]//tr';
        $baseFares = $this->http->XPath->query($baseFarexpath);
        $baseFare = [];

        foreach ($baseFares as $root) {
            $passengers = (float) $this->http->FindSingleNode('./*[5]', $root, true, "(\d+)");

            $sbaseFare = $this->http->FindSingleNode('./*[2]', $root);
            $baseFare[] = $this->normalizePrice($sbaseFare) * $passengers;
        }
        $it['BaseFare'] = array_sum($baseFare);

        return $it;
    }

    protected function enDate($nodeForDate)
    {
        $res = '';

        if (preg_match("#(?<day>[\d]+)\s+(?<month>.+)\s+(?<year>\d{4})(?:\s+(?<time>\d+:\d+))?#", $nodeForDate, $chek)) {
            $month = str_replace('.', '', $chek['month']);
            $month = MonthTranslate::translate($month, $this->lang);

            if (empty($month)) {
                $month = MonthTranslate::translate($chek['month'], 'mt');
            }

            if (isset($chek['time']) && !empty($chek['time'])) {
                $res = $chek['day'] . ' ' . $month . ' ' . $chek['year'] . ' ' . $chek['time'];
            } else {
                $res = $chek['day'] . ' ' . $month . ' ' . $chek['year'];
            }
        }

        return $res;
    }

    private function NormalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})\s*(\S+?),?\s+(\d{4})\S?\s*(\d+:\d+)#u',
            '#[\S\s]*(\d{2})\s*(\S+?),?\s+(\d{4})#u',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $3',
        ];

        return $this->enDate(preg_replace($in, $out, $date));
    }

    private function normalizePrice($price)
    {
        if (preg_match("#([.,])\d{2}($|[^\d])#", $price, $m)) {
            $delimiter = $m[1];
        } else {
            $delimiter = '.';
        }
        $price = preg_replace('/[^\d\\' . $delimiter . ']+/', '', $price);
        $price = (float) str_replace(',', '.', $price);

        return $price;
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
        foreach (self::$dict as $lang => $reBody) {
            if (stripos($body, $reBody['Booking Confirmation']) !== false && stripos($body, $reBody['Air Itinerary Details']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
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
