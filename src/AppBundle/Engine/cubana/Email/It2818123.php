<?php

namespace AwardWallet\Engine\cubana\Email;

class It2818123 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Confirmación\s+de\s+la\s+compra.+?elegido\s+Cubana\s+de\s+Aviación#is', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Confirmación de la compra', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]cubana#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]cubana#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "18.06.2015, 10:22";
    public $crDate = "17.06.2015, 14:06";
    public $xPath = "";
    public $mailFiles = "cubana/it-2818123.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match('/[@.]cubana\./i', $headers['from']) > 0
            && isset($headers['subject']) && stripos($headers['subject'], 'Confirmación de la compra') !== false;
    }

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
                        return re("#Número\s+de\s+reserva:\s+(\w+)\s*\n#uis");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $psngrRaw = re("/información\s+del\s+viajero(\s+.+)\s+su\s+selección\s+de\s+vuelos/uis", $this->text());
                        preg_match_all("/\n\s*(?:Sr|Sra)\s+(.+?)\s*\n/ui", $psngrRaw, $psgnrs);
                        array_walk($psgnrs[1], function (&$val, $key) { $val = beautifulName($val); });

                        return $psgnrs[1];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return [
                            'TotalCharge' => cost(re("#Billete y pago del vuelo\s+([\d.,]+)\s+\b([A-Z]{3})\b#", $this->text())),
                            'Currency'    => currency(re(2)),
                        ];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Estado del viaje:\s*([^\n]+?)\s*\n#", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*Vuelo\s+\d+\s+\w+,\s*(?:\w+\s+)+\d+)#u");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Línea aérea:?\s*?\n.*?\w+?(\d+)\s*\n#ui");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#Salida:\s*[\d:]+\s*(.*?)(,\s*terminal\s*\w+)?\s+Llegada#ims");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $depDate = strtotime(en(preg_replace("/\bde\s+/ui", "", re('#\s*\w+,(.*?)\n#ims')) . ' ' . re("#Salida:\s*([\d:]+)\s*.*\n#ims"), "es"));
                            $arrDate = strtotime(en(preg_replace("/\bde\s+/ui", "", re('#\s*\w+,\s*(.*?)\n#ims')) . ' ' . re("#Llegada:\s*([\d:]+)\s*.*\n#ims"), "es"));

                            if ($arrDate < $depDate) {
                                $arrDate = strtotime('+1 day', $arrDate);
                            }

                            return [
                                'DepDate' => $depDate,
                                'ArrDate' => $arrDate,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#Llegada:\s*[\d:]+\s*(.*?)(,\s*terminal\s*\w+)?\s+Línea aérea#ims");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#Línea aérea:?\s*?\n.*?(\w+?)\d+\s*\n#ui");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Avión:\s*(.*)\s*\n#u");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#Tipo de tarifa:\s*(.*?)\s*\n#ims");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $flightIndex = re("#\n\s*(Vuelo\s+\d+)\s*#");
                            $info = re("#Solicitudes especiales de vuelo.*?{$flightIndex}:\s*(.*?)\s+(?:Vuelo\s+\d+:|Indica que la)#ms", $this->text());

                            return [
                                'Seats' => re("#\s+(\d+[A-Z])\s+([^\n]+)#", $info),
                                'Meal'  => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#Duración:?\s*?\n.*?([\d:]+)\s*\n#ui");
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
