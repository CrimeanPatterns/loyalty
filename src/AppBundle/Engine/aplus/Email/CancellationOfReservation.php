<?php

namespace AwardWallet\Engine\aplus\Email;

class CancellationOfReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#this is to reconfirm the cancellation of your reservation.*?Accor Group#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#accor\.reservation\.transmission@accor\.com#i";
    public $reProvider = "#accor\.com#i";
    public $xPath = "";
    public $mailFiles = "aplus/it-1919772.eml";
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
                        $r = '#reconfirm the cancellation of your reservation\s+([\w\-]+)\s+at#i';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'ConfirmationNumber' => $m[1],
                                'Status'             => 'cancelled',
                                'Cancelled'          => true,
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= '(.*)\s*\(For\s+more\s+information.*?\)\s+';
                        $r .= '((?s).*)\s+';
                        $r .= 'Phone:\s+(.*)\s+';
                        $r .= 'Fax:\s+(.*)\s+';
                        $r .= '#i';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'HotelName' => nice($m[1]),
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Dates\s+of\s+your\s+stay:\s+from\s+(.*)\s+to\s+(.*)\.#i';

                        if (preg_match($regex, $text, $m)) {
                            $ciTimeRe = '#Check\s+in\s+Policy\s+.*\s+(\d+:\d+\s*(?:am|pm)?)#i';
                            $coTimeRe = '#Check\s+out\s+Policy\s+.*\s+(\d+:\d+\s*(?:am|pm)?)#i';

                            return [
                                'CheckInDate'  => strtotime($m[1] . ', ' . re($ciTimeRe)),
                                'CheckOutDate' => strtotime($m[2] . ', ' . re($coTimeRe)),
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#In\s+favor\s+of\s*:\s+(.*)#i')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Number\s+of\s+persons\s*:\s+(\d+)\s+adult#i', $text, $m)) {
                            return $m[1];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+policy\s+(.*)#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+type\s*:\s+(.*)#i');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+amount\s+of\s+the\s+reservation\s*:\s+(.*)#i'), 'Total');
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
