<?php

namespace AwardWallet\Engine\aviancataca\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-6881201.eml";

    private $detects = [
        'E-TICKET NUMBER',
    ];

    private $year = '';
    // private $dateFirstFlight; // for aviancataca

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $result = [
            'emailType'  => 'FlightTicketEn',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];

        // if (isset($this->dateFirstFlight) && $this->dateFirstFlight >= strtotime(('2019-07-01'))) {
        //     $result['providerCode'] = 'aviancataca';
        // }

        return $result;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'avianca.com.br') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'avianca.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, 'tam.com.br') !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getNode('RECORD LOCATOR', '/([A-Z\d]{5,7})/');

        $it['ReservationDate'] = strtotime($this->normalizeDate($this->getNode('Issue date', '/(\d{1,2}\s*\w+\s*\d{2})/')));

        $it['Passengers'][] = $this->getNode('NAME', '/:\s*(.+)/');

        $xpath = "//tr[contains(., 'FROM') and contains(., 'TO')]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $i => $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $res = [
                '/from\s+(?<DName>.+)\s+(?<DCode>[A-Z]{3})\s+to\s+(?<AName>.+)\s+(?<ACode>[A-Z]{3})/i',
                '/from\s+(?<DName>.+)\s+(?<DCode>[A-Z]{3})\s+to\s+(?<AName>.+)/i',
                '/from\s+(?<DName>.+)\s+to\s+(?<AName>.+)\s+(?<ACode>[A-Z]{3})/i',
                '/from\s+(?<DName>.+)\s+to\s+(?<AName>.+)/i',
            ];

            foreach ($res as $re) {
                if (preg_match($re, trim($root->nodeValue), $m)) {
                    $seg['DepName'] = $m['DName'];
                    $seg['ArrName'] = $m['AName'];
                    $seg['DepCode'] = !empty($m['DCode']) ? $m['DCode'] : TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = !empty($m['ACode']) ? $m['ACode'] : TRIP_CODE_UNKNOWN;

                    break;
                }
            }

            $date = $this->normalizeDate($this->getNode2('Date', $root, '/:\s*(.+)/'));

            $flight = $this->getNode2('Flight', $root, '/:\s+(.+)/');

            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s*-\s*operated by\s+(.+)/i', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Operator'] = $m[3];
            }

            $dep = $this->getNode2('Departure', $root, '/:\s+(.+)/');
            $arr = $this->getNode2('Arrival', $root, '/:\s+(.+)/');
            $depArr = ['Departure' => $dep, 'Arrival' => $arr];
            $re = '/(\d{1,2}:\d{2})\s+.+\s+terminal\s*([A-Z\d]{1,3})?/i';
            array_walk($depArr, function ($val, $key) use (&$seg, $re, $date) {
                if (preg_match($re, $val, $m)) {
                    $seg[substr($key, 0, 3) . 'Date'] = strtotime($date . ', ' . $m[1]);
                    $seg[$key . 'Terminal'] = !empty($m[2]) ? $m[2] : null;
                } else {
                    $seg[substr($key, 0, 3) . 'Date'] = MISSING_DATE;
                }
            });

            $node = $this->getNode2('Class', $root, '/:\s+(.+)/');

            if (preg_match('/(\w+)\s+class\s+\(\s*(\w)\s*\)\s+seat:\s*([A-Z\d]{1,3})?/i', $node, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['BookingClass'] = $m[2];
                $seg['Seats'] = !empty($m[3]) ? $m[3] : null;
            }

            $seg['Aircraft'] = $this->getNode2('Aircraft', $root, '/:\s+(.+)/');

            // if (isset($seg['DepDate']) && !empty($seg['DepDate']) && !isset($this->dateFirstFlight)) {
            //     $this->dateFirstFlight = $seg['DepDate'];
            // }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode($str, $re = null)
    {
        return $this->http->FindSingleNode("//*[contains(normalize-space(text()), '" . $str . "')]", null, true, $re);
    }

    private function getNode2($str, \DOMNode $root, $re = null)
    {
        return $this->http->FindSingleNode("(following-sibling::tr[contains(., '" . $str . "')])[1]", $root, true, $re);
    }

    private function normalizeDate($str)
    {
        $in = [
            '/^(\d{1,2})\s*(\w+)\s*(\d{2})$/',
            '/^(\d{1,2})\s*(\w+)$/',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2 ' . $this->year,
        ];

        return preg_replace($in, $out, $str);
    }
}
