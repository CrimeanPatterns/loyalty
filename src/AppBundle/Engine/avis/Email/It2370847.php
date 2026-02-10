<?php

namespace AwardWallet\Engine\avis\Email;

class It2370847 extends \TAccountCheckerExtended
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
    public $upDate = "22.01.2015, 12:24";
    public $crDate = "22.01.2015, 12:11";
    public $xPath = "";
    public $mailFiles = "avis/it-2370847.eml, avis/it-2370878.eml";
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
                        return re("#Confirmation Number is: ([A-Z\d\-]+)#ix");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*Pick[\s-]*Up\s+Location:\s*(.*?)\n\s*Pick[\s-]*Up\s+Time:\s*(\w+,\s*\w+\s*\d+,\s*\d+[\s-]+\d+)#is");

                        return [
                            'PickupDatetime' => totime(re(2)),
                            'PickupPhone'    => detach("#\n\s*([\d\(\)+\- ]{7,})\s*(?:$|\n)#", $addr),
                            'PickupLocation' => nice($addr, ','),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*Return\s+Location:\s*(.*?)\n\s*Return\s+Time:\s*(\w+,\s*\w+\s*\d+,\s*\d+[\s-]+\d+)#is");

                        return [
                            'DropoffDatetime' => totime(re(2)),
                            'DropoffPhone'    => detach("#\n\s*([\d\(\)+\- ]{7,})\s*(?:$|\n)#", $addr),
                            'DropoffLocation' => nice($addr, ','),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Name\s*:\s*([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return xpath("//img[contains(@src, 'confirm_1.gif')]") ? 'Confirmed' : null;
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
