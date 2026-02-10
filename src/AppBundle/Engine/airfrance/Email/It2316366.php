<?php

namespace AwardWallet\Engine\airfrance\Email;

class It2316366 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]airfrance[.]fr#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]airfrance[.]fr#i";
    public $reProvider = "#[@.]airfrance[.]fr#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "31.12.2014, 11:05";
    public $crDate = "31.12.2014, 10:49";
    public $xPath = "";
    public $mailFiles = "airfrance/it-2316366.eml";
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
                        return reni('Booking ref : (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Passenger : (.+?) \n');

                        return [$name];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total paid : (\w+ [\d.,]+)');

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Date of issue : (.+? \d{4})');
                        $date = totime($date);
                        $this->anchor = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Passenger : (.+)');

                        $q = white('\s+ From :');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('Flight : (\w+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return reni('From : (.+?) \n');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('Date : (\d+ \w+)');
                            $date = date_carry($date, $this->anchor);

                            $time1 = reni('Dep : (\d{4})');
                            $time2 = reni('Arr : (\d{4})');
                            $time1 = sprintf('%s:%s', substr($time1, 0, 2), substr($time1, 2, 2));
                            $time2 = sprintf('%s:%s', substr($time2, 0, 2), substr($time2, 2, 2));

                            $dt1 = strtotime($time1, $date);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return reni('To : (.+?) \n');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('Class : (\w)');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return reni('Seat : (\w+) \n');
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
