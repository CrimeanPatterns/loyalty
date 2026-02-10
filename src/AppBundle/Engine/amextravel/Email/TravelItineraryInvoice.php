<?php

namespace AwardWallet\Engine\amextravel\Email;

class TravelItineraryInvoice extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Surf\s+City\s+Travel\s+-\s+American\s+Express#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]amextravel#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]amextravel#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "09.04.2015, 12:09";
    public $crDate = "09.04.2015, 11:26";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2602358.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter('#\n\s*(FLIGHT\s+.*\s+Air\s+Vendor)#');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s*\#\s*:\s+(\w+)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#Flight\s+Number:\s+(\d+)#');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re('#\n\s*From:\s+(.*)#');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re('#FLIGHT\s+(.*)#');

                            if (!$dateStr) {
                                return null;
                            }
                            $res = null;

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $s = re('#' . $value . ':\s+(.*)#i');

                                if (preg_match('#^(.*?)(?:\s+on\s+(\w+\s+\d+\s+\d+))?$#i', $s, $m)) {
                                    if (isset($m[2])) {
                                        $dateStr = $m[2];
                                    }
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re('#\n\s*To:\s+(.*)#');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re('#Air\s+Vendor:\s+(.*)#');
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#Aircraft:\s+(.*)#');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class\s+of\s+Service:\s+(.*)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Seat:\s+(\d+\w)#');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#FLIGHT\s+TIME:\s+(.*)#');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re('#(FOOD\s+FOR\s+PURCHASE|DINNER|MEALS)\s+(?:FLIGHT\s+TIME|DEPART)#i');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#NON-STOP#') ? 0 : null;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        $totalStr = re('#Total\s+Payment:(.*)#i', $this->text());
                        $itNew[0]['TotalCharge'] = cost($totalStr);
                        $itNew[0]['Currency'] = currency($totalStr);
                    }

                    return $itNew;
                },
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
