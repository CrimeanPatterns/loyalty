<?php

namespace AwardWallet\Engine\aplus\Email;

class PlainText extends \TAccountCheckerExtended
{
    public $reFrom = "#Online@motel6\.com#i";
    public $reProvider = "#motel6\.com#i";
    public $rePlain = "#MOTEL\s+6\s+RESERVATION\s+CONFIRMATION#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Motel\s+6\s+Reservation\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "aplus/it-1724352.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re('#Confirmation\s+Number:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#PROPERTY\s+==+\s+(.*)\s+((?s).*?)\s+Phone:\s+(.*)\s+Fax:\s+(.*)#';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-in\s+Date:\s+\w+,\s+(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-out\s+Date:\s+\w+,\s+(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Guest\s+Name:\s+(.*)#')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Number\s+of\s+Adults:\s+(\d+)#');

                        if ($subj) {
                            return (int) $subj;
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Number\s+of\s+Rooms:\s+(\d+)#');

                        if ($subj) {
                            return (int) $subj;
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#CANCELLATION\s+POLICY\s+==+\s+(.*?)\s+==+#s'));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Description:\s+(.*)#');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Total\s+(.*)#');

                        if ($subj) {
                            return ['Total' => cost($subj), 'Currency' => currency($subj)];
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
}
