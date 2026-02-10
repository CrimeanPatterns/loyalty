<?php

namespace AwardWallet\Engine\airchina\Email;

class It3966463 extends \TAccountChecker
{
    public $mailFiles = "airchina/it-290784675.eml, airchina/it-3966463.eml, airchina/it-50784847.eml, airchina/it-63071886.eml, airchina/it-63072032.eml";

    public static $dictionary = [
        "zh" => [
            'tableHeader' => [
                'New flight reservation details:',
                'Flight(s) below have been cancelled.', 'Flight(s) below have been canceled.',
            ],
            'cancelledTexts' => ['Flight(s) below have been cancelled.', 'Flight(s) below have been canceled.'],
            'statusVariants' => ['changed', 'cancelled', 'canceled'],
        ],
    ];

    public $lang = "zh";

    //	use \DateTimeTools;
    //	use \PriceTools;

    private $reFrom = "@airchina.com";

    private $reSubject = [
        "zh"=> "国航航班变更通知",
        "en"=> "Flight Change notice of Air China", "Air China Schedule Change Email",
    ];

    private $reBody = 'Air China';

    private $reBody2 = [
        "zh"  => "Flight(s) below have been changed",
        'zh2' => 'Important notice of scheduled flight changes',
    ];

    public function parseFlight(&$itineraries): void
    {
        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->getField("Original flight reservation details:", "/^\s*([A-Z\d]{5,7})\s*$/");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Original flight reservation details:')][1]", null, true, '/Original flight reservation details:[ ]*([A-Z\d]{5,9})$/');
        }

        if (empty($it['RecordLocator'])
            && !empty($this->http->FindSingleNode("//text()[normalize-space(.) = 'Original flight reservation details:']/following::text()[normalize-space()][1]", null, true, '/^\s*(原来的订座信息：|Passenger Name: )\s*$/'))
        ) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // Passengers
        if ($name = $this->getField("Passenger Name:")) {
            $it['Passengers'][] = $name;
        }

        if ($name = $this->getField("Ticket Number:")) {
            $it['TicketNumbers'][] = $name;
        }

