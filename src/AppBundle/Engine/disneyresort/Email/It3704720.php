<?php

namespace AwardWallet\Engine\disneyresort\Email;

class It3704720 extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-58804507.eml"; // +1 bcd

    public $reBody = "Disney";
    public $reBody2 = [
        "Your Reservation Details",
        "Your Cancelled Reservation Details",
    ];
    private $reSubject = [
        "Walt Disney World Resorts Reservation Confirmation",
        "Walt Disney World Resorts Reservation Cancellation",
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$this->reBody}')]")->length === 0) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            if (strpos($body, $reBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $subj) {
            if (strpos($headers["subject"], $subj) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = $this->parseHotel();

        $result = [
            'emailType'  => 'It3704720',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function parseHotel()
    {
        $text = text($this->http->Response["body"]);

        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->getField("Confirmation Number:");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = trim($this->re("#We\s+are\s+pleased\s+to\s+confirm\s+your\s+reservation\s+at\s+(.*?),#", $text));

        if (empty($it['HotelName'])) {
            $it['HotelName'] = trim($this->re("#Your\s+reservation\s+at\s+(.*?),#", $text));
        }

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->getField("Arrival Date:") . ', ' . $this->re("#Check-In\s+after\s+(\d+:\d+\s+[AP]M)#i", $this->getField("Important Notes:")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->getField("Departure Date:") . ', ' . $this->re("#Check-Out\s+before\s+(\d+:\d+\s+[AP]M)#i", $this->getField("Important Notes:")));

        // Address
        $it['Address'] = $it['HotelName'];

        // DetailedAddress

        // Phone
        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->getField("Reservation Name:")];

        // Guests
        $guests = $this->getField("Number of Guests:");

        if (preg_match("#(\d+)\s+\d+#", $guests, $m) || preg_match("#Adults\s+(\d+)#", $guests, $m)) {
            $it['Guests'] = $m[1];
        }

        // Kids
        if (preg_match("#\d+\s+(\d+)#", $guests, $m) || preg_match("#Children\s+(\d+)#", $guests, $m)) {
            $it['Kids'] = $m[1];
        }

        // Rooms
        $it['Rooms'] = $this->getField("Number of Rooms:");

        // Rate
        // RateType

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->getField("Cancel Policy:");

        if (empty($it['CancellationPolicy'])) {
            $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'CANCELLATION POLICY:')]", null, true,
                "#CANCELLATION POLICY:\s*(.+)#");
        }

        // RoomType
        $it['RoomType'] = $this->getField("Room Type:");

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = cost($this->getField("Total Charge:"));

        // Currency
        if ($this->http->FindSingleNode("(//*[" . $this->contains("All rates are in U.S. dollars.") . "])[1]")) {
            $it['Currency'] = 'USD';
        }
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        if ($this->http->FindSingleNode("(//*[" . $this->contains(" has been cancelled") . "])[1]")) {
            $it['Status'] = 'Cancelled';
            $it['Cancelled'] = true;
        }
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->getField("Date Booked:"));

        // NoItineraries
        $itineraries[] = $it;

        return $itineraries;
    }

    private function getField($str)
    {
        return $this->http->FindSingleNode("//td[normalize-space(.)='{$str}']/following-sibling::td[1]");
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "normalize-space({$text})=\"{$s}\"";
        }, $field));
    }

    private function starts($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "starts-with(normalize-space({$text}), \"{$s}\")";
        }, $field));
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "contains(normalize-space({$text}), \"{$s}\")";
        }, $field));
    }
}
