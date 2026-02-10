<?php

namespace AwardWallet\Engine\airtran\Email;

class CheckInOnline extends \TAccountCheckerExtended
{
    public $reFrom = "#airtran\.com#i";
    public $reProvider = "#airtran\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+flying\s+AirTran\s+Airways.\s+Check\s+in\s+online#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#AirTran\s+Airways\s+Check-in\s+Online#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "airtran/it-1699722.eml, airtran/it-1741206.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation\s+Number\s*:\s*([\w\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = explode("\n", re("#\n\s*(?:Passengers?|Passenger\(s\))\s*:\s*(.*?)\s+Flight#ms"));
                        $names = [];

                        foreach ($info as $item) {
                            if (re("#^\s*([A-Z ]+)\s+\d+\s*$#", $item)) {
                                $names[] = re(1);
                            }
                        }

                        return $names;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Payment Information:\s*Total\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*\w+[,\s]+\w+\s+\d+,\s*\d+[,\s]+Flight\s+\d+)#ims");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Flight\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#\w+\s+\d+,\s+\d{4}#');

                            foreach (['Dep' => 'Departing', 'Arr' => 'Arriving'] as $key => $value) {
                                if (preg_match('#' . $value . '\s+(.*)\s+\((\w+)\)\s+at\s+(.*)#', $text, $m)) {
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[3]);
                                }
                            }

                            return $res;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return nice(str_replace('/', ',', re("#\s+Seat:\s*(.*)#")));
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Non-Stop#i', $text)) {
                                return 0;
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
}
