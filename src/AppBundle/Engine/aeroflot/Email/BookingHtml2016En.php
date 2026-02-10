<?php

namespace AwardWallet\Engine\aeroflot\Email;

class BookingHtml2016En extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-4412779.eml, aeroflot/it-4546585.eml";

    public $reBody2 = "Booking code (PNR)";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking code (PNR)')]", null, true, "#Booking code \(PNR\)\s*-\s*([A-Z\d]{5,6})#");
                $it['Passengers'] = $this->http->FindNodes("//th[text()='Passengers']/ancestor::tr[1]/following-sibling::tr/td[1]/span/b");
                $it['Status'] = re("#Status\s*:\s*(\w+)#", text($this->http->Response['body']));

                $xpath = "//th[contains(., 'Departure')]/ancestor::tr[1]/following-sibling::tr[not(contains(., 'Airport change')) and not(contains(., 'Terminal change')) and not(contains(., 'connection'))]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $itsegment = [];

                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/b[1]", $root, true, "#(\d+)#");

                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/div[4]/span", $root);

                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/div[2]", $root) . ' ' . $this->http->FindSingleNode("./td[2]/div[1]", $root));

                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]/div[4]/span", $root);

                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/div[2]", $root) . ' ' . $this->http->FindSingleNode("./td[3]/div[1]", $root));

                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/b[1]", $root, true, "#(\D+)#");

                    $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[4]/text()[contains(., 'Airplane')]/following::*[1]", $root);

                    $itsegment['Cabin'] = $this->http->FindSingleNode("./td[4]/text()[contains(., 'Booking class')]/following::*[1]", $root, true, "#(.*?)\s*/#");

                    $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[4]/text()[contains(., 'Booking class')]/following::*[1]", $root, true, "#.*?\s*/\s*(.+)#");

                    $itsegment['Duration'] = $this->http->FindSingleNode("./td[4]/text()[contains(., 'Flight')]/following::*[1]", $root);

                    $itsegment['Meal'] = $this->http->FindSingleNode("./td[4]/text()[contains(., 'Meal')]/following::*[1]", $root);

                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
        && stripos($headers['from'], 'callcenter@aeroflot.ru') !== false && (
        stripos($headers['subject'], 'Check-in is open for booking') !== false
        // ✈ Payment of MXXJQG booking on Aeroflot airlines website ✈
        || preg_match('/✈ Payment of [A-Z\d]{5,6} booking on Aeroflot airlines website ✈/', $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Booking code (PNR)') !== false
        && (strpos($parser->getHTMLBody(), 'The information about free baggage allowance published in the itinerary receipt.') !== false
        || strpos($parser->getHTMLBody(), 'Itinerary receipt of the booking is in the attachment.') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aeroflot.ru') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
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
