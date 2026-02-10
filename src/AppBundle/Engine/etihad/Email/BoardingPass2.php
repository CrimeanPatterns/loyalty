<?php

namespace AwardWallet\Engine\etihad\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar formats: aeroflot/MobileBoardingPassHtml2013En

class BoardingPass2 extends \TAccountChecker
{
    public $mailFiles = "etihad/it-18040600.eml, etihad/it-4601704.eml, etihad/it-4637977.eml, etihad/it-4724039.eml, etihad/it-4917217.eml, etihad/it-5090779.eml, etihad/it-7449266.eml, etihad/it-7452022.eml, etihad/it-8559515.eml";

    public static $dictionary = [
        'es' => [
            'Passenger Name:'      => 'Nombre del pasajero:',
            'Confirmation Number:' => 'Número de confirmación:',
            'Departure Time:'      => 'Hora de salida:',
            'Gate/Terminal:'       => 'Puerta/Terminal:',
            'From:'                => 'Desde:',
            'To:'                  => 'A:',
            'Arrival Time:'        => 'Hora de llegada:',
            'Flight Number:'       => 'Número de vuelo:',
            'Seat:'                => 'Asiento:',
            'Departure Date:'      => 'Fecha de salida:',
            //			'Class' => '',
        ],
        'fr' => [
            'Passenger Name:'      => 'Nom du passager :',
            'Confirmation Number:' => 'Numéro de confirmation :',
            'Departure Time:'      => 'Horaire de départ :',
            'Gate/Terminal:'       => 'Porte/Terminal :',
            'From:'                => 'De :',
            'To:'                  => 'À :',
            'Arrival Time:'        => 'Horaire d\'arrivée :',
            'Flight Number:'       => 'Numéro de vol :',
            'Seat:'                => 'Siège :',
            'Departure Date:'      => 'Date de départ:',
            //			'Class' => '',
        ],
        'en' => [
            'Gate/Terminal:'  => ['Gate/Terminal:', 'Gate:'],
            'Arrival Time:'   => ['Arrival Time:', 'Arrival:'],
            'Flight Number:'  => ['Flight Number:', 'Airline and Flight #'],
            'Departure Date:' => ['Departure Date:', 'Departure date:'],
        ],
    ];

    protected $providerCode = '';

    protected $lang = '';

