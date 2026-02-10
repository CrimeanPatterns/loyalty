<?php

namespace AwardWallet\Engine\etihad\Email;

class Itinerary1 extends \TAccountChecker
{
    use \DateTimeTools;

    public const DATE_TIME_FORMAT = 'd M Y H:i';

    public $mailFiles = "etihad/it-1.eml, etihad/it-12234256.eml, etihad/it-2.eml, etihad/it-2567570.eml, etihad/it-2848829.eml, etihad/it-3952717.eml, etihad/it-4599845.eml, etihad/it-4605497.eml, etihad/it-4643030.eml, etihad/it-4693421.eml, etihad/it-4711755.eml, etihad/it-5091839.eml, etihad/it-5116557.eml, etihad/it-8588061.eml";
    public $nameFilePDF = "travel reservation";

    public $reBody = [
        "Thank you for choosing to travel with Etihad Airways",
        "On behalf of the Etihad Airways",
        "Thank you for choosing to fly with Etihad Airways",
        "Vielen Dank, dass Sie sich entschieden haben, mit Etihad Airways",
        "Grazie per aver scelto di viaggiare con Etihad Airways",
        "Nous vous remercions d\'avoir choisi de voyager avec Etihad Airways",
        "The Etihad Airways Staff Travel Team wishes you an enjoyable journey",
        "Благодарим за то, что вы решили путешествовать с авиакомпанией EtihadAirways",
        "Obrigado por escolher viajar com a Etihad Airways",
    ];
    public $lang = 'en';

    public static $dict = [
        'en' => [
            //			'N/A' => '',
            'Reservation code' => ['Reservation code', 'Booking Reference', 'Booking Reference'],
            //			'Passenger(s):' => '',
            //			'Flight' => '',
            //			'TERMINAL' => '',
            //			'Cancelled' => '',
        ],
        'de' => [
            'N/A'              => 'N.Z.',
            'Reservation code' => ['Reservierungscode'],
            'Passenger(s):'    => 'Passagier (-e):',
            'Flight'           => ['Flight', 'Flugnummer'],
            'TERMINAL'         => 'TERMINAL',
            //			'Cancelled' => '',
        ],
        'it' => [
            'N/A'              => 'ND',
            'Reservation code' => 'Codice di prenotazione',
            'Passenger(s):'    => 'Passeggero/i:',
            'Flight'           => 'Voli',
            'TERMINAL'         => 'TERMINAL',
            //			'Cancelled' => '',
        ],
        'fr' => [
            'N/A'              => 'N/A',
            'Reservation code' => 'Numéro de réservation:',
            'Passenger(s):'    => 'Passager(s):',
            'Flight'           => 'Vol',
            'TERMINAL'         => 'TERMINAL',
            //			'Cancelled' => '',
        ],
        'ru' => [
            'N/A'              => '',
            'Reservation code' => 'Код бронирования:',
            'Passenger(s):'    => 'Пассажир(ы):',
            'Flight'           => 'Рейсы',
            'TERMINAL'         => 'TERMINAL',
            //			'Cancelled' => '',
        ],
        'pt' => [
            'N/A'              => 'N/A',
            'Reservation code' => 'Código de reserva',
            'Passenger(s):'    => 'Passageiro(s):',
            'Flight'           => 'N. do Voo',
            'TERMINAL'         => 'TERMINAL',
            //			'Cancelled' => '',
        ],
        'ko' => [
            'N/A'              => '해당 없음',
            'Reservation code' => '예약 번호:',
            'Passenger(s):'    => '승객:',
            'Flight'           => '항공 편명',
            'TERMINAL'         => 'TERMINAL',
            //			'Cancelled' => '',
        ],
        'ja' => [
            //			'N/A' => '',
            'Reservation code' => '予約コード',
            'Passenger(s):'    => '乗客:',
            //			'Flight' => '',
            //			'TERMINAL' => 'TERMINAL',
            //			'Cancelled' => '',
        ],
    ];

    private $flightSeats = [];

    public function it_1eml()
    {
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Reservation code')) . "][1]", null, true, "#:\s*([A-Z\d]+)#");

        $ticketNumbers = [];
        $passengers_info = $this->http->XPath->query("//text()[" . $this->contains($this->t('Passenger(s):')) . "]/ancestor::tr[1]/following-sibling::tr[./td[3]]");

        foreach ($passengers_info as $passenger_info) {
            $it['Passengers'][] = $this->http->FindSingleNode('./td[1]', $passenger_info);
            $ticketNumberTexts = $this->http->FindNodes('./td[2]/descendant::text()[string-length(normalize-space(.))>2]', $passenger_info, '/^[-\d\s\/]+$/');
            $ticketNumberValues = array_values(array_filter($ticketNumberTexts));

            if (count($ticketNumberValues)) {
                $ticketNumbers = array_merge($ticketNumbers, $ticketNumberValues);
            }
            $this->flightSeats[] = $this->http->FindSingleNode('./td[3]', $passenger_info);
        }

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = array_unique($ticketNumbers);
        }

