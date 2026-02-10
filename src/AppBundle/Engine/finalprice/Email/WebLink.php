<?php

namespace AwardWallet\Engine\finalprice\Email;

class WebLink extends \TAccountChecker
{
    /** @var \HttpBrowser */
    protected $web;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $url = $this->http->FindSingleNode('//a[contains(text(), "View order details")]/@href');

        if (!isset($url)) {
            return [];
        }
        $this->web = new \HttpBrowser('none', new \CurlDriver());
        $this->web->OnLog = $this->http->OnLog;
        $this->web->GetURL($url);
        $result = [];

        if ($this->web->FindSingleNode('//p[contains(normalize-space(.), "Your reservation is booked. No need to call to reconfirm")]')) {
            $result[] = $this->ParseHotel('Booked');
        }
        $this->web->cleanup();

        return [
            'parsedData' => ['Itineraries' => $result],
            'emailType'  => 'WebLink',
        ];
    }

    /*	protected function parseDate($date, $time, $reference) {
            $year = date('Y', $reference);
            $unix = strtotime($date . ' ' . $year . ' ' . $time);
            if ($unix < $reference)
                $unix = strtotime('+1 year', $unix);
            return $unix;
        }
    */
    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@emails.finalprice.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'About FinalPrice') !== false
            && $this->http->FindSingleNode('//a[contains(text(), "View order details")]/@href') !== null;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]finalprice\.com$/', $from) > 0;
    }

    protected function ParseHotel($status)
    {
        $it = [
            'Kind'   => 'R',
            'Status' => $status,
        ];
        $it['HotelName'] = $this->web->FindSingleNode('//div[@class="hotel-data"]/h2');
        $it['Address'] = $this->web->FindSingleNode('//div[@class="hotel-address"]');
        $it['ConfirmationNumber'] = $this->web->FindSingleNode('//p[contains(., "Please use the booking confirmation number")]', null, true, '/confirmation number\D+(\d{4,20}) to check-in/');
        $it['GuestNames'] = $this->web->FindNodes('//b[contains(text(), "Guest name:")]/following-sibling::span');
        $it['RoomType'] = $this->web->FindSingleNode('//b[contains(text(), "Room category:")]/following-sibling::text()');
        $it['RoomTypeDescription'] = $this->web->FindSingleNode('//b[contains(text(), "Room type:")]/following-sibling::text()');
        $it['Rate'] = $this->web->FindSingleNode('//td[contains(text(), "Room Rate per night")]/following-sibling::td', null, true, '/([\d.]+)$/');
        $it['Taxes'] = $this->web->FindSingleNode('//td[contains(text(), "Taxes & Fees")]/following-sibling::td', null, true, '/([\d.]+)$/');
        $it['Cost'] = $this->web->FindSingleNode('//td[b[contains(text(), "Room Subtotal")]]/following-sibling::td', null, true, '/([\d.]+)$/');
        $it['Total'] = $this->web->FindSingleNode('//td[contains(text(), "Charged")]/following-sibling::td', null, true, '/([\d.]+)$/');

        if ($this->web->FindSingleNode('//p[contains(text(), "Rates are quoted")]/b[contains(text(), "US Dollars")]')) {
            $it['Currency'] = 'USD';
        }
        //		$date = $this->web->FindSingleNode('//script[contains(., "const changedAt")]', null, true, '/changedAt = new Date\((\d+)\d{3}\);/');
        //		if (isset($date) && $date < strtotime('01/01/2000'))
        //			unset($date);
        $s = $this->web->FindSingleNode('//b[contains(text(), "Check-in:")]/following-sibling::text()');

        if (isset($s)) {
            preg_match('/(?<time>[\d:]+\s*[AP]M)\s*\-\s*\w{3}, (?<date>\w{3} \d{1,2}, 20\d\d)/', $s, $checkIn);
        }
        $s = $this->web->FindSingleNode('//b[contains(text(), "Check-out:")]/following-sibling::text()');

        if (isset($s)) {
            preg_match('/(?<time>[\d:]+\s*[AP]M)\s*\-\s*\w{3}, (?<date>\w{3} \d{1,2}, 20\d\d)/', $s, $checkOut);
        }

        if (!empty($checkIn) && !empty($checkOut)) {
            $it['CheckInDate'] = strtotime($checkIn['date'] . ' ' . $checkIn['time']);
            $it['CheckOutDate'] = strtotime($checkOut['date'] . ' ' . $checkOut['time']);
        }

        return $it;
    }
}
