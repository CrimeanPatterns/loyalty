<?php

namespace AwardWallet\Engine\dresorts\Email;

class It3788593 extends \TAccountCheckerExtended
{
    public $reBody = 'diamondresorts.com';
    public $reBody2 = [
        "en"=> "Flight Information",
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

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->getField($this->t("Booking Ref :"));

                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[normalize-space(text())='Passenger names']/ancestor::tr[1]/following-sibling::tr/td[1]");

                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = cost($this->getField($this->t("Total For Services")));

                // BaseFare
                // Currency
                $it['Currency'] = currency($this->getField($this->t("Total For Services")));

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[normalize-space(text())='Outbound' or normalize-space(text())='Inbound']/following::table[1]/tbody/tr";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $date = strtotime($this->http->FindSingleNode("(./td[4]//text()[normalize-space(.)])[1]", $root));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]/div[contains(., 'Flight No:')]", $root, true, "#Flight No:\s+(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/div[contains(., ' to ')]", $root, true, "#\(([A-Z]{3})\)#");

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("(./td[4]//text()[normalize-space(.)])[2]", $root), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]/div[contains(., ' to ')]", $root, true, "#\([A-Z]{3}\).*?\(([A-Z]{3})\)#");

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("(./td[4]//text()[normalize-space(.)])[4]", $root), $date);

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]/div[contains(., 'Carrier:')]", $root, true, "#Carrier:\s+(.+)#");

                    // Aircraft
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./td[2]/div[contains(., 'Class:')]", $root, true, "#Class:\s+(\w+)#");

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    $itsegment['Seats'] = implode(", ", $this->http->FindNodes("./td[4]//text()[contains(., 'Seat ')]", $root, "#Seat\s+(\d{2}\w)#"));

                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
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
