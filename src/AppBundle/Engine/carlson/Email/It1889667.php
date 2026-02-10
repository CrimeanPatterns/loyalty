<?php

namespace AwardWallet\Engine\carlson\Email;

class It1889667 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*(?:Von|From)\s*:[^\n]*?[@]radisson[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@radisson[.]com#i";
    public $reProvider = "#[@.]radisson[.]com#i";
    public $xPath = "";
    public $mailFiles = "carlson/it-1887965.eml, carlson/it-1889667.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('/Hotel\s*Confirmation\s*no:\s*(.+)/i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = re("#Thank\s*you\s*for\s*choosing\s*the\s*(.+?)\s*for\s*your\s*#i");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dates = re('/Dates:\s*(.+?)\s*Room\s*Type:/is');
                        $pat = '/(?P<day1>\d+)-(?P<day2>\d+)\s*(?P<month>\w+)\s*,\s*(?P<year>\d+)/i';

                        if (!preg_match($pat, $dates, $ms)) {
                            return;
                        }

                        $dt1 = "{$ms['day1']} {$ms['month']} {$ms['year']}";
                        $dt2 = "{$ms['day2']} {$ms['month']} {$ms['year']}";

                        $dt1 = uberDateTime($dt1);
                        $dt2 = uberDateTime($dt2);

                        $dt1 = strtotime($dt1);
                        $dt2 = strtotime($dt2);

                        return [
                            'CheckInDate'  => $dt1,
                            'CheckOutDate' => $dt2,
                        ];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#radissonblu[.]com\s*(.+?)\s*Please\s*consider\s*#is");

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $tel = re("#Tel:\s*(.+?)\s*Direct:#is");

                        return nice($tel);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $fax = re("#Fax:\s*(.+?)\s*reservations#is");

                        return nice($fax);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re('/Dear\s*(.+?),/is');

                        return [nice($name)];
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = re("#Room\s*Type:\s*(.+?)\s*Hotel\s*Confirmation\s*no:#is");

                        return nice($type);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('/We\s*are\s*delighted\s*to\s*confirm/i')) {
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
}
