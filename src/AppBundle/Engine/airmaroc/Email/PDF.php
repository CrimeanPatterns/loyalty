<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\airmaroc\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "airmaroc/it-6720248.eml";

    private $detects = [
        'You have filled in the following advance',
    ];

    private $pdfText = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);

        return [
            'emailType'  => 'PdfAirTicket',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'royalairmaroc.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'royalairmaroc.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $text = $this->pdfText;

        $recLoc = $this->cutText('BOOKING CONFIRMED', 'RESERVATION INFORMATION', $text);

        if (preg_match('/\b([A-Z\d]{5,7})\b/', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $passengersInfo = $this->cutText('Date of issue', 'Price', $text);

        if (preg_match('/date of issue:\s+(\d{1,2} \w+ \d{4})\s+total amount:\s+(\S)\s+([\d\.\,]+)/iu', $passengersInfo, $m)) {
            $it['ReservationDate'] = strtotime($m[1]);
            $it['Currency'] = str_replace(['$'], ['USD'], $m[2]);
            $it['TotalCharge'] = str_replace([','], [''], $m[3]);
        }
        preg_match_all('/(m[irs]\.\s+.+)/iu', $passengersInfo, $m);

        if (isset($m[1])) {
            $it['Passengers'] = $m[1];
        }

        $segments = $this->cutText('Itinerary', 'Details Of Services', $text);

        $dates = [];
        $reDate = '/(\w+\s+\d{1,2}\s+\w+\s+\d{4})/iu';

        if (preg_match_all($reDate, $segments, $m)) {
            $dates = $m[1];
        }

        $re = '/(?<DTime>\d{1,2}:\d{2})\s+(?<AddDDay>\+\s*\d\s+day)?(?<DName>\D+)\s+\((?<DCode>[A-Z]{3})\)\s+(?:terminal\s+(?<DTerm>[A-Z\d]+))?\s+(?<ATime>\d{1,2}:\d{2})\s+(?<AName>\D+)';
        $re .= '\s+\((?<ACode>[A-Z]{3})\)\s+(?:terminal\s+(?<ATerm>[A-Z\d]{1,3}))?\s+duration:\s+(?<Dur>.+)\s+airline:\s+.+\s+\((?<AirName>[A-Z\d]{2})\s+';
        $re .= '(?<FNum>\d+)\)\s+aircraft:\s+(?<Aircraft>.+)\s+cabin\s+(?<Cabin>\w+)/iu';

        preg_match_all('/(\d{1,2}:\d{2}\s+[\D\d]+\s+airline:\s+.+\s+\([A-Z\d]{2}\s+\d+\)\s+.+\s+cabin\s+\w++)/iuU', $segments, $m);

        if (count($m[1]) === 0) {
            return false;
        }

        $countS = count($m[1]);

        while ($countS > count($dates)) {
            $dates = array_merge($dates, $dates);
        }

        sort($dates);

        foreach ($m[1] as $i => $text) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match($re, $text, $m)) {
                $seg['DepName'] = preg_replace('/\s+/', ' ', $m['DName']);
                $seg['DepCode'] = $m['DCode'];
                $seg['DepartureTerminal'] = !empty($m['DTerm']) ? $m['DTerm'] : null;
                $seg['ArrName'] = preg_replace('/\s+/', ' ', $m['AName']);
                $seg['ArrCode'] = $m['ACode'];
                $seg['ArrivalTerminal'] = !empty($m['ATerm']) ? $m['ATerm'] : null;
                $seg['Duration'] = $m['Dur'];
                $seg['AirlineName'] = $m['AirName'];
                $seg['FlightNumber'] = $m['FNum'];
                $seg['Aircraft'] = $m['Aircraft'];
                $seg['Cabin'] = $m['Cabin'];
                $seg['DepDate'] = strtotime($dates[$i] . ' ' . $m['DTime']);
                $seg['ArrDate'] = strtotime($dates[$i] . ' ' . $m['ATime']);
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $end, true) !== false) {
                    return stristr($begin, $end, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
        $nbsp = chr(194) . chr(160);
        $shy = chr(194) . chr(173);
        $body = str_replace($nbsp, ' ', $body);
        $body = str_replace($shy, '', $body);

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
    }
}
