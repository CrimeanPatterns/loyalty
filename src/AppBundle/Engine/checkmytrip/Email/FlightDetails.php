<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\checkmytrip\Email;

class FlightDetails extends \TAccountChecker
{
    public $mailFiles = "checkmytrip/it-10105517.eml";

    private $from = '@bargainairticket.com';

    private $prov = 'Bargain Air Ticket';

    private $detects = [
        'Thank you for choosing Bargain Air Ticket and for trusting your travel plans with us',
    ];

    private $lang = 'en';

    private $year = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->prov) === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
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

        $it['RecordLocator'] = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Agency Confirmation') and not(.//td)]", null, true, '/Agency Confirmation #\s*:\s*([A-Z\d]{5,7})/');

        $it['Passengers'] = $this->http->FindNodes("//img[contains(@src, 'images/icons/MR') or contains(@src, 'images/icons/MS') or contains(@src, 'images/icons/MRS')]/following-sibling::*[1]");

        $total = $this->http->FindSingleNode("//p[contains(., 'Your booking total is') and not(.//p)]");

        if (preg_match('/(\D)\s*([\d\.\,]+)/', $total, $m)) {
            $it['Currency'] = str_replace(['$'], ['USD'], $m[1]);
            $it['TotalCharge'] = (float) str_replace([','], [''], $m[2]);
        }

        $xpath = "//tr[(contains(., 'Departure') or contains(., 'Return')) and count(td)>4]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $info = [
                'info'  => $this->http->FindSingleNode('descendant::tr[1]/td[normalize-space(.)][2]', $root),
                'air'   => $this->http->FindSingleNode('descendant::tr[1]/td[normalize-space(.)][3]', $root),
                'cabin' => $this->http->FindSingleNode('descendant::tr[1]/td[normalize-space(.)][5]', $root),
            ];
            $re = '/(?<dtime>\d+:\d+\s*[ap]m)\s*(?<dmonth>\w+)\s+(?<dday>\d{2,4})\s*[\—,\-]\s*(?<dname>.+)\s*[\—,\-]\s*(?<atime>\d+:\d+\s*[ap]m)\s*(?<amonth>\w+)\s+(?<aday>\d{2,4})\s*[\—,\-]\s*(?<aname>.+)/i';
            $tire = chr(226) . chr(128) . chr(148);
            $info['info'] = str_replace($tire, '-', $info['info']);

            if (preg_match($re, $info['info'], $m)) {
                $seg['DepDate'] = strtotime($m['dday'] . ' ' . $m['dmonth'] . ' ' . $this->year . ', ' . $m['dtime']);
                $seg['ArrDate'] = strtotime($m['aday'] . ' ' . $m['amonth'] . ' ' . $this->year . ', ' . $m['atime']);
                $seg['DepName'] = $m['dname'];
                $seg['ArrName'] = $m['aname'];
            }

            if (preg_match('/([a-z\s]+)\s*Airline\s*\#(\d+)\s*Terminal\s*(?:([A-Z\d]{1,3})|\s*\-\s*)Terminal\s*(?:([A-Z\d]{1,3})|\s*\-\s*)/i', $info['air'], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (!empty($m[3])) {
                    $seg['DepartureTerminal'] = $m[3];
                }

                if (!empty($m[4])) {
                    $seg['ArrivalTerminal'] = $m[4];
                }
            }

            if (!empty($seg['DepName']) && !empty($seg['ArrName']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $seg['Cabin'] = $info['cabin'];

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
