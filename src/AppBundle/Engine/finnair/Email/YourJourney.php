<?php

namespace AwardWallet\Engine\finnair\Email;

class YourJourney extends \TAccountCheckerExtended
{
    public $mailFiles = "finnair/it-3141114.eml, finnair/it-3141122.eml, finnair/it-4083274.eml, finnair/it-4089706.eml, finnair/it-4145478.eml, finnair/it-4189957.eml, finnair/it-4202673.eml";

    public $lang = 'en';
    public $reSubject = [
        'en' => 'Your journey to',
        'fi' => 'Lentosi kohteeseen',
        'fi' => 'Finnair - Ennen matkaa',
        'sv' => ': Ditt flyg till ',
    ];
    public $reHtml = [
        'en' => [
            'Don\'t forget to tailor your trip with our extensive services!',
            'Get the most out of your journey to',
        ],
        'fi' => [
            'Matkasi alkaa pian. Tee lähdöstäsi',
            'Matkan ensimmäinen Finnairin lento',
        ],
        'sv' => [
            'Om några dagar flyger du till',
        ],
    ];

    private $dictionary = [
        'en' => [
            'reservation' => 'reservation\s+number:\s+(\w+)',
            'flight'      => 'flight',
            'travelClass' => 'travel class',
            'departName'  => '\)\s+departs\s+from\s+(.*?)\s+airport\s+at',
            'arriveName'  => 'our journey to\s+(.*?)\s+on\s*\d+',
            'departDate'  => 'departs',
            'arriveDate'  => 'arrives',
        ],
        'fi' => [
            'reservation' => 'varausnumero:\s+(\w+)',
            'flight'      => 'lento',
            'travelClass' => 'matkustusluokka',
            'departName'  => '\)\s+lähtee\s+lentoasemalta\s+(.*?)\s+kello',
            'arriveName'  => 'Katso,\s+mitä\s+kaikkea\s+(.*?)\s+voi\s+sinulle\s+tarjota!',
            'arriveName2' => 'kohteeseen\s+(.*?)\s+\d',
            'departDate'  => 'lähtee',
            'arriveDate'  => 'saapuu',
        ],
        'sv' => [
            'reservation' => 'bokningsnummer:\s+(\w+)',
            'flight'      => 'flyg',
            'travelClass' => 'reseklass',
            'departName'  => '\)\s+avgår\s+från\s+flygplatsen(?:\s+i)?\s+(.*?)\s+kl\.',
            'arriveName'  => 'Om\s+några\s+dagar\s+flyger\s+du\s+till\s+(.*?)\s+\d',
            'arriveName2' => 'Ditt flyg till\s+(.*?)\s+den',
            'departDate'  => 'avgår',
            'arriveDate'  => 'anländer',
        ],
    ];

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
                        return re('#' . $this->dictionary[$this->lang]['reservation'] . '#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $result = null;
                            $nodes = nodes('(//tr[contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), '
                                    . '"' . $this->dictionary[$this->lang]['flight'] . '") '
                                    . 'and contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), '
                                    . '"' . $this->dictionary[$this->lang]['travelClass'] . '") and not(.//tr)])[1]/following-sibling::tr[1]/td');

                            if (preg_match('#^([A-Z]{2})\s*(\d{3,4})#i', $nodes[0], $m)) {
                                $result['AirlineName'] = $m[1];
                                $result['FlightNumber'] = $m[2];
                            }

                            if (preg_match('#^((?:\d+[A-Z],?\s*)+)$#i', $nodes[1], $m)) {
                                $result['Seats'] = $m[1];
                            }

                            if (preg_match('#^\w.*$#i', $nodes[2], $m)) {
                                $result['Cabin'] = $m[0];
                            }

                            return $result;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re('#' . $this->dictionary[$this->lang]['departName'] . '#u');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $result = null;
                            $nodes = nodes('(//tr[contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $this->dictionary[$this->lang]['departDate'] . '") '
                                    . 'and contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $this->dictionary[$this->lang]['arriveDate'] . '") and not(.//tr)])[1]/following-sibling::tr[1]/td');

                            foreach (['Dep' => 0, 'Arr' => 1] as $key => $index) {
                                // 03.06.14 18:10
                                $result[$key . 'Date'] = strtotime(preg_replace('/^(\d+)\.(\d+)\.(\d+)/', '$3-$2-$1', $nodes[$index]));
                            }

                            return $result;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->dictionary[$this->lang]['arriveName'])) {
                                $name = re('#' . $this->dictionary[$this->lang]['arriveName'] . '#ui');
                            }

                            if (empty($name) && isset($this->dictionary[$this->lang]['arriveName2'])) {
                                // LIPPONEN MARKO  : Lentosi kohteeseen  Singapore 23.11.14
                                $name = re('#' . $this->dictionary[$this->lang]['arriveName2'] . '#ui', $this->parser->getSubject());

                                if (empty($name)) {
                                    $name = re('#' . $this->dictionary[$this->lang]['arriveName2'] . '#ui');
                                }
                            }

                            return $name;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $lang => $rules) {
            if (isset($headers['from']) && stripos($headers['from'], 'noreply.customerservice@finnair.com') !== false
                    && isset($headers['subject']) && stripos($headers['subject'], $rules) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->reHtml as $lang => $rules) {
            foreach ($rules as $rule) {
                if ($this->http->XPath->query('//*[contains(text(), "' . $rule . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@finnair.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fi', 'sv'];
    }
}
