<?php

namespace AwardWallet\Engine\checkmytrip\Email;

class PrintPDF extends \TAccountChecker
{
    public $mailFiles = "checkmytrip/it-6067487.eml, checkmytrip/it-6067505.eml, checkmytrip/it-6067517.eml";

    public $reFrom = "checkmytrip.com";
    public $reSubject = [
        "fr" => "A IMPRIMER",
    ];
    public $reBody = 'checkmytrip';
    public $reBody2 = [
        "fr" => "INFORMATIONS SUR LE VOYAGEUR",
        "es" => "INFORMACIÓN DEL VIAJERO",
    ];

    public static $dictionary = [
        "fr" => [
            'res'       => 'Numéro de réservation',
            'pas'       => 'INFORMATIONS SUR LE VOYAGEUR',
            'startSeg'  => 'INFORMATIONS SUR LE VOL',
            'endSeg'    => ['REMARQUES GÉNÉRALES', 'CONDITIONS DU CONTRAT ET AUTRES INFORMATIONS IMPORTANTES'],
            'separator' => '\n.+?à.+\n.+?\|\s+',
            'nConfirm'  => 'N  de Confirmation',
            'nFlight'   => 'Numéro de vol',
            'duration'  => 'durée',
            'Depart'    => 'Dép',
            'Arrive'    => 'Arr',
            'Cabin'     => 'Type de tarif',
            'Aircraft'  => 'Appareil',
            'Meal'      => 'Repas',
        ],
        "es" => [
            'res'       => 'Número de reserva',
            'pas'       => 'INFORMACIÓN DEL VIAJERO',
            'startSeg'  => 'INFORMACIÓN SOBRE VUELOS',
            'endSeg'    => ['OBSERVACIONES GENERALES'],
            'separator' => '\n\s*de.+?a.+\n.+?\|\s+',
            'nConfirm'  => 'Número de confirmación',
            'nFlight'   => 'Número de vuelo',
            'duration'  => 'duración',
            'Depart'    => 'Sal.',
            'Arrive'    => 'Lleg.',
            'Cabin'     => 'Tipo de tarifa',
            'Aircraft'  => 'Avión',
            'Meal'      => 'Comida',
        ],
    ];

