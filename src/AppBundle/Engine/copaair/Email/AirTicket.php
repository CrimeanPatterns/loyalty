<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\copaair\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "copaair/it-4975506.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketEs',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@copaair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@copaair.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(normalize-space(.), 'Thank you for choosing Copa Airlines')]")->length > 0;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//span[contains(., 'Your reservation code')]/following-sibling::span[1]");
        $xpath = '//tr[count(th) > 6]/following-sibling::tr';
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->log(LOG_LEVEL_NORMAL, 'Segments not found');

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['FlightNumber'] = $this->http->FindSingleNode('td[2]', $root);
            $seg['AirlineName'] = $this->http->FindSingleNode('td[3]', $root);
            $date = $this->http->FindSingleNode('td[4]', $root);
            $depTime = $this->http->FindSingleNode('td[7]', $root, true, '/(\d+:\d+ (?:am|pm))/i');
            $arrTime = $this->http->FindSingleNode('td[8]', $root, true, '/(\d+:\d+ (?:am|pm))/i');
            $date = \DateTime::createFromFormat('m/d/Y', $date);

            if (is_object($date)) {
                $date->format('d/m/Y');
                $depDate = clone $date;
                $arrDate = clone $date;
                $depDate->modify($depTime);
                $arrDate->modify($arrTime);
                $seg['DepDate'] = ($depDate) ? $depDate->getTimestamp() : null;
                $seg['ArrDate'] = ($arrDate) ? $arrDate->getTimestamp() : null;
            }
            $seg['DepName'] = $this->http->FindSingleNode('td[5]', $root);
            $seg['ArrName'] = $this->http->FindSingleNode('td[6]', $root);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
