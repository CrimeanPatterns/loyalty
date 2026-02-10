<?php

namespace AwardWallet\Engine\airasia\Email;

class FlightReminder extends \TAccountCheckerExtended
{
    use \DateTimeTools;
    public static $reBody = [
        'en' => ['Thank you for flying with AirAsia', 'Fly with ease with AirAsia', 'Thank you for choosing AirAsia', 'AirAsia Travel Itinerary'],
        'zh' => ['感謝您辦理登機手續', '亞洲航空會員', '亞洲航空會員'], //亞洲航空會員 is repeating
        'ja' => ['エアアジアの旅程表', '予約番号', '出発地'],
        'ko' => ['예약이 확인되었습니다.'],
    ];
    public $dict = [
        'en' => [
            'RecordLocator' => 'Your booking number is',
            'Passengers'    => 'Dear',
        ],
        'zh' => [
            'RecordLocator' => '訂位編號',
            'Passengers'    => '親愛的',
            'Flight'        => '航班',
            'Depart'        => '出發',
            'Arrive'        => '到逹',
        ],
        'ja' => [
            'RecordLocator' => '予約番号',
            'Passengers'    => '様',
            'Flight'        => '便名',
            'Depart'        => '出発地',
            'Arrive'        => '到着地',
        ],
        'ko' => [
            'RecordLocator' => '예약번호',
            'Passengers'    => '안녕하세요.',
            'Flight'        => '항공편',
            'Depart'        => '출발지',
            'Arrive'        => '도착지',
        ],
    ];
    public $lang = '';

    public $mailFiles = "airasia/it-18206972.eml, airasia/it-2153150.eml, airasia/it-2459788.eml, airasia/it-2684445.eml, airasia/it-3185215.eml, airasia/it-4684420.eml, airasia/it-5064978.eml";

    private $prov = 'airasia';

    private $from = '/[@\.]airasia\.com/i';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $body = $this->http->Response['body'];

                    foreach (self::$reBody as $lang => $reBody) {
                        foreach ((array) $reBody as $re) {
                            if (false !== stripos($body, $re)) {
                                $this->lang = $lang;

                                break;
                            }
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Booking\s+Number\s*:\s+([\w\-]+)#i'),
                            re('#' . $this->t('RecordLocator') . '[:：\s]+([\w\-]+)#i')
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $pax = re("#\n\s*" . $this->t('Passengers') . "\s?([^\n,]+[a-zA-Z])#");

                        if (empty($pax)) {
                            $pax = re("#\n\s*([^\n,]+[a-zA-Z])" . $this->t('Passengers') . " *\n#");
                        }

                        return $pax;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#booking has been (\w+)#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//tr[contains(., '" . $this->t('Flight') . "') and contains(., '" . $this->t('Depart') . "') and
						contains(., '" . $this->t('Arrive') . "') and not(.//tr)]/following-sibling::tr[normalize-space(.)!=''][not(contains(normalize-space(.), 'Transit in'))]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})\s*(\d+)\s?(.*)#', node('./td[string-length(normalize-space(.))>1][1]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Cabin'        => (isset($m[3])) ? $m[3] : null,
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $xpath = './td[string-length(normalize-space(.))>1][position() = 2 or position() = 3]/';
                            $codes = nodes($xpath . 'descendant::td[string-length(normalize-space(.))>1][1]');
                            $names = nodes($xpath . 'descendant::td[string-length(normalize-space(.))>1][2]/descendant::text()[string-length(normalize-space(.))>1][1]');
                            $dates = nodes($xpath . 'descendant::td[string-length(normalize-space(.))>1][2]/descendant::text()[string-length(normalize-space(.))>1][2]');
                            $times = nodes($xpath . 'descendant::td[string-length(normalize-space(.))>1][2]/descendant::text()[string-length(normalize-space(.))>1][3]');

                            foreach (['Dep' => 2, 'Arr' => 4] as $key => $value) {
                                $res[$key . 'Code'] = array_shift($codes);
                                $res[$key . 'Name'] = array_shift($names);
                                $res[$key . 'Date'] = strtotime($this->normalizeDate(array_shift($dates)) . ' ' . array_shift($times));
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach (self::$reBody as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$reBody);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$reBody);
    }

    protected function t($str)
    {
        if (!isset($this->dict) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }

    /**
     * example: 2016年10月8日 (星期六)
     * 			Tue 24 Feb 2015.
     *
     * @param $str
     *
     * @return string
     */
    private function normalizeDate($str)
    {
        $in = [
            '#(?<Year>\d+)\D+(?<Month>\d+)\D+(?<Day>\d+).*#',
            '#.*\s+(?<Day>\d+)\s+(?<Month>\w+)\s+(?<Year>\d+)#',
        ];

        foreach ($in as $item) {
            if (preg_match($item, $str, $m)) {
                $month = (int) $m['Month'];

                return ($month !== 0) ? $m['Month'] . '/' . $m['Day'] . '/' . $m['Year'] : $m['Day'] . ' ' . $this->monthNameToEnglish($m['Month']) . ' ' . $m['Year'];
            }
        }

        return $str;
    }
}
