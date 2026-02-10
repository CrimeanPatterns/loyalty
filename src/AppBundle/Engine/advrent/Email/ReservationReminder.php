<?php

namespace AwardWallet\Engine\advrent\Email;

class ReservationReminder extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?advrent#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Advantage Rent a Car Reservation Reminder#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Reminders@Advantage\.com#i";
    public $reProvider = "#Advantage\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "advrent/it-2016712.eml";
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
                        return re('#Confirmation\s+Code:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $location = re('#(.*)\s*».*#i', cell('Location and Rental Information'));

                        return [
                            'PickupLocation'  => $location,
                            'DropoffLocation' => $location,
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick Up', 'Dropoff' => 'Return'] as $key => $value) {
                            $res[$key . 'Datetime'] = strtotime(str_replace('at', ',', re('#' . $value . '\s+Date:\s+(.*)#i')));
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re('#Vehicle\s+Type:\s+(.*)#');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#reservation\s+that\s+you\s+made\s+on\s+(\d+/\d+/\d+)#'));
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
