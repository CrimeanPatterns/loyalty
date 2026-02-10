<?php

namespace AwardWallet\Engine\avis\Email;

class It2370848 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?avis[.\s-]#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]avis[-.]#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]avis[-.]#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "22.01.2015, 17:25";
    public $crDate = "22.01.2015, 17:19";
    public $xPath = "";
    public $mailFiles = "avis/it-2370848.eml";
    public $re_catcher = "#.*?#";
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
                        return re("#Confirmation Number\s*:\s*([A-Z\d]+)#ix");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return [
                            'PickupLocation' => re("#\n\s*Pick[\s-]*up:\s*Location:\s*(.*?)\n\s*Date[\s&]+Time:\s*(.*?)\n\s*Return:#is"),
                            'PickupDatetime' => totime(re(2)),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'DropoffLocation' => re("#\n\s*Return:\s*Location:\s*(.*?)\n\s*Date[\s&]+Time:\s*(.*?)\n\s#is"),
                            'DropoffDatetime' => totime(re(2)),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Thank you for visiting (.*?)[:.!]#ix");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car Group\s*:\s*([^\n]+)#ix");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Dear (.*?),#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#your reservation is (\w+)#ix");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation is cancel+ed#ix") ? true : false;
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
