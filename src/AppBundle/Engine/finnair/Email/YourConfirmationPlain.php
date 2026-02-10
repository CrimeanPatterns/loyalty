<?php

namespace AwardWallet\Engine\finnair\Email;

use AwardWallet\Engine\MonthTranslate;

class YourConfirmationPlain extends \TAccountChecker
{
    public $mailFiles = "finnair/it-10454380.eml, finnair/it-12133931.eml, finnair/it-12233765.eml, finnair/it-4191979.eml, finnair/it-4191979.eml, finnair/it-4202643.eml, finnair/it-4210704.eml, finnair/it-4235625.eml, finnair/it-4235625.eml, finnair/it-4559171.eml, finnair/it-4559824.eml, finnair/it-4564653.eml, finnair/it-4575830.eml, finnair/it-4737219.eml, finnair/it-4936098.eml, finnair/it-5023622.eml, finnair/it-5028688.eml, finnair/it-5460467.eml, finnair/it-6153350.eml, finnair/it-8237473.eml, finnair/it-8434878.eml";

    protected $lang = '';

    protected $langDetectors = [
        'fi' => ["\nMENO\n", "\nPALUU\n", 'MENO', 'MATKAKUVAUS'],
        'sv' => ["\nAVFÄRD\n", "\nRESPLAN\n"],
        'da' => ["\nAFREJSE\n"],
        'no' => ["\nAVGANG\n", "\nREISERUTE\n"],
        'es' => ["SALIDA", "\nREGRESO\n"],
        'it' => ["PARTENZA", "\nINDIETRO\n"],
        'en' => ["DEPARTURE", "\nRETURN\n", "\nITINERARY\n"],
        'de' => ["ABFLUG", "\nRÜCKFLUG\n"],
        'fr' => ["DÉPART", "RETOUR"],
    ];

