<?php

namespace AwardWallet\Engine\azul\Email;

class It2005709 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?azul#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Itinerario Azul#i', 'us', ''],
    ];
    public $reFrom = [
        ['#[@.]voeazul#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]voeazul#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "7117";
    public $upDate = "30.07.2015, 21:47";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "azul/it-2005709.eml, azul/it-3137577.eml";
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
                        return re("#\n\s*Código\s+localizador\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = filter(nodes("//*[normalize-space(text()) = 'Passageiros']/ancestor::tr[4]/following-sibling::tr[1]//td[string-length(.)>1][2]"));

                        return array_unique($names);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+em[:\s]+([^\s\d]+\s+[\d.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxas\s*:\s*([^\n]+)#"));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total em pontos\s*:\s*([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#dinheiro\s+foi\s+efetuada\s+([^\n,.]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(),'VOO -')]/ancestor::table[contains(., 'Ida') or contains(.,'Volta')][1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\n\s*VOO\s*\-\s*([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return ure("#(\w+)\s+\([A-Z]{3}\)#", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(clear('#/#', re("#(?>Ida|Volta)\s+([^\n]+)#i"), ' '), 'pt');
                            $dep = $date . ',' . uberTime(1);
                            $arr = $date . ',' . uberTime(2);
                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return ure("#(\w+)\s+\([A-Z]{3}\)#", 2);
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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
