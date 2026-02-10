<?php

namespace AwardWallet\Engine\airmilesca\Email;

class It2207821 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]airmiles[.]ca#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]airmiles[.]ca#i";
    public $reProvider = "#[@.]airmiles[.]ca#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "airmilesca/it-2207821.eml";
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
                        return re_white('Hotel Confirmation Number (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(@src, 'star_img')]/ancestor::td[1]");
                        $name = node("//*[contains(@src, 'star_img')]/preceding::font[1]");
                        $name = preg_quote($name, '/');
                        $addr = clear("/$name/isu", $info);

                        return [
                            'HotelName' => nice($name),
                            'Address'   => nice($addr),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('(\w+ \d+ - \d+, \d+)');
                        $date = clear('/-\s*\d+/', $date);

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('(\w+ \d+ - \d+, \d+)');
                        $date = clear('/\d+\s*-/', $date);

                        return totime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = nodes("//*[contains(text(), 'Name:')]");
                        $names = array_map(function ($x) { return clear('/Name:/i', $x); }, $names);

                        return filter(nice($names));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) Adults');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Room 1 - (.+?) \d+ Adults');

                        return nice($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('TOTAL HOTEL STAY  (\w+ [\d.,]+)');

                        return total($x, 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re_white('TOTAL AIR MILES REWARD MILES  ([\d.,]+)');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('complete hotel booking confirmation')) {
                            return 'confirmed';
                        }
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
