<?php

namespace AwardWallet\Engine\cheapnl\Email;

class It2463401 extends \TAccountCheckerExtended
{
    public $mailFiles = "cheapnl/it-2463401.eml, cheapnl/it-5462750.eml";

    public $rePlain = [
        ['#\n[>\s*]*De\s*:[^\n]*?[@.]cheaptickets[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reBody = [
        'de' => 'Flugdetails',
        'nl' => 'Vluchtgegevens',
    ];
    public $reSubject = [
        'Bestätigung Ihrer Reservierung',
        'Boekingsbevestiging van uw online reservering',
    ];
    public $reFrom = [
        ['#[@.]cheaptickets[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]cheaptickets[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de, nl";
    public $typesCount = "2";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "13.02.2015, 07:53";
    public $crDate = "12.02.2015, 10:14";
    public $xPath = "";

    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    public $lang = '';
    public static $dict = [
        'de' => [
            'RecordLocator' => 'Reservierungsnummer',
            'Total'         => 'Gesamtpreis',
            'ForStatus'     => 'Diese Buchungsbestätigung gilt',
            'ResDate'       => 'Buchungsdatum',
            'FlightNumber'  => 'Flugnummer',
        ],
        'nl' => [
            'RecordLocator' => 'Reserveringsnummer',
            'Name'          => 'Naam',
            'Total'         => 'Totaalprijs',
            'ForStatus'     => 'Deze boekingsbevestiging dient tevens als',
            'ResDate'       => 'Boekingsdatum',
            'FlightNumber'  => 'vluchtnummer',
        ],
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter"               => function ($text = '', $node = null, $it = null) {
                    foreach ($this->reBody as $lang => $re) {
                        if (strpos($text, $re) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni($this->t('RecordLocator') . '\s+(\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nice(nodes("//*[normalize-space(text()) = '" . $this->t('Name') . "']/following::td[1]"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni($this->t('Total') . '\s+.*? (. [\d.,]{2,})');

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (reni($this->t('ForStatus'))) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew($this->t('ResDate') . '\s+(.+?) \n');

                        return totime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text()) = '" . $this->t('FlightNumber') . "']/ancestor::tr[1]
						/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('./td[last()]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[3]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = re("#\d+:\d+#");
                            $date = timestamp_from_format($date, 'd/m/y|');
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[4]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $date = timestamp_from_format($date, 'd/m/y|');
                            $dt = strtotime($time, $date);

                            return $dt;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'CheapTickets')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }
}
