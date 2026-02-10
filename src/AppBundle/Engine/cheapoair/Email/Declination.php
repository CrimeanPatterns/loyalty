<?php

namespace AwardWallet\Engine\cheapoair\Email;

class Declination extends \TAccountCheckerExtended
{
    public $reFrom = "#cheapoair@cheapoair\.com#i";
    public $reProvider = "#cheapoair\.com#i";
    public $rePlain = "#CheapOair\s+Booking\s+Alert\s+-\s+Your\s+booking.*?has\s+been\s+Declined#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "cheapoair/it-1631257.eml";
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
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $regex = '#unable\s+to\s+complete\s+your\s+flight\s+booking\s+number\s+([\w\-]+)\s+because#';

                        return re($regex);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Dear\s+([^,]+),#')];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return ['Status' => 'Declined', 'Cancelled' => true];
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
