<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\aegean\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "aegean/it-1653061.eml, aegean/it-2854290.eml, aegean/it-2854291.eml, aegean/it-6162707.eml";

    private $pdfText = '';

    private $detectBody = [
        'This is an automated notification email, please do not reply. If you wish to contact Aegean',
    ];

    private $subj = 'AEGEAN AIRLINES S.A. - Web check-in Confirmation';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => 'PdfForAirTrip',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aegeanair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], '@aegeanair.com') !== false
            && stripos($headers['subject'], $this->subj) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    private function parseEmail()
    {
        $text = $this->pdfText;
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $recLoc = $this->cutText('Booking Reference', 'Passengers', $text);

        if (preg_match('/:\s+\b([A-Z\d]{5,7})\b/', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $psng = $this->cutText('Booking Reference', 'From', $text);

        if (preg_match_all('/\n+(.+)[ ]+Ticket number:\s+(\d+)\s+(?:Member id:\s+([\d \w]+))?/iu', $psng, $m)) {
            $it['Passengers'] = array_map('trim', $m[1]);
            $it['TicketNumbers'] = array_map('trim', $m[2]);

            if (!empty($m[3])) {
                $it['AccountNumbers'] = array_filter(array_map('trim', $m[3]));
            }
        }

        $total = $this->cutText('TOTAL PRICE', 'Manage my booking', $text);

        if (preg_match('/(\D)\s+([\d\.]+)/iu', $total, $m)) {
            $it['TotalCharge'] = $m[2];
            $it['Currency'] = str_replace(['€'], ['EUR'], $m[1]);
        }

        $segments = $this->cutText('From', 'FLIGHT PRICE:', $text);
        $date = '';

        if (preg_match('/(\d{1,2}\s+\w+\s+\d{4})/', $segments, $m)) {
            $date = $m[1];
        }
        $re = '/(\d+:\d+\s+\w+\s+\d+:\d+\s+\w+\s+[A-Z\d]{2}\s*\d+\s+\D\s+[\w\s]+\s{2,}[\w\.\s]+?\s{2,}[\w\.\s]+?\s{2,}\d+\D\s*\d+\D\s+\w+)/iu';
        preg_match_all($re, $segments, $matches);
        $count = count($matches);

        for ($i = 1; $i < $count; $i++) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $re = '/(?<DTime>\d+:\d+)\s+(?<DName>\w+)\s+(?<ATime>\d+:\d+)\s+(?<AName>\w+)\s+(?<AirName>[A-Z\d]{2})\s*(?<FNum>\d+)\s+\D\s+[\w\s]+\s{2,}(?<DName2>[\w\.\s]+?)\s{2,}(?<AName2>[\w\.\s]+?)\s{2,}(?<Dur>\d+\D\s*\d+\D)\s+(?<Cabin>\w+)/iu';

            if (isset($matches[$i][0]) && !empty($date) && preg_match($re, $matches[$i][0], $m)) {
                $seg['AirlineName'] = $m['AirName'];
                $seg['FlightNumber'] = $m['FNum'];
                $seg['DepName'] = $m['DName'] . ', ' . $m['DName2'];
                $seg['ArrName'] = $m['AName'] . ', ' . $m['AName2'];
                $seg['Duration'] = $m['Dur'];
                $seg['Cabin'] = $m['Cabin'];
                $seg['DepDate'] = strtotime($date . ', ' . $m['DTime']);
                $seg['ArrDate'] = strtotime($date . ', ' . $m['ATime']);
            }

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
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

        return stristr(stristr($text, $start), $end, true);
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
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
