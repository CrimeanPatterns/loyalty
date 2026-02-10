<?php

namespace AwardWallet\Engine\finnair\Email;

use AwardWallet\Engine\MonthTranslate;

class YourConfirmation extends \TAccountChecker
{
    public $mailFiles = "finnair/it-1778618.eml, finnair/it-4080502.eml, finnair/it-4089709.eml, finnair/it-4202884.eml, finnair/it-4225109.eml, finnair/it-4244569.eml, finnair/it-4270173.eml, finnair/it-4604913.eml, finnair/it-4731999.eml, finnair/it-4732287.eml, finnair/it-5013132.eml, finnair/it-5092181.eml, finnair/it-5418701.eml, finnair/it-5838914.eml, finnair/it-6239942.eml, finnair/it-8330481.eml, finnair/it-8303452.eml, finnair/it-8429566.eml, finnair/it-8407490.eml, finnair/it-8462752.eml, finnair/it-8700217.eml";

    public $reSubject = [
        'fi' => ['Vahvistus varaustunnukselle', 'Finnairin 72 h alustava varausvahvistus'],
        'fr' => ['Votre confirmation, Réf. de réservation', 'Votre confirmation de réservation'],
        'da' => ['Din bekræftelse, reservationsref'],
        'it' => ['Conferma, riferimento prenotazione'],
        'no' => ['Bekreftelse, bookingref'],
        'de' => ['Ihre Bestätigung, Buchungsreferenz'],
        'pl' => ['Twoje potwierdzenie, nr rezerwacji'],
        'sv' => ['Bekräftelse för bokningskoden'],
        'en' => ['Your confirmation, Booking Ref', 'Hold My Booking Ref'],
    ];

    public $date = 0;

    public $lang = '';

    public static $dict = [
        'fi' => [
            'reservation'      => 'VARAUSTUNNUS',
            'segments'         => ['Säilytä tunnus, sillä se helpottaa varauksesi löytymistä', 'Varaustunnus pysyy samana', 'Säilytä tunnus'],
            'routes'           => ['MENO', 'PALUU', 'MATKAKUVAUS'],
            'Terminal'         => 'Terminaali',
            'operator'         => ['Liikennöijä', 'Liikennöi'],
            'segmentsSplitter' => 'matkustusluokka',
            'Total duration'   => 'Kokonaiskesto',
            'passenger'        => 'Matkustaja',
            'price'            => 'HINTA',
            'First name'       => 'Etunimi',
            'Family name'      => 'Sukunimi',
        ],
        'fr' => [
            'reservation'      => ['VOTRE RÉFÉRENCE DE RÉSERVATION', 'VOTRE RÉFÉRENCE HOLD MY BOOKING'],
            'segments'         => ['Notez-le ou imprimez-le et prenez-le avec', 'Cette confirmation tiendra également lieu'],
            'routes'           => ['DÉPART', 'RETOUR'],
            'operator'         => 'Opéré par',
            'segmentsSplitter' => 'cabine',
            'Total duration'   => 'Durée totale',
            'passenger'        => 'Passager',
            'price'            => 'PRIX',
            'First name'       => 'Prénom',
            'Family name'      => 'Nom',
        ],
        'da' => [
            'reservation'      => 'DIN RESERVATIONSREFERENCE',
            'segments'         => 'Skriv det ned eller udskriv det',
            'routes'           => ['AFREJSE', 'VENDE', 'RETURN'],
            'operator'         => ['Varetages af', 'Beflyves af'],
            'segmentsSplitter' => 'kabine',
            'Total duration'   => 'Samlet rejsetid',
            'passenger'        => 'Passager',
            'price'            => 'PRIS',
            'First name'       => 'Fornavn',
            'Family name'      => 'Efternavn',
        ],
        'it' => [
            'reservation' => 'RIFERIMENTO DELLA PRENOTAZIONE',
            'segments'    => 'Annotare e stampare il documento',
            'routes'      => ['PARTENZA', 'RITORNO'],
            //			'operator' => '',
            'segmentsSplitter' => 'cabina',
            'Total duration'   => 'Durata totale',
            'passenger'        => 'Passeggero',
            'price'            => 'PREZZO',
            //			'First name' => '',
            //			'Family name' => '',
        ],
        'no' => [
            'reservation'      => 'BOOKINGREFERANSEN DIN',
            'segments'         => 'Skriv den ned, eller skriv den ut',
            'routes'           => ['AVGANG', 'RETUR'],
            'operator'         => 'Betjenes av',
            'segmentsSplitter' => 'kabin',
            'Total duration'   => 'Total varighet',
            'passenger'        => 'Passasjer',
            'price'            => 'PRIS',
            'First name'       => 'Fornavn',
            'Family name'      => 'Etternavn',
        ],
        'de' => [
            'reservation'      => 'IHRE BUCHUNGSREFERENZ',
            'segments'         => 'Schreiben Sie die Buchungsreferenz',
            'routes'           => ['ABFLUG', 'RÜCKFLUG'],
            'operator'         => 'Durchgeführt von',
            'segmentsSplitter' => 'Kabine',
            'Total duration'   => 'Gesamtdauer',
            'passenger'        => 'Passagier',
            'price'            => 'PREIS',
            'First name'       => 'Vorname',
            'Family name'      => 'Nachname',
        ],
        'pl' => [
            'reservation' => 'NUMER REZERWACJI',
            'segments'    => 'Zapisz lub wydrukuj tę',
            'routes'      => ['WYLOT', 'POWRÓT', 'ZWRÓCIĆ'],
            //			'operator' => '',
            'segmentsSplitter' => 'kabina',
            'Total duration'   => 'Łączny czas trwania',
            'passenger'        => 'Pasażer',
            'price'            => 'CENA',
            'First name'       => 'Imię',
            'Family name'      => 'Nazwisko',
        ],
        'sv' => [
            'reservation' => 'DIN BOKNINGSKOD',
            'segments'    => 'Anteckna eller skriv ut din bokningskod',
            'routes'      => ['AVFÄRD', 'RETUR'],
            //			'operator' => '',
            'segmentsSplitter' => 'resklass',
            'Total duration'   => 'Restid totalt',
            'SpentAwardsPreg'  => '\s+\+\s+([\d\s]+?\s+pts)\s+from',
            'passenger'        => 'Passagerare',
            'price'            => 'PRIS',
            //			'First name' => '',
            //			'Family name' => '',
        ],
        'es' => [
            'reservation'      => 'CÓDIGO DE RESERVA',
            'segments'         => 'Anótelo o imprímalo y llévelo encima',
            'routes'           => ['SALIDA', 'REGRESO'],
            'operator'         => 'Operado por',
            'segmentsSplitter' => 'cabina',
            'Total duration'   => 'Duración total',
            'SpentAwardsPreg'  => '\s+\+\s+([\d\s]+?\s+pts)\s+from',
            'passenger'        => 'Pasajero',
            'price'            => 'PRECIO',
            'First name'       => 'Nombre',
            'Family name'      => 'Apellido',
        ],
        'en' => [
            'reservation'      => ['YOUR BOOKING REFERENCE', 'YOUR HOLD MY BOOKING REFERENCE'],
            'segments'         => ['Write it down or print it out and have', 'This will also be your booking reference'],
            'routes'           => ['DEPARTURE', 'RETURN'],
            'operator'         => 'Operated by',
            'segmentsSplitter' => 'cabin',
            'SpentAwardsPreg'  => '\s+\+\s+([\d\s]+?\s+pts)\s+from',
            'passenger'        => 'Passenger',
            'price'            => 'PRICE',
        ],
    ];

