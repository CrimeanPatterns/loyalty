<?php

namespace AwardWallet\Engine\edreams\Email;

class AirPlainText extends \TAccountChecker
{
    public $mailFiles = "edreams/it-4009622.eml, edreams/it-6150429.eml, edreams/it-6150431.eml";

    public $reBody = [
        'en' => ['Travel Itinerary', 'eDreams'],
        'es' => ['Detalles del viaje', 'eDreams'],
        'it' => ['Dettagli di viaggio', 'eDreams'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Passengers' => 'Full Name',
            'Phone'      => 'Phone number',
            'Total'      => 'The total cost of your reservation is',
        ],
        'es' => [
            'Booking references'  => 'Números de confirmación',
            'Pending'             => 'Pendiente',
            'Pending Reservation' => 'Reserva Pendiente',
            'Flight'              => 'Vuelo',
            'Departure'           => 'Salida',
            'Arrival'             => 'Llegada',
            'Class'               => 'Clase',
            'Passengers'          => 'Nombre y apellidos',
            'Phone'               => 'Teléfono',
            'Total'               => 'El precio total de tu reservación es de',
        ],
        'it' => [
            'Booking references'  => 'Numeri di conferma',
            'Pending'             => 'In attesa',
            'Pending Reservation' => 'Prenotazione in attesa',
            'Flight'              => 'Volo',
            'Departure'           => 'Partenza',
            'Arrival'             => 'Arrivo',
            'Class'               => 'Classe',
            'Passengers'          => 'Nome',
            'Phone'               => 'Numero/i di telefono',
            'Total'               => 'Il costo totale della tua prenotazione',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'urgentefindesemana@edreams.com') !== false
            || stripos($headers['from'], 'recuperolc@edreams.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && strpos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@edreams.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && strpos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                } else {
                    $this->lang = 'en';
                }
            }
        }
        $textBody = empty($parser->getPlainBody()) ? text($parser->getHTMLBody()) : $parser->getPlainBody();
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
        $its = $this->parseEmail($textBody);

        return [
            'parsedData' => ['Itineraries' => $its],
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

    protected function parseSegment($textSegment)
    {
        $segmentParts = explode($this->t('Arrival'), $textSegment);

        if (count($segmentParts) !== 2) {
            return false;
        }
        $seg = [];
        $patterns = [
            'code' => '/\(([A-Z]{3})\)[^\n]*$/m',
            'date' => '/^[>\s]*(\d{1,2}:\d{2})[^\d\n]+(\d{1,2})\s+([^\d]{3})/m',
        ];

        if (preg_match('/^[>\s]*([A-Z\d]{2})\s+(\d+)/m', $segmentParts[0], $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        if (preg_match($patterns['code'], $segmentParts[0], $matches)) {
            $seg['DepCode'] = $matches[1];
        }

        if (preg_match($patterns['code'], $segmentParts[1], $matches)) {
            $seg['ArrCode'] = $matches[1];
        }

        if (preg_match($patterns['date'], $segmentParts[0], $matches)) {
            $seg['DepDate'] = strtotime($matches[2] . ' ' . ($this->lang !== 'en' ? \AwardWallet\Engine\MonthTranslate::translate($matches[3], $this->lang) : $matches[3]) . ' ' . $this->year . ' ' . $matches[1]);
        }

        if (preg_match($patterns['date'], $segmentParts[1], $matches)) {
            $seg['ArrDate'] = strtotime($matches[2] . ' ' . ($this->lang !== 'en' ? \AwardWallet\Engine\MonthTranslate::translate($matches[3], $this->lang) : $matches[3]) . ' ' . $this->year . ' ' . $matches[1]);
        }

        return $seg;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function parseEmail($textBody)
    {
        $textBody = preg_replace('/<[^>]+>/', "\n", $textBody);

        $it = ['Kind' => 'T', 'TripSegments' => []];

        if ($this->http->XPath->query('//*[starts-with(normalize-space(.),"' . $this->t('Booking references') . '") and ./text()[contains(.,"' . $this->t('Pending') . '")]]')->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        } elseif ($this->http->XPath->query('//*[./text()[normalize-space(.)="' . $this->t('Pending Reservation') . '"]]')->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        } elseif ($recordLocator = $this->http->FindSingleNode('//*/text()[starts-with(normalize-space(.),"' . $this->t('Flight') . ':")]', null, true, '/:\s*([-A-Z\d]{5,})/')) {
            $it['RecordLocator'] = $recordLocator;
        }

        $it['Passengers'] = $this->http->FindNodes("//text()[contains(.,'" . $this->t('Passengers') . "') and not(contains(., '" . $this->t('Phone') . "'))]/following-sibling::text()[normalize-space(.)!=''][1]");
        $total = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Total') . "')]/following-sibling::text()[normalize-space(.)!=''][1]");
        $it['TotalCharge'] = cost($total);
        $it['Currency'] = currency($total);

        $reservDate = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t('date of booking') . "')]");

        if (preg_match("#" . $this->t('date of booking') . "\s*:\s*[\w|\D]+\s+(\d{1,2})\s+(\w+),\s+(\d{4})#", $reservDate, $math)) {
            $it['ReservationDate'] = strtotime(($this->lang !== 'en' ? \AwardWallet\Engine\MonthTranslate::translate($math[2], $this->lang) : $math[2]) . ' ' . $math[1] . ' ' . $math[3]);
        }

        $it['TripSegments'] = [];
        preg_match_all('/' . $this->t('Departure') . '\s*\d{1,2}:\d{2}.+?' . $this->t('Class') . '\s+-/uis', $textBody, $matchesSegments, PREG_SET_ORDER);

        foreach ($matchesSegments as $textSegment) {
            $it['TripSegments'][] = $this->parseSegment($textSegment[0]);
        }

        return [$it];
    }
}
