<?php

namespace AwardWallet\Engine\eurobonus\Email;

class ReturnHome extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-4066460.eml";

    private $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'info-sas@flysas.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'It\'s nearly time to return home') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Home sweet home')
                && stripos($parser->getHTMLBody(), 'It is almost time for your return trip.');
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysas.com') !== false;
    }

    protected function parseEmail()
    {
        $this->result['Kind'] = 'T';

        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(normalize-space(text()), "Booking reference:")]', null, false, '/[A-Z\d]{6}/');
        $this->result['Passengers'] = preg_split('/,\s*/', $this->http->FindSingleNode('//*[contains(normalize-space(text()), "Hello ")]', null, false, '/Hello\s+([\w\s]+),/'));

        $this->parseBody('//*[contains(normalize-space(text()), "Booking reference:")]/ancestor::table[1]//tr');

        return [$this->result];
    }

    protected function parseBody($query)
    {
        $array = [];

        foreach ($this->http->XPath->query($query) as $value) {
            foreach ($value->childNodes as $val) {
                if (empty($val->childNodes) !== true
                        && $inner = $this->innerArray($val)) {
                    $array[] = $inner;
                }
            }
        }

        if (empty($array) !== true) {
            $this->result['TripSegments'] = $this->segments($array);
        }
        unset($array);
    }

    protected function innerArray(\DOMElement $element)
    {
        $array = [];

        foreach ($element->childNodes as $value) {
            // Link https://en.wikipedia.org/wiki/Non-breaking_space
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return count($array) > 2 ? $array : false;
    }

    protected function segments($array = [])
    {
        $segment = [];

        foreach ($array as $key => $value) {
            $date = null;
            // Outbound: Wednesday 30 Dec 2015
            // Return: Wednesday 6 Jan 2016
            if (preg_match('/\d+\s*[a-z]{3}\s+\d{4}/i', $value[0], $match)) {
                $date = strtotime($match[0]);
            }

            // 10:35 Barcelona (SK 2586 )
            if (preg_match('/(\d+:\d+)\s+([a-z\s-]+).*([a-z]{2})\s*(\d{1,4}).*/i', $value[1], $match)) {
                $segment[$key]['DepDate'] = strtotime($match[1], $date);
                $segment[$key]['DepName'] = trim($match[2]);
                $segment[$key]['AirlineName'] = $match[3];
                $segment[$key]['FlightNumber'] = $match[4];
            }

            // →  14:05 Stockholm
            if (preg_match('/(\d+:\d+)\s+([a-z\s-]+)/i', $value[2], $match)) {
                $segment[$key]['ArrDate'] = strtotime($match[1], $date);
                $segment[$key]['ArrName'] = trim($match[2]);
            }

            $segment[$key]['ArrCode'] = $segment[$key]['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        return $segment;
    }
}
