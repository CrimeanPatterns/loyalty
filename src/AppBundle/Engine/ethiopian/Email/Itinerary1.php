<?php

namespace AwardWallet\Engine\ethiopian\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATETIME_FORMAT = 'j M Y g:ia';
    public $mailFiles = "ethiopian/it-1.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && (strcasecmp($headers["from"], "Reservation@ethiopianairlines.com") == 0);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($this->http->Response['body'],
                        'itinerary is brought to you by Ethiopian Airlines')
                    !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindPreg('/Reservation code: ([A-Z]{6})/');

        $passengers = $this->http->XPath->query("//td/text()[contains(., 'Passenger(s)')]/ancestor::tr[1]/following-sibling::tr");
        $passengersSeats = [];

        if ($passengers->length > 0) {
            $i = 0;

            foreach ($passengers as $passenger) {
                if ($name = $this->http->FindSingleNode("./td[1]", $passenger)) {
                    $itineraries['Passengers'][] = $name;

                    if ($seats = $this->http->FindSingleNode("./td[3]", $passenger)) {
                        $passengersSeats[$i] = explode(",", $seats);
                    }
                }
                $i++;
            }
        }

        $segments = $this->http->XPath->query("//tr[./td/text()[contains(., 'Date')] and ./td/text()[contains(., 'From')]]/ancestor::tr[2]/following-sibling::tr//tr[count(td)>1]");

        if ($segments->length > 0) {
            $itineraries['TripSegments'] = [];
            $i = 0;

            foreach ($segments as $segment) {
                $flight = $this->http->FindSingleNode("./td[4]", $segment);
                $flightData = explode(' ', $flight);
                $itineraries['TripSegments'][$i]['AirlineName'] = $flightData[0];
                $itineraries['TripSegments'][$i]['FlightNumber'] = $flightData[1];

                $itineraries['TripSegments'][$i]['DepCode'] = TRIP_CODE_UNKNOWN;
                $itineraries['TripSegments'][$i]['DepName'] =
                    $this->http->FindSingleNode("./td[2]/br[1]/preceding-sibling::node()[1][self::text()]", $segment);
                $terminal = $this->http->FindSingleNode("./td[2]/br[2]/following-sibling::node()[1][self::text()]", $segment);

                if ($terminal) {
                    $itineraries['TripSegments'][$i]['DepName'] .= " ($terminal)";
                }

                $itineraries['TripSegments'][$i]['ArrCode'] = TRIP_CODE_UNKNOWN;
                $itineraries['TripSegments'][$i]['ArrName'] =
                    $this->http->FindSingleNode("./td[3]/br[1]/preceding-sibling::node()[1][self::text()]", $segment);
                $terminal = $this->http->FindSingleNode("./td[3]/br[2]/following-sibling::node()[1][self::text()]", $segment);

                if ($terminal) {
                    $itineraries['TripSegments'][$i]['ArrName'] .= " ($terminal)";
                }

                $depArrDates = explode('-', $this->http->FindSingleNode("./td[1]", $segment));

                if (count($depArrDates) > 1) {
                    $depDateString = $depArrDates[0];
                    $arrDateString = $depArrDates[1];
                } else {
                    $depDateString = $depArrDates[0];
                    $arrDateString = $depDateString;
                }
                $depTimeString = $this->http->FindSingleNode("./td[2]/br[1]/following-sibling::node()[1][self::text()]", $segment);
                $arrTimeString = $this->http->FindSingleNode("./td[3]/br[1]/following-sibling::node()[1][self::text()]", $segment);
                $itineraries['TripSegments'][$i]['DepDate'] =
                    $this->_buildDate(date_parse_from_format(self::DATETIME_FORMAT, $depDateString . $depTimeString));
                $itineraries['TripSegments'][$i]['ArrDate'] =
                    $this->_buildDate(date_parse_from_format(self::DATETIME_FORMAT, $arrDateString . $arrTimeString));

                $itineraries['TripSegments'][$i]['Cabin'] =
                    $this->http->FindSingleNode("td[5]/br[1]/following-sibling::node()[1][self::text()]", $segment);

                $seats = "";

                for ($j = 0; $j < count($itineraries['Passengers']); $j++) {
                    $seats .= $passengersSeats[$j][$i] . ", ";
                }
                $itineraries['TripSegments'][$i]['Seats'] = rtrim($seats, ", ");

                $itineraries['TripSegments'][$i]['Status'] =
                    $this->http->FindSingleNode("td[5]/br[1]/preceding-sibling::node()[1][self::text()]", $segment);

                $i++;
            }
        }

        return [
            'emailType'  => 'TicketReceipt',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]ethiopianairlines\.com/", $from);
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
