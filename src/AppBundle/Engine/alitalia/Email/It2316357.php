<?php

namespace AwardWallet\Engine\alitalia\Email;

class It2316357 extends \TAccountCheckerExtended
{
    public $rePlain = '#\n[>\s*]*From\s*:[^\n]*?alitalia#i';
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = '#alitalia#i';
    public $reProvider = '#alitalia#i';
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.12.2014, 20:51";
    public $crDate = "30.12.2014, 20:39";
    public $xPath = "";
    public $mailFiles = "alitalia/it-2316357.eml";
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
                        return re("#\n\s*RESERVATION\s+([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'PASSENGER(S)')]/ancestor::tr[1]/following::tr[1]/td[1]", null, true, "#^([^\\(]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cell("Total price", +1, 0);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cell("Fare", +1, 0);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return cell("Total price", +2, 0);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cell("Taxes and surcharges", +1, 0);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'FROM:')]/ancestor::table[1]/following-sibling::table[contains(., ':') and contains(., '-')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node('.//tr[1]/td[string-length(normalize-space(.))>1][last()]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('.//tr[1]/td[string-length(normalize-space(.))>1][1]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node('.//tr[1]/td[string-length(normalize-space(.))>1][3]')));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('.//tr[1]/td[string-length(normalize-space(.))>1][2]');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node('.//tr[1]/td[string-length(normalize-space(.))>1][4]')));
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
