<?php

namespace AwardWallet\Engine\disneyresort\Email;

class VacationPackageReservation extends \TAccountCheckerExtended
{
    public $mailFiles = "";
    public $reBody = "Disney";
    public $reBody2 = "Vacation Package Reservation";
    public $reFrom = "dlr.guest.mail@disneyonline.com";
    public $reSubject = "Disneyland Confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // "it-2796585.eml"
            $this->reBody2 => function (&$itineraries) {
                $hotelinfo = "//*[contains(text(), 'Vacation') and contains(text(), 'Package') and contains(text(),'Reservation')]/ancestor::tr[2]/following-sibling::tr[2]//table//td[2]";

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'Package Confirmation Number:')]/..", null, true, "#Package Confirmation Number:\s+(\S+)#");

                // TripNumber
                // ConfirmationNumbers

                // HotelName
                $it['HotelName'] = $this->http->FindSingleNode($hotelinfo . "/*[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re("#(\w+)\s+(\d+)\s+\(\w+\),\s+(\d+)#", $this->http->FindSingleNode($hotelinfo . "/div[3]", null, true, "#^(.*?)\s-#"), 2) . ' ' . re(1) . ' ' . re(3));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re("#(\w+)\s+(\d+)\s+\(\w+\),\s+(\d+)#", $this->http->FindSingleNode($hotelinfo . "/div[3]", null, true, "#-\s+(.+)#"), 2) . ' ' . re(1) . ' ' . re(3));

                // Address
                $it['Address'] = nice($this->http->FindSingleNode("//td[contains(text(), 'Travel Documents')]/../following::tr[2]//p"));

                // DetailedAddress
                // Phone
                // Fax
                // GuestNames
                $GuestNames = $this->http->FindNodes("//*[contains(text(), 'Guest')][contains(text(),'name')]/parent::*/following-sibling::*");

                if (count($GuestNames) > 0) {
                    $it['GuestNames'] = array_unique($GuestNames);
                }

                // Guests
                $it['Guests'] = (int) $this->http->FindSingleNode($hotelinfo . "/div[7]", null, true, "#(\d+)\s+Adults#");

                // Kids
                $it['Kids'] = (int) $this->http->FindSingleNode($hotelinfo . "/div[7]", null, true, "#(\d+)\s+Children#");

                // Rooms
                // Rate
                // RateType
                // CancellationPolicy
                // RoomType
                $it['RoomType'] = trim($this->http->FindSingleNode($hotelinfo . "/div[9]"));

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = (float) str_replace(',', '', $this->http->FindSingleNode("//*[contains(text(), 'Payment Today')]/following::*[1]", null, true, "#([\d\.,]+)#"));

                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Payment Today')]/following::*[1]", null, true, "#([^\s\d\.,]+)#");

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }
}
