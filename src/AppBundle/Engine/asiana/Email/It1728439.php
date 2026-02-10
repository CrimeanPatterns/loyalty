<?php

namespace AwardWallet\Engine\asiana\Email;

class It1728439 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?Aeroplan|Aeroplan Contact Centr#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#Aeroplan#i', 'us', ''],
    ];
    public $reProvider = [
        ['#Aeroplan#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "22.01.2015, 10:54";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "asiana/it-1728439.eml";
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
                        return re("#\n\s*Booking Reference\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[1]");
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            currency(cell("Total Airport Taxes")),
                            currency(cell("Total Airport Taxes", +2))
                        );
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Taxes, fees & surcharges\s*:\s*([^\n]+)#"));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reward Value\s*:\s*([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Ticket Issue Date\s*:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//text()[contains(., "Departs")]/ancestor::div[3][contains(., "Arrives")]');
                    //return splitter("#\n\s*(DEPARTURE|RETURN|Time to connect)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#([^\n]+)\s+Departs\s*:#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $anchorDate = re('#\w+\s+(\w+\s+\d+,\s+\d{4})#i', node('./ancestor::div[1]/preceding-sibling::div[1]'));
                            $year = re('#\d{4}#i', $anchorDate);
                            $anchorDate = strtotime($anchorDate);

                            if (!$anchorDate or !$year) {
                                return null;
                            }
                            $result = [];

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $d = node('.//div[contains(., "' . $value . '") and not(.//div)]/following-sibling::div[1]');
                                $t = node('.//div[contains(., "' . $value . '") and not(.//div)]/following-sibling::div[2]');
                                $s = $d . ', ' . $year . ', ' . $t . "\n";
                                $result[$key . 'Date'] = strtotime($s);
                            }
                            correctDates($result['DepDate'], $result['ArrDate'], $anchorDate);

                            return $result;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(Business|Economy)\s+#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Duration\s*:\s*([^\n]+)#");
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
