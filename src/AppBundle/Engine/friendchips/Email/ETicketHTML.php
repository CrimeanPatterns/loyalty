<?php

namespace AwardWallet\Engine\friendchips\Email;

class ETicketHTML extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-7216437.eml, friendchips/it-7260703.eml, friendchips/it-7284653.eml, friendchips/it-7305677.eml";

    public $reFrom = ["eticketing.thomson.co.uk", "eticketing.firstchoice.co.uk"];
    public $reBody = [
        'en' => ['E-ticket', 'This email contains all your booking details'],
    ];
    public $reSubject = [
        '#E-ticket\s+.+?\s+Your\s+confirmed\s+flight\s+details#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicketHTML' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'eticketing.firstchoice.co.uk') or contains(@href,'eticketing.thomson.co.uk')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])) {
            foreach ($this->reFrom as $re) {
                if (stripos($headers['from'], $re) !== false) {
                    if (isset($this->reSubject)) {
                        foreach ($this->reSubject as $reSubject) {
                            if (preg_match($reSubject, $headers["subject"])) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseFlightSeg($root, $num)
    {
        $seg = [];
        $node = $this->http->FindSingleNode("(.//tr/td[{$num}])[starts-with(normalize-space(.),'Flight code')]", $root, true, "#:\s*([A-Z\d]+)#");

        if (preg_match("#([A-Z]{3})\s*(\d+)#", $node, $m)) {
            $seg['FlightNumber'] = $m[2];

            if ($m[1] === 'TOM') {
                $seg['AirlineName'] = 'BY';
            }
        }

        if (!isset($seg['AirlineName'])) {
            $seg['AirlineName'] = $this->http->FindSingleNode("./preceding::tr[normalize-space(.)][1]/td[contains(.,'Airline')]", $root, true, "#:\s*(.+)#");
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(.//tr/td[{$num}])[starts-with(normalize-space(.),'Date')]", $root, true, "#:\s*(.+)#")));
        $node = $this->http->FindSingleNode("(.//tr/td[{$num}])[starts-with(normalize-space(.),'Departure airport')]", $root, true, "#:\s*(.+)#");

        if (preg_match("#^(.+?)\s*(?:TERM[\.\s]*(.*))?$#", $node, $m)) {
            $seg['DepName'] = $m[1];

            if (isset($m[2]) & !empty($m[2])) {
                $seg['DepartureTerminal'] = $m[2];
            }
        }
        $node = $this->http->FindSingleNode("(.//tr/td[{$num}])[starts-with(normalize-space(.),'Arrival airport')]", $root, true, "#:\s*(.+)#");

        if (preg_match("#^(.+?)\s*(?:TERM[\.\s]*(.*))?$#", $node, $m)) {
            $seg['ArrName'] = $m[1];

            if (isset($m[2]) & !empty($m[2])) {
                $seg['ArrivalTerminal'] = $m[2];
            }
        }
        $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(.//tr/td[{$num}])[starts-with(normalize-space(.),'Depart:')]", $root, true, "#:\s*(\d+[:\.]\d+(\s*[ap]m)?)\s*$#i")), $date);
        $node = $this->http->FindSingleNode("(.//tr/td[{$num}])[starts-with(normalize-space(.),'Arrive:')]", $root, true, "#:\s(.+)#");

        if (preg_match("#^\s*(\d+[:\.]\d+(\s*[ap]m)?)\s*$#i", $node, $m)) {
            $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]), $date);
        } else {
            $seg['ArrDate'] = strtotime($this->normalizeDate($node));
        }

        return $seg;
    }

    private function parseEmail()
    {
        $its = [];
        //Flights
        $xpath = "//text()[starts-with(normalize-space(.),'Flight code')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $it = ['Kind' => 'T', 'TripSegments' => []];

            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Ref')]", null, true, "#:\s*([A-Z\d]+)#");
            $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Passenger:')]/ancestor::table[1]//tr[position()>1]/td[1]");

            foreach ($nodes as $i => $node) {
                for ($i = 1; $i < 3; $i++) {
                    if ($this->http->XPath->query("(.//tr/td[{$i}])[contains(.,'Date')]", $node)->length > 0) {
                        $it['TripSegments'][] = $this->parseFlightSeg($node, $i);
                    }
                }
            }
            $its[] = $it;
        }
        //Hotels
        $xpath = "//text()[starts-with(normalize-space(.),'Accommodation name')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $node) {
            $it = ['Kind' => 'R'];

            $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//td[starts-with(normalize-space(.),'Reference No')]", $node, true, "#:\s*([A-Z\d]+)#");
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Ref')]", null, true, "#:\s*([A-Z\d]+)#");
            $it['GuestNames'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Passenger:')]/ancestor::table[1]//tr[position()>1]/td[1]");
            $it['Guests'] = $this->http->XPath->query("//text()[starts-with(normalize-space(.),'Passenger:')]/ancestor::table[1]//tr[position()>1]/td[1]")->length;
            $it['HotelName'] = $this->http->FindSingleNode(".//td[starts-with(normalize-space(.),'Accommodation name')]", $node, true, "#:\s*(.+)#");
            $it['Address'] = $it['HotelName'];
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[starts-with(normalize-space(.),'Check in')]", $node, true, "#:\s*(.+)#")));
            $it['CheckOutDate'] = strtotime('+' . $this->http->FindSingleNode(".//td[starts-with(normalize-space(.),'Duration')  and count(descendant::td)=0]", $node, true, "#:\s*(\d+)#") . ' days', $it['CheckInDate']);
            $it['RoomType'] = $this->http->FindSingleNode(".//td[starts-with(normalize-space(.),'Room type')]", $node, true, "#:\s*(.+)#");

            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)[:\.](\d+(?:\s*[ap]m)?)$#',
            '#^\s*(\d+\s+\w+\s+\d+)\s*$#',
            '#^\s*(\d+\s+\w+\s+\d+)\s*(\d+)[:\.](\d+(?:\s*[ap]m)?)\s*$#',
            '#^(\d+)[:\.](\d+(?:\s*[ap]m)?)\s+\(\s*(\d+\s+\w+\s+\d+)\s*\)\s*$#',
        ];
        $out = [
            '$1:$2',
            '$1',
            '$1 $2:$3',
            '$3 $1:$2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
