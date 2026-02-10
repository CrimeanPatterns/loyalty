<?php

namespace AwardWallet\Engine\ctrip\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-5460750.eml, ctrip/it-6339601.eml, ctrip/it-6370672.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketEn',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(text(), 'Thank you for using Ctrip') or contains(text(), 'Thank you for choosing Ctrip')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'ctrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'ctrip.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'Ctrip flight order') !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Passengers'] = $this->http->FindNodes("//td[normalize-space(.) = 'Passenger name(s)']/ancestor::tr[1]/following-sibling::tr/td[1]");
        $total = $this->http->FindSingleNode("//*[contains(text(), 'Total amount:')]", null, true, '/.+:\s+(.+)/');

        if (preg_match('/([A-Z]{2,4})\s+([\d\.]+)/', $total, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = $m[2];
        }
        $it['Tax'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Tax:')]", null, true, '/[A-Z]+\s+([\d\.]+)/');
        $it['BaseFare'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Fare:')]", null, true, '/[A-Z]+\s+([\d\.]+)/');

        $xpath = "//td[normalize-space(.) = 'Flight']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $flight = $this->http->FindSingleNode('td[2]', $root);

            if (preg_match('/([A-Z]{1,2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['DepDate'] = strtotime($this->http->FindSingleNode('td[5]', $root));
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode('td[6]', $root));
            $depInfo = $this->http->FindSingleNode('td[3]', $root);

            if (preg_match('/(.+)\s+\(([A-Z]{3})\)/', $depInfo, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
            } elseif (preg_match('/terminal ([a-z0-9]{1,2})\s+\w+\s+(.+)/i', $depInfo, $m)) {
                $seg['DepName'] = $m[2];
                $seg['DepartureTerminal'] = $m[1];
            } elseif (preg_match('/(.+?)\s*(?:terminal\s*([a-z0-9]*)|$)/i', $depInfo, $m)) {
                $seg['DepName'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['DepartureTerminal'] = $m[2];
                }
            } else {
                $seg['DepName'] = $depInfo;
            }
            $arrInfo = $this->http->FindSingleNode('td[4]', $root);

            if (preg_match('/(.+)\s+\(([A-Z]{3})\)/', $arrInfo, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            } elseif (preg_match('/terminal ([a-z0-9]{1,2})\s+\w+\s+(.+)/i', $arrInfo, $m)) {
                $seg['ArrName'] = $m[2];
                $seg['ArrivalTerminal'] = $m[1];
            } elseif (preg_match('/(.+?)\s*(?:terminal\s*([a-z0-9]*)|$)/i', $arrInfo, $m)) {
                $seg['ArrName'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['ArrivalTerminal'] = $m[2];
                }
            } else {
                $seg['ArrName'] = $arrInfo;
            }

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber']) && empty($seg['DepCode']) && empty($seg['ArrCode'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $class = $this->http->FindSingleNode('td[7]', $root);

            if (preg_match('/(?<Cabin>(?:Economy|business|))\s*[\(]?(?<BClass>[A-Z]{1})[\)]?/', $class, $m)) {
                $seg['BookingClass'] = $m['BClass'];
                $seg['Cabin'] = (isset($m['Cabin'])) ? $m['Cabin'] : null;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
