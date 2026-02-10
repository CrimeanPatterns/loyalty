<?php

namespace AwardWallet\Engine\expedia\Email;

class It2145094 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?expedia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "expedia/it-2145094.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//text()[normalize-space(.) = 'Car']/ancestor::table[1]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+confirmation code\s*:\s*([A-Z\d-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pick-up\s*:\s*\d+/\d+/\d+\s+([^\n]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#\n\s*Pick-up\s*:\s*([^\n]+)#"), '.'));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Drop-off\s*:\s*\d+/\d+/\d+\s+([^\n]+)#");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#\n\s*Drop-off\s*:\s*([^\n]+)#"), '.'));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return [
                            'PickupHours'  => re("#\n\s*Hours of Operation[:\s]+(\d+/\d+/\d+[:\s]+\d+:\d+\s*\-\s*\d+:\d+)\s+(\d+/\d+/\d+[:\s]+\d+:\d+\s*\-\s*\d+:\d+)#ix"),
                            'DropoffHours' => re(2),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RentalCompany' => re("#\n\s*Car\s+([^\n]+)\s+(.*?)\s*/\s*([\d\-\+ \(\)]+)#"),
                            'PickupPhone'   => re(2),
                            'DropoffPhone'  => re(3),
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return node(".//text()[contains(normalize-space(.), 'Drop-off')]/ancestor::tr[2]/following-sibling::tr[1]");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Main contact\s*:\s*([^\n]+)#ix", $this->text());
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#As of (\d+/\d+/\d+), all the details are confirmed#ix", $this->text()), '.'));
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
