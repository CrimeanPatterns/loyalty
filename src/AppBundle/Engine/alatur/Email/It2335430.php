<?php

namespace AwardWallet\Engine\alatur\Email;

class It2335430 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Alatur\s+Viagens#i', 'blank', '/1'],
    ];
    public $reHtml = [
        ['#Alatur\s+Viagens#i', 'us', ''],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = "";
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.02.2015, 11:08";
    public $crDate = "16.01.2015, 07:49";
    public $xPath = "";
    public $mailFiles = "alatur/it-2335430.eml, alatur/it-2335441.eml, alatur/it-2339530.eml, alatur/it-2339728.eml, alatur/it-2356089.eml, alatur/it-2435017.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = rew('(VOUCHER DE HOTEL .+)');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmação[.]+:  (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return reni('Para:  (.+?)  Fone:');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $q = '^ ([\d ]+\/[\d ]+\/\d{4})';
                        $date = ure("/$q/imu", 1);
                        $date = clear('/\s/', $date);

                        return timestamp_from_format($date, 'd / m / Y|');
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $q = '^ ([\d ]+\/[\d ]+\/\d{4})';
                        $date = ure("/$q/imu", 2);
                        $date = clear('/\s/', $date);

                        return timestamp_from_format($date, 'd / m / Y|');
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $part1 = reni('Fone: .+? \n  (.+?)  Fax:');
                        $part2 = reni('Fax: .+? \n  (.+?)  Email:');

                        return nice("$part1. $part2");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Fone: (.+?) \n');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax: (.+?) \n');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [reni('Hóspede \d+ (.+?) \n')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('Hóspede (\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('(CANCELAMENTO .+?) \n');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Diária  Total
							(?P<Rooms> \d+)  (?P<RoomType> \w+)
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total do Voucher[.]+  (.*? [\d.,]+)');

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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
