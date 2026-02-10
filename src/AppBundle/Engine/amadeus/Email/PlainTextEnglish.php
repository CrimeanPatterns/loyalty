<?php

namespace AwardWallet\Engine\amadeus\Email;

class PlainTextEnglish extends \TAccountCheckerExtended
{
    public $mailFiles = "amadeus/it-3682703.eml";
    public $reBody = "checkmytrip.com";
    public $reBody2 = "GENERAL INFORMATION";
    public $reFrom = "itinerary@amadeus.com";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n(FLIGHT\s+\w{2}\s+\d+|RAIL)#");
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#FLIGHT\s+\w{2}\s+\d+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#AIRLINE\s+LOCATOR\s*:\s*\w{2}\s*/\s*([\w]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return trim(re('#TICKET:.*?BY\s+(.*)\s+MR#i', $this->text()));
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#FREQUENT\s+TRAVELER:\s+(.*?)\s+BY#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(CONFIRMED)\s+RESERVATION#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})\s+FLIGHT\s+(\d+)\s+-#', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            } elseif (preg_match('#FLIGHT\s+(\w{2})\s+(\d+)\s+-#', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return trim(nice(re("#OUTPUT:\s*(.*?)\s*\d+\s*\w{3}\s*\d+:\d+#s")), '-');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $year = re("#FLIGHT\s+[^\n]*(\d{4})\n#");

                            return strtotime(en(re("#OUTPUT:\s*.*?(\d+\s*\w{3})\s*(\d+:\d+)#s") . ' ' . $year . ', ' . re(2)));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return trim(nice(re("#ARRIVAL:\s*(.*?)\s*\d+\s*\w{3}\s*\d+:\d+#s")), '-');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $year = re("#FLIGHT\s+[^\n]*(\d{4})\n#");

                            return strtotime(en(re("#ARRIVAL:\s*.*?\s+(\d+\s*\w{3})\s*(\d+:\d+)#s") . ' ' . $year . ', ' . re(2)));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#EQUIPMENT:\s+([^\n]+)#"));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#,\s*(\w+)\s*\((\w)\)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#,\s*\w+\s*\((\w)\)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#SEAT:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#TIME:\s*(\d+:\d+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#FOOD:\s*([^\n]+)#");
                        },
                    ],
                ],

                "#RAIL#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(CONFIRMED)#");
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#TRAIN\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\n\s*(.*?)\s*-\s*.*?\s*\n\s*TRAIN#"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = strtotime(re("#RAIL\s+\w+\s+(\d+\s+\w+\s+\d{4})#"));

                            return strtotime(re("#DEP\s+(\d{1,2})(\d{2})\s+#") . ':' . re(2), $date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\n\s*.*?\s*-\s*(.*?)\s*\n\s*TRAIN#"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = strtotime(re("#RAIL\s+\w+\s+(\d+\s+\w+\s+\d{4})#"));

                            return orval(
                                strtotime(re("#ARR\s+(\d{1,2})(\d{2})\s+#") . ':' . re(2), $date),
                                strtotime(re("#DEP\s+(\d{1,2})(\d{2})\s+#") . ':' . re(2), $date)
                            );
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
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
        return true;
    }
}
