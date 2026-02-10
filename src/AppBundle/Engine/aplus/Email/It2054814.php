<?php

namespace AwardWallet\Engine\aplus\Email;

class It2054814 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?accorhotels#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#accor\s*hotels#i";
    public $reProvider = "#accorhotels#i";
    public $caseReference = "6993";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "aplus/it-2054814.eml";
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
                            'ConfirmationNumber' => re("#your\s+reservation\s+([A-Z\d\-]+)\s+made\s+on\s+(.*?)\s+at\s+(.*?)\s+for\s+the\s+(.*?)\s+hotel\s+could\s+not#ims"),
                            'CheckInDate'        => totime(re(2) . ',' . re(3)),
                            'CheckOutDate'       => MISSING_DATE,
                            'HotelName'          => nice(re(4)),
                            'Address'            => nice(re(4)),
                        ];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#could\s+not\s+be\s+processed#") ? 'Cancelled' : null;
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#could\s+not\s+be\s+processed#") ? true : false;
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
