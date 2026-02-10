<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\amadeus\Email;

class Hotel extends \TAccountChecker
{
    private $detectBody = 'your vacation is almost here. Are you ready';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => 'HotelReservation',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["from"])) {
            return stripos($headers['from'], 'amadeus') !== false;
        } else {
            return false;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'amadeus') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), $this->detectBody) !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];

        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'RESERVATION NUMBER')]/following::*[normalize-space(.)][1]");

        $checkin = $this->http->FindSingleNode("//*[contains(text(), 'CHECK-IN')]/following::span[1]");
        $checkout = $this->http->FindSingleNode("//*[contains(text(), 'CHECK-OUT')]/following::span[1]");

        $inOut = ['CheckIn' => $checkin, 'CheckOut' => $checkout];
        $re = '/(\w+)\s+(\d{1,2}),\s+(\d{4})/';
        array_walk($inOut, function ($s, $key) use (&$it, $re) {
            if (preg_match($re, $s, $m)) {
                $it[$key . 'Date'] = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3]);
            }
        });

        $it['RoomType'] = $this->http->FindSingleNode("//*[contains(text(), 'ROOM TYPE')]/following::span[1]");

        $it['HotelName'] = $this->http->FindSingleNode("//a[contains(@href, 'click.mgmresorts.com/AprimoMarketing/') and contains(text(), 'Unsubscribe')]/following-sibling::text()[normalize-space(.)][1]");

        $it['Address'] = implode(', ', $this->http->FindNodes("//a[contains(@href, 'click.mgmresorts.com/AprimoMarketing/') and contains(text(), 'Unsubscribe')]/following-sibling::text()[normalize-space(.)][position() = 2 or position() = 3]"));

        return [$it];
    }
}
