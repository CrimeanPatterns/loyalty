<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1894542 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?THAIAIRWAYS.COM#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1894542.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader('date'));

                    return splitter("#\n\s*(ARR\s+FLT|IN\-OUT\s+DATE)#");
                },

                "#ARR#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*BOOKING REF[-\s]+[A-Z\d]{2}/([A-Z\d\-]+)#", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*NAME\s+([A-Z\d/ ]+)\n#", $this->text())];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#((?:ARR|DEP)\s+FLT)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#FLT\s+([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            re("#(\d+[A-Z]{3})\s+([A-Z]{3})([A-Z]{3})\s+\w+\s+(\d{2})(\d{2})\s+(\d{2})(\d{2})#");

                            $dep = strtotime(re(1) . ',' . re(4) . ':' . re(5), $this->date);
                            $arr = strtotime(re(1) . ',' . re(6) . ':' . re(7), $this->date);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                                'DepCode' => re(2),
                                'ArrCode' => re(3),
                            ];
                        },
                    ],
                ],

                "#IN-OUT DATE#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*BOOKING REF[-\s]+[A-Z\d]{2}/([A-Z\d\-]+)#", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*HOTEL\s+([^\n]*?)\s+BKD/TEL#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CheckInDate'  => totime(re("#IN\-OUT DATE\s+(\d+[A-Z]{3}\d+)\s*\-\s*(\d+[A-Z]{3}\d+)#")),
                            'CheckOutDate' => totime(re(2)),
                        ];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*HOTEL\s+([^\n]*?)\s+BKD/TEL#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#BKD/TEL[.\s]+([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*NAME\s+([A-Z\d/ ]+)\n#", $this->text())];
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*ROOM\s+([^\n]+)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*SERVICE\s+INCL\s+(.*?)\s+VOUCHER\s+NO#ims"));
                    },
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
        return true;
    }
}
