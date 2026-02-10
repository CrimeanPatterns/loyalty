<?php

namespace AwardWallet\Engine\flybe\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "flybe/it-4548381.eml";

    private $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(),"Your flight booking reference: ")]', null, false, '/[A-Z\d]{5,6}/');
        $this->result += total($this->http->FindSingleNode('//*[contains(normalize-space(text()),"Total Cost:")]', null, false, '/:\s*(.*)/'));
        $this->parsePassengers();
        $this->parseSegments();

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'no-reply@flybe.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"Your flight booking reference:")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"BOOKING CONFIRMATION")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flybe.com') !== false;
    }

    private function parsePassengers()
    {
        $passengers = [];

        foreach ($this->http->XPath->query('//*[contains(text(),"Names")]/ancestor::thead[1]/following-sibling::tbody[1]/tr') as $current) {
            $passengers[] = $this->http->FindSingleNode('./td[2]', $current);
        }
        $this->result['Passengers'] = array_unique($passengers);
    }

    private function parseSegments()
    {
        foreach ($this->http->XPath->query('//*[contains(text(),"Flight No")]/ancestor::thead[1]/following-sibling::tbody[1]/tr') as $current) {
            $this->result['TripSegments'][] = $this->parseSegment($current);
        }
    }

    private function parseSegment(\DOMElement $element)
    {
        $flightNumber = $this->http->FindSingleNode('./td[3]', $element);

        if (preg_match('/([A-Z]{2})(\d+)/', $flightNumber, $matches)) {
            $date = str_replace('/', '.', $this->http->FindSingleNode('./td[2]', $element));
            $route = explode(' to ', $this->http->FindSingleNode('./td[4]', $element));

            return [
                'AirlineName'  => $matches[1],
                'FlightNumber' => $matches[2],
                'DepCode'      => TRIP_CODE_UNKNOWN,
                'ArrCode'      => TRIP_CODE_UNKNOWN,
                'DepName'      => $route[0],
                'ArrName'      => $route[1],
                'DepDate'      => strtotime($date . ' ' . $this->http->FindSingleNode('./td[5]', $element)),
                'ArrDate'      => strtotime($date . ' ' . $this->http->FindSingleNode('./td[6]', $element)),
            ];
        }
    }
}
