<?php

namespace AwardWallet\Engine\extrip\Email;

class ETicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "exploretrip/it-10113192.eml, extrip/it-10113192.eml, extrip/it-10348630.eml";

    public $reFrom = "support@exploretrip.com";
    public $reProvider = "@exploretrip.com";
    public $reSubject = [
        'en'  => ['E-Ticket Confirmation for PNR', 'from Explore Trip'],
        'en2' => ['Booking Information for PNR', 'from Explore Trip'],
    ];

    public $reBody2 = [
        'en'  => "booking your trip with Explore Trip",
        'en2' => "Thank you for choosing Explore Trip",
        'en3' => "Thank you for booking your trip with ExploreTrip",
    ];
    public $lang = "en";
    public $total = [];

    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $its = $this->parseEmail();
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];

        if (!empty(array_filter($this->total))) {
            $result['TotalCharge']['Amount'] = $this->total['TotalCharge'];
            $result['TotalCharge']['Currency'] = $this->total['Currency'];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $lang => $reSubject) {
            if (strpos($headers["subject"], $reSubject[0]) !== false && strpos($headers["subject"], $reSubject[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    public function amount($s)
    {
        $amount = (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));

        if ($amount == 0) {
            $amount = null;
        }

        return $amount;
    }

    private function parseEmail(): array
    {
        $its = [];
        //RecordLocator
        $rlDefault = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'PNR #')]/following::text()[normalize-space()][1])[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        //TripNumber
        $TripNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Ref #')]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        //Passengers
        $Passengers = $this->http->FindNodes("//text()[normalize-space()='Passenger Information']/ancestor::table[1]/following-sibling::table//text()[starts-with(normalize-space(), 'Name')][1]/following::text()[normalize-space()][1]");

        //AccountNumbers
        //TicketNumbers
        $TicketNumbersAll = array_filter($this->http->FindNodes("//text()[normalize-space()='Passenger Information']/ancestor::table[1]/following-sibling::table//text()[starts-with(normalize-space(), 'E-ticket #')][1]/following::text()[normalize-space()][1]", null, "#[\d\-,]{5,}#"));
        $TicketNumbers = [];

        foreach ($TicketNumbersAll as $ticket) {
            $TicketNumbers = array_merge($TicketNumbers, explode(",", $ticket));
        }
        $TicketNumbers = array_unique(array_filter($TicketNumbers));
        //Cancelled
        //TotalCharge
        $TotalCharge = $this->amount($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Amount')]/following::text()[normalize-space()][1]", null, true, "#^\s*([\d,. ]+)\s*$#"));

        //BaseFare
        $BaseFare = $this->amount($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Base Fare')]/following::text()[normalize-space()][1]", null, true, "#^\s*([\d,. ]+)\s*$#"));

        //Currency
        $Currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Fare Details')]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z]{3})\s*$#");

        //Tax
        $Tax = $this->amount($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Taxes')]/following::text()[normalize-space()][1]", null, true, "#^\s*([\d,. ]+)\s*$#"));

        //Fees
        //SpentAwards
        //EarnedAwards
        //Status
        //ReservationDate
        //NoItineraries
        //TripCategory
        //TripSegments

        $xpath = "//text()[starts-with(normalize-space(), 'From')]/ancestor::table[contains(.,'Arrival')][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($nodes as $root) {
            $seg = [];
            $RecordLocator = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'PNR #')]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#");

            if (empty($RecordLocator)) {
                $RecordLocator = $rlDefault;
            }
            //FlightNumber
            //AirlineName
            //Operator
            $node = $this->http->FindSingleNode("./preceding::table[normalize-space()][1]//td[starts-with(normalize-space(), 'Flights')][1]", $root);

            if (preg_match("#Flights\s*:\s*(.+),\s*([A-Z\d]{2})\s*-\s*(\d{1,5})#", $node, $m)) {
                $seg['FlightNumber'] = $m[3];
                $seg['AirlineName'] = $m[2];
                $seg['Operator'] = trim($m[1]);
            }
            //DepName
            $seg['DepName'] = $this->http->FindSingleNode("(.//td[starts-with(normalize-space(), 'From') and not(.//td)]/following-sibling::td[normalize-space()][1])[1]", $root);

            //DepCode
            if (!empty($seg['DepName'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            //DepartureTerminal
            //DepDate
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("(.//td[starts-with(normalize-space(), 'Departure') and not(.//td)]/following-sibling::td[normalize-space()][1])[1]", $root));

            //ArrName
            $seg['ArrName'] = $this->http->FindSingleNode("(.//td[starts-with(normalize-space(), 'To') and not(.//td)]/following-sibling::td[normalize-space()][1])[last()]", $root);

            //ArrCode
            if (!empty($seg['ArrName'])) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            //ArrivalTerminal
            //ArrDate
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("(.//td[starts-with(normalize-space(), 'Arrival') and not(.//td)]/following-sibling::td[normalize-space()][1])[last()]", $root));

            if (!$seg['ArrDate']) {
                $seg['ArrDate'] = strtotime(preg_replace('/( (?:AM|PM))/', '', $this->http->FindSingleNode("(.//td[starts-with(normalize-space(), 'Arrival') and not(.//td)]/following-sibling::td[normalize-space()][1])[last()]", $root)));
            }

            //Aircraft
            //TraveledMiles
            //Cabin
            //BookingClass
            //PendingUpgradeTo
            //Seats
            //Duration
            $seg['Duration'] = $this->http->FindSingleNode("./preceding::table[normalize-space()][1]//td[starts-with(normalize-space(), 'Duration')][1]", $root, null, "#Duration\s*:\s*(.+)#");

            //Meal
            //Smoking
            //Stops
            $seg['Stops'] = $this->http->FindSingleNode("./preceding::table[normalize-space()][1]//td[starts-with(normalize-space(), 'Stops')][1]", $root, null, "#Stops\s*:\s*(\d+)#");

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return [];
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    $its[$key]['TripSegments'][] = $seg;
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (!empty($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (!empty($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }

                if (!empty($Passengers)) {
                    $it['Passengers'] = $Passengers;
                }

                if (!empty($TicketNumbers)) {
                    $it['TicketNumbers'] = $TicketNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        if (count($its) == 1) {
            if (!empty($TotalCharge)) {
                $its[0]['TotalCharge'] = $TotalCharge;
            }

            if (!empty($BaseFare)) {
                $its[0]['BaseFare'] = $BaseFare;
            }

            if (!empty($Currency)) {
                $its[0]['Currency'] = $Currency;
            }

            if (!empty($Tax)) {
                $its[0]['Tax'] = $Tax;
            }
        } elseif (count($its) > 1) {
            if (!empty($TotalCharge)) {
                $this->total['TotalCharge'] = $TotalCharge;
                $this->total['Currency'] = $Currency;
            }
        }

        return $its;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
