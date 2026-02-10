<?php

namespace AwardWallet\Engine\edreams\Email;

class HTML extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-4028432.eml";
    public $reBody = [
        'en' => ['Travelport', 'Flight'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();
        $this->http->SetBody($text);
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if ($body && isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "noreply@edreams.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "noreply@edreams.com") !== false;
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
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $text = $this->http->Response['body'];
        $text = stristr($text, 'Reservation Number');
        $recordLocator = substr($text, 0, 50);

        if (preg_match("#Reservation Number\s+(\w+)\s+.+#", $recordLocator, $m)) {
            $it['RecordLocator'] = $m[1];
        }
        $text = stristr($text, 'Today\'s Date');
        $reservDate = substr($text, 0, 40);

        if (preg_match("#Today[\'s]*\s+Date:\s+(\d{2})\s+(\w+)\s+(\d{4})#", $reservDate, $math)) {
            $it['ReservationDate'] = strtotime($this->monthNameToEnglish($math[2]) . ' ' . $math[1] . ' ' . $math[3]);
        }
        $text = stristr($text, 'Traveller');
        $traveller = substr($text, 0, 50);

        if (preg_match("#Traveller[s]*\s+(\w+,\s+\w+)\n#", $traveller, $mathec)) {
            $it['Passengers'][] = $mathec[1];
        }
        $depart = stristr($text, 'Depart:');
        $depart = substr($depart, 0, 100);
        preg_match_all("#(Depart:)#", $depart, $roots, PREG_PATTERN_ORDER);
        $count = count($roots[1]);

        if ($count > 0) {
            for ($i = 0; $i < $count; $i++) {
                $seg = [];
                $text = stristr($text, 'Flight');

                if (preg_match("#Flight[ ]*-.*?\(([A-Z\d]{2})\)[ ]*-[ ]*\d{1,5}\s+#", $text, $m)) {
                    $seg['AirlineName'] = $m[1];
                }
                $flightDate = substr($text, 0, 80);

                if (preg_match("#.*-\s+\d+\s+\w+ (?<day>\d{2}) (?<month>\w+) (?<year>\d{4})#", $flightDate, $q)) {
                    $date = $this->monthNameToEnglish($q['month']) . ' ' . $q['day'] . ' ' . $q['year'];
                }
                $text = stristr($text, 'Depart:');
                $dep = substr($text, 0, 50);

                if (preg_match("#Depart:\s+(?<depTime>\d+:\d+)\s+(?<depName>[\w\s]*)\s+\((?<depCode>\w{3})\)#", $dep, $qt) && !empty($date)) {
                    $seg['DepName'] = $qt['depName'];
                    $seg['DepDate'] = strtotime($date . ' ' . $qt['depTime']);
                    $seg['DepCode'] = $qt['depCode'];
                }
                $text = stristr($text, 'Arrive');
                $arr = substr($text, 0, 50);

                if (preg_match("#Arrive:\s+(?<arrTime>\d+:\d+)\s+(?<arrName>[\w\s]*)\s+\((?<arrCode>\w{3})\)#", $arr, $qnt) && !empty($date)) {
                    $seg['ArrName'] = $qnt['arrName'];
                    $seg['ArrDate'] = strtotime($date . ' ' . $qnt['arrTime']);
                    $seg['ArrCode'] = $qnt['arrCode'];
                }
                $text = stristr($text, 'Flight');
                $flight = substr($text, 0, 50);

                if (preg_match("#Flight\s+(?<flightNumber>\d+)#", $flight, $qts)) {
                    $seg['FlightNumber'] = $qts['flightNumber'];
                }

                if (stristr($text, 'Class of Service')) {
                    $text = stristr($text, 'Class of Service');
                    $class = substr($text, 0, 50);
                    $seg['Cabin'] = (preg_match("#Class of Service:\s+(\w+)#", $class, $cnts)) ? $cnts[1] : null;
                }

                if (stristr($text, 'Flight Operated By')) {
                    $text = stristr($text, 'Flight Operated By');
                    $flightOperated = substr($text, 0, 50);
                    $seg['Operator'] = (preg_match("#Flight Operated By:\s+[\w\s]*\s+\((\w{2})\)#", $flightOperated, $c)) ? $c[1] : null;
                }

                if (stristr($text, 'Equipment')) {
                    $text = stristr($text, 'Equipment');
                    $aircraft = substr($text, 0, 30);
                    $seg['Aircraft'] = (preg_match("#Equipment:\s+(\w+ [\d-]+)#", $aircraft, $cts)) ? $cts[1] : null;
                }

                if (stristr($text, 'Flying Time')) {
                    $text = stristr($text, 'Flying Time');
                    $flyingTime = substr($text, 0, 30);
                    $seg['Duration'] = (preg_match("#Flying Time:\s+([\d:]+)#", $flyingTime, $s)) ? $s[1] : null;
                }

                if (stristr($text, 'Meal Service')) {
                    $text = stristr($text, 'Meal Service');
                    $meal = substr($text, 0, 30);
                    $seg['Meal'] = (preg_match("#Meal Service:\s+(\w+)#", $meal, $sum)) ? $m[1] : null;
                }

                if (stristr($text, 'Ticket Numbers')) {
                    $text = stristr($text, 'Ticket Numbers');
                    $ticketNums = substr($text, 0, 80);
                    $it['TicketNumbers'][] = (preg_match("#Ticket Numbers\s+[\(\)\w-]*\s+([\w]+)#", $ticketNums, $smr)) ? $smr[1] : null;
                }

                if (stristr($text, 'Status')) {
                    $text = stristr($text, 'Status');
                    $status = substr($text, 0, 30);
                    $it['Status'] = (preg_match("#Status\s+(Confirmed)#", $status, $sum)) ? $sum[1] : null;
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
