<?php

namespace AwardWallet\Engine\czech\Email;

class CheckBooking extends \TAccountChecker
{
    public $mailFiles = "czech/it-10313396.eml, czech/it-10387103.eml";

    protected $lang = '';
    protected $langDetectors = [
        'en' => ['Reservation status:', "Payment status:"],
        'cs' => ['Status rezervace:'],
        'es' => ['Estado de reserva:'],
    ];

    protected static $dict = [
        'en' => [
            //			"Booking code:" => "",
            "Reservation status:" => ["Reservation status:", "Payment status:"],
            "Departure:"          => ["Departure:", "Return:"],
            //			"From" => "",
            //			"To" => "",
            //			"Passenger:" => "",
        ],
        'cs' => [
            "Booking code:"       => "Rezervační kód:",
            "Reservation status:" => "Status rezervace:",
            "Departure:"          => ["Odlet:"],
            "From"                => "Odkud",
            "To"                  => "Kam",
            "Passenger:"          => "Cestující:",
        ],
        'es' => [
            "Booking code:"       => ["Código de la reserva:", "CГіdigo de la reserva:"],
            "Reservation status:" => "Estado de reserva:",
            "Departure:"          => ["Salida:", "Regreso:"],
            "From"                => "De",
            "To"                  => "A",
            "Passenger:"          => "Pasajero:",
        ],
    ];
    private $detectSubject = [
        "en"  => "Flight itinerary",
        "en2" => "check booking",
        "en3" => "Itinerario voli",
        "cs"  => "Itinerář",
        "es"  => "Itinerario de vuelo",
    ];
    private $airline;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@csa.cz') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Czech airlines') !== false) {
            foreach ($this->detectSubject as $detectSubject) {
                if (stripos($headers['subject'], $detectSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        if (!self::detectEmailFromProvider($from) && !self::detectEmailByHeaders(['from' => $from, 'subject' => $subject])) {
            return false;
        }

        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $textBody = str_replace([chr(194) . chr(160), '&nbsp;'], ' ', $textBody);
        $textBody = str_replace('&#1042;', '', $textBody);

        return $this->assignLang($textBody);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $textBody = str_replace([chr(194) . chr(160), '&nbsp;'], ' ', $textBody);
        $textBody = preg_replace("#([^А-Яа-я])В(?= |$)#", '$1 ', $textBody);

        if ($this->assignLang($textBody) === false) {
            return false;
        }

        if (self::detectEmailByBody($parser)) {
            $this->airline = 'OK';
        } //IATA CSA Czech Airlines

        $it = $this->parseEmail($textBody);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'checkBooking_' . $this->lang,
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

    protected function parseEmail($text)
    {
        if (is_array($this->t('Booking code:'))) {
            foreach ($this->t('Booking code:') as $booking) {
                $start = strpos($text, $booking);

                if ($start !== false) {
                    break;
                }
            }
        } else {
            $start = strpos($text, $this->t('Booking code:'));
        }

        if (is_array($this->t('Reservation status:'))) {
            foreach ($this->t('Reservation status:') as $booking) {
                $end = strpos($text, $booking, $start);

                if ($end !== false) {
                    break;
                }
            }
        } else {
            $end = strpos($text, $this->t('Reservation status:'), $start);
        }

        if ($start === false || $end === false) {
            return false;
        }
        $text = substr($text, $start, $end - $start);

        $patterns = [
            'date' => '\d{1,2}\.\d{1,2}\.\d{2,4}', // 15.8.2016
            'time' => '\d{1,2}:\d{1,2}(?:[ ]*[AaPp][Mm])?', // 15:35
        ];

        $it = [];
        $it['Kind'] = 'T';

        if (preg_match('/^[>\s]*' . $this->preg_implode($this->t("Booking code:")) . '\s*([A-Z\d]{5,})\b/m', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        $it['TripSegments'] = [];
        $segments = $this->splitText($text, '/^[>\s]*' . $this->preg_implode($this->t("Departure:")) . '/mi');

        if (count($segments) === 0) {
            return false;
        }

        foreach ($segments as $segmentText) {
            $seg = [];

            // From: Helsinki (HEL) 15.8.2016 15:35
            if (preg_match('/\b' . $this->preg_implode($this->t("From")) . '\s*:\s*(?<airport>.+)[ ]+(?<date>' . $patterns['date'] . ')[ ]+(?<time>' . $patterns['time'] . ')/', $segmentText, $matches)) {
                if (preg_match('/(.+)\(([A-Z]{3})\)$/', trim($matches['airport']), $m)) {
                    $seg['DepName'] = trim($m[1]);
                    $seg['DepCode'] = $m[2];
                } else {
                    $seg['DepName'] = $matches['airport'];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
                $seg['DepDate'] = strtotime($matches['date'] . ', ' . $matches['time']);
            }

            // To: Helsinki (HEL) 15.8.2016 15:35
            if (preg_match('/\b' . $this->preg_implode($this->t("To")) . '\s*:\s*(?<airport>.+)[ ]+(?<date>' . $patterns['date'] . ')[ ]+(?<time>' . $patterns['time'] . ')/', $segmentText, $matches)) {
                if (preg_match('/(.+)\(([A-Z]{3})\)$/', trim($matches['airport']), $m)) {
                    $seg['ArrName'] = trim($m[1]);
                    $seg['ArrCode'] = $m[2];
                } else {
                    $seg['ArrName'] = $matches['airport'];
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
                $seg['ArrDate'] = strtotime($matches['date'] . ', ' . $matches['time']);
            }

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }

            $seg['AirlineName'] = $this->airline;

            $it['TripSegments'][] = $seg;
        }

        // Passenger: MR TADATAKA SAITO
        if (preg_match_all('/^[>\s]*' . $this->preg_implode($this->t("Passenger:")) . '\s*([^:\n]{2,})/mi', $segments[count($segments) - 1], $matches)) {
            $it['Passengers'] = array_unique(array_map('trim', $matches[1]));
        }

        return $it;
    }

    protected function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
