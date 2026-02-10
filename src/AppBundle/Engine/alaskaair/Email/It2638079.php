<?php

namespace AwardWallet\Engine\alaskaair\Email;

class It2638079 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]alaskaair[.]#i', 'blank', ''],
        ['#Alaska\s+Airlines#', 'blank', '-1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]alaskaair[.]#i', 'us', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "17.04.2015, 12:03";
    public $crDate = "17.04.2015, 11:32";
    public $xPath = "";
    public $mailFiles = "alaskaair/it-2638079.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[contains(text(), 'Reservation Details')]/ancestor::table[2]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return reni('Car confirmation number: (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $addr = reni('Address: (.+?) \n [\w\s-]+:');
                        $city = reni('City: (.+?) \n+ [\w\s-]+:');

                        return "$addr $city";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('(\d+ , \w+ \d{4})');
                        $date = ure("/$q/isu", 1);

                        $date = timestamp_from_format($date, 'd , M Y|');
                        $time = uberTime(1);

                        return strtotime($time, $date);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return arrayVal($it, 'PickupLocation');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('(\d+ , \w+ \d{4})');
                        $date = ure("/$q/isu", 2);

                        $date = timestamp_from_format($date, 'd , M Y|');
                        $time = uberTime(2);

                        return strtotime($time, $date);
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return reni('Rental company: (.+?) \n [\w\s-]+:');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return reni('Reservation Details (.+?) \n [\w\s-]+:');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return reni('Primary Driver: (.+?) \n [\w\s-]+:');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('in the local currency: (.+?) \n');

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Below is your booking confirmation')) {
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
