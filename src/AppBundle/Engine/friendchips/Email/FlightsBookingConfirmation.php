<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\friendchips\Email;

use PlancakeEmailParser;

class FlightsBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-10352751.eml";

    private $detects = [
        'Thank you for booking your flight with Thomson',
    ];

    private $from = '/[@.]tuifly\.com/i';

    private $prov = 'Thomson';

    private $lang = 'en';

    private static $dict = [];

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
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

        $it['RecordLocator'] = $this->getNode('Flight booking reference');

        $it['ReservationDate'] = strtotime($this->getNode('Original booking date'));

        $it['Passengers'] = $this->http->FindNodes("(//tr[contains(., 'Passengers') and not(.//tr)])[1]/following-sibling::tr[normalize-space(.)]/td[1]");

        $total = $this->getNode('Total cost of booking');

        if (preg_match('/(\D)\s*([\d\.]+)/u', $total, $m)) {
            $it['TotalCharge'] = $m[2];
            $it['Currency'] = str_replace('£', 'GBP', $m[1]);
        }

        $xpath = "//tr[(contains(., 'Outbound') or contains(., 'Inbound')) and not(.//tr)]/following-sibling::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $re = '/(\d{1,2}\s+\w{3}\s+\d{2,4})\s+(\d{1,2}:\d{2})\s+Depart\s+(.+)\s+\(\s*([A-Z]{3})\s*\)/i';
            $date = '';

            if (preg_match($re, $root->nodeValue, $m)) {
                $date = $m[1];
                $seg['DepDate'] = strtotime($date . ', ' . $m[2]);
                $seg['DepName'] = $m[3];
                $seg['DepCode'] = $m[4];
            }

            $re = '/Flight\s+([A-Z\d]{2,3})\s+(\d+)\s+(\d{1,2}:\d{2})\s+(?:Arrive\s+)?(.+)\s+\(\s*([A-Z]{3})\s*\)/i';

            if (preg_match($re, $this->http->FindSingleNode('following-sibling::tr[1]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['ArrDate'] = strtotime($date . ', ' . $m[3]);
                $seg['ArrName'] = $m[4];
                $seg['ArrCode'] = $m[5];
            }

            $seg['Operator'] = $this->http->FindSingleNode("ancestor::tr[1]/following-sibling::tr[contains(normalize-space(.), 'Your flight is operated by')]", $root, true, '/Your flight is operated by\s+(.+)/');

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode(string $str, string $re = null)
    {
        return $this->http->FindSingleNode("//td[contains(normalize-space(.), '{$str}') and not(.//td)]/following-sibling::td[1]", null, true, $re);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
