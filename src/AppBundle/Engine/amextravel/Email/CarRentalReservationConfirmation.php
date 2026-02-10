<?php

namespace AwardWallet\Engine\amextravel\Email;

class CarRentalReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amextravel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#American\s+Express\s+Centurion\s+Concierge#i";
    public $reProvider = "#amextravel#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2143463.eml";
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
                        return re('#Confirmation\s+number:\s+([\w\-]+)#i');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Pick-up\s+location:\s+(.*?)\s+Drop-off#s'), ',');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('@', '', re('#Pick-up\s+date/time:\s+(.*)#')));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Drop-off\s+location:\s+(.*?)\s+Pick-up#s'), ',');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = strtotime(str_replace('@', '', re('#Drop-off\s+date/time:\s+(.*)#')));

                        if (!$date) {
                            return MISSING_DATE;
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Main\s+passenger\'s\s+name:\s+(.*)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+price:\s+(.*)#'));
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
