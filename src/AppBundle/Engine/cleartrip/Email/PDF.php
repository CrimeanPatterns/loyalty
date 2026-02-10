<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\cleartrip\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-6390509.eml";

    private $pdfText = '';

    private $detectBody = [
        'CONDITIO NS OF CONTRACT AND OTHER IM PORTANT NOTICES',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detect($parser);

        return [
            'emailType'  => 'PdfAirPlane',
            'parsedData' => ['Itineraries' => $this->parseEmail()],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cleartrip.ae') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], '@cleartrip.ae') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detect($parser);
    }

    private function parseEmail()
    {
        $text = $this->pdfText;

        $aboutFlight = $this->cutText('FLIGHT INFORMATION', 'MISCELLANEOUS', $text);

        $segments = preg_split('/\w+\s+(\d{1,2}\s+\w+\s+\d{4})\s+\|\s+duration\s+(\d{1,2}:\d{2})/iu', $aboutFlight, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $roots = [];
        $dates = [];
        $durations = [];
        $rls = [];
        $status = '';

        foreach ($segments as $segment) {
            if (strpos($segment, 'Dep') !== false && strpos($segment, 'Arr') !== false) {
                $roots[] = $segment;

                if (preg_match('/Confirmation Number\s*:\s+.*\b([A-Z\d]{5,7})\b/', $segment, $m)) {
                    $rls[] = $m[1];
                }

                continue;
            }

            if (preg_match('/[A-Z]{2}\s*\d+\s+(\w+)/', $segment, $m)) {
                $status = $m[1];

                continue;
            }

            if (preg_match('/\d{1,2}\s+\w+\s+\d{4}/', $segment)) {
                $dates[] = $segment;

                continue;
            }

            if (preg_match('/\d{1,2}:\d{2}/', $segment)) {
                $durations[] = $segment;

                continue;
            }
        }
        $rls = array_unique($rls);

        $travelInformation = $this->cutText('TRAVELLER INFORMATION', 'FLIGHT INFORMATION', $text);

        $its = [];

        foreach ($rls as $rl) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T'];
            $it['Status'] = $status;
            $it['RecordLocator'] = $rl;

            if (preg_match('/([mrs]+\s+.+)\s+E-mail:\s+[\S\s]+E-ticket number\s+(.+)\s+/iu', $travelInformation, $m)) {
                $it['Passengers'][] = $m[1];
                $it['TicketNumbers'][] = $m[2];
            }

            foreach ($roots as $i => $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $depArr = ['Dep' => $root, 'Arr' => $root];
                $reDepArr = '\s*:\s+(\d{1,2}:\d{2})\s+(\D+)\s+\b([A-Z]{3})\b';
                array_walk($depArr, function ($val, $key) use (&$seg, $reDepArr, $dates, $i) {
                    if (preg_match('/' . $key . $reDepArr . '/u', $val, $m)) {
                        $seg[$key . 'Name'] = preg_replace('/\s+/', ' ', $m[2]);
                        $seg[$key . 'Code'] = $m[3];
                        $seg[$key . 'Date'] = strtotime($dates[$i] . ', ' . $m[1]);
                    }
                });

                $re = '/Flight Number:\s+.+([A-Z]{2})\s+(\d+)\s+Fare type:\s+(\w+)\s+Aircraft:\s+(.+)\s+Meal:\s+(.+)\s+\w+/iu';

                if (preg_match($re, $root, $m)) {
                    $seg['FlightNumber'] = $m[2];
                    $seg['AirlineName'] = $m[1];
                    $seg['Cabin'] = $m[3];
                    $seg['Aircraft'] = $m[4];
                    $seg['Meal'] = $m[5];
                }

                $it['TripSegments'][] = $seg;
            }

            $its[] = $it;
        }

        return $its;
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function detect(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            $this->logger->info('PDF attach not found');

            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        foreach ($this->detectBody as $dt) {
            if (stripos($body, $dt) !== false) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
    }
}
