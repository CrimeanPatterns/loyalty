<?php

namespace AwardWallet\Engine\aeromexico\Email;

class It1924026 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@aeromexico[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "es, en";
    public $typesCount = "1";
    public $reFrom = "#@aeromexico[.]com#i";
    public $reProvider = "#@aeromexico[.]com#i";
    public $caseReference = "7061";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "aeromexico/it-1924026.eml, aeromexico/it-2051665.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');

                    return splitter("#([^\n.]*?\s+PNR\s+[A-Z\d\-]+\s+)#", $text);
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#PNR\s*([\w-]+)#iu");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re("#^\s*(.+?)\s*PNR#i");
                        $name = clear("#^[^.]+\.#", $name);

                        return [nice($name)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(?:Flight|Vuelo)\s+(\d+)#is");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(?:Departure|Desde)\s+(.*?)\s+(?:Arrival|Hacia)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re('#\s+(?:Fecha|Date)\s+(\d+[A-Z]+\d+)#') . ', ' . re('#\s+(?:Time|Hora)\s+(\d+)(\d{2})#') . ':' . re(2));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(?:Arrival|Hacia)\s+(.*?)\s+(?:Date|Fecha)\s+#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return AIRLINE_UNKNOWN;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(?:Clase|Class)\s+([A-Z])\s+#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(?:Asiento|Seat)\s+(\d+[A-Z]+)\s+#");
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true, true);
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
        return ["es", "en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
