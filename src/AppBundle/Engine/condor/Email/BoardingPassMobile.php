<?php

namespace AwardWallet\Engine\condor\Email;

class BoardingPassMobile extends \TAccountChecker
{
    public $mailFiles = "condor/it-6499229.eml, condor/it-7704760.eml";

    public $reFrom = "@condor.com";
    public $reBody = [
        'es' => ['Puede descargar su  tarjeta de embarque móvil aquí', 'Para poder imprimir la tarjeta de embarque necesitará un Adobe Acrobat Reader'],
        'de' => ['Ihre Mobile Bordkarte(n) können Sie hier herunterladen', 'Sollten Sie den Adobe Reader noch nicht auf Ihrem Computer installiert haben'],
    ];
    public $reSubject = [
        'es' => 'Su(s) tarjeta(s) de embarque',
        'de' => 'Ihre Condor Bordkarte(n)',
    ];
    public $lang = '';
    public $fileNameBarCode;
    public $URLBarCode;
    public $pdfNamePattern = "BoardingPass.*pdf";
    public static $dict = [
        'es' => [
            'startSeg' => 'Original',
            'endSeg'   => 'Información importante acerca de su vuelo',
            'preURL'   => 'Puede descargar su  tarjeta de embarque móvil aquí:',
        ],
        'de' => [
            'startSeg' => 'Original',
            'endSeg'   => 'Wichtige Hinweise für Ihren Flug',
            'preURL'   => 'Ihre Mobile Bordkarte\(n\) können Sie hier herunterladen:',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $search = $parser->searchAttachmentByName('Barcode.+\.jpg');

        if (count($search) !== 1) {
            $this->http->Log(sprintf('Invalid number of jpg attachments %d', count($search)));
            $this->fileNameBarCode = null;
        } else {
            $search = $search[0];
            $name = $parser->getAttachmentHeader($search, 'Content-Type');

            if (!$name || !preg_match('/name=\"?(Barcode[A-Z\d]{2}\d+\w+\d+\.jpg)\"?/u', $name, $m)) {
                $this->http->Log('invalid filename');
                $this->fileNameBarCode = null;
            } else {
                $this->fileNameBarCode = $m[1];
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            if (($text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_SIMPLE))) !== null) {
                $body = $parser->getPlainBody();

                if (empty($body)) {
                    $body = $parser->getHTMLBody();
                }
                $this->AssignLang($body);
                $this->URLBarCode = $this->re("#{$this->t('preURL')}\s+(http\S+)#u", text($body));
                $res = $this->parseEmail($text);
                $class = explode('\\', __CLASS__);

                return [
                    // 'parsedData' => ['Itineraries' => $res[0], 'BoardingPass' => $res[1]],
                    'parsedData' => ['Itineraries' => $res],
                    'emailType'  => end($class) . ucfirst($this->lang),
                ];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $body = $parser->getPlainBody();

            if (empty($body)) {
                $body = $parser->getHTMLBody();
            }

            if (stripos($body, 'Condor') !== false) {
                return $this->AssignLang($body);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function parseEmail($textPDF)
    {
        $res = [];

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Passengers'][] = $this->re("#Name of passenger\s+(.+)\n#", $textPDF);

        $seg = [];

        if (preg_match("#Depar?ture time\s+Gate\s.+\n(.+)\n(.+)\n([A-Z\d]{2})\s*(\d+)\s+([A-Z]{1,2})\s+(.+)\n(\d+:\d+\s*(?:[ap]m)?)\n#i", $textPDF, $m)) {
            $seg['DepName'] = $m[1];
            $seg['ArrName'] = $m[2];
            $seg['AirlineName'] = $m[3];
            $seg['FlightNumber'] = $m[4];
            $seg['BookingClass'] = $m[5];
            $date = strtotime($this->normalizeDate($m[6]));
            $seg['DepDate'] = strtotime($m[7], $date);
            $seg['ArrDate'] = MISSING_DATE;
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match("#" . $it['Passengers'][count($it['Passengers']) - 1] . "\s+(\d+)#i", $textPDF, $m)) {
            $it['TicketNumbers'][] = $m[1];
        }
        $seg['Seats'] = $this->re("#Seat\s+(?:.+\s+)?\d{1,2}:\d{2}+.*\n\s*(\d+[A-Za-z])#", $textPDF);

        $it['TripSegments'][] = $seg;

        // if (isset($seg['FlightNumber']) && ($this->URLBarCode || $this->fileNameBarCode)) {
        //     $bp = [
        //         'FlightNumber'       => $seg['FlightNumber'],
        //         'DepCode'            => $seg['DepCode'],
        //         'DepDate'            => $seg['DepDate'],
        //         'RecordLocator'      => $it['RecordLocator'],
        //         'Passengers'         => $it['Passengers'],
        //         'BoardingPassURL'    => $this->URLBarCode,
        //         'AttachmentFileName' => $this->fileNameBarCode,
        //     ];
        // } else {
        //     $bp = [];
        // }
        // $res = [[$it], [$bp]];
        $res = [$it];

        return $res;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\s*(\w{3})\s*(\d+)\s*$#',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

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
}
