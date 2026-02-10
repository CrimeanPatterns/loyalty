<?php

namespace AwardWallet\Engine\ebookers\Email;

class It2159239 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]ebookers[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]ebookers[.]com#i";
    public $reProvider = "#[@.]ebookers[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "ebookers/it-2159239.eml, ebookers/it-2227863.eml";
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
                        return re_white('Hotel confirmation number:		(\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[normalize-space(text()) = 'Hotel']/following::span[1]");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Check-in: (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Check-out: (.+? \d{4})');

                        return totime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = node("//*[contains(text(), 'Phone:')]/preceding::a[1]");

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $x = re('#Phone:[ ]*([\d. \(\)+\-]{5,})#');

                        return nice($x);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $x = re('#Fax:[ ]*([\d. \(\)+\-]{5,})\s+#');

                        return nice($x);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Hotel reservations under:		(.+?) \n');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) guests');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) Room\(s\)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('(.[\d.]+) avg\/night');

                        return nice($x);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between('Cancellation:', '**Please do not');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Room description:
							(?P<RoomType> .+?) -
							(?P<RoomTypeDescription> .+?)
							Check-in:
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total trip cost	(.[\d.]+)');

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
