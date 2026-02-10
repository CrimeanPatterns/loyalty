<?php

namespace AwardWallet\Engine\expedia\Email;

class CarRentalTextNodes extends \TAccountChecker
{
    public $mailFiles = "";

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['subject']) && stripos($headers['subject'], '@expedia') !== false)
            && (isset($headers['from']) && stripos($headers['from'], '@expedia') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], '@expedia') !== false
               && strpos($parser->getPlainBody(), "Pick up:") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'expedia') !== false;
    }

    /**
     * @example expedia/it-25.eml
     * @example expedia/it-40.eml
     */
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!preg_match('#Driver:\s+.*\s+-+#i', $parser->getPlainBody())) {
            return null;
        }

        if ($this->http->XPath->query('//text()')->length === 1) {
            $html = strip_tags($parser->getPlainBody());
            $this->http->SetBody($html);
            $this->convertPlainToDom($html, $this->http);
        }
        $http = $this->http;
        $xpath = $this->http->XPath;
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions(['stripos', 'CleanXMLValue', 'preg_match']);

        $it = ['Kind' => "L"];

        if (!($it['Number'] = $http->FindSingleNode('//text()[contains(., "Itinerary number:")]', null, true, '/Itinerary number:\s*(\S+)/ims'))) {
            $it['Number'] = $http->FindSingleNode('//text()[contains(., "Itinerary number:")]/following-sibling::text()[1]');
        }

        $it['RenterName'] = $http->FindSingleNode('//text()[contains(., "Driver:")]', null, true, '/Driver:\s*(.+)/ims');

        if (empty($it['RenterName'])) {
            $it['RenterName'] = $http->FindSingleNode('//text()[contains(., "Driver:")]/following::text()[normalize-space(.)][1]');
        }
        $it['RentalCompany'] = $http->FindSingleNode('//text()[contains(., "Car:")]', null, true, '/Car:\s*(.+)/ims');

        $it['CarType'] = $http->FindSingleNode('//text()[contains(., "Pick up:")]/preceding::text()[normalize-space(.)][1][not(contains(., "-----"))]');

        foreach ([['Pickup', 'Pick up'], ['Dropoff', 'Drop off']] as $keys) {
            $shift = 0;
            [$Pickup, $Pick_up] = $keys;

            $it["{$Pickup}Datetime"] = strtotime($http->FindSingleNode("//text()[contains(., '{$Pick_up}:')]", null, true, "/{$Pick_up}:\s*(.+)/ims"));
            $it["{$Pickup}Location"] = $http->FindSingleNode("(//text()[contains(., '{$Pick_up}:')]
                /following::text()[
                    string-length(normalize-space(.)) > 2
                    and number(php:functionString('preg_match', '/\d+.\d+\s*(a|p)m/ims', normalize-space(.))) = 0
                ])[1]");
        }

        $it['TotalTaxAmount'] = $http->FindSingleNode('//text()[contains(., "Taxes") and contains(php:functionString("CleanXMLValue", .), "Taxes & Fees:")]', null, true, '/Taxes & Fees:.+?(\d+.\d+|\d+)/ims');

        if (empty($it['TotalTaxAmount'])) {
            $it['TotalTaxAmount'] = $http->FindSingleNode('//text()[contains(., "Taxes") and contains(php:functionString("CleanXMLValue", .), "Taxes & Fees:")]/following::text()[normalize-space(.)][1]', null, true, '/(\d+.\d+|\d+)/ims');
        }

        if (preg_match('/Car total:\s*(\S)?(\d+.\d+|\d+)/ims', $http->FindSingleNode('//text()[contains(., "Car total:")]'), $matches)) {
            if (!empty($matches[1])) {
                $it['Currency'] = ('$' === $matches[1]) ? 'USD' : $matches[1];
            }
            $it['TotalCharge'] = $matches[2];
        }

        if (preg_match('/(\S)?(\d+.\d+|\d+)/ims', $http->FindSingleNode('//text()[contains(., "total") and contains(php:functionString("CleanXMLValue", .), "Car total:")]/following::text()[normalize-space(.)][1]'), $matches)) {
            if (!empty($matches[1])) {
                $it['Currency'] = ('$' === $matches[1]) ? 'USD' : $matches[1];
            }
            $it['TotalCharge'] = $matches[2];
        }

        return [
            'parsedData' => [
                'Itineraries' => [$it],
                'emailType'   => 'CarRentalTextNodes',
            ],
        ];
    }

    private function convertPlainToDom($plainText, $http)
    {
        $lines = explode("\n", $plainText);
        $document = new \DOMDocument();

        foreach ($lines as $line) {
            $document->appendChild($document->createTextNode($line));
        }
        $http->DOM = $document;
        $http->XPath = new \DOMXPath($this->http->DOM);
    }
}
