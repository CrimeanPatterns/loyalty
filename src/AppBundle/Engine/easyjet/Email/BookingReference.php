<?php

namespace AwardWallet\Engine\easyjet\Email;

class BookingReference extends \TAccountChecker
{
    public $mailFiles = 'easyjet/it-4923488.eml';

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@easyjet.com') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'Reference Easyjet') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"Your easyJet booking reference is")]')->length > 0
            && $this->http->XPath->query('//a[contains(@href,"//www.easyjet.com")]')->length > 0;
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
            'emailType' => 'BookingReference',
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
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Your easyJet booking reference is")]/following::text()[normalize-space(.)!=""][1]', null, true, '/([A-Z\d]{6,7})/');
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('//ul[not(.//ul) and starts-with(normalize-space(.),"Depart") and contains(.,"Arrive")]');

        foreach ($rows as $row) {
            $seg = [];
            $router = $this->http->FindSingleNode('./preceding::h4[1]', $row);

            if (preg_match('/^(.+[^,])\s+to\s+(.+)$/', $router, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['ArrName'] = $matches[2];
            }
            $dateDep = $this->http->FindSingleNode('.//text()[normalize-space(.)="Depart:"]/following::text()[normalize-space(.)!=""][1]', $row);
            $dateArr = $this->http->FindSingleNode('.//text()[normalize-space(.)="Arrive:"]/following::text()[normalize-space(.)!=""][1]', $row);

            if ($dateDep && $dateArr) {
                $seg['DepDate'] = strtotime($dateDep);
                $seg['ArrDate'] = strtotime($dateArr);
            }
            $flight = $this->http->FindSingleNode('.//*[not(.//*) and starts-with(normalize-space(.),"Flight:")]', $row);

            if (preg_match('/^Flight:\s+([A-Z]{2,3})(\d+)$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
