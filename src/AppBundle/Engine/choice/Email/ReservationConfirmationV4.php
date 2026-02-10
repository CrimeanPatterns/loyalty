<?php

namespace AwardWallet\Engine\choice\Email;

class ReservationConfirmationV4 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Choice Hotels International, Inc. All rights reserved.', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['ihelp@choicehotels.com', 'blank', ''],
    ];
    public $reProvider = [
        ['choicehotels.com', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "05.07.2016, 13:12";
    public $crDate = "05.07.2016, 12:58";
    public $xPath = "";
    public $mailFiles = "choice/it-3990813.eml";
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
                        return re('#Your\s+Reservation\s+number\s+is\s+(\d+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('//text()[normalize-space(.)="Map and Directions"]/preceding::table[1]//tr[1]/td[1]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $d = str_replace('/', '.', cell('Check-In Date:', +1));
                        $t = cell('Check-In Time:', +1);

                        return strtotime($d . ', ' . $t);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $d = str_replace('/', '.', cell('Check-Out Date:', +1));
                        $t = cell('Check-Out Time:', +1);

                        return strtotime($d . ', ' . $t);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node('//text()[normalize-space(.)="Map and Directions"]/../../descendant::text()[normalize-space(.)][1]');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node('//text()[normalize-space(.)="Phone:"]/following::text()[normalize-space(.)][1]'),
                            node('//text()[starts-with(normalize-space(.), "Phone:")]', null, true, "#Phone:\s+(.+)#")
                        );
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell('Name:', +1);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Adults\s+(\d+)#');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#Children\s+(\d+)#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Non-Smoking Rooms:', +1) + node('//td[normalize-space(.) = "Smoking Rooms:"]/following-sibling::td[1]');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+Deadline:\s+.*#');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Estimated Total:', +1), 'Total');
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
