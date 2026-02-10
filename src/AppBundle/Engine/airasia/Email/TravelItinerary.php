<?php

namespace AwardWallet\Engine\airasia\Email;

class TravelItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@airasia[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@airasia[.]com#i";
    public $reProvider = "#[@.]airasia[.]com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re_white('(\w+) Email');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = between('Guest Details', 'Flight Details');
                        $ppl = preg_split('/\s*\d+[.]\s*/', $ppl);
                        array_shift($ppl);

                        return $ppl;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'Total Amount')]/following::p[1]");

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("//*[contains(text(), 'Booking Date')]/following::p[1]");
                        $date = uberDateTime($date);

                        return strtotime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = re('#Flight\s+Details(.*?)(?:Domestic|In-?Flight\s+Services)#is');

                        return splitter('/([A-Z]{2,}\d+)/', $info); // by flight number
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('^ ([A-Z]+\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( ( [A-Z]{3} ) \)');

                            if (!preg_match_all("/$q/isu", $text, $ms)) {
                                return;
                            }
                            $codes = $ms[1];

                            if (sizeof($codes) !== 2) {
                                return;
                            }

                            return [
                                'DepCode' => $codes[0],
                                'ArrCode' => $codes[1],
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $q = white('( \d+ \w+ \d+, \d+ ) hrs');

                            if (!preg_match_all("/$q/isu", $text, $ms)) {
                                return;
                            }
                            $dts = $ms[1];

                            if (sizeof($dts) !== 2) {
                                return;
                            }

                            $fmt = ' d M Y, Hi ';
                            $dt1 = \DateTime::createFromFormat($fmt, $dts[0]);
                            $dt1 = $dt1 ? $dt1->getTimestamp() : null;
                            $dt2 = \DateTime::createFromFormat($fmt, $dts[1]);
                            $dt2 = $dt2 ? $dt2->getTimestamp() : null;

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if ($cabin = nice(re('#[A-Z]{2}\d+\s+(Economy\s+Promo|Economy|Regular)#i', $text))) {
                                return $cabin;
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
