<?php

namespace AwardWallet\Engine\cheapnl\Email;

class It2463449 extends \TAccountChecker
{
    public $mailFiles = "cheapnl/it-2463449.eml, cheapnl/it-6308975.eml, cheapnl/it-6324666.eml, cheapnl/it-6369022.eml, cheapnl/it-7075611.eml, cheapnl/it-7075616.eml";

    protected $detectSubject = [
        'de' => ['Ihr elektronisches Flugticket CheapTickets'],
        'nl' => ['E-ticket CheapTickets'],
    ];

    protected $detectBody = [
        'de' => ['Agency Reference', 'Wir wünschen Ihnen eine angenehme Reise'],
        'nl' => ['Agency Reference', 'Wij wensen je een prettige reis'],
    ];
    protected $year;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

        $global = [];
        $global['RecordLocator'] = $this->http->FindSingleNode('//text()[normalize-space(.)="Agency Reference"]/following::text()[normalize-space(.)!=""][1]', null, true, '/[A-Z\d]{5,7}/');
        $global['Passengers'] = $this->http->FindNodes('//td[normalize-space(.)="Passengers" and not(.//td)]/ancestor::tr[count(./td)=3][1]/following-sibling::tr[count(./td)=3]/td[1]');
        $global['TicketNumbers'] = $this->http->FindNodes('//*[(name()="b" or name()="strong") and starts-with(normalize-space(.),"Ticket Nummer") and contains(.,":")]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=""][1]', null, '/^\s*([-\d\s]{2,})\s*$/');

        $its = [];

        if ($air = $this->parseAir()) {
            $its[] = $air;
        }

        if ($train = $this->parseTrain()) {
            $its[] = $train;
        }

        foreach ($its as $key => $it) {
            foreach ($global as $name => $value) {
                $its[$key][$name] = $value;
            }
        }

