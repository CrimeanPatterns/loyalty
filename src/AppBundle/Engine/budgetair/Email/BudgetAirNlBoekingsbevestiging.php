<?php

namespace AwardWallet\Engine\budgetair\Email;

class BudgetAirNlBoekingsbevestiging extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#budgetair\.nl\s+.+?Totale\s+kosten#is', 'blank', '2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['BudgetAir.nl - boekingsbevestiging', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]budgetair\.nl#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]budgetair#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "04.06.2015, 14:21";
    public $crDate = "02.06.2015, 12:04";
    public $xPath = "";
    public $mailFiles = "budgetair/it-1.eml";
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
                        return re("#Boekingscode\:\s+(\w+)\s+#uism");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = [];
                        $psngrNodes = xpath("//table[./thead/tr[1]/td/*[contains(text(), 'Passagier')]]/tbody/tr[./th]");

                        for ($i = 0; $i < $psngrNodes->length; $i++) {
                            $passengers[] = re("/\.\s([^.]+)$/iux", node("./th/text()", $psngrNodes->item($i)));
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//*[contains(text(), 'Totale kosten')][1]/*/text()"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//table[./*/tr/*[contains(text(), 'Vluchtnr.')]]/tbody/tr[position() mod 2 = 1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("/(\d+)/ui", node("./*[5]/*/text()"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("./*[3]/*/text()") . " " . node("./*[3]/*/*[2]/text()");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $sDate = re("/.+?\,\s*(.+)/ui", node("./*[2]/*/text()"));
                            $sTime = node("./*[4]/*/text()");
                            $depDate = $sDate . " " . $sTime;

                            if ($depDT = \DateTime::createFromFormat("d-F-Y H:i", en($depDate, "nl"))) {
                                return $depDT->getTimestamp();
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[1]/*[3]/*/text()") . " " . node("./following-sibling::tr[1]/*[3]/*/*[2]/text()");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            if (!($sDate = re("/.+?\,\s*(.+)/ui", node("./following-sibling::tr[1]/*[2]/*/text()")))) {
                                $sDate = re("/.+?\,\s*(.+)/ui", node("./*[2]/*/text()"));
                            }
                            $sTime = node("./following-sibling::tr[1]/*[4]/*/text()");
                            $arrDate = $sDate . " " . $sTime;

                            if ($arrDT = \DateTime::createFromFormat("d-F-Y H:i", en($arrDate, "nl"))) {
                                return $arrDT->getTimestamp();
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("/(\w+?)\d+/ui", node("./*[5]/*/text()"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node("./*[7]/*/text()");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[1]/*[7]/*/text()");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[1]/*[5]/*/text()");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return intval(node("./*[6]/*/text()"));
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
