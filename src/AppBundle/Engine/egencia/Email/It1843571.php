<?php

namespace AwardWallet\Engine\egencia\Email;

class It1843571 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?egencia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#egencia#i";
    public $reProvider = "#egencia#i";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // Parser toggled off as it is covered by 'emailTravelBookingChecker.php'
                    return null;
                    $text = $this->setDocument("application/pdf", "text");
                    $text = clear("#\n\s*www.egencia.com.au\s*\|\s*Egencia.*?\n\s*Page\s+\d+#ims", $text);

                    return splitter("#(\n\s*\w+,\s*\d+\s+\w+,\s*\d{4}\s+Flight\s+Departing\s+)#", $text);
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Class\s+([A-Z\d]{5,})#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return preg_replace("#([a-z])([A-Z])#", '\1 \2', re("#\n\s*([^\n]*?)\s+Company Code:#", $this->text()));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Airfare\s+([A-Z]{3}.*?[\d.,]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Airfare\s+([A-Z]{3}.*?[\d.,]+)#", $this->text()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes\s+([A-Z]{3}.*?[\d.,]+)#", $this->text()));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(Confirmed)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\s+Class\s+[A-Z\d]{5,}\n\s*([A-Z\d]{2}\d+)#ms"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate();

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

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Aircraft\s*:\s*([^\n]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Seat\s+Request\s*:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Total time\s*:\s*([^\n]+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = re("#\n\s*Stops\s*:\s*([^\n]+)#");
                            $count = 0;
                            re("#\([A-Z]{3}\)#", function ($m) use (&$count) {
                                $count++;
                            }, $stops);

                            return $count;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
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
}