        return [
            'emailType'  => 'ETicket',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cheaptickets.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'info@cheaptickets.') !== false) {
            return true;
        }

        foreach ($this->detectSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getPlainBody();

        if (empty($textBody)) {
            $textBody = $parser->getHTMLBody();
        }

        if (stripos($textBody, 'info@cheaptickets.') === false && stripos($textBody, 'CheapTickets.de') === false && stripos($textBody, 'CheapTickets.nl') === false && stripos($textBody, 'CheapTickets.be') === false) {
            return false;
        }

        foreach ($this->detectBody as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($textBody, $phrase) === false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['de', 'nl'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function parseSegment($root)
    {
        $patterns = [
            'time'     => '/^(\d{2})(\d{2})\s+hrs$/i',
            'date'     => '/^\s*(\w{3,}\s+\d{1,2})\s*$/iu',
            'terminal' => '/^\s*TERMINAL\s*([A-Z\d]{1,2})\s*$/i',
        ];

        $seg = [];

        $flight = $this->http->FindSingleNode('./preceding-sibling::tr[.//tr[count(./td)>1] and normalize-space(.)!=""  and not(contains(.,"From"))][1]', $root);

        if (preg_match('/^(.+)\s+Flight\s+(\d+)\s+([\w\s]+)$/i', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
            $seg['Cabin'] = $matches[3];
        } elseif (preg_match('/Train\s+Number\s+(\d+)\s+([\w\s]+)$/i', $flight, $matches)) {
            $seg['FlightNumber'] = $matches[1];
            $seg['Cabin'] = $matches[2];
        }

        $operator = $this->http->FindSingleNode('./preceding-sibling::tr[starts-with(normalize-space(.),"Operated by") and count(./td)=1 and position()=1]', $root);

        if (preg_match('/^\s*Operated\s+by\s+([\w\s]+)\s*$/i', $operator, $matches)) {
            $seg['Operator'] = $matches[1];
        }

        $departureTexts = $this->http->FindNodes('.//td[normalize-space(.)="From:" and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]/descendant::text()[normalize-space(.)!=""]', $root);
        $seg['DepName'] = $departureTexts[0];
        $depDateValues = explode(',', $departureTexts[1]);

        if (count($depDateValues) >= 2) {
            if (preg_match($patterns['time'], trim($depDateValues[0]), $matches)) {
                $timeDep = $matches[1] . ':' . $matches[2];
            }

            if (preg_match($patterns['date'], trim($depDateValues[count($depDateValues) - 1]), $matches)) {
                $dateDep = $matches[1];
            }

            if (isset($timeDep) && isset($dateDep) && $timeDep && $dateDep) {
                $dateDep = strtotime($dateDep . ', ' . $this->year);
                $seg['DepDate'] = strtotime($timeDep, $dateDep);
            } else {
                return [];
            }

            if (isset($departureTexts[2]) && preg_match($patterns['terminal'], $departureTexts[2], $matches)) {
                $seg['DepartureTerminal'] = $matches[1];
            }
        } else {
            $seg['DepDate'] = MISSING_DATE;

            if (preg_match($patterns['terminal'], $departureTexts[1], $matches)) {
                $seg['DepartureTerminal'] = $matches[1];
            }
        }

        $arrivalTexts = $this->http->FindNodes('.//td[normalize-space(.)="To:" and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]/descendant::text()[normalize-space(.)!=""]', $root);
        $seg['ArrName'] = $arrivalTexts[0];
        $arrDateValues = explode(',', $arrivalTexts[1]);

        if (count($arrDateValues) >= 2) {
            if (preg_match($patterns['time'], trim($arrDateValues[0]), $matches)) {
                $timeArr = $matches[1] . ':' . $matches[2];
            }

            if (preg_match($patterns['date'], trim($arrDateValues[count($arrDateValues) - 1]), $matches)) {
                $dateArr = $matches[1];
            }

            if (isset($timeArr) && isset($dateArr) && $timeArr && $dateArr) {
                $dateArr = strtotime($dateArr . ', ' . $this->year);
                $seg['ArrDate'] = strtotime($timeArr, $dateArr);
            } else {
                return [];
            }

            if (isset($arrivalTexts[2]) && preg_match($patterns['terminal'], $arrivalTexts[2], $matches)) {
                $seg['ArrivalTerminal'] = $matches[1];
            }
        } else {
            $seg['ArrDate'] = MISSING_DATE;

            if (preg_match($patterns['terminal'], $arrivalTexts[1], $matches)) {
                $seg['ArrivalTerminal'] = $matches[1];
            }
        }

        if (isset($seg['DepDate']) && isset($seg['ArrDate']) && $seg['DepDate'] === MISSING_DATE && $seg['ArrDate'] === MISSING_DATE) {
            return [];
        }

        $seatsTexts = $this->http->FindNodes('.//td[normalize-space(.)="Seats:" and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]/descendant::text()', $root, '/^\s*(\d{1,2}[A-Z]).+Confirmed\s*$/i');
        $seatsValues = array_filter($seatsTexts);

        if (isset($seatsValues[0]) && $seatsValues[0]) {
            $seg['Seats'] = implode(', ', $seatsValues);
        }

        if ($equipment = $this->http->FindSingleNode('.//td[normalize-space(.)="Equipment:" and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]', $root)) {
            $seg['Aircraft'] = $equipment;
        }

        if ($duration = $this->http->FindSingleNode('.//td[normalize-space(.)="Duration:" and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]', $root)) {
            $seg['Duration'] = $duration;
        }

        $meal = $this->http->FindSingleNode('.//td[normalize-space(.)="Meals:" and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]', $root);

        if ($sMeal = $this->http->FindSingleNode('.//td[normalize-space(.)="Special Meals:" and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]', $root)) {
            $sMealValue = trim(explode(',', $sMeal)[0]);
        }

        if ($meal && isset($sMealValue) && $sMealValue) {
            $seg['Meal'] = $meal . ', ' . $sMealValue;
        } elseif ($meal) {
            $seg['Meal'] = $meal;
        } elseif (isset($sMealValue) && $sMealValue) {
            $seg['Meal'] = $sMealValue;
        }

        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

        return $seg;
    }

    protected function parseAir()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[.//text()[normalize-space(.)="From:"] and count(descendant::tr)=0]/ancestor::table[1][.//text()[normalize-space(.)="To:"]]/ancestor::tr[1][not(contains(normalize-space(./preceding-sibling::tr[normalize-space()][1]),"Train Number"))]');

        foreach ($segments as $segment) {
            $seg = $this->parseSegment($segment);

            if (count($seg) > 0) {
                $it['TripSegments'][] = $seg;
            }
        }

        return count($it['TripSegments']) > 0 ? $it : null;
    }

    protected function parseTrain()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"TRAIN")]/following-sibling::tr[.//text()[normalize-space(.)="From:"] and .//text()[normalize-space(.)="To:"]][1]');

        foreach ($segments as $segment) {
            $it['TripSegments'][] = $this->parseSegment($segment);
        }

        return count($it['TripSegments']) > 0 ? $it : null;
    }
}
