<?php

namespace AwardWallet\Engine\capitalcards\Email;

class It2355238 extends \TAccountCheckerExtended
{
    public $mailFiles = "capitalcards/it-2355238.eml, capitalone/it-2355238.eml";

    private $detects = [
        'There is no need for you to call Capital One at this time',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#use reference code ([A-Z\d\-]+)#ix"),
                            clear("#\s#", re("#\n\s*Your Trip ID is\s*:\s*([A-Z\d- ]+)#", $this->text()))
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes("//*[contains(text(), 'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[1]"),
                            nodes("//*[contains(text(), 'Passengers')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]")
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total:", +1, 0));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Adult:", +1, 0));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Taxes + Airline & Agency Fees:", +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#airline has (\w+) the flight#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Depart:')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Flight\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+\(([A-Z]{3})\)\s*[\n]*#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                uberDate(node("preceding::tr[contains(., 'check-in') or contains(., ' to ')][1]")),
                                uberDate(node("preceding::*[contains(., ' to ')][1]"))
                            );

                            $dep = $date . ',' . re("#Depart\s*:\s*(\d+:\d+\s*[amp]*)#i");
                            $arr = $date . ',' . re("#Arrive\s*:\s*(\d+:\d+\s*[amp]*)#i");

                            if (re("#next\s+day#i")) {
                                $dep = totime($dep);
                                $arr = strtotime('+1 day', totime($arr));
                            } else {
                                correctDates($dep, $arr);
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\s+\(([A-Z]{3})\)\s*[\n]*#", 2);
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re("#\n\s*(.*?),\s*Flight\s+\d+#"),
                                node('tbody/tr[1]/td[3]')
                            );
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight\s+\d+\s+\(on\s+([^\)]+)\)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(.*?)\s+Class#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#Seats\s*:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Total Travel Time\s*:\s*([^\n]+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non\-stop#") ? 0 : null;
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return node("preceding::tr[contains(., 'check-in') or contains(., ' to ')][1]", null, true, "#check\-in\s+code:\s*([A-Z\d\-]+)#i");
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'capitalone') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'capitalone') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }
}
