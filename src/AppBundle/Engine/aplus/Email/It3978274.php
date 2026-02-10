<?php

namespace AwardWallet\Engine\aplus\Email;

class It3978274 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Greetings from Grand Mercure Singapore Roxy!', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]aplus#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]aplus#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "29.06.2016, 08:49";
    public $crDate = "29.06.2016, 08:34";
    public $xPath = "";
    public $mailFiles = "";
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
                        return cell('Reservation Number:', +1);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('//text()[contains(., "Tel:")]/ancestor::span[contains(., "Fax:")]/preceding-sibling::b');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $d = cell('Arrival Date:', +1);
                        $t = re('#Check-In time is at (\d+) hrs#i');

                        return strtotime($d . ', ' . $t);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $d = cell('Departure Date:', +1);
                        $t = re('#Check-Out time is at (\d+) hrs#i');

                        return strtotime($d . ', ' . $t);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $s = node('//text()[contains(., "Tel:")]/ancestor::span[contains(., "Fax:")]');

                        if (preg_match('#(.*)\s+Tel:\s+(.*),\s+Fax:\s+(.*)#i', $s, $m)) {
                            return [
                                'Address' => $m[1],
                                'Phone'   => $m[2],
                                'Fax'     => $m[3],
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell('Name Of Guest:', +1);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s+adult#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $s = cell('Number Of Rooms', +1);

                        if (preg_match('#(\d+)\s+(.*)#i', $s, $m)) {
                            return [
                                'Rooms'    => $m[1],
                                'RoomType' => $m[2],
                            ];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell('Cancellation Policy', +1);
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
