<?php

namespace AwardWallet\Engine\condor\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "condor/it-10231688.eml, condor/it-6569494.eml, condor/it-8369821.eml";

    public $reFrom = "@condor.com";

    // [0] - preg in section between [1] and [2]
    public $reBodyPDF = [
        'en'  => ['#FROM\s+\-+\s+TO#', 'BOARDING PASS', 'SECURITY'],
        'en2' => ['#FROM\s+(?:[A-Z\d]{0,10})?\s+TO#', 'BOARDING PASS', 'SECURITY'],
        'es'  => ['#FROM\s+\-+\s+TO#', 'BOARDING PASS', 'SEGURIDAD'],
    ];
    public $reSubject = [
        'Ihre Bordkarte(n)',
        'Su tarjeta de embarque',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = "BoardingPass.*pdf";
    public static $dict = [
        'en' => [
            'startSeg' => 'BOARDING PASS',
            'endSeg'   => 'SECURITY',
        ],
        'es' => [
            'startSeg' => 'BOARDING PASS',
            'endSeg'   => 'SEGURIDAD',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        if (count($pdfs) === 0) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);

            if (!$htmlPdf) {
                continue;
            }
            $textPdf = text($htmlPdf);

            if (!empty($textPdf) && $this->assignLang($textPdf)) {
                $its[] = $this->parseEmail($textPdf);
            }
        }

        if (count($its)) {
            $its = $this->mergeItineraries($its);
        }

        return [
            'emailType'  => 'BoardingPass' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf || strpos($textPdf, 'BOARDING PASS') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
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

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                $its[$j]['TicketNumbers'] = array_merge($its[$j]['TicketNumbers'], $its[$i]['TicketNumbers']);
                $its[$j]['TicketNumbers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['TicketNumbers'])));
                $its[$j]['AccountNumbers'] = array_merge($its[$j]['AccountNumbers'], $its[$i]['AccountNumbers']);
                $its[$j]['AccountNumbers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['AccountNumbers'])));
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

    private function parseEmail($text)
    {
        $text = $this->findСutSection($text, $this->t('startSeg'), $this->t('endSeg'));

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#BOOKING REF:\s+([A-Z\d]+)#", $text);
        $it['Passengers'][] = $this->re("#NAME\s+(.+)#", $text);
        $it['TicketNumbers'][] = $this->re("#TICKET\s+(.+)#", $text);
        $it['AccountNumbers'][] = $this->re("#FQTV\s+([A-Z\d-]{7,})#", $text); // LH992004081338275
        $seg = [];
        $date = strtotime($this->normalizeDate($this->re("#DATE\s+(.+)#", $text)));
        $seg['Cabin'] = $this->re("#CLASS\s+(.{3,})#", $text);
        $seg['BookingClass'] = $this->re("#CLASS\s+(.{1,2})\s#", $text);

        if (preg_match("#SEAT\s+(.+)\n(.+)#", $text, $m)) {
            $seg['DepName'] = $m[1];
            $seg['ArrName'] = trim($m[2]);
        }

        if (preg_match("#\n\s*([A-Z]{3})\s+([A-Z]{3})#", $text, $m)) {
            $seg['DepCode'] = $m[1];
            $seg['ArrCode'] = $m[2];
        }
        $seg['Seats'][] = $this->re("#(\d+[A-Za-z])\n\s*[A-Z]{3}\s+[A-Z]{3}#", $text);

        if (preg_match("#\n\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\n\s*(\d+:\d+)\n\s*(\d+:\d+)#", $text, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
            $seg['DepDate'] = strtotime($m[3], $date);
            $seg['ArrDate'] = strtotime($m[4], $date);
        }

        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\s*(\w{3})\s*(\d{4})\s*$#',
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

    private function assignLang($body)
    {
        if (isset($this->reBodyPDF)) {
            foreach ($this->reBodyPDF as $lang => $reBody) {
                $text = $this->findСutSection($body, $reBody[1], $reBody[2]);

                if (!empty($text) && preg_match($reBody[0], $text)) {
                    $this->lang = substr($lang, 0, 2);

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
