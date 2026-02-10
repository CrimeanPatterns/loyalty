<?php

namespace AwardWallet\Engine\copaair\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "copaair/it-10465068.eml";

    public $reFrom = "@copaair.com";
    public $reBody = [
        'es' => ['PASE DE ABORDAR', 'Hora salida'],
    ];
    public $lang = '';
    public $date;
    public $pdf;
    public $pdfNamePattern = ".*pdf";

    public static $dict = [
        'es' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
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
            $this->pdf->SetBody($html);
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

                if (stripos($text, 'copaair') !== false) {
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
        return strpos($from, $this->reFrom) !== false;
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
        $nodes = $this->splitter("#^\s*({$this->opt($this->t('PASE DE ABORDAR'))})\n#m", $textPDF);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Confirmación'))}[\s:]+([A-Z\d]{5,})#", $root);
            $it['Passengers'][] = $this->re("#{$this->opt($this->t('Nombre'))}[\s:]+(.+)#", $root) . ' ' . $this->re("#{$this->opt($this->t('Apellido'))}[\s:]+(.+)#", $root);
            $it['TicketNumbers'][] = $this->re("#{$this->opt($this->t('eTicket'))}[\s:]+(\d+)#", $root);
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match("#{$this->opt($this->t('ASIENTO'))}\s+([A-Z]{3})\s+([A-Z]{3})\s+([A-Z\d]{2})\s*(\d+)\s+[^\n]+\s+(\d+[A-Z])?#", $root, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $seg['AirlineName'] = $m[3];
                $seg['FlightNumber'] = $m[4];

                if (isset($m[5]) && !empty($m[5])) {
                    $seg['Seats'] = $m[5];
                }
            }
            $date = $this->normalizeDate($this->re("#{$this->opt($this->t('Hora de abordaje'))}.+?\s+\d+:\d+(?:\s*[ap]m)?\s+(\d+\s+\w+\s+\d{4})#is", $root));

            $seg['DepDate'] = strtotime($this->re("#{$this->opt($this->t('Hora salida'))}[\s:]+(\d+:\d+(?:\s*[ap]m)?)#i", $root), $date);
            $seg['ArrDate'] = strtotime($this->re("#{$this->opt($this->t('Hora llegada'))}[\s:]+(\d+:\d+(?:\s*[ap]m)?)#i", $root), $date);
            $it['AccountNumbers'][] = $this->re("#{$this->opt($this->t('Viajero Frecuente'))}[\s:]+([A-Z\d]{5,})#", $root);
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }
        $its = $this->mergeItineraries($its);

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\s+(\w+)\s+(\d{4})$#',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
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
}
