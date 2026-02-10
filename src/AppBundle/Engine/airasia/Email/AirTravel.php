<?php

namespace AwardWallet\Engine\airasia\Email;

class AirTravel extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "airasia/it-4709122.eml, airasia/it-4969634.eml, airasia/it-12698249.eml, airasia/it-12654732.eml, airasia/it-12643288.eml";

    protected $detectBody = [
        'en' => ['thank you for choosing AirAsia Indonesia', 'AirAsia would like to advise that flight'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    private $lang = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'AirTravel' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->detectBody as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airasia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'AirAsia Notification') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Your flight') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $patterns = [
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
        ];

        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//span[contains(normalize-space(.), 'Your booking number')]/following-sibling::span[normalize-space(.)][1]");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(., 'Your booking number')]/following-sibling::b[1])[1]");
        }

        $passenger = $this->http->FindSingleNode('//text()[normalize-space(.)="Your booking number:"]/following::*[starts-with(normalize-space(.),"Dear ") and contains(.,",")][1]', null, true, '/^Dear\s+([A-z][-,.\'A-z\s\/]*[.A-z])\s*,/');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        $seg = [];

        $depDate = '';
        $depTime = '';
        $arrTime = '';

        $airInfo1 = $this->http->FindSingleNode('//p[contains(normalize-space(.),"new departure date is")]');

        $pattern1 = '/'
            . 'new\s+departure\s+date\s+is\s*(?<dateDep>.+?)' // new departure date is on Tuesday, September 29, 2015
            . '\s*with\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)' //  with QZ 176
            . '\s*at\s*(?<timeDep>' . $patterns['time'] . ')' // at 05:00
            . '/';

        if (preg_match($pattern1, $airInfo1, $matches)) {
            $depDate = $this->normalizeDate($matches['dateDep']);

            if (!empty($matches['airline'])) {
                $seg['AirlineName'] = $matches['airline'];
            }
            $seg['FlightNumber'] = $matches['flightNumber'];
            $depTime = $matches['timeDep'];
        }

        if (empty($depDate)) {
            $depDate = $this->normalizeDate($this->http->FindSingleNode("//span[contains(., 'departure time')]/descendant::b[normalize-space(.)][2]"));
        }

        if (empty($depTime)) {
            $depTime = $this->http->FindSingleNode('//p[contains(normalize-space(.),"departure time is")]', null, true, '/departure\s+time\s+is\s*(' . $patterns['time'] . ')/');
        }

        if (empty($depTime)) {
            $depTime = $this->http->FindSingleNode("//span[contains(., 'departure time')]/following-sibling::span[normalize-space(.)][1]", null, true, '#(\d{1,2}:\d{2}\s?(?:PM|AM|))#');
        }

        if (empty($depTime)) {
            $depTime = $this->http->FindSingleNode("(//span[contains(., 'departure time')]/descendant::b[normalize-space(.)][1])[1]", null, true, '#(\d{2}:\d{2})#');
        }

        if (empty($arrTime)) {
            $arrTime = $this->http->FindSingleNode('//p[contains(normalize-space(.),"arrival time is")]', null, true, '/arrival\s+time\s+is\s*(' . $patterns['time'] . ')/');
        }

        if (empty($arrTime)) {
            $arrTime = $this->http->FindSingleNode("//span[contains(., 'arrival time')]/following-sibling::span[normalize-space(.)][1]", null, true, '#(\d{1,2}:\d{2}\s?(?:PM|AM|))#');
        }

        if (empty($arrTime)) {
            $arrTime = $this->http->FindSingleNode("//span[contains(., 'arrival time')]/descendant::b[normalize-space(.)][3]", null, true, '#.*(\d{2}:\d{2}).*#');
        }

        if (empty($arrTime)) {
            $arrTime = $this->http->FindSingleNode("//span[contains(., 'arrival time')]/descendant::text()[string-length()>3][3]", null, true, '#^(\d{2}:\d{2})$#');
        }

        if (empty($arrTime)) {
            $arrTime = $this->http->FindSingleNode("//text()[contains(., 'arrival time')]", null, true, '#arrival time is\s+(\d{2}:\d{2})#');
        }

        if (empty($depTime) && empty($arrTime)) {
            $depAndArrTime = $this->http->FindSingleNode("//text()[contains(., 'arrival time')]");
        }

        if (isset($depAndArrTime) && preg_match('#\((?<DepTime>\d{2}:\d{2} \D{2})\).+\((?<ArrTime>\d{2}:\d{2} \D{2})\).*#', $depAndArrTime, $math)) {
            $depTime = $math['DepTime'];
            $arrTime = $math['ArrTime'];
        }

        $flight = $this->http->FindSingleNode('//p[contains(normalize-space(.),"new flight number is")]');

        if (preg_match('/new\s+flight\s+number\s+is\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)/', $flight, $matches)) {
            if (!empty($matches['airline'])) {
                $seg['AirlineName'] = $matches['airline'];
            }
            $seg['FlightNumber'] = $matches['flightNumber'];
        }

        $airInfo2 = $this->http->FindSingleNode('//p[' . $this->contains(['your flight', 'your AirAsia flight', 'advise that flight']) . ' and not(contains(normalize-space(.),"departure"))]');

        $pattern2 = '/'
            . '.*flight(?:\s?code|)\s+(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+[-]*(?<FlightNumber>\d+).+from\s?(?<DepName>(?:.+|))\s+[\(]?(?<DepCode>[A-Z]{3})[\)]?\s+to\s?'
            . '(?<ArrName>(?:.+|))\s+[\(]?(?<ArrCode>[A-Z]{3})[\)]?\s?(?:has(?:unfortunately)? been (?<Status>changed|cancelled).*|(?:on|at)\s+(?:\w+|.+),\s+'
            . '(?:(?<Month>\w+)\s+(?<Day>\d+)|(?<Day2>\d{1,2}) (?<Month2>\w+))[,\s]*(?<Year>\d{2,4})(?: at (?<DepTime>' . $patterns['time'] . '))?.*)'
            . '/';

        if (preg_match($pattern2, $airInfo2, $m)) {
            $monthAndDay = $this->chechDateToCorrect($m);
            $date = $monthAndDay !== null && !empty($m['Year']) ? $monthAndDay['Day'] . ' ' . $monthAndDay['Month'] . ' ' . $m['Year'] : null;

            if (empty($seg['AirlineName'])) {
                $seg['AirlineName'] = $m['AirlineName'];
            }

            if (empty($seg['FlightNumber'])) {
                $seg['FlightNumber'] = $m['FlightNumber'];
            }

            if (!empty($m['DepName'])) {
                $seg['DepName'] = $m['DepName'];
            }
            $seg['DepCode'] = $m['DepCode'];

            if (!empty($m['ArrName'])) {
                $seg['ArrName'] = $m['ArrName'];
            }
            $seg['ArrCode'] = $m['ArrCode'];

            if (empty($depTime) && !empty($m['DepTime'])) {
                $depTime = $m['DepTime'];
            }

            if (!empty($depTime)) {
                $seg['DepDate'] = !empty($depDate) ? strtotime($depDate . ', ' . $depTime) : strtotime($date . ', ' . $depTime);
            }

            if (!empty($arrTime)) {
                $seg['ArrDate'] = !empty($depDate) ? strtotime($depDate . ', ' . $arrTime) : strtotime($date . ', ' . $arrTime);
            }
        }
        $it['TripSegments'][] = $seg;

        // Status
        if (preg_match('/has(?: unfortunately)? been (\w+)/i', $airInfo2, $matches)) {
            $it['Status'] = $matches[1];

            if (stripos($it['Status'], 'cancel') !== false) {
                $it['Cancelled'] = true;
            }
        }

        return [$it];
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function chechDateToCorrect(array $mathec)
    {
        if (!empty($mathec) && (!empty($mathec['Day']) || !empty($mathec['Day2'])) && (!empty($mathec['Month']) || !empty($mathec['Month2']))) {
            return [
                'Day'   => (!empty($mathec['Day'])) ? $mathec['Day'] : $mathec['Day2'],
                'Month' => (!empty($mathec['Month'])) ? $mathec['Month'] : $mathec['Month2'],
            ];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $res = null;
        $in = [
            '/.*, (?<Month>[^\d\W]{3,}) (?<Day>\d{1,2}), (?<Year>\d{4})[,]?/', // on Tuesday, September 29, 2015
        ];
        $out = [
            '$2 $1 $3',
        ];
        // if you need to translate into English the month
        //		$e = function($m){
        //			return $m['Day']. ' ' .$this->monthNameToEnglish($m['Month']). ' ' .$m['Year'];
        //		};
        //		$res = preg_replace_callback($in, $e, $str);
        //		return (!empty($res)) ? $res : null;
        return preg_replace($in, $out, $str);
    }

    private function t($s)
    {
        if (!isset(self::$dict) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
