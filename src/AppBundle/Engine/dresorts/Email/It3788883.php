<?php

namespace AwardWallet\Engine\dresorts\Email;

class It3788883 extends \TAccountCheckerExtended
{
    public $reBody = 'diamondresorts.com';
    public $reBody2 = [
        "en"=> "Car Rental Information",
    ];

    public $reSubject = [
        "en"=> "DiamondResorts Invoice",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "html" => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->getField("Booking Reference:");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(str_replace(' @ ', ', ', $this->http->FindSingleNode("(//*[normalize-space(text())='Pick-up']/../text()[normalize-space(.)])[1]")));

                // PickupLocation
                $it['PickupLocation'] = implode(", ", $this->http->FindNodes("(//*[normalize-space(text())='Pick-up']/../text()[normalize-space(.)])[position()>1]"));

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(str_replace(' @ ', ', ', $this->http->FindSingleNode("(//*[normalize-space(text())='Drop-off']/../text()[normalize-space(.)])[1]")));

                // DropoffLocation
                $it['DropoffLocation'] = implode(", ", $this->http->FindNodes("(//*[normalize-space(text())='Drop-off']/../text()[normalize-space(.)])[position()>1]"));

                if (strpos($it['DropoffLocation'], 'Pick-up')) {
                    $it['DropoffLocation'] = $it['PickupLocation'];
                }

                // PickupPhone
                // PickupFax
                // PickupHours
                // DropoffPhone
                // DropoffHours
                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = $this->http->FindSingleNode("(//img[@class='car-image']/ancestor::tr[1]/td[2]//text()[normalize-space(.)])[1]");

                // CarModel
                $it['CarModel'] = $this->http->FindSingleNode("(//img[@class='car-image']/ancestor::tr[1]/td[2]//text()[normalize-space(.)])[2]");

                // CarImageUrl
                $it['CarImageUrl'] = $this->http->FindSingleNode("(//img[@class='car-image'])[1]/@src");

                // RenterName
                $it['RenterName'] = $this->getField("Driver:");

                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = cost($this->getField("Total For Services"));

                // Currency
                $it["Currency"] = currency($this->getField("Total For Services"));

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

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][1]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
