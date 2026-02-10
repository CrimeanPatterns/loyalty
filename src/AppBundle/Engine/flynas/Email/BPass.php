<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\flynas\Email;

class BPass extends \TAccountChecker
{
    public $mailFiles = "flynas/it-7406112.eml";

    private $lang = 'en';

    private $detects = [
        'Open link to view your boarding pass',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();

        return [
            'parsedData' => [
                'Itineraries'  => $this->parseEmail($text),
                'BoardingPass' => $this->parseBp($text),
            ],
            'emailType' => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'flynas.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flynas.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (stripos($body, 'Flynas') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $re = '/PNR\s+(?<RE>[A-Z\d]{5,7})\s+Name:\s+(?<Name>.+)\s+Flight:\s+(?<DName>.+)\s+-\s+(?<AName>.+)\s*,\s*(?<AirName>[A-Z\d]{2})\s+(?<FNum>\d+)\s+Date:\s+(?<DDate>.+)\s+Seat:\s+(?<Seat>[A-Z\d]{1,3})/is';

        if (preg_match($re, $text, $m)) {
            $it['RecordLocator'] = $m['RE'];
            $it['Passengers'][] = $m['Name'];
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['DepName'] = $m['DName'];
            $seg['ArrName'] = $m['AName'];
            $seg['AirlineName'] = $m['AirName'];
            $seg['FlightNumber'] = $m['FNum'];
            $seg['DepDate'] = strtotime($m['DDate']);
            $seg['ArrDate'] = MISSING_DATE;

            if (!empty($seg['DepName']) && !empty($seg['ArrName']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $seg['Seats'][] = $m['Seat'];
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseBp($text)
    {
        $it = [];
        $re = '/PNR\s+(?<RE>[A-Z\d]{5,7})\s+Name:\s+(?<Name>.+)\s+Flight:\s+(?<DName>.+)\s+-\s+(?<AName>.+)\s*,\s*(?<AirName>[A-Z\d]{2})\s+(?<FNum>\d+)\s+Date:\s+(?<DDate>.+)\s+Seat:\s+(?<Seat>[A-Z\d]{1,3})/is';

        if (preg_match($re, $text, $m)) {
            $it['RecordLocator'] = $m['RE'];
            $it['Passengers'][] = $m['Name'];
            $it['DepName'] = $m['DName'];
            $it['ArrName'] = $m['AName'];
            $it['AirlineName'] = $m['AirName'];
            $it['FlightNumber'] = $m['FNum'];
            $it['DepDate'] = strtotime($m['DDate']);
            $it['ArrDate'] = MISSING_DATE;
            $it['Seats'][] = $m['Seat'];
        }

        if (preg_match('#(http://barcode\.flynas\.com.+|https://barcode\.flynas\.com.+)#i', $text, $m)) {
            $it['BoardingPassURL'] = $m[1];
        }

        return [$it];
    }
}
