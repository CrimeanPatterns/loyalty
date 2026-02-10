<?php

namespace AwardWallet\Engine\aerlingus\Email;

// it-4493213.eml

class LoungePass extends \TAccountChecker
{
    public $mailFiles = "aerlingus/it-182640700.eml, aerlingus/it-4493213.eml, aerlingus/it-4602536.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'loungeaccess@aerlingus.com') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'Aer Lingus Lounge Pass') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return preg_match('/Thank\s+you\s+for\s+booking\s+your\s+next\s+flight\s+with\s+Aer\s+Lingus/i', $this->http->Response['body'])
            && $this->http->XPath->query('//img[contains(@src,"//www.aerlingus.com")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aerlingus.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'LoungePass',
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
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[normalize-space(.)="Booking Reference:"]/following::text()[normalize-space(.)!=""][1]', null, true, '/([A-Z\d]{6})/');

        if ($reservationDate = $this->http->FindSingleNode('//text()[normalize-space(.)="Date:"]/following::text()[normalize-space(.)!=""][1]', null, true, '/(\d{1,2}\s*[^\d\s]+\s*\d{4})/')) {
            $it['ReservationDate'] = strtotime($reservationDate);
        }
        $it['Passengers'] = $this->http->FindNodes('//text()[normalize-space(.)="Passengers"]/ancestor::tr[1]/following-sibling::tr//text()[normalize-space()]');
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('//table[contains(.,"Departs:") and not(.//table)]');

        foreach ($rows as $row) {
            $seg = [];
            $flight = $this->http->FindSingleNode('.//tr[contains(.,"Flight")][1]', $row);

            if (preg_match('/Flight\s+([A-Z]{2})(\d+)\s+[^\d\s]{3}\s+(\d{1,3}\s+[^\d\s]{3}\s+\d{4})/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
                $date = $matches[3];
            }
            $departure = $this->http->FindSingleNode('.//td[contains(.,"Departs:") and not(.//td)]/following-sibling::td[1]', $row);

            if (preg_match('/^(.+?)(-Terminal\s+\w+|-\w+ Terminal|)\s+\(([A-Z]{3})\)\s+(\d{1,2}:\d{2})$/', $departure, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepartureTerminal'] = preg_replace(['/\s*\bTerminal\b\s*/i', '/\s*\bUnknown\b\s*/'], '', trim($matches[2], ' -'));
                $seg['DepCode'] = $matches[3];
                $timeDep = $matches[4];
            }
            $arrival = $this->http->FindSingleNode('.//td[contains(.,"Arrives:") and not(.//td)]/following-sibling::td[1]', $row);

            if (preg_match('/^(.+?)(-Terminal\s+\w+|-\w+ Terminal|)\s+\(([A-Z]{3})\)\s+(\d{1,2}:\d{2})$/', $arrival, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrivalTerminal'] = preg_replace(['/\s*\bTerminal\b\s*/i', '/\s*\bUnknown\b\s*/'], '', trim($matches[2], ' -'));
                $seg['ArrCode'] = $matches[3];
                $timeArr = $matches[4];
            }

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
