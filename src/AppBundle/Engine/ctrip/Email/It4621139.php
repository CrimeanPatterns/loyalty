<?php

namespace AwardWallet\Engine\ctrip\Email;

class It4621139 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Ctrip International Flight Reservation#im', 'us', ''],
    ];
    public $reHtml = '#Thank you for using Ctrip#im';
    public $rePDF = "";
    public $reSubject = "#Ctrip flight order#";
    public $reFrom = [
        ['#Ctrip International Flight Reservation#i', 'us', ''],
    ];
    public $reProvider = [
        ['#ctrip#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "ctrip/it-4621139.eml, ctrip/it-4621139.eml";
    public $re_catcher = "#.*?#";
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
                        return re("#.+Your order no\.(\d+).+#", node("//p[contains(.,'Your order no.')]"));
                    },
                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#([\d\.]+)#", node("//p[contains(.,'Payment details')]/following::tr[contains(.,'Total Amount')]/td[1]")));
                    },
                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#([\d\.]+)#", node("//p[contains(.,'Payment details')]/following::tr[contains(.,'Fare')]/td[1]")));
                    },
                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#([\d\.]+)#", node("//p[contains(.,'Payment details')]/following::tr[contains(.,'Tax')]/td[1]")));
                    },
                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#(.+)\s*[\d\.]+#", node("//p[contains(.,'Payment details')]/following::tr[contains(.,'Total Amount')]/td[1]")));
                    },
                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return array_unique(nodes("//p[contains(.,'Passenger info')]/following::tr[contains(.,'Passenger #')]/td"));
                    },
                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $acc = re('#Account:\s+(.+)\)#', node("//p[contains(.,'Dear') and contains(.,'Account')]"));

                        if (stripos($acc, '_') !== false) {
                            $acc = str_replace('_', '', $acc);
                        }

                        return $acc;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $segments = splitter("#(Route.+?Class.+?)\s#s", re('#Flight details:(.*)Payment details:#s'));

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (re("#Flight no:\s+([A-Z\d]{2})\s*(\d+)#", $text)) {
                                return [
                                    'FlightNumber' => re(2),
                                    'AirlineName'  => re(1),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#Departure:\s*(.+)\s+@\s+(\d+:\d+)(?:,(.+)(?>Airport))?\s*(?:Airport|Arrival|)#", $text, $m)) {
                                $r = [
                                    'DepCode' => TRIP_CODE_UNKNOWN,
                                    'DepName' => (isset($m[3])) ? $m[3] : null,
                                    'DepDate' => correctDate(strtotime($m[1] . ' ' . date('Y', $this->getEmailDate()) . ' ' . $m[2]), $this->getEmailDate()),
                                ];
                            }

                            if (empty($r['DepName']) && preg_match('/Route\s+\D\d\s+(?<DepName>.+) - .+/', $text, $m)) {
                                $r['DepName'] = $m['DepName'];
                            }

                            return $r;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#Arrival:\s*(.+)\s+@\s+(\d+:\d+)(?:,(.+)(?>Airport))?\s*(?:Airport|Class|)#", $text, $m)) {
                                $r = [
                                    'ArrCode' => TRIP_CODE_UNKNOWN,
                                    'ArrName' => (isset($m[3])) ? $m[3] : null,
                                    'ArrDate' => correctDate(strtotime($m[1] . ' ' . date('Y', $this->getEmailDate()) . ' ' . $m[2]), $this->getEmailDate()),
                                ];
                            }

                            if (empty($r['ArrName']) && preg_match('/Route\s+\D\d\s+.+ - (?<ArrName>.+)/', $text, $m)) {
                                $r['ArrName'] = $m['ArrName'];
                            }

                            return $r;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('Class:\s*(\w+)');
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
