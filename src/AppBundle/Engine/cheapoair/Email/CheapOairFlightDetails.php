<?php

namespace AwardWallet\Engine\cheapoair\Email;

class CheapOairFlightDetails extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+booking\s+with\s+CheapOair#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#cheapoair#i";
    public $reProvider = "#cheapoair#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cheapoair/it-2234442.eml, cheapoair/it-2234445.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (preg_match_all('#\s*(.*)\s*\(Ticket\s+No\.#i', $text, $m)) {
                        $this->passengers = nice($m[1]);
                    } else {
                        $this->passengers = null;
                    }
                    $r = '#';
                    $r .= '(?P<AirlineName>.*)\s*';
                    $r .= 'From:\s+(?P<DepName>.*)\s+-\s+(?P<DepCode>[A-Z]{3})\s*';
                    $r .= 'To:\s+(?P<ArrName>.*)\s+-\s+(?P<ArrCode>[A-Z]{3})\s*';
                    $r .= 'Departure:\s+(?P<DepDate>.*)\s*';
                    $r .= 'Arrival:\s+(?P<ArrDate>.*)\s*';
                    $r .= 'Flight\s+No\.:\s+(?P<FlightNumber>\d+)\s*';
                    $r .= 'Confirmation\s+No\.:\s+([\w\-]+)';
                    $r .= '#i';
                    $this->reservationRegex = $r;

                    if (preg_match_all($r, $text, $m)) {
                        return $m[0];
                    }
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+No\.:\s+([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match($this->reservationRegex, $text, $m)) {
                                $res = null;
                                $keys = ['AirlineName', 'DepName', 'DepCode', 'ArrName', 'ArrCode', 'DepDate', 'ArrDate', 'FlightNumber'];

                                foreach ($keys as $k) {
                                    $res[$k] = $m[$k] ?? null;
                                }

                                foreach (['DepDate', 'ArrDate'] as $k) {
                                    if ($badYear = re('#00\d{2}#i', $res[$k])) {
                                        $fixedYear = preg_replace('#^00#i', '20', $badYear);
                                        $res[$k] = str_replace($badYear, $fixedYear, $res[$k]);
                                    }
                                    $res[$k] = strtotime(str_replace(['-', '.'], [',', ''], $res[$k]));
                                }

                                return $res;
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
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
