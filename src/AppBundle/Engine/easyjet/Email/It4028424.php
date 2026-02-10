<?php

namespace AwardWallet\Engine\easyjet\Email;

class It4028424 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "easyjet/it-4028424.eml, easyjet/it-4365253.eml, easyjet/it-4878837.eml";

    public $reFrom = 'donotreply@easyjet.com';
    public $reSubject = [
        'easyJet booking',
    ];
    public $reBody = 'easyJet';
    public $reBody2 = [
        'es' => 'Todas las horas mostradas son locales.',
        'nl' => 'Alle getoonde tijden zijn lokale tijden.',
        'it' => 'Tutti gli orari sono visualizzati in ora locale.',
    ];

    public static $dictionary = [
        'es' => [],
        'nl' => [
            'reserva de easyJet' => 'easyJet-boeking',
            'Vuelo'              => 'Vlucht',
            'a'                  => 'naar',
        ],
        'it' => [
            'reserva de easyJet' => 'prenotazione easyJet',
            'Vuelo'              => 'Volo',
        ],
    ];

    public $lang = 'es';

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(., '" . $this->t('reserva de easyJet') . "')])[1]", null, true, "#" . $this->t('reserva de easyJet') . "\s+(\w+)#");

        // TripNumber
        // Passengers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = '//text()[contains(.,"' . $this->t('Vuelo') . ' ")]/ancestor::dd[1]';
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode('.', $root, true, '/\s+\w{3}(\d+)$/');

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            // DepartureTerminal
            $from = $this->http->FindSingleNode('./preceding-sibling::dt[1]', $root, true, '/(.*?)\s+' . $this->t('a') . '\s+.+/');

            if (preg_match('/^(.+)\s+\(([^)(]*Terminal[^)(]*)\)$/i', $from, $matches)) {
                $itsegment['DepName'] = $matches[1];
                $itsegment['DepartureTerminal'] = $matches[2];
            } else {
                $itsegment['DepName'] = $from;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode('./following-sibling::dd[1]/descendant::text()[normalize-space(.)][2]', $root) . ', ' . $this->http->FindSingleNode('./following-sibling::dd[1]/descendant::text()[normalize-space(.)][3]', $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            // ArrivalTerminal
            $to = $this->http->FindSingleNode('./preceding::text()[normalize-space(.)][1]', $root, true, '/.*?\s+' . $this->t('a') . '\s+(.+)/');

            if (preg_match('/^(.+)\s+\(([^)(]*Terminal[^)(]*)\)$/i', $to, $matches)) {
                $itsegment['ArrName'] = $matches[1];
                $itsegment['ArrivalTerminal'] = $matches[2];
            } else {
                $itsegment['ArrName'] = $to;
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode('./following-sibling::dd[2]/descendant::text()[normalize-space(.)][2]', $root) . ', ' . $this->http->FindSingleNode('./following-sibling::dd[2]/descendant::text()[normalize-space(.)][3]', $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode('.', $root, true, '/\s+(\w{3})\d+$/');

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
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
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($itineraries);
        $result = [
            'emailType'  => 'reservations',
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
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        $year = date('Y', $this->date);
        $in = [
            "#^\w+,\s+(\d+\s+\w+\s+\d{4},\s+\d+:\d+)$#",
        ];
        $out = [
            "$1",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
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
