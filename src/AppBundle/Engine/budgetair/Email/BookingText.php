<?php

namespace AwardWallet\Engine\budgetair\Email;

class BookingText extends \TAccountChecker
{
    use \PriceTools;
    public $mailFiles = "budgetair/it-5187484.eml";
    protected $retext;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getBody();
        $this->retext = $body;
        $its = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "BookingText",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();

        return stripos($text, 'Budgetair.co.uk') !== false && stripos($text, 'Booking Confirmation') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers["subject"], "BudgetAir.co.uk - Booking Confirmation") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "Budgetair.co") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function clearText($t)
    {
        $t = preg_replace('#[\n\r]#', ' ', $t);
        $t = str_replace('  ', '', $t);

        return trim($t);
    }

    protected function re($re, $text = null, $index = 1)
    {
        if (!$text) {
            $text = $this->retext;
        }

        if (preg_match($re, $text, $m)) {
            return $m[$index] ?? $m[0];
        } else {
            return null;
        }
    }

    private function parseEmail($body)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re('#Airline\sreference:\s+\*(\w+)\*#');

        if (preg_match_all('#^\s+Passenger\s+\d+$\n+^(.+?)\s+\(.+$#m', $body, $m)) {
            $it['Passengers'] = $m[1];
        }

        if (($tmp = $this->re('#Ticket\s+Number\(s\)\s+(\d+\-\d+)#'))) {
            $it['TicketNumbers'] = $tmp;
        }

        if (preg_match('#Total\s+(?P<Currency>[^\d]+)\s*(?P<TotalCharge>[\d\.,]+)#s', $body, $m)) {
            $it['Currency'] = $this->currency($m['Currency']);
            $it['TotalCharge'] = $m['TotalCharge'];
        }
        $flightsDate = preg_split('#\s{4,}(\w+\s+\d{1,2}\s+[A-Z][a-z]+\s+\d{4})#', $this->re('#(\s{4,}\w+\s+\d{1,2}\s+[A-Z][a-z]+\s+\d{4}.+?)\s*\<http:\/\/cars\.budgetair#s'), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($flightsDate); $i += 2) {
            $flights = preg_split('#(Flight\snumber:\s\*[A-Z\d]{2}\d+\*)#', $flightsDate[$i + 1], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            for ($j = 0; $j < count($flights); $j += 2) {
                $seg = [];

                if (isset($flights[$j + 1]) && preg_match('#(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#', $flights[$j + 1], $m)) {
                    $seg['AirlineName'] = $m['AirlineName'];
                    $seg['FlightNumber'] = $m['FlightNumber'];
                }

                if (preg_match('#(?:Flight\s+duration\s+\d+h\s+\d+m)?\s+(?P<DTime>\d{1,2}:\d{2})\s+(?P<DDate>\d{1,2}\s+\w{3})\s+(?P<DepName>.+?)\s+(?P<Duration>\d{1,2}:\d{2})\s+(?P<ATime>\d{1,2}:\d{2})\s+(?P<ADate>\d{1,2}\s+\w{3})\s+(?P<ArrName>.+)#s', $flights[$j], $m)) {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['DepName'] = $this->clearText($m['DepName']);
                    $seg['DepDate'] = strtotime($flightsDate[$i] . ' ' . $m['DTime']);
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrName'] = $this->clearText($m['ArrName']);
                    $seg['ArrDate'] = strtotime($flightsDate[$i] . ' ' . $m['ATime']);
                    $seg['Duration'] = $m['Duration'];
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }
}
