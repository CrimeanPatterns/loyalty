<?php

namespace AwardWallet\Engine\celebritycruises\Email;

class Cruise extends \TAccountCheckerExtended
{
    public $mailFiles = "celebritycruises/it-2924732.eml";

    public $reBody = 'Celebrity Cruises';
    public $reBody2 = "We look forward to welcoming you on board.";
    public $reSubject = "Your Celebrity Cruises Reservation Confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "it-2758409.eml"
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking Number')]/..", null, true, "#Booking Number\s*:\s*(\d+)#");

                // TripNumber
                // Passengers
                // AccountNumbers
                // Cancelled
                // ShipName
                // ShipCode
                // CruiseName
                // Deck
                // RoomNumber
                // RoomClass
                // Status

                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

                // TripSegments

                // Departing
                $itsegment = [];
                // Port
                $itsegment['Port'] = $this->http->FindSingleNode("//*[contains(text(), 'Departing From')]/..", null, true, "#Departing From\s*:\s*(.+)#");
                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Sail Date')]/..", null, true, "#Sail Date\s*:\s*(.+)#"));
                $it['TripSegments'][] = $itsegment;

                // Returning
                $itsegment = [];
                // Port
                $itsegment['Port'] = $this->http->FindSingleNode("//*[contains(text(), 'Departing From')]/..", null, true, "#Departing From\s*:\s*(.+)#");
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Returning')]/..", null, true, "#Returning\s*:\s*(.+)#"));
                $it['TripSegments'][] = $itsegment;

                // Convert
                $this->converter = new \CruiseSegmentsConverter();
                $it['TripSegments'] = $this->converter->Convert($it['TripSegments']);

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
        return strpos($headers["subject"], $this->reSubject) !== false;
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
