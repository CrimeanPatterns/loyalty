<?php

namespace AwardWallet\Engine\fairmont\Email;

class FairmontHotelsandResortsReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?fairmont#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Fairmont Hotels and Resorts Reservation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#GuestService@fairmont\.com|noreply@fairmont.com#i";
    public $reProvider = "#fairmont\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "fairmont/it-1877710.eml, fairmont/it-1892306.eml, fairmont/it-1942332.eml, fairmont/it-1950134.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+(?:confirmation|reservation)\s+number\s+is:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('TEL', 0, 0, '//text()');
                        $regex = '#';
                        $regex .= '\s*(.*)\s+';
                        $regex .= '((?s).*)\s+';
                        $regex .= 'TEL\s+(.*)\s+';
                        $regex .= 'FAX\s+(.*)';
                        $regex .= '#i';

                        if (preg_match($regex, $subj, $m)) {
                            $hotelName = orval($m[1], re('#For\s+more\s+information\s+on\s+(.*)\s+click#i'));
                            $address = ($m[2]) ? nice($m[2], ',') : $hotelName;

                            return [
                                'HotelName' => $hotelName,
                                'Address'   => $address,
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        } elseif (preg_match('#Cancel\s+by.*\s*\n\s*\n\s*(.*)\s*\n\s*((?s).*?)\s+Tel\s+(.*)\s+Fax\s+(.*)\s+E-mail#i', $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Arriving\s+on\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Departing\s*on\s+(.*)#i'));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s*adult#i');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s+Children#i');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Rate:\s+(.*)#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancel\s*Policy:\s+(.*)#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Type:\s+(.*)#i');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(orval(re('#Total\s+Rate:\s+(.*)\s+plus#i'), re('#Total:\s+(.*)#i')), 'Total');
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
}
