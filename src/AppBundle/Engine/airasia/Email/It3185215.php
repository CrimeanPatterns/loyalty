<?php

namespace AwardWallet\Engine\airasia\Email;

class It3185215 extends \TAccountCheckerExtended
{
    public $mailFiles = "";
    public $reBody = 'AirAsia';
    public $reBody2 = "Your booking has been confirmed.";
    public $reFrom = "itinerary@airasia.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Your booking number is:')]/ancestor-or-self::p/following-sibling::p[1]");
                // TripNumber
                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                $it['EarnedAwards'] = $this->http->FindSingleNode("//*[contains(text(), 'Total points earned:')]/ancestor-or-self::p/following-sibling::p[1]");

                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[text()='Flight']/ancestor::tr[1]/following-sibling::tr";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($nodes as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("(./td[1]//text()[string-length(normalize-space(.))>1])[1]", $root, true, "#\w+\s+(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("(./td[2]//text()[string-length(normalize-space(.))>1])[1]", $root);

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime(implode(", ", $this->http->FindNodes("./td[2]//td[2]/p/*[2]//span", $root)));

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("(./td[4]//text()[string-length(normalize-space(.))>1])[1]", $root);

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(implode(", ", $this->http->FindNodes("./td[4]//td[2]/p/*[2]//span", $root)));

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("(./td[1]//text()[string-length(normalize-space(.))>1])[1]", $root, true, "#(\w+)\s+(\d+)#");

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
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

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false;
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
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }
}
