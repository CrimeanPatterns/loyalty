<?php

namespace AwardWallet\Engine\british\Email;

// TODO: Obsolete similar parser - It2485768.php

class BAHtml2016En extends \TAccountChecker
{
    public $mailFiles = "british/it-4679746.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->http->XPath->query('//*[contains(@bgcolor, "#172E4D")]/ancestor::table[2]') as $current) {
            if (strpos($current->nodeValue, 'Pick-up') !== false) {
                return $this->parseCar($this->innerArray($current));
            }
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'contact@contact.britishairways.com') !== false
            && preg_match('/BA receipt - [A-Z\d]{5,6}/', $headers['subject']) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Confirmation email') !== false
            || strpos($parser->getHTMLBody(), 'Pick-up') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@contact.britishairways.com') !== false;
    }

    protected function innerArray(\DOMElement $element)
    {
        $array = [];

        foreach ($element->childNodes as $value) {
            $value->nodeValue = trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0)));

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return join("\n", $array);
    }

    private function parseCar($text)
    {
        $resv = [];
        $resv['Kind'] = 'L';
        $resv['Number'] = $this->http->FindSingleNode('//*[contains(text(), "Booking reference")]', null, false, '/[A-Z\d]{5,6}/');
        $resv['RenterName'] = $this->http->FindSingleNode('//td[contains(text(), "Passenger(s)")]/following-sibling::td[1]');
        // CarCompactManual Group D - Renault Captur or similar
        if (preg_match('/Car(.+?)\s*-\s*(.+)/', $text, $matches)) {
            $resv['CarType'] = $matches[1];
            $resv['CarModel'] = $matches[2];
        }
        // Pick-up18 Jul 2015 12:30, Avis, Aeroporto Palese, BariDrop-off2 Aug 2015 11:30, Avis, Aeroporto Palese, BariPriceGBP 440.00
        if (preg_match('/Pick-up\s*(.+?\d+:\d+),\s*(.+?)Drop-off\s*(.+?\d+:\d+),\s*(.+?)Price\s*(.+)/', $text, $matches)) {
            $resv['PickupDatetime'] = strtotime($matches[1]);
            $resv['PickupLocation'] = $matches[2];
            $resv['DropoffDatetime'] = strtotime($matches[3]);
            $resv['DropoffLocation'] = $matches[4];
            $resv += total($matches[5]);
        }

        return [
            'emailType'  => 'parseCar: itinerary "Car"',
            'parsedData' => ['Itineraries' => [$resv]],
        ];
    }
}
