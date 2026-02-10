<?php

namespace AwardWallet\Engine\chase\Email;

class It2133224 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?(?:Travel\s+Rewards|travelemail)#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#travelemail#i";
    public $reProvider = "#travelemail#i";
    public $caseReference = "7518";
    public $isAggregator = "0";
    public $upDate = "23.12.2014, 15:15";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "chase/it-2133224.eml, chase/it-2297834.eml";
    public $pdfRequired = "0";

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
                        return re("#\n\s*Trip Locator\s*:\s*([A-Z\d-]+)#x");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return filter(explode("\n", re("#\n\s*Trip\s+Locator[^\n]+\s+([A-Z. \s]*?)\n\s*\w+\s+\d+\s+\w+\s+\d{4}#ims")));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//*[contains(text(), 'Total Charges:')]/ancestor::tr[1]/td[last()]"));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return cell("Payment to Rewards Program", +1, 0);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return reset(explode("\n", cell("Status:", +1, 0)));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Depart:')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\s+\-\s+Flight\s+([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return cell("Depart:", +1, 0);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate();

                            $dep = $date . ',' . node(".//*[contains(text(), 'Depart:')]/ancestor::tr[1]/following-sibling::tr[contains(., 'AM') or contains(., 'PM')][1]/td[2]");
                            $arr = $date . ',' . node(".//*[contains(text(), 'Arrive:')]/ancestor::tr[1]/following-sibling::tr[contains(., 'AM') or contains(., 'PM')][1]/td[2]");

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return cell("Arrive:", +1, 0);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return cell("Aircraft:", +1, 0);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'BookingClass' => re("#^([A-Z])\s*\-\s*(.+)#", cell("Class", +1, 0)),
                                'Cabin'        => re(2),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return cell("Seat:", +1, 0);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return cell("Travel Time:", +1, 0);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return cell("Meal Service:", +1, 0);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return cell("Stopovers:", +1, 0);
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return cell("Airline Ref:", +1, 0);
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
