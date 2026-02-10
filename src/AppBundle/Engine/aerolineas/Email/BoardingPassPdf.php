<?php

namespace AwardWallet\Engine\aerolineas\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "aerolineas/it-10466167.eml, aerolineas/it-10466509.eml";

    public $reBody = [//order is impotant
        'es' => ['TARJETA DE EMBARQUE', 'Número Documento'],
        'en' => ['BOARDING PASS', 'Document Number'],
    ];
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'es' => [
        ],
        'en' => [
            'Nombre del Pasajero' => 'Passenger Name',
            'Viajero Frecuente'   => 'Frequent Flyer Number',
        ],
    ];
    private $lang = '';
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        } else {
            return null;
        }

        $body = text($this->pdf->Response['body']);
        $this->AssignLang($body);

        $its = $this->parseEmail($body);
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'aerolineas') !== false && stripos($text, 'E-Ticket') === false && stripos($text, 'Itinerario') === false) {
                    if ($this->AssignLang($text)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@us.aerolineas.aero') !== false
            || stripos($from, '@aerolineas.com') !== false
            || stripos($from, 'Aerolineas Argentinas') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                            && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                        ) {
                            $new = "";

                            if (isset($tsJ['Seats'])) {
                                $new .= "," . $tsJ['Seats'];
                            }

                            if (isset($tsI['Seats'])) {
                                $new .= "," . $tsI['Seats'];
                            }
                            $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                            $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                            $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = "";

                    if (isset($its[$j]['Passengers'])) {
                        $new .= "," . implode(",", $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new .= "," . implode(",", $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['TicketNumbers'] = $new;
                }

                unset($its[$i]);
            }
        }

        return array_values($its);
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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail($textPDF)
    {
        $its = [];
        $nodes = $this->splitter("#^\s*(BOARDING PASS)\n#m", $textPDF);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match("#Document Number[\s:]+([A-Z\d]{2})\s*(\d+)\s+(?:([A-Z]{1,2})\s+)?(\d+)(\w+)\s+(\d+)\s+([A-Z\d]{5,})#", $root, $m)) {
                $it['RecordLocator'] = $m[7];
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (isset($m[3])) {
                    $seg['BookingClass'] = $m[3];
                }
                $seg['DepDate'] = $this->normalizeDate($m[4] . ' ' . $m[5] . ', ' . $m[6]);
            }
            $seg['ArrDate'] = MISSING_DATE;
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['DepName'] = $this->re("#From[\s:]+([^\n]+)#", $root);
            $seg['ArrName'] = $this->re("#To[\s:]+([^\n]+)#", $root);
            $seg['Seats'] = $this->re("#Seat[\s:]+.+?\s+\b(\d{1,3}[A-Z])\b#s", $root);
            $it['TicketNumbers'][] = $this->re("#ELECTRONIC.+?\s+(\d \d{10,} \d)#s", $root);
            $it['Passengers'][] = $this->re("#{$this->opt($this->t('Nombre del Pasajero'))}[\s\/]+([A-Z\/ ]{5,})#", $root);
            $it['AccountNumbers'][] = $this->re("#{$this->opt($this->t('Viajero Frecuente'))}[\s\/]+([A-Z\d ]{5,})#", $root);
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }
        $its = $this->mergeItineraries($its);

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^(\d+)\s+(\w+),\s+(\d{2})(\d{2})$#',
        ];
        $out = [
            '$1 $2 ' . $year . ' $3:$4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        if ($this->date > $str) {
            $str = strtotime("+1 year", $str);
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
