<?php

namespace AwardWallet\Engine\airbaltic\Email;

class It1879597 extends \TAccountCheckerExtended
{
    public $mailFiles = "airbaltic/it-1879597.eml, airbaltic/it-1918473.eml, airbaltic/it-7115410.eml, airbaltic/it-8451101.eml";

    private $from = "airbaltic.com";

    private $detects = [
        "Yours faithfully",
        'airBaltic wishes you a pleasant flight',
        'We wish you a pleasant flight and thank you for choosing airBaltic service',
        'Thank you for choosing airBaltic',
        'Sie müssen Ihre Bordkarte(n) an der Sicherheitskontrolle und beim Boarding vorzeigen',
    ];

    private $anchor = 'airBaltic';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_diff(array_unique(nodes("//p[contains(text(), 'Ticket No.')]/following-sibling::p[position() = 3 or position() = 4]", null, '/(\d{7,})/')), [null]);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//p[contains(text(), 'Ticket No.')]/following-sibling::p[2]");

                        return array_values(array_unique($ppl));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        // simplifying assumption is, only one flight-segment and few people
                        return xpath("//p[contains(text(), 'Ticket No.')]/ancestor::div[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = array_diff(nodes('.//p[contains(text(), "Flight no.")]/following-sibling::p[position() = 4 or position() = 5]', null, '/([A-Z\d]{2}\s*\d+)/'), [null]);

                            return uberAir(array_shift($fl));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $dep = array_diff(nodes('.//p[contains(text(), "From")]/following-sibling::p[position() = 4 or position() = 5]', null, '/(\D+\s+Terminal\s+[A-Z\d]{1,3}|\b[A-Z\s\)\(]+\b)/i'), [null]);

                            if (count($dep) > 0 && preg_match('/(\D+)\s+Terminal\s+([A-Z\d]{1,3})/', $dep[0], $m)) {
                                return [
                                    'DepName'           => $m[1],
                                    'DepartureTerminal' => $m[2],
                                ];
                            }

                            return array_shift($dep);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('.//p[contains(text(), "Date")]/following-sibling::p[5]', null, true, '/(\d{1,2}\D+\d{4})/');

                            if (empty($date)) {
                                $date = node('.//p[contains(text(), "Date")]/following-sibling::p[4]', null, true, '/(\d{1,2}\D+\d{4})/');
                            }
                            $time = node('.//p[contains(text(), "Departure time")]/following-sibling::p[5]', null, true, '/(\d{1,2}:\d{2})/');

                            if (empty($time)) {
                                $time = node('.//p[contains(text(), "Departure time")]/following-sibling::p[4]', null, true, '/(\d{1,2}:\d{2})/');
                            }
                            $dt = "$date $time";

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $arr = node('.//p[contains(text(), "To")]/following-sibling::p[position() = 5 and not(contains(., "Boarding time"))]', null, true, '/(\D+\s+Terminal\s+[A-Z\d]{1,3}|\D+)/');

                            if (empty($arr)) {
                                $arr = node('.//p[contains(text(), "To")]/following-sibling::p[2]', null, true, '/(\D+\s+Terminal\s+[A-Z\d]{1,3}|\D+)/');
                            }

                            if (preg_match('/(\D+)\s+Terminal\s+([A-Z\d]{1,3})/', $arr, $m)) {
                                return [
                                    'ArrName'         => $m[1],
                                    'ArrivalTerminal' => $m[2],
                                ];
                            }

                            return nice($arr);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $class = node('.//p[contains(text(), "Check your final gate information.") or contains(text(), "BEFORE DEPARTURE")]/following-sibling::p[1]');

                            return nice($class);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $res = nice(node('.//p[contains(text(), "Class")]/text()[2]', null, true, '/([A-Z])/'));

                            if (empty($res)) {
                                $res = node('.//p[contains(text(), "Class")]/following-sibling::p[2]', null, true, '/([A-Z])/');
                            }

                            return $res;
                        },
                    ],
                ],
                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $it[0]['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $it[0]['TripSegments'])));
                    $seats = nodes('//p[contains(text(), "Boarding time")]/following-sibling::p[4]');
                    $count = count($it[0]['TripSegments']);

                    foreach ($it[0]['TripSegments'] as $i => $tripSegment) {
                        if ($count > 1 && count($seats) > 0) {
                            $tripSegment = array_merge($tripSegment, ['Seats' => [array_shift($seats)]]);
                        } elseif ($count === 1 && count($seats) > 0) {
                            $tripSegment = array_merge($tripSegment, ['Seats' => $seats]);
                        }
                        $it[0]['TripSegments'][$i] = $tripSegment;
                    }

                    return $it;
                },
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, $this->anchor) !== false) {
                return true;
            }
        }

        return false;
    }
}
