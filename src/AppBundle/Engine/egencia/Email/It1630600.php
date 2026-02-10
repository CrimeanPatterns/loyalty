<?php

namespace AwardWallet\Engine\egencia\Email;

class It1630600 extends \TAccountCheckerExtended
{
    public $reFrom = "#egencia#i";
    public $reProvider = "#egencia#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?egencia|^Egencia#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "egencia/it-1630600.eml";
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
                        return re("#confirmation number:\s*([\d\w\-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Location:\s*([^|\n]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell('Pick up:')));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Location:\s*([^|\n]+)#");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell('Drop off:')));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hours of operation:\s*([^\n]+)#");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]+)\s+confirmation number#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return clear("#:.+#ms", cell('Driver:', +1));
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#Driver:\s*([^\n]+)#", cell('Driver:'));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Car rental total[*\s]+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Car rental total[*\s]+([^\n]+)#"));
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
