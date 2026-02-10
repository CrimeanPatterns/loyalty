<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketItineraryReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-11439818.eml, aeroflot/it-11449041.eml, aeroflot/it-33358166.eml";

    // associated with parser Booking
    private $htmlDetectors = [
        'ru' => ['Вылет / Прилет', 'ВЫЛЕТ / ПРИЛЕТ', 'Вылет / Прилёт', 'ВЫЛЕТ / ПРИЛЁТ'],
        'en' => ['Departure / Arrival', 'DEPARTURE / ARRIVAL'],
        'de' => ['Abflug/Ankunft'],
    ];

    private $subjects = [
        'ru' => ['Информация о вашем бронировании'],
        'en' => ['Your booking details'],
        'de' => ['Ihre Buchungsdetails'],
    ];

    private $langDetectors = [
        'ru' => ['Вид тарифа:', 'Провоз багажа:'],
        'en' => ['Fare type:', 'Carriage of baggage:'],
        'de' => ['Tariftyp:', 'Beförderung des Gepäcks:'],
    ];

    private $lang = '';

    private static $dict = [
        'ru' => [
            'itSplitPattern'    => '/Маршрутная\s+квитанция\s+электронного\s+билета/i',
            'Itinerary'         => 'Маршрут следования',
            'Booking code'      => 'Код\s+бронирования',
            'E-ticket number'   => '№\s+эл\.\s*билета\s*:',
            'Flight'            => 'Рейс',
            'Status'            => 'Статус',
            'Status bad values' => '(?:Обменян)',
            'datePattern'       => '\d{1,2}[ ]*\D+[ ]*\d{4}(?:[ ]*г\.)?', // 3 апреля 2018 г.
            'Carrier'           => 'Перевозчик',
            'Aircraft type'     => 'Тип ВС',
            'Class'             => 'Класс',
            'Duration'          => 'В пути',
            'Payment amount'    => 'Сумма платежа',
            'Service type'      => 'Вид предоставляемой услуги',
            'Payment amount2'   => 'Итого по тарифу/сборам',
        ],
        'en' => [
            'itSplitPattern' => '/E-ticket\s+itinerary\s+receipt/i',
            //			'Itinerary' => '',
            'Booking code'    => 'Booking\s+code',
            'E-ticket number' => 'E-ticket\s+number\s*:',
            //			'Flight' => '',
            //			'Status' => '',
            //			'Status bad values' => '(?:)',
            'datePattern' => '(?:[^,.\d\s]{3,}[ ]*\d{1,2},[ ]*\d{4}|\d{1,2}[ ]*[^,.\d\s]{3,}[ ]*\d{4})', // December 12, 2017  |   31 Aug 2019
            //			'Carrier' => '',
            //			'Aircraft type' => '',
            //			'Class' => '',
            //			'Duration' => '',
            //			'Payment amount' => '',
            //			'Service type' => '',
        ],
        'de' => [
            'itSplitPattern'  => '/E-Ticket-Reiseroutenbeleg/i',
            'Itinerary'       => 'Reiseroute',
            'Booking code'    => 'Buchungscode\*?',
            'E-ticket number' => 'E-Ticket-Nummer:',
            'Flight'          => 'Flug',
            'Status'          => 'Status',
            //			'Status bad values' => '(?:)',
            'datePattern'    => '\d{1,2}[. ]+[^,.\d\s]{3,}[ ]*\d{4}', // 6. März 2019
            'Carrier'        => 'Beförderer',
            'Aircraft type'  => 'Flugzeugtyp',
            'Class'          => 'Klasse',
            'Duration'       => 'Dauer',
            'Payment amount' => 'Gesamtsumme',
            'Service type'   => 'Bestätigungen/Einschränkungen',
        ],
    ];

    private $pdf;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'ПАО «Аэрофлот»') !== false
            || stripos($from, '@aeroflot.ru') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // associated with parser Booking
        if ($this->detectHtml()) {
            return false;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf|[^\.]');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Aeroﬂot PJSC') === false && stripos($textPdf, 'Aeroflot') === false && stripos($textPdf, 'ПАО «Аэрофлот') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // associated with parser Booking
        if ($this->detectHtml()) {
            return false;
        }

        $htmlPdfFull = '';
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf|[^\.]');

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);

            if ($this->assignLang($htmlPdf)) {
                $htmlPdfFull .= $htmlPdf;
                $textPdfFull .= $textPdf;
            }
        }

        if ($htmlPdfFull === '') {
            return false;
        }

        //		$this->pdf = clone $this->http;
        //		$this->pdf->SetEmailBody($htmlPdfFull);

        $its = $this->parsePdf($htmlPdfFull, $textPdfFull);

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'ETicketItineraryReceiptPDF' . ucfirst($this->lang),
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

    private function parsePdf($htmlPdf, $textPdforig)
    {
        $htmlPdf = preg_replace('/<br *\/?>/i', "\n", $htmlPdf);
        $textPdf = preg_replace('/<[A-z\/!][^>]*>/', '', $htmlPdf);
        $textPdf = str_replace("\n\n", "\n", $textPdf);

        $itineraries = $this->splitText($textPdf, $this->t('itSplitPattern'));
        $itineraries2 = $this->splitText($textPdforig, $this->t('itSplitPattern'));

        if (empty($itineraries) || count($itineraries) !== count($itineraries2)) {
            $this->logger->debug('seems other format');

            return false;
        }

        $its = [];

        foreach ($itineraries as $i=>$itinerary) {
            if (($itFlight = $this->parseItinerary($itinerary, $itineraries2[$i])) === false) {
                continue;
            }

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                if (!empty($itFlight['Passengers'][0])) {
                    if (!empty($its[$key]['Passengers'][0])) {
                        $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    } else {
                        $its[$key]['Passengers'] = $itFlight['Passengers'];
                    }
                }

                if (!empty($itFlight['TicketNumbers'][0])) {
                    if (!empty($its[$key]['TicketNumbers'][0])) {
                        $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    } else {
                        $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                    }
                }

                if (!empty($itFlight['AccountNumbers'][0])) {
                    if (!empty($its[$key]['AccountNumbers'][0])) {
                        $its[$key]['AccountNumbers'] = array_merge($its[$key]['AccountNumbers'], $itFlight['AccountNumbers']);
                        $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                    } else {
                        $its[$key]['AccountNumbers'] = $itFlight['AccountNumbers'];
                    }
                }
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);

                if (!empty($itFlight['Currency']) && $itFlight['TotalCharge'] !== null) {
                    if (!empty($its[$key]['Currency']) && $its[$key]['TotalCharge'] !== null) {
                        if ($itFlight['Currency'] === $its[$key]['Currency']) {
                            $its[$key]['TotalCharge'] += $itFlight['TotalCharge'];
                        }
                    }
                }
            } else {
                $its[] = $itFlight;
            }
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    private function parseItinerary($text, $text2)
    {// $text2 for parse totalcharge
        $patterns = [
            'time'     => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?', // 15:40
            'airport'  => '[A-Z]{3}', // SVO
            'terminal' => '[ ]*[A-Z\d]+', // D
        ];

        $it = [];
        $it['Kind'] = 'T';

        $endHeader = mb_stripos($text, "\n" . $this->t('Itinerary') . "\n");

        if ($endHeader !== false) {
            $textHeader = mb_substr($text, 0, $endHeader);
        } else {
            $this->logger->debug('not found textHeader');

            return false;
        }

        // Passengers
        if (preg_match('/^\s*([A-Z][-.\'A-Z\s]+[A-Z]\b)/', $textHeader, $matches)) {
            $it['Passengers'] = [$matches[1]];
        }

        // RecordLocator
        if (preg_match('/^\s*' . $this->t('Booking code') . '\s*\*?\s*$.+?^\s*([A-Z\d]{5,6})\s*$/mis', $textHeader, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        // TicketNumbers
        if (preg_match('/^\s*' . $this->t('E-ticket number') . '\s*$.+?^\s*(\d{13})\s*$/mis', $textHeader, $matches)) {
            $it['TicketNumbers'] = [$matches[1]];
        } elseif (preg_match('/^\s*' . $this->t('E-ticket number') . '\s*$.+?^[ ]*.+?\s+(\d{13})\b/mis', $textHeader, $matches)) {
            $it['TicketNumbers'] = [$matches[1]];
        }

        // AccountNumbers
        if (preg_match('/^\s*' . $this->t('E-ticket number') . '\s*$.+?^\s*\d{13}[ ]*\n[ ]*(\d{7,})/mis', $textHeader, $matches)) {
            $it['AccountNumbers'] = [$matches[1]];
        } elseif (preg_match('/^\s*' . $this->t('E-ticket number') . '\s*$.+?^[ ]*.+?\s+\d{13}\b[ ]+(\d{7,})/mis', $textHeader, $matches)) {
            $it['AccountNumbers'] = [$matches[1]];
        }

        // TripSegments
        $segments = $this->splitText($text, '/^\s*' . $this->t('Flight') . ':?\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+(?:[ ]*\d+[A-z])?)\s*$/m', true);

        if (empty($segments)) {
            $this->logger->debug('not found segments');

            return false;
        }

        $it['TripSegments'] = [];

        foreach ($segments as $segment) {
            if (preg_match('/^[ ]*' . $this->t('Status') . '[ ]*:[ ]*' . $this->t('Status bad values') . '[ ]*$/m', $segment)) {
                continue;
            }

            $seg = [];

            // AirlineName
            // FlightNumber
            if (preg_match('/^[ ]*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)[ ]*(?:[ ]*(\d+[A-z]))?[ ]*\n/', $segment, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];

                if (isset($matches[3]) && !empty($matches[3])) {
                    $seg['Seats'][] = $matches[3];
                }
            }

            $dateDep = 0;
            $dateArr = 0;

            if (preg_match('/^[ ]*(?<dateDep>' . $this->t('datePattern') . ')[ ]*$\s+^[ ]*(?<dateArr>' . $this->t('datePattern') . ')[ ]*$\s+^[ ]*' . $this->t('Carrier') . '/m', $segment, $matches)) {
                if ($dateDepNormal = $this->normalizeDate($matches['dateDep'])) {
                    $dateDep = $dateDepNormal;
                } else {
                    $dateDep = $matches['dateDep'];
                }

                if ($dateArrNormal = $this->normalizeDate($matches['dateArr'])) {
                    $dateArr = $dateArrNormal;
                } else {
                    $dateArr = $matches['dateArr'];
                }
            }

            // Operator
            if (preg_match('/^[ ]*' . $this->t('Carrier') . '[ ]*:[ ]*([^:\n]+\b)/m', $segment, $matches)) {
                $seg['Operator'] = $matches[1];
            }

            // Aircraft
            if (preg_match('/^[ ]*' . $this->t('Aircraft type') . '[ ]*:[ ]*([^:\n]+\b)/m', $segment, $matches)) {
                $seg['Aircraft'] = $matches[1];
            }

            // DepDate
            // ArrDate
            // DepCode
            // ArrCode
            // DepartureTerminal
            // ArrivalTerminal
            $pattern = '/'
                . '^[ ]*(?<timeDep>' . $patterns['time'] . ')[ ]+(?<airportDep>' . $patterns['airport'] . ')(?<terminalDep>' . $patterns['terminal'] . ')?[ ]*$\s+'
                . '^.*(?<airportArr>' . $patterns['airport'] . ')(?<terminalArr>' . $patterns['terminal'] . ')?[ ]+(?<timeArr>' . $patterns['time'] . ')'
                . '/m';

            if (preg_match($pattern, $segment, $matches)) {
                if ($dateDep) {
                    $seg['DepDate'] = strtotime($dateDep . ', ' . $matches['timeDep']);
                }

                if ($dateArr) {
                    $seg['ArrDate'] = strtotime($dateArr . ', ' . $matches['timeArr']);
                }
                $seg['DepCode'] = $matches['airportDep'];
                $seg['ArrCode'] = $matches['airportArr'];

                if (!empty($matches['terminalDep'])) {
                    $seg['DepartureTerminal'] = trim($matches['terminalDep']);
                }

                if (!empty($matches['terminalArr'])) {
                    $seg['ArrivalTerminal'] = trim($matches['terminalArr']);
                }
            }

            // Cabin
            // BookingClass
            if (preg_match('/^[ ]*' . $this->t('Class') . '[ ]*:[ ]*([^:\n]+\b)/m', $segment, $matches)) {
                if (preg_match('/(.+)[ ]*\/[ ]*([A-Z]{1,2})$/', $matches[1], $m)) { // Economy / T
                    $seg['Cabin'] = $m[1];
                    $seg['BookingClass'] = $m[2];
                } else {
                    $seg['Cabin'] = $matches[1];
                }
            }

            // Duration
            if (preg_match('/^[ ]*' . $this->t('Duration') . '[ ]*:[ ]*(.+)/m', $segment, $matches)) { // Duration: 12 hrs 10 mins
                $seg['Duration'] = $matches[1];
            }

            $it['TripSegments'][] = $seg;
        }

        if (count($it['TripSegments']) === 0) {
            return false;
        }

        // Currency
        // TotalCharge
        $startPayment = strpos($text, "\n" . $this->t('Payment amount') . "\n");
        $endPayment = strpos($text, "\n" . $this->t('Service type') . "\n");

        if ($startPayment === false || $endPayment === false) {
            if (preg_match("#{$this->t('Payment amount2')}[ ]*\n[^\n]+?[ ]+([A-Z][A-Z ]*[A-Z])[ ]+(\d[,.\d ]*)[ ]*\n#",
                $text2, $m)) {
                $it['Currency'] = trim($m[1]);
                $it['TotalCharge'] = (float) $this->normalizePrice($m[2]);
            } elseif (preg_match("#{$this->t('Payment amount2')}[ ]*\n.+\n[^\n]+?[ ]+([A-Z][A-Z ]*[A-Z])[ ]+(\d[,.\d ]*)[ ]*\n#",
                $text2, $m)) {
                $it['Currency'] = trim($m[1]);
                $it['TotalCharge'] = (float) $this->normalizePrice($m[2]);
            } elseif (preg_match('/Amount paid and payment method\s*.*\s*([A-Z]{3})[ ]+([\d\.]+)/', $text2, $m)) {
                $it['Currency'] = $m[1];
                $it['TotalCharge'] = $m[2];
            }
        } else {
            $textPayment = substr($text, $startPayment, $endPayment - $startPayment);
            // USD 15677.00
            if (preg_match_all('/^\s*([A-Z][A-Z ]*[A-Z])[ ]+(\d[,.\d ]*)\s*$/m', $textPayment, $paymentMatches, PREG_SET_ORDER)) {
                $matches = $paymentMatches[count($paymentMatches) - 1];
                $it['Currency'] = trim($matches[1]);
                $it['TotalCharge'] = (float) $this->normalizePrice($matches[2]);
            }
        }

        return $it;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                $condition1 = $segment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $uniqueSegment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $segment['FlightNumber'] === $uniqueSegment['FlightNumber'];
                $condition2 = $segment['DepCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['DepCode'] !== TRIP_CODE_UNKNOWN && $segment['DepCode'] === $uniqueSegment['DepCode']
                    && $segment['ArrCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['ArrCode'] !== TRIP_CODE_UNKNOWN && $segment['ArrCode'] === $uniqueSegment['ArrCode'];
                $condition3 = $segment['DepDate'] !== MISSING_DATE && $uniqueSegment['DepDate'] !== MISSING_DATE && $segment['DepDate'] === $uniqueSegment['DepDate'];

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    private function normalizeDate($string = '')
    {
        if (preg_match('/^(\d{1,2})[. ]+(\D+)[ ]+(\d{4})/', $string, $matches)) { // 3 апреля 2018 г.
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function detectHtml()
    {
        foreach ($this->htmlDetectors as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
