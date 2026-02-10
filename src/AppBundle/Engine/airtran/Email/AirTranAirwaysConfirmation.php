<?php

namespace AwardWallet\Engine\airtran\Email;

class AirTranAirwaysConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+flying\s+AirTran\s+Airways#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#AirTran\s+Airways\s+Confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#confirmations@airtran\.com#i";
    public $reProvider = "#airtran\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "airtran/it-1.eml, airtran/it-1724755.eml, airtran/it-1730814.eml, airtran/it-2135167.eml, airtran/it-2135169.eml, airtran/it-2135454.eml, airtran/it-2159505.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("plain");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number\s*:\s*([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = explode("\n", re("#\n\s*(?:Passengers|Passenger|Passenger\(s\))\s*:\s*(.*?)\s+Flight#ms"));
                        $names = [];

                        foreach ($info as $item) {
                            if (re("#^\s*([A-Z ]+?)\s*\d*\s*$#", $item)) {
                                $names[] = re(1);
                            } else {
                                break;
                            }
                        }

                        return $names;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Payment Information:(?:(?s).*?)\s*Total\s*([^\n]+)#'));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Payment Information:(?:(?s).*?)\s*Fare\s*([^\n]+)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*\*?\w+[,\s]+\w+\s+\d+,\s*\d+[,\s]+Flight\s+\d+)#ims");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Flight\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);

                            $dep = $date . ',' . uberTime(1);
                            $arr = $date . ',' . uberTime(2);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\([A-Z]{3}\).*?\(([A-Z]{3})\)#ms");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Seat:\s*(\d+[A-Z]+)#");
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
