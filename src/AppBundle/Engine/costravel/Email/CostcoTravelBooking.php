<?php

namespace AwardWallet\Engine\costravel\Email;

class CostcoTravelBooking extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank\s+you\s+for\s+booking\s+your\s+.*?\s+with\s+Costco\s+Travel#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#costravel#i', 'us', ''],
    ];
    public $reProvider = [
        ['#costravel#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "9062";
    public $upDate = "16.01.2015, 10:38";
    public $crDate = "16.01.2015, 09:52";
    public $xPath = "";
    public $mailFiles = "costravel/it-2332677.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'simpletable');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number\s*:\s+(\d+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $nodes = xpath('//tr[contains(., "Passenger(s)")]/following-sibling::tr[1][contains(., "First")]/following-sibling::tr[position() < 20]');
                        $passengers = [];

                        foreach ($nodes as $n) {
                            $s = nice($n->nodeValue);

                            if (re('#^Itinerary$#i', $s)) {
                                break;
                            } else {
                                $p = '';

                                foreach (nodes('./td', $n) as $nn) {
                                    if (re('#^\s*\d+\s*$#i', $nn)) {
                                        break;
                                    }

                                    if ($p) {
                                        $p .= ' ';
                                    }
                                    $p .= $nn;
                                }
                                $passengers[] = nice($p);
                            }
                        }

                        return $passengers;
                    },

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        return node('//td[contains(., "Ship Name:")]/following-sibling::td[string-length(normalize-space(.)) > 1][1]');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#TOTAL\s+PRICE\s*:\s+(.*)#i'));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_CRUISE;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Date") and contains(., "Port of Call") and contains(., "Arrival Time")]/following-sibling::tr[following-sibling::tr[contains(., "Special Req")] and contains(., ":")]');
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $n = nice(nodes('./td[string-length(normalize-space(.)) > 1]'));

                            if (count($n) == 4) {
                                return [
                                    'DepName' => $n[1],
                                    'DepDate' => stripos($n[3], '--') === false ? strtotime($n[0] . ', ' . $n[3]) : null,
                                    'ArrDate' => stripos($n[2], '--') === false ? strtotime($n[0] . ', ' . $n[2]) : null,
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
