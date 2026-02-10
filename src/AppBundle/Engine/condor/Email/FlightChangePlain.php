<?php

namespace AwardWallet\Engine\condor\Email;

class FlightChangePlain extends \TAccountChecker
{
    public $mailFiles = "condor/it-6464898.eml";

    public $reFrom = "condor.com";
    public $reBody = [
        'en' => ['We have had to make a change to your flight schedule', 'NEW FLIGHT TIME'],
    ];
    public $reSubject = [
        'Flight Change Information',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'body'        => 'Your Booking Information',
            'newSegments' => 'NEW FLIGHT TIME',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }
        $this->AssignLang($body);

        $its = $this->parseEmail($body);
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (stripos($body, 'Condor') !== false) {
            return $this->AssignLang($body);
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

    private function parseEmail($text)
    {
        $text = strstr($text, $this->t('body'));

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#Booking No.+?\-{3,}\s+([A-Z\d\-]+)#s", $text);
        $it['Passengers'][] = $this->re("#Booking No.+?\-{3,}\s+[A-Z\d\-]+.+?([\w\/]+)\n#s", $text);

        $text = strstr($text, $this->t('newSegments'));

        $nodes = $this->splitter("#^\s*([A-Z\d]{2}\s+\d+.+)#m", $text);

        foreach ($nodes as $root) {
            $seg = [];
            $t = explode("@", preg_replace('#\s{2,}#', '@', $root));

            if (count($t) > 6) {
                $seg['AirlineName'] = $this->re("#([A-Z\d]{2})#", $t[0]);
                $seg['FlightNumber'] = $this->re("#(\d+)#", $t[1]);
                $date = strtotime($this->normalizeDate($t[2]));
                $seg['DepDate'] = strtotime($t[3], $date);
                $seg['ArrDate'] = strtotime($t[4], $date);
                $seg['DepName'] = $t[5];
                $seg['ArrName'] = $t[6];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\d+)\-(\w+)\-(\d+)#',
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
