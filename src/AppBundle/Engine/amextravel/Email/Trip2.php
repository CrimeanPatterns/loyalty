<?php

namespace AwardWallet\Engine\amextravel\Email;

class Trip2 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Agency: American Express Global Business Travel', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]amextravel#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]amextravel#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "06.07.2016, 10:16";
    public $crDate = "06.07.2016, 09:48";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!$text) {
                        $text = $this->setDocument('plain');
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Record\s+locator:\s+(\w+)#');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re('#Pick-up:\s+.*\s+(.*)#');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('at', ',', re('#Pick-up:\s+(.*)#')));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re('#Drop-off:\s+.*\s+(.*)#');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('at', ',', re('#Drop-off:\s+(.*)#')));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re('#Pick-up:\s+.*\s+.*\s+Hours\s+of\s+operation:\s+(.*)#');
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return re('#Drop-off:\s+.*\s+.*\s+Hours\s+of\s+operation:\s+(.*)#');
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re('#Car Rental in:\s+.*\s+(.*)#');
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re('#Intermediate#');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Traveler:\s+(.*)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Approximate price including taxes:\s+(.*)#'));
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
