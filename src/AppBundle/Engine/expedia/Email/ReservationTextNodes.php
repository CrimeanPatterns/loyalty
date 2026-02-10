<?php

namespace AwardWallet\Engine\expedia\Email;

class ReservationTextNodes extends \TAccountChecker
{
    public $mailFiles = "expedia/it-22.eml, expedia/it-23.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Expedia Travel') !== false
            || strpos($from, 'Expedia.com') !== false
            || stripos($from, '@expedia.com') !== false
            || stripos($from, '@ExpediaConfirm.com') !== false
            || stripos($from, '@expediamail.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'Expedia Travel Confirmation') !== false
            || stripos($headers['from'], 'Confirmation@ExpediaConfirm.com') !== false
            || stripos($headers['subject'], 'Expedia travel confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()['
            . 'contains(normalize-space(.),"Expedia Travel Confirmation")'
            . ' or contains(normalize-space(.),"Expedia travel confirmation")'
            . ' or contains(normalize-space(.),"Expedia, Inc")'
            . ' or contains(normalize-space(.),"booking with Expedia")'
            . ' or contains(normalize-space(.),"booking an Expedia")'
            . ' or contains(normalize-space(.),"update on Expedia.com")'
            . ' or contains(.,"www.expedia.com")'
            . ']')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".expedia.com/") or contains(@href,".expediamail.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(),"Itinerary #") and contains(normalize-space(),"View hotel details")]')->length > 0;
    }

