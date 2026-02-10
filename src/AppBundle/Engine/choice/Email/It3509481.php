<?php

namespace AwardWallet\Engine\choice\Email;

class It3509481 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Choice Hotels International#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]choice[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]choice[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "09.02.2016, 12:06";
    public $crDate = "09.02.2016, 11:46";
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
                        return reni('Reservation number is (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = nice(node("//*[contains(text(), 'Map and Directions')]/preceding::b[1]"));
                        $q = white("
							Your Reservation number is .+?
							$name
							(?P<Address> .+?)
							Phone : (?P<Phone> [+\s\d]+\d)
						");

                        $res = re2dict($q, $text);
                        $res['HotelName'] = $name;

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("//*[contains(text(), 'Check-In Date')]/following::td[1]");
                        $time = node("//*[contains(text(), 'Check-In Time')]/following::td[1]");

                        $dt = timestamp_from_format($date, '| d / m / Y');

                        if ($time) {
                            $dt = strtotime($time, $dt);
                        }

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("//*[contains(text(), 'Check-Out Date')]/following::td[1]");
                        $time = node("//*[contains(text(), 'Check-Out Time')]/following::td[1]");

                        $dt = timestamp_from_format($date, '| d / m / Y');

                        if ($time) {
                            $dt = strtotime($time, $dt);
                        }

                        return $dt;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Name : (\S.+?) \n');

                        return [$name];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('Adults (\d+)');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return reni('Children (\d+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $n1 = reni('Non-Smoking Rooms : (\d+)');
                        $n2 = reni('\sSmoking Rooms : (\d+)');

                        return $n1 + $n2;
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('Cancellation Policy (.+?) Guarantee Policy');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Room \# 1 :  \S.+? \n
							(?P<RoomType> \S.+?) \n
							(?P<RoomTypeDescription> \S.+?)
							Adults \d+
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Sub Total : (\S .+?) \n');

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Estimated Taxes and/or Fees : (\S .+?) \n');

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = rew('ESTIMATED TOTAL : (\S .+?) \n');

                        return total($x, 'Total');
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
