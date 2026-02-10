<?php

namespace AwardWallet\Engine\flysaa\Email;

/**
 * @author Mark Iordan
 */

// parsers with similar formats: flysaa/BoardingPassPdf

class MobileBoardingPassText2015En extends \TAccountChecker
{
    public $mailFiles = "flysaa/it-4431601.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = strip_tags(empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody());

        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = CONFNO_UNKNOWN;

        if (preg_match('/(?:Mr|Ms|Mrs)\s+(.+?)\s+-\s+(.+?)\s*[A-Z]{2}\s*\d+/s', $body, $matches, null, 150)) {
            $this->result['Passengers'][] = $matches[1];
            $this->result['Status'] = $matches[2];
        }

        $this->parseSegments($body);

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
                && stripos($headers['from'], 'mobile@flysaa.com') !== false
                && stripos($headers['subject'], 'Your South African Airways Mobile Boarding Pass') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'The following link contains your Boarding Pass and important information about your trip.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysaa.com') !== false;
    }

    protected function parseSegment($elemet)
    {
        $segment['AirlineName'] = $elemet[1];
        $segment['FlightNumber'] = $elemet[2];

        $name = '/(.*)\s+\(([A-Z]{3})/';

        if (preg_match($name, $elemet[3], $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['DepCode'] = $matches[2];
        }

        if (preg_match($name, $elemet[4], $matches)) {
            $segment['ArrName'] = $matches[1];
            $segment['ArrCode'] = $matches[2];
        }

        $date = strtotime(str_replace('/', '-', $elemet[5]));
        $segment['DepDate'] = strtotime($elemet[6], $date);
        $segment['ArrDate'] = strtotime($elemet[7], $date);

        return $segment;
    }

    protected function parseSegments($body)
    {
        // SA372 - Cape Town (CPT) - Johannesburg (JNB) - 24/2/2015 - 19:50
        // Flight Boarding: 19:20
        // Flight Arrival: 21:50
        if (preg_match_all('#([A-Z]{2})\s*(\d+)\s*-\s*(.+?)\s*-\s*(.+?)'
                        . '\s*-\s*(\d+/\d+/\d+)\s*-\s*(\d+:\d+).*'
                        . 'Flight Arrival:\s*(\d+:\d+)#s', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $value) {
                if (count($value) !== 8) {
                    $this->http->Log('The number of elements in a segment != 8', LOG_LEVEL_ERROR);

                    return;
                }
                $this->result['TripSegments'][] = $this->parseSegment($value);
            }
        }
    }
}
