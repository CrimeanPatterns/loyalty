<?php

namespace AwardWallet\Engine\agoda\Email;

class ConfirmationForBooking2 extends \TAccountCheckerExtended
{
    public $rePlain = "#Your\s+booking\s+with\s+Agoda#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#cs@agoda\.com#i";
    public $reProvider = "#agoda\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "agoda/it-2186917.eml";
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
                        return cell('AGODA Booking ID:', +1);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell('Hotel name:', +1);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Arrival Date:', +1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Departure Date:', +1));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $a = cell('Address:', +1);
                        $c = cell('City / Country:', +1);

                        if ($a and $c) {
                            return $a . ', ' . $c;
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell('Lead Guest:', +1);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('No. of Adults:', +1);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return cell('Number of Children:', +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('No. of Rooms:', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $str = node('//*[normalize-space(.) = "Cancellation Policy"]/following-sibling::ul');

                        if (!empty($str)) {
                            return substr($str, 0, 1000);
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Type:', +1);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Total Charge', +1), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+booking\s+with\s+Agoda\s+is\s+(confirmed)#');
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
