<?php

namespace AwardWallet\Engine\ebookers\Email;

class It2579916 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?ebookers#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]ebookers#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]ebookers#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.03.2015, 22:57";
    public $crDate = "30.03.2015, 22:47";
    public $xPath = "";
    public $mailFiles = "ebookers/it-2579916.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[normalize-space(text()) = 'Your Car']/ancestor::tr[1]/following::tr[1]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Booking Reference\s*:\s*([A-Z\d-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'PickupDatetime' => totime(re("#\n\s*Pick-up\s*:\s*([^|]+)\|\s*([^\|\n]+)#")),
                            'PickupLocation' => re(2),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            'DropoffDatetime' => totime(re("#\n\s*Drop-off\s*:\s*([^|]+)\|\s*([^\|\n]+)#")),
                            'DropoffLocation' => re(2),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#^(.*?)\s+Booking\s+Reference#");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return node(".//img[contains(@src, '/logos/')]/following::tr[1]");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car reservation under\s*:\s*([^\n]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $cost = re("#\n\s*Total trip cost[\s:]+([^\n]+)#", $this->text());

                        if (re("#pts#i", $cost)) {
                            return [
                                "SpentAwards" => $cost,
                            ];
                        } else {
                            return total($cost);
                        }
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
