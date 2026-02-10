<?php

namespace AwardWallet\Engine\expedia\Email;

class It1568015 extends \TAccountCheckerExtended
{
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $rePlain = "#Here\s+are\s+your\s+itinerary\s+and\s+confirmation\s+numbers\s+for\s+your\s+trip.*?expedia#is";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "expedia/it-1568011.eml, expedia/it-1568015.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!preg_match($this->rePlain, $text)) {
                        // Ignore emails of other types
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Traveler')]/ancestor-or-self::tr[1]/following-sibling::tr[contains(., 'Update frequent')]/descendant::text()[normalize-space(.)][1]");
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#confirmation code:\s*([\d\w\-]+)#"),
                            re("#itinerary number:\s*([\w\d\-]+)#")
                        );
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#confirmed as of\s*(\d+/\d+/\d+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*\w{3}\s+\d+\-\w{3}\-\d+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Flight:\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            re("#\n\s*([^\n\(]+)\s+\(([^\)]+)\)\s+to\s+([^\n\(]+)\s+\(([^\)]+)\)#");

                            $depCode = re(2);
                            $arrCode = re(4);

                            $depName = re(1);
                            $arrName = re(3);

                            return [
                                'DepName' => $depName . (re("#^[A-Z]{3}$#", $depCode) ? '' : ", $depCode"),
                                'ArrName' => $arrName . (re("#^[A-Z]{3}$#", $arrCode) ? '' : ", $arrCode"),
                                'DepCode' => re("#^[A-Z]{3}$#", $depCode) ? $depCode : TRIP_CODE_UNKNOWN,
                                'ArrCode' => re("#^[A-Z]{3}$#", $arrCode) ? $arrCode : TRIP_CODE_UNKNOWN,
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate();

                            $depDate = strtotime($date . ', ' . uberTime(1));
                            $arrDate = strtotime($date . ', ' . uberTime(2));

                            return [
                                'ArrDate' => $arrDate,
                                'DepDate' => $depDate,
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'TraveledMiles' => re("#(\d+)\s*mi\s+([^\n]+)\s+Depart\s+#"),
                                'AirlineName'   => re(2),
                            ];
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'    => re("#\n\s*([^\n\t]*?)\s+Class\s+([^\n\t]+)#"),
                                'Aircraft' => trim(re(2)),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration:\s*((?:\d|h|m| |)+)#");
                        },
                    ],
                ],
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