    protected static $dict = [
        'fi' => [
            'YOUR BOOKING REFERENCE' => 'Varaustunnus',
            'RESERVATION OFFICE'     => 'VARAUSTOIMISTO',
            'passengersEnd'          => ['MAKSUTAPA', 'LIPUN TOIMITUS'],
            'Passenger'              => 'Matkustaja',
            'cabin'                  => 'matkustusluokka',
            'Terminal'               => 'Terminaali',
            'Frequent flyer'         => 'Kanta-asiakas',
            'routes'                 => ['MENO', 'PALUU', 'MATKAKUVAUS'],
        ],
        'sv' => [
            'YOUR BOOKING REFERENCE' => 'BOKNINGSKOD',
            'RESERVATION OFFICE'     => 'BOKNINGS KONTOR',
            'passengersEnd'          => ['LEVERANS AV BILJETTEN'],
            'Passenger'              => 'Passagerare',
            'cabin'                  => 'resklass',
            //			'Terminal' => '',
            'Frequent flyer' => 'Stamkund',
            'routes'         => ['AVFÄRD', 'RETUR'],
        ],
        'da' => [
            'YOUR BOOKING REFERENCE' => 'DIN RESERVATIONSREFERENCE',
            'RESERVATION OFFICE'     => 'RESERVATIONSBUREAU',
            'passengersEnd'          => ['BILLETLEVERING'],
            'Passenger'              => 'Passager',
            'cabin'                  => 'kabine',
            //			'Terminal' => '',
            //			'Frequent flyer' => '',
            'routes' => ['AFREJSE', 'VENDE', 'RETURN'],
        ],
        'no' => [
            'YOUR BOOKING REFERENCE' => 'BOOKINGREFERANSEN DIN',
            'RESERVATION OFFICE'     => 'RESERVASJONSKONTOR',
            'passengersEnd'          => ['BILLETTLEVERING'],
            'Passenger'              => 'Passasjer',
            'cabin'                  => 'kabin',
            //			'Terminal' => '',
            //			'Frequent flyer' => '',
            'routes' => ['AVGANG', 'RETUR'],
        ],
        'es' => [
            'YOUR BOOKING REFERENCE' => 'CÓDIGO DE RESERVA',
            'RESERVATION OFFICE'     => 'OFICINA DE RESERVAS',
            'passengersEnd'          => ['ENTREGA DE BILLETE'],
            'Passenger'              => 'Pasajero',
            'cabin'                  => 'cabina',
            //			'Terminal' => '',
            'Frequent flyer' => 'Viajero frecuente',
            'routes'         => ['SALIDA', 'REGRESO'],
        ],
        'it' => [
            'YOUR BOOKING REFERENCE' => 'RIFERIMENTO DELLA PRENOTAZIONE',
            'RESERVATION OFFICE'     => 'UFFICIO PRENOTAZIONI',
            'passengersEnd'          => ['CONSEGNA BIGLIETTO'],
            'Passenger'              => 'Passeggero',
            'cabin'                  => 'cabina',
            //			'Terminal' => '',
            //			'Frequent flyer' => '',
            'routes' => ['PARTENZA', 'RITORNO'],
        ],
        'de' => [
            'YOUR BOOKING REFERENCE' => 'IHRE BUCHUNGSREFERENZ',
            'RESERVATION OFFICE'     => 'RESERVIERUNGSBÜRO',
            'passengersEnd'          => ['ZAHLUNGSMETHODE', 'TICKETVERSAND'],
            'Passenger'              => 'Passagier',
            'cabin'                  => 'Kabine',
            'Terminal'               => 'Terminal',
            'Frequent flyer'         => 'Vielflieger',
            'routes'                 => ['ABFLUG', 'RÜCKFLUG'],
        ],
        'fr' => [
            'YOUR BOOKING REFERENCE' => 'VOTRE RÉFÉRENCE DE RÉSERVATION',
            'RESERVATION OFFICE'     => 'AGENCE DE RÉSERVATION',
            'passengersEnd'          => ['LIVRAISON DE BILLET'],
            'Passenger'              => 'Passager',
            'cabin'                  => 'cabine',
            'Terminal'               => 'Terminal',
            'Frequent flyer'         => 'Voyageur fréquent',
            'routes'                 => ['DÉPART', 'RETOUR'],
        ],
        'en' => [
            'passengersEnd' => ['TICKET DELIVERY'],
            'routes'        => ['DEPARTURE', 'RETURN'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->detectFormat()) {
            return false;
        }

        if (empty($textBody = text($parser->getHTMLBody()))) {
            $textBody = $parser->getPlainBody();
        }
        $textBody = str_replace(['&#160;', '&nbsp;', '  '], ' ', $textBody);
        $textBody = preg_replace('/^[> ]+/m', '', $textBody);
        $this->assignLang($textBody);

        return [
            'parsedData' => [
                'Itineraries' => $this->parseText($textBody),
            ],
            'emailType' => 'YourConfirmationPlain' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reply@finnair.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/^Finnair/', $from)
            || stripos($from, '@finnair.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectFormat()) {
            return false;
        }

        if (empty($textBody = text($parser->getHTMLBody()))) {
            $textBody = $parser->getPlainBody();
        }

        $textBody = str_replace(['&#160;', '&nbsp;', '  '], ' ', $textBody);
        $textBody = preg_replace('/^[> ]+/m', '', $textBody);

        if (stripos($textBody, 'www.finnair.com') === false && stripos($textBody, 'cartrawler.com/finnair/') === false && stripos($textBody, ' Finnair,') === false) {
            return false;
        }

        return $this->assignLang($textBody);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseText($textBody)
    {
        $text = $this->cutText($this->t('YOUR BOOKING REFERENCE'), $this->t('RESERVATION OFFICE'), $textBody);

        if (empty($text)) {
            $this->logger->info('Itineraries not found!');

            return false;
        }

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        if (preg_match('/:\s*\b([A-Z\d]{5,7})/', $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $passengersText = '';

        foreach ((array) $this->t('passengersEnd') as $passengersEnd) {
            if (
                ($passengersText = $this->cutText($this->t('Passenger'), $passengersEnd . "\n", $text))
                || ($passengersText = $this->cutText($this->t('Passenger'), $passengersEnd, $text))
            ) {
                break;
            }
        }

        // Barcelona, Airport (Terminal 1) - Helsinki, Helsinki Vantaa (Terminal 2)
        // Sunday 28 August 2016    or    to 02.02.2017
        // AY917 W 17:00 - 18:00 (Mon.)
        // Economy cabin
        $re = '/(?<DName>.+?)\s+-\s+(?<AName>.+?)\s+\w{2,}\s+(?<Day>\d{1,2})[\.\s]+(?<Month>(?:[^,\.\d\s]{3,}|\d{1,2}))[\.\s]+(?<Year>\d{2,4})\s+(?<AirName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FNum>\d+)(?:,00)?\s+(?<BClass>[A-Z])\s*(?<DTime>\d{1,2}:\d{2})\s+-\s+(?<ATime>\d{1,2}:\d{2})(\s*\((?<Nextday>[^)(]{2,})\))?\s+(?<Cabin>.*?' . $this->t('cabin') . '.*?)?/siu';
        $text = preg_replace('/&\s*039;/', ' ', $text);
        preg_match_all($re, $text, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $m) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            // Barcelona, Airport (Terminal 1)
            $re = '/(.+)\((' . $this->t('Terminal') . '[^)(]+|[^)(]+' . $this->t('Terminal') . ')\)/u';
            $names = ['Departure' => str_replace("\n", " ", $m['DName']), 'Arrival' => str_replace("\n", " ", $m['AName'])];
            array_walk($names, function ($name, $key) use (&$seg, $re) {
                if (preg_match($re, $name, $matches)) {
                    if (!empty($str = $this->http->FindPreg("#{$this->opt($this->t('routes'))}\s*(.+)$#", false, $matches[1]))) {
                        $seg[substr($key, 0, 3) . 'Name'] = trim($str);
                    } else {
                        $seg[substr($key, 0, 3) . 'Name'] = trim($matches[1]);
                    }

                    if (4 < strlen(trim($matches[2]))) {
                        $matches[2] = trim(str_replace($this->t('Terminal'), '', $matches[2]));
                    }
                    $seg[$key . 'Terminal'] = $matches[2];
                } else {
                    if (!empty($str = $this->http->FindPreg("#{$this->opt($this->t('routes'))}\s*(.+)$#", false, $name))) {
                        $seg[substr($key, 0, 3) . 'Name'] = trim($str);
                    } else {
                        $seg[substr($key, 0, 3) . 'Name'] = trim($name);
                    }
                }
            });

            $seg['AirlineName'] = $m['AirName'];
            $seg['FlightNumber'] = $m['FNum'];

            // AY089 11H
            if (preg_match_all('/' . $seg['AirlineName'] . $seg['FlightNumber'] . '(?:,00)? (\d{1,2}[A-Z])/', $passengersText, $seatMatches)) {
                $seg['Seats'] = $seatMatches[1];
            }

            $seg['BookingClass'] = $m['BClass'];

            if (preg_match('/\D+/', $m['Month'])) {
                if ($this->lang !== 'en') {
                    $m['Month'] = MonthTranslate::translate($m['Month'], $this->lang);
                }
                $date = $m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'];
            } else {
                $date = $m['Day'] . '.' . $m['Month'] . '.' . $m['Year'];
            }

            if ($date) {
                $seg['DepDate'] = strtotime($date . ', ' . $m['DTime']);
                $seg['ArrDate'] = strtotime($date . ', ' . $m['ATime']);

                if (!empty($m['Nextday'])) {
                    $seg['ArrDate'] = strtotime('+1 days', $seg['ArrDate']);
                }
            }

            $seg['DepDate'] = strtotime($date . ', ' . $m['DTime']);
            $seg['ArrDate'] = strtotime($date . ', ' . $m['ATime']);

            if (!empty($m['Nextday'])) {
                $seg['ArrDate'] = strtotime('+1 days', $seg['ArrDate']);
            }

            $seg['Cabin'] = trim(preg_replace('/[ ]*' . $this->t('cabin') . '[ ]*/', '', $m['Cabin']));

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber'])) {
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        $passengers = [];
        $ticketNumbers = [];
        $accountNumbers = [];

        $passengerItems = $this->splitText($passengersText, '/' . $this->t('Passenger') . ' \d{1,3}:\s*/m');

        foreach ($passengerItems as $passengerItem) {
            if (preg_match('/^(.+?)(?:\d|\n|$)/', $passengerItem, $matches)) {
                $passengers[] = trim($matches[1]);
            }
            // Warning! 358-467106539 - this is a mobile phone number!
            // TICKET=ETICKET\n00358 442536431
            if (preg_match('/ETICKET\s+(\d[\d\/ ]+\d)/', $passengerItem, $matches)) {
                $ticketNumbers[] = $matches[1];
            }
            // Kanta-asiakas: AA 6P9BJ40
            if (preg_match('/' . $this->t('Frequent flyer') . '[: ]+([-A-Z\d\/ ]+[A-Z\d]{5}[-A-Z\d\/ ]+?)(?:' . $this->t('Passenger') . '|\n|$)/', $passengerItem, $matches)) {
                $accountNumbers[] = trim($matches[1]);
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = array_unique($ticketNumbers);
        }

        if (!empty($accountNumbers[0])) {
            $it['AccountNumbers'] = array_unique($accountNumbers);
        }

        return [$it];
    }

    protected function cutText($start, $end, $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return null;
        }

        return stristr(stristr($text, $start), $end, true);
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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function detectFormat(): bool
    {
        return $this->http->XPath->query('//a[contains(@href,"//www.finnair.com") and not(contains(@href,"//www.finnair.com/int/gb/customer-care")) and not(contains(@href, "finnair.com/fi/fi/customer-care"))]')->length > 0 && $this->http->XPath->query('//text()[contains(.,"' . $this->t('Passenger') . '")]/ancestor::td[1]')->length > 0;
    }
}
