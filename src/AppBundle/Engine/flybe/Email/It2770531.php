<?php

namespace AwardWallet\Engine\flybe\Email;

class It2770531 extends \TAccountCheckerExtended
{
    public $mailFiles = "flybe/it-2770531.eml";
    public $reBody = 'Your Flybe booking reference is';
    public $reBody2 = "The schedule that ";
    public $reSubject = "Confirmation of changes to your Flybe schedule";
    private $year = '';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "it-2770531.eml"
            $this->reBody2 => function (&$itineraries) {
                // echo $this->http->Response['body'];
                // die();
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Your Flybe booking reference is')]/strong");

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
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[contains(text(), 'Flight Number')]/ancestor::tr[1]/following-sibling::tr";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]", $root);

                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root) . ' ' . $this->year . ', ' . $this->http->FindSingleNode("./td[5]", $root));

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]", $root);

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root) . ' ' . $this->year . ', ' . $this->http->FindSingleNode("./td[6]", $root));

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#([A-Z]+)#");

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
        return strpos($headers["subject"], $this->reSubject);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getHeader("date")));

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
