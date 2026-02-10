<?php

namespace AwardWallet\Engine\eurostar\Email;

class It1732825 extends \TAccountCheckerExtended
{
    public $reFrom = "#[@.]eurostar\.com\b#i";
    public $reProvider = "#[@.]eurostar\.com\b#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?eurostar#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "fr";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Booking reference\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $r = filter(explode("\n", text(xpath("//*[contains(text(), 'VOYAGEUR(S)')]/ancestor::tr[1]/following-sibling::tr[1]"))));

                        foreach ($r as &$name) {
                            $name = trim(clear("#(?:^[^\w]+)|(?:\s*\-\s*ADULT$)#", $name));
                        }

                        return $r;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*PRIX TOTAL PAYÉ\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*PRIX TOTAL PAYÉ\s*:\s*([^\n]+)#"));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(Trajet retour)#");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*De\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $p = explode("/", uberDate());

                            if (!isset($p[2])) {
                                return null;
                            }

                            $date = "$p[2]-$p[1]-$p[0]";

                            $dep = $date . "," . uberTime(1);
                            $arr = $date . "," . uberTime(2);

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
                            return re("#\n\s*vers\s*:\s*([^\n]+)#");
                        },

                        "Type" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Numéro du train\s*:\s*([^\n]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = [];

                            re("#Voiture\s+(\d+)[,\s]+Place\s+(\d+)#", function ($m) use (&$seats) {
                                $seats[] = "Voiture $m[1]/$m[2]";
                            }, $text);

                            return implode(",", $seats);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Durée\s*:\s*([^\n]+)#");
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
        return ["fr"];
    }
}
