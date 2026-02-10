<?php

namespace AwardWallet\Engine\aeroplan\Email;

class OnlineCheckIn extends \TAccountCheckerExtended
{
    public $rePlain = "#We\s+look\s+forward\s+to\s+having\s+you\s+travel\s+with\s+us.*Air\s+Canada#si";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Air\s+Canada#i";
    public $reProvider = "";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "aeroplan/it-1736243.eml";
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
                        return re('#booking\s+reference:\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Dear\s+(.*),#')];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight:\s+(\w+?)(\d+)#', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $regex = '#travel\s+with\s+us\s+on\s+\w+\s+(\d+-\w+,\s+\d+)#';
                            $dateStr = str_replace(['-', ','], ' ', re($regex));
                            $res = null;

                            foreach (['Dep' => 'Departing', 'Arr' => 'Arriving'] as $key => $value) {
                                if (preg_match('#' . $value . ':\s+(.*)\s+-\s+(\d+:\d+)#', $text, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[2]);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
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
