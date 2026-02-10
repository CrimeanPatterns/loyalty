<?php

namespace AwardWallet\Engine\airbaltic\Email;

class It1996734 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@airbaltic[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@airbaltic[.]com#i";
    public $reProvider = "#[@.]airbaltic[.]com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "airbaltic/it-1996734.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');

                    $reserv = between(
                        'Your itinerary has been successfully registered on Wednesday,',
                        '('
                    );
                    $reserv = \DateTime::createFromFormat('d.m.Y H:i', $reserv);
                    $this->reserv = $reserv ? $reserv->getTimestamp() : null;

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('BOOKING REFERENCE  ([\w-]+)');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [between('ADULT/-S:', 'OUTBOUND FLIGHT:')];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = between('Price for your purchase:', 'Please see');

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return $this->reserv;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = between('Flight No.:', 'Class:');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = re_white('Outbound: \d+:\d+ (.+?) -? Arrival:');

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('Date: \w+ (\d+/\d+) Outbound:');
                            $year = date('Y', $this->reserv);
                            $date = \DateTime::createFromFormat('d/m/Y', "$date/$year");

                            if (!$date) {
                                return;
                            }
                            $date = $date->getTimestamp();

                            if ($date < $this->reserv) {
                                $date = strtotime('+1 year', $date);
                            }

                            $time1 = re_white('Outbound: (\d+:\d+)');
                            $time2 = re_white('Arrival: (\d+:\d+)');

                            $dt1 = strtotime($time1, $date);
                            $dt2 = strtotime($time2, $date);

                            if ($dt2 < $dt1) {
                                $dt2 = strtotime('+1 day', $dt2);
                            }

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = re_white('Arrival: \d+:\d+ (.+?)  Aircraft type:');

                            return nice($name);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return between('Aircraft type:', 'Flight No.:');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('Class: (.+?) ,');

                            return nice($x);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('Class: .+? , (\w+)');

                            return nice($x);
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
