<?php

namespace AwardWallet\Engine\british\Email;

class It2841261 extends \TAccountCheckerExtended
{
    public $mailFiles = "british/it-2841261.eml";

    public $reBody = "British Airways";
    public $reBody2 = "This is NOT a boarding pass";
    public $reBody3 = "Your itinerary";
    public $reBody4 = "Check-in";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Booking reference: (\w+)');
                    },
                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return array_unique(nodes("//*[contains(text(),'Passengers')]/ancestor::table[1]/tbody/tr/td[1]"));
                    },
                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Status:\s+(\w+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(),'Flight')]/ancestor::div[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#Flight\s+\d+\s*:\s*(\S+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                "DepName"=> re("#Departing\s+(.*?)\s+\w+\s+(\d+\s+\w+\s+\d+)[ -]+(\d+:\d+)#msi"),
                                "DepDate"=> strtotime(re(2) . ' ' . re(3)),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return [
                                "ArrName"=> re("#Arriving\s+(.*?)\s+\w+\s+(\d+\s+\w+\s+\d+)[ -]+(\d+:\d+)#msi"),
                                "ArrDate"=> strtotime(re(2) . ' ' . re(3)),
                            ];
                        },
                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return implode(', ', nodes(".//*[contains(text(),'Seats')]/ancestor::table[1]/tbody/tr/td[3]"));
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, $this->reBody) !== false && stripos($body, $this->reBody2) !== false && stripos($body, $this->reBody3) !== false && stripos($body, $this->reBody4) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
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
