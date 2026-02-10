<?php

namespace AwardWallet\Engine\eurostar\Email;

class It1984483 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?eurostar[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@eurostar[.]com#i";
    public $reProvider = "#[@.]eurostar[.]com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "eurostar/it-1984483.eml";
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
                        return re_white('Booking ref: ([\w-]+)');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'Your Details')]/ancestor-or-self::tr[1]/following-sibling::tr//td/strong[1]");

                        return $ppl;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Grand total', +1);

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Subtotal', +1);

                        return cost($x);
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Outward journey') or contains(text(), 'Inbound journey')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(@class, 'destination')]/strong[2]");

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = node("(.//*[contains(@class, 'train_details')]) [1]");
                            $dt = clear('/th/', $dt);
                            $dt = uberDateTime($dt);

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(@class, 'destination')]/strong[3]");

                            return nice($name);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = node("(.//*[contains(@class, 'train_details')]) [2]");
                            $dt = clear('/th/', $dt);
                            $dt = uberDateTime($dt);

                            return strtotime($dt);
                        },

                        "Type" => function ($text = '', $node = null, $it = null) {
                            $x = node("(.//*[contains(@class, 'train_details')]) [4]");

                            return re('/(\d+)/', $x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(text(), 'x Adult')]/preceding-sibling::td[1]");

                            return re("#(\w+)#", $info);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $x = node("(.//*[contains(@class, 'train_details')]) [3]");

                            return re('/(\d+h\d+m)/', $x);
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
}
