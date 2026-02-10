<?php

namespace AwardWallet\Engine\british\Email;

class BookingText extends \TAccountChecker
{
    public $mailFiles = "british/it-1575229.eml, british/it-2004683.eml, british/it-2011791.eml, british/it-5151139.eml";
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
        $text = $parser->getBody();

        return stripos($text, 'British Airways') !== false && stripos($text, 'http://www.britishairways.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers["subject"], "BA e-ticket receipt") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "ba.com") !== false || stripos($from, "britishairways.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
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
        $it['RecordLocator'] = $this->re('#British\sAirways\sBooking\sreference:\s+(\w+)#');

        if (preg_match_all('#^(?P<Passengers>.+)$#m', $this->re('#Passenger\(s\)\n+(.+?)Flight Number\:#s', $body), $m)) {
            $it['Passengers'] = $m['Passengers'];
        }

        if (($tmp = $this->re('#Ticket\s+Number\(s\)\s+(\d+\-\d+)#'))) {
            $it['TicketNumbers'] = $tmp;
        }

        if (preg_match('#Total\s+Paid\s+(?P<Currency>\w+)\s+(?P<TotalCharge>[\d\.,]+)#', $body, $m)) {
            $it['Currency'] = $m['Currency'];
            $it['TotalCharge'] = $m['TotalCharge'];
        }
        $flights = preg_split('#(Flight\s+Number:\s+\w{2}\s*\d+)#', $this->re('#(Flight\s+Number:\s+\w{2}\s*\d+.+)Please\s+note\s+that\s+all\s+flights\s+are\s+designated#s'), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($flights); $i += 2) {
            $seg = [];

            if (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#", $flights[$i], $m)) {
                $seg['AirlineName'] = $m['AirlineName'];
                $seg['FlightNumber'] = $m['FlightNumber'];
            }

            foreach (['From' => 'DepName', 'To' => 'ArrName', 'Depart' => 'DepDate', 'Arrive' => 'ArrDate', 'Cabin' => 'Cabin', 'OPERATED BY' => 'Operator'] as $k => $v) {
                if (($tmp = $this->re('#' . $k . ':\s+(.+)\n+#i', $flights[$i + 1]))) {
                    if (strpos($v, 'Name') !== false) {
                        if (preg_match('#(?P<Name>.+?)\s+Terminal\s+(?P<Terminal>\d+)#', $tmp, $m)) {
                            $seg[$v] = $m['Name'];
                            $seg[((strpos($v, 'Dep') !== false) ? 'Departure' : 'Arrival') . 'Terminal'] = $m['Terminal'];
                        } else {
                            $seg[$v] = $tmp;
                        }
                        $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    } elseif (strpos($v, 'Date') !== false) {
                        $seg[$v] = strtotime($tmp);
                    } else {
                        $seg[$v] = $tmp;
                    }
                }
            }

            if ($seg['DepName'] !== "To:") { //filter out unsuitable segments
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }
}
