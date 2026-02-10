<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Engine\MonthTranslate;

class TravellerItinerary extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-8356924.eml, amadeus/it-8497537.eml, amadeus/it-8537857.eml";

    private $reFrom = '@amadeus.';
    private $reSubject = [
        'Traveller(s) Itinerary - Booking Reference:',
    ];
    private $lang = '';
    private $reBody = [
        'fr' => ['Itineraire du passager'],
        'en' => ['Traveller(s) Itinerary'],
    ];
    private static $dict = [
        'en' => [
            //			'Booking Reference:' => '',
            //			'Traveller(s) Information' => '',
            //			'E-Ticket Numbers' => '',
            //			'Flight' => '',
            //			'Departure Date:' => '',
            //			'Arrival Date:' => '',
            'Depart:'  => ['Depart:', 'Departure:'],
            'Arrive:'  => ['Arrive:', 'Arrival:'],
            'Airline:' => ['Airline:', 'Flight No.'],
            //			'Class / Cabin:' => '',
            //			'Cabin:' => '',
            //			'Duration:' => '',
            //			'Meal:' => '',
        ],
        'fr' => [
            'Booking Reference:'       => 'Référence de réservation:',
            'Traveller(s) Information' => 'Information passager',
            'E-Ticket Numbers'         => 'Billet électronique',
            'Flight'                   => 'Vol',
            'Departure Date:'          => 'Date de départ:',
            'Arrival Date:'            => "Date d'arrivée:",
            'Depart:'                  => 'Départ:',
            'Arrive:'                  => 'Arrivée:',
            'Airline:'                 => 'Compagnie aérienne / numéro de vol:',
            //			'Class / Cabin:' => '',
            'Cabin:'    => 'Cabine',
            'Duration:' => 'Temps de vol:',
            //			'Meal:' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["from"]) && stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detect($parser->getHTMLBody());
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->lang = $this->detect($parser->getHTMLBody())) {
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TravellerItinerary' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function detect($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (is_array($reBody)) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($body, $reBody) !== false) {
                return $lang;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[" . $this->xpathArray($this->t('Booking Reference:')) . "])[1]", null, true, "#" . $this->t('Booking Reference:') . "\s*([A-Z\d]+)\s*#");

        $it['Passengers'] = $this->http->FindNodes("//text()[contains(., '" . $this->t('Traveller(s) Information') . "')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]//td[not(.//td) and normalize-space(.)]");
        $it['TicketNumbers'] = array_unique($this->http->FindNodes("//text()[contains(., '" . $this->t('E-Ticket Numbers') . "')]/ancestor::tr[2]/following-sibling::tr//td[2]", null, "#^\s*([\d\-]+)\s*$#"));

        $xpath = "//text()[" . $this->xpathArray($this->t('Arrive:')) . "]/ancestor::table[contains(.,'" . $this->t('Flight') . "')][1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found');

            return false;
        }

        foreach ($roots as $root) {
            $seg = [];
            $date = $this->http->FindSingleNode("(.//text()[contains(., '" . $this->t('Departure Date:') . "')]/following::text()[normalize-space(.)][1])[1]", $root, true, "#(\d{1,2}\s*\w+\s*\d{4})#u");

            if (empty($date)) {
                $nodes = $this->http->FindNodes("(.//tr[1])[1]", $root, "#(\d{1,2}\s*\w+\s*\d{4})#");
                $date = array_shift($nodes);
            }

            $timeDep = $this->http->FindSingleNode("(.//text()[" . $this->xpathArray($this->t('Depart:')) . "]/following::text()[normalize-space(.)][1])[1]", $root, true, "#(\d{1,2}:\d{2})#");
            $timeArr = $this->http->FindSingleNode("(.//text()[" . $this->xpathArray($this->t('Arrive:')) . "]/following::text()[normalize-space(.)][1])[1]", $root, true, "#(\d{1,2}:\d{2})#");

            if (!empty($timeDep)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($date . ' ' . $timeDep, false));
            }

            if (!empty($timeArr)) {
                $dateArr = $this->http->FindSingleNode("(.//text()[contains(., \"" . $this->t('Arrival Date:') . "\")]/following::text()[normalize-space(.)][1])[1]", $root, true, "#(\d{1,2}\s*\w+\s*\d{4})#u");

                if (!empty($dateArr)) {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($dateArr . ' ' . $timeArr, false));
                } else {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($date . ' ' . $timeArr, false));
                }
            }

            $depName = $this->http->FindSingleNode("(.//text()[" . $this->xpathArray($this->t('Depart:')) . "]/following::text()[normalize-space(.)][2])[1]", $root);

            if (preg_match("#(.+)\s*,\s*Terminal\s*(.*)#", $depName, $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['DepartureTerminal'] = $m[2];
            } else {
                $seg['DepName'] = $depName;
            }

            $arrName = $this->http->FindSingleNode("(.//text()[" . $this->xpathArray($this->t('Arrive:')) . "]/following::text()[normalize-space(.)][2])[1]", $root);

            if (preg_match("#(.+)\s*,\s*Terminal\s*(.*)#", $arrName, $m)) {
                $seg['ArrName'] = trim($m[1]);
                $seg['ArrivalTerminal'] = $m[2];
            } else {
                $seg['ArrName'] = $arrName;
            }

            $flight = $this->http->FindSingleNode("(.//text()[" . $this->xpathArray($this->t('Airline:')) . "]/following::text()[normalize-space(.)][1])[1]", $root);

            if (preg_match("#([^/]*)\s*/\s*([A-Z\d]{2})(\d+)#", $flight, $m)) {
                $seg['Operator'] = trim($m[1]);
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];
            }
            $seg['Aircraft'] = $this->http->FindSingleNode("(.//text()[contains(., '" . $this->t('Aircraft:') . "')]/following::text()[normalize-space(.)][1])[1]", $root);

            $class = $this->http->FindSingleNode("(.//text()[contains(., '" . $this->t('Class / Cabin:') . "')]/following::text()[normalize-space(.)][1])[1]", $root);

            if (preg_match("#([A-Z]{1,2})\s*/\s*(.+)#", $class, $m)) {
                $seg['BookingClass'] = trim($m[1]);
                $seg['Cabin'] = $m[2];
            }

            if (empty($seg['Cabin'])) {
                $seg['Cabin'] = $this->http->FindSingleNode("(.//text()[contains(., '" . $this->t('Cabin:') . "')]/following::text()[normalize-space(.)][1])[1]", $root);
            }

            if (isset($seg['FlightNumber']) && isset($seg['DepDate']) && isset($seg['ArrDate'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $seg['Duration'] = $this->http->FindSingleNode("(.//text()[contains(., '" . $this->t('Duration:') . "')]/following::text()[normalize-space(.)][1])[1]", $root);
            $seg['Meal'] = $this->http->FindSingleNode("(.//text()[contains(., '" . $this->t('Meal:') . "')]/following::text()[normalize-space(.)][1])[1]", $root);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function xpathArray($array, $str1 = 'normalize-space(.)', $method = 'contains', $operator = 'or')
    {
        $arr = [];

        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($array as $str2) {
            $arr[] = "{$method}({$str1},\"{$str2}\")";
        }

        return join(' ' . $operator . ' ', $arr);
    }

    private function normalizeDate($str)
    {
        //		$in = [
        //			"#^\s*(\d{1,2})\s*(\w+)\.?\s*(\d{4})\s*(\d+:\d+)\s*$#u", //03 Septembre 2017
        //		];
        //		$out = [
        //			"$1 $2 $3 $4",
        //		];
        //		$str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
