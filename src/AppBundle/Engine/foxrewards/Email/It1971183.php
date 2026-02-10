<?php

namespace AwardWallet\Engine\foxrewards\Email;

class It1971183 extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Fox\s+Rent-A-Car#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@foxrewards[.]com|fox@foxrentacar\.com#i";
    public $reProvider = "#[@.]foxrewards[.]com|foxrentacar\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "foxrewards/it-1971183.eml, foxrewards/it-1973167.eml, foxrewards/it-1980888.eml, foxrewards/it-2125607.eml";
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
                        return re_white('Confirmation Number	([\w-]+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            between('Rental Location', 'Return Location'),
                            between('Rental Pickup Location', 'Rental Dropoff Location')
                        );
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Pickup Time', 'Dropoff Time');
                        //$dt = preg_replace('/(\d+:\d+):\d+/', '', $dt);
                        return strtotime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            between('Return Location', 'Pickup Time'),
                            between('Rental Dropoff Location', 'Pickup Time')
                        );
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Dropoff Time', 'Vehicle');
                        //$dt = preg_replace('/(\d+:\d+):\d+/', '', $dt);
                        return strtotime($dt);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return between('Phone:', 'Address:');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return between('Vehicle', 'First Name');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $first = between('First Name', 'Last Name');
                        $last = between('Last Name', 'Email');

                        return nice("$first $last");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = nice(re_white('ESTIMATED TOTAL  (. [\s\d]+[.]\d+)'));

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Submitted On', 'Rental Location');
                        $dt = preg_replace('/:\d+\s+/', '', $dt);
                        $dt = strtotime($dt);

                        return $dt ? $dt : null;
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
