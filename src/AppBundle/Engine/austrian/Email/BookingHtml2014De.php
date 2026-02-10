<?php

namespace AwardWallet\Engine\austrian\Email;

class BookingHtml2014De extends \TAccountChecker
{
    public $mailFiles = "austrian/it-5205990.eml";
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Buchungsinformation")]', null, false, '/[A-Z\d]{5,6}/');
        $this->result['Passengers'] = $this->http->FindNodes('//td[normalize-space(text()) = "Name:"]/following-sibling::td[last()]');

        $total = $this->http->FindSingleNode('//td[contains(normalize-space(text()), "Gesamt")]/following-sibling::td[last()]');

        if (preg_match('/([\d,]+)\s*([A-Z]{3})/', $total, $matches)) {
            $this->result['TotalCharge'] = (float) str_replace(',', '.', $matches[1]);
            $this->result['Currency'] = $matches[2];
        }

        $this->parseSegments();

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Buchungsinformation') !== false
                && $this->http->XPath->query('//a[contains(@href,"//www.austrian.com")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return ['de'];
    }

    protected function parseSegments()
    {
        foreach ($this->http->XPath->query('//img[contains(@src, "/icon_departure.gif") or contains(@src, "/icon_arrival.gif")]/ancestor::tr[1]') as $root) {
            if ($date = $this->http->FindSingleNode('td[2]', $root)) {
                $date = $this->normalizeDate($date);
            }

            if (isset($date) && preg_match('/(\d{2}:\d{2})\s*(\d{2}:\d{2})/', $this->http->FindSingleNode('td[3]', $root), $matches)) {
                $segment['DepDate'] = strtotime($date . ', ' . $matches[1]);
                $segment['ArrDate'] = strtotime($date . ', ' . $matches[2]);
            }

            $names = $this->innerArray($this->http->XPath->query('td[4]/text()', $root));

            if (!empty($names) && count($names) === 2) {
                $segment['DepName'] = $names[0];
                $segment['ArrName'] = $names[1];
            }

            $flight = $this->innerArray($this->http->XPath->query('td[5]/text()', $root));

            if (!empty($flight) && count($names) === 2 && preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight[0], $matches)) {
                $segment['AirlineName'] = $matches[1];
                $segment['FlightNumber'] = $matches[2];
                $segment['Operator'] = $flight[1];
            }

            if (preg_match('#(.+?) / (\w+)#', $this->http->FindSingleNode('td[6]', $root), $matches)) {
                $segment['Cabin'] = $matches[1];
                $segment['BookingClass'] = $matches[2];
            }
            $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

            $this->result['TripSegments'][] = $segment;
        }
    }

    protected function innerArray(\DOMNodeList $element)
    {
        $array = [];

        foreach ($element as $value) {
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return $array;
    }

    protected function normalizeDate($string)
    {
        $string = preg_replace('/.+?(\d+\. \w+ \d+)/', '$1', $string);

        $months['ru'] = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        $months['de'] = ['Jan', 'Feb', 'März', 'Apr', 'Mai', 'Juni', 'Juli', 'Aug', 'Sept', 'Okt', 'Nov', 'Dez'];
        $months['it'] = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

        foreach ($months as $value) {
            $date = str_ireplace($value, ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'], $string);

            if ($date !== $string) {
                return $date;
            }
        }

        return $string;
    }
}
