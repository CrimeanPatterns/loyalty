<?php

namespace AwardWallet\Engine\copaair\Email;

class DetallesDeSuReserva extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*De\s*:[^\n]*?copaair#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#reservas@copaair\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]copaair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "22.04.2015, 10:10";
    public $crDate = "22.04.2015, 07:41";
    public $xPath = "";
    public $mailFiles = "copaair/it-2657384.eml";
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
                        return re('#Código\s+de\s+la\s+reservación:\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Pasajeros en este viaje") and contains(., "Asientos") and not(.//tr)]/following-sibling::tr[contains(., "Contacto de")]';
                        $passengersAndSeatsInfoNodes = xpath($xpath);
                        $passengers = [];
                        $this->seats = [];

                        foreach ($passengersAndSeatsInfoNodes as $n) {
                            $flightNo = re('#^(\w{2}\s+\d+)\s+#i', node('../../preceding-sibling::*[1]', $n));
                            $passengers[] = nice(node('./td[1]', $n));
                            $this->seats[$flightNo][] = node('./td[2]', $n);
                        }
                        $passengers = array_unique($passengers);

                        return $passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Hora de salida") and contains(., "Hora de llegada") and not(.//tr)]/../../following-sibling::*[contains(., "hr") and contains(., "min") and not(contains(., "Duración total"))]/tbody/tr');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#^(\w{2})\s*(\d+)$#i', node('./td[2]'), $m)) {
                                return [
                                    'FlightNumber' => $m[2],
                                    'AirlineName'  => $m[1],
                                    'Seats'        => $this->seats[$m[1] . ' ' . $m[2]] ?? null,
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $dateStr = re('#\d+\s+de\s+\w+\s+de\s+\d{4}#i', node('../../../preceding-sibling::div[1]'));

                            if ($dateStr) {
                                $dateStr = en(nice(str_replace('de', '', $dateStr)));
                            }

                            foreach (['Dep' => 3, 'Arr' => 4] as $key => $value) {
                                $r = '#^(\d+:\d+\s+[ap]\.m\.)\s*(.*)\s+\((\w{3})\)#i';
                                $s = node("./td[$value]");

                                if (preg_match($r, $s, $m)) {
                                    $res[$key . 'Date'] = $dateStr ? strtotime($dateStr . ', ' . $m[1]) : null;
                                    $res[$key . 'Name'] = nice($m[2]);
                                    $res[$key . 'Code'] = nice($m[3]);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Clase\s+(.*)\s+Aeronave\s+(.*)#i', node('./td[6]'), $m)) {
                                return [
                                    'Cabin'    => $m[1],
                                    'Aircraft' => $m[2],
                                ];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#\d+hr\s+\d+min#');
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
