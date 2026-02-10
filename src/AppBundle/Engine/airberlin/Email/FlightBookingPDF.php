<?php

namespace AwardWallet\Engine\airberlin\Email;

class FlightBookingPDF extends \TAccountChecker
{
    public $mailFiles = "airberlin/it-2852773.eml, airberlin/it-6098898.eml, airberlin/it-6144823.eml, airberlin/it-6152093.eml, airberlin/it-1813447.eml, airberlin/it-1989377.eml, airberlin/it-1989382.eml, airberlin/it-2.eml, airberlin/it-2011505.eml, airberlin/it-2030302.eml, airberlin/it-3.eml, airberlin/it-3991271.eml, airberlin/it-10300572.eml"; // +1 bcdtravel(pdf)[en]

    protected $lang = '';

    protected $langDetectors = [
        'de' => [
            'Buchungsnummer',
        ],
        'es' => [
            'Código de reserva',
        ],
        'en' => [
            'Booking reference',
        ],
    ];

    protected static $dict = [
        'de' => [
            'Split Start'       => 'Air Berlin PLC & CO',
            'Split End'         => 'Es gelten die Allgemeinen Beförderungsbedingungen der airberlin group',
            'Booking Reference' => 'Buchungsnummer',
            'Booking Date'      => 'Buchungsdatum',
            'Passengers'        => ['Passagiere', 'Fluggäste'],
            'Flights'           => 'Fluginformationen',
            'Payment'           => 'Rechnungsbetrag',
        ],
        'es' => [
            'Split Start'       => 'Air Berlin PLC & CO',
            'Split End'         => 'Consulte los Términos y Condiciones Generales de Transporte del grupo ariberlin',
            'Booking Reference' => 'Código de reserva',
            'Booking Date'      => 'Fecha de reserva',
            'Passengers'        => 'Viajero',
            'Flights'           => 'Vuelos',
            'Payment'           => 'Importe de la factura',
        ],
        'en' => [
            'Split Start'       => 'Air Berlin PLC & CO',
            'Split End'         => 'The General Terms and Conditions of Carriage of the airberlin group apply',
            'Booking Reference' => 'Booking[ ]+reference(?:[ ]+number)?',
            'Booking Date'      => 'Booking[ ]+date',
            'Payment'           => 'Invoice[ ]+Total',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]airberlin\.com/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reply@airberlin.com')
            || stripos($headers['from'], 'reply@invoices.airberlin.com');
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'your Service Team airberlin') === false && stripos($textPdf, 'airberlin.com') === false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if ($this->assignLang($textPdf) === false) {
                continue;
            }

