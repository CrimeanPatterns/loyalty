<?php

namespace AwardWallet\Engine\egyptair\Email;

class ItPDF extends \TAccountCheckerExtended
{
    public $reFrom = "#EgyptAir#i";
    public $reProvider = "#EgyptAir#i";
    public $mailFiles = "egyptair/it-1569570.eml";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = $parser->getAttachmentBody($pdf);

            if (($html = \PDF::convertToText($body)) !== null) {
                if (stripos($html, 'EgyptAir') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    if (!re("#egyptair#i")) {
                        return null;
                    }

                    $this->fullText = $text;

                    return [$text];
                },

                "#Flight \d#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Booking Confirmation Number:.*\s(\w+)\n#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $regexp = '#Flight \d:.*\n(.*)\n(\d+\w+)\n.*\n(.*)#';
                        $subject = re('#Special Requests(.*)Re se rv ation Office#s');
                        $passengers = [];

                        if (preg_match_all($regexp, $subject, $m)) {
                            if (empty($passengers)) {
                                $passengers[] = $m[1][0];
                            }
                            $this->seats = $m[2];
                            $this->meals = $m[3];
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        //$r .= 'travellers\s+airfare\s+taxes, fees & charges\s+.*\s+total for all travellers[^\S\n]+([\d\.]+\s\w+)';
                        $r .= 'travellers\s+airfare\s+taxes, fees & charges\s+';
                        $r .= '(\d+) [\w\(\)]+ x \(([\d\.]+)\s+\+\s+([\d\.]+)\)\s+';
                        $r .= '=\s+[\d\.]+\s\w+\s+';
                        $r .= 'total for all travellers[^\S\n]+([\d\.]+)\s(\w+)';
                        $r .= '#';

                        if (preg_match($r, $text, $m)) {
                            [$count, $fare, $tax, $total, $currency] = array_slice($m, 1);

                            return [
                                'BaseFare'    => cost($count * $fare),
                                'Tax'         => cost($count * $tax),
                                'TotalCharge' => cost($total),
                                'Currency'    => currency($currency),
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Trip status:\s+(\w+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $segments = splitter('#(Flight \d.*)#', re('#Flight Se le ction\s+(.*)\s+Flight payment and ticket#s'));

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (!isset($this->segmentIndex)) {
                                $this->segmentIndex = 0;
                            } else {
                                $this->segmentIndex++;
                            }

                            if (preg_match('#Airline:\n(.*)\s[^\d\s]+(\d+)#', $text, $m)) {
                                return ['AirlineName' => $m[1], 'FlightNumber' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $datetimeStr = re('#Flight \d\s+\w+, (\w+ \d+, \d+)#');

                            if (preg_match('#Departure:\s+(\d{2}:\d{2})\s(.*)#', $text, $m)) {
                                $datetimeStr .= ", $m[1]";
                                $res['DepName'] = $m[2];
                            }
                            $res['DepDate'] = strtotime($datetimeStr);

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $datetimeStr = re('#Flight \d\s+\w+, (\w+ \d+, \d+)#');
                            $dayShift = 0;

                            if (preg_match('#Arrival:\s+(\d{2}:\d{2})(?:\s\+(\d) day\(s\))?\n?(.*)#', $text, $m)) {
                                $datetimeStr .= ", $m[1]";

                                if (isset($m[2])) {
                                    $dayShift = (int) $m[2];
                                }
                                $res['ArrName'] = trim($m[3]);
                            }
                            $res['ArrDate'] = strtotime($datetimeStr) + $dayShift * 24 * 60 * 60;

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Aircraft:\n(.*)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#Fare type:\s(.*)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return $this->seats[$this->segmentIndex];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#Duration:\s(.*)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return $this->meals[$this->segmentIndex];
                        },
                    ],
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
