<?php

namespace AwardWallet\Engine\choice\Email;

class ReservationConfirmationV3 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?choice#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#choicehotels.com#i";
    public $reProvider = "#choicehotels.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "choice/it-2031668.eml";
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
                        return cell('Confirmation Number:', +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= '(\w+\s+\d+,\s+\d+)\s+-\s+';
                        $regex .= '\w+,\s+(\w+\s+\d+,\s+\d+)\s+';
                        $regex .= '(.*)\s+((?s).*?)\s+Phone:\s*(.*)\s+';
                        $regex .= 'Map\s+and\s+Directions';
                        $regex .= '#i';

                        if (preg_match($regex, str_replace('CN191', '', $text), $m)) {
                            return [
                                'CheckInDate'  => strtotime($m[1] . ', ' . re('#Check\s+In\s+Time:\s+(.*)#i')),
                                'CheckOutDate' => strtotime($m[2] . ', ' . re('#Check\s+Out\s+Time:\s+(.*)#i')),
                                'HotelName'    => $m[3],
                                'Address'      => nice($m[4]),
                                'Phone'        => $m[5],
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [cell('Name:', +1)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Guests:\s+(\d+)\s+Adults\s+(\d+)\s+Children#i', $text, $m)) {
                            return [
                                'Guests' => $m[1],
                                'Kids'   => $m[2],
                            ];
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Non-Smoking\s+Rooms:\s+(\d+)\s+Smoking\s+Rooms:\s+(\d+)#i', $text, $m)) {
                            return $m[1] + $m[2];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#(Cancellation\s+Deadline:\s+If.*)\s+Reservations\s+may\s+be#s'));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Estimated\s+Taxes:\s+(.*)#'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Estimated\s+Total\s*:\s+(.*)#'), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return cell('Reservation Status:', +1);
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
