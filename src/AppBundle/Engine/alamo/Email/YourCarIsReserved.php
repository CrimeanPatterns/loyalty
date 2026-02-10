<?php

namespace AwardWallet\Engine\alamo\Email;

class YourCarIsReserved extends \TAccountCheckerExtended
{
    public $rePlain = "#Your\s+ALAMO\s+Car\s+is\s+Reserved!#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#rentals@discounthawaiicarrental\.com#i";
    public $reProvider = "#discounthawaiicarrental\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "alamo/it-2264699.eml";
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
                        return re('#Confirmation\s*\#\s*([\w\-]+)#i');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return cell('Pick-up Location', +1);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Pick-up Date & Time', +1));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return cell('Drop-off Location', +1);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Drop-off Date & Time', +1));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+reservation\s+is\s+with\s+(.*?)\s+-#');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re('#YOUR\s+RESERVATION\s+DETAILS\s+(.*\s+or\s+similar)#');
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node('//img[contains(@src, "images/fleet/cars/")]/@src');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[contains(., "First Name") and contains(., "Last Name") and not(.//tr)]/following-sibling::tr[1]');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Total Estimated Rental Amount', +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Your.*?Car\s+is\s+(.*?)!#');
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
