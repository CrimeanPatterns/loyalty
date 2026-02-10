<?php

namespace AwardWallet\Engine\airbaltic\Email;

class FlightCheckIn extends \TAccountChecker
{
    public $mailFiles = "airbaltic/it-6018481.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reservation@info.airbaltic.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@info.airbaltic.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"airBaltic")]')->length > 0
            && $this->http->XPath->query('//a[contains(@href,"//links.info.airbaltic.com")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'FlightCheckIn',
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

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('//*[normalize-space(.)="Booking reference:"]/following-sibling::*[normalize-space(.)!=""][1]', null, true, '/([A-Z\d]{5,7})/');

        if ($passenger = $this->http->FindSingleNode('(//*[starts-with(normalize-space(.),"Dear") and contains(.,",")])[1]', null, true, '/Dear\s+([^,]+),/')) {
            $it['Passengers'] = [$passenger];
        }
        $tables = $this->http->XPath->query('//table[starts-with(normalize-space(.),"Flight") and contains(.,"Departure") and contains(.,"Arrival")]');

        if ($tables->length > 0) {
            $root = $tables->item(0);
            $pattern1 = '/(\d{1,2}:\d{2})\s+([,.)(\w\d\s]+)/u';
            $it['TripSegments'] = [];
            $seg = [];
            $flight = $this->http->FindSingleNode('.//*[starts-with(normalize-space(.),"Flight") and string-length(normalize-space(.))>7 and (name(.)="td" or name(.)="th" or name(.)="div")]', $root);

            if (preg_match('/Flight\s+([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }
            $date = $this->http->FindSingleNode('.//*[contains(.,"/") and string-length(normalize-space(.))>12 and (name(.)="td" or name(.)="th" or name(.)="div")]', $root, true, '/(\d{1,2}\s*\/\s*\d{2}\s*\/\s*\d{4})/');
            $departure = $this->http->FindSingleNode('.//table[not(.//table)]//tr[starts-with(normalize-space(.),"Departure")]/following-sibling::tr[contains(.,":")][1]', $root);

            if (preg_match($pattern1, $departure, $matches)) {
                $timeDep = $matches[1];
                $seg['DepName'] = $matches[2];
            }
            $arrival = $this->http->FindSingleNode('.//table[not(.//table)]//tr[starts-with(normalize-space(.),"Arrival")]/following-sibling::tr[contains(.,":")][1]', $root);

            if (preg_match($pattern1, $arrival, $matches)) {
                $timeArr = $matches[1];
                $seg['ArrName'] = $matches[2];
            }

            if ($date && $timeDep && $timeArr) {
                $date = strtotime(preg_replace('/(\d{1,2})\s*\/\s*(\d{2})\s*\/\s*(\d{4})/', '$1.$2.$3', $date));
                $seg['DepDate'] = strtotime($timeDep, $date);
                $seg['ArrDate'] = strtotime($timeArr, $date);
                //				if ($seg['DepDate'] > $seg['ArrDate'])
//					$seg['ArrDate'] = strtotime('+1 days', $seg['ArrDate']);
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
