<?php

namespace AwardWallet\Engine\amextravel\Email;

class It2299940 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amex[\s-]*?travel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amex[\s-]*?travel#i";
    public $reProvider = "#amex[\s-]*?travel#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "23.12.2014, 15:36";
    public $crDate = "23.12.2014, 15:19";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2299940.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//text()[normalize-space(.) = 'AIR']/ancestor::table[1]");
                },

                "#^AIR#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Airline\s*REF\s*:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node(".//text()[normalize-space(.) = 'NAME']/ancestor::tr[1]/following::tr[string-length(normalize-space(.))>1][1]/td[1]");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return node(".//text()[normalize-space(.) = 'NAME']/ancestor::tr[1]/following::tr[string-length(normalize-space(.))>1][1]/td[last()]", $node, true, "#^([\dA-Z\-]+)$#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#^AIR\s+.*?\s+([A-Z\d]{2}\s*\d+)\s*\n#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+([A-Z]{3})$#", cell("From:", +2));
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#^(.*?)\s+[A-Z]{3}$#", cell("From:", +2));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $anchor = re("#\n\s*Received\s*:\s*([^\n]+)#");
                            $date = re("#\d+[A-Z]{3}#", cell("From:", +1, 0));

                            if ($date) {
                                $date .= $this->getEmailYear();

                                return correctDate($date . ',' . cell("From:", +3, 0), $anchor);
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+([A-Z]{3})$#", cell("To:", +2));
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#^(.*?)\s+[A-Z]{3}$#", cell("To:", +2));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $anchor = re("#\n\s*Received\s*:\s*([^\n]+)#");
                            $date = re("#\d+[A-Z]{3}#", cell("To:", +1, 0));

                            if ($date) {
                                $date .= $this->getEmailYear();

                                return correctDate($date . ',' . cell("To:", +3, 0), $anchor);
                            }
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Airplane\s*:\s*([^\n]+)#");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Miles\s*:\s*([^\n]+)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Class\s*:\s*([A-Z])#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node(".//text()[normalize-space(.) = 'NAME']/ancestor::tr[1]/following::tr[string-length(normalize-space(.))>1][1]/td[2]", $node, true, "#^(\d+[A-Z]+)$#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return node(".//text()[normalize-space(.) = 'NAME']/ancestor::tr[1]/following::tr[string-length(normalize-space(.))>1][1]/td[3]", $node, true, "#^(\d+[A-Z]+)$#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Stop\s*:\s*(\d+)#");
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
