<?php

namespace AwardWallet\Engine\designh\Email;

class BookingHtml2017En extends \TAccountChecker
{
    public $mailFiles = "designh/it-6095591.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its[] = $this->parseHotel();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BookingHtml2017En',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@theprincipalmadridhotel') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Reservation Confirmation ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'theprincipalmadridhotel') !== false
                && strpos($parser->getHTMLBody(), 'Booking number:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@theprincipalmadridhotel') !== false;
    }

    protected function parseHotel()
    {
        $i = ['Kind' => 'R'];

        $i['HotelName'] = $this->http->FindSingleNode('//img[contains(@src, "/confirmation/logo.jpg")]/@alt');

        $footer = $this->http->FindSingleNode('//a[. = "www.theprincipalmadridhotel.com"]/preceding-sibling::text()[normalize-space(.)!=""]');

        if (preg_match('/^(.+?)\.\s+([+\d\s()-]+)/', $footer, $matches)) {
            $i['Address'] = $matches[1];
            $i['Phone'] = trim($matches[2]);
        }

        $inDate = $this->http->FindSingleNode('//strong[.="Check in:"]/following-sibling::text()[1]', null, false, '/[\d:]+(?:\s*[ap.]m)?\b/');
        $outDate = $this->http->FindSingleNode('//strong[.="Check out:"]/following-sibling::text()[1]', null, false, '/[\d:]+(?:\s*[ap.]m)?\b/');

        foreach ($this->http->XPath->query('//text()[contains(., "Booking number:")]/ancestor::table[1]') as $root) {
            $i['GuestNames'][] = $this->http->FindSingleNode('.//td[. = "Guest name:"]/following-sibling::td[1]', $root);
            $i['ConfirmationNumber'] = $this->http->FindSingleNode('.//td[. = "Booking number:"]/following-sibling::td[1]', $root);

            $i['CheckInDate'] = strtotime($this->http->FindSingleNode('.//td[. = "Check-in:"]/following-sibling::td[1]', $root) . ',' . $inDate, false);
            $i['CheckOutDate'] = strtotime($this->http->FindSingleNode('.//td[. = "Check-out:"]/following-sibling::td[1]', $root) . ',' . $outDate, false);

            $i['Rooms'] = $this->http->FindSingleNode('.//td[. = "Number of rooms:"]/following-sibling::td[1]', $root);
            $i['RoomType'] = $this->http->FindSingleNode('.//td[. = "Room type:"]/following-sibling::td[1]', $root);
            $i['Guests'] = $this->http->FindSingleNode('.//td[. = "Persons per room:"]/following-sibling::td[1]', $root);
            $i['Rate'] = join(', ', $this->http->FindNodes('.//td[contains(., "Rate per night")]/following-sibling::td[1]//text()', $root));
            $i['RateType'] = $this->http->FindSingleNode('.//td[. = "Rate name:"]/following-sibling::td[1]', $root);

            if ($total = $this->http->FindSingleNode('.//td[. = "Total Stay (incl. tax):"]/following-sibling::td[1]', $root)) {
                $i['Total'] = preg_replace('/[^\d.]+/', '', $total);
                $i['Currency'] = preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/'], ['', 'EUR', 'USD'], $total);
            }
        }

        $i['CancellationPolicy'] = $this->http->FindSingleNode('//text()[. = "Warranty & Cancellation policy:"]/ancestor::p[1]', null, false, '/policy:\s*(.+?)$/s');

        return $i;
    }
}