    /**
     * @example expedia/it-22.eml
     * @example expedia/it-23.eml
     */
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//text()')->length === 1) {
            $html = strip_tags($parser->getPlainBody());
            $this->http->SetEmailBody($html);
            $this->convertPlainToDom($html, $this->http);
        }
        $http = $this->http;
        $text = text($this->http->Response['body']);

        $it = ['Kind' => "R"];

        // ConfirmationNumber
        $itineraryNumber = $http->FindSingleNode('//text()[contains(normalize-space(.),"Itinerary #")]', null, true, '/#\s*(\S+)/ims');

        if (!$itineraryNumber) {
            $itineraryNumber = $http->FindSingleNode('//text()[normalize-space(.)="Itinerary #"]/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})/');
        }

        if ($itineraryNumber) {
            $it['ConfirmationNumber'] = $itineraryNumber;
        }

        $xpathFragment1 = '//text()[normalize-space(.)="Hotel overview"]/following::text()[ normalize-space(.) and not(contains(.,"/") or starts-with(normalize-space(.),"[")) ]';

        // Address
        $address = $http->FindSingleNode('//text()[contains(normalize-space(.),"View hotel details")]/following::text()[normalize-space(.)][1]');

        if (!$address) {
            $address = $http->FindSingleNode($xpathFragment1 . '[2][contains(.,",")]');
        }

        if ($address) {
            $it['Address'] = trim(str_ireplace('View hotel', '', $address), '>< ');
        }

        // HotelName
        $hotelName = $http->FindSingleNode('//text()[contains(normalize-space(.),"Itinerary #")]/following-sibling::text()[normalize-space(.)][1]');
        $hotelName = str_replace('To: <', '', $hotelName);

        if (!$hotelName && preg_match('/Itinerary\s+\#\s+\d+\s+(.+?)\s+\w{3}\s+\w{3}\/\d+\/\d+/', $text, $m)) {
            $hotelName = $m[1];
        }

        if (!$hotelName) {
            $hotelName = $http->FindSingleNode($xpathFragment1 . '[1]');
        }

        if ($hotelName) {
            $it['HotelName'] = $hotelName;
        }

        // CheckInDate
        // CheckOutDate
        if (preg_match('/(.+)\s*-\s*(.+)/ims', $http->FindSingleNode('//text()[starts-with(normalize-space(.),"Itinerary #")]/preceding::text()[normalize-space(.)][1]'), $matches)) {
            $it['CheckInDate'] = strtotime(str_replace('/', ' ', $matches[1]));
            $it['CheckOutDate'] = strtotime(str_replace('/', ' ', $matches[2]));
        } else {
            $regex = '#';
            $regex .= '(?P<HotelName>.*)\s+';
            $regex .= '\w+\s+(?P<CheckInMonth>\w+)/(?P<CheckInDay>\d+)/(?P<CheckInYear>\d+)\s+-\s+';
            $regex .= '\w+\s+(?P<CheckOutMonth>\w+)/(?P<CheckOutDay>\d+)/(?P<CheckOutYear>\d+)\s+';
            $regex .= '\d+\s+\w+\s+\|\s+\d+\s+\w+\s+';
            $regex .= '(?P<Status>\w{2,})?';
            $regex .= '#';

            if (preg_match($regex, $text, $m)) {
                if (!isset($it['HotelName']) || !$it['HotelName']) {
                    $it['HotelName'] = nice($m['HotelName']);
                }

                foreach (['CheckIn', 'CheckOut'] as $key) {
                    $it[$key . 'Date'] = strtotime($m[$key . 'Day'] . ' ' . $m[$key . 'Month'] . ' ' . $m[$key . 'Year']);
                }

                if (!empty($m['Status'])) {
                    $it['Status'] = $m['Status'];

                    if (stripos($it['Status'], 'cancelled') !== false) {
                        $it['Cancelled'] = true;
                    }
                }
            }
        }

        if (!empty($it['CheckInDate']) && preg_match('/Check-in time starts at\s*(\d{1,2}(?::\d{2})?(?:\s*[AaPp][Mm])?)/i', $text, $matches)) { // Check-in time starts at 2 PM
            $it['CheckInDate'] = strtotime($matches[1], $it['CheckInDate']);
        }

        // Status
        if (empty($it['Status']) && $this->http->XPath->query('//node()[contains(normalize-space(.),"Your booking is confirmed") or contains(normalize-space(.),"Your reservation is confirmed")]')->length > 0) {
            $it['Status'] = 'confirmed';
        }

        // Rooms
        if (preg_match('/(\d+) rooms?\s*\|\s*(\d+) nights?/ims', $http->FindSingleNode('//text()[contains(.,"room") and contains(.,"|")]'), $matches)) {
            $it['Rooms'] = $matches[1];
        }

        $it['Phone'] = $http->FindSingleNode('//text()[contains(.,"Tel:")]', null, true, '/Tel:\s*([-\d\s)(]{5,}\b)/i');
        $it['Fax'] = $http->FindSingleNode('//text()[contains(.,"Fax:")]', null, true, '/Fax:\s*([-\d\s)(]{5,}\b)/i');
        $it['Rate'] = nice(re('#.*\s+/night#', $text));

        // RoomType
        if (preg_match_all('/^\s*Room\s*$\s+(.+)/mi', $text, $roomMatches)) {
            foreach ($roomMatches[1] as $room) {
                if (stripos($room, 'Guests') === false) {
                    $it['RoomType'] = $room;
                }
            }
        }

        // has to be in text()
        if ($http->FindSingleNode('//span[normalize-space(text())="Reserved for"]')) {
            return null;
        }

        // RoomTypeDescription
        $roomTypeDescription = $http->FindSingleNode('//text()[contains(.,"Request") and ./preceding-sibling::text()[contains(normalize-space(.),"Reserved for")]]/following-sibling::text()[normalize-space(.)][1]');

        if (!$roomTypeDescription && preg_match('/\n\s*Room requests\s+(.+?)\s+Message hotel/is', $text, $matches)) {
            $roomTypeDescription = str_replace(["\n\n", "\n"], ["\n", ', '], $matches[1]);
        }

        if ($roomTypeDescription) {
            $it['RoomTypeDescription'] = $roomTypeDescription;
        }

        $it['Currency'] = $http->FindSingleNode('//text()[contains(normalize-space(.),"All prices quoted")]', null, true, '/All prices quoted in ([A-Z]+)\.?/ims');
        $it['Cost'] = cost(re('#price.*?\n\s*\d+\s+night\s+(.*?)\s*\n#is', $text));
        $it['Taxes'] = cost(re('#\n\s*Taxes(?:\s+&\s+Fees)?\s*(.*)#', $text));
        $totalStr = re('#\n\s*(?:Adjusted\s+)?Total\s*(.*)#i', $text);
        $it['Total'] = cost($totalStr);
        $it['Currency'] = currency($totalStr);

        if (preg_match('#Reserved\s+for(.+?)\s+(\d+)\s+adults?#ims', $text, $matches)) {
            $it['GuestNames'] = [nice($matches[1])];
            $it['Guests'] = $matches[2];
        }

        return [
            'parsedData' => [
                'Itineraries' => [$it],
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
