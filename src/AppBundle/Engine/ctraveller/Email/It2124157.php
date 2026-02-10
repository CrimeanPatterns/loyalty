<?php

namespace AwardWallet\Engine\ctraveller\Email;

class It2124157 extends \TAccountCheckerExtended
{
    public $mailFiles = "ctraveller/it-2124157.eml, ctraveller/it-6673140.eml";

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
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(),'for approval')]/following::span[1]");

                        return [nice($name)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(),'DEPART:')]/ancestor-or-self::span[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding::span[not(contains(.,"OPERATED BY"))][1]');
                            $fl = re_white('FLIGHT\s+([A-Z\d]{2}\s*\d+)', $info);

                            return uberAir($fl);
                        },

                        "Operator" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding::span[1]');
                            $operator = re_white('OPERATED BY: (.+)', $info);

                            return nice($operator);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = re_white('DEPART: (.+?)  (?:\d+)');

                            return nice($name);
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            $nextRow = node('./following::span[1]');
                            $terminalDep = re_white('TERMINAL\s+([\w\s]+)', $nextRow);

                            return nice($terminalDep);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = re_white('\s+ (\d+(?:P|A)) \s+');

                            if (strlen($time) === 4) { // for proper parsing
                                $time = '0' . $time;
                            }
                            $time .= 'M'; // missing 'M' in AM/PM

                            $dt = "$date, $time";
                            $dt = timestamp_from_format($dt, 'd M y, hia');
                            $this->dep_dt = $dt;

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::span[contains(.,"ARRIVE")][1]');
                            $name = re_white('ARRIVE: (.+?)  (?:\d+)', $info);

                            return nice($name);
                        },

                        "ArrivalTerminal" => function ($text = '', $node = null, $it = null) {
                            $nextRow = node('./following::span[contains(.,"ARRIVE")][1]/following::span[1]');
                            $terminalArr = re_white('TERMINAL\s+([\w\s]+)', $nextRow);

                            return nice($terminalArr);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::span[contains(.,"ARRIVE")][1]');

                            $date = uberDate($info, 1);

                            if (empty($date)) {
                                $date = uberDate(1);
                            }

                            if (!preg_match('/\s+\d{2,4}\s*$/', $date)) {
                                $date .= ' ' . substr(getdate($this->dep_dt)['year'], -2);
                            }

                            $time = re_white('\s+ (\d+(?:P|A)) \s+', $info);

                            if (strlen($time) === 4) { // for proper parsing
                                $time = '0' . $time;
                            }
                            $time .= 'M'; // missing 'M' in AM/PM

                            $dt = "$date, $time";
                            $dt = timestamp_from_format($dt, 'd M y, hia');

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::span[contains(.,"AIRCRAFT TYPE:")][1]');
                            $x = re_white('TYPE: (.+)', $info);

                            return nice($x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding::span[not(contains(.,"OPERATED BY"))][1]');

                            return re_white('([A-Z]{2,}) (?:CLS|CLASS)', $info);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::span[contains(.,"FLIGHT DURATION:")][1]');
                            $x = re_white('DURATION: (.+)', $info);

                            return nice($x);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (re_white('NONSTOP')) {
                                return 0;
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@corporatetraveller.ca') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/\w{3,}@corporatetraveller[.]ca/i', $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(.,"Manager Corporate Traveller") or contains(.,"@corporatetraveller.ca") or contains(.,"www.corporatetraveller.ca")] | //a[contains(@href,"//www.corporatetraveller.ca")]')->length === 0) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(.,"FLIGHT") and contains(.,"DEPART") and contains(.,"ARRIVE")]')->length > 0) {
            return true;
        }

        return false;
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
}
