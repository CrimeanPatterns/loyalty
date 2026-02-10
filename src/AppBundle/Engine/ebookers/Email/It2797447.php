<?php

namespace AwardWallet\Engine\ebookers\Email;

class It2797447 extends \TAccountCheckerExtended
{
    public $mailFiles = "ebookers/it-2797447.eml, ebookers/it-5752780.eml";
    public $reBody = "ebookers";
    public $reBody2 = "Your Car";
    public $reBody3 = "For Car Rental Company only";
    public $reFrom = "travellercare@ebookers.com";
    public $reSubject = "Rental Car Confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "it-2797447.eml"
            $this->reBody2 => function (&$itineraries) {
                $carRoot = "//img[contains(@src,'/car/')]/parent::td/parent::tr/..";

                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Reference')]/b");

                if (!$it['Number']) {
                    $it['Number'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Reference')]", null, false, "#:\s+([A-Z\d\-]+)#");
                }

                // TripNumber
                $it['TripNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'ebookers booking number')]/following::*[1]");

                // PickupDatetime
                $it['PickupDatetime'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Pick-up')]/..", null, true, "#Pick-up:\s+.*?(\d+\s+\w+\s+\d+\s*,\s+\d+:\d+)#"));

                // PickupLocation
                $it['PickupLocation'] = $this->http->FindSingleNode("//*[contains(text(), 'Pick-up')]/..", null, true, "#^.*?\|\s+(.*?)\s+\|#");

                if (!$it['PickupLocation']) {
                    $it['PickupLocation'] = $this->http->FindSingleNode("//*[contains(text(), 'Pick-up')]/../following-sibling::tr[contains(.,'Location')][1]", null, true, "#Location\s+(.+)#");
                }

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Drop-off')]/..", null, true, "#Drop-off:\s+.*?(\d+\s+\w+\s+\d+\s*,\s+\d+:\d+)#"));

                // DropoffLocation
                $it['DropoffLocation'] = $this->http->FindSingleNode("//*[contains(text(), 'Drop-off')]/..", null, true, "#^.*?\|\s+(.*?)\s+\|#");

                if (!$it['DropoffLocation']) {
                    $it['DropoffLocation'] = $this->http->FindSingleNode("//*[contains(text(), 'Drop-off')]/../following-sibling::tr[contains(.,'Location')][1]", null, true, "#Location\s+(.+)#");
                }

                // PickupPhone
                $it['PickupPhone'] = $this->http->FindSingleNode("//b[contains(text(), 'Phone')]/following::*[1]");

                if (!$it['PickupPhone']) {
                    $it['PickupPhone'] = $this->http->FindSingleNode("//*[contains(text(), 'Phone')]", null, false, "#Phone\s*:?\s+(.+)#");
                }

                // PickupFax
                // PickupHours
                // DropoffPhone
                // DropoffHours
                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = trim($this->http->FindSingleNode("{$carRoot}/tr[3]"));

                // CarModel
                $it['CarModel'] = trim($this->http->FindSingleNode("{$carRoot}/tr[2]"));

                // CarImageUrl
                $it['CarImageUrl'] = trim($this->http->FindSingleNode("{$carRoot}/tr[1]//img/@src"));

                // RenterName
                $it['RenterName'] = trim($this->http->FindSingleNode("//*[contains(text(), 'Car reservation under')]/following::*[1]"));

                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = $this->http->FindSingleNode("//*[contains(text(), 'Total trip cost')]/following::*[1]", null, true, "#([0-9\.]+)#");

                // Currency
                $it["Currency"] = str_replace("£", "GBP", $this->http->FindSingleNode("//*[contains(text(), 'Total trip cost')]/following::*[1]", null, true, "#([^\s0-9\.]+)#"));

                // TotalTaxAmount
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // ServiceLevel
                // Cancelled
                // PricedEquips
                // Discount
                // Discounts
                // Fees
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false && strpos($body, $this->reBody3) !== false;
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
}
