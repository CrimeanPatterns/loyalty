<?php

namespace AwardWallet\Engine\cheapoair\Email;

class YourBookingOnCheapOairHasBeenCancelled extends \TAccountCheckerExtended
{
    public $rePlain = "#Your\s+reservation\s+with\s+booking\s+.*\s+has\s+been\s+cancelled.*?Thank\s+you,.*?CheapOair\.com#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#cheapoair#i";
    public $reProvider = "#cheapoair#i";
    public $xPath = "";
    public $mailFiles = "cheapoair/it-1908121.eml";
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
                        if (preg_match('#Your\s+reservation\s+with\s+booking\s+\#\s+(\d+)\s+made\s+on\s+(\d+/\d+/\d+)\s+has\s+been\s+(cancelled)#i', $text, $m)) {
                            return [
                                'ConfirmationNumber' => $m[1],
                                'ReservationDate'    => strtotime($m[2]),
                                'Status'             => $m[3],
                                'Cancelled'          => true,
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
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
