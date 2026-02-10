<?php

namespace AwardWallet\Engine\avis\Email;

class It2135174 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@avis[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Avis\s+Rent\s+A\s+Car#i";
    public $reProvider = "#[@\.]avis\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "avis/it-2135174.eml";
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
                        return re_white('Confirmation Number: (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $info = between('Pick-up Location:', 'Pick-Up:');
                        $q = white('
							(?: .+?) \s+
							(?P<PickupLocation> \d+ .+?)
							(?P<PickupPhone> \( \d+ \) [\d-]+)
							(?P<PickupHours> .+)
						');

                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Pick-Up:', 'Return Location:');
                        $dt = uberDateTime($dt);

                        return strtotime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $info = between('Return Location:', 'Return:');
                        $q = white('
							(?: .+?) \s+
							(?P<DropoffLocation> \d+ .+?)
							(?P<DropoffPhone> \( \d+ \) [\d-]+)
							(?P<DropoffHours> .+)
						');

                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Return:', 'Rental Information');
                        $dt = uberDateTime($dt);

                        return strtotime($dt);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Car Selection (\w+)');

                        return nice($x);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Car Selection (?:\w+)  (.+? similar)');

                        return nice($x);
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'Car Selection')]/following::img[contains(@src, 'vehicle')] [1]/@src");

                        return nice($x);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (.+?),');

                        return nice($name);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Estimated Total: (\d+[.]\d+ \w+)');

                        return total($x);
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Tax: (\d+[.]\d+ \w+)');

                        return cost($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('your rental car is reserved')) {
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
