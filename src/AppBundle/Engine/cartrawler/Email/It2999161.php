<?php

namespace AwardWallet\Engine\cartrawler\Email;

class It2999161 extends \TAccountCheckerExtended
{
    public $mailFiles = "cartrawler/it-2999161.eml";
    public $reBody = "Your Customer Care Team";
    public $reBody2 = "Your car rental voucher";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $a = $this->http->FindSingleNode("//a[contains(@href, 'voucher.php')]/@href");
                $site = clone $this->http;

                if ($a = $this->http->FindSingleNode("//a[contains(@href, 'voucher.php')]/@href")) {
                    $site->GetURL($a);
                }

                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->getField("Confirmation no.", false);
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(str_replace(" /", ",", $this->getField("Date, Time", false, 0)));

                // PickupLocation
                $it['PickupLocation'] = $this->getField("Address", false, 0);

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(str_replace(" /", ",", $this->getField("Date, Time", false, 1)));

                // DropoffLocation
                $it['DropoffLocation'] = $this->getField("Address", false, 1);

                // PickupPhone
                $it['PickupPhone'] = $this->getField("Desk telephone no.", false, 0);

                // PickupFax
                // PickupHours
                // DropoffPhone
                $it['DropoffPhone'] = $this->getField("Desk telephone no.", false, 1);

                // DropoffHours
                // DropoffFax
                // RentalCompany
                $it['RentalCompany'] = $this->getField("Car rental provider", false);

                // CarType
                // CarModel
                $it['CarModel'] = $this->getField("Car type", false);

                // CarImageUrl
                // RenterName
                $it['RenterName'] = $this->getField("Lead driver's name", false);

                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = cost($this->getField("Total cost", false, false, "#.+#", $site));

                // Currency
                $it["Currency"] = currency($this->getField("Total cost", false, false, "#.+#", $site));

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

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
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

    public function getField($str, $strictly = true, $n = false, $regexp = "#.+#", $browser = false)
    {
        if (!$browser) {
            $browser = $this->http;
        }

        if (!$strictly) {
            if ($n === false) {
                return $browser->FindSingleNode("//*[contains(text(), \"{$str}\")]/ancestor-or-self::*[self::td or self::th][1]/following-sibling::td[1]", null, true, $regexp);
            } else {
                $nodes = $browser->FindNodes("//*[contains(text(), \"{$str}\")]/ancestor-or-self::*[self::td or self::th][1]/following-sibling::td[1]", null, $regexp);

                return $nodes[$n] ?? null;
            }
        } else {
            if ($n === false) {
                return $browser->FindSingleNode("//*[text()=\"{$str}\"]/ancestor-or-self::*[self::td or self::th][1]/following-sibling::td[1]", null, true, $regexp);
            } else {
                $nodes = $browser->FindNodes("//*[text()=\"{$str}\"]/ancestor-or-self::*[self::td or self::th][1]/following-sibling::td[1]", null, $regexp);

                return $nodes[$n] ?? null;
            }
        }
    }
}
