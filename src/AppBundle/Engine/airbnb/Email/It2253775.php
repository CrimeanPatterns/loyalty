<?php

namespace AwardWallet\Engine\airbnb\Email;

class It2253775 extends \TAccountCheckerExtended
{
    public $reBody = "Thanks for using Airbnb";
    public $mailFiles = "airbnb/it-2253775.eml";

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
                        return re_white('Confirmation \#	(\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = cell('Property', +1);

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $info = cell('Check-in', +1);
                        $dt = uberDateTime($info);

                        return totime($dt);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $info = cell('Check-out', +1);
                        $dt = uberDateTime($info);

                        return totime($dt);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = cell('Address', +1);

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Phone', +1);

                        return nice($x);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Hello (.+?),');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('Guests		(\d+)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = re_white('(.?\d+) per night');

                        return nice($rate);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $s = cell('Cancellation', +1);

                        return nice($s);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Total', +1);

                        return total($x, 'Total');
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
