<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\easyjet\Email;

use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-10226402.eml";

    private $detects = [
        'We are pleased to confirm that one or more of your standby tickets below has been converted to a confirmed seat',
    ];

    private $from = '/[@.]easyjet\.com/i';

    private $lang = 'en';

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query("//a[contains(@href, 'myeasyjet')]")->length === 0) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $it['Passengers'][] = $this->http->FindSingleNode("//p[contains(., 'Dear') and not(.//p)]", null, true, '/Dear\s+(.+),/');

        $xpath = "//tr[contains(., 'Flight') and not(.//tr)]/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $seg['FlightNumber'] = $this->getNode($root);

            if (!empty($seg['FlightNumber'])) {
                $seg['AirlineName'] = 'U2';
            }

            $re = '/(.+)\s+\(\s*([A-Z]{3})\s*\w*\)/';
            $re2 = '/(.+)\s+Terminal\s+([A-Z\d]{1,5})/';
            $re3 = '/(\w+)/';
            $node = $this->getNode($root, 2);

            if (preg_match($re, $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
            } elseif (preg_match($re2, $node, $m) || preg_match($re3, $node, $m)) {
                $seg['DepName'] = $m[1];

                if (!empty($m[2])) {
                    $seg['DepartureTerminal'] = $m[2];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $node = $this->getNode($root, 3);

            if (preg_match($re2, $node, $m) || preg_match($re3, $node, $m)) {
                $seg['ArrName'] = $m[1];

                if (!empty($m[2])) {
                    $seg['ArrivalTerminal'] = $m[2];
                }
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            } elseif (preg_match($re, $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }

            $seg['DepDate'] = strtotime($this->getNode($root, 4));

            $seg['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode(\DOMNode $root, int $td = 1, string $re = null)
    {
        return $this->http->FindSingleNode("td[{$td}]", $root, true, $re);
    }
}
