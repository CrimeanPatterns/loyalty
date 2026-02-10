<?php

namespace AwardWallet\Engine\agoda\Email;

class AgodaConfirmedBooking extends \TAccountCheckerExtended
{
    public $rePlain = "#Your booking at the.*?Agoda Customer Support|Din bokning på.*?Agoda kundservice#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Agoda Confirmed Booking at.*#i";
    public $langSupported = "en, sv";
    public $typesCount = "2";
    public $reFrom = "#no-reply@agoda\.com#i";
    public $reProvider = "#agoda\.com#i";
    public $xPath = "";
    public $mailFiles = "agoda/it-1568071.eml, agoda/it-1903983.eml";
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
                        return re('#(?:confirmation number is|bekräftelsenummer är)\s*([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#(?:Your booking at the|Din bokning på)\s+(.*?)\s+(?:is|är)\s+([^\.]+)#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Status'    => $m[2],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#\n\s*(?:Check\-in|Incheckningen):\s*([^\n]+)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#\n\s*(?:Check\-out|Utcheckningen):\s*([^\n]+)#i'));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:HOTEL ADDRESS|ADRESSEN PÅ HOTELLET)\s+([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [nice(re('#\n\s*(?:Guest Name on Check in|Gästens namn)\s*:\s*([^\n]+)#i'))];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#\s*(\d+)\s+(?:Adults|Vuxna)#i');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#\s*(\d+)\s+(?:Children|Barn)#i');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#\s+(\d+)\s+(?:Rooms|Rum)#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:CANCELLATION POLICY|AVBOKNINGSPOLICY)\s+([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*(?:Room Type|Typ av rum)\s+([^\n]+)#");
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "sv"];
    }
}
