<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;

class TravelReservationPlain extends \TAccountChecker
{
    public $mailFiles = "british/it-1705001.eml, british/it-2057509.eml, british/it-2057510.eml, british/it-2240991.eml, british/it-2931372.eml, british/it-4832360.eml";

    protected $lang = null;

    protected $langDetectors = [
        'es' => [
            'Detalles de su itinerario',
        ],
        'en' => [
            'Your Itinerary Details',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'BritishAirways.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'contact@contact.BritishAirways.com') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'British Airways') === false) {
            return false;
        }

        if (stripos($headers['subject'], 'Travel Reservation') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        if (stripos($textBody, "Kind regards,\nBritish Airways") === false && stripos($textBody, 'Thank you for using British Airways') === false && stripos($textBody, 'Gracias por utilizar British Airways') === false && stripos($textBody, 'British Airways customer') === false) {
            return false;
        }

        foreach ($this->langDetectors as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($textBody, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getPlainBody();

        if (empty($textBody)) {
            $textBody = text($parser->getHTMLBody());
        }

        $textSubject = $parser->getSubject();

        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($textBody, $phrase) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $it = $this->parseEmail($textBody, $textSubject);

        if ($it === false) {
            $textBody = text($parser->getHTMLBody());

            foreach ($this->langDetectors as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($textBody, $phrase) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
            $it = $this->parseEmail($textBody, $textSubject);
        }

        return [
            'emailType'  => 'TravelReservationPlain' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['es', 'en'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function parseEmail($textBody, $textSubject)
    {
        $patterns = [
            'recordLocator' => '/Your\s+British\s+Airways\s+Travel\s+Reservation:\s*([A-Z\d]{5,7})$/mi',
            'date'          => '[^\s]{2,},?\s*(\d{1,2})\s+([^\d\s]{3,})\s+(\d{4}),?\s*(\d{1,2}:\d{2})',
        ];

        $it = [];
        $it['Kind'] = 'T';

        if (preg_match($patterns['recordLocator'], $textBody, $matches)) {
            $it['RecordLocator'] = $matches[1];
        } elseif (preg_match($patterns['recordLocator'], $textSubject, $matches)) {
            $it['RecordLocator'] = $matches[1];
        } else {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['TripSegments'] = [];
        $segments = preg_split('/(?:Your\s+Itinerary\s+Details|Detalles\s+de\s+su\s+itinerario)/ims', $textBody);
        unset($segments[0]);

        foreach ($segments as $segment) {
            $seg = [];

            if (preg_match('/^[>\s]*(?:From|Desde)\s*:\s*(.+?)\s*\n+[>\s]*(?:Terminal:[ ]*(?<term>.*)|Flight\s+Number|\bto\b)/umi', $segment, $matches)) {
                $seg['DepName'] = trim($matches[1]);
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                if (!empty($matches['term'])) {
                    $seg['DepartureTerminal'] = $matches['term'];
                }
            } else {
                return false;
            }

            if (preg_match('/^[>\s]*(?:to|a)\s+(\w.*?)\s*\n+[>\s]*(?:Terminal:[ ]*(?<term>.*)|Flight\s+Number|Número\s+de\s+vuelo)/umi', $segment, $matches)) {
                $seg['ArrName'] = trim($matches[1]);
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (!empty($matches['term'])) {
                    $seg['ArrivalTerminal'] = $matches['term'];
                }
            } else {
                return false;
            }

            if (preg_match('/(?:Flight\s+Number|Número\s+de\s+vuelo):\s*([A-Z\d]{2})(\d+)/i', $segment, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            } else {
                return false;
            }

            if (preg_match('/(?:Depart|Salida)\s*:\s*' . $patterns['date'] . '\s*$/umi', $segment, $matches)) {
                $seg['DepDate'] = strtotime($matches[1] . ' ' . MonthTranslate::translate($matches[2], $this->lang) . ' ' . $matches[3] . ', ' . $matches[4]);
            } else {
                return false;
            }

            if (preg_match('/(?:Arrive|Llegada)\s*:\s*' . $patterns['date'] . '\s*$/umi', $segment, $matches)) {
                $seg['ArrDate'] = strtotime($matches[1] . ' ' . MonthTranslate::translate($matches[2], $this->lang) . ' ' . $matches[3] . ', ' . $matches[4]);
            } else {
                return false;
            }

            if (preg_match('/(?:Cabin|Cabina)\s*:\s*(.+)$/umi', $segment, $matches)) {
                $seg['Cabin'] = $matches[1];
            }

            if (preg_match('/(?:Operated\s+By|Operado\s+por)\s*:\s*(.+)$/umi', $segment, $matches)) {
                $seg['Operator'] = $matches[1];
            }

            if (preg_match('/(?:Number\s+of\s+Stops|Número\s+de\s+paradas)\s*:\s*(\d+)$/umi', $segment, $matches)) {
                $seg['Stops'] = $matches[1];
            }

            $it['TripSegments'][] = $seg;
        }

        if (preg_match_all('/(?:Passenger|Pasajero):\s*(.+)\n/i', $textBody, $passengerMatches)) {
            $it['Passengers'] = array_unique(array_filter(array_map('trim', $passengerMatches[1])));
        }

        return $it;
    }
}
