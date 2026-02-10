<?php

namespace AwardWallet\Engine\alaskaair\Email;

use PlancakeEmailParser;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-12408174.eml";

    private $subjects = [
        'en' => ['Group fare agreement:'],
    ];

    private $detects = [
        'This agreement sets forth the terms and conditions under which Alaska Airlines agrees to allow you to reserve',
    ];

    private $from = '/[@.]alaskaair\.com/';

    private $prov = 'Alaska Airlines';

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
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(): array
    {
        $its = [];
        $airs = [];
        $baseFares = [];

        foreach ($this->http->XPath->query("//td[starts-with(normalize-space(.), 'CONFIRMATION CODE') and not(.//td)]") as $node) {
            /** @var \DOMNode $node */
            if (preg_match('/:\s*(\w+)/', $node->nodeValue, $m)) {
                $baseFares[$m[1]] = $this->http->FindSingleNode("ancestor::td[1]/following-sibling::td[1]/descendant::text()[starts-with(normalize-space(.), 'Per Person Base Fare excluding Taxes')]/following-sibling::node()[1]", $node);

                foreach ($this->http->XPath->query("ancestor::tr[3]/preceding-sibling::tr[not(contains(normalize-space(.), 'Flight')) and not(contains(normalize-space(.), 'Itinerary'))]", $node) as $segment) {
                    $airs[$m[1]][] = $segment;
                }
            }
        }

        foreach ($airs as $rl => $air) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T'];

            if (isset($baseFares[$rl]) && preg_match('/([\d\.]+)\s*([A-Z]{3})/', $baseFares[$rl], $m)) {
                $it['BaseFare'] = $m[1];
                $it['Currency'] = $m[2];
            }

            $it['RecordLocator'] = $rl;

            foreach ($air as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $this->getNode($root), $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                foreach ([
                    'Dep' => $this->getNode($root, 2, null, true),
                    'Arr' => $this->getNode($root, 3, null, true),
                ] as $key => $value) {
                    if (preg_match("/(.+)\n(\w+\s+\d{1,2},\s+\d{2,4})\n(\d{1,2}:\d{2}\s*[ap]m)/i", $value, $m)) {
                        $seg[$key . 'Name'] = $m[1];
                        $seg[$key . 'Date'] = strtotime($m[2] . ', ' . $m[3]);
                        $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                }

                $it['TripSegments'][] = $seg;
            }

            $its[] = $it;
        }

        return $its;
    }

    private function getNode(\DOMNode $root, int $td = 1, ?string $re = null, bool $descendant = false): ?string
    {
        if (!$descendant) {
            return $this->http->FindSingleNode("descendant::tr[count(td)=4]/td[{$td}]", $root, true, $re);
        } else {
            return implode("\n", $this->http->FindNodes("descendant::tr[count(td)=4]/td[{$td}]/descendant::text()[normalize-space(.)]", $root, $re));
        }
    }
}
