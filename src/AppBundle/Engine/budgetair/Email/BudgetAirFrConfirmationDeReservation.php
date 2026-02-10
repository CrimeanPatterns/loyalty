<?php

namespace AwardWallet\Engine\budgetair\Email;

class BudgetAirFrConfirmationDeReservation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#budgetair\.fr.+?Numéro\s+de\s+réservation#is', 'blank', '2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['BudgetAir.fr - Confirmation de réservation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]budgetair\.fr#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]budgetair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "fr";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "04.06.2015, 16:23";
    public $crDate = "03.06.2015, 11:51";
    public $xPath = "";
    public $mailFiles = "budgetair/it-2266792.eml";
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
                        return re("#Code de réservation\s*\:\s+([^\s]+)\s#uism");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = [];
                        $psngrNodes = xpath("//table[./thead/tr[1]/td/*[contains(text(), 'Passager(s)')]]/tbody/tr[./th]");

                        for ($i = 0; $i < $psngrNodes->length; $i++) {
                            $passengers[] = re("/\.\s([^.]+)$/uxi", node("./th/text()", $psngrNodes->item($i)));
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//*[contains(text(), 'Total des coûts')][1]/*/text()"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//table[./*/tr/*[contains(text(), 'N° de vol')]]/tbody/tr[position() mod 2 = 1]");
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
                            $depDate = str_replace('.', '', $depDate);

                            if ($depDT = \DateTime::createFromFormat("d-F-Y H:i", en($depDate, "fr"))) {
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
                            $arrDate = str_replace('.', '', $arrDate);

                            if ($arrDT = \DateTime::createFromFormat("d-F-Y H:i", en($arrDate, "fr"))) {
                                return $arrDT->getTimestamp();
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("/(\w+?)\d+/ui", node("./*[5]/*/text()"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node("./*[7]/*/text()");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            $sDistance = node("./following-sibling::tr[1]/*[6]/*/text()");
                            $fDistance = floatval(re("/(\d+([.,]\d+)?)/ui", $sDistance));

                            return re("/\d+\s*(\w+?)\b/ui", $sDistance) == "km" ? ($fDistance * 0.62) : $sDistance;
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
        return ["fr"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
