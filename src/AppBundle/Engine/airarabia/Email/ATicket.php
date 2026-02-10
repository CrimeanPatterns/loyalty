<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\airarabia\Email;

class ATicket extends \TAccountChecker
{
    public $mailFiles = "airarabia/it-7414746.eml, airarabia/it-7739077.eml";

    private $detects = [
        'Thank you for booking your flight with Air Arabia',
        'Благодарим Вас за Выбор полета с авиакомпанией Air Arabia',
    ];

    private $headers = [
        'Pre select services for your travel with Air Arabia',
    ];

    private $lang = '';

    private $pnr = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $subj = $parser->getHeader('subject');

        if (preg_match('/pnr\s*\S*\s*([A-Z\d]{5,})/i', $subj, $m)) {
            $this->pnr = $m[1];
        }

        return [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'airarabia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'airarabia.com') === false) {
            return false;
        }

        foreach ($this->headers as $header) {
            if (stripos($headers['subject'], $header) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

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

        if (!empty($this->pnr)) {
            $it['RecordLocator'] = $this->pnr;
        } else {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        if (preg_match('/Dear\s+([msr]+\s+\D+)/i', $this->http->FindSingleNode("//td[contains(., 'Dear') and not(descendant::td)]"), $m)) {
            $it['Passengers'][] = $m[1];
        }

        $xpath = "//td[contains(., 'Flight') and not(descendant::td)]/ancestor::table[2]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $re = '/^(\D+?)\s*(?:-\s*Terminal\s*([A-Z])|\s*([A-Z\d]{1,3})|\s+([A-Z\d]{1,3}))?\s*\/\s*(\D+?)\s*(?:-\s*Terminal\s*([A-Z])|\s*([A-Z\d]{1,3})|\s+([A-Z\d]{1,3}))?$/';

            if (preg_match($re, $this->http->FindSingleNode('descendant::tr[1]', $root), $m)) {
                $seg['DepName'] = $this->doBetter($m[1]);
                $seg['DepartureTerminal'] = $this->existsIndex($m, [2, 3, 4]);
                $seg['ArrName'] = $this->doBetter($m[5]);
                $seg['ArrivalTerminal'] = $this->existsIndex($m, [6, 7, 8]);
            }

            $flight = $this->http->FindSingleNode("descendant::tr[contains(., 'Flight') and not(descendant::tr)]", $root);

            if (preg_match('/Flight\s+([A-Z\d]{2})\s*(\d+)(?:[A-Z])?\s+Status\s+(\w+)/i', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $it['Status'] = $m[3];
            }

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("descendant::tr[contains(., 'Departure') and not(descendant::tr)]", $root, true, '/(\w+,\s+\d+\s+\w+\s+\d{2,4}\s+\d+:\d+)/'));

            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("descendant::tr[contains(., 'Arrival') and not(descendant::tr)]", $root, true, '/(\w+,\s+\d+\s+\w+\s+\d{2,4}\s+\d+:\d+)/'));

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function existsIndex($m, $indexes = [])
    {
        foreach ($indexes as $index) {
            if (!empty($m[$index])) {
                return $m[$index];
            }
        }

        return null;
    }

    private function doBetter($str)
    {
        return trim(str_ireplace(['terminal', '-'], ['', ''], $str));
    }
}
