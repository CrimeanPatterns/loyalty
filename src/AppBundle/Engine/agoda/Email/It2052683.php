<?php

namespace AwardWallet\Engine\agoda\Email;

class It2052683 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]agoda#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "he";
    public $typesCount = "1";
    public $reFrom = "#[@.]agoda#i";
    public $reProvider = "#[@.]agoda#i";
    public $caseReference = "7049";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "agoda/it-2052683.eml, agoda/it-2052685.eml, agoda/it-2053795.eml, agoda/it-2053796.eml, agoda/it-2053797.eml";
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
                        return re("#מספר הזמנה (\d{5,})\.#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#הזמנתך ב-(.*?) אושרה. מספר האישור הנו (\d+).#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*כניסה\s*\(צ'ק-אין\):\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#עזיבה\s*\(צ'ק-אאוט\):\s*([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*כתובת בית המלון\s+([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return niceName(re("#\n\s*שם האורח בכניסה\s*\(\צ'ק-אין\):\s*([^\n]+)#"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+) מבוגרים#");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+) מיטות נוספות#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+) חדרים, \d+ מיטות נוספות#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*מדיניות ביטולים\s+([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#סוג החדר ([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return trim(re("# ([^\s]+) מספר האישור הנו \d+.#"), " .");
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
        return ["he"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
