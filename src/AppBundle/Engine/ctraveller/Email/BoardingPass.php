<?php

namespace AwardWallet\Engine\ctraveller\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "ctraveller/it-6603744.eml";

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@corporatetraveller.co.za') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/\w{3,}@corporatetraveller[.]co[.]za/i', $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(.,"Corporate Traveller is a leading business") or contains(.,"www.corporatetraveller.co.za")] | //a[contains(@href,"//www.corporatetraveller.co.za")]')->length === 0) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(.,"Flight") and contains(.,"Arrival") and contains(.,"ATTACHED BOARDING PASS")]')->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $textPdfTarget = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (strpos($textPdf, 'Flight') !== false && strpos($textPdf, 'BOOKING REFERENCE') !== false && strpos($textPdf, 'TICKET') !== false) {
                $textPdfTarget = $textPdf;
            }
        }

        $it = $this->parseEmail($textBody, $textPdfTarget);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'BoardingPass',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function parseEmail($textBody = '', $textPdf = '')
    {
        $start = strpos($textBody, 'Flight:');
        $end = strpos($textBody, 'ATTACHED BOARDING PASS');

        if ($start === false || $end === false) {
            return null;
        }
        $text = substr($textBody, $start, $end - $start);

        $it = [];
        $it['Kind'] = 'T';

        $it['TripSegments'] = [];
        $seg = [];

        if (preg_match('/^[>\s]*Flight:\s+([A-Z\d]{2})\s*(\d+)\s+-\s+(.+)\s+\(([A-Z]{3})\)\s+-\s+(.+)\s+\(([A-Z]{3})\)\s+-\s+(\d{1,2}\s*[^\d\s]{3,}\s*\d{2,4})\s+-\s+(\d{1,2}:\d{2})\s*$/mi', $text, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
            $seg['DepName'] = $matches[3];
            $seg['DepCode'] = $matches[4];
            $seg['ArrName'] = $matches[5];
            $seg['ArrCode'] = $matches[6];
            $date = $matches[7];
            $timeDep = $matches[8];

            if (preg_match('/' . $seg['AirlineName'] . '\s*' . $seg['FlightNumber'] . ' (?:.+?) BOOKING REFERENCE(.+?) TICKET$/ms', $textPdf, $matches)) {
                if (preg_match('/\s{2,}([A-Z\d]{5,7})$/m', $matches[1], $m)) {
                    $it['RecordLocator'] = $m[1];
                } else {
                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                }
            }
        }

        $passengers = [];
        $ticketNumbers = [];
        preg_match_all('/^[>\s]*([^:]{2,}):\s+Checked\s*in\s+-\s+Ticket\s+number:\s+([-\d\s]+\b)\s*$/mi', $text, $passengersMatches, PREG_SET_ORDER);

        foreach ($passengersMatches as $matches) {
            $passengers[] = $matches[1];
            $ticketNumbers[] = $matches[2];
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = array_unique($ticketNumbers);
        }

        if (preg_match('/^[>\s]*Flight\s+Arrival:\s+(\d{1,2}:\d{2})\s*$/mi', $text, $matches)) {
            $timeArr = $matches[1];
        }

        if ($date && $timeDep && $timeArr) {
            $date = strtotime($date);
            $seg['DepDate'] = strtotime($timeDep, $date);
            $seg['ArrDate'] = strtotime($timeArr, $date);
        }

        $it['TripSegments'][] = $seg;

        if (empty($textPdf)) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        return $it;
    }
}