    public $lang = "";

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "fr" => [
            "janv"     => 0, "janvier" => 0,
            "févr"     => 1, "fevrier" => 1, "février" => 1,
            "mars"     => 2,
            "avril"    => 3, "avr" => 3,
            "mai"      => 4,
            "juin"     => 5,
            "juillet"  => 6, "juil" => 6,
            "août"     => 7, "aout" => 7,
            "sept"     => 8, "septembre" => 8,
            "oct"      => 9, "octobre" => 9,
            "novembre" => 10, "nov" => 10,
            "decembre" => 11, "décembre" => 11, "déc" => 11,
        ],
        "es" => [
            "enero"  => 0,
            "feb"    => 1, "febrero" => 1,
            "marzo"  => 2,
            "abr"    => 3, "abril" => 3,
            "mayo"   => 4,
            "jun"    => 5, "junio" => 5,
            "julio"  => 6, "jul" => 6,
            "agosto" => 7,
            "sept"   => 8, "septiembre" => 8,
            "oct"    => 9, "octubre" => 9,
            "nov"    => 10, "noviembre" => 10,
            "dic"    => 11, "diciembre" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    protected $result = [];

    private $tripNum;

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                $html = null;

                if (($html = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                    foreach ($this->reBody2 as $lang => $re) {
                        if (strpos($html, $re) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }
                    $its = $this->parseEmail($html);

                    foreach ($its as $it) {
                        $itineraries[] = $it;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        return [
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    protected function parseEmail($plainText)
    {
        $this->tripNum = $this->re("#^\s*:\s*([A-Z\d]+)#", $this->findСutSection($plainText, $this->t('res'), $this->t('pas')));
        $its = $this->parseSegments($this->findСutSection($plainText, $this->t('startSeg'), $this->t('endSeg')), $this->t('separator'));

        return $its;
    }

    protected function recordLocator($recordLocator)
    {
        if (preg_match('#^\s*:\s*\w+\s*([A-Z\d]{5,})#', $recordLocator, $m)) {
            $this->result['RecordLocator'] = $m[1];
        } else {
            $this->result['RecordLocator'] = $this->tripNum;
        }
    }

    protected function parsePassengers($plainText)
    {
        if (preg_match("#^\n?(.+?)\n#us", $plainText, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function parseSegments($plainText, $segmentsSplitter = '\n.+?à.+\n.+?\|\s+')
    {
        $pax[] = $this->parsePassengers($plainText);
        $its = [];

        foreach (preg_split('/' . $segmentsSplitter . '/', $plainText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = trim($value);

            if (empty($value) !== true) {
                if (preg_match("/^[A-Z\d]{2}\s*\d+\s+/", substr($value, 0, 10))) {
                    $this->result = [];
                    $this->result['Kind'] = 'T';
                    $this->recordLocator($this->findСutSection($value, $this->t('nConfirm'), $this->t('nFlight')));
                    $this->result['Status'] = $this->re('#^[A-Z\d]{2}\s*\d+\s+(.+?)\s*\n#', $value);
                    $this->result['Passengers'] = $pax;
                    $this->result['TripSegments'][] = $this->iterationSegments(html_entity_decode($value));
                    $its[] = $this->result;
                }
            }
        }

        if (count($its) > 0) {
            $its = $this->mergeItineraries($its);
        }

        return $its;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function iterationSegments($value)
    {
        $segment = [];
        $date = null;

        if (preg_match('#^([A-Z\d]{2})\s*(\d+)#u', $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        if (preg_match("#.+?\s+(\d+\s+.+?)\s*\|\s*" . $this->t('duration') . "\s+(.+)#", $value, $m)) {
            $date = strtotime($this->normalizeDate($m[1]));
            $segment['Duration'] = $m[2];
        }

        if (preg_match('#\n' . $this->t('Depart') . '\s*:\s*(\d+:\d+)\s+(.+?)\s+([A-Z]{3})(?:\s+\|\s+(.+?))?\n#us', $value, $m)) {
            $segment['DepDate'] = strtotime($m[1], $date);
            $segment['DepName'] = $m[2];
            $segment['DepCode'] = $m[3];

            if (isset($m[4]) && !empty($m[4])) {
                $segment['DepartureTerminal'] = $m[4];
            }
        }

        if (preg_match('#\n' . $this->t('Arrive') . '\s*:\s*(\d+:\d+)\s+(?:\((\+\d+)\s*.+?\))?(.+?)\s+([A-Z]{3})(?:\s+\|\s+(.+?))?\n#us', $value, $m)) {
            $segment['ArrDate'] = strtotime($m[1], $date);

            if (isset($m[2]) && !empty($m[2])) {
                $segment['ArrDate'] = strtotime($m[2] . ' days', $segment['ArrDate']);
            }
            $segment['ArrName'] = $m[3];
            $segment['ArrCode'] = $m[4];

            if (isset($m[4]) && !empty($m[5])) {
                $segment['ArrivalTerminal'] = $m[5];
            }
        }
        $segment['Cabin'] = $this->re('#\n' . $this->t('Cabin') . '\s*:\s*(.*?)\n#u', $value);
        $segment['Aircraft'] = $this->re('#\n' . $this->t('Aircraft') . '\s*:\s*(.*?)\n#u', $value);
        $segment['Meal'] = $this->re('#\n' . $this->t('Meal') . '\s*:\s*(.*?)\n#su', $value);

        return $segment;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+\s+\S+)\s+et\s+(\d{4})$#",
            "#^(\d+\s+\S+\s+\d{4})$#",
            "#^(\d+)\s+de\s+(\S+)\s+de\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2",
            "$1",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish(mb_strtolower($str));
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
