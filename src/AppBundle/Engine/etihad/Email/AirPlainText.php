<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\etihad\Email;

class AirPlainText extends \TAccountChecker
{
    public $mailFiles = "etihad/it-6614392.eml";

    private $detects = [
        'Click here to see the itinerary',
    ];

    private $passengerNames = [];

    private $from = 'etihad';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $header = $parser->getHeader('subject');

        if (preg_match_all('/(m[sir]\s+[a-z\s]+)/i', $header, $m)) {
            $this->passengerNames = $m[1];
        }

        return [
            'emailType'  => 'AirPlainTextEn',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, $this->from) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getNode('Reservation Code:', '/:\s+([A-Z\d]{5,7})/');

        $it['Passengers'] = $this->passengerNames;

        $it['Status'] = $this->getNode('Status', '/:\s*(\w+)/');

        $ticketNumbers = $this->getNode('Ticket Number', '/:\s+(.+)/');

        if (preg_match_all('/(\d+)/', $ticketNumbers, $m)) {
            $it['TicketNumbers'] = $m[1];
        }

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $flight = $this->getNode('Flight:');

        if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }

        $dep = $this->getNode('From:');
        $arr = $this->getNode('To:');
        $depArr = ['Dep' => $dep, 'Arr' => $arr];
        $re = '/:\s+(.+)\s+\(([A-Z]{3})\)/iu';
        array_walk($depArr, function ($val, $key) use (&$seg, $re) {
            if (preg_match($re, $val, $m)) {
                $seg[$key . 'Name'] = $m[1];
                $seg[$key . 'Code'] = $m[2];
            }
        });
        $seg['DepDate'] = strtotime($this->getNode('Departs', '/:\s+(.+)/'));
        $seg['ArrDate'] = strtotime($this->getNode('Arrives', '/:\s+(.+)/'));

        $seg['DepartureTerminal'] = $this->getNode('Departing Terminal', '/terminal\s+([A-Z\d]{1,3})/i');
        $seg['ArrivalTerminal'] = $this->getNode('Arrival Terminal', '/terminal\s+([A-Z\d]{1,3})/i');

        $seg['Cabin'] = $this->getNode('Class', '/:\s+(\w+)/');

        $seg['Meal'] = $this->getNode('Meals', '/:\s+(.+)/');

        $seg['Aircraft'] = $this->getNode('Aircraft', '/:\s+(\w+)/');

        $seg['Stops'] = $this->getNode('Stop(s)', '/:\s+(\d+)/');

        $seg['Duration'] = $this->getNode('Duration', '/:\s+(.+)/');

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function getNode($str, $re = null)
    {
        return $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), '" . $str . "')])[last()]", null, true, $re);
    }
}