        if (empty($it['Passengers'])) {
            $cnt1 = $this->http->XPath->query("//tr[starts-with(normalize-space(),'Passenger Name') and contains(normalize-space(),'Ticket Number')]/preceding-sibling::tr[normalize-space()]")->length;
            $cnt2 = $this->http->XPath->query("//tr[not(.//tr) and starts-with(normalize-space(),'Flight No.') and contains(normalize-space(),'From') and (following-sibling::tr[{$this->starts($this->t('tableHeader'))}])]/preceding-sibling::tr[normalize-space()]")->length;
            $cnt = $cnt2 - $cnt1;
            $it['Passengers'] = array_filter($this->http->FindNodes("//tr[starts-with(normalize-space(),'Passenger Name') and contains(normalize-space(),'Ticket Number')]/following-sibling::tr[normalize-space()][position()<{$cnt}]/td[1]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'));
            $it['TicketNumbers'] = array_filter($this->http->FindNodes("//tr[starts-with(normalize-space(),'Passenger Name') and contains(normalize-space(),'Ticket Number')]/following-sibling::tr[normalize-space()][position()<{$cnt}]/td[2]", null, '/^\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2}$/'));
        }

        // it-3966463.eml, it-50784847.eml
        if (!$this->parseSegments1($it)) {
            // it-63072032.eml, it-63071886.eml
            $this->parseSegments2($it);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseFlight($itineraries);

        $result = [
            'emailType'  => 'Flight' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        // types segments: 1_1, 1_2, 2
        return 3;
    }

    private function parseSegments1(&$it): bool
    {
        $it['TripSegments'] = [];

        $subFormats = [
            1 => "descendant::tr[1]/td[2][contains(.,'Date')]", // it-3966463.eml
            2 => "descendant::tr[1]/td[2][contains(.,'Class')]", // it-50784847.eml
        ];

        foreach ($subFormats as $i => $str) {
            $xpath = "//text()[{$this->starts($this->t('tableHeader'))}]/following::table[1][{$str}]//tr[position()>1 and td[string-length(normalize-space())>1]]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length === 0) {
                $this->logger->debug("Segments type 1_{$i} not found by XPath: $xpath");
            } else {
                $this->logger->debug("[XPATH]: $xpath");
                $subFormat = $i;

                break;
            }
        }

        if ($segments->length === 0) {
            return false;
        }

        foreach ($segments as $root) {
            $itsegment = [];

            if (empty($this->http->FindSingleNode("td[1]", $root))
                && !empty($this->http->FindSingleNode('td[2]', $root, true, '/^([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\s*$/'))
            ) {
                $nodesToStip = $this->http->XPath->query("*[1]", $root);

                foreach ($nodesToStip as $nodeToStip) {
                    $nodeToStip->parentNode->removeChild($nodeToStip);
                }
            }

            if ($subFormat === 2) {
                $num = 3;
                $node = $this->http->FindSingleNode("./td[2]", $root);

                if (preg_match("/^[A-Z]{1,2}$/", $node)) {
                    $itsegment['BookingClass'] = $node;
                } else {
                    $itsegment['Cabin'] = $node;
                }
            } else {
                $num = 2;
            }

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[{$num}]", $root)));

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('td[1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $itsegment['AirlineName'] = $m['name'];
                $itsegment['FlightNumber'] = $m['number'];
            }

            // DepName
            $num++;
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[{$num}]", $root);

            // ArrName
            $num++;
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[{$num}]", $root);

            // DepDate
            if (!empty($date)) {
                $num++;
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[{$num}]", $root), $date);
            }

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;
        }

        return true;
    }

    private function parseSegments2(&$it): bool
    {
        $it['TripSegments'] = [];
        $xpathTableHeader = "starts-with(normalize-space(),'Flight No.') and contains(normalize-space(),'From') and not(.//tr)";
        $xpath = "//tr[{$this->starts($this->t('tableHeader'))} and not(.//tr)]/following-sibling::tr[normalize-space()][position()<3][{$xpathTableHeader}]/following-sibling::tr[normalize-space() and *[5] and not(contains(normalize-space(),'Operated')) and not(contains(normalize-space(),'Important notes'))]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("Segments type 2 not found by XPath: {$xpath}");

            return false;
        }
        $tableTitle = $this->http->FindSingleNode("preceding-sibling::tr[{$xpathTableHeader}][1]/preceding-sibling::tr[position()<3][{$this->starts($this->t('tableHeader'))}]", $segments->item(0));

        if (preg_match("/{$this->opt($this->t('cancelledTexts'))}/i", $tableTitle)) {
            // it-63071886.eml
            $it['Cancelled'] = true;
        }

        if (preg_match("/\b({$this->opt($this->t('statusVariants'))})\b/i", $tableTitle, $m)) {
            $it['Status'] = $m[1];
        }

        foreach ($segments as $root) {
            $seg = [];
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/', $this->http->FindSingleNode('td[1]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $re = '/'
                . '(?<Name>.+?)[ ]+\(\s*(?<Code>[A-Z]{3})\s*\)'
                . '(?:\s+Terminal[ ]+(?<Terminal>[A-Z\d]{1,5}|Not Assigned))?'
                . '\s+(?<Time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)'
                . '/s';
            $n = implode("\n", $this->http->FindNodes('./td[2]/descendant::text()[normalize-space(.)]', $root));

            if (preg_match($re, $n, $m)) {
                $seg['DepName'] = preg_replace('/\s+/', ' ', $m['Name']);
                $seg['DepCode'] = $m['Code'];

                if (!empty($m['Terminal']) && $m['Terminal'] !== 'Not Assigned') {
                    $seg['DepartureTerminal'] = $m['Terminal'];
                }
                $seg['DepDate'] = strtotime($m['Time'], $date);
            }
            $n = implode("\n", $this->http->FindNodes('./td[3]/descendant::text()[normalize-space(.)]', $root));

            if (preg_match($re, $n, $m)) {
                $seg['ArrName'] = preg_replace('/\s+/', ' ', $m['Name']);
                $seg['ArrCode'] = $m['Code'];

                if (!empty($m['Terminal']) && $m['Terminal'] !== 'Not Assigned') {
                    $seg['ArrivalTerminal'] = $m['Terminal'];
                }
                $seg['ArrDate'] = strtotime($m['Time'], $date);
            }
            $class = $this->http->FindSingleNode('td[4]', $root);

            if (preg_match('/^[A-Z]{1,2}$/', $class)) {
                $seg['BookingClass'] = $class;
            } else {
                $seg['Cabin'] = $class;
            }
            $operator = $this->http->FindSingleNode('td[6]', $root);

            if ($operator && !preg_match('/^Air China$/i', $operator)) {
                $seg['Operated'] = $operator;
            }
            $it['TripSegments'][] = $seg;
        }

        return true;
    }

    private function getField($field, $regexp = null)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$field}']/following::text()[string-length(normalize-space(.))>1][1]", null, true, $regexp);
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{4})/(\d{2})/(\d{1,2})\s*$#",
            '/^(\d{1,2})\-([A-Z]{3})\-(\d{2,4})$/', // 17-JAN-2020
        ];
        $out = [
            "$3.$2.$1",
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $str);
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
