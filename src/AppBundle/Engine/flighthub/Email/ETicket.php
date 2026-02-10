<?php

namespace AwardWallet\Engine\flighthub\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "flighthub/it-5168278.eml, flighthub/it-5174980.eml, flighthub/it-5358066.eml";

    public $reBody = 'FlightHub';
    public $reBody2 = ["Booking Reference", "Thank you for booking with FlightHub"];
    public $reSubject = ["Your Electronic Ticket"];
    public $reFrom = "flighthub.com";

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
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        $this->parseEmail($itineraries);

        $result = [
            'emailType'  => 'FlightEn',
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => total($this->http->FindSingleNode("//*[contains(text(), 'Grand Total')]/ancestor-or-self::td/following-sibling::td[1]"), 'Amount'),
            ],
        ];

        return $result;
    }

    private function parseEmail(&$itineraries)
    {
        $xpath = "//text()[contains(.,'YOUR ITINERARY')]/ancestor::tr[1]/following::table[1]//text()[contains(.,'Flight ')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->info("segments root not found: $xpath");

            return null;
        }
        $rls = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./td[position()=4]//text()[normalize-space(.) and not(contains(.,'Confirmation'))]", $root);

            if (preg_match("#^\w{6}$#", $rl)) {
                $rls[$rl][] = $root;
            } elseif (strpos($rl, 'Call Us') !== false) {
                $rls[CONFNO_UNKNOWN][] = $root;
            }
        }

        foreach ($rls as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";

            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(.,'Booking Reference')]/following::text()[normalize-space(.)][1]");
            $it['Passengers'] = $this->http->FindNodes("//tr[count(descendant::tr)=0 and th[contains(.,'Name')] and th[contains(.,'E-Ticket')]]/following-sibling::tr/td[1]");

            if (count($rls) == 1) {
                $it['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(), 'Grand Total')]/ancestor-or-self::td/following-sibling::td[1]", null, true, "#[\d\.\,]+#");
                $it['BaseFare'] = $this->http->FindSingleNode("//*[contains(text(), 'Airfare')]/ancestor-or-self::td/following-sibling::td[1]", null, true, "#[\d\.\,]+#");
                $it['Tax'] = $this->http->FindSingleNode("//*[contains(text(), 'Taxes & Fees')]/ancestor-or-self::td/following-sibling::td[1]", null, true, "#[\d\.\,]+#");
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Grand Total')]/ancestor-or-self::td/following-sibling::td[1]", null, true, "#[A-Z]{3}#");
            }

            foreach ($roots as $root) {
                $airline = $this->http->FindSingleNode("./td[position()=1]/text()[normalize-space(.)][1]", $root);
                $it['TicketNumbers'] = $this->http->FindNodes("//tr[count(descendant::tr)=0 and th[contains(.,'Name')] and th[contains(.,'E-Ticket')]]/following-sibling::tr/td[2]", null, "#{$airline}\s*([\d\-]{5,})(?:[A-Za-z ]|$)#");

                $itsegment = [];
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[position()=1]/text()[normalize-space(.)][2]", $root, true, "#^.*?\s+(\d+)#");
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[position()=1]/text()[normalize-space(.)][1]", $root);

                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[position()=2]/text()[normalize-space(.)][1]", $root));
                $node = $this->http->FindSingleNode("./td[position()=2]/text()[normalize-space(.)][2]", $root);

                if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $itsegment['DepCode'] = $m[2];
                    $itsegment['DepName'] = $m[1];
                }
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[position()=2]/span[1]", $root);

                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[position()=3]/text()[normalize-space(.)][1]", $root));
                $node = $this->http->FindSingleNode("./td[position()=3]/text()[normalize-space(.)][2]", $root);

                if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $itsegment['ArrCode'] = $m[2];
                    $itsegment['ArrName'] = $m[1];
                }
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[position()=3]/span[1]", $root);

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
    }
}
