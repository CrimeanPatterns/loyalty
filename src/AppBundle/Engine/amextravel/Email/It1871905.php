<?php

namespace AwardWallet\Engine\amextravel\Email;

class It1871905 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?viewtrip-admin@travelport[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amextravel#i";
    public $reProvider = "#amextravel#i";
    public $xPath = "";
    public $mailFiles = "amextravel/it-1871905.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation\s*Number\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Passenger Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]";

                        return nodes($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cell('Total', +2);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return cell('Total:', +1);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("##");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(normalize-space(text()), 'Confirm Baggage Fees')]/ancestor::table[2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('./preceding::table[1]/tbody/tr[2]/td[1]');

                            if (preg_match('/[(](\w+)[)]\s*(\d+)/', $fl, $ms)) {
                                return [
                                    'FlightNumber' => $ms[2],
                                    'AirlineName'  => $ms[1],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $dep = cell('Depart:', +1);

                            return re('/[(]([A-Z]+)[)]/', $dep);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('./preceding::table[1]/tbody/tr[1]/td[1]');

                            $time1 = cell('Depart:', +2);
                            $time2 = cell('Arrive:', +2);

                            $dt1 = "$date, $time1";
                            $dt2 = "$date, $time2";

                            $dt1 = strtotime(uberDateTime($dt1));
                            $dt2 = strtotime(uberDateTime($dt2));

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $dep = cell('Arrive:', +1);

                            return re('/[(]([A-Z]+)[)]/', $dep);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding::table[1]/tbody/tr[2]/td[2]');

                            if (preg_match('/(.+)\s*[(](\w+)[)]/', $info, $ms)) {
                                return [
                                    'Cabin'        => nice($ms[1]),
                                    'BookingClass' => $ms[2],
                                ];
                            }

                            return $info;
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
