<?php

namespace AwardWallet\Engine\flighthub\Email;

class ETicketInvoice extends \TAccountChecker
{
    public $mailFiles = "flighthub/it-11491950.eml";

    public $reBody = 'FlightHub';
    public $reBody2 = ["Thank you for booking your trip with us"];
    public $reSubject = [
        "Your E-Ticket Invoice",
    ];
    public $reFrom = "flighthub.com";

    public function parseEmail()
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()),'Reference #')]/following::text()[normalize-space()][1]", null, true, "#^\s*([\d\-]+)\s*$#");

        // Passengers
        $it['Passengers'] = [];
        $Passengers = array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(),'Traveller(s)')]", null, "#:\s*(.+)#"));

        foreach ($Passengers as $pass) {
            $pass = explode(",", $pass);
            $it['Passengers'] = array_merge($it['Passengers'], $pass);
        }

        if (!empty($it['Passengers'])) {
            $it['Passengers'] = array_unique(array_filter(array_map('trim', $it['Passengers'])));
        }

        // AccountNumbers
        // Cancelled

        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total')]/ancestor::td[1]/following-sibling::td[normalize-space()][1]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total')]", null, true, "#\(([A-Z]{3})\)#");

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//*[starts-with(normalize-space(text()),'Flight')]/ancestor-or-self::tr[1]/following-sibling::tr//table";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);

            return null;
        }

        foreach ($nodes as $root) {
            $itsegment = [];

            // FlightNumber
            // AirlineName
            $node = $this->http->FindSingleNode("./tr[2] | ./tbody/tr[2]", $root);

            if (preg_match("#^\s*(.+?)\s+(\d{1,5})\s*$#", $node, $m)) {
                $itsegment['FlightNumber'] = $m[2];
                $itsegment['AirlineName'] = $m[1];
            }

            // DepName
            // DepCode
            // ArrCode
            // ArrName
            $node = $this->http->FindSingleNode("./tr[1]/td[1] | ./tbody/tr[1]/td[1]", $root);

            if (preg_match("#(.+?)\(([A-Z]{3})\) to (.+?)\(([A-Z]{3})\)#", $node, $m)) {
                $itsegment['DepName'] = trim($m[1]);
                $itsegment['DepCode'] = $m[2];
                $itsegment['ArrName'] = trim($m[3]);
                $itsegment['ArrCode'] = $m[4];
            }

            // DepDate
            // ArrDate
            $node = $this->http->FindSingleNode("./tr[1]/td[2] | ./tbody/tr[1]/td[2]", $root);

            if (preg_match("#(.+) - (.+)#", $node, $m)) {
                $itsegment['DepDate'] = strtotime($m[1]);
                $itsegment['ArrDate'] = strtotime($m[2]);
            }

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

        return $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) !== false) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        $its[] = $this->parseEmail();

        $result = [
            'emailType'  => 'ETicketInvoice',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        return $result;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
