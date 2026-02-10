<?php

namespace AwardWallet\Engine\copaair\Email;

class It1734432 extends \TAccountCheckerExtended
{
    public $reFrom = "#copa\s*airlines#i";
    public $reProvider = "#copaair#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?copaair#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "es";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "copaair/it-1734432.eml, copaair/it-1735070.eml, copaair/it-1784100.eml, copaair/it-1785306.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re("#Número\s*de\s*confirmación[:]?\s*([\w\d]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#Web\s*Check-In\s*para\s*([\w ]+)#i");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDate(en('Junio 20, 2014')));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[normalize-space(.)='Salida']/ancestor::table[1]/ancestor::tr[1]";

                        return xpath($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $flight = orval(cell('Salida', 0, -1), cell('Salida', 0, -2));
                            $flight = preg_replace('/[*]/i', '', $flight); // weird star

                            return uberAir($flight);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re('##');
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re('#(.*)\s*-#i', cell(' - ', 0, 0));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            if (preg_match('/[(](\w+)[)],\s*.*?,\s*(.*)/i', cell('Salida', +1, 0), $ms)) {
                                $res['DepCode'] = $ms[1];
                                $res['DepDate'] = $ms[2];
                                // spanish-only so far
                                $res['DepDate'] = preg_replace('/\s*de/i', '', $res['DepDate']);
                                $res['DepDate'] = preg_replace('/\s*a las/i', '', $res['DepDate']);
                                $res['DepDate'] = totime(uberDateTime(en($res['DepDate'])));
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re('##');
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re('#-\s*(.*)#i', cell(' - ', 0, 0));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            if (preg_match('/[(](\w+)[)],\s*.*?,\s*(.*)/i', cell('Llegada', +1, 0), $ms)) {
                                $res['ArrCode'] = $ms[1];
                                $res['ArrDate'] = $ms[2];
                                // spanish-only so far
                                $res['ArrDate'] = preg_replace('/\s*de/i', '', $res['ArrDate']);
                                $res['ArrDate'] = preg_replace('/\s*a las/i', '', $res['ArrDate']);
                                $res['ArrDate'] = totime(uberDateTime(en($res['ArrDate'])));
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
        return ["es"];
    }
}
