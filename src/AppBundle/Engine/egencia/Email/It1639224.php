<?php

namespace AwardWallet\Engine\egencia\Email;

class It1639224 extends \TAccountCheckerExtended
{
    public $reFrom = "#egencia#i";
    public $reProvider = "#egencia#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?egencia|Egencia\s+booking\s+confirmation#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "egencia/it-2.eml, egencia/it-2709400.eml, egencia/it-5.eml, egencia/it-7.eml, egencia/it-8.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n\s*(\w+\s+\d+\-\w+\-\d{4}\s+(?:Flight|Hotel|Car))#");
                },

                "#\s+Car\s+\(#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },
                ],

                "#\s+Hotel\s+\(#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation number:\s*([\w\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]*?)\s+Confirmation number#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CheckInDate'  => totime(re("#\n\s*Check out\s+(\w+\s*\d+\-\w+\-\d+)\s+(\w+\s*\d+\-\w+\-\d+)\s+(.*?)\s+([\d \(\)\-+]+)\s+Fax\s+([\d \(\)\-+]+)#ms")),
                            'CheckOutDate' => totime(re(2)),
                            'Address'      => nice(glue(re(3))),
                            'Phone'        => re(4),
                            'Fax'          => re(5),
                        ];
                    },

                    "RateType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]*?)\s*\-\s*Stay and Save#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#\n\s*Cancellation\s+and\s+Changes[:\s]+(.*?)\n{2,}#ims")));
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Includes:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total price:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total price:\s*([^\n]+)#"));
                    },
                ],

                "#\s+Flight\s+\(#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $airline = re("#\s+([^\n]+)\s+Arrive\s+\d+:\d+#");
                        $conf = re("#$airline\s+confirmation code:\s*([A-Z\w\-]+)#", $this->text());

                        return $conf;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n\s*Account holder:\s+([^\n\t]*?)\s{2,}#", $this->text()));
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#frequent flyer \#([\d\w\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total price:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total price:\s*([^\n]+)#", $this->text()));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Airline ticketing date:\s*([^\n]+)#", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $text = clear("#\n\s*Flight:\s+.*?\s+to\s+.*?.+#ms", $text);

                        return splitter("#(\n\s*Depart\s+\d+:\d+)#", $text);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s+(\d+)\s+#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#(\d+\-\w+\-\d{4})#", $this->parent());
                            $dep = $date . ',' . uberTime(1);
                            $arr = $date . ',' . uberTime(2);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+([^\n]+)\s+Arrive\s+\d+:\d+#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#\s+([\w/]+)\s+Class\s+\(([A-Z])\)#"),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Seat\s+(\d+\w)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Duration' => re("#\s+(\d+hr\s*\d+mn),\s*([^,]*?)\s{3,}#"),
                                'Aircraft' => nice(re(2)),
                            ];
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