    protected $langDetectors = [
        'es' => [
            'Número de vuelo:',
        ],
        'en' => [
            'Flight number:',
            'Flight Number:',
            'Airline and Flight #',
        ],
        'fr' => [
            'Numéro de vol :',
        ],
    ];
    protected $pdfDetectors = ['Boleto electrónico:', 'eTicket:'];
    private $boardings = [];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reply@etihad.ae') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignProvider($parser) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Etihad Airways') !== false
            || stripos($from, '@etihad.ae') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignProvider($parser);
        $this->assignLang();

        $htmlPdfFull = '';

        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $htmlPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>«»?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $htmlPdf);

            foreach ($this->pdfDetectors as $phrase) {
                if (stripos($htmlPdf, $phrase) !== false) {
                    $htmlPdfFull .= $htmlPdf;

                    break;
                }
            }
        }

        if ($htmlPdfFull) {
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdfFull);
        }

        $its = $this->parseEmail();
        $result = [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType'    => 'BoardingPass2_' . $this->lang,
            'providerCode' => $this->providerCode,
        ];

        if (count($this->boardings) > 0) {
            $result['parsedData']['BoardingPass'] = $this->boardings;
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['aeromexico', 'etihad'];
    }

    protected function translate($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/([^,\d\s]{3,})\s+(\d{1,2})[,\s]+(\d{4})$/', $string, $matches)) { // Sun Jun 29, 2014
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})\s+([^,\d\s]{3,})[,\s]+(\d{4})/', $string, $matches)) { // 05 Aug 2016    [OR]    15 February, 2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
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

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    // функция возвращает ключ из $array в котором был найден $recordLocator, иначе FALSE
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

    protected function parseEmail()
    {
        $its = [];

        $blocks = $this->http->XPath->query('//div[starts-with(normalize-space(.),"' . $this->translate('Passenger Name:') . '") and .//text()[starts-with(normalize-space(.),"' . $this->translate('Confirmation Number:') . '")]]');

        foreach ($blocks as $block) {
            $it = $this->parseFlights($block);

            if (($key = $this->recordLocatorInArray($it['RecordLocator'], $its)) !== false) {
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $it['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $it['TripSegments']);
            } else {
                $its[] = $it;
            }
        }

        // parsing TicketNumbers from PDF-attachments
        if (isset($this->pdf)) {
            foreach ($its as $key => $it) {
                $ticketNumbers = $this->pdf->FindNodes('//text()[contains(.,": ' . $it['RecordLocator'] . '")]/following::text()[normalize-space(.)][position()<10 and ' . $this->starts($this->pdfDetectors) . ']', null, '/:\s*([-\d\s]+)$/');
                $ticketNumberValues = array_values(array_filter($ticketNumbers));

                if (!empty($ticketNumberValues[0])) {
                    $its[$key]['TicketNumbers'] = array_unique($ticketNumberValues);
                }
            }
        }

        return $its;
    }

    protected function parseFlights($root)
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['Passengers'] = [];
        $it['Passengers'][] = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('Passenger Name:') . '")]/following::text()[normalize-space(.)][1]', $root);
        $rewardsProgramNumber = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('Rewards Program:') . '")]/following::text()[normalize-space(.)][1]', $root, true, '/([-A-Z\d]+)$/');

        if ($rewardsProgramNumber) {
            $it['AccountNumbers'] = [trim($rewardsProgramNumber, '- ')];
        }
        $it['RecordLocator'] = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('Confirmation Number:') . '")]/following::text()[normalize-space(.)][1]', $root, true, '/([A-Z\d]{5,})/');
        $it['TripSegments'] = [];
        $seg = [];
        $timeDep = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('Departure Time:') . '")]/following::text()[normalize-space(.)][1]', $root, true, '/^(\d{2}:\d{2})$/');
        $terminal = $this->http->FindSingleNode('.//text()[' . $this->starts($this->translate('Gate/Terminal:')) . ']/following::text()[normalize-space(.)][1]', $root, true, '/\/([A-Z\d\s]+)$/');

        if ($terminal) {
            $seg['DepartureTerminal'] = $terminal;
        }
        $from = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('From:') . '")]/following::node()[normalize-space(.)][1]', $root);

        if (preg_match('/^(.+)\s+\(([A-Z]{3})\)$/', $from, $matches)) {
            $seg['DepName'] = $matches[1];
            $seg['DepCode'] = $matches[2];
        } elseif (preg_match('/^([A-Z]{3})$/', $from)) {
            $seg['DepCode'] = $from;
        } elseif ($from) {
            $seg['DepName'] = $from;
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }
        $to = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('To:') . '")]/following::node()[normalize-space(.)][1]', $root);

        if (preg_match('/^(.+)\s+\(([A-Z]{3})\)$/', $to, $matches)) {
            $seg['ArrName'] = $matches[1];
            $seg['ArrCode'] = $matches[2];
        } elseif (preg_match('/^([A-Z]{3})$/', $to)) {
            $seg['ArrCode'] = $to;
        } elseif ($to) {
            $seg['ArrName'] = $to;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }
        $timeArr = $this->http->FindSingleNode('.//text()[' . $this->starts($this->translate('Arrival Time:')) . ']/following::text()[normalize-space(.)][1]', $root, true, '/^(\d{2}:\d{2})$/');
        $flight = $this->http->FindSingleNode('.//text()[' . $this->starts($this->translate('Flight Number:')) . ']/following::text()[normalize-space(.)][1]', $root);

        if (preg_match('/([A-Z\d]{2})\s*(\d+)(?:\s*Operated\s+by\s*(.*))?$/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];

            if (isset($matches[3])) {
                $seg['Operator'] = $matches[3];
            }
        } elseif (preg_match('/\s*(\d+)\s+\(([A-Z\d]{2})\)$/', $flight, $matches)) {
            $seg['FlightNumber'] = $matches[1];
            $seg['AirlineName'] = $matches[2];
        }
        $seat = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('Seat:') . '")]/following::text()[normalize-space(.)][1]', $root, true, '/^(\d{1,2}[A-Z])/');

        if ($seat) {
            $seg['Seats'] = [$seat];
        }
        $date = $this->http->FindSingleNode('.//text()[' . $this->starts($this->translate('Departure Date:')) . ']/following::text()[normalize-space(.)][1]', $root);

        if (empty($date)) {
            $date = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"' . $this->translate('Date:') . '")]/following::text()[normalize-space(.)][1]', $root);
        }

        if ($timeDep && $timeArr && $date) {
            if ($date = $this->normalizeDate($date)) {
                $seg['DepDate'] = strtotime($date . ', ' . $timeDep);
                $seg['ArrDate'] = strtotime($date . ', ' . $timeArr);
            }
        }
        $seg['Cabin'] = $this->http->FindSingleNode('.//text()[' . $this->starts($this->translate('Class')) . ']/following::text()[normalize-space(.)][1]', $root, true, '/^(.+)$/');

        $it['TripSegments'][] = $seg;

        if (!empty($href = $this->http->FindSingleNode("./preceding-sibling::div[1]/img/@src", $root))
            && isset($it['RecordLocator'],$it['Passengers'],$seg['FlightNumber'],$seg['DepCode'],$seg['DepDate'])
        ) {
            $bp = [
                'FlightNumber'    => $seg['FlightNumber'],
                'DepCode'         => $seg['DepCode'],
                'DepDate'         => $seg['DepDate'],
                'RecordLocator'   => $it['RecordLocator'],
                'Passengers'      => $it['Passengers'],
                'BoardingPassURL' => $href,
            ];
            $this->boardings[] = $bp;
        }

        return $it;
    }

    protected function assignProvider($parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"visit an Etihad Airways")]')->length > 0;
        $condition3 = $this->http->XPath->query('//a[contains(@href,"//etihad.com")]')->length > 0;
        $condition4 = $this->http->XPath->query('//img[contains(@src,"//checkin.etihad.com")]')->length > 0;

        if ($condition1 || $condition2 || $condition3 || $condition4) {
            $this->providerCode = 'etihad';

            return true;
        }

        $condition1 = strpos($from, 'Aeromexico') !== false || stripos($from, '@aeromexico.com') !== false;
        $condition2 = stripos($subject, 'Aeromexico') !== false;
        $condition3 = $this->http->XPath->query('//node()[contains(normalize-space(.),"por Aerovías de México") or contains(normalize-space(.),"by Aerovías de México") or contains(.,"aeromexico.com")]')->length > 0;
        $condition4 = $this->http->XPath->query('//img[contains(@src,"//webcheckin.aeromexico.com")]')->length > 0;

        if ($condition1 || $condition2 || $condition3 || $condition4) {
            $this->providerCode = 'aeromexico';

            return true;
        }

        return false;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
