<?php

namespace AwardWallet\Engine\dividendmiles\Email;

class It2165502 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]usairways[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]usairways[.]com#i";
    public $reProvider = "#[@.]usairways[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "dividendmiles/it-2165502.eml, dividendmiles/it-2166868.eml";
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
                        return re_white('Confirmation code  (\w+)');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Your flight is delayed')) {
                            return 'updated';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Flight #')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re_white('(\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $code = node('(.//font) [1]');

                            return nice($code);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('./preceding::font[1]');
                            $date = uberDate($date);

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $dt1 = "$date, $time1";
                            $dt2 = "$date, $time2";
                            correctDates($dt1, $dt2);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $code = node('(.//font) [2]');

                            return nice($code);
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