            if ($it = $this->parsePdf($textPdf)) {
                return [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => 'FlightBookingPDF_' . $this->lang,
                ];
            }
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        } else {
            return $s;
        }
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
            //			array_shift($result);
        }

        return $result;
    }

    protected function parsePdf($textPdf)
    {
        $start = stripos($textPdf, $this->translate('Split Start'));

        foreach (self::$dict as $phrases) {
            if (empty($phrases['Split End'])) {
                continue;
            }
            $end = strrpos($textPdf, $phrases['Split End']);

            if ($end !== false) {
                break;
            }
        }

        if ($start === false || $end === false) {
            return null;
        }
        $text = substr($textPdf, $start, $end - $start);

        $it = [];
        $it['Kind'] = 'T';

        if (preg_match('/^[ ]*' . $this->translate('Booking Reference') . '[ ]+([A-Z\d]{5,7})/umi', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        if (preg_match('/^[ ]*' . $this->translate('Booking Date') . '[ ]+(\d{1,2}\.\d{1,2}\.\d{4})/umi', $text, $matches)) {
            $it['ReservationDate'] = strtotime($matches[1]);
        }

        if (preg_match('/^[ ]*' . $this->translate('Reference Passenger name') . '[ ]+(.+)$/umi', $text, $matches)) {
            $it['Passengers'] = [$matches[1]];
        }

        if (empty($it['Passengers'][0]) && preg_match('/^[ ]*(?:' . implode('|', (array) $this->translate('Passengers')) . ')\s+(.+?)\s+' . $this->translate('Flights') . '/ums', $text, $matches)) {
            $passengers = [];
            $passengersRows = explode("\n", $matches[1]);

            foreach ($passengersRows as $passengersRow) {
                if (preg_match('/^[ ]*\d{1,3}[ ]+(.+)$/', $passengersRow, $m)) {
                    $passengers[] = $m[1];
                }
            }

            if (!empty($passengers[0])) {
                $it['Passengers'] = $passengers;
            }
        }

        // hardcode airports list
        $airports = [
            'Paris - Charles de Gaulle',
            'Gothenburg - Landvetter',
            'Stockholm - Arlanda',
            'Palma de Mallorca',
            'Rome - Fiumicino',
            'New York - JFK',
            'Berlin - Tegel',
            'Cologne\/Bonn',
            'Paris - Orly',
            'Dusseldorf',
            'Vienna',
            'Malaga',
            'Faro',
        ];

        $it['TripSegments'] = [];
        $segmentParts = $this->splitText($text, '/^[ ]*(.+[ ]{4,}\d{1,2}\.\d{1,2}\.\d{4}[ ]+\d{1,2}:\d{2}[ ]*-[ ]*\d{1,2}:\d{2}(?:[ ]*\([+](\d{1,2})\))?[ ]+[A-Z\d]{2}[ ]*\d+)/um', true);

        foreach ($segmentParts as $segText) {
            $seg = [];

            if (preg_match('/^[ ]*(.+?[ ]{4,})(\d{1,2}\.\d{1,2}\.\d{4})[ ]+(\d{1,2}:\d{2})[ ]*-[ ]*(\d{1,2}:\d{2})(?:[ ]*\([+](\d{1,2})\))?[ ]+([A-Z\d]{2})[ ]*(\d+)/um', $segText, $segmentMatches)) {
                $airportsValues = explode(' – ', $segmentMatches[1]);

                if (count($airportsValues) === 2) {
                    $seg['DepName'] = $airportsValues[0];
                    $seg['ArrName'] = preg_replace('/^[ ]*(.+?)[ ]{4,}.*/', '$1', $airportsValues[1]);
                } elseif (preg_match('/^[ ]*(' . implode('|', $airports) . '|[^\n]+?) - (' . implode('|', $airports) . '|[^\n]+?)[ ]{4}/', $segmentMatches[1], $matches)) {
                    $seg['DepName'] = $matches[1];
                    $seg['ArrName'] = $matches[2];
                }

                if ($segmentMatches[2] && $segmentMatches[3] && $segmentMatches[4]) {
                    $seg['DepDate'] = strtotime($segmentMatches[2] . ', ' . $segmentMatches[3]);
                    $seg['ArrDate'] = strtotime($segmentMatches[2] . ', ' . $segmentMatches[4]);

                    if (!empty($segmentMatches[5])) {
                        $seg['ArrDate'] = strtotime('+' . $segmentMatches[5] . ' days', $seg['ArrDate']);
                    }
                }
                $seg['AirlineName'] = $segmentMatches[6];
                $seg['FlightNumber'] = $segmentMatches[7];

                if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                    $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            if (preg_match('/^[ ]*Operated by (.+)/m', $segText, $matches)) {
                $operatorParts = $this->splitText($matches[1], '/[ ]{2,}/');
                $seg['Operator'] = $operatorParts[0];
            }

            $it['TripSegments'][] = $seg;
        }

        if (preg_match('/^[ ]*' . $this->translate('Payment') . '[ ]+([A-Z]{3})[ ]+([,.\d ]+)/umi', $text, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
        }

        return $it;
    }

    protected function assignLang($text)
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
}
