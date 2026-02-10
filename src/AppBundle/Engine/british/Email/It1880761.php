<?php

namespace AwardWallet\Engine\british\Email;

// TODO: merge with parsers british/CheckInConfirm (in favor of british/CheckInConfirm)

class It1880761 extends \TAccountCheckerExtended
{
    public $mailFiles = "british/it-1880760.eml, british/it-1880761.eml, british/it-7094889.eml, british/it-7421285.eml";

    private $detects = [
        'en' => [
            'Receipt for paid seat selection',
            'British Airways Customer Services',
            'All British Airways flights are non-smoking',
        ],
        'ru' => [
            'Служба по работе с клиентами British Airways',
        ],
        'ja' => [
            'チェックイン確認',
        ],
    ];

    private $reFlight = '/^\s*(\d+\s+\w+\s+\d{4}\s+\d+:\d+)\s+(?:\b([A-Z]{3})\b\s+\((\D+)\)|(\D+))\s*(?:(?:Terminal|Терминал|ターミナル)\s+([A-Z\d]{1,3}))*$/s';

    private $lang = '';

    private static $dict = [
        'en' => [],
        'ru' => [
            'Passenger(s)'      => 'Пассажир(ы)',
            'Booking reference' => 'Номер бронирования',
            'Terminal'          => 'Терминал',
        ],
        'ja' => [
            'Passenger(s)'      => 'ご搭乗者',
            'Booking reference' => '予約番号',
            'Terminal'          => 'ターミナル',
        ],
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $body = $this->parser->getHTMLBody();

                    foreach ($this->detects as $lang => $detects) {
                        foreach ($detects as $detect) {
                            if (stripos($body, $detect) !== false) {
                                $this->lang = $lang;
                            }
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+" . $this->t('Booking reference') . "\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $title = ['Mr', 'Miss', 'Ms', 'MR', 'M', 'Mstr'];
                        $titleRules = '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $title)) . ')';

                        return array_filter(array_unique(nodes("//tr[contains(., '" . $this->t('Passenger(s)') . "')]/following-sibling::tr[ {$titleRules} or descendant::img[contains(@src, 'greenTick')]]/descendant::tr[count(td)>=2]/td[1]")));
                    },

                    // "TotalCharge" => function ($text = '', $node = null, $it = null) {
                    //     return cost(node("//*[contains(text(), 'Payment total')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]"));
                    // },
                    //
                    // "Currency" => function ($text = '', $node = null, $it = null) {
                    //     return currency(node("//*[contains(text(), 'Payment total')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]"));
                    // },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), '" . $this->t('Passenger(s)') . "')]/ancestor::tr[2]/preceding-sibling::tr[contains(., ':')][1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^\s*([A-Z\d]{2})(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $text = text(xpath("td/table/tr[last()]/td/table/tr/td[1]|td/table/tbody/tr[last()]/td/table/tbody/tr/td[1]"));

                            if (preg_match($this->reFlight, $text, $m)) {
                                $depName = !empty($m[3]) ? trim($m[3]) : trim($m[4]);

                                if (preg_match('/^(.+?)(?:Terminal|Терминал|ターミナル)\s*(.+)$/', $depName, $mat)) {
                                    return [
                                        'DepDate'           => strtotime(preg_replace("#\s+#", ' ', $m[1])),
                                        'DepCode'           => !empty($m[2]) ? $m[2] : TRIP_CODE_UNKNOWN,
                                        'DepName'           => trim(preg_replace('/\s+/', ' ', $mat[1])),
                                        'DepartureTerminal' => $mat[2],
                                    ];
                                }

                                return [
                                    'DepDate'           => strtotime(preg_replace("#\s+#", ' ', $m[1])),
                                    'DepCode'           => !empty($m[2]) ? $m[2] : TRIP_CODE_UNKNOWN,
                                    'DepName'           => preg_replace('/\s+/', ' ', $depName),
                                    'DepartureTerminal' => !empty($m[5]) ? $m[5] : null,
                                ];
                            }
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $text = text(xpath("td/table/tr[last()]/td/table/tr/td[2]|td/table/tbody/tr[last()]/td/table/tbody/tr/td[2]"));
                            $re = '/^\s*(\d+\s+\w+\s+\d{4}\s+\d+:\d+)\s+(?:\b([A-Z]{3})\b\s+(\D+)|(\D+))\s*(?:(?:Terminal|Терминал|ターミナル)\s+([A-Z\d]{1,3}))*$/s';

                            if (preg_match($this->reFlight, $text, $m)) {
                                $arrName = !empty($m[3]) ? trim($m[3]) : trim($m[4]);

                                return [
                                    'ArrDate'         => strtotime(preg_replace("#\s+#", ' ', $m[1])),
                                    'ArrCode'         => !empty($m[2]) ? $m[2] : TRIP_CODE_UNKNOWN,
                                    'ArrName'         => $arrName,
                                    'ArrivalTerminal' => !empty($m[5]) ? $m[5] : null,
                                ];
                            } elseif (preg_match('/^(?:\b([A-Z]{3})\b\s+((?!(?:Terminal|Терминал|ターミナル)\s+[A-Z\d]{1,3})\D+?)|((?!(?:Terminal|Терминал|ターミナル)\s+[A-Z\d]{1,3})\D+?))\s*(?:(?:Terminal|Терминал|ターミナル)\s+([A-Z\d]{1,3}))*$/s', $text, $m)) {
                                $arrName = !empty($m[2]) ? trim($m[2]) : trim($m[3]);
                                $arrName = str_replace(['(', ')'], ['', ''], $arrName);

                                return [
                                    'ArrDate'         => MISSING_DATE,
                                    'ArrCode'         => !empty($m[1]) ? $m[1] : TRIP_CODE_UNKNOWN,
                                    'ArrName'         => $arrName,
                                    'ArrivalTerminal' => !empty($m[4]) ? $m[4] : null,
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return array_filter(nodes("following-sibling::tr[contains(., '" . $this->t('Passenger(s)') . "')][1]/following-sibling::tr[contains(., 'Seat')][1]//td[not(.//td)][contains(., 'Seat')]", null, "#Seat\s*(\d+[A-Z])\b#"));
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $anchor = $this->http->XPath->query("//img[contains(@src, 'britishairways.com')]/@src | //a[contains(@href, 'ba.com/travel')]")->length > 0;
        $body = $parser->getHTMLBody();
        $body = preg_replace('/\s+/', ' ', $body);

        foreach ($this->detects as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false && $anchor) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'british') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'britishairways.com') !== false || stripos($from, 'British Airways') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($s)
    {
        if (empty(self::$dict[$this->lang]) || empty(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
