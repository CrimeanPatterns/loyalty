<?php

namespace AwardWallet\Engine\fairmont\Email;

class ReservationCancellation extends \TAccountCheckerExtended
{
    public $rePlain = "#Your reservation has been cancelled successfully.*?Fairmont Hotels & Resorts#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@fairmont\.com#i";
    public $reProvider = "#fairmont\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "fairmont/it-1945614.eml";
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
                        $regex = '#Your\s+reservation\s+has\s+been\s+(cancelled)\s+successfully\.\s+Your\s+reservation\s+([\w\-]+)#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Status'             => $m[1],
                                'Cancelled'          => true,
                                'ConfirmationNumber' => $m[2],
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
