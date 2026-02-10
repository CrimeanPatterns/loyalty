<?php

namespace AwardWallet\Engine\edreams\Email;

class It2586815 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?edreams#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]edreams#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]edreams#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "1";
    public $caseReference = "";
    public $upDate = "29.03.2015, 02:09";
    public $crDate = "29.03.2015, 01:58";
    public $xPath = "";
    public $mailFiles = "edreams/it-2586815.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n\s*((?:FLIGHT|HOTEL|CAR)\s+RESERVATION)#");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+BOOKING REF\s*:\s*([A-Z\d-]+)#", $this->text());
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*LOCATION\s*:\s*([^\n]*?)(?:CHECK-IN|\s{2,})#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $anchor = re("#\s+DATE\s*:\s*(\d+\s+\w+\s+\d+)#", $this->text());

                        return correctDate(re("#\s+CHECK-IN\s*:\s*([^\n]+)#"), $anchor);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return correctDate(re("#\n\s*CHECK-OUT\s*:\s*([^\n]+)#"), $it['CheckInDate']);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $info = clear("#^[\s-]+#", re("#CHECK-OUT:[^\n]+(.*?)\n\s*GENERAL#s"));

                        return [
                            "RoomType" => re("#^([^\n]+)\s+(.*?)\n\s*REF:#s", $info),
                            "Address"  => nice(re(2), ','),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+([A-Z/]+\s+MRS?)\s*\n#", $this->text());
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(CONFIRMED|CANCEL+ED)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+DATE\s*:\s*(\d+\s+\w+\s+\d+)#", $this->text()));
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
        return true;
    }
}
