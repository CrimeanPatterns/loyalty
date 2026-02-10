<?php

namespace AwardWallet\Engine\dat\Email;

use AwardWallet\Engine\MonthTranslate;

class ChangeNotification extends \TAccountChecker
{
    public $mailFiles = "dat/it-10961806.eml";

    protected $reFrom = '@dat.dk';
    protected $reSubject = [
        'DAT Flight change notification',
    ];

    protected $reBody = 'DAT';

    protected $reBody2 = [
        'en' => ['schedule change for the booking'],
    ];

    protected $lang = '';
    protected static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = strip_tags($parser->getHtmlBody());
        }
        $its = $this->parseEmail($body);
        $result = [
            'emailType'  => 'ChangeNotification',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getHtmlBody();
        }

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseEmail($text)
    {
        $it = ['Kind' => 'T'];

        // RecordLocator
        if (preg_match("#for the booking\s+([A-Z\d]{5,7})\s*:#", $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        // TripNumber
        // ConfirmationNumbers

        // Passengers
        // TicketNumbers
        // AccountNumbers
        // TripSegments
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // Fees
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        preg_match_all('#\n\s*Flight:.+\n.+Arrival time.+#', $text, $flights);

        foreach ($flights[0] as $key => $flight) {
            $seg = [];

            if (preg_match("#Flight:\s*(?<date>[\d\.]+),\s*(?<al>[A-Z\d]{2})\s*(?<fl>\d{1,5})\s+\((?<depCode>[A-Z]{3})\s*-\s*(?<arrCode>[A-Z]{3})\).*"
                    . "\n.+Departure\s*time\s.+?to\s+(?<depTime>\d{1,2}:\d{2}).+Arrival time\s.+?to\s+(?<arrTime>\d{1,2}:\d{2})#", $flight, $m)) {
                $date = $m['date'];

                // FlightNumber
                $seg['FlightNumber'] = $m['fl'];

                // DepCode
                $seg['DepCode'] = $m['depCode'];

                // DepName
                // DepartureTerminal
                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($date . ' ' . $m['depTime']));

                // ArrCode
                $seg['ArrCode'] = $m['arrCode'];

                // ArrName
                // ArrivalTerminal
                // ArrDate
                $seg['ArrDate'] = strtotime($this->normalizeDate($date . ' ' . $m['arrTime']));

                // AirlineName
                $seg['AirlineName'] = $m['al'];
            }
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
            // Operator
            // Gate
            // ArrivalGate
            // BaggageClaim
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^(\d+)\.(\d+).(\d{2})\s+(\d+:\d+)$#", //23.04.17 18:05
        ];
        $out = [
            "$1.$2.20$3 $4",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }
}
