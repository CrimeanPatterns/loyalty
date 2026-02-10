<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class NotificationText2014En extends \TAccountChecker
{
    public $mailFiles = "cheaptickets/it-4440443.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->data = date('Y-m-d', strtotime($parser->getDate()));
        $text = empty($parser->getBody()) ? $parser->getPlainBody() : $parser->getBody();

        $this->result['Kind'] = 'T';
        $this->result['Status'] = 'Notification';

        if (preg_match('/locator for this trip is\s*(.*?)\./s', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('/Primary traveler name:\s*(.*?)\./s', $text, $matches)) {
            $this->result['Passengers'][] = $matches[1];
        }

        $this->result['TripSegments'][] = $this->parseItinerary($parser->getBody());

        return [
            'parsedData' => [
                'Itineraries' => [$this->result],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'flightstatus@cheaptickets.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Cheaptickets Flight Status Notification') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Your Cheaptickets record locator for this trip is') !== false
                && strpos($parser->getHTMLBody(), 'Additional Flight Services') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cheaptickets.com') !== false;
    }

    protected function parseItinerary($text)
    {
        $segment = [];

        if (preg_match('/(.*?) flight (\d{3,4})/', $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = (int) $matches[2];
        }

        if (preg_match('/departs (.*?)\s*\(([A-Z]{3})\).*?at (\d+:\d+\s*[AP]M)/s', $text, $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['DepCode'] = $matches[2];
            $segment['DepDate'] = strtotime($this->data . ' ' . $matches[3]);
        }

        if (preg_match('/arrives (.*?)\s*\(([A-Z]{3})\).*?at (\d+:\d+\s*[AP]M)/s', $text, $matches)) {
            $segment['ArrName'] = $matches[1];
            $segment['ArrCode'] = $matches[2];
            $segment['ArrDate'] = strtotime($this->data . ' ' . $matches[3]);
        }

        return $segment;
    }
}
