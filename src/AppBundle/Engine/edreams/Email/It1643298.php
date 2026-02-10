<?php

namespace AwardWallet\Engine\edreams\Email;

class It1643298 extends \TAccountCheckerExtended
{
    public $reFrom = "#@odigeo#i";
    public $reProvider = "#@odigeo#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@odigeo#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $isAggregator = "1";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "edreams/it-1643298.eml, edreams/it-1703870.eml, edreams/it-1806197.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#booking\s*ref\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#([a-z]+/[a-z]+\s*(?:mr[.]?|mrs[.]?)?)\s*ticket#i', $text, $ms)) {
                            return $ms[1];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $res = splitter("#(service|equipment)#i"); // yes, weirdly we _need_ grouping here
                        array_pop($res);

                        return $res;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $x = re('#-\s*([a-z]{2}\s*\d+)#i');

                            return uberAir($x);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            // all in one go here
                            if (preg_match('#(?P<date>[a-z]{3}\s*\d{1,2}[a-z]+)\s{3,}(?P<dep>(?:\w+ )+)\s{2,}(?P<arr>(?:\w+ )+)\s{2,}(?P<time1>\d+)\s{2,}(?P<time2>\d+)\s{2,}(?P<dep_more>(?:\w+ )+)\s{1,}(?P<arr_more>(?:\w+(?: \w+)*))#i', $text, $ms)) {
                                $res = [];

                                $dt1 = re("#\w+\s+(\d+)(\w+)#", $ms['date']) . ' ' . re(2) . ', ' . re("#(\d+)(\d{2})#", $ms['time1']) . ':' . re(2);
                                $res['DepDate'] = strtotime($dt1, $this->date);

                                $dt2 = re("#\w+\s+(\d+)(\w+)#", $ms['date']) . ' ' . re(2) . ', ' . re("#(\d+)(\d{2})#", $ms['time2']) . ':' . re(2);
                                $res['ArrDate'] = strtotime($dt2, $this->date);

                                $res['DepName'] = trim($ms['dep']) . ', ' . trim($ms['dep_more']);
                                $res['ArrName'] = trim($ms['arr']) . ', ' . trim($ms['arr_more']);

                                return $res;
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#reservation\s*confirmed\s*-\s*([a-z])\s*(\w+)#i', $text, $ms)) {
                                return [
                                    'Cabin'        => $ms[2],
                                    'BookingClass' => $ms[1],
                                ];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#duration\s*(.*)\b#i");
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
        return true;
    }
}
