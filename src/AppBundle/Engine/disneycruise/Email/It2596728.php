<?php

namespace AwardWallet\Engine\disneycruise\Email;

// TODO: move to disneyvacation

class It2596728 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?disneydestinations[.]com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]disneydestinations#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]disneydestinations#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "02.04.2015, 20:06";
    public $crDate = "02.04.2015, 18:50";
    public $xPath = "";
    public $mailFiles = "disneycruise/it-2596728.eml";
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
                        return re("#^(\d+)#", xpath("//*[contains(text(), 'Confirmation Number:')]/following::td[1]"));
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Guest(s)')]/following::tr[1]/td[2]/text()[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*(\w+,\s*\w+\s+\d+,\s*\d+)#", text(xpath("//*[contains(text(), 'Confirmation Number:')]/following::td[1]"))));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\w+,\s*\w+\s+\d+,\s*\d+\n\s*(\w+,\s*\w+\s+\d+,\s*\d+)#", text(xpath("//*[contains(text(), 'Confirmation Number:')]/following::td[1]"))));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Address' => nice(re("#Payment Information.*?\n\s*{$it['HotelName']}\s*\n\s*(.+?)\n\s*([+\d\(\)-]{5,})#s")),
                            'Phone'   => re(2),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = [];
                        re("#\n\s*Guest\s*\d+\s*:\s*([^\n\(]+)#", function ($m) use (&$names) {
                            $names[trim($m[1])] = 1;
                        }, $text);

                        return array_keys($names);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return trim(end(explode(',', node("//*[contains(text(), 'Guest(s)')]/following::tr[1]/td[last()]"))));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Cancellation Guidelines')]/following::table[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Guest(s)')]/following::tr[1]/td[2]/text()[2]");
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total Points for this Stay\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Date:", +1, 0));
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
