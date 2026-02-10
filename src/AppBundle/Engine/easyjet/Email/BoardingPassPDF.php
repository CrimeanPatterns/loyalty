<?php

namespace AwardWallet\Engine\easyjet\Email;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-8327807.eml";

    protected $langDetectors = [ // from MODE_SIMPLE
        'en' => ['Flying from', 'Flight number', 'Flight departs', 'Seat numbr', 'Pasengr'],
    ];

    protected $lang = '';

    protected static $dict = [
        'en' => [],
    ];

    /** @var \HttpBrowser */
    private $pdf = null;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'email.easyJet.com') !== false
               || stripos($from, '@easyJet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (
                stripos($headers['from'], 'email.easyJet.com') !== false
                || stripos($headers['from'], '@easyJet.com') !== false
            );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdf);

            if ($this->assignLang()) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdf);
            $this->assignLang();
            $its = $this->parsePdf();

            if (count($its)) {
                return [
                    'parsedData' => [
                        'Itineraries' => $its,
                    ],
                    'emailType' => 'BoardingPassPDF_' . $this->lang,
                ];
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parsePdf()
    {
        $its = [];

        $travelSegments = $this->pdf->XPath->query('//p[normalize-space(.)="Travel date"]');

        foreach ($travelSegments as $travelSegment) {
            $itFlight = $this->parseFlight($travelSegment);

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                if (!empty($itFlight['Passengers'][0])) {
                    if (!empty($its[$key]['Passengers'][0])) {
                        $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    } else {
                        $its[$key]['Passengers'] = $itFlight['Passengers'];
                    }
                }
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
            } else {
                $its[] = $itFlight;
            }
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    protected function parseFlight($root)
    {
        $patterns = [
            'airport'  => '/^\(([A-Z]{3})\)/',
            'terminal' => '/\((Terminal [^)(\n]+|[^)(\n]+ Terminal)\)/i',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['TripSegments'] = [];
        $seg = [];

        $travelDate = $this->pdf->FindSingleNode('./following::p[normalize-space(.)][1]', $root);

        $flight = $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Flight number"]/following::p[normalize-space(.)][1]', $root);

        if (preg_match('/^([A-Z\d]{2})(\d+)$/', $flight, $matches) || preg_match('/^([A-Z\d]{3})(\d+)$/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        if ($seat = $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Seat number"]/following::p[normalize-space(.)][1]', $root, true, '/^(\d{1,2}[A-Z])$/')) {
            $seg['Seats'] = [$seat];
        }

        $from = $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Flying from"]/following::p[normalize-space(.)][1]', $root);

        if (preg_match($patterns['airport'], $from, $matches)) {
            $seg['DepCode'] = $matches[1];
            $teminalDep = $from . ' ' . $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Flying from"]/following::p[normalize-space(.)][2]', $root);

            if (preg_match($patterns['terminal'], $teminalDep, $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }
        }

        $to = $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Going to"]/following::p[normalize-space(.)][1]', $root);

        if (preg_match($patterns['airport'], $to, $matches)) {
            $seg['ArrCode'] = $matches[1];
            $teminalArr = $to . ' ' . $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Going to"]/following::p[normalize-space(.)][2]', $root);

            if (preg_match($patterns['terminal'], $teminalArr, $m)) {
                $seg['ArrivalTerminal'] = $m[1];
            }
        }

        $timeDep = $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Flight departs"]/following::p[normalize-space(.)][1]', $root);

        if ($travelDate && $timeDep) {
            $seg['DepDate'] = strtotime($travelDate . ', ' . $timeDep);
        }
        $seg['ArrDate'] = MISSING_DATE;

        if ($bookingReference = $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Booking reference"]/following::p[normalize-space(.)][1]', $root, true, '/^([A-Z\d]{5,})$/')) {
            $it['RecordLocator'] = $bookingReference;
        }

        $it['TripSegments'][] = $seg;

        if ($passenger = $this->pdf->FindSingleNode('./following::p[position()<30][normalize-space(.)="Passenger"]/following::p[normalize-space(.)][1]', $root, true, '/^([^}{]+)$/')) {
            $it['Passengers'] = [$passenger];
        }

        return $it;
    }

    protected function recordLocatorInArray($recordLocator, $array)
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

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                        $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            $detectMarker = true;

            foreach ($phrases as $phrase) {
                if ($this->pdf->XPath->query('//text()[normalize-space(.)="' . $phrase . '"]')->length === 0) {
                    $detectMarker = false;
                }
            }

            if ($detectMarker) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
