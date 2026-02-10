<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1736595 extends \TAccountCheckerExtended
{
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amadeus#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "#Amadeus#i";
    public $rePDFRange = "";
    public $pdfRequired = "1";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("application/pdf", "text");

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation\s+n\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Arrival Date\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Departure Date\s*:\s*([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel\s*:\s*[^\n]+\s+([^\n]+)#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return ure("#\n\s*Tel\s*:\s*([^\n]+)#", 2);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guest's Name\s*:\s*([^\n]+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Room\(s\)\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(You have unti[^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type\s*:\s*([^\n]+)#");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*City Tax\s*:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Price incl Taxes[\s:]+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#we are pleased to (\w+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Date\s*:\s*([^\n]+)#"));
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
        return true;
    }
}
