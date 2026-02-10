<?php

namespace AwardWallet\Engine\delta\Email;

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "delta/it-10.eml, delta/it-11.eml, delta/it-12.eml, delta/it-13.eml, delta/it-14.eml, delta/it-1693844.eml, delta/it-7.eml, delta/it-8.eml, delta/it-9.eml";
    // YOUR ITINERARY AND RECEIPT or YOUR ITINERARY AND PASSENGER DETAILS
    // subject: mix of traveler's name, route and date or 'Delta Reservation Itinerary'

    public function ParseEmail($subject = "", $emailDate)
    {
        $it = ["Kind" => "T"];

        foreach ($this->http->XPath->query("//sup") as $sup) {
            $sup->nodeValue = "";
        }
        $year = "";
        preg_match("/\d{2}[A-Z]{3}(\d{2})/", $subject, $matches);

        if ($matches && strtotime($matches[0])) {
            $year = '20' . $matches[1];
        }
        $conf = $this->http->FindSingleNode("//font[contains(text(), 'Flight Confirmation #:')]/font[1]");

        if (!$conf) {
            $conf = $this->http->FindSingleNode("//font[contains(text(), 'Confirmation #')]/strong");
        }

        if (!$conf) {
            $conf = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation #:')]/parent::*/parent::*", null, true, "/Confirmation #: ([A-Z\d]{6})/");
        }

        if ($conf) {
            $it["RecordLocator"] = $conf;
        }
        $passInfo = $this->http->XPath->query("//*[contains(normalize-space(text()), 'SkyMiles #')]/ancestor::tr[1]");
        $passengers = [];
        $seats = [];

        for ($i = 0; $i < $passInfo->length; $i++) {
            if ($name = $this->http->FindSingleNode("td[2]//strong", $passInfo->item($i))) {
                $passengers[] = beautifulName($name);
            } elseif ($name = $this->http->FindSingleNode("td[2]//b", $passInfo->item($i))) {
                $passengers[] = beautifulName($name);
            }
            $seatNodes = $this->http->XPath->query("td[4]/table//tr", $passInfo->item($i));

            for ($j = 0; $j < $seatNodes->length; $j++) {
                $node = $seatNodes->item($j);
                $seat = $this->http->FindSingleNode("td[3]", $node, true, "/\d+[A-Z]/");

                if ($seat !== null) {
                    $seats[$j][] = $seat;
                }
            }
        }
        $it["Passengers"] = $passengers;
        // codes from Baggage section - more reliable, but sometimes it gets cut
        $nodes = $this->http->XPath->query("//*[contains(normalize-space(text()), 'Baggage Fees')]/ancestor::table[1]/following-sibling::*");
        $codes = [];

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);

            if (stripos(CleanXMLValue($node->nodeValue), 'fees may apply') === 0) {
                break;
            }

            if (preg_match("/\s([A-Z]{3})\s([A-Z]{3})\s/", CleanXMLValue($node->nodeValue), $matches)) {
                if (!in_array("GET", $matches)) {
                    $codes[] = [$matches[1], $matches[2]];
                }
            }
        }

        // codes from Fare Details section. less reliable
        $altCodes = [];
        $fare = $this->http->FindSingleNode("//strong[contains(text(), 'Fare Details')]/ancestor::table[1]/following-sibling::table[1]//strong");

        if (!$fare) {
            $fare = $this->http->FindSingleNode("//*[contains(text(), 'Fare Details')]/ancestor::table[1]/following-sibling::table[1]//b");
        }

        if (!$fare) {
            $fare = $this->http->FindSingleNode("//*[contains(text(), 'Fare Details')]/ancestor::table[1]/following-sibling::table[1]//strong");
        }
        $fare = " " . preg_replace([
            "/END.*$/",
            "/[XE]\//",
            "/USD\s?[\d\.]+/",
            "/NUC\s?[\d\.]+/",
        ], "", $fare);
        preg_match_all("/\s([A-Z]{3})/", $fare, $matches);

        if ($matches) {
            $altCodes = $matches[1];
        }

        $anchor = $this->http->XPath->query("//*[contains(normalize-space(text()), 'Flight Information')]/ancestor::table[1]");

        if ($anchor->length > 0) {
            $anchor = $anchor->item(0);
        } else {
            $anchor = null;
        }
        $flights = $this->http->XPath->query("following-sibling::table[1]/tr | following-sibling::table[1]/tbody/tr", $anchor);

        if ($flights->length == 0 && isset($anchor)) {
            $anchor = $this->http->XPath->query("ancestor::*[following-sibling::table[1]]", $anchor)->item(0);
            $flights = $this->http->XPath->query("following-sibling::table[1]/tr | following-sibling::table[1]/tbody/tr", $anchor);
        }
        $segments = [];
        $day = null;
        $code = 0;
        $idx = 0;
        $airlines = [];

        for ($i = 0; $i < $flights->length; $i++) {
            $row = $flights->item($i);
            $td = $this->http->FindSingleNode("td[3]", $row);
            preg_match("/[a-zA-Z]{3}\s(\d+[a-zA-Z]{3})/", $td, $matches);

            if ($matches) {
                $day = $matches[1];

                continue;
            }

            if (stripos($td, 'LV') !== false && isset($day)) {
                $segment = [];
                $depTime = strtoupper($this->http->FindSingleNode("td[3]//strong | td[3]//b", $row));
                $segment["DepDate"] = $this->realTime($depTime . " " . $day . $year);

                if (empty($year) && $segment["DepDate"] && $emailDate) {
                    // get year from email date
                    $year = date("Y", $emailDate);
                    $segment["DepDate"] = $this->realTime($depTime . " " . $day . " " . $year);

                    if ($segment["DepDate"] < $emailDate) {
                        $year++;
                        $segment["DepDate"] = $this->realTime($depTime . " " . $day . $year);
                    }
                }
                $arrTime = strtoupper($this->http->FindSingleNode("td[7]//strong | td[7]//b", $row));
                $arrDate = $this->http->FindSingleNode("td[7]//tr[2]", $row, true, "/[a-zA-Z]{3}\s(\d+[a-zA-Z]{3})/");

                if (!$arrDate) {
                    $arrDate = $day;
                }
                $segment["ArrDate"] = $this->realTime($arrTime . " " . $arrDate . $year);
                $segment["DepName"] = beautifulName($this->http->FindSingleNode("td[5]", $row));
                $segment["ArrName"] = beautifulName($this->http->FindSingleNode("td[9]", $row));
                $segment["DepCode"] = $segment["ArrCode"] = "";

                if (empty($codes)) {
                    if (!empty($altCodes)) {
                        if (isset($altCodes[$code])) {
                            $segment["DepCode"] = $altCodes[$code];
                        }

                        if (isset($altCodes[++$code])) {
                            $segment["ArrCode"] = $altCodes[$code];
                        }
                    }
                } else {
                    if (isset($codes[$code])) {
                        $segment["DepCode"] = $codes[$code][0];
                    }

                    if (isset($codes[$code])) {
                        $segment["ArrCode"] = $codes[$code][1];
                    }
                    $code++;
                }

                if (empty($segment["DepCode"])) {
                    $segment["DepCode"] = TRIP_CODE_UNKNOWN;
                }

                if (empty($segment["ArrCode"])) {
                    $segment["ArrCode"] = TRIP_CODE_UNKNOWN;
                }
                $flightNumber = $this->http->FindSingleNode("td[11]/font[1]", $row, true, "/^\D+(\d+\*?)/");

                if (!$flightNumber) {
                    $flightNumber = $this->http->FindSingleNode("td[11]", $row, true, "/^\D+(\d+\*?)/");
                }

                if ($flightNumber !== null) {
                    if (stripos($flightNumber, "*") !== false) {
                        $flightNumber = trim($flightNumber, "*");
                        $airline = $this->http->FindNodes("//*[contains(text(), '*Flight " . $flightNumber . " Operated by')]");

                        if (!isset($airlines[$flightNumber])) {
                            $airlines[$flightNumber] = 0;
                        }
                        $ix = $airlines[$flightNumber];

                        if (isset($airline[$ix])) {
                            $segment["Operator"] = beautifulName(preg_replace("/^.+Operated by /", "", $airline[$ix]));
                            $airlines[$flightNumber]++;
                        }
                    }
                    $segment["FlightNumber"] = $flightNumber;
                    $segment['AirlineName'] = 'DL';
                }
                $cabin = $this->http->FindSingleNode("td[11]/font[1]/text()[last()]", $row);

                if (!$cabin) {
                    $cabin = $this->http->FindSingleNode("td[11]", $row, true, "/^\D+\d+\*?\s*(\w+\s*\(\w+\))/");
                }

                if (preg_match("/^([^\(]+)\s?\(([A-Z]+)\)/", $cabin, $matches)) {
                    $segment["Cabin"] = trim(beautifulName($matches[1]));
                    $segment["BookingClass"] = $matches[2];
                } elseif (preg_match("/^\(([A-Z]+)\)$/", $cabin, $matches)) {
                    $segment["BookingClass"] = $matches[1];
                } else {
                    $segment["Cabin"] = beautifulName($cabin);
                }
                $segment["Meal"] = $this->http->FindSingleNode("td[11]/font[2]", $row);

                if (isset($seats[$idx])) {
                    $segment["Seats"] = implode(", ", $seats[$idx]);
                }
                $idx++;

                $segments[] = $segment;
            }
        }
        $it["TripSegments"] = $segments;

        return [$it];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $emailDate = strtotime($parser->getHeader('date'));
        $its = $this->ParseEmail($parser->getSubject(), $emailDate);

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "ItineraryReceipt",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Delta Reservation Itinerary') !== false
        || isset($headers['from']) && stripos($headers['from'], 'deltaairlines@e.delta.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $body = preg_replace('/\s+/', ' ', $body);

        return stripos($body, 'Thanks for choosing Delta') !== false
        || stripos($body, 'Thank you for choosing Delta') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from);
    }

    public function GetStatementCriteria()
    {
        return ['FROM "DeltaAirLines@e.delta.com"'];
    }

    private function realTime($time)
    {
        $time = str_replace("12:00N", "12:00PM", $time);
        $time = strtotime($time);
        //		we're getting year from date header
        //		if ($time && $time < strtotime('- 4 months'))
        //			$time = strtotime("+ 1 year", $time);
        return $time;
    }
}
