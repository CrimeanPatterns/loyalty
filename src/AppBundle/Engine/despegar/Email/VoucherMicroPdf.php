<?php

namespace AwardWallet\Engine\despegar\Email;

class VoucherMicroPdf extends \TAccountChecker
{
    public $mailFiles = "despegar/it-12500745.eml, despegar/it-38143211.eml";

    public $reFrom = ["despegar.com"];
    public $reBody = [
        'es' => ['Uso exclusivo guarda del servicio', 'Detalle del pasaje'],
    ];
    public $reSubject = [
        'Envío de tu ticket electrónico por reserva de micro nro',
    ];
    public $lang = '';
    public $pdfNamePattern = "(?:voucher_micro_\d+|file|.*_bus_\d+)\.pdf";
    public static $dict = [
        'es' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->assignLang($text);
                    $its = array_merge($its, $this->parseEmailPdf($text));
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='TOTAL']/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));

        if (!empty($total['Total'])) {
            $result['TotalCharge'] = [
                'Amount'   => $total['Total'],
                'Currency' => $total['Currency'],
            ];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->assignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
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

    private function parseEmailPdf($textPDF)
    {
        $rl = $this->re("#{$this->opt($this->t('N° de solicitud de compra'))}[\s:]+(\d+)#", $textPDF);
        $its = [];
        $nodes = $this->splitter("#({$this->opt($this->t('Detalle del pasaje'))}\s+{$this->opt($this->t('Datos del pasajero'))})#",
            $textPDF);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\TrainTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['TripCategory'] = TRIP_CATEGORY_BUS;
            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $rl;
            $it['TicketNumbers'][] = $this->re("#{$this->opt($this->t('Boleto Nº'))}[\s:]+([A-Z\d]{5,})#", $root);
            $it['ReservationDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Fecha de venta'))}[\s:]+(.+?\d{4})#",
                $root));
            $it['Passengers'][] = $this->re("#{$this->opt($this->t('Nombre'))}[\s:]+(.+)#",
                    $root) . ' ' . $this->re("#{$this->opt($this->t('Apellido'))}[\s:]+(.+)#", $root);
            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total del Boleto'))}[\s:]+(.+?)(?:\s{3,}|\n)#",
                $root));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['DepName'] = $this->re("#{$this->opt($this->t('Origen'))}[\s:]+(.+?)\s{3,}#", $root);
            $seg['ArrName'] = $this->re("#{$this->opt($this->t('Destino'))}[\s:]+(.+?)\s{3,}#", $root);
            $seg['AirlineName'] = $this->re("#{$this->opt($this->t('Empresa'))}[\s:]+(.+?)\s{3,}#", $root);

            $depDate = $this->re("#{$this->opt($this->t('Fecha de salida'))}[\s:]+(.+?\s+\d{4})#", $root);
            $depTime = $this->re("#{$this->opt($this->t('Horario de salida'))}[\s:]+(.+?)(?:\s{3,}|\n)#", $root);
            $seg['DepDate'] = $this->normalizeDate($depDate . ' ' . $depTime);

            $arrDate = $this->re("#{$this->opt($this->t('Fecha de llegada'))}[\s:]+(.+?\s+\d{4})#", $root);
            $arrTime = $this->re("#{$this->opt($this->t('Horario de llegada'))}[\s:]+(.+?)(?:\s{3,}|\n)#", $root);
            $seg['ArrDate'] = $this->normalizeDate($arrDate . ' ' . $arrTime);

            $seg['Seats'][] = $this->re("#{$this->opt($this->t('Asiento Nº'))}[\s:]+(.+?)(?:\s{3,}|\n)#", $root);
            $seg['Cabin'] = $this->re("#{$this->opt($this->t('Categoría'))}[\s:]+(.+?)(?:\s{3,}|\n)#", $root);

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Jue 29 Mar 2018 01:30 hs.
            '#^\s*[\w\-]+\s+(\d+)\s+(\w+)\s+(\d{4})\s+(\d+:\d+)\s*[hs\.]+\s*$#u',
            //Mar 30 Ene 2018
            '#^\s*[\w\-]+\s+(\d+)\s+(\w+)\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("R$", "BRL", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => (float) $tot, 'Currency' => $cur];
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
