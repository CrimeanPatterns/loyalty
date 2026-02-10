<?php

namespace AwardWallet\Engine\axs\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "axs/it-2853532.eml";
    public $reBody = "www.axs.com";
    public $reBody2 = "Venue Information";
    public $reFrom = "@boxoffice.axs.com";
    public $reSubject = "Your Order Confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $it = [];
                $it['Kind'] = "E";

                // ConfNo
                $it['ConfNo'] = str_replace(' ', '', $this->http->FindSingleNode("//*[contains(text(),'Order #:')]/ancestor-or-self::td[1]/following-sibling::td[1]"));
                // TripNumber
                // Name
                $it['Name'] = $this->http->FindSingleNode("//*[contains(text(),'Event:')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, "#^(.*?)\s+-#");
                // StartDate
                $it['StartDate'] = strtotime(str_replace(" at", ",", $this->http->FindSingleNode("//*[contains(text(),'Date & Time:')]/ancestor-or-self::td[1]/following-sibling::td[1]")));
                // EndDate
                // Address
                $it['Address'] = nice($this->http->FindSingleNode("//*[contains(text(),'Location:')]/ancestor-or-self::td[1]/following-sibling::td[1]"));
                // Phone
                // DinerName
                $it['DinerName'] = $this->http->FindSingleNode("//*[contains(text(),'Customer name:')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                // Guests
                $it['Guests'] = $this->http->FindSingleNode("//*[contains(text(),'Number of tickets:')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                // TotalCharge
                $it['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(),'Total Cost:')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, "#([\d\.]+)#");
                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(),'Total Cost:')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, true, "#([^\s\d\.]+)#");
                // Tax
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                $it['AccountNumbers'] = $this->http->FindSingleNode("//*[contains(text(),'Customer #:')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                // Status
                // Cancelled
                // ReservationDate
                $it['ReservationDate'] = strtotime(re("#(\w+)\s+(\d+),\s+(\d+)#", $this->http->FindSingleNode("//*[contains(text(),'Order date:')]/ancestor-or-self::td[1]/following-sibling::td[1]"), 2) . ' ' . re(1) . ' ' . re(3));
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
        return strpos($headers["from"], $this->reFrom) && strpos($headers["subject"], $this->reSubject);
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
