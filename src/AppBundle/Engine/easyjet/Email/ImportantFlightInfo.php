<?php

namespace AwardWallet\Engine\easyjet\Email;

class ImportantFlightInfo extends \TAccountChecker
{
    public $mailFiles = 'easyjet/it-4916298.eml';

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'customer.service@easyjet.com') !== false
            || isset($headers['subject']) && preg_match('/information\s+about\s+your\s+easyJet\s+flight/i', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return preg_match('/(Thank\s+you\s+for\s+choosing\s+easyJet|easyJet\s+Customer\s+Services)/i', $this->http->Response['body'])
            || $this->http->XPath->query('//a[contains(@href,"//www.easyjet.com")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easyjet.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ImportantFlightInfo',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Booking Number:")]', null, true, '/:\s*([A-Z\d]{6,7})/');
        $it['Passengers'] = [];

        if ($passenger = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Dear ") and contains(.,",")]', null, true, '/Dear\s+([^,]+\S)\s*,/')) {
            $it['Passengers'][] = $passenger;
        }
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('//tr[contains(normalize-space(.),"Flight Date") and contains(normalize-space(.),"Flight No") and not(.//tr)]/following-sibling::tr[count(./td)>5]');

        foreach ($rows as $row) {
            $seg = [];
            $date = $this->http->FindSingleNode('./td[1]', $row, true, '/(\d{1,2}-[^\d\s]{3}-\d{1,2})/');
            $seg['FlightNumber'] = $this->http->FindSingleNode('./td[2]', $row, true, '/(\d+)/');
            $seg['AirlineName'] = 'U2';
            $seg['DepCode'] = $this->http->FindSingleNode('./td[3]', $row, true, '/([A-Z\d]{3})/');
            $seg['ArrCode'] = $this->http->FindSingleNode('./td[4]', $row, true, '/([A-Z\d]{3})/');
            $timeDep = $this->http->FindSingleNode('./td[5]', $row, true, '/(\d{2}:\d{2})/');
            $timeArr = $this->http->FindSingleNode('./td[6]', $row, true, '/(\d{2}:\d{2})/');

            if ($date && $timeDep && $timeArr) {
                $date = strtotime($date);
                $seg['DepDate'] = strtotime($timeDep, $date);
                $seg['ArrDate'] = strtotime($timeArr, $date);
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
