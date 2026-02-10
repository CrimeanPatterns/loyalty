<?php

namespace AwardWallet\Engine\asia\Email;

class It2049555 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@cathaypacific[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@cathaypacific[.]com#i";
    public $reProvider = "#[@.]cathaypacific[.]com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "asia/it-2049555.eml";
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
                        return re_white('PNR Number: (\w+)');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (re_white('has been cancelled successfully')) {
                            return true;
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('has been cancelled successfully')) {
                            return 'cancelled';
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
