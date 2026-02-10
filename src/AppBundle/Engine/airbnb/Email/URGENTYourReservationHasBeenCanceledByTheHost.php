<?php

namespace AwardWallet\Engine\airbnb\Email;

class URGENTYourReservationHasBeenCanceledByTheHost extends \TAccountCheckerExtended
{
    public $rePlain = "#We.ve\s+been\s+informed\s+that\s+your\s+accommodation.*?is no longer available.*?Regards,\s*(<.*>\s*)?The\s+Airbnb\s+Team#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#urgent@airbnb\.com#i";
    public $reProvider = "#airbnb\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "airbnb/it-1965577.eml";
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
                        $regex = '#We.ve\s+been\s+informed\s+that\s+your\s+accommodation\s+starting\s+on\s+(.*)\s+\(reservation\s+([\w\-]+)\)\s+is\s+(no\s+longer\s+available)#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'CheckInDate'        => strtotime($m[1]),
                                'ConfirmationNumber' => $m[2],
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
