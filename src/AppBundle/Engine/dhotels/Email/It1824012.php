<?php

namespace AwardWallet\Engine\dhotels\Email;

class It1824012 extends \TAccountCheckerExtended
{
    public $reFrom = "#@destinationhotels#i";
    public $reProvider = "#@destinationhotels#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@destinationhotels#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "dhotels/it-1824012.eml, dhotels/it-1824034.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation Number:\s*([\w-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('//*[@class = "hotelName"]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $d = cell('Arrival Date:', +1);

                        return totime(uberDateTime($d));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $d = cell('Departure Date:', +1);

                        return totime(uberDateTime($d));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = nodes('//*[@class = "hotelAddress"][position() < 3]');

                        return implode(',', $addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node('//*[@class = "hotelAddress"][3]');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = cell('Guest Name:', +1);

                        return [$name];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Occupancy:', +1);

                        if (preg_match('#(\d+)#', $x, $ms)) {
                            return intval($ms[1]);
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell('Cancellation policy', +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = cell('Room Type:', +1);

                        return re('#(.+?)[.]#', $type);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Description', +1);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Subtotal:', +1);

                        return [
                            'Cost'     => cost($x),
                            'Currency' => currency($x),
                        ];
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Total (incl Tax):', +1);

                        return cost($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $st = trim(node("//*[@class = 'txt_headline']"));

                        if (preg_match('#Confirmation#i', $st, $ms)) {
                            return 'confirmed';
                        } elseif (preg_match('#Cancellation#i', $st, $ms)) {
                            return 'cancelled';
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (re("#Cancellation Number:#")) {
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
}
