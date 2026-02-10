<?php

namespace AwardWallet\Engine\easyjet\Email;

class GroupHtml2017En extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-6163688.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its[] = $this->parseAir();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'group booking',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'groups@easyjet.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'easyJet Group Quotation Information') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Thank you for your enquiry for a group booking with easyJet.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easyjet.com') !== false;
    }

    protected function parseAir()
    {
        $result = ['Kind' => 'T', 'RecordLocator' => CONFNO_UNKNOWN];

        if ($total = $this->http->FindSingleNode('//text()[contains(., "Total cost when paying by debit card:")]/following-sibling::*[1]')) {
            $result['TotalCharge'] = preg_replace('/[^\d.]+/', '', $total);
            $result['Currency'] = preg_replace(['/[\d.,\s\\\\]+/', '/€/', '/^\$$/'], ['', 'EUR', 'USD'], $total);
        }

        foreach ($this->http->XPath->query('//text()[contains(., "Flight charges")]/ancestor::tr[1]') as $root) {
            $i = ['DepCode' => TRIP_CODE_UNKNOWN, 'ArrCode' => TRIP_CODE_UNKNOWN];

            if (preg_match('/^\s*(.+?)\s+to\s+(.+?)\s*$/', $this->http->FindSingleNode('preceding-sibling::tr[1]', $root), $matches)) {
                $i['DepName'] = $matches[1];
                $i['ArrName'] = $matches[2];
            }
            $text = join("  ", $this->http->FindNodes('.//text()', $root));

            if (preg_match('/Dep\s+(\d+ \w+ \d+\s+[\d:]+(?:\s*[ap.]m)?)/i', $text, $matches)) {
                $i['DepDate'] = strtotime($matches[1], false);
            }

            if (preg_match('/Arr\s+(\d+ \w+ \d+\s+[\d:]+(?:\s*[ap.]m)?)/i', $text, $matches)) {
                $i['ArrDate'] = strtotime($matches[1], false);
            }

            if (preg_match('/Flight\s+([A-Z]{2})?\s*(\d+)/i', $text, $matches)) {
                if (!empty($matches[1])) {
                    $i['AirlineName'] = $matches[1];
                } else {
                    $i['AirlineName'] = 'U2';
                }
                $i['FlightNumber'] = $matches[2];
            }
            $result['TripSegments'][] = $i;
        }

        return $result;
    }
}
