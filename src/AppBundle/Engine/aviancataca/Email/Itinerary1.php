<?php

namespace AwardWallet\Engine\aviancataca\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-1.eml, aviancataca/it-2.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->checkMails($headers["from"]);
    }

    public function detectEmailFromProvider($from)
    {
        return in_array($from, ["edesk@taca.com", "reserva@aviancataca.com"]);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->checkMails($parser->getHTMLBody());
    }

    public function checkMails($input = '')
    {
        preg_match('/([\.@]aviancataca\.com)|([\.@]taca\.com)/ims', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = null;

        switch ($this->getItineraryProvider($parser->getPlainBody())) {
            case 'taca':
                $itineraries = $this->getTacaItineraries();

            break;

            case 'aviancataca':
                $itineraries = $this->getAviancatacaItineraries();

            break;
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => $itineraries,
            ],
        ];
    }

    public function getItineraryProvider($text)
    {
        $parserName = null;
        preg_match('/(@aviancataca\.com)|(www\.taca\.com)/i', $text, $match);

        if (isset($match[0])) {
            $parserName = preg_replace('/@|\.com|www\./i', '', $match[0]);
        }

        return $parserName;
    }

    public function getAviancatacaItineraries()
    {
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = trim($this->http->FindSingleNode("//td[contains(text(), 'Reference number')]/span[1]"));
        $passengerRow = $this->http->XPath->query("//tbody[count(tr) > 9 and tr[contains(., 'Frequent')]]");

        foreach ($passengerRow as $row) {
            $itineraries['Passengers'] = $this->http->FindNodes("./tr[2]/td[1]", $row);
            $itineraries['AccountNumbers'] = $this->http->FindSingleNode(".//td[contains(., 'Frequent')]/following-sibling::td[1]", $row);
        }
        $charges = $this->getCharges($this->http->FindSingleNode(".//td[contains(., 'Payment')]/following-sibling::td[1]"));
        $itineraries['TotalCharge'] = $charges['Ammount'];
        $itineraries['Currency'] = $charges['CurrencyName'];
        $itineraries['BaseFare'] = $charges['CurrencyName'];
        preg_match_all('/\d+\.\d+/', $this->http->FindSingleNode("//td[contains(text(), 'Passengers')]/../following-sibling::tr[1]"), $tax);

        if (isset($tax[0][0])) {
            $itineraries['BaseFare'] = $tax[0][0];
        }

        if (isset($tax[0][1])) {
            $itineraries['Tax'] = $tax[0][1];
        }
        $iterator = 0;
        $date = '';
        $rowSegments = $this->http->XPath->query("//tbody[count(tr) > 5 and tr[contains(., 'Segment')]]/tr");
        $segments = [];

        foreach ($rowSegments as $row) {
            if ($this->http->FindSingleNode("./td[contains(text(), 'Segment')]", $row)) {
                $date = $this->http->FindSingleNode(".//td[contains(text(), 'Segment')]/following-sibling::td[1]", $row);
                $date = preg_replace('/^[A-z]+,|,/', '', $date);
                $seats = $this->http->XPath->query("//div/*[contains(.,'Flight special requests')]/following-sibling::table[1]/tbody/tr[contains(.,'Segment')]");

                foreach ($seats as $key => $row) {
                    if ($key == $iterator) {
                        $customerSeat = $this->http->FindSingleNode(".//td[2]", $row);
                    }
                }
                $iterator++;

                if (isset($customerSeat)) {
                    $segments[$iterator]['Seats'] = $customerSeat;
                }
            }

            if ($this->http->FindSingleNode(".//td[contains(text(), 'Airline:')]/following-sibling::td[1]", $row)) {
                $node = $this->http->FindSingleNode(".//td[contains(text(), 'Airline:')]/following-sibling::td[1]", $row);

                if (preg_match("/.*\b([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $node, $m)) {
                    $segments[$iterator]['AirlineName'] = $m[1];
                    $segments[$iterator]['FlightNumber'] = $m[2];
                } else {
                    $segments[$iterator]['FlightNumber'] = $node;
                }
            }

            if ($this->http->FindSingleNode(".//td[contains(text(), 'Aircraft:')]/following-sibling::td[1]", $row)) {
                $segments[$iterator]['Aircraft'] = $this->http->FindSingleNode(".//td[contains(text(), 'Aircraft:')]/following-sibling::td[1]", $row);
            }

            if ($this->http->FindSingleNode(".//td/span[contains(text(), 'Departure:')]/../following-sibling::td[1]", $row)) {
                $time = $this->http->FindSingleNode(".//td/span[contains(text(), 'Departure:')]/../following-sibling::td[1]", $row);
                $segments[$iterator]['DepDate'] = strtotime($date . ' ' . $time);
            }

            if ($this->http->FindSingleNode(".//td/span[contains(text(), 'Departure:')]/../following-sibling::td[2]", $row)) {
                $segments[$iterator]['DepName'] = $this->http->FindSingleNode(".//td/span[contains(text(), 'Departure:')]/../following-sibling::td[2]", $row);
                $segments[$iterator]['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if ($this->http->FindSingleNode(".//td/span[contains(text(), 'Arrival:')]/../following-sibling::td[1]", $row)) {
                $time = $this->http->FindSingleNode(".//td/span[contains(text(), 'Arrival:')]/../following-sibling::td[1]", $row);
                $segments[$iterator]['ArrDate'] = strtotime($date . ' ' . $time);
            }

            if ($this->http->FindSingleNode(".//td/span[contains(text(), 'Arrival:')]/../following-sibling::td[2]", $row)) {
                $segments[$iterator]['ArrName'] = $this->http->FindSingleNode(".//td/span[contains(text(), 'Arrival:')]/../following-sibling::td[2]", $row);
                $segments[$iterator]['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (!isset($segments[$iterator]['AirlineName']) && $this->http->FindSingleNode(".//td/span[contains(text(), 'Operated by')]", $row)) {
                $segments[$iterator]['AirlineName'] = str_replace('Operated by ', '', $this->http->FindSingleNode(".//td/span[contains(text(), 'Operated by')]", $row));
            }
        }
        $itineraries['TripSegments'] = $segments;

        return [$itineraries];
    }

    public function getTacaItineraries()
    {
        $itineraries = null;
        $airPorts = null;
        $airPortsExtra = null;
        $itineraries['Kind'] = 'T';
        $itineraries['Passengers'] = $this->http->FindNodes("//p[4]/font/../following-sibling::table[1]//tr/td/font");
        $itineraries['RecordLocator'] = trim($this->http->FindSingleNode("//font[contains(text(), 'Reservation')]/following-sibling::font[1]"));
        $charge = $this->getCharges($this->http->FindSingleNode("//font[contains(text(), 'Charge')]/following-sibling::font[1]"));

        if (!empty($charge['Ammount'])) {
            $itineraries['TotalCharge'] = $charge['Ammount'];
            $itineraries['Currency'] = $charge['CurrencyName'];
        }
        $fare = $this->getCharges($this->http->FindSingleNode("//td/font[contains(text(), 'Fare')]/../following-sibling::td[1]/font"));

        if (!empty($fare['Ammount'])) {
            $itineraries['BaseFare'] = $fare['Ammount'];
        }
        $tax = $this->getCharges($this->http->FindSingleNode("//td/font[contains(text(), 'Taxes')]/../following-sibling::td[1]/font"));

        if (!empty($tax['Ammount'])) {
            $itineraries['Tax'] = $tax['Ammount'];
        }

        $rawRows = $this->http->XPath->query("//tr[count(td) = 6 and td[contains(., 'AM') or contains(., 'PM')]]");

        foreach ($rawRows as $key => $row) {
            $airPorts[$key] = $this->getAirport($this->http->FindSingleNode("./td[1]", $row));
            $airPorts[$key]['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $row);
            $airPorts[$key]['AirlineName'] = $this->http->FindSingleNode("./td[3]", $row);
            $airPorts[$key]['Cabin'] = $this->http->FindSingleNode("./td[4]", $row);
            $airPorts[$key]['DepDate'] = strtotime($this->http->FindSingleNode("./td[5]", $row));
            $airPorts[$key]['ArrDate'] = strtotime($this->http->FindSingleNode("./td[6]", $row));
        }
        $extraRawRows = $this->http->XPath->query("//tr[count(td) > 6 and td[contains(., 'AM') or contains(., 'PM')]]");

        foreach ($extraRawRows as $row) {
            $airPortsExtra[0] = $this->getAirport($this->http->FindSingleNode("./td[1]", $row));
            $airPortsExtra[0]['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $row);
            $airPortsExtra[0]['AirlineName'] = $this->http->FindSingleNode("./td[3]", $row);
            $airPortsExtra[0]['Cabin'] = $this->http->FindSingleNode("./td[4]", $row);
            $airPortsExtra[0]['DepDate'] = strtotime($this->http->FindSingleNode("./td[5]", $row));
            $airPortsExtra[0]['ArrDate'] = strtotime($this->http->FindSingleNode("./td[6]", $row));

            $airPortsExtra[1] = $this->getAirport($this->http->FindSingleNode("./td[7]", $row));
            $airPortsExtra[1]['FlightNumber'] = $this->http->FindSingleNode("./td[8]", $row);
            $airPortsExtra[1]['AirlineName'] = $this->http->FindSingleNode("./td[9]", $row);
            $airPortsExtra[1]['Cabin'] = $this->http->FindSingleNode("./td[10]", $row);
            $airPortsExtra[1]['DepDate'] = strtotime($this->http->FindSingleNode("./td[11]", $row));
            $airPortsExtra[1]['ArrDate'] = strtotime($this->http->FindSingleNode("./td[12]", $row));
        }

        $itineraries['TripSegments'] = array_merge($airPorts, $airPortsExtra);

        return [$itineraries];
    }

    public function getCharges($string)
    {
        preg_match('/[0-9].+?\.[0-9].+?/', $string, $charge);
        preg_match('/\s?[A-Z]{3}\s?/i', $string, $currency);
        $charge = [
            'Ammount'      => (isset($charge[0])) ? $charge[0] : '',
            'CurrencyName' => (isset($currency[0])) ? trim($currency[0]) : '',
        ];

        return $charge;
    }

    public function getAirport($airports)
    {
        $portsInfo = null;
        $airportNames = preg_split('/\sto\s/i', preg_replace('/\s\([A-Z]{3}\)/i', '', $airports));

        if (!empty($airportNames)) {
            if (isset($airportNames[0])) {
                $portsInfo['DepName'] = $airportNames[0];
            }

            if (isset($airportNames[1])) {
                $portsInfo['ArrName'] = $airportNames[1];
            }
        }
        preg_match_all('/\([A-Z]{3}\)/i', $airports, $matches);

        if (isset($matches[0])) {
            if (isset($matches[0][0])) {
                $portsInfo['DepCode'] = preg_replace('/\(|\)/i', '', $matches[0][0]);
            }

            if (isset($matches[0][1])) {
                $portsInfo['ArrCode'] = preg_replace('/\(|\)/i', '', $matches[0][1]);
            }
        }

        return $portsInfo;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }
}
