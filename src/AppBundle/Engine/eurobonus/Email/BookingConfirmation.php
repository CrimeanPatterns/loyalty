<?php

namespace AwardWallet\Engine\eurobonus\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "eurobonus/it-1557734.eml, eurobonus/it-1591387.eml, eurobonus/it-6884811.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $prefixes = [
                            'Referanse',
                            'Referens',
                        ];

                        return re('#(?:' . implode('|', $prefixes) . '):\s+([-A-Z\d]{5,})#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $prefixes = [
                            'Fly',
                            'Flyg',
                        ];
                        $xpath = '//text()[' . $this->eq($prefixes) . ']/ancestor::tr[1]';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\w+\s+(\d+)#");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#\b([A-Z\d]{2})\s+(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $prefixesDep = [
                                'Avreise',
                                'Avresa',
                            ];
                            $prefixesArr = [
                                'Ankomst',
                            ];

                            foreach (['Dep' => $prefixesDep, 'Arr' => $prefixesArr] as $key => $value) {
                                $subj = node('./following-sibling::tr[' . $this->contains($value) . '][1]');

                                if (preg_match('#:\s+(.*)\s+\((\w{3})\)#', $subj, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                }

                                $subj = node('./following-sibling::tr[' . $this->contains($value) . '][1]/following-sibling::tr[1]');
                                $regex = '#';
                                $regex .= '\w+\s+(?P<Date>\d+\.\d+\.\d+),\s+';
                                $regex .= 'kl\.\s+(?P<Time>\d+:\d+)';
                                $regex .= '(?:,\s+(?P<Terminal>Terminal:\s+\w+))?';
                                $regex .= '#';

                                if (preg_match($regex, $subj, $m)) {
                                    $res[$key . 'Date'] = strtotime($m['Date'] . ', ' . $m['Time']);

                                    if (isset($m['Terminal'])) {
                                        //$res[$key.'Name'] .= ' ('.$res[$key.'Name'].')';
                                        $res[['Dep' => 'Departure', 'Arr' => 'Arrival'][$key] . 'Terminal'] = $m['Terminal'];
                                    }
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $prefixesAircraft = [
                                'Transportmiddel',
                                'Transportmedel',
                            ];
                            $prefixesClass = [
                                'Klasse',
                                'Klass',
                            ];
                            $subj = node('./following-sibling::tr[' . $this->contains($prefixesAircraft) . '][1]');

                            if (preg_match('#(?:' . implode('|', $prefixesAircraft) . '):\s+(.*),\s+(?:' . implode('|', $prefixesClass) . '):\s+(\w)#', $subj, $m)) {
                                return ['Aircraft' => $m[1], 'BookingClass' => $m[2]];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $result = [];
                            $prefixes = [
                                'Reisetid',
                                'Restid',
                            ];
                            $subj = node('./following-sibling::tr[' . $this->contains($prefixes) . '][1]');

                            if (preg_match('#(?:' . implode('|', $prefixes) . '):\s+(\d+:\d+)\s*(?:Sete:\s+(\w+))?#', $subj, $m)) {
                                $result['Duration'] = $m[1];

                                if (!empty($m[2])) {
                                    $result['Seats'] = $m[2];
                                }

                                return $result;
                            }
                        },
                    ],

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $prefixes = [
                            'Reiserute\s+for',
                            'Bokningsbekräftelse\s+för',
                        ];
                        $passenger = re('/(?:' . implode('|', $prefixes) . ')\s+([^,]+),/');

                        return [$passenger];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $prefixes = [
                            'Total\s+flypris\s+for\s+hele\s+bestillingen\s+er',
                        ];
                        $subj = re('/(?:' . implode('|', $prefixes) . ')\s+(\w+\s+[.\d]+)/');

                        return ['TotalCharge' => cost(str_replace('.', '', $subj)), 'Currency' => currency($subj)];
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@sas\.(no|se)/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'web.reservation@sas.no') !== false
            || stripos($headers['from'], 'donotreply@sas.se') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@sas.no") or contains(.,"bedrift.sas.no") or contains(.,"@sas.se") or contains(normalize-space(.),"SAS Corporate Booking")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//bedrift.sas.no")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(.,"Ankomst:")]')->length > 0) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['no', 'sv'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
