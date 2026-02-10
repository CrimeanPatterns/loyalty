<?php

namespace AwardWallet\Engine\carmel\Email;

class It2091943 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@carmellimo[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@carmellimo[.]com#i";
    public $reProvider = "#[@.]carmellimo[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "carmel/it-2091943.eml, carmel/it-2091944.eml";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re_white('Trip Id:  (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = cell('From:', +1);

                        return nice($loc);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = cell('Valid Date:', +1);

                        return strtotime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = cell('To:', +1);

                        return nice($loc);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return MISSING_DATE;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Transportation Type:', +1);

                        return nice($x);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Traveler:', +1);

                        return nice($x);
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
