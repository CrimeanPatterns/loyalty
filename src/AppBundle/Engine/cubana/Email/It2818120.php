<?php

namespace AwardWallet\Engine\cubana\Email;

// TODO: merge with parsers amadeus/AirTicketHtml2016, tahitinui/ConfirmationReservation, lotpair/ConfirmChanges (in favor of amadeus/AirTicketHtml2016)

class It2818120 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Confirmation\s+of\s+your\s+order.+?choosing\s+Cubana\s+de\s+Aviación#is', 'blank', ''],
        ['#Confirmation\s+for\s+reservation.+?checkmytrip\.com#is', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Confirmation for your order', 'blank', ''],
        ['Confirmation for reservation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]cubana#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]cubana#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "18.06.2015, 10:05";
    public $crDate = "17.06.2015, 14:06";
    public $xPath = "";
    public $mailFiles = "cubana/it-2818120.eml, cubana/it-2818279.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match('/[@.]cubana\./i', $headers['from']) > 0
            && isset($headers['subject']) && (stripos($headers['subject'], 'Confirmation for your order') !== false || stripos($headers['subject'], 'Confirmation for reservation') !== false);
    }

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
                        return re("#Booking\s+reservation\s+number:\s+(\w+)\s*\n#is", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $psngrRaw = re("/TRAVELLER\s+INFORMATION(\s+.+)\s+YOUR\s+FLIGHT\s+SELECTION/uis", $this->text());
                        preg_match_all("/\n\s*(?:Mr|Ms|Mrs)\s+(.+?)\s*\n/ui", $psngrRaw, $psgnrs);
                        array_walk($psgnrs[1], function (&$val, $key) { $val = beautifulName($val); });

                        return $psgnrs[1];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return [
                            'TotalCharge' => cost(re("#Flight payment and ticket\s+([\d.,]+)\s+\b([A-Z]{3})\b#", $this->text())),
                            'Currency'    => currency(re(2)),
                        ];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Trip status:\s*([^\n]+)#", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*Flight\s+\d+\s+\w+,\s*\w+\s+\d+)#u");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Airline\s*?\n.*?\w+?(\d+)\s*\n#ui");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#Departure:\s*[\d:]+\s*(.*?)(,\s*terminal\s*\w+)?\s+Arrival#ims");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $depDate = strtotime(re('#\s*\w+,(.*?)\n#ims') . ' ' . re("#Departure:\s*([\d:]+)\s*.*\n#ims"));
                            $arrDate = strtotime(re('#\s*\w+,(.*?)\n#ims') . ' ' . re("#Arrival:\s*([\d:]+)\s*.*\n#ims"));

                            if ($arrDate < $depDate) {
                                $arrDate = strtotime('+1 day', $arrDate);
                            }

                            return [
                                'DepDate' => $depDate,
                                'ArrDate' => $arrDate,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#Arrival:\s*[\d:]+\s*(.*?)(,\s*terminal\s*\w+)?\s+Airline#ims");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#Airline\s*?\n.*?(\w+?)\d+\s*\n#ui");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Aircraft:\s*(.*)\s*\n#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#Fare Type:\s*(.*?)\s*\n#ims");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $flightIndex = re("#\n\s*(Flight\s+\d+)\s*#");
                            $info = re("#Flight special requests.*?{$flightIndex}:\s*(.*?)\s+(?:Flight\s+\d+:|You have req)#ms", $this->text());

                            return [
                                'Seats' => re("#\s+(\d+[A-Z])\s+([^\n]+)#", $info),
                                'Meal'  => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#Duration:?\s*?\n.*?([\d:]+)\s*\n#ui");
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
