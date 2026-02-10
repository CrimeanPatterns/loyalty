<?php

namespace AwardWallet\Engine\china\Email;

class It1925320 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@china-airlines[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#China\s*Airlines#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#China\s*Airlines#i";
    public $reProvider = "#[@.]china-airlines[.]com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "china/it-1925320.eml, china/it-1938527.eml, china/it-2087497.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#PNR:\s*([\w-]+)#is");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = re('/TICKET\s*INFORMATION\s*(.+?)\s*ITINERARY/is');

                        if (preg_match_all('/\d+[.]\s*(.+?)\s*\d+/s', $info, $ms)) {
                            return $ms[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        // order is important, first checking `p[4]`, then `p[3]`
                        $tot4 = node("//*[contains(text(), 'Total Amount')]/following::p[4]");
                        $tot4 = preg_replace('/,/', '', $tot4);
                        $tot3 = node("//*[contains(text(), 'Total Amount')]/following::p[3]");

                        return orval(
                            cost($tot4),
                            cost($tot3)
                        );
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $fare = node("//*[contains(text(), 'Total Amount')]/following::p[1]");
                        $fare = preg_replace('/,/', '', $fare);

                        return cost($fare);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $cur = node("//*[contains(text(), 'Total Amount')]");
                        $cur = re('#\[([A-Z]+)\]#', $cur);

                        return currency($cur);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $tax = node("//*[contains(text(), 'Total Amount')]/following::p[2]");
                        $tax = preg_replace('/,/', '', $tax);

                        return cost($tax);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Order\s*Date:\s*(.+?)\s*E-Mail:#is");
                        $date = uberDateTime($date);

                        return strtotime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departure Date') or contains(text(), 'Return Date')]/ancestor-or-self::p[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('following::p[10]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/\]\s*(.+?)\s*To\s*(.+)\s*$/i', node('.'), $ms)) {
                                return [
                                    'DepName' => nice($ms[1]),
                                    'ArrName' => nice($ms[2]),
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('Date (\d+ / \d+ / \d+)');

                            if (!$date) {
                                return;
                            }

                            // sometimes dep time, arr time and duration are all together in p[14], sometimes separately in p[14-16].
                            // so we take p[14-16] just to be safe.
                            // inclined to resort to re next time, but missing fields may be problematic.
                            $info = implode(' ', [node('following::p[14]'), node('following::p[15]'), node('following::p[16]')]);
                            $q = white('.+? -(\d{3,4}) \s+ .+? -(\d{3,4}) \s+ (\d+:\d+)');

                            if (!preg_match("/$q/isu", $info, $ms)) {
                                return;
                            }
                            $time1 = $ms[1];
                            $time2 = $ms[2];
                            $dur = $ms[3];

                            $dt1 = "$date, $time1";
                            $dt2 = "$date, $time2";

                            $fmt = 'Y / m / d, Hi';
                            $dt1 = \DateTime::createFromFormat($fmt, $dt1);
                            $dt1 = $dt1 ? $dt1->getTimestamp() : null;
                            $dt2 = \DateTime::createFromFormat($fmt, $dt2);
                            $dt2 = $dt2 ? $dt2->getTimestamp() : null;

                            return [
                                'DepDate'  => $dt1,
                                'ArrDate'  => $dt2,
                                'Duration' => nice($dur),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $cls = node('following::p[12]');

                            return nice($cls);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = node('following-sibling::p[17]');

                            if (re('/non-stop/i', $stops)) {
                                return 0;
                            }
                            $stops = node('following-sibling::p[19]');

                            if (re('/non-stop/i', $stops)) {
                                return 0;
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
