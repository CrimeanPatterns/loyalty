<?php

namespace AwardWallet\Engine\cheapoair\Email;

class It2082648 extends \TAccountCheckerExtended
{
    public $rePlain = "#booking\s+with\s+CheapOair[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@cheapoair[.]com#i";
    public $reProvider = "#[@.]cheapoair[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cheapoair/it-2082648.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re_white('booking \# (\d+)');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (.+?) ,');

                        return nice($name);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Your car booking has recently been cancelled')) {
                            return 'cancelled';
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Your car booking has recently been cancelled')) {
                            return true;
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