        // --------------------- TRIP SEGMENTS ----------------------

        $it['TripSegments'] = [];
        $xpath = '//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]/descendant::tr[normalize-space(.) and td[3]]';
        $tripSegments = $this->http->XPath->query($xpath);

        if ($tripSegments->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }
        $i = 0;

        foreach ($tripSegments as $segment) {
            $seg = [];
            $flight = $this->http->FindSingleNode('./td[4]', $segment);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
                $seg['DepName'] = $this->http->FindSingleNode('./td[2]//text()[normalize-space(.)][1]', $segment);
                $seg['ArrName'] = $this->http->FindSingleNode('./td[3]//text()[normalize-space(.)][1]', $segment);
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $timeDep = $this->http->FindSingleNode('./td[2]//text()[normalize-space(.)][2]', $segment);
                $timeArr = $this->http->FindSingleNode('./td[3]//text()[normalize-space(.)][2]', $segment);
                $dateDepArr = $this->http->FindSingleNode('./td[1]', $segment);

                if (stripos($dateDepArr, '-') !== false) {
                    if (preg_match('/^([^-]+)\s+\-\s+([^-]+)$/', $dateDepArr, $matches)) {
                        $dateDep = strtotime($this->dateStringToEnglish($matches[1] . ', ' . $timeDep));
                        $dateArr = strtotime($this->dateStringToEnglish($matches[2] . ', ' . $timeArr));

                        if (empty($dateDep) || empty($dateArr)) {
                            $dateDep = $this->correctDate($matches[1], $timeDep);
                            $dateArr = $this->correctDate($matches[2], $timeArr);
                        }
                    }
                } else {
                    $dateDep = strtotime($this->dateStringToEnglish($dateDepArr . ' ' . $timeDep));
                    $dateArr = strtotime($this->dateStringToEnglish($dateDepArr . ' ' . $timeArr));

                    if (empty($dateDep) || empty($dateArr)) {
                        $dateDep = $this->correctDate($dateDepArr, $timeDep);
                        $dateArr = $this->correctDate($dateDepArr, $timeArr);
                    }
                }

                if (isset($dateDep)) {
                    $seg['DepDate'] = $dateDep;
                }

                if (isset($dateArr)) {
                    $seg['ArrDate'] = $dateArr;
                }
                $terminalDep = $this->http->FindSingleNode('./td[2]//text()[3]', $segment);

                if (strpos($terminalDep, $this->t('TERMINAL')) !== false) {
                    $seg['DepartureTerminal'] = trim(str_replace($this->t('TERMINAL'), ' ', $terminalDep));
                }
                $terminalArr = $this->http->FindSingleNode('./td[3]//text()[3]', $segment);

                if (strpos($terminalArr, $this->t('TERMINAL')) !== false) {
                    $seg['ArrivalTerminal'] = trim(str_replace($this->t('TERMINAL'), ' ', $terminalArr));
                }
                $seg['Cabin'] = $this->http->FindSingleNode('./td[5]/descendant::text()[string-length(normalize-space(.))>1][2]', $segment);

                if ($seats = $this->_getFlightSeats($i)) {
                    $seg['Seats'] = $seats;
                }
            }
            $i++;
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    public function it_2eml()
    {
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Reservation code')) . "][1]", null, true, "#:\s*([A-Z\d]+)#");

        $ticketNumbers = [];
        $passengers_info = $this->http->XPath->query("//text()[" . $this->contains($this->t('Passenger(s):')) . "]/ancestor::tr[1]/following-sibling::tr[./td[3]]");

        foreach ($passengers_info as $passenger_info) {
            $itineraries['Passengers'][] = $this->http->FindSingleNode('./td[1]', $passenger_info);
            $ticketNumberTexts = $this->http->FindNodes('./td[2]/descendant::text()[string-length(normalize-space(.))>2]', $passenger_info, '/^[-\d\s\/]+$/');
            $ticketNumberValues = array_values(array_filter($ticketNumberTexts));

            if (count($ticketNumberValues)) {
                $ticketNumbers = array_merge($ticketNumbers, $ticketNumberValues);
            }
            $Seats = explode(', ', $this->http->FindSingleNode('./td[3]', $passenger_info));
        }

        if (!empty($ticketNumbers[0])) {
            $itineraries['TicketNumbers'] = array_unique($ticketNumbers);
        }

        // --------------------- TRIP SEGMENTS ----------------------

        $c = 0;
        $tripSegments = $this->http->FindNodes('//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]//tr');

        for ($i = 1; $i <= count($tripSegments); $i++) {
            if (count($this->http->FindNodes('//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]//tr[' . $i . ']/td')) > 2) {
                $flight = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]//tr[' . $i . ']/td[4]');

                if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                    $itineraries['TripSegments'][$c]['AirlineName'] = $matches[1];
                    $itineraries['TripSegments'][$c]['FlightNumber'] = $matches[2];
                }
                $dateDepArr[$c] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]//tr[' . $i . ']/td[1]');

