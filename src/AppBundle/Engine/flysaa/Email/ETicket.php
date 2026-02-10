<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\flysaa\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "flysaa/it-8057241.eml";

    private $lang = '';

    private $detects = [
        'Thank you for booking your flight with Travelstart',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flysaa.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'travelstart.com')]")->length <= 0) {
            return false;
        }
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'flysaa.com') !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//tr[contains(., 'Check-in reference') and not(.//tr)]", null, true, '/:\s*([A-Z\d]{5,7})/');

        $booking = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Booked on') and contains(normalize-space(.), 'Ticket number') and not(.//td)]");

        if (preg_match('/booking reference:\s*(\w+)\s*Ticket number:\s*([\d\-]+)\s*Booked on:\s*(.+)/', $booking, $m)) {
            $br = $m[1];
            $it['TicketNumbers'][] = $m[2];
            $it['ReservationDate'] = strtotime($m[3]);
        }

        $it['Passengers'] = $this->http->FindNodes("//tr[contains(., 'Passengers') and not(.//tr)]/following-sibling::tr[contains(., 'Ms') or contains(., 'Mr') or contains(., 'Miss') or contains(., 'Adult')]");

        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Total amount')]");

        if (preg_match('/([A-Z]{3})\s*([\d\.]+)/', $total, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = (float) $m[2];
        }

        $xpath = "//tr[contains(., 'Itinerary')]/following-sibling::tr[contains(., 'Check-in reference')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $fnum = $this->getNode($root, 2);

            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s*(\w+)/', $fnum, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Cabin'] = $m[3];
            }

            $depArr = [
                'Dep' => array_unique($this->http->FindNodes("descendant::tr[count(td)=3]/td[1]//text()[normalize-space(.)]", $root)),
                'Arr' => array_unique($this->http->FindNodes("descendant::tr[count(td)=3]/td[3]//text()[normalize-space(.)]", $root)),
            ];

            $re = '/(.+)\s+\w+,\s+(\d+ \w+ \d+, \d+:\d+)/s';
            array_walk($depArr, function ($val, $key) use (&$seg, $re) {
                if (preg_match($re, implode("\n", $val), $m)) {
                    $seg[$key . 'Name'] = preg_replace('/\s+/', ' ', $m[1]);
                    $seg[$key . 'Date'] = strtotime($m[2]);
                }
            });

            if (preg_match('/Operated by\s*:\s*(.+)/', $root->nodeValue, $m)) {
                $seg['Operator'] = trim($m[1]);
            }

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode(\DOMNode $root, $td = 1)
    {
        return $this->http->FindSingleNode("descendant::tr[count(td)=3]/td[" . $td . "]", $root);
    }
}
