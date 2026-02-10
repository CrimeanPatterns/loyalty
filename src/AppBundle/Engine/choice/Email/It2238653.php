<?php

namespace AwardWallet\Engine\choice\Email;

class It2238653 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]choicehotels[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]choicehotels[.]com#i";
    public $reProvider = "#[@.]choicehotels[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "choice/it-2238653.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = nice(node('//a[contains(@class, "hotelname")]'));
                        $addr = between("$name", 'Phone:');
                        $addr = clear('/^\s*\(.*?\)\s*/', $addr);

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date1 = uberDate(1);
                        $days = re_white('Length of Stay: (\d+) Nights');

                        $time1 = uberTime(1);
                        $time2 = uberTime(2);

                        $date1 = totime($date1);
                        $date2 = strtotime("+$days days", $date1);
                        $dt1 = strtotime($time1, $date1);
                        $dt2 = strtotime($time2, $date2);

                        return [
                            'CheckInDate'  => $dt1,
                            'CheckOutDate' => $dt2,
                        ];
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $tel = re_white('Phone: ([(\d) -]+)');

                        return nice($tel);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = cell('Name:', +1);

                        return [nice($name)];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re_white('Number of Rooms: (\d+)');
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
