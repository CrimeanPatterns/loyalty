<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class HotelReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?cheaptickets#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#cheaptickets#i";
    public $reProvider = "#cheaptickets#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cheaptickets/it-2122513.eml";
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
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#record\s+locator\s*:\s+([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotel\s+name:\s+(.*)#');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(preg_replace('#[\(\)]#i', '', re('#Check-in\s+date:\s+(.*)#')));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(preg_replace('#[\(\)]#i', '', re('#Check-out\s+date:\s+(.*)#')));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Address\s*:\s+(.*?)\s+Phone#is'));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Phone\s+number\s*:\s+(.*)#i');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Total\s+number\s+of\s+guests\s*:\s+(\d+)#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        if (re('#Total\s+number\s+of\s+rooms\s*:\s+(\d+)\s+rooms?\s+(.*?)\s+Special#s')) {
                            return [
                                'Rooms'    => re(1),
                                'RoomType' => re(2),
                            ];
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#Average\s+rate\s+per\s+night\s*:\s+(.*)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s*:\s+(.*)#is', node('//td[contains(., "Cancellation:") and not(.//td)]'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Amount\s+charged\s+to\s+your\s+credit\s+card\s*:\s+(.*)#'), 'Total');
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
