<?php

namespace AwardWallet\Engine\airfrance\Email;

class YourTripPlain extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-5599163.eml";

    public $reFrom = "airfrance.fr";
    public $reSubject = [
        "Votre voyage pour",
    ];
    public $reBody = 'Air France';
    public $reBody2 = [
        "fr" => "AIR FRANCE & KLM Global Meetings",
    ];

    public static $dictionary = [
        "fr" => [],
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
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    protected $result = [];

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
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = $this->parseEmail($body);

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
        //		return strpos($headers["from"], $this->reFrom) !== false;
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
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
        $this->result = ['Kind' => 'T'];
        $this->result['RecordLocator'] = $this->parseRecordLocator($plainText);
        $this->parsePassengers($this->findСutSection($plainText, $this->t('INFORMATIONS VOYAGEURS'), $this->t('MODE DE LIVRAISON DES BILLETS D\'AVION')));
        $this->parseTotal($this->findСutSection($plainText, $this->t('INFORMATIONS TARIFAIRES'), $this->t('RESUME DES VOLS')));
        $this->parseSegments($this->findСutSection($plainText, $this->t('RESUME DES VOLS'), $this->t('AIR FRANCE & KLM Global Meetings')));

        return [$this->result];
    }

    protected function parseTotal($plainText)
    {
        if (preg_match("#Côut total\s*:\s*(.+?)\n#u", $plainText, $m)) {
            $tot = $this->getTotalCurrency($m[1]);
            $this->result['TotalCharge'] = $tot['Total'];
            $this->result['Currency'] = $tot['Currency'];
        }
    }

    protected function parsePassengers($plainText)
    {
        if (preg_match_all("#Nom\s+du\s+voyageur\s*:\s*(.+?)\n#u", $plainText, $m)) {
            if (is_array($m[1])) {
                $this->result['Passengers'] = array_unique($m[1]);
            }
        }

        if (preg_match_all("#Numéro\s+de\s+carte\s+Flying\s+Blue\s*:\s*(.+?)\n#u", $plainText, $m)) {
            if (is_array($m[1])) {
                $this->result['AccountNumbers'] = array_unique($m[1]);
            }
        }
    }

    protected function parseRecordLocator($recordLocator)
    {
        if (preg_match("#votre numéro de réservation\s*:?\s*([A-Z\d]{5,})#", $recordLocator, $m)) {
            return $m[1];
        } else {
            return false;
        }
    }

    protected function parseSegments($plainText)
    {
        $segmentsSplitter = "\n" . $this->t('Vol de') . "\s*";

        foreach (preg_split('/' . $segmentsSplitter . '/', $plainText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = trim($value);

            if (empty($value) !== true && strlen($value) > 100) {
                $this->result['TripSegments'][] = $this->iterationSegments(html_entity_decode($value));
            }
        }
    }

    private function iterationSegments($value)
    {
        $segment = [];
        $date = null;

        if (preg_match("#" . $this->t('Temps de vol') . "\s*:\s*(.+?)\n#u", $value, $m)) {
            $segment['Duration'] = trim($m[1]);
        }

        if (preg_match('#Compagnie aérienne.+?\s*\(([A-Z\d]{2})\s*(\d+)\)#u', $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        if (preg_match("#^\s*(.+?)\s*\(([A-Z]{3})\)\s*vers\s*(.+?)\s*\(([A-Z]{3})\)#u", $value, $m)) {
            $segment['DepCode'] = $m[2];
            $segment['DepName'] = $m[1];
            $segment['ArrCode'] = $m[4];
            $segment['ArrName'] = $m[3];
        }

        if (preg_match("#" . $this->t('Terminal de départ') . "\s*:\s*(.+)#u", $value, $m)) {
            $segment['DepartureTerminal'] = trim($m[1]);
        }

        if (preg_match("#" . $this->t('Terminal d\'arrivée') . "\s*:\s*(.+)#u", $value, $m)) {
            $segment['ArrivalTerminal'] = trim($m[1]);
        }

        if (preg_match("#" . $this->t('Départ le') . "\s+(.+?)\s*à\s*(\d+:\d+)#u", $value, $m)) {
            $segment['DepDate'] = strtotime($this->normalizeDate($m[1]) . ' ' . $m[2]);
        }

        if (preg_match("#" . $this->t('Arrivée le') . "\s+(.+?)\s*à\s*(\d+:\d+)#u", $value, $m)) {
            $segment['ArrDate'] = strtotime($this->normalizeDate($m[1]) . ' ' . $m[2]);
        }

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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)\/(\d+)\/(\d+)$#",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish(mb_strtolower($str));
        }

        return $str;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(' ', '', $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
