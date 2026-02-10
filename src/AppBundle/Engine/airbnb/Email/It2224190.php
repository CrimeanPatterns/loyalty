<?php

namespace AwardWallet\Engine\airbnb\Email;

class It2224190 extends \TAccountCheckerExtended
{
    public $reBody = "The Airbnb Team";
    public $mailFiles = "airbnb/it-2224190.eml, airbnb/it-2224247.eml";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false;
    }

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
                        return CONFNO_UNKNOWN;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = re_white("reservation to stay at '(.+?)' with");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('during (.+? \d{4})');
                        $date = clear('/-\s*\d+/', $date);

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('during (.+? \d{4})');
                        $date = clear('/\d+\s*-/', $date);

                        return totime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re_white('The address is (.+?)[.]');

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $tel = re_white('([+\d ]+)[.] The address');

                        return nice($tel);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (.+?),');

                        return [nice($name)];
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
