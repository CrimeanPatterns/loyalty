<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\budgetair\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "budgetair/it-6177691.eml";

    private $detectBody = [
        'Thank you for choosing BudgetAir.com',
    ];

    private $subj = [
        'BudgetAir.com Booking Request Acknowledgement',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => 'AirTravelPlaneEn',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@budgetair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subj as $s) {
            if (
                stripos($headers['from'], '@budgetair.com') !== false
                && stripos($headers['subject'], $s) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//td[contains(text(), 'Your Trip ID')]/following-sibling::td[1]");

        $it['Passengers'] = $this->http->FindNodes("//tr[contains(td, 'TRAVELER')]/following-sibling::tr[normalize-space(.)][1]/descendant::tr", null, '/\d*\.*\s*(.+)/iu');

        $total = $this->http->FindSingleNode("//td[contains(., 'Total') and not(descendant::td)]/following-sibling::td[1]", null, true, '/\D\s*(.+)/');
        $it['TotalCharge'] = str_replace([','], [''], $total);

        $it['Currency'] = $this->http->FindSingleNode("//td[contains(., 'Total') and not(descendant::td)]/following-sibling::td[2]");

        $xpath = "//img[contains(@src, 'logos/air/large/')]/ancestor::table[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match('/([A-Z]{2})\s*(\d+)/', $this->http->FindSingleNode('descendant::tr[1]/descendant::td[2]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $dep = $this->http->FindSingleNode('descendant::tr[1]/descendant::td[3]', $root);
            $arr = $this->http->FindSingleNode('descendant::tr[1]/descendant::td[4]', $root);
            $depArr = ['Dep' => $dep, 'Arr' => $arr];
            $re = '/\b([A-Z]{3})\b[\)]*\s+(.+)\s+(\d{1,2})-(\w+)-(\d{2})\s+\D+(\d{1,2}:\d{2}(?:am|pm))/ui';
            array_walk($depArr, function ($node, $key) use (&$seg, $re) {
                if (preg_match($re, $node, $m)) {
                    $seg[$key . 'Code'] = $m[1];
                    $seg[$key . 'Name'] = $m[2];
                    $seg[$key . 'Date'] = strtotime($m[3] . ' ' . $m[4] . ' ' . $m[5] . ', ' . $m[6]);
                }
            });

            $plane = $this->http->FindSingleNode('descendant::tr[2]/descendant::td[3]', $root);

            if (preg_match('/flight time\s*:\s*(.+)\s+stops\s*:\s*(\w+)/i', $plane, $m)) {
                $seg['Duration'] = $m[1];
                $seg['Stops'] = $m[2];
            }

            $flight = $this->http->FindSingleNode('descendant::tr[2]/descendant::td[4]', $root);

            if (preg_match('/class\s*:\s*(\w+)\s+aircraft\s*:\s*(.+)/i', $flight, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['Aircraft'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
