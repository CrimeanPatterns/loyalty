<?php

namespace AwardWallet\Engine\capitalcards\Email;

class It2430957 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?capitalone#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#capitalone#i', 'us', ''],
    ];
    public $reProvider = [
        ['#capitalone#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.02.2015, 15:07";
    public $crDate = "03.02.2015, 14:55";
    public $xPath = "";
    public $mailFiles = "capitalcards/it-2430957.eml, capitalcards/it-2430957.eml";
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
                        return re("#\n\s*Confirmation\s*\#\s*:\s*([A-Z\d-\-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pick up Location\s*:\s*([^\n]+)#ix");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Pick-up\s*:\s*([^\n]+)#")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Drop off Location\s*:\s*([^\n]+)#ix"),
                            MISSING_DATE
                        );
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Drop-off\s*:\s*([^\n]+)#")));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hours\s+of\s+Operation\s*:\s*(.*?)(?: *\n *){2,}#is");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rental Company\s*:\s*([^\n]+)#ix");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(.*?Car)\s*\n\s*Hours\s+of\s+Operation#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Primary Contact\s*:\s*([^\n]+)#ix");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
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
