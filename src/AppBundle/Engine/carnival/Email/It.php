<?php

namespace AwardWallet\Engine\carnival\Email;

class It extends \TAccountCheckerExtended
{
    public $mailFiles = "carnival/it-1584526.eml";

    // var $reFrom = "flyingblue@airfrance-klm.com";
    public $reSubject = [
        "en"=> "Carnival Cabin Confirmation",
    ];
    public $reBody = 'Carnival';
    public $reBody2 = [
        "en"=> "BOOKING NO:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    // public function detectEmailFromProvider($from) {
    // return strpos($from, $this->reFrom)!==false;
    // }

    public function detectEmailByHeaders(array $headers)
    {
        // if(strpos($headers["from"], $this->reFrom)===false)
        // return false;

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
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'BOOKING NO:')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[text() = 'GUEST']/ancestor::tr[1]/following-sibling::tr[position() mod 2 = 0 and not(contains(., 'Indicates temporary VIFP Level'))]//td[2]";

                        return nodes($xpath);
                    },

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'SAILING:')]/ancestor::td[1]/following-sibling::td[1]";
                        $subj = node($xpath);

                        if (preg_match('#(.*)\s+(\d+/\w+/\d+)#', $subj, $m)) {
                            if (isset($m[2])) {
                                $this->cruiseDateStr = str_replace('/', ' ', $m[2]);
                            }

                            return $m[1];
                        }
                    },

                    "CruiseName" => function ($text = '', $node = null, $it = null) {
                        return re("#ITINERARY:\s+(.*)\s+Itinerary#");
                    },

                    "RoomNumber" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'STATEROOM:')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "RoomClass" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'CATEGORY:')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[. = 'TOTAL CHARGES']/ancestor::td[1]/following-sibling::td[1]";
                        $subj = node($xpath);

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Cruise Rate')]/ancestor::td[1]/following-sibling::td[1]";

                        return cost(node($xpath));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[. = 'Taxes, Fees & Port Expenses']/ancestor::td[1]/following-sibling::td[1]";

                        return cost(node($xpath));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[normalize-space(text()) = 'DAY']/ancestor::tr[2]/following-sibling::tr[position() mod 2 = 0]//tr";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->segmentIndex)) {
                                $this->segmentIndex++;
                            } else {
                                $this->segmentIndex = 0;
                            }

                            return node('./td[3]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            if (isset($this->cruiseDateStr)) {
                                if (node('./td[4]')) {
                                    $date = strtotime($this->cruiseDateStr . ', ' . node('./td[4]'));
                                    $date += $this->segmentIndex * 24 * 60 * 60;
                                    $res['ArrDate'] = $date;
                                }

                                if (node('./td[5]')) {
                                    $date = strtotime($this->cruiseDateStr . ', ' . node('./td[5]'));
                                    $date += $this->segmentIndex * 24 * 60 * 60;
                                    $res['DepDate'] = $date;
                                }
                            }

                            return $res;
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
