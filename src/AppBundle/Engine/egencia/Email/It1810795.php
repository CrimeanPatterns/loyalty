<?php

namespace AwardWallet\Engine\egencia\Email;

class It1810795 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?egencia#i";
    public $rePlainRange = "";
    public $reHtml = "VIA Egencia";
    public $reHtmlRange = "-1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#egencia#i";
    public $reProvider = "#egencia#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "egencia/it-14270985.eml, egencia/it-1810795.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[contains(text(), 'FLIGHT')]/ancestor-or-self::table[1]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Airline reference\s*:\s*([\d\w\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re('#E-ticket receipt for\s+([A-Z/ .,-]+?)(?:\s+\-|\()#', $this->text());
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_filter([cell("FREQUENT FLYER NO", 0, +1, '', null)]);
                    },
                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//text()[starts-with(normalize-space(), 'ETK / ')]", null, "#ETK /\s*([\d\-]{7,})\b#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDate(re("#E\-ticket receipt for\s+([^\n]+)#", $this->text())));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(cell("Departure", +2));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Departure\s+([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(cell("Departure", -2, 0) . ',' . cell("Departure", -1, 0));
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            return trim(str_ireplace('Terminal', '', cell("Check-in:", +2, 0)));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\s*([^\n]+)#", cell("Arrival", +1, 0));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(cell("Arrival", -2, 0) . ',' . cell("Arrival", -1, 0));
                        },

                        "ArrivalTerminal" => function ($text = '', $node = null, $it = null) {
                            return trim(str_ireplace('Terminal', '', cell("Arrival", +2, 0)));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Equipment\s*:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#\n\s*Class\s*:\s*(.*?)\s+\(([A-Z])\)#"),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s*:\s*([^\n]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return array_filter([re("#\n\s*Seat\s*:\s*(\d{1,3}[A-Z])\b#")]);
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
