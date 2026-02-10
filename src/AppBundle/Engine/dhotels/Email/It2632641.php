<?php

namespace AwardWallet\Engine\dhotels\Email;

class It2632641 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Destination Delivers program#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]destinationhotels[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.04.2015, 11:19";
    public $crDate = "30.04.2015, 11:07";
    public $xPath = "";
    public $mailFiles = "dhotels/it-2632641.eml";
    public $re_catcher = "#.*?#";
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
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Reservation : (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $q = white('Sincerely ,
							(?P<HotelName> .+?) Reservations
							(?P<Address> .+?) Reservations Toll Free
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Arrive : (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Depart : (.+? \d{4})');

                        return totime($date);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Hotel Direct Toll Free ([\d-]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Dear (.+?) ,');

                        return [$name];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('Deposit & Cancellation Policy
							(.+?)
						Stay Connected');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew(' Cancellation Information')) {
                            return 'cancelled';
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (rew('Cancellation Information')) {
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
