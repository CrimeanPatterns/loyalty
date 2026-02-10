<?php

namespace AwardWallet\Engine\advrent\Email;

class It3793144 extends \TAccountCheckerExtended
{
    public $reBody = 'Advantage';
    public $reBody2 = [
        "en"=> "Below is your information for your upcoming reservation",
    ];
    public $reSubject = [
        "en"=> "Advantage Reservation",
    ];

    public $reFrom = "reservations@advantage.com";

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
                $it['Number'] = $this->getField("Confirmation Number");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(str_replace(" at ", ", ", $this->getField("Pick Up Information", 2)));

                // PickupLocation
                $it['PickupLocation'] = $this->getField("Pick Up Information");

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(str_replace(" at ", ", ", $this->getField("Return", 3)));

                // DropoffLocation
                $it['DropoffLocation'] = $this->getField("Return", 2);

                // PickupPhone
                // PickupFax
                // PickupHours
                // DropoffPhone
                // DropoffHours
                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = $this->getField("Your Reservation");

                // CarModel
                // CarImageUrl
                // RenterName
                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = cost($this->getField("Total:"));

                // Currency
                $it["Currency"] = currency($this->getField("Total:"));

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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
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

    private function getField($field, $n = 1)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][{$n}]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
