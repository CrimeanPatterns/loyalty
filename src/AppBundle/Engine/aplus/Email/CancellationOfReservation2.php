<?php

namespace AwardWallet\Engine\aplus\Email;

class CancellationOfReservation2 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?aplus#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#accor\.reservation\.transmission@accor\.com#i";
    public $reProvider = "#accor\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "aplus/it-2019003.eml";
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
                        return re('#Reservation\s+number\s*:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'Due\s+to\s+a\s+technical\s+issue,\s+your\s+reservation\s+made\s+on\s+';
                        $regex .= '(.*)\s+for\s+the\s+(.*)\s+hotel\s+';
                        $regex .= '(could\s+not\s+be\s+processed\s+and\s+such\s+is\s+not\s+confirmed)\.#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'ReservationDate' => strtotime(str_replace('at', ',', $m[1])),
                                'HotelName'       => preg_replace('#\s*\(.*#', '', $m[2]),
                                'Status'          => $m[3],
                                'Cancelled'       => true,
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
