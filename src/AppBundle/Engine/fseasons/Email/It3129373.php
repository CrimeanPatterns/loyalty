<?php

namespace AwardWallet\Engine\fseasons\Email;

class It3129373 extends \TAccountCheckerExtended
{
    public $mailFiles = "fseasons/it-3129373.eml";
    public $reBody = "Four Seasons";
    public $reBody2 = "Use the Four Seasons App to";
    public $reFrom = "fourseasons@email.fourseasons.com";
    public $reSubject = "Save time - check in now with our app";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->getField("RESERVATION CONFIRMATION NUMBER:");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//a[@title='View Map']/preceding::text()[string-length(normalize-space(.))>1][1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("CHECK IN:"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("CHECK OUT:"));

                // Address
                $it['Address'] = $this->http->FindSingleNode("//a[@title='View Map']");

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->http->FindSingleNode("//a[@title='Call']");

                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("GUEST:")];

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

    private function getField($field)
    {
        $xpath = "//text()[normalize-space(.)='{$field}']/following::text()[string-length(normalize-space(.))>1][1]";

        return $this->http->FindSingleNode($xpath);
    }
}
