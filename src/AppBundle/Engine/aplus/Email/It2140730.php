<?php

namespace AwardWallet\Engine\aplus\Email;

class It2140730 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@accor[.]net#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ACCOR\s+HOTELS#i";
    public $reProvider = "#[@.]accor[.]net#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "aplus/it-2140730.eml";
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
                        return re_white('Confirmation number :	([\w-]+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							of cancellation \.
							(?P<HotelName> .+?)
							Tel : (?P<Phone> .+?) \|
							(?P<Address> .+?)
							Access
						');

                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = uberDate(1);

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = uberDate(2);

                        return strtotime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = between('Reservation made in the name of', 'Confirmation number');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) adult');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) child');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between('Cancellation delay', 'Check in Policy');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Access
							Room \d+ \| (?P<RoomType> .+?) and (?P<RoomTypeDescription> .+?)
							Rate
						');

                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total with taxes  (\d+[.]\d+ \w+)');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Your reservation is now complete')) {
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
