<?php

namespace AwardWallet\Engine\delta\Email;

class YourDeltaItineraryHasChanged extends \TAccountCheckerExtended
{
    public $rePlain = "#(?:\n[>\s*]*From\s*:[^\n]*?delta|Updated Itinerary \- Delta confirmation)#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]delta\.com#i";
    public $reProvider = "#[@.]delta\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "delta/it-2185727.eml";
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
                        return re('#Delta\s+confirmation\s*\#\s+([\w\-]+)#i');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Our\s+schedule\s+has\s+(changed)#i');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#\w+,\s+\w+\s+\d+\s+(?:Delta)?\s+Flight:(?:(?s).*?)Seats?:\s+.*#i', $text, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                "AirlineName" => re('#(Delta) Flight\s*:\s+(?:Delta\s+)?(\d+)#'),
                                "FlightNumber"=> re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $d = re('#\w+,\s+(\w+\s+\d+)#i');

                            if (!$d) {
                                return null;
                            }
                            $y = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$y) {
                                return null;
                            }
                            $res = null;

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $r = '#' . $value . ':\s+(\d+:\d+\s*[ap]m)(?:\s*\((\w+\s+\d+)\))?\s+(?:from|at)\s+(.*)#i';

                                if (preg_match($r, $text, $m)) {
                                    $res[$key . 'Date'] = strtotime(($m[2] ? $m[2] : $d) . ', ' . $y . ', ' . $m[1]);
                                    $res[$key . 'Name'] = $m[3];
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
