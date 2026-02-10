<?php

namespace AwardWallet\Engine\expedia\Email;

class It2145809 extends \TAccountCheckerExtended
{
    public $reFrom = "@expediamail.com";
    public $reSubject = [
        "en"=> "Expedia travel confirmation",
    ];
    public $reBody = "Expedia";
    public $reBody2 = [
        "en"=> "Travel Confirmation",
    ];

    public $mailFiles = "expedia/it-2144727.eml, expedia/it-2144885.eml, expedia/it-2144887.eml, expedia/it-2145808.eml, expedia/it-2145809.eml, expedia/it-2146394.eml, expedia/it-2146422.eml, expedia/it-2146423.eml, expedia/it-2146587.eml, expedia/it-2146601.eml, expedia/it-2146688.eml, expedia/it-2146693.eml, expedia/it-2146707.eml, expedia/it-2146709.eml, expedia/it-2189503.eml, expedia/it-2189689.eml, expedia/it-25.eml, expedia/it-3.eml, expedia/it-5539520.eml";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("plain");

                    return splitter("#((?:Car|Flight\s*):)#");
                },

                "#^Flight#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Itinerary number\s*:|Itin\s*\#)\s*([A-Z\d-]+)#", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return explode(", ", clear("#Total.+#", re("#Traveler names?\s*:\s*([^\n=]+)#ix")));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Airfare total\s*:\s*([^\n]+)#msi"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Total ticket cost\s*:\s*([^\n]+)#ix"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Taxes & Fees\s*:\s*([^\n]+)#ix"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#item\(s\) you just ([^\n.;,]+)#ix", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*([^\n]*?\(.*?\)\s+to\s+[^\n]*?\(.*?\))#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => trim(re("#\-\s*\d+:\d+(?:\s*[APM]{2})?(?:\s*\+\d+\s+da\w+)?\s*(.*?)\s+(\d+)#"), '| '),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            re("#^([^\n]*?)\s*\((.*?)\)\s+to\s+([^\n]*?)\s*\((.*?)\)#");

                            return [
                                'DepName' => trim(preg_match("#^[A-Z]{3}$#", re(2)) ? re(1) : re(1) . ', ' . re(2), '| '),
                                'DepCode' => preg_match("#^[A-Z]{3}$#", re(2)) ? re(2) : TRIP_CODE_UNKNOWN,

                                'ArrName' => preg_match("#^[A-Z]{3}$#", re(4)) ? re(3) : re(3) . ', ' . re(4),
                                'ArrCode' => preg_match("#^[A-Z]{3}$#", re(4)) ? re(4) : TRIP_CODE_UNKNOWN,
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            // echo text($text)."\n";
                            $subj = re("#^[^\n]*?\(.*?\)\s+to\s+.*?-\s+\d+:\d+\s*[AP]m#msi");
                            $date = nice(re("#\d{2}/\d{2}/\d{2}#", $subj));
                            re("#/\d{2}\s*(\d+:\d+(?:\s+[AP]M)?)\s+-\s+(\d+:\d+(?:\s+[AP]M)?)#i", $subj);

                            $dep = totime($date . ',' . nice(re(1)));
                            $arr = totime($date . ',' . nice(re(2)));

                            if (re(4)) {
                                $arr = strtotime('+' . re(4) . ' day', $arr);
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },
                    ],
                ],

                "#^Car#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Itinerary number\s*:|Itin\s*\#)\s*([A-Z\d-]+)#", $this->text());
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'PickupDatetime' => totime(nice(re("#Pick[\s-]+up\s*:[^\d\w]+(\w+\s+\d+/\d+/\d+\s+\d+:\d+(?:\s+[AP]M)?)#mis"))),
                            'PickupLocation' => trim(nice(orval(re(2), re("#Car\s*:\s*[^,]+,\s*(.*?)Driver#ms"))), '| '),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'DropoffDatetime' => totime(preg_replace("#\s+#", " ", re("#Drop[\s-]+off\s*:[^\d\w]+(\w+\s+\d+/\d+/\d+\s*\d+:\d+\s*[APM]*)\s+([^\n]+)#i"))),
                            'DropoffLocation' => trim(preg_match("#^Special#i", re(2)) ? $it['PickupLocation'] : re(2), '| '),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Car:\s*([^,]+),#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n[^\d\w]*([^\n]+)[^\d\w]+Pick[\s-]+up#i"), '| ');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Driver\s*:\s*([^\n]*?)\s*Base\s+price#"),
                            re("#Driver\s*:\s*([^\n]+)#")
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Car total\s*:\s*([^\n]+)#"));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes & Fees\s*:\s*([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#item\(s\) you just ([^\n.;,]+)#ix", $this->text());
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
