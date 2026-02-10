<?php

namespace AwardWallet\Engine\alatur\Email;

use AwardWallet\Engine\MonthTranslate;

class It2775376 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]alaturjtb[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['#ROTEIRO DA VIAGEM.+?(Agência:\s*ABC|Agência:\s*ALATUR|Agência:\s*MASCARO\s*TOUR)\b#si', 'blank', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]alaturjtb[.]#i', 'blank', ''],
        ['#[@.]argoit[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "02.06.2015, 14:08";
    public $crDate = "02.06.2015, 13:43";
    public $xPath = "";
    public $mailFiles = "alatur/it-2775376.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    private $year;
    private $textAll;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->textAll = $this->setDocument('application/pdf', 'text');
                    $text = rew('(LOCALIZADOR .+)');

                    $date = reni('\d+ \/ \w+ \/ (\d{4})');
                    $this->year = $date;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('LOCALIZADOR : (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $this->logger->debug($this->textAll);

                        return reni('DA VIAGEM\s+([A-Z\s]+)\s+O\.S\.\s*\d+', $this->textAll);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total : (\w+ [\d.,]+)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Valor : (\w+ [\d.,]+)');

                        return cost($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Data Emissão:\s*(\d+\s*\/\s*\w+\s*\/\s*\d{4})');
                        $date = timestamp_from_format($date, 'd / M / Y|');

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('\w+ \s+ \w{2} \s+ \d+ \s+ \w \s+');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('\s+ (\w{2} \s+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $qdate = white('\s+ (\d{2} \/ \w{2,3}) \s+');
                            $date = ure("/$qdate/isu", 1);

                            $qtime = white('\s+ (\d+:\d+) \s+');
                            $time = ure("/$qtime/isu", 1);
                            $dt = strtotime($this->normalizeDate($date . ', ' . $time));

                            if ($dt < $this->year) {
                                $dt = strtotime('+1 year', $dt);
                            }

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $year = date('Y', $this->year);

                            $qdate = white('\s+ (\d{2} \/ \w{2,3}) \s+');
                            $date = ure("/$qdate/isu", 2);

                            $qtime = white('\s+ (\d+:\d+) \s+');
                            $time = ure("/$qtime/isu", 2);

                            $dt = strtotime($this->normalizeDate($date . ', ' . $time));

                            if ($dt < $this->year) {
                                $dt = strtotime('+1 year', $dt);
                            }

                            return $dt;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('Classe (\w+)');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('\s+ \w{2} \s+ \d{4} \s+ (\w) \s+');
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

    private function normalizeDate($str)
    {
        $year = $this->year;
        $this->logger->debug($str);
        $in = [
            // 18/06
            "#^(\d+)/(\d+), (\d+:\d+)$#u", // 30 нояб. 2016 03:05
            // 12/dez
            "#^(\d+)/([A-z]{3}), (\d+:\d+)$#u", // 30 нояб. 2016 03:05
        ];
        $out = [
            "$2/$1/{$year}, $3",
            "$1 $2 {$year}, $3",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug($str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], 'pt')) {
                $str = str_replace($m[1], $en, $str);
                //$this->logger->debug($str);
            }
        }

        return $str;
    }
}
