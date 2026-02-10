<?php

namespace AwardWallet\Engine\china\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "china/it-6338200.eml";

    protected $lang = null;

    protected $langDetectors = [
        'pt' => [
            'Detalhes da Viagem',
        ],
        'en' => [
            'Booking Details',
        ],
    ];

    protected $dict = [
        'Travel Details' => [
            'pt' => 'Detalhes da Viagem',
            'en' => 'Booking Details',
        ],
        'Passenger' => [
            'pt' => 'Passageiro',
            'en' => 'Passenger',
        ],
        'Reservation code' => [
            'pt' => 'Código da Reserva',
            'en' => 'Booking Reference',
        ],
        'Flight' => [
            'pt' => 'Voo',
            'en' => 'Flight',
        ],
        'From' => [
            'pt' => 'Origem',
            'en' => 'From',
        ],
        'To' => [
            'pt' => 'Destino',
            'en' => 'To',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'china-airlines.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'notice@email.china-airlines.') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'China Airlines') !== false && stripos($headers['subject'], 'Boarding Pass') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href,"//www.china-airlines.com")]')->length > 0
            && $this->http->XPath->query('//node()[contains(.,"Booking Details")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0) {
                    $this->lang = $lang;
                }
            }
        }
        $its = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'BoardingPass_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt', 'en'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    // функция возвращает ключ из $array в котором был найден $recordLocator, иначе FALSE
    protected function recordLocatorInArray($recordLocator, $array)
    {
        $result = false;

        foreach ($array as $key => $value) {
            if (in_array($recordLocator, $value)) {
                $result = $key;
            }
        }

        return $result;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d]{3})\s+(\d{2,4})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $string, $matches)) {
            if ((int) $matches[2] > 12) {
                $month = $matches[1];
                $day = $matches[2];
            } else {
                $day = $matches[1];
                $month = $matches[2];
            }
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . '.' . $year;
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

    protected function ParseEmail()
    {
        $its = [];
        $blocks = $this->http->XPath->query('//table[./preceding-sibling::*[starts-with(normalize-space(.),"' . $this->translate('Travel Details') . '")] and starts-with(normalize-space(.),"' . $this->translate('Passenger') . '")]');

        foreach ($blocks as $block) {
            $it = $this->ParseFlights($block);

            if (($key = $this->recordLocatorInArray($it['RecordLocator'], $its)) !== false) {
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $it['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $it['TripSegments']);
            } else {
                $its[] = $it;
            }
        }

        return $its;
    }

    protected function ParseFlights($root)
    {
        $it = [];
        $it['Kind'] = 'T';

        if ($passenger = $this->http->FindSingleNode('.//text()[contains(.,"' . $this->translate('Passenger') . '")]/following::text()[string-length(normalize-space(.))>2][1]', $root)) {
            $it['Passengers'] = [$passenger];
        }
        $it['RecordLocator'] = $this->http->FindSingleNode('.//text()[contains(.,"' . $this->translate('Reservation code') . '")]/following::text()[string-length(normalize-space(.))>4][1]', $root, true, '/[A-Z\d]{5,7}/');
        $it['TripSegments'] = [];
        $seg = [];
        $xpathFragments = [
            'Flight' => './/text()[contains(.,"' . $this->translate('Flight') . '")]',
            'From'   => './descendant::tr[starts-with(normalize-space(.),"' . $this->translate('From') . '") and not(.//tr)][1]//text()[contains(.,"' . $this->translate('From') . '")][1]',
            'To'     => './descendant::tr[starts-with(normalize-space(.),"' . $this->translate('To') . '") and not(.//tr)][1]//text()[contains(.,"' . $this->translate('To') . '")][1]',
        ];
        $patterns = [
            'date' => '/(\d{1,2}\s+[^\d]{3}\s+\d{2,4}|\d{1,2}\/\d{1,2}\/\d{2,4})\s*-\s*(\d{1,2}:\d{2})/',
        ];
        $flight = $this->http->FindSingleNode($xpathFragments['Flight'] . '/following::text()[string-length(normalize-space(.))>2][1]', $root);

        if (preg_match('/^\s*([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }
        $seg['Cabin'] = $this->http->FindSingleNode($xpathFragments['Flight'] . '/following::text()[(normalize-space(.)="-" or .=" ") and position()>1 and ./following::text()[contains(normalize-space(.),"Latest Boarding Time") or contains(normalize-space(.),"LatestBoardingTime")]][1]/following::text()[1]', $root);
        $seg['DepName'] = $this->http->FindSingleNode($xpathFragments['From'] . '/following::text()[string-length(normalize-space(.))>2][1]', $root);

        if ($terminalDep = $this->http->FindSingleNode($xpathFragments['From'] . '/following::text()[starts-with(normalize-space(.),"Terminal")][1]', $root, true, '/Terminal\s*([A-Z\d]{1,2})/')) {
            $seg['DepartureTerminal'] = $terminalDep;
        }
        $dateDep = $this->http->FindSingleNode($xpathFragments['From'] . '/following::text()[contains(.,"-") and contains(.,":") and position()>1][1]', $root);

        if (preg_match($patterns['date'], $dateDep, $matches)) {
            $seg['DepDate'] = strtotime($this->normalizeDate($matches[1]) . ', ' . $matches[2]);
        }
        $seg['ArrName'] = $this->http->FindSingleNode($xpathFragments['To'] . '/following::text()[string-length(normalize-space(.))>2][1]', $root);

        if ($terminalArr = $this->http->FindSingleNode($xpathFragments['To'] . '/following::text()[starts-with(normalize-space(.),"Terminal")][1]', $root, true, '/Terminal\s*([A-Z\d]{1,2})/')) {
            $seg['ArrivalTerminal'] = $terminalArr;
        }
        $dateArr = $this->http->FindSingleNode($xpathFragments['To'] . '/following::text()[contains(.,"-") and contains(.,":") and position()>1][1]', $root);

        if (preg_match($patterns['date'], $dateArr, $matches)) {
            $seg['ArrDate'] = strtotime($this->normalizeDate($matches[1]) . ', ' . $matches[2]);
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $it['TripSegments'][] = $seg;

        return $it;
    }
}