                if (stripos($dateDepArr[$c], '-') !== false) {
                    if (preg_match('/^([^-]+)\s+\-\s+([^-]+)$/', $dateDepArr[$c], $matches)) {
                        $dateDep[$c] = $matches[1];
                        $dateArr[$c] = $matches[2];
                    }
                } else {
                    $dateDep[$c] = $dateArr[$c] = $dateDepArr[$c];
                }
                $From[$c] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]//tr[' . $i . ']/td[2]');
                $To[$c] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]//tr[' . $i . ']/td[3]');
                $Status[$c] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Flight')) . ']/ancestor::tr[3]/following-sibling::tr[1]//tr[' . $i . ']/td[5]');

                if (isset($Seats[$c]) && $Seats[$c] !== $this->t('N/A')) {
                    $itineraries['TripSegments'][$c]['Seats'] = $Seats[$c];
                }

                if (preg_match('/(.*?)\s*(\d*\:\d*)(.*)/', $From[$c], $matches)) {
                    $itineraries['TripSegments'][$c]['DepName'] = $matches[1];
                    $itineraries['TripSegments'][$c]['DepDate'] = strtotime($dateDep[$c] . ' ' . $matches[2]);

                    if (strpos($matches[3], $this->t('TERMINAL')) !== false) {
                        $itineraries['TripSegments'][$c]['DepartureTerminal'] = $matches[3];
                    }
                    $itineraries['TripSegments'][$c]['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (preg_match('/(.*?)\s*(\d*\:\d*)(.*)/', $To[$c], $matches)) {
                    $itineraries['TripSegments'][$c]['ArrName'] = $matches[1];
                    $itineraries['TripSegments'][$c]['ArrDate'] = strtotime($dateArr[$c] . ' ' . $matches[2]);

                    if (strpos($matches[3], $this->t('TERMINAL')) !== false) {
                        $itineraries['TripSegments'][$c]['ArrivalTerminal'] = $matches[3];
                    }
                    $itineraries['TripSegments'][$c]['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                if (preg_match('/(\w+)\s*(\w+)/', $Status[$c], $matches)) {
                    $itineraries['TripSegments'][$c]['Cabin'] = $matches[2];

                    if ($matches[2] === $this->t('Cancelled')) {
                        unset($itineraries['TripSegments'][$c]);
                    }
                }
                $c++;
            }
        }

        return $itineraries;
    }

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reply@etihad.ae') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName("(?:{$this->nameFilePDF}).*pdf");

        if (isset($pdf[0])) {
            return false; // go to parse by: ctraveller:TravelReservationPDF or ethiopian:TravelReservationPDF
        }

        if (($this->http->XPath->query('//a[contains(@href,"www.etihad.com") or contains(@href,"www.etihadairways.com")]')->length == 0)
                || ($this->http->XPath->query('//img[contains(@src,"www.etihad.com") or contains(@src,"sswassets.etihad.com") or contains(@src,"etihadguest.com")]')->length == 0)
                ) {
            return false;
        }

        foreach ($this->reBody as $key => $reBody) {
            if ($this->http->XPath->query('//text()[contains(normalize-space(.),"' . $reBody . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@etihad.ae') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = [];

        $body = $this->http->Response['body'];

        foreach (self::$dict as $lang => $value) {
            if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($value['Reservation code']) . "][1]", null, true, "#:\s*([A-Z\d]+)#"))) {
                $this->lang = $lang;

                break;
            }
        }

        if (stripos($body, 'Chauffeur service booked for ') !== false) {
            $emailType = 2;
            $it = $this->it_2eml();
        } else {
            $emailType = 1;
            $it = $this->it_1eml();
        }

        return [
            'emailType'  => 'TicketReceipt' . $emailType . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'de', 'it', 'fr', 'ru', 'pt', 'ko'];
    }

    public static function getEmailTypesCount()
    {
        return 6;
    }

    private function _getFlightSeats($index)
    {
        $resultSeats = '';

        foreach ($this->flightSeats as $seats) {
            $seats = explode(', ', $seats);

            if (isset($seats[$index]) && $seats[$index] !== $this->t('N/A')) {
                $resultSeats .= $seats[$index] . ', ';
            }
        }

        return substr($resultSeats, 0, -2);
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    /**
     * ex: 11 month 17 day 2014 - 11 month 18 day 2014, 11 month 17 day 2014.
     *
     * @param $strDate
     * @param $strTime
     *
     * @return int|null
     */
    private function correctDate($strDate, $strTime)
    {
        if (preg_match('/(?<Month>\d{2}) \D+ (?<Day>\d{2}) \D+ (?<Year>\d{4})/', $strDate, $m)) {
            return strtotime($m['Month'] . '/' . $m['Day'] . '/' . $m['Year'] . ' ' . $strTime);
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field));
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }
}
