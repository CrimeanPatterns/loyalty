<?php

namespace AwardWallet\Engine\airbnb\Email;

class It2002355 extends \TAccountCheckerExtended
{
    public $reBody = "Thanks for using Airbnb";

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
                    if (strlen(nice($text)) !== 0) {
                        return;
                    }
                    $text = $this->setDocument('plain');

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re_white('Confirmation Code: (\w+)');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        // bad regex, but no options
                        $q = white('
							(?: \d+ Guests )
							\n
							(?P<name> .+? )
							\n
							(?: .+? )
							\n
							(?P<addr> .+? )
							\n
						');

                        if (preg_match("/$q/isu", $text, $ms)) {
                            return [
                                'HotelName' => nice($ms['name']),
                                'Address'   => nice($ms['addr']),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Check In (\w+ \d+, \d+)');

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Check Out (\w+ \d+, \d+)');

                        return strtotime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Hi (.+?),');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) Guests');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between('Cancellation Policy', 'Security Deposit');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total: (.\d+)');

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
}
