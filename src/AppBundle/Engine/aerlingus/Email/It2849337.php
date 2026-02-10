<?php

namespace AwardWallet\Engine\aerlingus\Email;

// it-2849337.eml, it-4560307.eml

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class It2849337 extends \TAccountCheckerExtended
{
    public $mailFiles = "aerlingus/it-2849337.eml, aerlingus/it-4560307.eml";

    public $lang = 'en';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $recordLocators = $this->http->FindNodes('//td[string-length(normalize-space(.))=6]');

                        foreach ($recordLocators as $recordLocator) {
                            if (preg_match('/([A-Z\d]{6})/', $recordLocator)) {
                                return $recordLocator;
                            }
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return array_filter([re("#Hi\s+(.+?),#")]);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $nodes = xpath("//img[contains(@src, 'outbound_icon.png') or contains(@src, 'inbound_icon.png')]/ancestor::table[2]");

                        if ($nodes->length == 0) {
                            $nodes = xpath("//text()[translate(normalize-space(), '0123456789', 'dddddddddd') = 'dd:dd']/ancestor::table[4][count(.//text()[translate(normalize-space(), '0123456789', 'dddddddddd') = 'dd:dd']) = 2]");
                        }

                        return $nodes;
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#(\d+\:\d+\s+\w+,\s+\d+\s+\w+)\s+(.+?),\s*(\w{3})\s*(.+?)\s+\d+:\d+#", re("#.+#s"), $m)) {
                                return [
                                    'DepCode'           => $m[3],
                                    'DepName'           => $m[2],
                                    'DepartureTerminal' => re("#^\s*Terminal (.+)#", $m[4]),
                                    // 'DepDate'           => strtotime(uberDatetime($m[1]), $this->date),
                                    'DepDate'           => $this->normalizeDate($m[1], $this->date),
                                ];
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#\d+\:\d+\s+\w+,.+?\n\s*(\d+\:\d+\s+\w+,\s+\d+\s+\w+)\s+(.+?),\s*(\w{3})\s*(.+)#s", re("#.+#s"), $m)) {
                                return [
                                    'ArrCode'         => $m[3],
                                    'ArrName'         => $m[2],
                                    'ArrivalTerminal' => re("#^\s*Terminal (.+)#", $m[4]),
                                    // 'ArrDate'         => strtotime(uberDatetime($m[1]), $this->date),
                                    'ArrDate'         => $this->normalizeDate($m[1], $this->date),
                                ];
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re('/([A-Z\d]{2})\s*\d+/');
                        },

                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('/[A-Z\d]{2}\s*(\d+)/');
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'aerlingus@fly.aerlingus.com') !== false
                && isset($headers['subject']) && (
                    stripos($headers['subject'], 'Your Upcoming Trip') !== false
                    || stripos($headers['subject'], 'Before You Fly') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href,"//fly.aerlingus.com")]')->length > 0
            || $this->http->XPath->query('//text()[contains(.,"Aer Lingus")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fly.aerlingus.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            // 07:45       Mon, 8 Jan
            '/^\s*(\d{1,2}:\d{2})\s+([-[:alpha:]]+)\s*,\s*(\d{1,2})\s+([[:alpha:]]+)\s*$/u', // Wednesday, May 29
        ];
        $out = [
            '$2, $3 $4 ' . $year . ', $1',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
        // $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
