<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\aegean\Email;

class AirTicketText extends \TAccountChecker
{
    public $mailFiles = "aegean/it-5381951.eml";
    private $detectBody = '/If\s+you\s+wish\s+to\s+contact\s+Aegean/i';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();
        $its = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'plain text',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (is_string($this->detectBody) && preg_match($this->detectBody, $body)) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'aegeanair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'aegeanair.com') !== false;
    }

    private function parseEmail($body)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];
        $it['Kind'] = 'T';
        $body = $this->deleteHorizontalTab($body); // hex - 09

        $recordLoc = $this->cutText('Online Booking', 'Passengers', $body);

        if (preg_match('/Booking\s+Reference:\s+(\w+)/i', $recordLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $psnr = $this->cutText('Passengers', 'Flight itinerary', $body);

        if (preg_match_all('/((?:mr|ms|mrs|miss)\s+[\w\s]+)\s+.+ticket number:\D\s+(\d+)/i', $psnr, $m)) {
            $it['Passengers'] = $m[1];
            $it['TicketNumbers'] = $m[2];
        }

        $total = $this->cutText('My Booking', 'Change your flight', $body);
        $reTotal = '/.+Taxes\s+\D\s+(?<Tax>[\d\.]+)\s+Total\s+(?<Cur>\D)\s+(?<Total>[\d\.]+).+/iu'; // u - this modifier is needed

        if (($total = preg_replace('/[*]+/', '', $total)) && preg_match($reTotal, trim($total), $m)) {
            $it['Tax'] = $m['Tax'];
            $it['Currency'] = ($m['Cur'] === '€') ? 'EUR' : '';
            $it['TotalCharge'] = $m['Total'];
        }

        $flightInfoSeg = $this->cutText('Flight itinerary', 'FLIGHT PRICE', $body);
        $segmentsInfo = preg_split('/(\Dfrom\s+.+\s+to\s+.+\s+-\s+)/i', $flightInfoSeg, 0, PREG_SPLIT_NO_EMPTY);
        array_shift($segmentsInfo);

        foreach ($segmentsInfo as $segmentInfo) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $date = '';

            if (preg_match('/(\d+\s+\w+\s+\d{4})/', $segmentInfo, $m)) {
                $date = $m[1];
            }
            $re = '/';
            $re .= '(?<DepTime>\d{2}:\d{2})\s+(?<DepName>.+)\s+(?<ArrTime>\d{2}:\d{2})\s+(?<ArrName>.+)\s+';
            $re .= '(?<AName>\D{2})\s*(?<FNum>\d+)\s+-\s+(\d+\D\s+\d+\D)\s+(?<Cabin>\w+)\s+(?<BClass>\D)';
            $re .= '/';

            if (preg_match($re, $segmentInfo, $m) && !empty($date)) {
                $seg['DepDate'] = strtotime($date . ' ' . $m['DepTime']);
                $seg['DepName'] = $m['DepName'];
                $seg['ArrDate'] = strtotime($date . ' ' . $m['ArrTime']);
                $seg['ArrName'] = $m['ArrName'];
                $seg['AirlineName'] = $m['AName'];
                $seg['FlightNumber'] = $m['FNum'];
                $seg['Cabin'] = $m['Cabin'];
                $seg['BookingClass'] = $m['BClass'];
            }

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function deleteHorizontalTab($text)
    {
        return preg_replace('/\x09/', '', $text);
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $txt = stristr(stristr($text, $start), $end, true);

            return substr($txt, strlen($start));
        }

        return false;
    }
}
