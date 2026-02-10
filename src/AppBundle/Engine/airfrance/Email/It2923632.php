<?php

namespace AwardWallet\Engine\airfrance\Email;

class It2923632 extends \TAccountCheckerExtended
{
    public $mailFiles = "airfrance/it-2923632.eml, airfrance/it-2923652.eml";
    public $reBody = "Thank you for choosing Air France for your car rental";
    // var $reBody2 = "Your car rental confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation number')]/span");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Date and time')]/following-sibling::td[1]"));

                // PickupLocation
                $it['PickupLocation'] = $this->http->FindSingleNode("//*[contains(text(), 'Address')]/following-sibling::td[1]");

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Date and time')]/following-sibling::td[2]"));

                // DropoffLocation
                $it['DropoffLocation'] = $this->http->FindSingleNode("//*[contains(text(), 'Address')]/following-sibling::td[2]");

                if ($it['DropoffLocation'] == 'Same as pick-up') {
                    $it['DropoffLocation'] = $it['PickupLocation'];
                }

                // PickupPhone
                $it['PickupPhone'] = $this->http->FindSingleNode("//*[contains(text(), 'Tel')]/following-sibling::td[1]");

                // PickupFax
                // PickupHours
                $it['PickupHours'] = trim($this->http->FindSingleNode("//*[contains(text(), 'Opening hours')]/following-sibling::td[1]"), '- ');

                // DropoffPhone
                $it['DropoffPhone'] = $this->http->FindSingleNode("//*[contains(text(), 'Tel')]/following-sibling::td[2]");

                if ($it['DropoffPhone'] == 'Same as pick-up') {
                    $it['DropoffPhone'] = $it['PickupPhone'];
                }

                // DropoffHours
                $it['DropoffHours'] = trim($this->http->FindSingleNode("//*[contains(text(), 'Opening hours')]/following-sibling::td[2]"), '- ');

                if ($it['DropoffHours'] == 'Same as pick-up') {
                    $it['DropoffHours'] = $it['PickupHours'];
                }

                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = $this->http->FindSingleNode("//*[contains(text(),'Category')]/following-sibling::td[1]", null, true, '#^(.*?)\s*-#');

                // CarModel
                $it['CarModel'] = $this->http->FindSingleNode("//*[contains(text(),'Category')]/following-sibling::td[1]", null, true, '#^.*?\s*-\s*(.+)#');

                // CarImageUrl
                // RenterName
                $it['RenterName'] = $this->http->FindSingleNode("//*[contains(text(),'Driver name')]/following-sibling::td[1]");

                // PromoCode
                $it['PromoCode'] = $this->http->FindSingleNode("//*[contains(text(),'Discount code')]/following-sibling::td[1]");

                // TotalCharge
                $it["TotalCharge"] = $this->http->FindSingleNode("//*[contains(text(), 'total')]/ancestor::td[1]/preceding-sibling::td[2]", null, true, "#([\d\.]+)#");

                // Currency
                $it["Currency"] = $this->http->FindSingleNode("//*[contains(text(), 'total')]/ancestor::td[1]/preceding-sibling::td[2]", null, true, "#[\d\.]+\s+(\w+)#");

                // TotalTaxAmount
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it["Status"] = $this->http->FindSingleNode("//*[contains(text(), 'Booking status:')]/span");

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

        return strpos($body, $this->reBody) !== false;
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
