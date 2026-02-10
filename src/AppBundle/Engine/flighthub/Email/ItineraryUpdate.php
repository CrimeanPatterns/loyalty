<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\flighthub\Email;

class ItineraryUpdate extends \TAccountChecker
{
    public $mailFiles = "flighthub/it-9008527.eml";

    private $detects = [
        'This is to inform you that there has been a minor change to your flight schedule',
    ];

    private $from = '@flighthub.com';

    private $provider = 'flighthub';

    private $lang = 'en';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $classParts = explode('\\', __CLASS__);

        return [
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
            'emailType' => end($classParts) . ucfirst($this->lang),
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

        if (stripos($body, $this->provider) === false) {
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

        $it['RecordLocator'] = $this->http->FindSingleNode("//node()[contains(text(), 'SCHEDULE CHANGE NOTIFICATION FOR RESERVATION')]", null, true, '/\#\s*\b([A-Z\d\-]{5,})\b/');

        $xpath = "//tr[contains(., 'Airline') and contains(., 'Class')]/following-sibling::tr[string-length(normalize-space(.))>2]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['AirlineName'] = $this->node($root);

            $seg['FlightNumber'] = $this->node($root, 2, '/(\d+)/');

            $seg['BookingClass'] = $this->node($root, 3);

            if (preg_match('/([A-Z]{3})\s*-\s*([A-Z]{3})/', $this->node($root, 4), $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }

            foreach ([
                'Departure' => $this->node($root, 5),
                'Arrival' => $this->node($root, 6),
            ] as $key => $value) {
                if (preg_match('/(\d{1,2}[A-Z]+\d{2,4})\s*-\s*(\d+:\d+)\s*(?:[\[]*terminal\s+([A-Z\d]{1,3}))?/i', $value, $m)) {
                    $seg[substr($key, 0, 3) . 'Date'] = $this->normalizeDate($m[1] . ', ' . $m[2]);
                    $seg[$key . 'Terminal'] = !empty($m[3]) ? $m[3] : null;
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/^(\d{1,2})\s*([A-Z]+)\s*(\d{2,4})\s*,\s*(\d+:\d+)$/i',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function node(\DOMNode $root, $td = 1, $re = null)
    {
        return $this->http->FindSingleNode("td[{$td}]", $root, true, $re);
    }
}
