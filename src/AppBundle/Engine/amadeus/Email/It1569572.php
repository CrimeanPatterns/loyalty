<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1569572 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#amadeus#i";
    public $rePDFRange = "2000";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@amadeus\.#i";
    public $reProvider = "#@amadeus\.#i";
    public $caseReference = "6834";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1569572.eml";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("application/pdf", "text");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            end(explode('/', re("#,\s*AIRLINE:\s*([A-Z\d/\-]{3,})#"))),
                            re("#BOOKING\s+REF\s*:\s*(?:AMADEUS\s*:\s*)*([\w\d\-]+)#")
                        );
                    },

                    "TripNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#BOOKING\s+REF\s*:\s*(?:AMADEUS\s*:\s*)*([\w\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+NAME\s*:\s*(.*?)(?:\s{2,}|\n)#");
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\s+TICKET NUMBER\s*:\s*ETKT\s*([\d ]*?)(?:\s{2,}|\n)#")];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\s*TOTAL\s*:\s*(\w{3}\s*[\d.]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\s*AIR\s+FARE\s*:\s*(\w{3}\s*[\d.]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\s*TOTAL\s*:\s*([^\n]+)#"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $tax = 0;

                        foreach (explode(' ', clear("#[^\d.]+#", re("#\s*TAX\s*:((?:\s*\w{3}\s*[\d,]+\w*)+)#"), ' ')) as $item) {
                            $tax += $item;
                        }

                        return $tax;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\s+DATE\s*:\s*(\w{2}\s*\w+\s*\d{4})#ms"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $trip = re("#FROM\s*/TO\s*FLIGHT\s*CL\s*DATE\s*DEP\s*FARE\s*BASIS\s*NVB\s*NVA\s*BAG\s*ST\s+(.*?\n)\s*(?:THE\s*PICTURE|PAYMENT|AT\s*CHECK\-IN)#ms");
                        $its = splitter("#^(.+? +\b(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+)#m", $trip);

                        return $its;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#\s+\b(\w{2})\s*(\d{2,})\s+#");

                            return [
                                'FlightNumber' => re(2),
                                'AirlineName'  => re(1),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#^\s*(.*?)\s+(\w{2}\s*\d+)\s+(\w)*\s+(\d{2}\w{3})\s+(\d+)\s+(.*?)\s+(\d{2}\w{3}\s+)*(\d{2}\w{3}\s+)*(\d+\w)*\s*(\w+)*#", $text, 1));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $anchorDate = strtotime(re("#DATE\s*:\s*(\d{2}\s*\w+\s*\d+)#ms", $this->text()));
                            $year = date('Y', $anchorDate);

                            $depDate = strtotime(re("#^\s*(.*?)\s+(\w{2}\s*\d+)\s+(\w)*\s+(\d{2}\w{3})\s+(\d+)\s+(.*?)\s+(\d{2}\w{3}\s+)*(\d{2}\w{3}\s+)*(\d+\w)*\s*(\w+)*#", $text, 4) . $year . ',' . re(5));
                            $arrDate = strtotime(re(4) . $year . ',' . re("#^\s*([^\n]+)\s+(SEAT\s*:\s*(\d+\w)\s+)*ARRIVAL\s*TIME\s*:\s*(\d+)#ms", $text, 4));

                            if ($arrDate < $depDate) {
                                $arrDate = strtotime('+1 day', $arrDate);
                            }

                            if ($depDate < $anchorDate) {
                                $depDate = strtotime('+1 year', $depDate);
                                $arrDate = strtotime('+1 year', $arrDate);
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
                            $arrName = trim(re("#\s*([\d\w ]+)\s+(SEAT\s*:\s*\d+\w\s+)*ARRIVAL\s*TIME\s*:\s*(\d+)#ms",
                                $text));

                            if (isset($it['DepName']) && !empty($arrName)) {
                                $depTerm = re("#{$it['DepName']}.+?TERMINAL\s*:\s*(\w+).+?{$arrName}#s", $text);
                                $arrTerm = re("#{$arrName}.+?TERMINAL\s*:\s*(\w+)#s", $text);

                                return [
                                    'ArrName'           => $arrName,
                                    'DepartureTerminal' => $depTerm,
                                    'ArrivalTerminal'   => $arrTerm,
                                ];
                            }

                            return $arrName;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#^\s*(.*?)\s+(\w{2}\s*\d+)\s+(\w)*\s+(\d{2}\w{3})\s+(\d+)\s+([^\n]+)\s+(\d{2}\w{3}\s+)*(\d{2}\w{3}\s+)*(\d+\w)*\s*(\w+)*#", $text, 3);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#^\s*([^\n]+)\s{2,}SEAT\s*:\s*(\d+\w)\s+ARRIVAL\s*TIME\s*:\s*(\d+)#ms", $text, 2);
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
        return true;
    }
}
