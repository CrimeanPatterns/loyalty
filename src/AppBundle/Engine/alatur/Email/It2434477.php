<?php

namespace AwardWallet\Engine\alatur\Email;

class It2434477 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]alatur[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['#\bALATUR.*\b#i', 'blank', ''],
    ];
    public $reSubject = "";
    public $reFrom = "";
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.02.2015, 10:51";
    public $crDate = "03.02.2015, 08:45";
    public $xPath = "";
    public $mailFiles = "alatur/it-2434477.eml, alatur/it-2937719.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    private $pdfText = false;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if ($this->pdfText) {
                        return [$this->pdfText];
                    }
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#DATA EMISSÃO\s+[^\n]+\s+(\w+)#ms", text($text));
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = rew('PASSAGEIRO (.+?)DADOS DE EMISSÃO', text($text));
                        $q = 'Adulto?\s+(\w[^\n(]+)';

                        if (preg_match_all("/$q/msi", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $q = white('\n (\S .+?) \n Orientações para embarque');
                        $x = re("/$q/iu");

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $info = rew('DATA EMISSÃO (.+) SEGMENTO');
                        $date = uberDate($info, 1);
                        $date = timestamp_from_format($date, 'd / m / Y|');

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('SEGMENTO (.+?) TARIFA');
                        $q = white('\( [A-Z]{3} \) .*? \( [A-Z]{3} \)');

                        return splitter("/($q)/su", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								\) \s{2,}
								(?P<AirlineName> \w .+?)
								(?P<FlightNumber> \d+)
							');
                            $res = re2dict($q, $text);

                            if (empty($res)) {
                                $q = "#\)\s+(?P<AirlineName>[^\n)]+)\n\s*(?P<FlightNumber>\d+)#ms";
                                preg_match($q, text($text), $m);

                                if (!empty($m)) {
                                    $res = [
                                        "AirlineName" => $m['AirlineName'],
                                        "FlightNumber"=> $m['FlightNumber'],
                                    ];
                                }
                            }

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $date = timestamp_from_format($date, 'd / m / Y|');
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $date = timestamp_from_format($date, 'd / m / Y|');
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return reni('Eqpt[.]: (.+?) \|');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('Classe: (\w)');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return reni('(\w+) (?:Bagagem|Aéreas Bagagem)', text($text));
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return reni('\d+:\d+ .+? \d+:\d+ (\w+) \w+ Bagagem');
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $res = parent::detectEmailByBody($parser);

        if ($res === true) {
            return $res;
        }

        if (strpos($parser->getHeader("content-type"), "application/pdf") !== false
            && strpos($parser->getHeader("content-transfer-encoding"), "base64") !== false) {
            $body = base64_decode(implode("", $parser->getRawBody()));
            $this->pdfText = \PDF::convertToHtml($body, \PDF::MODE_SIMPLE);

            if (strpos($this->pdfText, 'ALATUR') !== false && strpos($this->pdfText, 'Passageiro') !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
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
