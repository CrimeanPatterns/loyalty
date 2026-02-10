<?php

namespace AwardWallet\Engine\alitalia\Email;

/**
 * it-4266261.eml.
 */
class BoardingPassPdf2014En extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-4266261.eml, alitalia/it-4566021.eml";

    protected $reservations = [];
    protected $tripSegments = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('[\w\s-]+\s+(web.?check.?in|summary).*\.pdf');

        if (empty($pdf) || count($pdf) > 1) {
            return false;
        }

        $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        $this->parseEmail(str_replace(' ', ' ', $pdfText));

        return [
            'parsedData' => ['Itineraries' => $this->mergePassengersSegments()],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Web Check-in — Send summary') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Collect your boarding pass at the airport from the self-service stations, where possible') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    protected function parseEmail($pdfText)
    {
        $this->parseSegments($this->findСutSection($pdfText, 'TERMINAL', 'IMPORTANT INFORMATION'));
        $this->iterationReservations($this->findСutSection($pdfText, 'SEAT', 'FLIGHT'));
    }

    protected function mergePassengersSegments()
    {
        foreach ($this->reservations as $i => $value) {
            $this->reservations[$i]['TripSegments'] = $this->tripSegments;
        }
        unset($this->tripSegments);

        return array_values($this->reservations);
    }

    protected function iterationReservations($pdfText)
    {
        foreach (preg_split('/\n/', $pdfText, -1, PREG_SPLIT_NO_EMPTY) as $key => $value) {
            $value = trim($value);
            $segment = ['Kind' => 'T', 'AccountNumbers' => []];

            if (preg_match('/^\w+\s+\w+/', $value, $matches)) {
                $segment['Passengers'][] = $matches[0];
            }

            if (preg_match_all('/[\d]{5,}/', $value, $matches)) {
                if (count($matches[0]) > 1) {
                    $segment['AccountNumbers'][] = $matches[0][0];
                    $segment['TicketNumbers'][] = $matches[0][1];
                } else {
                    $segment['TicketNumbers'][] = $matches[0][0];
                }
            }

            if (preg_match('/\d\s+([A-Z\d]{5,6})\s+\d/', $value, $matches)) {
                $segment['RecordLocator'] = $matches[1];
            }

            if (!empty($segment) && !empty($segment['Passengers']) && !empty($segment['TicketNumbers'])) {
                $this->reservations = $this->mergeReservations($segment);
            }
        }
    }

    protected function mergeReservations($segment)
    {
        if (isset($this->reservations[$segment['RecordLocator']])) {
            $current = $this->reservations[$segment['RecordLocator']];
            $this->reservations[$segment['RecordLocator']]['Passengers'] = array_merge($current['Passengers'], $segment['Passengers']);
            $this->reservations[$segment['RecordLocator']]['AccountNumbers'] = array_merge($current['AccountNumbers'], $segment['AccountNumbers']);
            $this->reservations[$segment['RecordLocator']]['TicketNumbers'] = array_merge($current['TicketNumbers'], $segment['TicketNumbers']);
        } else {
            $this->reservations[$segment['RecordLocator']] = $segment;
        }

        return $this->reservations;
    }

    protected function parseSegments($pdfText)
    {
        foreach (preg_split('/\n/', $pdfText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            if (strlen($value) > 50) {
                $this->tripSegments[] = $this->iterationSegments($value);
            }
        }
    }

    protected function iterationSegments($text)
    {
        $segments = [];

        if (preg_match('/([\w]{2})\s*([\d]{3,4})/', $text, $matches)) {
            $segments['AirlineName'] = $matches[1];
            $segments['FlightNumber'] = $matches[2];
        }

        if (preg_match('/\d+\/\d+\/\d{4}/', $text, $matches)) {
            $date = strtotime(str_replace('/', '-', $matches[0]));
        }
        //   /\d+\s+([A-Z,.\s]{3,})/
        if (preg_match_all('/\d+\s+([A-Z][A-Z\,\s]+)/', $text, $matches)) {
            $segments['DepName'] = trim($matches[1][0]);
            $segments['ArrName'] = trim($matches[1][1]);
        }

        if (preg_match_all('/\d+\.\d+/', $text, $matches)) {
            $segments['DepDate'] = strtotime($matches[0][0], $date);
            $segments['ArrDate'] = strtotime($matches[0][1], $date);

            //$segments['DepDate_'] = date("Y-m-d H:i:s",  $segments['DepDate']);
            //$segments['ArrDate_'] = date("Y-m-d H:i:s",  $segments['ArrDate']);
        }

        $segments['ArrCode'] = $segments['DepCode'] = TRIP_CODE_UNKNOWN;

        return $segments;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/FLIGHT\n(.*)Price details')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>FLIGHT</b> <i>cut text</i> <b>Price details</b>.
     *
     * @param type $input
     * @param type $searchStart
     * @param type $searchFinish
     *
     * @return type
     */
    protected function findСutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_stristr(mb_stristr($input, $searchStart), $searchFinish, true);

        return mb_substr($input, mb_strlen($searchStart));
    }
}
