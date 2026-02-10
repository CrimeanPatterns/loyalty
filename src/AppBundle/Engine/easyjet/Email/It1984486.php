<?php

namespace AwardWallet\Engine\easyjet\Email;

class It1984486 extends \TAccountCheckerExtended
{
    use \DateTimeTools;

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?@easyjet[.]com#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#\beasyJet\s+booking#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#@easyjet[.]com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]easyjet[.]com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = 'en, fr, de';
    public $typesCount = "3";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "27.08.2015, 15:50";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "easyjet/it-1984486.eml, easyjet/it-3021250.eml, easyjet/it-4878129.eml, easyjet/it-4962052.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $q = strtotime($this->parser->getHeader('date'));
                    $this->year = orval(
                        re('/^\s*Date:\s.*?\b(\d{4})\b/m'),
                        date('Y', $q)
                    );

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Booking reference: ([\w-]+)');

                        return orval(
                            re("#$q#i"),
                            re('/easyJet\s+booking\s+([\w-]+)/'),
                            re('/Réservation\s+easyJet\s+([\w-]+)/')
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('((?:Mr|Mrs) .+?) is flying on');
                        $name = orval(
                            re("#$q#i"),
                            re('/\n\s*Hello\s+([^\n]+?)\s*[,.]/i'),
                            re('/\n\s*Bonjour\s+([^\n]+?)\s*[,.]/i'),
                            re('/\n\s*Hallo\s+([^\n]+?)\s*[,.]/i')
                        );

                        return [nice($name)];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Total paid (.?\d+[.]\d+ (?:\w+)?)');
                        $cost = re("#$q#iu");

                        return total($cost);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Booking confirmed');

                        if (re("#$q#i")) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $segments = orval(
                            re('/is flying on:(.+?)Hold baggage and sports equipment/si'),
                            re('/are local times\.(.+?)A significant number of routes/si'),
                            re('/en heures locales\.(.+?)Pour un nombre important de routes/si'),
                            re('/Alle Zeitangaben(.+?)Für einen Großteil/si')
                        );

                        if (preg_match_all('/(-----\n[^\d\s]+\s+\d{2}\s+[^\d\s]+.+?[Aa]rr[^\n]+\d{2}:\d{2}\s*\n)/s', $segments, $matches)) {
                            return $matches[1];
                        } elseif (preg_match_all('/([^\n]+\s+to\s+.+?[Aa]rr[^\n]+\d{2}:\d{2}\s*\n)/s', $segments, $matches)) {
                            return $matches[1];
                        } elseif (preg_match_all('/([^\n]+\s+à\s+.+?[Aa]rr[^\n]+\d{2}:\d{2}\s*\n)/s', $segments, $matches)) {
                            return $matches[1];
                        } elseif (preg_match_all('/([^\n]+\s+nach\s+.+?[Aa]nkunft[^\n]+\d{2}:\d{2}\s*\n)/s', $segments, $matches)) {
                            return $matches[1];
                        } else {
                            return [];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data['AirlineName'] = strlen(re('/(?:flight|vol|flug)\s+(\w*?)\s*(\d+)/i')) > 0 ? re(1) : 'U2';
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = whiten('
								-----
								\w+ \d+ \w+
								(.+?)
								To
							');
                            $name = re('/(?:\-\-\-\-\-\s*\w+\s+\d+\s+\w+)*\s*(\w.+?)\s+(?:to|à|nach)\s+([^\n]+?)\;?\s+(?:flight|vol|flug)/si');
                            $name = nice($name);

                            if (preg_match('/^(.+)\s+\(([^)(]*Terminal[^)(]*)\)$/i', $name, $matches)) {
                                return [
                                    'DepName'           => $matches[1],
                                    'DepartureTerminal' => $matches[2],
                                ];
                            } else {
                                return $name;
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $q = whiten('\b(?:dep|Dép|Abflug)[.]? (.+?)(?:\;|\n)');
                            $dt = re("#$q#i");

                            if (!preg_match("#\b\d{4}\b#", $dt)) {
                                $dt = "$dt {$this->year}";
                            }
                            $dt = preg_replace("#^\s*[^\d\s]+,\s*(\d+)\s+([^\d\s.,]+)\.?\s+(\d{4})#u", '$1 $2 $3', $dt);
                            $dt = uberDateTime($dt);
                            $dt = $this->dateStringToEnglish($dt);

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = orval(
                                between('To', 'flight'),
                                between('à', 'vol'),
                                between('nach', 'flug')
                            );
                            $name = clear('/;/', $name);

                            if (preg_match('/^(.+)\s+\(([^)(]*Terminal[^)(]*)\)$/i', $name, $matches)) {
                                return [
                                    'ArrName'         => $matches[1],
                                    'ArrivalTerminal' => $matches[2],
                                ];
                            } else {
                                return $name;
                            }
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $q = whiten('\b(?:arr|ankunft)[.]? (.+?) (?:Check in opens|\n)');
                            $dt = re("#$q#i");

                            if (!preg_match("#\b\d{4}\b#", $dt)) {
                                $dt = "$dt {$this->year}";
                            }
                            $dt = preg_replace("#^\s*[^\d\s]+,\s*(\d+)\s+([^\d\s.,]+)\.?\s+(\d{4})#u", '$1 $2 $3', $dt);
                            $dt = uberDateTime($dt);
                            $dt = $this->dateStringToEnglish($dt);

                            return strtotime($dt);
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr', 'de'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
