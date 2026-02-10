<?php

namespace AwardWallet\Engine\delta\Email;

class Updated extends \TAccountChecker
{
    public $mailFiles = "delta/it-4.eml, delta/it-6391039.eml";

    // delta updated itinerary email, with 'Updated Itinerary'
    // subject: Your Delta itinerary has changed
    // it-4.eml

    // subject: URGENT Message about your upcoming flight

    public function ParseItinerary(\PlancakeEmailParser $parser)
    {
        $it = ['Kind' => 'T'];

        $emailDate = strtotime($parser->getHeader('date'));

        if (!$emailDate) {
            return null;
        }
        $emailYear = date('Y', $emailDate);

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'confirmation #')]", null, true, '/confirmation #\s*([A-Z\d]{6})/i');

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = trim($this->http->FindPreg("#Delta confirmation\s*\#\s*([\w\f\-]+)#"));
        }

        if ($passengers = $this->http->FindSingleNode("//*[starts-with(text(), 'Dear ')]")) {
            $passengers = preg_replace("/^Dear /", "", $passengers);
            $passengers = str_replace(" and ", ", ", $passengers);
            $passengers = trim($passengers, " ,");
            $it['Passengers'] = explode(", ", $passengers);
        }

        $nodes = $this->http->XPath->Query("//table//table");

        for ($i = 0; $i < $nodes->length; $i++) {
            $table = $nodes->item($i);

            $seg = [];

            if (!strtotime($date = $this->http->FindSingleNode("preceding-sibling::p[1]", $table))) {
                continue;
            }
            $tbody = "";

            if ($this->http->XPath->query("tbody", $table)->length > 0) {
                $tbody = "tbody/";
            }

            $flight = $this->http->FindSingleNode($tbody . "tr/td/*[contains(.,'Flight:')]/ancestor::td[1]/following-sibling::td[1]", $table);

            if ($pos = stripos($flight, 'Operated by')) {
                $flight = substr($flight, 0, $pos - 1);
            }

            if (preg_match("/^(.+) (\d+)$/", $flight, $m)) {
                $seg['FlightNumber'] = $m[2];
                $seg['AirlineName'] = $m[1];
            } else {
                $seg['FlightNumber'] = $flight;
            }
            $seg['Seats'] = $this->http->FindSingleNode($tbody . "tr/td/*[contains(.,'Seats:')]/ancestor::td[1]/following-sibling::td[1]", $table);
            $seg['Cabin'] = $this->http->FindSingleNode($tbody . "tr/td/*[contains(.,'Cabin:')]/ancestor::td[1]/following-sibling::td[1]", $table);

            $depNameTime = $this->http->FindSingleNode($tbody . "tr/td/*[contains(.,'Departs:')]/ancestor::td[1]/following-sibling::td[1]", $table);
            $arrNameTime = $this->http->FindSingleNode($tbody . "tr/td/*[contains(.,'Arrives:')]/ancestor::td[1]/following-sibling::td[1]", $table);

            if (preg_match("#^(.*?)\s+from\s+(.*?)$#", $depNameTime, $m)) {
                $seg['DepName'] = $m[2];
                $seg['DepDate'] = strtotime($this->clearDayOfWeek($date . ' ' . $emailYear . ', ' . $m[1]));
            }

            if (preg_match("#^(.*?)\s+at\s+(.*?)$#", $arrNameTime, $m)) {
                $seg['ArrName'] = $m[2];
                $seg['ArrDate'] = strtotime($this->clearDayOfWeek($date . ' ' . $emailYear . ', ' . $m[1]));

                if (preg_match("#^(.*?)\((\w+\s*\d+)\)$#", $m[1], $m)) {
                    $seg['ArrDate'] = strtotime($this->clearDayOfWeek($m[2] . ', ' . ' ' . $emailYear . ', ' . $m[1]));
                }
            }
            correctDates($seg['DepDate'], $seg['ArrDate'], $emailDate);

            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseItinerary($parser);

        return [
            'parsedData' => ["Itineraries" => [$its]],
            'emailType'  => "UpdatedItinerary",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Your Delta itinerary has changed') !== false
            || isset($headers['from']) && stripos($headers['from'], 'DeltaMessenger@delta.com') !== false
            || (isset($headers['from']) && stripos($headers['from'], 'DeltaAirLines@Delta.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'URGENT Message about your upcoming flight') !== false)
            ;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Updated Itinerary - Delta confirmation') !== false
            || stripos($body, 'to update your Secure Flight Passenger Data') !== false;
    }

    private function clearDayOfWeek($date)
    {
        return trim(preg_replace("/^Monday\,?|^Tuesday\,?|^Wednesday\,?|^Thursday\,?|^Friday\,?|^Saturday\,?|^Sunday\,?/", "", $date), ' ,');
    }

    //	private function realTime($time) {
//		if (!empty($time) && $time < strtotime('- 4 months'))
//			$time = strtotime("+ 1 year", $time);
//		return $time;
//	}
}
