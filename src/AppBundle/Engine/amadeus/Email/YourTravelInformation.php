<?php

namespace AwardWallet\Engine\amadeus\Email;

class YourTravelInformation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#mailto:emailserver2@pop3.amadeus.net#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#emailserver2@pop3.amadeus.net#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#amadeus.net#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "29.06.2016, 09:28";
    public $crDate = "29.06.2016, 09:16";
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
                        return re('#CONFIRMATION:\s+(\w+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#HOTEL\s+-\s+(.*)#');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#CHECK IN:\s+(\d+\w+)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#CHECK OUT:\s+(\d+\w+)#i'));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#ADDR:\s+(.*)\s+TELEPHONE:\s+(.*)\s+(.*)\s+FAX:\s+(.*)\s+(.*)#i', $text, $m)) {
                            $addr = nice($m[1] . ', ' . $m[3] . ', ' . $m[5]);

                            return [
                                'Address' => $addr,
                                'Phone'   => $m[2],
                                'Fax'     => $m[4],
                            ];
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(\d+)\s+GUEST\s+-\s+(.*)#', $text, $m)) {
                            return [
                                'Guests'   => $m[1],
                                'RoomType' => $m[2],
                            ];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#CANCELLATION\s+POLICY:\s+(.*?)\s+TAX:#s'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#(.*)\s+TOTAL\s+RATE#'), 'Total');
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
