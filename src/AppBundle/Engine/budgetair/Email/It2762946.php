<?php

namespace AwardWallet\Engine\budgetair\Email;

class It2762946 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#BudgetAir\.co\.uk.+?This\s+is\s+your\s+booking\s+verification#mis', 'blank', '2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['BudgetAir.co.uk - Confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]budgetair\.co\.uk#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]budgetair#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "05.06.2015, 11:43";
    public $crDate = "04.06.2015, 15:31";
    public $xPath = "";
    public $mailFiles = "budgetair/it-2762946.eml";
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
                        return re("#\bReservation\s+Number\s+([A-Z\d]{5,6})\s#uim");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = [];
                        $psngrNodes = xpath("//tr[ ./td[1]/*/*/tr/td[2]/*[contains(text(), 'Traveller(s)')]]/following-sibling::tr[./td[1]/*[starts-with(text(), 'Ms.') or starts-with(text(), 'Mr.')]]");

                        for ($i = 0; $i < $psngrNodes->length; $i++) {
                            $passengers[] = re("/\.\s+([^.]+)$/uxi", node("./td[1]/*/text()", $psngrNodes->item($i)));
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//tr[./td[1]/*[normalize-space(text())='Total']]/td[3]/text()"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return node("//tr[./td[1]/*[normalize-space(text())='Total']]/td[2]/text()");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//tr[./td[1]/table/tbody/tr/td[2]/*[normalize-space(text())='Flight Information']]/following-sibling::tr[2]/td/table[(.) != (../table[last()])]/*");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/Flight\s+number\s+([A-Z\d]{2})\s*(\d+)/', node(".//*[contains(text(), 'Flight number')]"), $matches)) {
                                return [
                                    'AirlineName'  => $matches[1],
                                    'FlightNumber' => $matches[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("./tr[2]/td/table[1]/.//table[1]/.//td/text()");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re("/^\s*\w+?\s+(\d+.+)\s*$/ui", node("./tr[1]/td/*/text()"))
                                        . " " . trim(node("./tr[2]/td/table[1]/.//table[2]/*/tr/td/*[1]/text()"));

                            if ($dt = \DateTime::createFromFormat("d M Y H:i", $dateStr)) {
                                return $dt->getTimestamp();
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("./tr[2]/td/table[2]/*/tr/td/text()");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re("/^\s*\w+?\s+(\d+.+)\s*$/ui", node("./tr[1]/td/*/text()"));
                            $sArrTime = $dateStr . " " . trim(node("./tr[2]/td/table[1]/.//table[2]/*/tr/td/*[3]/text()"));

                            if ($dtArr = \DateTime::createFromFormat("d M Y H:i", $sArrTime)) {
                                $arrTime = $dtArr->getTimestamp();

                                return $arrTime;
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("/Duration\s+(\d+:\d+)/iu", node("./tr[3]/td/table[1]/.//table[2]/*/tr[1]/td/text()"));
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
