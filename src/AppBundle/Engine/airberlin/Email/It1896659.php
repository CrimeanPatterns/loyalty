<?php

namespace AwardWallet\Engine\airberlin\Email;

class It1896659 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?airberlin|click\s+on\s+the\s+following\s+link.*?airberlin#i";
    public $rePlainRange = "2222";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#airberlin#i";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#airberlin#i";
    public $reProvider = "#airberlin#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "airberlin/it-1834696.eml, airberlin/it-1896659.eml, airberlin/it-1896661.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->getDocument("application/pdf", "text");

                    if (!re("#airberlin#", $text)) {
                        return null;
                    }

                    // create simple html table
                    $this->setDocument("application/pdf", "simpletable");

                    // extract "original" letter only
                    if (nodes("//*[contains(text(), \"Customer's Copy\")]")) {
                        $this->setDocument("xpath", "//*[contains(text(), \"Customer's Copy\")]/ancestor::tr[1]/preceding-sibling::tr[position()<20]");
                    }

                    return xpath("//*[contains(text(), 'Name of passenger')]/ancestor::tr[1]");
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("following-sibling::tr[1]/td"));

                        return [
                            'Passengers'     => [next($r)],
                            'AccountNumbers' => next($r),
                        ];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("following-sibling::tr[contains(., 'From') and contains(., 'Flight no')][1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $r = filter(nodes("following-sibling::tr[1]/td"));

                            return [
                                'DepName'      => reset($r),
                                'ArrName'      => next($r),
                                'AirlineName'  => re("#^([A-Z\d]{2})\s*(\d+)#", next($r)),
                                'FlightNumber' => re(2),
                                'DepDate'      => totime(next($r) . ',' . next($r)),
                                'ArrDate'      => MISSING_DATE,
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $a = filter(nodes("following-sibling::tr[contains(., 'Cabin Class')][1]/following-sibling::tr[1]/td"));

                            return re("#^[A-Z]$#", reset($a));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $r = filter(nodes("following-sibling::tr[contains(., 'Seat')][1]/following-sibling::tr[1]/td"));
                            end($r);

                            return re("#^\d+[A-Z]+$#", prev($r));
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true, true);
                },

                "#.*#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
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
}
