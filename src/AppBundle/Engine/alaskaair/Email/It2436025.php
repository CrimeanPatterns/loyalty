<?php

namespace AwardWallet\Engine\alaskaair\Email;

class It2436025 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?alaskaair#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#alaskaair#i', 'us', ''],
    ];
    public $reProvider = [
        ['#alaskaair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.02.2015, 17:01";
    public $crDate = "03.02.2015, 16:45";
    public $xPath = "";
    public $mailFiles = "alaskaair/it-2436025.eml";
    public $re_catcher = "#.*?#";
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
                        return re("#\n\s*Hotel confirmation number\s*:\s*([A-Z\d\-]+)#ix");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-in\s*:\s*([^\n]+)#ix"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-out\s*:\s*([^\n]+)#ix"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = text(xpath("//img[contains(@src, 'icon_hotels')]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>5][1]"));

                        return [
                            'HotelName' => re("#^([^\n]+)\s+(.*?)\s+Telephone\s*:\s*([\d+\(\) \-]+)#is", $addr),
                            'Phone'     => re(3),
                            'Address'   => nice(re(2)),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Name on reservation\s*:\s*([^\n]+)#ix");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of guests\s*:\s*(\d+)#ix");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Cancellation policy')]/ancestor-or-self::p[1]", null, true, "#Cancellation\s+policy[:\s]+(.+)#si");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Description\s+([^\n]+)#ix");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes and fees\s*:\s*[A-Z]{3}\s*([\d,.]+)#ix"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total amount charged to your card\s*:\s*([A-Z]{3})\s*([\d,.]+)#ix", $text, 2));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re(1);
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
