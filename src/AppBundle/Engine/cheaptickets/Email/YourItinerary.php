<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class YourItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#This\s+message\s+has\s+been\s+sent\s+from\s+CheapTickets\.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#travelercare@cheaptickets\.com#i";
    public $reProvider = "#cheaptickets#i";
    public $xPath = "";
    public $mailFiles = "cheaptickets/it-1881837.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $subj = re('#Airline\s+record\s+locator:\s+.*?Traveler#is');
                    $this->recordLocators = [];

                    if (preg_match_all('#(.*)\s+-\s+([\w\-]+)#i', $subj, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->recordLocators[nice($m[1])] = $m[2];
                        }
                    }
                    $this->travelers = nodes('//td[normalize-space(.) = "Traveler(s)"]/ancestor::tr[1]/following-sibling::tr/td[1]');

                    return xpath('//td[contains(., "Depart:") and not(.//td)]/ancestor::table[2]');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $this->currentAirlineName = null;
                        $this->currentFlightNumber = null;

                        if (preg_match('#(.*)\s+\#(\d+)#i', $this->http->FindSingleNode("(.//text()[contains(., '#')][1])[1]", $node), $m)) {
                            $this->currentAirlineName = nice($m[1]);
                            $this->currentFlightNumber = nice($m[2]);

                            if (isset($this->recordLocators[$this->currentAirlineName])) {
                                return $this->recordLocators[$this->currentAirlineName];
                            }
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return $this->currentFlightNumber;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re('#\w+\s+\d+,\s+\d+#i');
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $res[$key . 'Code'] = re('#\((\w{3})\)#i', cell($value, +2, 0));
                                $res[$key . 'Name'] = cell($value, +2, 0, '//strong');
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . re('#\d+:\d+\s*(?:am|pm)#i', cell($value, +1)));
                            }

                            if (preg_match('#This\s+is\s+an\s+overnight\s+flight#i', $node->nodeValue) and isset($res['ArrDate'])) {
                                $res['ArrDate'] = strtotime('+1 day', $res['ArrDate']);
                            }

                            return $res;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return $this->currentAirlineName;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $regex = '#';
                            $regex .= '(.*)\|';
                            $regex .= '(.*)\|';
                            $regex .= '(.*)\|';
                            $regex .= '(.*)\s+miles';
                            $regex .= '#i';

                            if (preg_match($regex, $this->http->FindSingleNode(".//tr[contains(.,'|') and not(.//tr)][last()]", $node), $m)) {
                                return nice([
                                    'Cabin'         => $m[1],
                                    'Aircraft'      => $m[2],
                                    'Duration'      => $m[3],
                                    'TraveledMiles' => $m[4],
                                ]);
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
