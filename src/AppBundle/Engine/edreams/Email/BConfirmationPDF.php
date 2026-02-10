<?php

namespace AwardWallet\Engine\edreams\Email;

class BConfirmationPDF extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "edreams/it-5950756.eml, edreams/it-5960067.eml";

    protected $pdf;

    protected $lang = null;

    protected $langDetectors = [
        'en' => [
            'eDreams reference',
            'eDreams easier for you',
            'contact with eDreams by calling',
        ],
        'es' => [
            'Número de referencia de eDreams',
            'eDreams se reserva el derecho',
            'Gracias por haber confiado en eDreams',
            'eDreams te desea un feliz viaje',
            'Ahora eDreams también te gestiona',
            'eDreams sólo realizará por su parte',
            'reserva en eDreams estás aceptando',
        ],
    ];

    protected $dict = [
        'bookingListStart' => [
            'en' => ['booking number'],
            'es' => ['Referencias de la reserva'],
        ],
        'withAirline' => [
            'en' => ['with'],
            'es' => ['con'],
        ],
        'bookingListEnd' => [
            'en' => ['flight information'],
            'es' => ['Detalles del viaje'],
        ],
        'departure' => [
            'en' => ['Departure'],
            'es' => ['Salida'],
        ],
        'arrival' => [
            'en' => ['Arrival'],
            'es' => ['Llegada'],
        ],
        'recordLocator' => [
            'en' => ['Flight:'],
            'es' => ['Número de referencia de eDreams'],
        ],
        'pendingText' => [
            'en' => ['Pending Reservation'],
            'es' => ['Reserva Pendiente'],
        ],
        'passengers' => [
            'en' => ['Travelling'],
            'es' => ['Pasajeros'],
        ],
        'passengerMarker' => [
            'en' => ['adult'],
            'es' => ['Adulto'],
        ],
        'totalPayment' => [
            'en' => ['The total costs for the reservation is', 'The total cost of your reservation is'],
            'es' => ['Coste total de tu reserva'],
        ],
    ];

    protected $allFlightSegments = [];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'serviceclient-uk@edreams.com') !== false
            || stripos($headers['from'], 'serviciocliente@edreams.com') !== false
            || preg_match('/eDreams.+booking/i', $headers['subject'])
            || preg_match('/VALORACION.+EDREAMS/i', $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@edreams.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'edreams.com') === false) {
                continue;
            }

            foreach ($this->langDetectors as $lines) {
                foreach ($lines as $line) {
                    if (stripos($textPdf, $line) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = pdfHtmlHtmlTable(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $htmlPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $htmlPdf);
            $this->pdf = clone $this->http;
            $this->pdf->SetBody($htmlPdf);

            foreach ($this->langDetectors as $lang => $lines) {
                foreach ($lines as $line) {
                    if ($this->pdf->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0) {
                        $this->lang = $lang;
                        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

                        return $this->ParsePdf();
                    }
                }
            }
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'es'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function translate($index)
    {
        $result = $this->dict[$index][$this->lang];

        return $result ?? null;
    }

    protected function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function getAllFlightSegments()
    {
        $depList = [];
        $depCells = $this->pdf->XPath->query('//td[normalize-space(.//text())="' . $this->translate('departure')[0] . '" and .//br and contains(.,":") and not(.//td)]');

        foreach ($depCells as $key => $depCell) {
            if (preg_match('/(\d{1,2}:\d{2})[^\d]+(\d{1,2}\s+[^\d\s]{3,})\s*$/', $depCell->nodeValue, $matches)) {
                $dateDep = strtotime($this->dateStringToEnglish($matches[2]) . ' ' . $this->year);
                $depList[$key]['DepDate'] = strtotime($matches[1], $dateDep);
            }
            $nextCells = $this->pdf->XPath->query('./following::td[normalize-space(.)!="" and not(.//td) and ./following::tr[normalize-space(.)="' . $this->translate('passengers')[0] . '"]]', $depCell);

            foreach ($nextCells as $cell) {
                $cellText = $cell->nodeValue;
                // следующие условия должны быть строго упорядочены "по убыванию приоритетности"
                if (!empty($depList[$key]['DepCode']) && !empty($depList[$key]['AirlineName']) || preg_match('/' . $this->translate('arrival')[0] . '\s*\d{1,2}:\d{2}\s+/', $cellText)) {
                    continue 2;
                } elseif (empty($depList[$key]['DepCode']) && preg_match('/\(([A-Z]{3})\)(?:\s*Terminal\s+([A-Z\d]{1,2})|)\s*$/', $cellText, $matches)) {
                    $depList[$key]['DepCode'] = $matches[1];

                    if (isset($matches[2])) {
                        $depList[$key]['DepartureTerminal'] = $matches[2];
                    }
                } elseif (empty($depList[$key]['AirlineName']) && preg_match('/\s*(\S.+\S)\s*([A-Z\d]{2})\s+(\d+)\s*$/', $cellText, $matches)) {
                    $depList[$key]['_airline'] = $matches[1];
                    $depList[$key]['AirlineName'] = $matches[2];
                    $depList[$key]['FlightNumber'] = $matches[3];
                }
            }
        }
        //		print_r($depList);

        $arrList = [];
        $arrCells = $this->pdf->XPath->query('//td[normalize-space(.//text())="' . $this->translate('arrival')[0] . '" and .//br and contains(.,":") and not(.//td)]');

        foreach ($arrCells as $key => $arrCell) {
            if (preg_match('/(\d{1,2}:\d{2})[^\d]+(\d{1,2}\s+[^\d\s]{3,})\s*$/', $arrCell->nodeValue, $matches)) {
                $dateArr = strtotime($this->dateStringToEnglish($matches[2]) . ' ' . $this->year);
                $arrList[$key]['ArrDate'] = strtotime($matches[1], $dateArr);
            }
            $nextCells = $this->pdf->XPath->query('./following::td[normalize-space(.)!="" and not(.//td) and ./following::tr[normalize-space(.)="' . $this->translate('passengers')[0] . '"]]', $arrCell);

            foreach ($nextCells as $cell) {
                $cellText = $cell->nodeValue;

                if (preg_match('/' . $this->translate('departure')[0] . '\s*\d{1,2}:\d{2}\s+/', $cellText)) {
                    continue 2;
                } elseif (preg_match('/\(([A-Z]{3})\)(?:\s*Terminal\s+([A-Z\d]{1,2})|)\s*$/', $cellText, $matches)) {
                    $arrList[$key]['ArrCode'] = $matches[1];

                    if (isset($matches[2])) {
                        $arrList[$key]['ArrivalTerminal'] = $matches[2];
                    }

                    continue 2;
                }
            }
        }
        //		print_r($arrList);

        if (count($depList) !== count($arrList)) {
            return false;
        }

        foreach ($depList as $key => $segment) {
            $this->allFlightSegments[] = array_merge($segment, $arrList[$key]);
        }

        return count($this->allFlightSegments) ? true : false;
    }

    protected function getBookingList()
    {
        $bookingList = [];
        $bookingRows = $this->pdf->FindNodes('//tr[./preceding-sibling::tr[normalize-space(.)="' . $this->translate('bookingListStart')[0] . '"] and ./following-sibling::tr[normalize-space(.)="' . $this->translate('bookingListEnd')[0] . '"] and not(.//tr)]');

        foreach ($bookingRows as $bookingRow) {
            if (preg_match('/\(([A-Z]{3})\)[^(]+\(([A-Z]{3})\)[,\s]+' . $this->translate('withAirline')[0] . '\s+(\S.+\S)[:\s]+([A-Z\d]{5,7})\s*/', $bookingRow, $matches)) {
                $bookingList[$matches[4]][] = [
                    'DepCode'  => $matches[1],
                    'ArrCode'  => $matches[2],
                    '_airline' => $matches[3],
                ];
            }
        }

        return count($bookingList) ? $bookingList : false;
        // фейковый массив для расширенного тестирования сортировки сегментов (работает только с it-5950756.eml)
//		return [
//			'ZIUPXJ' => [
//				0 => [
//					'DepCode' => 'VIE',
//					'ArrCode' => 'BKK',
//					'_airline' => 'Air China'
//				]
//			],
//			'RWPNKC' => [
//				0 => [
//					'DepCode' => 'BKK',
//					'ArrCode' => 'VIE',
//					'_airline' => 'Air China'
//				]
//			]
//		];
    }

    protected function sortFlightSegments($flights)
    {
        $resultSegments = [];

        foreach ($flights as $flight) {
            $startSegments = -1;
            $endSegments = -1;

            foreach ($this->allFlightSegments as $key => $segment) {
                if ($flight['_airline'] === $segment['_airline'] && $flight['DepCode'] === $segment['DepCode'] && $flight['ArrCode'] === $segment['ArrCode']) {
                    $startSegments = $key;
                    $endSegments = $key;

                    break;
                } elseif ($flight['_airline'] === $segment['_airline'] && $flight['DepCode'] === $segment['DepCode']) {
                    $startSegments = $key;

                    continue;
                } elseif ($flight['_airline'] === $segment['_airline'] && $flight['ArrCode'] === $segment['ArrCode'] && $startSegments >= 0) {
                    $endSegments = $key;

                    break;
                }
            }

            if ($startSegments >= 0 && $endSegments >= 0) {
                $length = $endSegments - $startSegments + 1;
                $resultSegments = array_merge($resultSegments, array_slice($this->allFlightSegments, $startSegments, $length));
                array_splice($this->allFlightSegments, $startSegments, $length);
            }
        }

        foreach ($resultSegments as $key => $resultSegment) {
            unset($resultSegments[$key]['_airline']);
        } // удаляем из итогового массива сегментов вспомагательные поля Airline

        return $resultSegments;
    }

    protected function ParsePdf()
    {
        /*
         * Будут парсится только те PDF'ки, у которых:
         *	1) в сегментах перелётов присутствуют DepDate, ArrDate, DepCode, ArrCode, AirlineName + FlightNumber, Airline
         *	2) в списке бронирования присутствуют DepCode, ArrCode, Airline
         * Иначе, PDF'ку можно считать непригодной для парсинга.
         */

        if (!$this->getAllFlightSegments()) {
            return null;
        }
        //		print_r($this->allFlightSegments);

        $its = [];
        $passengers = $this->pdf->FindNodes('//tr[starts-with(normalize-space(.),"' . $this->translate('passengers')[0] . '") and not(.//tr)]/following-sibling::tr[./td[normalize-space(.)="' . $this->translate('passengerMarker')[0] . '"] and not(.//tr)]/td[normalize-space(.)!=""][1]');

        if ($bookingList = $this->getBookingList()) {
            //			print_r($bookingList);
            foreach ($bookingList as $key => $bookingItem) {
                $it = [];
                $it['Kind'] = 'T';
                $it['RecordLocator'] = $key;
                $it['TripSegments'] = $this->sortFlightSegments($bookingItem);
                $it['Passengers'] = $passengers;
                $its[] = $it;
            }
        } elseif ($recordLocator = $this->pdf->FindSingleNode('(//*[./text()[contains(.,"' . $this->translate('recordLocator')[0] . '")]])[1]', null, true, '/:\s+([-A-Z\d]{5,})/')) {
            $it = [];
            $it['Kind'] = 'T';
            $it['RecordLocator'] = $recordLocator;
            $it['TripSegments'] = $this->allFlightSegments;
            $it['Passengers'] = $passengers;
            $its[] = $it;
        } elseif ($this->pdf->XPath->query('//text()[starts-with(normalize-space(.),"' . $this->translate('pendingText')[0] . '")]')->length > 0) {
            $it = [];
            $it['Kind'] = 'T';
            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripSegments'] = $this->allFlightSegments;
            $it['Passengers'] = $passengers;
            $its[] = $it;
        }

        foreach ($this->translate('totalPayment') as $text) {
            if ($payment = $this->pdf->FindSingleNode('//tr[./td[starts-with(normalize-space(.),"' . $text . '")] and not(.//tr)]', null, true, '/' . $text . '[:\s]+([^:(]+)/')) {
                $payment = preg_replace('/\s+/', '', $payment);

                if (preg_match('/^([,.\d]+)([^(,.\d]*)/', $payment, $matches)) {
                    $amount = $this->priceNormalize($matches[1]);
                    $currency = $matches[2];
                } elseif (preg_match('/([^(,.\d]*)([,.\d]+)$/', $payment, $matches)) {
                    $currency = $matches[1];
                    $amount = $this->priceNormalize($matches[2]);
                }

                break;
            }
        }

        return [
            'parsedData' => [
                'Itineraries' => $its,
                'TotalCharge' => [
                    'Amount'   => $amount,
                    'Currency' => $currency,
                ],
            ],
            'emailType' => 'BConfirmationPDF',
        ];
    }
}
