<?php

namespace AwardWallet\Engine\flybe\Email;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "flybe/it-7167262.eml";

    public $reProvider = "flybe.com";
    public $reFrom = "noreply@flybe.com";
    public $reBody = ['Name of Passenger'];
    public $reSubject = ['Your Flybe Boarding Pass'];
    public $rePdfName = 'CCSEmail\d+\.pdf';
    public $lang = '';
    public $type = '';
    public $pdf;
    protected $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));

        $itineraries = [];
        $pdfs = $parser->searchAttachmentByName($this->rePdfName);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                $body = strip_tags(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE));
                $this->pdf->SetBody($body);
                $its = $this->parseEmail($body);

                foreach ($its as $it) {
                    $itineraries[] = $it;
                }
            }
        } else {
            return null;
        }

        return [
            'parsedData' => ['Itineraries' => $itineraries],
            'emailType'  => "BoardingPassPDF",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->rePdfName);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $reBody) {
                if (strpos($text, $reBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    private function parseEmail($text)
    {
        $it['Kind'] = "T";
        $it['TripSegments'] = [];

        if (preg_match("#Record locator:\s+([A-Z\d]{5,6})#", $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (preg_match("#Name of Passenger\s+(.*)\n#", $text, $m)) {
            $it['Passengers'][] = $m[1];
        }

        $segment = [];

        if (preg_match('#Operatedby\n(.*)\n([A-Z])\n(.*)\n(.*)\n#', $text, $m)) {
            $segment['DepName'] = $m[1];
            $segment['BookingClass'] = $m[2];
            $segment['AirlineName'] = $m[3];
            $segment['Operator'] = $m[3];
            $segment['ArrName'] = $m[4];
            $segment['DepCode'] = TRIP_CODE_UNKNOWN;
            $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match('#SEQ\n(\d{2})([\w]{3})\n(\d{2}:\d{2})\n.*\n\d+\n(\d+).*\n(\d+[A-Z])#', $text, $m)) {
            $estimatedDate = strtotime($m[1] . ' ' . $m[2] . ' ' . date("Y", $this->date) . ' ' . $m[3]);

            if ($estimatedDate < $this->date) {
                $segment['DepDate'] = strtotime("+1 year", $estimatedDate);
            } else {
                $segment['DepDate'] = $estimatedDate;
            }
            $segment['ArrDate'] = MISSING_DATE;
            $segment['FlightNumber'] = $m[4];
            $segment['Seats'][] = $m[5];
        }

        $it['TripSegments'][] = $segment;

        return [$it];
    }
}
