<?php

namespace AwardWallet\Engine\british\Email;

class It2498921 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?british#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]ba[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#british#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.02.2015, 05:09";
    public $crDate = "28.02.2015, 05:04";
    public $xPath = "";
    public $mailFiles = "british/it-2498921.eml";
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
                        return re("#\n\s*Booking reference\s*:\s*([A-Z\d\-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'PickupDatetime' => totime(re("#\n\s*Pick[\s\-]+up\s+(\d+\s+\w+\s+\d+\s+\d+:\d+),\s*([^\n]+)#ix")),
                            'PickupLocation' => re(2),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'DropoffDatetime' => totime(re("#\n\s*Drop[\s\-]+off\s+(\d+\s+\w+\s+\d+\s+\d+:\d+),\s*([^\n]+)#ix")),
                            'DropoffLocation' => re(2),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return cell("Passenger(s)", +1, 0);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Payment Total\s+([^\n]+)#"));
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
