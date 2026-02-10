<?php

namespace AwardWallet\Engine\choice\Email;

class ReservationCancellation extends \TAccountCheckerExtended
{
    public $rePlain = "#Your\s+cancellation\s+number\s+is.*?Choice\s+Hotels\s+International#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ihelp@choicehotels\.com#i";
    public $reProvider = "#choicehotels\.com#i";
    public $xPath = "";
    public $mailFiles = "choice/it-1899793.eml";
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
                        return re('#for\s+confirmation\s+number\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $subj = implode("\n", nodes('//td[contains(., "Map/") and not(.//td)]//text()'));
                        $regex = '#';
                        $regex .= '\s*(.*)\s+\(.*\)';
                        $regex .= '((?s).*)';
                        $regex .= 'Phone:\s+(.*)';
                        $regex .= '#i';

                        if (preg_match($regex, $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2]),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $regex = '#will\s+not\s+be\s+staying\s+with\s+us\s+on\s+\w+,\s+(.*)\s+to\s+\w+,\s+(.*?)\.#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'CheckInDate'  => strtotime($m[1]),
                                'CheckOutDate' => strtotime($m[2]),
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Your\s+cancellation\s+number\s+is#i', $text)) {
                            return [
                                'Status'    => 'Cancelled',
                                'Cancelled' => true,
                            ];
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
