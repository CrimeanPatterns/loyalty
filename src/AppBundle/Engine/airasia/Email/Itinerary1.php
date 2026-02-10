<?php

namespace AwardWallet\Engine\airasia\Email;

class Itinerary1 extends \TAccountChecker
{
    public const RESERVATION_DATE_FORMAT = 'D d M Y';
    public const DATE_FORMAT = 'D d M Y H:i';

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && $headers["from"] === 'itinerary@myses01.airasia.com')
                || stripos($headers['subject'], 'Your AirAsia booking') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = re("#Your\s+booking\s+number\s+is:\s*([\w\-]+)#", $text);
        $itineraries['Passengers'] = $this->http->FindPreg("/Dear\\s*([^\\,]*)/");
        $reservationDate = $this->http->FindPreg('/Your booking date is <[^\>]*>([^\.]*)/');
        $itineraries['ReservationDate'] = $this->buildDate(date_parse_from_format($this::RESERVATION_DATE_FORMAT, $reservationDate));

        $segments = [];

        $tripRows = $this->http->XPath->query("//strong[contains(text(), 'Flight')]/../../following-sibling::tr");

        if ($tripRows->length > 0) {
            foreach ($tripRows as $trip) {
                $date = $this->http->FindSingleNode('./td[2]', $trip);
                $depTime = $this->http->FindSingleNode('./td[5]', $trip);
                $tripSegment['DepDate'] = $this->buildDate(date_parse_from_format($this::DATE_FORMAT, $date . ' ' . $depTime));
                $flight = $this->http->FindSingleNode('./td[3]', $trip);

                if (preg_match('#(\w{2})\s*(\d+)#', $flight, $m)) {
                    $tripSegment['AirlineName'] = $m[1];
                    $tripSegment['FlightNumber'] = $m[2];
                }
                $arrTime = $this->http->FindSingleNode('./td[7]', $trip);
                $tripSegment['ArrDate'] = $this->buildDate(date_parse_from_format($this::DATE_FORMAT, $date . ' ' . $arrTime));
                $tripSegment['DepName'] = $tripSegment['DepCode'] = $this->http->FindSingleNode('./td[4]', $trip);
                $tripSegment['ArrName'] = $tripSegment['ArrCode'] = $this->http->FindSingleNode('./td[6]', $trip);

                $segments[] = $tripSegment;
            }
        } else {
            $subj = re('#Date\s+Flight\s+Depart\s+Arrive\s+(.*)\s+Terms\s+and\s+conditions\s+apply#s', $text);
            $regex = '#';
            $regex .= '\w+\s+(?P<Date>\d+\s+\w+\s+\d+)\s+';
            $regex .= '(?P<AirlineName>\w{2})\s*(?P<FlightNumber>\d+)\s+';
            $regex .= '(?P<DepCode>\w{3})\s+';
            $regex .= '(?P<DepTime>\d+:\d+)\s+';
            $regex .= '(?P<ArrCode>\w{3})\s+';
            $regex .= '(?P<ArrTime>\d+:\d+)';
            $regex .= '#';

            if (preg_match_all($regex, $subj, $matches, PREG_SET_ORDER)) {
                $tripSegment = [];

                foreach ($matches as $m) {
                    copyArrayValues($tripSegment, $m, ['AirlineName', 'FlightNumber', 'DepCode', 'ArrCode']);

                    foreach (['Dep', 'Arr'] as $key) {
                        $tripSegment["${key}Date"] = strtotime($m['Date'] . ' ' . $m["${key}Time"]);
                        $tripSegment["${key}Name"] = $m["${key}Code"];
                    }
                    $segments[] = $tripSegment;
                }
            }
        }

        $itineraries['TripSegments'] = $segments;

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]airasia\.com$/ims', $from);
    }
}
