<?php

namespace AwardWallet\Engine\designh\Email;

class It3719938 extends \TAccountCheckerExtended
{
    public $reBody = "destinationhotels";
    public $reBody2 = "Thank you for your reservation at the";
    public $reFrom = "donotreply_lorettoreservations@destinationhotels.com";
    public $reSubject = "Reservation Confirmation for";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Confirmation Number\s*:\s*(\w+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = re("#Thank you for your reservation at the\s+(.*?)\.#", $text);

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Arrival Date:") . ', ' . $this->getField("Check-in Time:"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Departure Date:") . ', ' . $this->getField("Check-out Time:"));

                // Address
                $it['Address'] = $it['HotelName'];

                // DetailedAddress

                // Phone
                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("Guest Name:")];

                // Guests
                $it['Guests'] = $this->getField("Adults:");

                // Kids
                $it['Guests'] = $this->getField("Children:");

                // Rate
                $it['Rate'] = $this->getField("Rate Per Night:");

                // RateType
                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("//*[normalize-space(text())='Deposit & Cancellation Policy:']/ancestor-or-self::b[1]/following-sibling::*[1]");

                // RoomType
                $it['RoomType'] = $this->getField("Room Type:");

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->getField("Total Room Cost:"));

                // Currency
                $it['Currency'] = currency($this->getField("Total Room Cost:"));

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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
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
        $this->subject = $parser->getHeader("subject");
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

    private function getField($str)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$str}']/following::text()[normalize-space(.)][1]");
    }
}
