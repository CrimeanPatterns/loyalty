<?php

namespace AwardWallet\Engine\egencia\Email;

class Canceled extends \TAccountCheckerExtended
{
    public $mailFiles = "egencia/it-3555569.eml, egencia/it-3564243.eml";
    public $reBody = "Egencia";
    public $reBody2 = "Car canceled";
    public $reBody3 = "Hotel canceled";
    public $reFrom = "teamagents@customercare.egencia.com";
    public $reSubject = "Egencia cancellation confirmed";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = re("#Itinerary number\s*:\s*(\w+)#", $text);
                // TripNumber
                // PickupDatetime
                re("#Your\s+reservation\s+at\s+(.*?)\s+from\s+(\d+-\w+-\d+)\s+to\s+(\d+-\w+-\d+)\s+has\s+been\s+(canceled)#", $text);

                $it['PickupDatetime'] = strtotime(re(2));

                // PickupLocation
                $it['PickupLocation'] = re(1);

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(re(3));

                // DropoffLocation

                // PickupPhone
                // PickupFax
                // PickupHours
                // DropoffPhone
                // DropoffHours
                // DropoffFax
                // RentalCompany
                // CarType
                // CarModel
                // CarImageUrl
                // RenterName
                // PromoCode
                // TotalCharge
                // Currency
                // TotalTaxAmount
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                if (re(4)) {
                    $it['Status'] = re(4);
                }

                // ServiceLevel
                // Cancelled
                if (re(4)) {
                    $it['Cancelled'] = true;
                }
                // PricedEquips
                // Discount
                // Discounts
                // Fees
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
            $this->reBody3 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = orval(
                    re("#Hotel\s+reservation\s+confirmation\s+number\s*:\s*(\w+)#", $text),
                    re("#Itinerary number\s*:\s*(\w+)#", $text)
                );

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name

                re("#Your\s+reservation\s+for\s+(.*?)\s+at\s+(.*?),\s+(.*?)\s+from\s+(\d+-\w+-\d+)\s+to\s+(\d+-\w+-\d+)\s+has\s+been\s+(canceled)#", $text);

                $it['HotelName'] = re(2);

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re(4));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re(5));

                // Address
                $it['Address'] = re(3);

                // DetailedAddress

                // Phone
                // Fax
                // GuestNames
                $it['GuestNames'] = re(1);

                // Guests
                // Kids
                // Rooms
                // Rate
                // RateType
                // CancellationPolicy
                // RoomType
                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                // Currency
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                if (re(6)) {
                    $it['Status'] = re(6);
                }

                // Cancelled
                if (re(6)) {
                    $it['Cancelled'] = true;
                }
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

        return strpos($body, $this->reBody) !== false && (
            strpos($body, $this->reBody2) !== false
            || strpos($body, $this->reBody3) !== false
        );
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
}
