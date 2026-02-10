<?php

namespace AwardWallet\Engine\choice\Email;

class ReservationCancellation2 extends \TAccountCheckerExtended
{
    public $rePlain = "#Cancellation Number:.*?Looking forward to your next Choice Hotels Stay!#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#yourstay@choicehotels\.com#i";
    public $reProvider = "#choicehotels\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "choice/it-1942108.eml";
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
                        return re('#Reservation\s+Confirmation\s+Number:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#not be staying with us on\s+(.*?) to (.*?)\.#i', $text, $m)) {
                            return [
                                'CheckInDate'  => strtotime($m[1]),
                                'CheckOutDate' => strtotime($m[2]),
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('#We\'re sorry that you will not be staying with us on#')) {
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
