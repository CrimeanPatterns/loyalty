<?php

namespace AwardWallet\Engine\cheapnl\Email;

class It5913459 extends \TAccountChecker
{
    public $mailFiles = "cheapnl/it-10857467.eml, cheapnl/it-5913459.eml, cheapnl/it-7234360.eml";

    public static $dictionary = [
        'de' => [
            'Reservation Number' => 'Reservierungsnummer',
            'Traveller(s)'       => 'Reisende(n)',
            //			'Frequent Flyer number' => '',
            'Total'              => ['Gesamt', 'Total'],
            'Flight Information' => 'Fluginformationen',
            'Flight number'      => 'Flugnummer',
            'Duration'           => 'Flugzeit',
        ],
        'en' => [
            'Reservation Number' => ['Reservation Number', 'Order number'],
        ],
    ];

    protected $providerCode;
    protected $reFrom = [
        'cheapnl'   => '@cheaptickets.',
        'flugladen' => '@flugladen.de',
    ];
    protected $reSubject = [
        'de' => '- Bestätigung',
        'en' => '- Confirmation',
    ];

    protected $lang = '';

    protected $langDetectors = [
        'de' => ['Fluginformationen'],
        'en' => ['Flight Information'],
    ];

    public static function getEmailProviders()
    {
        return ['cheapnl', 'flugladen'];
    }

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        $flightInfoNodes = $this->http->XPath->query('//table[starts-with(normalize-space(.),"' . $this->t('Flight Information') . '")]');

        if ($flightInfoNodes->length === 0) {
            return false;
        }

        $flightInfoRoot = $flightInfoNodes->item(0);

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t('Reservation Number'), $flightInfoRoot);

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Flight Information')) . ']/following::text()[' . $this->contains($this->t('Reservation Number')) . '][1]/following::text()[normalize-space(.)][1]', null, true, '/^\s*([A-Z\d]{5,7})\s*$/');
        }
        // TripNumber
        $it['TripNumber'] = $this->nextText($this->t('Reservation Number'));

        // Passengers
        // AccountNumbers
        $passengers = [];
        $accountNumbers = [];
        $passengerRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('Traveller(s)')) . ']/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr[count(./descendant::text()[normalize-space(.)])=1]');
        $prefix = ["Ms", "Mr", "Miss", "Herr", "Frau"];

        if ($passengerRows->length === 0) {
            $passengerRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('Traveller(s)')) . ']/following::tr[' . $this->starts($prefix) . '][not(.//tr)]');
        }

        if ($passengerRows->length === 0) {
            $passengerRows = $this->http->XPath->query('//text()[normalize-space(.)="Traveller(s)"]/following::tr[contains(., "Ms") or contains(., "Mr") or contains(., "Miss")][not(.//tr)]');
        }

        foreach ($passengerRows as $passengerRow) {
            $passengers[] = $this->http->FindSingleNode('.', $passengerRow);
            // LX-999907138240068
            if ($accountNumber = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)="' . $this->t('Frequent Flyer number') . '"][1]/following::text()[1]', $passengerRow, true, '/^[A-Z]{2}[-\/ ]*[A-Z\d]{4,}$/')) {
                $accountNumbers[] = $accountNumber;
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = $passengers;
        }

        if (!empty($accountNumbers[0])) {
            $it['AccountNumbers'] = $accountNumbers;
        }

        // TicketNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t('Total'), null, 2));

        if (empty($it['TotalCharge'])) {
            $it['TotalCharge'] = (float) $this->nextText($this->t('Total'), null, 1, '/([\d\.]+)/');
        }

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t('Total')));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = '//text()[' . $this->eq($this->t('Flight Information')) . ']/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr[string-length(normalize-space(.))>1][1]//tr[4]/..';

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = '//text()[' . $this->eq($this->t('Flight Information')) . ']/ancestor::tr[./following-sibling::tr][1]/descendant::table[' . $this->contains($this->t('Duration')) . '][contains(@class, "segment")]';
        }
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info("segments root not found: $xpath");
        }

        foreach ($segments as $root) {
            $date = strtotime($this->normalizeDate($this->orval(
                $this->http->FindSingleNode('./tr[1]', $root),
                $this->http->FindSingleNode('preceding-sibling::p[1]', $root)
            )));

            $itsegment = [];

            // FlightNumber
            // AirlineName
            $flight = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t('Flight number')) . ']', $root);

            if (preg_match('/' . $this->t('Flight number') . '\s+([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];
            }

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode('descendant::tr[contains(., ":")][1]/descendant::table[not(.//table) and normalize-space(.)][1]', $root);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode('descendant::tr[contains(., ":")][1]/descendant::table[not(.//table) and normalize-space(.)][2]/descendant::text()[normalize-space(.)][1]', $root), $date);

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode('descendant::tr[contains(., ":")][1]/descendant::table[not(.//table) and normalize-space(.)][3]', $root);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode('descendant::tr[contains(., ":")][1]/descendant::table[not(.//table) and normalize-space(.)][2]/descendant::text()[normalize-space(.)][2]', $root), $date);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode('.//text()[' . $this->starts($this->t('Duration')) . ']', $root, true, '/' . $this->t('Duration') . '\s+(.+)/');

            // Meal
            // Smoking
            // Stops

            // DepCode
            // ArrCode
            $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $prov => $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                $this->providerCode = $prov;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->reFrom as $prov => $reFrom) {
            if (stripos($headers['subject'], substr($reFrom, 1)) !== false) {
                $finded = true;
                $this->providerCode = $prov;

                break;
            }
        }

        if ($finded === false) {
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
        foreach ($this->reFrom as $prov => $reFrom) {
            $condition1 = $this->http->XPath->query('//node()[contains(.,"' . substr($reFrom, 1) . '")]')->length === 0;
            $condition2 = $this->http->XPath->query('//a[contains(@href,' . substr($reFrom, 1) . ')]')->length === 0;

            if ($condition1 && $condition2) {
                continue;
            }
            $this->providerCode = $prov;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $this->assignLang();

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ConfirmationFlight_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (isset($this->providerCode)) {
            $result['providerCode'] = $this->providerCode;
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function orval(...$items)
    {
        foreach ($items as $item) {
            if (!empty($item)) {
                return $item;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $n = 1, $re = null)
    {
        $rule = $this->eq($field);

        if (!empty($root)) {
            return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root, true, $re);
        } else {
            return $this->http->FindSingleNode("(//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", null, true, $re);
        }
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
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
