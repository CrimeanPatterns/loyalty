<?php

namespace AwardWallet\Engine\avis\Email;

class YourAvisReservationHasBeenCancelled extends \TAccountCheckerExtended
{
    public $rePlain = "#Your Avis Reservation has been Cancelled#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@\.]avis\.com#i";
    public $reProvider = "#[@\.]avis\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "avis/it-1968248.eml";
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
                        return re('#Upon review of your recent reservation, ([\w\-]+), it has come to our attention#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if ($status = re('#so your reservation has been (tentatively cancelled)#')) {
                            return [
                                'Status'    => $status,
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
