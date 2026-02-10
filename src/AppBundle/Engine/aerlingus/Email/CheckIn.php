<?php

namespace AwardWallet\Engine\aerlingus\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "aerlingus/it-6871599.eml";

    public $reFrom = "aerlingus@fly.aerlingus.com";
    public $reBody = [
        'en' => ['Check in for your flight now', 'Booking Reference'],
    ];
    public $reSubject = [
        'Check-in is now open',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $its = $this->parseEmail();
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'fly.aerlingus.com')]")->length > 0) {
            return $this->AssignLang();
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Booking Reference')}')]/following::text()[normalize-space(.)!=''][1]");

        $xpath = "//img[contains(@src,'details_arrow.png')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode("./ancestor::table[2]/following-sibling::table[1]/descendant::tr[count(descendant::tr)=0]/td[normalize-space(.)!=''][2]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = implode("\n", $this->http->FindNodes("./td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(\d+:\d+\s*(?:[apAP][mM])?)\n(.+)\n(.+?),\s+([A-Z]{3})\s*.*?(?:Terminal\s*(.+))?$#", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1], strtotime($this->normalizeDate($m[2])));
                $seg['DepName'] = $m[3];
                $seg['DepCode'] = $m[4];

                if (isset($m[5]) && !empty($m[5])) {
                    $seg['DepartureTerminal'] = $m[5];
                }
            }
            $node = implode("\n", $this->http->FindNodes("./td[normalize-space(.)!=''][2]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(\d+:\d+\s*(?:[apAP][mM])?)\n(.+)\n(.+?),\s+([A-Z]{3})\s*.*?(?:Terminal\s*(.+))?$#", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1], strtotime($this->normalizeDate($m[2])));
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];

                if (isset($m[5]) && !empty($m[5])) {
                    $seg['ArrivalTerminal'] = $m[5];
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Wed, 21 Jun
            '#^\s*\S+\s+(\d+)\s+(\w+)\s*$#',
        ];
        $out = [
            '$1 $2 ' . $year,
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

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            $body = $this->http->Response['body'];

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
