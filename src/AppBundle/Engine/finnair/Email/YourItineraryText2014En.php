<?php

namespace AwardWallet\Engine\finnair\Email;

class YourItineraryText2014En extends \TAccountChecker
{
    public $mailFiles = "finnair/it-4274413.eml";

    protected $dateYear = null;
    protected $result = [];

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL]).
     *
     * @param $input
     * @param $searchStart
     * @param $searchFinish
     *
     * @return bool|string
     */
    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->dateYear = date('Y', strtotime($parser->getDate()));

        $body = empty(trim($parser->getHTMLBody())) ? $parser->getPlainBody() : $parser->getHTMLBody();
        $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (stripos($headers['from'], 'dontreply@finnair.com') !== false
                                            || stripos($headers['from'], 'finnair.callcenter.se@finnair.com') !== false)
                && isset($headers['subject']) && stripos($headers['subject'], 'Your Itinerary, Booking Ref') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = empty(trim($parser->getHTMLBody())) ? $parser->getPlainBody() : $parser->getHTMLBody();

        return strpos($body, 'This is an automated message from Finnair') != false
                || strpos($body, '**IN FINNAIR.COM ->') != false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@finnair.com') !== false;
    }

    protected function parseEmail($body)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->findСutSection($body, 'BOOKING REF ', PHP_EOL);
        $this->parsePassengers($this->findСutSection($body, 'RESERVATION NUMBER(S)', 'Tama on Finnair Customer Care'));

        if (!isset($this->result['Passengers']) || empty($this->result['Passengers'])) {
            $this->parsePassengers($this->findСutSection($body, 'RESERVATION NUMBER(S)', '**IN FINNAIR.COM ->'));
        }
        $this->parseSegments($this->findСutSection($body, 'ARRIVE', 'RESERVATION NUMBER'));
    }

    protected function parsePassengers($body)
    {
        // MALKI/RACHID MR     TICKET:AY/ETKT 105 2453559012
        if (preg_match_all('#(\w+\/\w+).*TICKET:\s*(.*)#', $body, $matches)) {
            $this->result['Passengers'] = $matches[1];
            $this->result['TicketNumbers'] = $matches[2];
        }

        if (preg_match_all('#FREQUENT FLYER\s*(.*)#', $body, $matches)) {
            $this->result['AccountNumbers'] = $matches[1];
        }
    }

    protected function parseSegments($body)
    {
        foreach (preg_split('/\n{2,}/', $body, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $this->result['TripSegments'][] = $this->segment($value);
        }
    }

    protected function segment($text)
    {
        $segment = [];

        // FINNAIR - AY 834
        if (preg_match('/-\s*([A-Z]{2})\s*(\d{2,4})/s', $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        // FRI 06JUN      HELSINKI FI         STOCKHOLM SE           2010     2010
        if (preg_match('/(\d+\s*[A-Z]{3})\s{2,}(.*?)\s{2,}(.*?)\s+(\d{4})\s+(\d{4})/', $text, $matches)) {
            $date = strtotime($matches[1] . $this->dateYear);
            $segment['DepName'] = $matches[2];
            $segment['ArrName'] = $matches[3];
            $segment['DepDate'] = strtotime($matches[4], $date);
            $segment['ArrDate'] = strtotime($matches[5], $date);

            while ($segment['ArrDate'] < $segment['DepDate']) {
                $segment['ArrDate'] = strtotime('+1 day', $segment['ArrDate']);
            }
        }

        // NON STOP       TERMINAL 1          TERMINAL 2             DURATION 10:40
        if (preg_match('/TERMINAL\s+(\d+)\s+TERMINAL\s+(\d+)\s+DURATION\s+(\d+:\d+)/', $text, $matches)) {
            $segment['DepartureTerminal'] = $matches[1];
            $segment['ArrivalTerminal'] = $matches[2];
            $segment['Duration'] = $matches[3];
        }

        // RESERVATION CONFIRMED - W ECONOMY
        if (preg_match('/RESERVATION\s+(\w+)\s*-\s*([A-Z])\s+(\w+)/', $text, $matches)) {
            $segment['BookingClass'] = $matches[2];
            $segment['Cabin'] = $matches[3];
        }

        // FLIGHT OPERATED BY BE FLYBE
        if (preg_match('/FLIGHT OPERATED BY\s+(.*)/', $text, $matches)) {
            $segment['Operator'] = $matches[1];
        }

        // EQUIPMENT:EMBRAER 190
        if (preg_match('/EQUIPMENT:\s*(.*)/', $text, $matches)) {
            $segment['Aircraft'] = $matches[1];
        }

        // SEAT 05C NO SMOKING CONFIRMED MALKI/RACHID MR(ADT)
        if (preg_match('/SEAT\s+([A-Z\d]{2,3})/', $text, $matches)) {
            $segment['Seats'] = $matches[1];
        }

        $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

        return $segment;
    }
}
