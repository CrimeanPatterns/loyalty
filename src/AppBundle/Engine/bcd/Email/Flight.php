<?php

namespace AwardWallet\Engine\bcd\Email;

class Flight extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?bcdtravel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#bcdtravel#i";
    public $reProvider = "";
    public $caseReference = "";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "bcd/it-2032582.eml, bcd/it-2032584.eml, bcd/it-2032587.eml, bcd/it-2032588.eml, bcd/it-2032589.eml, bcd/it-2032592.eml, bcd/it-2032596.eml, bcd/it-2198783.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $r = '#\s*(.*)\s+Confirmation\s*:\s+([\w\-]+)#i';
                    $this->recordLocators = [];

                    if (preg_match_all($r, $text, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->recordLocators[$m[1]] = $m[2];
                        }
                    }

                    return splitter("#(\n\s*(?:[^\n]*?\s+No\.\s*\d+|Pick[\s-]+Up|Check[\s\-]In))#");
                },

                "#\s+No\.\s*\d+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $an = re('#\s*(.*)\s+No\.\s+\d+#i');

                        if (isset($this->recordLocators[$an])) {
                            return $this->recordLocators[$an];
                        }
                        //return re("#\n\s*[^\n]*?\s+Confirmation:\s*([A-Z\d\-]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Changed to#") ? 'Changed' : null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*(.*?)\s+No\.\s*(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $time = null; //re("#\n\s*Changed\s+to\s*(\d+:\d+\s*[paAP])#")?re(1).'m':null;

                            return [
                                'DepName' => nice(re("#\n\s*Depart\s*:\s*([^\n]*?)\s*\(([A-Z]{3})\)\s+on\s+(.*?),\s*(\d+:\d+\s*[APMapm]+)#")),
                                'DepCode' => re(2),
                                'DepDate' => totime(re(3) . ',' . ($time ? $time : re(4))),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrName' => nice(re("#\n\s*Arrive\s*:\s*([^\n]*?)\s*\(([A-Z]{3})\)\s+on\s+(.+)#")),
                                'ArrCode' => re(2),
                                'ArrDate' => totime(re(3)),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Seat No\s*:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Journey Time\s*:\s*([^\n]+)#");
                        },
                    ],
                ],

                "#Pick[\s-]+up#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RentalCompany' => re("#\n\s*(.*?)\s+Confirmation\s*:\s*([A-Z\d\-]+)#", $this->text()),
                            'Number'        => re(2),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'PickupLocation' => re("#\n\s*Pick[\s\-]+Up\s*:\s*(.*?)\s*(\w{3},\s*\w+\s+\d+,\s*\d{4},\s*\d+:\d+\s*[APMapm]+)#i"),
                            'PickupDatetime' => totime(re(2)),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'DropoffLocation' => re("#\n\s*Drop[\s\-]+Off\s*:\s*(.*?)\s*(\w{3},\s*\w+\s+\d+,\s*\d{4},\s*\d+:\d+\s*[APMapm]+)#i"),
                            'DropoffDatetime' => totime(re(2)),
                        ];
                    },
                ],

                "#Check[\s-]+in#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName'          => re("#\n\s*(.*?)\s*Confirmation\s*:\s*([A-Z\d\-]+)\s+Check[\s-]+In\s*:#", $this->text()),
                            'ConfirmationNumber' => re(2),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check[\s-]+In\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check[\s-]+Out\s*:\s*([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Address\s*:\s*([^\n]+)#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone\s*:\s*([\d\- \(\)+]+)#");
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
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
        return true;
    }
}
