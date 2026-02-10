<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1729257 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amadeus\.#i";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus\.#i";
    public $caseReference = "6734";
    public $xPath = "";
    public $mailFiles = "amadeus/it-2040554.eml, amadeus/it-2040555.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\s+Airline Confirmation\s+([A-Z\d\-]+)#"),
                            cell("Booking Reference:", +1, 0)
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return clear("#\s+ADT\s*$#", cell("Traveller:", +1, 0));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Total:", +1, 0));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Fare:", +1, 0));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell("Total:", +1, 0));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $str = clear("#[^\d.,]#", cell("Taxes, fees, charges:", +1, 0), ' ');
                        $total = 0;

                        foreach (explode(' ', $str) as $item) {
                            $item = trim($item);

                            if ($item) {
                                $total += $item;
                            }
                        }

                        return $total;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell("Date of issue:", +1, 0), $this->date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Check-In closed by:')]/ancestor-or-self::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("preceding-sibling::tr[1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return clear("#[,\s]+Terminal.+#", node("preceding-sibling::tr[1]/td[5]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dep = node("preceding-sibling::tr[1]/td[3]") . ',' . node("preceding-sibling::tr[1]/td[4]");
                            $arr = node("following-sibling::tr[1]/td[3]") . ',' . node("following-sibling::tr[1]/td[4]");

                            correctDates($dep, $arr, $this->date);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return clear("#[\s,]+Terminal.+#", node("following-sibling::tr[1]/td[5]"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return clear("#Seat:\s*#", node("following-sibling::tr[1]/td[7]"));
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