    protected $passengersText = '';

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('(//text()[' . $this->contains($this->t('reservation')) . ']/ancestor::*[1])[1]', null, true, '/:\s+([-A-Z\d]{5,})/s');

        if (!$it['RecordLocator']) {
            $it['RecordLocator'] = $this->http->FindSingleNode('(//text()[' . $this->contains($this->t('reservation')) . ']/ancestor::*[1])[2]', null, true, '/:\s+([-A-Z\d]{5,})/s');
        }

        $payment = $this->http->FindSingleNode('//*[normalize-space(text())="' . $this->t('price') . '"]/ancestor-or-self::td[1]');

        if (preg_match('/' . $this->t('price') . '\s*(\d[,.\d]*)\s+([A-Z]{3})(?:' . $this->t('SpentAwardsPreg') . ')?/', $payment, $m)) {
            $it['TotalCharge'] = $this->normalizePrice($m[1]);
            $it['Currency'] = $m[2];

            if (isset($m[3]) && !empty($m[3])) {
                $it['SpentAwards'] = $m[3];
            }
        }

        $passengers = [];
        //		$ticketNumbers = [];
        $accountNumbers = [];

        $passengersNodes = $this->http->XPath->query('//text()[contains(.,"' . $this->t('passenger') . '")]/ancestor::td[1]');
        $passengersTexts = $this->http->FindNodes('./descendant::text()[normalize-space(.)]', $passengersNodes->item(0));
        $this->passengersText = implode("\n", $passengersTexts);

        $passengerItems = $this->splitText($this->passengersText, '/\s*^[^:\d\n]+ \d{1,3}:\s*/m');

