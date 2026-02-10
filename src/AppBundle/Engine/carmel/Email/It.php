<?php

namespace AwardWallet\Engine\carmel\Email;

class It extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@carmellimo#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@carmellimo#i";
    public $reProvider = "#@carmellimo#i";
    public $xPath = "";
    public $mailFiles = "carmel/it-1828685.eml, carmel/it.eml";
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
                        //return re("#Confirmation number is:\s*([A-Z\d\-.]+)#");
                        return re("#Trip\s*Itinerary\s*\#?\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return cell('Pick Up:', +1);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = cell('Date & Time:', +1);

                        return totime(uberDateTime($dt));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return cell('Drop Off:', +1);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return MISSING_DATE;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return cell('Car Type:', +1);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return cell('Passenger Name:', +1);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#^([^\d]*?[\d.,]+)#", cell("Basic Fare", +1, 0)));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Reward Program:', +1);

                        return re('/\s*(.+)\s+\w+\b\s*will\s*be/is', $x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#Your\s*Confirmation\s*number\s*is#i")) {
                            return 'confirmed';
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
