<?php

namespace AwardWallet\Engine\cheapoair\Email;

class BookingReceipt2 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?cheapoair#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#cheapoair#i";
    public $reProvider = "#cheapoair#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cheapoair/it-2214505.eml, cheapoair/it-2239755.eml";
    public $pdfRequired = "0";

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
                        return re('#Reservation\s+ID\s*:\s+([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = nodes('//tr[contains(., "Traveler Name") and contains(., "Ticket No") and not(.//tr)]/following-sibling::tr[not(contains(., "Meal Request"))]/td[1]');

                        foreach ($passengers as &$p) {
                            $p = preg_replace('#\s*\(Adult\)#i', '', $p);
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Flight Total:', +1));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Booked\s+On\s*:\s+(\d+/\d+/\d+)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Flight") and contains(., "Depart") and contains(., "Arrive") and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 1 and not(contains(., "Flight Duration"))]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(.*)\s*Flight\s+(\d+)#i', node('./td[2]'), $m)) {
                                return [
                                    'FlightNumber' => $m[2],
                                    'AirlineName'  => nice($m[1]),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $res = null;

                            foreach (['Dep' => 3, 'Arr' => 4] as $key => $value) {
                                $r = '#(\w{3})\s+-\s+(.*?)(\d+:\d+[ap]m)\s*-\s*(\d+)(\w+)#i';

                                if (preg_match($r, node('./td[' . $value . ']'), $m)) {
                                    $res[$key . 'Code'] = $m[1];
                                    $res[$key . 'Name'] = nice($m[2]);
                                    $res[$key . 'Date'] = strtotime($m[4] . ' ' . $m[5] . ' ' . $year . ', ' . $m[3]);
                                }
                            }

                            return $res;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(Nonstop)\s+(.*)\s+(\d+hr\s+\d+min)#i', node('./td[5]'), $m)) {
                                return [
                                    'Stops'    => strtolower($m[1]) == 'nonstop' ? 0 : null,
                                    'Cabin'    => $m[2],
                                    'Duration' => $m[3],
                                ];
                            }
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
