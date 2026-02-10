<?php

namespace AwardWallet\Engine\asiana\Email;

use PlancakeEmailParser;

class It2240491 extends \TAccountCheckerExtended
{
    public $mailFiles = "asiana/it-1.eml, asiana/it-2.eml, asiana/it-2240491.eml, asiana/it-3.eml";

    private $subjects = [
        'Asiana Airlines Reservation Itinerary Information',
    ];

    private $detects = [
        'Passenger Itinerary',
    ];

    private $from = '/[@\.]flyasiana\.com/i';

    private $prov = 'flyasiana';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Reservation No (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = cell('Passenger Name', +1);
                        $ppl = preg_split('/,/', $info);

                        return nice($ppl);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departure')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('\d+[.] (\w+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return reni('Departure .*? \( (\w+) \) ');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('Departure .+? (\d+ \w+ \d{4})');
                            $date = totime($date);
                            $time = uberTime(1);
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return reni('Arrival .*? \( (\w+) \) ');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('Arrival .+? (\d+ \w+ \d{4})');
                            $date = totime($date);
                            $time = uberTime(2);
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+:\d+ ([A-Z]+)');

                            return re("/$q/u");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('Departure .+? \b(\w)\b');
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }
}
