<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\egencia\Email;

class AirTicketText extends \TAccountChecker
{
    public $mailFiles = "egencia/it-5602933.eml";

    private $detectBody = 'Thank you for choosing Egencia';

    private $subject = 'Egencia cancellation confirmation';

    private $from = '@customercare.egencia.com';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail($parser->getPlainBody());

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketText',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (is_string($this->detectBody) && stripos($body, $this->detectBody) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false
            && isset($headers['subject']) && stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, $this->from) !== false;
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        if (stripos($text, 'The flight shown below has been successfully canceled.') !== false) {
            $it['Status'] = 'Canceled';
        }

        $psng = $this->cutText('Traveler', 'Egencia itinerary number', $text);

        if (!empty($psng) && preg_match('/Traveler:\s+(.+)/i', $psng, $m)) {
            $it['Passengers'][] = trim($m[1]);
        }

        $recLoc = $this->cutText('Egencia itinerary number', 'Airline ticket number', $text);

        if (!empty($recLoc) && preg_match('/ID:\s+([A-Z0-9]{6,7})/', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $ticketNumber = $this->cutText('Airline ticket number', 'Delta confirmation code', $text);

        if (!empty($ticketNumber) && preg_match('/Airline ticket number\(s\):\s+(\d+)/', $ticketNumber, $m)) {
            $it['AccountNumbers'] = $m[1];
        }

        $segments = [];

        if (strpos($text, 'Flight:') !== false) {
            $segments = explode('Flight:', $text);
            array_shift($segments);
        }

        if (empty($segments)) {
            $this->logger->info('Segments not found by explode');

            return false;
        }

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $re = '/(?<AName>[a-z\s]+)\s+(?<FNum>\d+)\s*(?:\/\s*\d+)?\s+from\s+(?<DepName>.+)\s+to\s+(?<ArrName>.+)\s+Depart/is';

            if (preg_match($re, $segment, $m)) {
                $seg['AirlineName'] = trim($m['AName']);
                $seg['FlightNumber'] = $m['FNum'];
                $seg['DepName'] = trim($m['DepName']);
                $seg['ArrName'] = preg_replace('/\s+/', ' ', $m['ArrName']);
            }

            $depDate = $this->cutText('Depart', 'Arrive', $segment);
            $reDate = '/.*\w+\s+(\d+-\w+-\d+)\s+at\s+(\d{1,2}:\d+\s+(?:pm|am))/i';

            if (!empty($depDate) && preg_match($reDate, $depDate, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ', ' . $m[2]);
            }

            $arrDate = substr($segment, stripos($segment, 'Arrive'));

            if (preg_match($reDate, $arrDate, $m)) {
                $seg['ArrDate'] = strtotime($m[1] . ', ' . $m[2]);
            }

            if (isset($seg['FlightNumber']) && isset($seg['DepDate']) && isset($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }
}
