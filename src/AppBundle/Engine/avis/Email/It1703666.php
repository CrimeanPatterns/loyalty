<?php

namespace AwardWallet\Engine\avis\Email;

class It1703666 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@avis\.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@\.]avis\.#i";
    public $reProvider = "#[@\.]avis\.#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "avis/it-1703666.eml";
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
                        return re("#(?:Reservation number|Confirmation number)\s*([\w\d]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#Pick-Up.*?\n.*?\n.*?\n.*?\n(.*?)hours#ims")));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(trim(re("#Pick-Up.*?\n.*?\n(.*?)[-\n]#ims")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#Pick\-Up.*?\n.*?\n.*?\n.*?\n(.*?)hours#ims")));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return orval(
                                strtotime(trim(re("#Pick\-Up.*?\n.*?\n.*?\n(.*?)[-\n]#ims"))),
                                $it['PickupDatetime']
                            );
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Pick-Up.*?Phone\s*(.*?)\n#ims"));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Pick-Up.*?hours\s*(.*?)\n#ims"));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#car class.*?\s+[A-Z]\s+#msi"));
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "similar") and contains(normalize-space(text()), "or similar")]/preceding::h3[1]');
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node('//span[contains(text(), "similar") and contains(normalize-space(text()), "or similar")]/following::img[1]/@src');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Personal Information.*?\n.*?\n(.*?)\n#"));
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re("#Coupon:\s*([\w\d]+)#i");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        re("#estimated total\s*([\d.,]+)\s*(\w+)#");

                        return ["TotalCharge" => re(1), "Currency" => re(2)];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#YOUR\s+RESERVATION\s+([^\n]+)#i");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#RESERVATION IS CANCEL+ED#i") ? true : false;
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        return re("#Disct.*?-([\d,.]+)#ims");
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
