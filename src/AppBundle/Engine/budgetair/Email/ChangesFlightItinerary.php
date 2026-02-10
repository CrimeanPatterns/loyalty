<?php

namespace AwardWallet\Engine\budgetair\Email;

class ChangesFlightItinerary extends \TAccountChecker
{
    public static $dictionary = [
        "en" => [],
    ];
    public $mailFiles = "budgetair/it-4688330.eml, budgetair/it-4688339.eml";
    public $reFrom = "BudgetAir";
    public $reSubject = [
        "en" => "Changes to your flight itinerary",
    ];
    public $reBody = 'BudgetAir.';
    public $reBody2 = [
        "en" => "We have received a schedule change",
    ];
    public $status = "";

    public $lang = "en";

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
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

        foreach ($this->reBody2 as $lang => $re) {
            if (mb_strpos(html_entity_decode($this->http->Response["body"]), $re, 0, 'UTF-8') !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ChangesFlightItinerary',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//b[normalize-space(.)='" . $this->t('Reservation number') . "']/ancestor-or-self::td[1]//following-sibling::td[1]/font", null, false, "#[A-Z\d]+#");

        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//b[normalize-space(.)='" . $this->t('Sent on') . "']/ancestor-or-self::td[1]//following-sibling::td[1]/font", null, true, "#\d{1,2}\/\d{1,2}\/\d{2,4}\s+\d{1,2}:\d{2}#i")));

        $it['Passengers'] = $this->http->FindNodes("//table[tr[contains(.,'" . $this->t('Passenger name') . "')]]//tr[not(contains(.,'" . $this->t('Passenger name') . "'))]/td[1]");

        if (!isset($it['Passengers']) || (isset($it['Passengers']) && count($it['Passengers']) == 0)) {
            $it['Passengers'] = $this->http->FindNodes("//table/following::tr[contains(.,'Passenger name')]/following-sibling::tr/td[1]");
        }

        $nodes = $this->http->XPath->query("//table[tr[contains(.,'" . $this->t('Flight') . "')]]//tr[not(contains(.,'" . $this->t('Flight') . "'))]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//table//*[tr[contains(.,'" . $this->t('Flight') . "')]]//tr[not(contains(.,'" . $this->t('Flight') . "'))]");
        }

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        $seg = [];
        $prevRaw = "";

        foreach ($nodes as $i => $root) {
            //recognize raw type
            if (preg_match("#(Departure|Arrival|Terminal)#", $root->nodeValue, $mm)) {
                $dataNodes = $this->http->FindNodes("./td[normalize-space()]", $root);

                switch ($mm[1]) {
                    case 'Departure':
                        if ($prevRaw == 'Arrival' || $prevRaw == 'Terminal') {
                            $it['TripSegments'][] = $seg;
                            $seg = [];
                        }

                        if (preg_match("#(.+?)\(([A-Z]{3})\)#", $dataNodes[1], $m)) {
                            $seg['DepName'] = $m[1];
                            $seg['DepCode'] = $m[2];
                        }

                        if (preg_match('#([A-Z\d]{2})(\d+)#', $dataNodes[5], $m)) {
                            $seg['FlightNumber'] = $m[2];
                            $seg['AirlineName'] = $m[1];
                        }
                        $this->flightDate = $this->normalizeDate($dataNodes[0]);
                        $seg['DepDate'] = strtotime($this->flightDate . ' ' . $dataNodes[3]);
                        $this->status = trim($dataNodes[6]);

                        break;

                    case 'Arrival':
                        if (count($dataNodes) > 3 && preg_match('/\d-\w+-\d+/', $dataNodes[0])) {
                            array_shift($dataNodes);
                        }

                        if (preg_match("#(.+?)\(([A-Z]{3})\)#", $dataNodes[0], $m)) {
                            $seg['ArrName'] = $m[1];
                            $seg['ArrCode'] = $m[2];
                        }
                        $seg['ArrDate'] = strtotime($this->flightDate . ' ' . $dataNodes[2]);

                        if ($seg['ArrDate'] < $seg['DepDate']) {
                            $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
                        }

                        if (count($dataNodes) == 4 && ($op = re("#OPERATED BY\s+(.+)#i", $dataNodes[3]))) {
                            $seg['Operator'] = $op;
                        }

                        break;

                    case 'Terminal':
                        $seg[$prevRaw . 'Terminal'] = $dataNodes[1];

                        break;
                }
                $prevRaw = $mm[1];
            }
        }
        $it['TripSegments'][] = $seg;
        $it['Status'] = $this->status;
        $itineraries[] = $it;
    }

    public function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
        if (preg_match("#[a-z]#i", $str)) {
            $str = str_replace('-', ' ', $str);
        } else {
            $str = str_replace('/', '-', $str);
        }

        return $str;
    }
}
