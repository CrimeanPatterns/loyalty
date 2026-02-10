<?php

namespace AwardWallet\Engine\budgetair\Email;

class BudgetAirBookingVerification extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Your BudgetAir Trip Id', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['BudgetAir Booking Verification', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]budgetair\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]budgetair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "09.06.2015, 10:32";
    public $crDate = "02.06.2015, 12:07";
    public $xPath = "";
    public $mailFiles = "budgetair/it-1882093.eml";
    public $re_catcher = "#.*?#";
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
                        return node("//table/*[./tr[1]/td[3]/*[contains(text(),'Reservation Number')]]/tr[3]/td[3]/text()");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = [];

                        if (preg_match_all("/\b\d+\.\s+(\w+)\,\s+(\w+)\s+(MR|MRS)\s/xuism", re("/\bTravelers\s+Purchased\s+By\s+(\d+\..+?)Your\s+Itinerary/xuism"), $m, PREG_SET_ORDER)) {
                            for ($i = 0; $i < count($m); $i++) {
                                $passengers[] = beautifulName($m[$i][2] . " " . $m[$i][1]);
                            }
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//tr[./td[1]/*[text()='Total']]/td[2]/*/text()"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//tr[./td[2][normalize-space(text())='Your Itinerary']]/following-sibling::tr[1]/.//tr[./td[2][normalize-space(text())='Flight']]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("/\s*(\d+)/ui", node("./td[3]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("/\((\w+)\)/uism", node("./following-sibling::tr[./td[normalize-space(text())='Depart']][1]/td[2]"));
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("/>?\b((\w+|\s)+)\,/xuis", node("./following-sibling::tr[./td[normalize-space(text())='Depart']][1]/td[2]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = node("./following-sibling::tr[./td[normalize-space(text())='Depart']][1]/following-sibling::tr[1]/td[2]/text()") . "m";

                            if ($dt = \DateTime::createFromFormat("d-M-y (D) h:iA", $dateStr)) {
                                return $dt->getTimestamp();
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("/\((\w+)\)/ui", node("./following-sibling::tr[./td[normalize-space(text())='Arrive']][1]/td[2]"));
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("/>?\b((\w+|\s)+)\,/xuis", node("./following-sibling::tr[./td[normalize-space(text())='Arrive']][1]/td[2]"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = node("./following-sibling::tr[./td[normalize-space(text())='Arrive']][1]/following-sibling::tr[1]/td[2]/text()") . "m";

                            if ($dt = \DateTime::createFromFormat("d-M-y (D) h:iA", $dateStr)) {
                                return $dt->getTimestamp();
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("/\b(\w+?)\s*\d+/ui", node("./td[3]"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[./td[contains(text(),'Aircraft')]][1]/td[2]/text()");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("/\|\s(\w+)/ui", node("./following-sibling::tr[./td[normalize-space(text())='Flight Time']][1]/td[2]/text()"));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("/(.+)\s\|/ui", node("./following-sibling::tr[./td[normalize-space(text())='Flight Time']][1]/td[2]/text()"));
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = re("/(\w+|\d+)/uism", node("./following-sibling::tr[./td[normalize-space(text())='Stops']][1]/td[2]/text()"));

                            return (intval($stops)) ? intval($stops) : 0;
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
