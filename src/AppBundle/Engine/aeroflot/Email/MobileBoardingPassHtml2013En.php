<?php

namespace AwardWallet\Engine\aeroflot\Email;

class MobileBoardingPassHtml2013En extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $reHtml = [
        ['#Boarding\s+Time\s*:.+?Departure\s+Time\s*:.+?\bAeroflot\b#si', 'blank', '/1'],
    ];
    public $rePDF = "";
    public $reSubject = [
        ['Aeroflot Online Check-In Boarding Pass', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]aeroflot#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]aeroflot#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "19.08.2015, 13:01";
    public $crDate = "19.08.2015, 11:33";
    public $xPath = "";
    public $mailFiles = "aeroflot/it-3000786.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    private $airline;

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
                        if (stripos($text, 'aeroflot') !== false) {
                            $this->airline = 'SU';
                        }

                        return re("#Confirmation Number\s+([A-Z\d]{5,6})#i");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all("#^\s*(\S[^\n]+)#m", re("#Passenger\s+Name\/s[\s:]+(.+?\n\s*)Flight\s+\d+#si"), $m)) {
                            return $m[1];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*Flight(?:\ |\t)+\d+\s+.\s+[A-Z]+\s+\()#i");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = re("#\sFlight\s+(\d+)#i");

                            if (!empty($this->airline)) {
                                return [
                                    "AirlineName"  => $this->airline,
                                    "FlightNumber" => $subj,
                                ];
                            }

                            return $subj;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $data['DepCode'] = re("#\s([A-Z]+)\s+\(([^\n\)]+)\)#");
                            $data['DepName'] = re(2);

                            return $data;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $data['DepDate'] = totime($date . ", " . re("#Departure\s+Time\s*:\s*(\d+:\d+\s\w*).+?(\d+:\d+\s\w*)#si"));
                            $data['ArrDate'] = totime($date . ", " . re(2));
                            correctDates($data['DepDate'], $data['ArrDate']);

                            return $data;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $data['ArrCode'] = re("#\s[A-Z]+\s+\(.+?\s([A-Z]+)\s+\(([^\n\)]+)\)#s");
                            $data['ArrName'] = re(2);

                            return $data;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (preg_match_all("#\b(\d+[A-Z]+)\b#", re("#\sSeat\/s\s*:\s*(.+?)(?>\n\s*?\n|\s*$)#si"), $m)) {
                                return implode(", ", $m[1]);
                            }
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