        foreach ($passengerItems as $passengerItem) {
            if (preg_match('/' . $this->t('First name') . '[: ]+(.+)/', $passengerItem, $firstnameMatches)) {
                $passenger = $firstnameMatches[1];

                if (preg_match('/' . $this->t('Family name') . '[: ]+(.+)/', $passengerItem, $familynameMatches)) {
                    $passenger .= ' ' . $familynameMatches[1];
                }
                $passengers[] = $passenger;
            } elseif (preg_match('/^(.+)/', $passengerItem, $matches)) {
                $passengers[] = $matches[1];
            }
            // Warning! 358-467106539 - this is a mobile phone number!
            //			if ( preg_match('/^\s*(\d{1,4}[- ]*\d{5,})(?:\n|$)/m', $passengerItem, $matches) )
            //				$ticketNumbers[] = $matches[1];
            // AY621780761
            if (preg_match('/^\s*([A-Z]{2}[- ]*[A-Z\d]{5,})(?:\n|$)/m', $passengerItem, $matches)) {
                $accountNumbers[] = $matches[1];
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = $passengers;
        }

        //		if ( !empty($ticketNumbers[0]) )
        //			$it['TicketNumbers'] = $ticketNumbers;

        if (!empty($accountNumbers[0])) {
            $it['AccountNumbers'] = $accountNumbers;
        }

        $it['TripSegments'] = [];

        $xpathRoutes = '//text()[' . $this->contains($this->t('segments')) . ']/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[contains(normalize-space(.),"' . $this->t('segmentsSplitter') . '")]/ancestor::td[1]';
        $routes = $this->http->XPath->query($xpathRoutes);

        if ($routes->length === 0) {
            $this->http->Log("segments root not found: $xpathRoutes", LOG_LEVEL_NORMAL);
        }

        foreach ($routes as $root) {
            $tripSegments = $this->parseSegments($root);
            $it['TripSegments'] = array_merge($it['TripSegments'], $tripSegments);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@finnair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@finnair.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"contact Finnair") or contains(normalize-space(.),"Finnair Plus") or contains(.,"cartrawler.com/finnair/") or contains(normalize-space(.),"from Finnair") or contains(.,"www.finnair.com") or contains(normalize-space(.),"Finnair flight") or contains(normalize-space(.),"FINNAIR INTERNET")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.finnair.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $textBody = str_replace(chr(194) . chr(160), ' ', $this->http->Response['body']);

        $this->http->setEmailBody($textBody, true); // bad fr char " :"

        $this->assignLang();

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'YourConfirmation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseSegments($root)
    {
        $segments = [];

        $segmentsTexts = $this->http->FindNodes('./descendant::text()[normalize-space(.)]', $root);
        $segmentsText = implode("\n", $segmentsTexts);

        // Barcelona, Airport (Terminal 1) - Helsinki, Helsinki Vantaa (Terminal 2)
        $patternSeg = '(?<DName>.+)[ ]+-[ ]+(?<AName>.+)[\n]+';
        // Sunday 28 August 2016    or    to 02.02.2017
        $patternSeg .= '\w{2,}[ ]+(?<Day>\d{1,2})[. ]+(?<Month>(?:[^,.\d\s]{3,}|\d{1,2}))[. ]+(?<Year>\d{4}|\d{2})[\n]+';
        // AY917 W 17:00 - 18:00 (Mon.)
        $patternSeg .= '(?<AirName>[A-Z\d]{2})(?<FNum>\d+)(?:,00)?[ ]+(?<BClass>[A-Z])[ ]*(?<DTime>\d{1,2}:\d{2})[ ]+-[ ]+(?<ATime>\d{1,2}:\d{2})([ ]*\((?<Nextday>[^)(]{2,})\))?[\n]+';
        // Operator: Finnair
        $patternSeg .= '(?:(?:' . implode('|', (array) $this->t('operator')) . ')[: ]+(?<Operator>.+)[\n]+)?';
        // Economy cabin
        $patternSeg .= '(?<Cabin>.*' . $this->t('segmentsSplitter') . '.*)';

        preg_match_all('/' . $patternSeg . '/iu', $segmentsText, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $m) {
            $seg = [];

            // Barcelona, Airport (Terminal 1)
            $re = '/(.+)\((' . $this->t('Terminal') . '[^)(]+|[^)(]+' . $this->t('Terminal') . ')\)/u';
            $names = ['Departure' => $m['DName'], 'Arrival' => $m['AName']];
            array_walk($names, function ($name, $key) use (&$seg, $re) {
                if (preg_match($re, $name, $matches)) {
                    $seg[substr($key, 0, 3) . 'Name'] = $matches[1];
                    $seg[$key . 'Terminal'] = $matches[2];
                } else {
                    $seg[substr($key, 0, 3) . 'Name'] = $name;
                }
            });

            $seg['AirlineName'] = $m['AirName'];
            $seg['FlightNumber'] = $m['FNum'];

            // AY3184 10C
            if (preg_match_all('/' . $seg['AirlineName'] . $seg['FlightNumber'] . '(?:,00)? (\d{1,2}[A-Z])/', $this->passengersText, $seatMatches)) {
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

            if (!empty($m['Operator'])) {
                $seg['Operator'] = $m['Operator'];
            }

            $seg['Cabin'] = preg_replace('/[ ]*' . $this->t('segmentsSplitter') . '[ ]*/', '', $m['Cabin']);

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber'])) {
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $segments[] = $seg;
        }

        // Total duration: 2h 55min
        if (count($segments) === 1) {
            if (preg_match('/^\s*' . $this->t('Total duration') . '[: ]+(.+)$/m', $segmentsText, $matches)) {
                $segments[0]['Duration'] = $matches[1];
            }
        }

        return $segments;
    }

    protected function assignLang()
    {
        foreach (self::$dict as $lang => $phrases) {
            foreach ((array) $phrases['segments'] as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
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

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }
}
