<?php

namespace AwardWallet\Engine\fairmont\Email;

class RestaurantReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank you for choosing to dine with us.*?Fairmont\s+Hotels\s+&Resorts#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#KYV@hotelstay\.fairmont\.com#i";
    public $reProvider = "#hotelstay\.fairmont\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "fairmont/it-1944739.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+\#\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return re('#Location\s+(.*)#');
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $d = str_replace(',', '', re('#Date\s+\w+,\s+(.*)#i'));
                        $t = re('#Time\s+(.*)#i');

                        if ($d and $t) {
                            return strtotime($d . ', ' . $t);
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re('#Address\s+(.*)#');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Phone\s*\#\s+(.*)#');
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+Name\s+(.*)#i');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Party\s+Size\s+\(A,\s*C\)\s+(\d+),\s+(\d+)#i', $text, $m)) {
                            return $m[1] + $m[2];
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#Loyalty\s+Program\s+\#\s+([\w\-]+)\s+Location#i');
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
}
