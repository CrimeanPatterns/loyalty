<?php

namespace AwardWallet\Engine\airfrance\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = 'airfrance/it-4930884.eml';

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'info@service.airfrance.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"Your Booking has been successful")]')->length > 0
            && $this->http->XPath->query('//img[contains(@src,"airfrance.fr/")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@service.airfrance.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'YourBooking',
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
        $it['RecordLocator'] = $this->http->FindSingleNode('//td[not(.//td) and contains(normalize-space(.),"Your reservation reference number")]/following-sibling::td[1]', null, true, '/([A-Z\d]{5,7})/');
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('//*[normalize-space(.)="Your Flight" and ./following-sibling::table[not(.//table) and count(.//tr)=5]]');

        foreach ($rows as $row) {
            $seg = [];
            $dateAndRoute = $this->http->FindSingleNode('./following-sibling::node()[normalize-space(.)!=""][1]', $row);

            if (preg_match('/(\d{1,2}\s+[^\d\s]+\s+\d{4})\s*(\d{2}:\d{2}).+?\(([A-Z]{3})\).*?\s+-\s+(\d{2}:\d{2}).+?\(([A-Z]{3})\)/', $dateAndRoute, $matches)) {
                $seg['DepDate'] = strtotime($matches[1] . ' ' . $matches[2]);
                $seg['ArrDate'] = strtotime($matches[1] . ' ' . $matches[4]);
                $seg['DepCode'] = $matches[3];
                $seg['ArrCode'] = $matches[5];
            }
            $table = $this->http->XPath->query('./following-sibling::table[not(.//table) and count(.//tr)=5]', $row)->item(0);
            $flight = $this->http->FindSingleNode('.//td[not(.//td) and contains(normalize-space(.),"Flight number")]/following-sibling::td[1]', $table);

            if (preg_match('/([A-Z]{2})(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }
            $seg['Duration'] = $this->http->FindSingleNode('.//td[not(.//td) and contains(normalize-space(.),"Flight duration")]/following-sibling::td[1]', $table, true, '/(\d+h\d+)/');
            $seg['Aircraft'] = $this->http->FindSingleNode('.//td[not(.//td) and contains(normalize-space(.),"Aircraft type")]/following-sibling::td[1]', $table);
            $seg['Cabin'] = $this->http->FindSingleNode('.//td[not(.//td) and normalize-space(.)="Class:"]/following-sibling::td[1]', $table);
            $meal = $this->http->FindSingleNode('.//td[not(.//td) and contains(normalize-space(.),"Inflight meal served")]/following-sibling::td[1]', $table);

            if (stripos($meal, 'No meal served on bord') === false) {
                $seg['Meal'] = $meal;
            }
            $stops = $this->http->FindSingleNode('.//td[not(.//td) and normalize-space(.)="Stop:"]/following-sibling::td[1]', $table);

            if (stripos($stops, 'non-stop') !== false) {
                $seg['Stops'] = 0;
            } else {
                $seg['Stops'] = $stops;
            }
            $it['TripSegments'][] = $seg;
        }
        $it['Passengers'] = $this->http->FindNodes('//*[normalize-space(.)="Passenger"]/following::table[not(.//table)][1]//tr[not(.//tr) and normalize-space(.)!=""]');
        $baseFare = $this->http->FindSingleNode('//td[not(.//td) and contains(normalize-space(.),"Original cost of your ticket")]/following-sibling::td[1]');

        if (preg_match('/([,\d\s]+)/', $baseFare, $matches)) {
            $it['BaseFare'] = str_replace([',', ' '], ['.', ''], $matches[1]);
        }
        $tax = $this->http->FindSingleNode('//td[not(.//td) and contains(normalize-space(.),"Additional charge for your new reservation")]/following-sibling::td[1]');

        if (preg_match('/([,\d\s]+)/', $tax, $matches)) {
            $it['Tax'] = str_replace([',', ' '], ['.', ''], $matches[1]);
        }
        $totalCharge = $this->http->FindSingleNode('//td[not(.//td) and contains(normalize-space(.),"Total amount paid online")]/following-sibling::td[1]');

        if (preg_match('/([,\d\s]+)/', $totalCharge, $matches)) {
            $it['TotalCharge'] = str_replace([',', ' '], ['.', ''], $matches[1]);
        }
        $it['Currency'] = $this->http->FindSingleNode('//td[not(.//td) and contains(normalize-space(.),"Total amount paid online")]/following-sibling::td[2]');

        return $it;
    }
}
