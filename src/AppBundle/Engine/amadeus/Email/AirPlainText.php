<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\amadeus\Email;

class AirPlainText extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-6122695.eml";

    private $detectBody = [
        'THANK YOU FOR CHOOSING BRITISH AIRWAYS',
    ];

    private $monthNames = [];

    private $year = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($parser->getPlainBody())],
            'emailType'  => 'PlainTextForAirReservation',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach ($this->detectBody as $dt) {
            if (stripos($body, $dt) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'amadeus.net') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])) {
            return stripos($headers['from'], 'amadeus.net') !== false;
        } else {
            return false;
        }
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $recLoc = $this->cutText('TICKET DESK', 'BILLUND', $text);

        if (preg_match('/\w+\s+\b([A-Z\d]{5,7})\b/', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $psng = $this->cutText('BILLUND', 'TELEPHONE', $text);

        if (preg_match('/(\w+\/.+(?:mr|miss))/i', $psng, $m)) {
            $it['Passengers'][] = $m[1];
        }

        $resDate = $this->cutText('SUN AIR', 'TICKET DESK', $text);

        if (preg_match('/dato\s+(\d{1,2})\s*(\w+)\s*(\d{2})/i', $resDate, $m)) {
            $it['ReservationDate'] = strtotime($m[1] . ' ' . \AwardWallet\Engine\MonthTranslate::translate($m[2], 'de') . ' 20' . $m[3]);
        }

        $segment = $this->cutText('SERVICE', 'EQUIPMENT', $text);
        $segment = str_replace('>', '', $segment);

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        $re = '/(?<AName>[A-Z]{2})\s+(?<FNum>\d+)\D+(?<Day>\d{1,2})\s*(?<Month>\w+)\s{2,}(?<DepName>[\w\-]+)\s{2,}';
        $re .= '(?<ArrName>[\w\-]+)\s{2,}(?<DName>[A-Z\s]+?)\s{2,}(?<ArrName2>[A-Z\s]+)\s+Non stop\s+[\D\d]+\s+';
        $re .= '(?<DepTime>\d{3,4})\s+(?<ArrTime>\d{3,4})\s+duration\s+(?<D>\d+:\d+)\s*/iu';

        if (!empty($this->year) && preg_match($re, $segment, $m)) {
            $seg['AirlineName'] = $m['AName'];
            $seg['FlightNumber'] = $m['FNum'];
            $date = $m['Day'] . ' ' . $m['Month'] . ' ' . $this->year;
            $seg['DepName'] = $m['DepName'] . ' ' . $m['DName'];
            $seg['ArrName'] = trim($m['ArrName'] . ' ' . $m['ArrName2']);
            $seg['Duration'] = $m['D'];
            $seg['DepDate'] = strtotime($date . ', ' . $this->correctTime($m['DepTime']));
            $seg['ArrDate'] = strtotime($date . ', ' . $this->correctTime($m['ArrTime']));
        }

        $aircraft = $this->cutText('EQUIPMENT', 'RESERVATION NUMBER', $text);

        if (preg_match('/EQUIPMENT\s*:\s*(.+)\s+/i', $aircraft, $m)) {
            $seg['Aircraft'] = $m[1];
        }

        if (isset($seg['FlightNumber']) && isset($seg['DepDate']) && isset($seg['ArrDate'])) {
            $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function correctTime($time)
    {
        if (preg_match('/(\d{1,2})(\d{2})/', $time, $m)) {
            return $m[1] . ':' . $m[2];
        }

        return $time;
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) && empty($end) && empty($text)) {
            return false;
        }

        return stristr(stristr($text, $start), $end, true);
    }
}
