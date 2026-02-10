<?php

namespace AwardWallet\Engine\amextravel\Email;

class It2240384 extends \TAccountCheckerExtended
{
    public $mailFiles = "amextravel/it-2240384.eml";

    public $rePlain = [
        ['#American Express has initiated#i', 'us', '5000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@\.]aexp\.#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@\.]aexp\.#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "9499";
    public $upDate = "07.09.2015, 11:50";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = strtotime($this->parser->getHeader('date'));
                    $this->year = date('Y', $date);

                    return splitter("#\n\s*((?:AIR|CAR|HOTEL)\s*\-\s*\w+,\s*\w+\s+\d+)#");
                },

                "#^AIR#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $air = re("#^[^\n]+\n\s*(\w+)(?:\s+.*?)?\s+Flight\s+\d+#");

                        return re("#\n\s*{$air}\s+LOCATOR\s+([A-Z\d-]+)#i", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Passengers'     => re("#\n\s*Passengers\s+Reference\s*\#\s+Frequent\s+Flyer\s*\#\s+(.*?)\s{2,}(.*?)\s{2,}(.+)#", $this->text()),
                            'AccountNumbers' => re(3),
                        ];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^[^\n]+\n\s*(.*?)\s+Flight\s+(\d+)\s+(.*?)\n#"),
                                'FlightNumber' => re(2),
                                'Cabin'        => re(3),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*From\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = sprintf('%s %s', re("#\n\s*From\s*:\s*[^\n]+\s+([^\n]+)#"), $this->year);

                            return totime(uberDateTime($date));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*To\s*:\s*([^\n]+)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = sprintf('%s %s', re("#\n\s*To\s*:\s*[^\n]+\s+([^\n]+)#"), $this->year);

                            return totime(uberDateTime($date));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Equipment\s*:\s*([^\n]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Seats\s*:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meals\s*:\s*([^\n]+)#");
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
