<?php

namespace AwardWallet\Engine\carlson\Email;

class It2768678 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]carlsonhotels[.]#i', 'blank', ''],
    ];
    public $reHtml = [
        ['#[@.]carlsonhotels[.]#', 'blank', ''],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]carlsonhotels[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "04.06.2015, 10:13";
    public $crDate = "04.06.2015, 09:38";
    public $xPath = "";
    public $mailFiles = "carlson/it-2768678.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = $this->parser->getHeader('date');

                    if (strtotime($date) < strtotime('29 May 2015')) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Your confirmation number is (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'your upcoming stay')]/following::a[1]");

                        return $name;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Arrival Date:', +1);
                        $time = cell('Check- In:', +1);

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Departure Date:', +1);
                        $time = cell('Check- Out:', +1);

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(text(), 'your upcoming stay')]/following::a[1]/following::font[1]");
                        $q = white("
							(?P<Address> .+?)
							(?P<Phone> [+\s-\d]{9,})
						");
                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $first = cell('First Name:', +1);
                        $last = cell('Last Name:', +1);

                        return nice("$first $last");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('No\. of Adults : (\d+)');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return reni('No\. of Children : (\d+)');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $s = cell('Your Room:', +1);

                        return nice($s);
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
