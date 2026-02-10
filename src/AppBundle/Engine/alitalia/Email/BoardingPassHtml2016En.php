<?php

namespace AwardWallet\Engine\alitalia\Email;

/**
 * it-4150338.eml.
 *
 * @author Mark Iordan
 */
class BoardingPassHtml2016En extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-4150338.eml, alitalia/it-4569444.eml";

    public function monthLangToEn($date)
    {
        return trim(str_replace(['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'], ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], strtolower($date)));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => ['Itineraries' => [$this->parseEmail()]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Your boarding pass') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Alitalia') !== false && (
            stripos($parser->getHTMLBody(), 'IMPORTANT INFORMATION') !== false
            || stripos($parser->getHTMLBody(), 'INFORMATIONS IMPORTANTES') !== false
        );
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    protected function parseEmail()
    {
        $this->result['Kind'] = 'T';

        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(normalize-space(text()), "BOOKING CODE (PNR)") or contains(normalize-space(text()), "CODE DE RESERVATION (PNR)")]', null, false, '/[A-Z\d]{5,6}$/');
        $this->result['Passengers'] = preg_split('/,\s*/', $this->http->FindSingleNode('//*[contains(normalize-space(.), "Dear ") or contains(normalize-space(.), "Cher/Chère ")]/span'));
        $this->parseSegments('//*[contains(normalize-space(text()), "BOOKING CODE (PNR)") or contains(normalize-space(text()), "CODE DE RESERVATION (PNR)")]/ancestor::tr[1]/following-sibling::tr[2]/td/table');

        return $this->result;
    }

    protected function parseSegments($query)
    {
        foreach ($this->http->XPath->query($query) as $value) {
            foreach ($value->childNodes as $val) {
                // is tbody
                if ($val->tagName === 'tbody') {
                    $inner = $this->innerArray($val->childNodes->item(0));
                } else {
                    $inner = $this->innerArray($val);
                }

                if (empty($inner) !== true) {
                    $this->result['TripSegments'][] = $this->segments($inner);
                }
            }
        }
    }

    protected function innerArray(\DOMElement $element)
    {
        $array = [];

        foreach ($element->childNodes as $value) {
            // Link https://en.wikipedia.org/wiki/Non-breaking_space
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));
            $value->nodeValue = str_replace("\n", '', $value->nodeValue);

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return $array;
    }

    /**
     * [0] => 28Apr2016 AZ0075
     * [1] => 06:25 ‌Barcelona El Prat (BCN)
     * [2] => 08:10 ‌Rome Fiumicino (FCO).
     *
     * @param type $array
     *
     * @return type
     */
    protected function segments($array = [])
    {
        $segment = [];

        // 28Apr2016
        if (isset($array[0]) && preg_match('/\d+\w{3}\d+/', $array[0], $match)) {
            $date = strtotime($this->monthLangToEn($match[0]));
        }

        if (empty($date)) {
            return;
        }

        // AZ0075
        if (isset($array[0]) && preg_match('/([A-Z]{2})\s*(\d{3,4})/', $array[0], $match)) {
            $segment['FlightNumber'] = $match[2];
            $segment['AirlineName'] = $match[1];
        }

        // 06:25 ‌Barcelona El Prat (BCN)
        if (isset($array[1]) && preg_match('/(\d+:\d+)\s*(.*?)\s*\(([A-Z]{3})\)/i', $array[1], $match)) {
            $segment['DepDate'] = strtotime($match[1], $date);
            $segment['DepName'] = $match[2];
            $segment['DepCode'] = $match[3];
        }

        // 08:10 ‌Rome Fiumicino (FCO)
        if (isset($array[1]) && preg_match('/(\d+:\d+)\s*(.*?)\s*\(([A-Z]{3})\)/i', $array[2], $match)) {
            $segment['ArrDate'] = strtotime($match[1], $date);
            $segment['ArrName'] = $match[2];
            $segment['ArrCode'] = $match[3];
        }

        return $segment;
    }
}
