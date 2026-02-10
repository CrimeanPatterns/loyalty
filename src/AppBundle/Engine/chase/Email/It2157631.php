<?php

namespace AwardWallet\Engine\chase\Email;

class It2157631 extends \TAccountCheckerExtended
{
    public $mailFiles = "chase/it-2157631.eml, chase/it-2494558.eml, chase/it-5721128.eml";

    public $reFrom = "chase.com";
    public $reSubject = [
        "en" => "Travel Reservation Center Trip ID",
    ];
    public $reBody = 'Chase';
    public $reBody2 = [
        "en"  => "Hotel Reservation",
        "en2" => "Hotel Check-In",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (node("//text()[starts-with(normalize-space(.),'YOUR TRIP AT A GLANCE')]")) {
                        return null;
                    }//go to parse YourTripDetails

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Hotel Confirmation\s*\#\s*([A-Z\d-]+)#ix"),
                            CONFNO_UNKNOWN
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel\n\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check\-In\s*([^\n]+)#ix"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check\-Out\s*([^\n]+)#ix"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Hotel\n\s*[^\n]+\s+(.*?)\s+(?:To cancel|Room Type)#ims"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Lead Traveler\s*:\s*([^\n]+)#ix");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(\d+)\s+Guest\(s\)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return implode('; ', nodes("//text()[contains(., 'ancellations')]/ancestor::li[1]"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type\s+([^\n]+)#ix");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Amount Billed to Card\s*:\s*([^\n]+)#ix"), 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Points Redeemed\s*:\s*([^\n]+)#ix");
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
