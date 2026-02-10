<?php

namespace AwardWallet\Engine\british\Email;

class It2112333 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?verkehrsbuero#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#britishairways#i";
    public $reProvider = "#britishairways#i";
    public $caseReference = "6654";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "british/it-2112333.eml";
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
                        return re("#\n\s*Buchungsnummer\s*:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Für\s*:\s*([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#", $this->text());
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Datum\s*:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(\d+\s+Flug\n)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flug\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Von\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(re("#\n\s*Datum\s*:.*?(\d+\.\s*.*?\s+\d{4})#"));

                            $dep = $date . ',' . re("#Abflug\s*:\s*(\d+:\d+)#");
                            $arr = $date . ',' . re("#Ankunft\s*:\s*(\d+:\d+)#");

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Nach\s*:\s*([^\n]+)#");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*([A-Z\d]{2})\s*\-\s*(.*?)(?:,\s*Fluggesellschaft)?\n#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Buchungscode\s*:\s*([A-Z])#");
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
