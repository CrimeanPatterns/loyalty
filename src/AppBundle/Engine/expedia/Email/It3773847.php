<?php

namespace AwardWallet\Engine\expedia\Email;

class It3773847 extends \TAccountCheckerExtended
{
    public $mailFiles = "expedia/it-3773847.eml";
    public $reBody = "EXPEDIA.COM";
    public $reBody2 = "HOTEL CONFIRMATION";
    public $reSubject = "Your Expedia.com Hotel Reservation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = $this->text();
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Hotel Itinerary Number\s*:\s*(\w+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->getField("Name:");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Check In:"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Check Out:"));

                // Address
                $it['Address'] = $this->getField("Address:");

                // DetailedAddress

                // Phone
                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("Name:", 2)];

                // Guests
                // Kids
                // Rooms
                $it['Rooms'] = $this->getField("Rooms:");

                // Rate
                $it["Rate"] = $this->getField("Nightly Rate:");

                // RateType

                // CancellationPolicy
                // RoomType
                $it['RoomType'] = $this->getField("Room Type:");

                // RoomTypeDescription
                // Cost
                $it["Cost"] = cost($this->getField("Subtotal:"));

                // Taxes
                $it["Taxes"] = cost($this->getField("Taxes & Fees:"));

                // Total
                $it["Total"] = cost($this->getField("Total Charges:"));

                // Currency
                $it["Currency"] = currency($this->getField("Total Charges:"));

                // SpentAwards
                $it["SpentAwards"] = re("#You\s+earned\s+([\d\.,]+\s+WOWPoints)#", $text);

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
        return strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
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

    private function getField($str, $pos = 1)
    {
        return $this->http->FindSingleNode("(//text()[normalize-space(.)='{$str}'])[{$pos}]/following::text()[normalize-space(.)][1]");
    }
}
