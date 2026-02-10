<?php

namespace AwardWallet\Engine\agoda\Email;

class It2941964 extends \TAccountCheckerExtended
{
    public $mailFiles = "agoda/it-2941964.eml";

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
                        return [
                            "ConfirmationNumber"=> re("#With\s+reference\s+to\s+your\s+booking\s+(\d+)\s+at\s+the\s+(.*?)\s+from\s+(\w+\s+\d+,\s+\d+)\s+to\s+(\w+\s+\d+,\s+\d+)#i"),
                            "HotelName"         => re(2),
                            "CheckInDate"       => strtotime(re(3)),
                            "CheckOutDate"      => strtotime(re(4)),
                            "Address"           => re(2),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return beautifulName(re("#Dear\s+(.*?),#i"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#Cancellation Policy\s*:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Original Charge on \w+\s+\d+,\s+\d+\s*:\s*([^\n]+)#i"), "Total");
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
