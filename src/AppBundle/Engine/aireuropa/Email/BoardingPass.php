<?php

namespace AwardWallet\Engine\aireuropa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "aireuropa/it-10907971.eml, aireuropa/it-4061388.eml, aireuropa/it-4067581.eml, aireuropa/it-4092471.eml, aireuropa/it-4092472.eml, aireuropa/it-4107727.eml, aireuropa/it-4114127.eml, aireuropa/it-4114805.eml, aireuropa/it-4114807.eml, aireuropa/it-4114924.eml, aireuropa/it-4312237.eml, aireuropa/it-5470678.eml, aireuropa/it-5518520.eml, aireuropa/it-6223255.eml, aireuropa/it-6223259.eml, aireuropa/it-7825692.eml, aireuropa/it-8823167.eml, aireuropa/it-9914242.eml";

    public static $dictionary = [
        'es' => [
            'Localizador'       => ['Localizador', 'LOCALIZADOR'],
            'Vuelo'             => ['Vuelo', 'VUELO'],
            'Número de Billete' => ['BILLETE', 'Número de Billete', 'NÚMERO DE BILLETE'],
            'Operado por:'      => ['Operado por:', 'OPERADO POR:'],
            'Clase'             => ['Clase', 'CLASE'],
            'Viajero Frecuente' => ['Viajero Frecuente', 'VIAJERO FRECUENTE'],
        ],
        'nl' => [
            'Localizador'       => ['Codice PNR', 'CODICE PNR'],
            'Vuelo'             => ['Volo', 'VOLO'],
            'Número de Billete' => ['Numero di biglietto', 'NUMERO DI BIGLIETTO'],
            'Operado por:'      => ['Operado por:', 'OPERADO POR:'],
            'Clase'             => ['Classe', 'CLASSE'],
            'Viajero Frecuente' => ['Frequent Flyer', 'FREQUENT FLYER'],
        ],
        'fr' => [
            'Localizador'       => ['Référence', 'RÉFÉRENCE', 'Référence de', 'RÉFÉRENCE DE'],
            'Vuelo'             => ['Vol', 'VOL'],
            'Número de Billete' => ['Numéro de billet', 'NUMÉRO DE BILLET'],
            'Operado por:'      => ['Operado por:', 'OPERADO POR:'],
            'Clase'             => ['Classe', 'CLASSE'],
            'Viajero Frecuente' => ['Frequent Flyer', 'FREQUENT FLYER'],
        ],
        'en' => [
            'Localizador'       => ['Booking', 'BOOKING'],
            'Vuelo'             => ['Flight', 'FLIGHT'],
            'Número de Billete' => ['Ticket No.', 'TICKET No.'],
            'Operado por:'      => ['Operated by:', 'OPERATED BY:'],
            'Clase'             => ['Cabin', 'CABIN'],
            'Viajero Frecuente' => ['Frequent Flyer', 'FREQUENT FLYER'],
        ],
    ];

    private $lang = '';

    private $langDetectors = [
        'es' => ['Asegúrese de llevar consigo toda la'],
        'nl' => ['Si ricordi di portare con sè la'],
        'fr' => ["Assurez vous d'être en possession de la", "Assurez-vous d'être en possession de la"],
        'en' => ['Remember to take with you the'],
    ];

    private $headers = [
        'aireuropa' => [
            'from' => ['@air-europa.com'],
            'subj' => [
                'Su tarjeta de embarque Air Europa', //'es'
                'La sua carta dimbarco Air Europa', //'nl'
                'Su tarjeta de embarque Air Europa', //'fr'
                'Carte Dembarquement Air Europa', //'fr'
                'Your Air Europa boarding pass', //'en'
            ],
        ],
        'klm' => [
            'from' => ['@airfrance-klm.com'],
            'subj' => [
                "Flying Blue: Acknowledgement of receipt of your request",
            ],
        ],
    ];
    private $code;

    public static function getEmailProviders()
    {
        return ['aireuropa', 'klm'];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $bps = [];

        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf) === false) {
                continue;
            }

            $itFlight = $this->parsePdf($textPdf, $parser);

            if ((isset($itFlight['RecordLocator']) && $key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);

                if (!empty($itFlight['TicketNumbers'][0])) {
                    if (!empty($its[$key]['TicketNumbers'][0])) {
                        $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    } else {
                        $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                    }
                }

                if (!empty($itFlight['AccountNumbers'][0])) {
                    if (!empty($its[$key]['AccountNumbers'][0])) {
                        $its[$key]['AccountNumbers'] = array_merge($its[$key]['AccountNumbers'], $itFlight['AccountNumbers']);
                        $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                    } else {
                        $its[$key]['AccountNumbers'] = $itFlight['AccountNumbers'];
                    }
                }
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
            } else {
                $its[] = $itFlight;
            }

            $bpFlight = $this->parseBp($itFlight);
            $bpFlight['AttachmentFileName'] = $this->getAttachmentName($parser, $pdf);
            $bps[] = $bpFlight;
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        $result = [
            'parsedData' => [
                'Itineraries'  => $its,
                'BoardingPass' => $bps,
            ],
            'emailType' => 'BoardingPass_' . $this->lang,
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');
        $condition1 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'aireuropa.com') === false && $condition1 === false && stripos($textPdf, 'The boarding gate will close') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parsePdf($text, $parser)
    {
        $patterns = [
            'date'     => '\d{1,2}[^,.\d ]{3,}',
            'time'     => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?',
            'terminal' => '(?<terminal>[A-Z\d]+|-)',
            'gate'     => '(?:[A-Z\d]+|-)',
            'seat'     => '(?<seat>\d{1,2}[A-Z]|-)',
        ];

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $info = $this->cutText($this->t('Localizador')[0], $text, $this->t('Vuelo')[0]);

        // RecordLocator
        // Passengers
        // DepCode
        // ArrCode
        if (preg_match('/\s{2,}(?<RL>[A-Z\d]{5,})\s+^[ ]*(?<P>[,\w ]+)$\s+^[ ]*(?<DC>[A-Z]{3})(?:\s+|\s+.+?\s+)(?<AC>[A-Z]{3})/mu', $info, $m)) {
            $it['RecordLocator'] = $m['RL'];
            $passengerRowParts = preg_split('/[ ]{2,}/', $m['P']);
            $it['Passengers'] = [$passengerRowParts[0]];
            $seg['DepCode'] = $m['DC'];
            $seg['ArrCode'] = $m['AC'];
        }

        $flight = $this->cutText($this->t('Vuelo')[0], $text, $this->t('Número de Billete')[0]);

        // Operator
        if (preg_match('/(?:' . implode('|', $this->t('Operado por:')) . ')[ ]*([\w ]+)/', $flight, $matches)) {
            $seg['Operator'] = $matches[1];
        }

        // AirlineName
        // FlightNumber
        if (preg_match('/^([A-Z\d]{2})(\d+)$/m', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        // DepartureTerminal
        // Seats
        if (preg_match('/^(?<date>' . $patterns['date'] . ')[ ]+' . $patterns['time'] . '[ ]+(?<time>' . $patterns['time'] . ')[ ]+' . $patterns['terminal'] . '[ ]+' . $patterns['gate'] . '[ ]+' . $patterns['seat'] . '$/m', $flight, $matches)) {
            $dateDep = $matches['date'];
            $timeDep = $matches['time'];
            $seg['DepartureTerminal'] = $matches['terminal'];

            if ($matches['seat'] !== '-') {
                $seg['Seats'] = [$matches['seat']];
            }
        }

        // DepDate
        if (!empty($dateDep) && !empty($timeDep)) {
            if ($dateDep = $this->normalizeDate($dateDep)) {
                $dateDep = EmailDateHelper::calculateDateRelative($dateDep, $this, $parser);
                $seg['DepDate'] = strtotime($timeDep, $dateDep);
            }
        }

        // ArrivalTerminal
        if (preg_match('/^(?<date>' . $patterns['date'] . ')[ ]+(?<time>' . $patterns['time'] . ')[ ]+' . $patterns['terminal'] . '$/m', $flight, $matches)) {
            $dateArr = $matches['date'];
            $timeArr = $matches['time'];
            $seg['ArrivalTerminal'] = $matches['terminal'];
        }

        // ArrDate
        if (!empty($dateArr) && !empty($timeArr)) {
            if ($dateArr = $this->normalizeDate($dateArr)) {
                $dateArr = EmailDateHelper::calculateDateRelative($dateArr, $this, $parser);
                $seg['ArrDate'] = strtotime($timeArr, $dateArr);
            }
        }

        // TicketNumbers
        // AccountNumbers
        // Cabin
        if (preg_match('/^[ ]*(?:' . implode('|', $this->t('Número de Billete')) . ')\s+(?:' . implode('|', $this->t('Viajero Frecuente')) . ')\s+(?:' . implode('|', $this->t('Clase')) . ')$\s+(.+)/m', $text, $matches)) {
            $rowParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (count($rowParts) === 3) {
                if ($rowParts[0] !== '-') {
                    $it['TicketNumbers'] = [$rowParts[0]];
                }

                if ($rowParts[1] !== '-') {
                    $it['AccountNumbers'] = [$rowParts[1]];
                }

                if ($rowParts[2] !== '-') {
                    $seg['Cabin'] = $rowParts[2];
                }
            }
        } elseif (preg_match('/^[ ]*(?:' . implode('|', $this->t('Clase')) . ')\s+(?:' . implode('|', $this->t('Número de Billete')) . ')\s+(?:' . implode('|', $this->t('Viajero Frecuente')) . ')$\s+(.+)/m', $text, $matches)) { // it-4114924.eml
            $rowParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (count($rowParts) === 3) {
                if ($rowParts[0] !== '-') {
                    $seg['Cabin'] = $rowParts[0];
                }

                if ($rowParts[1] !== '-') {
                    $it['TicketNumbers'] = [$rowParts[1]];
                }

                if ($rowParts[2] !== '-') {
                    $it['AccountNumbers'] = [$rowParts[2]];
                }
            }
        }

        if (count(array_filter($seg)) < 5) {
            return false;
        }
        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function parseBp($it)
    {
        $bp = [];
        $bp['FlightNumber'] = $it['TripSegments'][0]['FlightNumber'];
        $bp['DepCode'] = $it['TripSegments'][0]['DepCode'];
        $bp['DepDate'] = $it['TripSegments'][0]['DepDate'];
        $bp['RecordLocator'] = $it['RecordLocator'];
        $bp['Passengers'] = $it['Passengers'];

        return $bp;
    }

    private function cutText($start, $text, $end = PHP_EOL)
    {
        if (empty($start) || empty($text) || empty($end)) {
            return false;
        }

        return mb_stristr(mb_stristr($text, $start), $end, true);
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})([^,.\d ]{3,})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
        }

        if (isset($day, $month)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1];
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            } elseif (($monthNew = MonthTranslate::translate($month, 'es')) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month;
        }

        return false;
    }

    private function recordLocatorInArray($recordLocator, $array)
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

    private function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private function assignLang($text)
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'aireuropa') {
                return null;
            } else {
                return $this->code;
            }
        }

        return null;
    }
}
