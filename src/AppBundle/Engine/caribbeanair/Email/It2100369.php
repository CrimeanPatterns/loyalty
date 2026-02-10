<?php

namespace AwardWallet\Engine\caribbeanair\Email;

class It2100369 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?caribbean-airlines.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@caribbean-airlines.com#i";
    public $reProvider = "#caribbeanair#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "caribbeanair/it-2100369.eml";
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
                        return cell("Booking reference", +1);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("(//*[contains(text(), 'Passenger')])[1]/ancestor-or-self::tr/following-sibling::tr/td[1]/a");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell("TOTAL TRIP COST", +1));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Depart / Arrive')]/ancestor-or-self::tr[1]/following-sibling::tr[2]";

                        return xpath($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node(".//td[1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//td[3]");
                            $node = explode(")", $node);
                            unset($node[2]);
                            $depcode = re("#\(([A-Z]{3})#", $node[0]);
                            $arrcode = re("#\(([A-Z]{3})#", $node[1]);
                            $depname = preg_split("#Terminal [0-9]#", $node[0]);
                            $test = re("#([^\n]+)\s*\(#", $depname[0]);

                            if ($test == null) {
                                $depname = re("#([^\n]+)\s*\(#", $depname[1]);
                            } else {
                                $depname = $test;
                            }
                            $arrname = preg_split("#Terminal [0-9]#", $node[1]);
                            //var_dump($arrname);
                            $test = re("#([^\n]+)\s*\(#", $arrname[0]);

                            if ($test == null) {
                                $arrname = re("#([^\n]+)\s*\(#", $arrname[1]);
                            } else {
                                $arrname = $test;
                            }

                            return [
                                'DepCode' => $depcode,
                                'ArrCode' => $arrcode,
                                'DepName' => nice($depname),
                                'ArrName' => nice($arrname),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//td[2]");
                            $dep = uberDatetime($node);
                            $arr = str_replace($dep, "", $node);
                            $arr = uberDatetime($arr);

                            return [
                                'DepDate' => totime($dep),
                                'ArrDate' => totime($arr),
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//td[4]");

                            return $node;
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
