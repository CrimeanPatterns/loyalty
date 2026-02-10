<?php

namespace AwardWallet\Engine\advrent\Email;

class ConfirmationOfReservationCancellation extends \TAccountCheckerExtended
{
    public $rePlain = "#This e-mail is confirmation that reservation [\w\-]+ has been cancelled.\s+Thank you for using Advantage#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#WebReservations@Advantage\.com#i";
    public $reProvider = "#Advantage\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "advrent/it-1960378.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        $regex = '#This e-mail is confirmation that reservation ([\w\-]+) has been (cancelled).\s+Thank you for using Advantage#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Number'    => $m[1],
                                'Status'    => $m[2],
                                'Cancelled' => true,
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
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
