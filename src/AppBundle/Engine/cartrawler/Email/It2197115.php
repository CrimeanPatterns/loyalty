<?php

namespace AwardWallet\Engine\cartrawler\Email;

class It2197115 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]cartrawler[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]cartrawler[.]com#i";
    public $reProvider = "#[@.]cartrawler[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cartrawler/it-2197115.eml, cartrawler/it-3495906.eml, cartrawler/it-2655445.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // error segment
                    return '';

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re_white('(?:Reservation|Booking) number  (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = node("//*[contains(text(), 'Pick-up')]/following::strong[1]");

                        return nice($loc);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Pick-up (.+? \d+:\d+)');

                        return totime($date);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = node("//*[contains(text(), 'Return')]/following::strong[1]");

                        return nice($loc);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Return (.+? \d+:\d+)');

                        return totime($date);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (.+?),');

                        return nice($name);
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
