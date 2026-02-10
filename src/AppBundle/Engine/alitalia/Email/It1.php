<?php

namespace AwardWallet\Engine\alitalia\Email;

/**
 * it-1.eml, it-1674188.eml, it-2.eml, it-4162763.eml.
 */
class It1 extends \TAccountCheckerExtended
{
    public $mailFiles = "alitalia/it-1.eml, alitalia/it-1674188.eml, alitalia/it-2.eml, alitalia/it-4162763.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (stripos($text, 'Passengers')) {
                        $this->lang = 'en';
                    } elseif (stripos($text, 'Passeggeri')) {
                        $this->lang = 'it';
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= '(?:Il\s+tuo\s+codice\s+di\s+prenotazione\s+\(PNR\)\s+è|';
                        $regex .= 'Your\s+booking\s+number\s+\(PNR\)\s+is)\s+';
                        $regex .= '([\w\-]+)';
                        $regex .= '#';

                        return re($regex);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//text()[contains(., 'Passengers') or contains(., 'Passeggeri')]/ancestor::tr[1]/following-sibling::tr[1]//text()[contains(., 'Ticket number') or contains(., 'Numero biglietto')]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $s = node("//text()[contains(., 'TOTAL') or contains(., 'TOTALE')]/ancestor::tr[1]/following-sibling::tr[1]");
                        $total = total($s);

                        if (empty($total['Currency'])) {
                            return total(mb_convert_encoding($s, 'auto', 'UTF-8'));
                        } else {
                            return $total;
                        }
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//text()[contains(., 'Adults fare') or contains(., 'Tariffa adulti')]/ancestor::td[1]/following-sibling::td[2]"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//text()[contains(., 'Taxes') or contains(., 'Tasse')]/ancestor::td[1]/following-sibling::td[2]"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return $this->http->XPath->query("//*[contains(@src, 'fly_icon_medium.png') or contains(text(), 'Terminal')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = join(' ', nodes("./descendant::td[1]//text()"));

                            if (preg_match('#(\w+)\s+(\d+)\s+(.*)#', $subj, $m)) {
                                $an = nice(re('#(?:operated\s+by|Questo\s+volo\s+è\s+operato\s+da)\s+(.*)#'));

                                if (!$an) {
                                    $an = $m[1];
                                }

                                return ['FlightNumber' => $m[2], 'Cabin' => nice($m[3]), 'AirlineName' => $an];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("./descendant::td[3]", $node);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            //*[contains(@src, 'fly_icon_medium.png') or contains(text(), 'Terminal')]/ancestor::tr[1]/ancestor::tr[1]/preceding-sibling::tr[contains(., 'Inbound') or contains(., 'Outbound')]
                            $res = [];
                            $date = node("./ancestor::tr[1]/preceding-sibling::tr[1]//text()"
                                    . "[contains(., 'Inbound') or contains(., 'Outbound') "
                                    . "or contains(., 'Departure') or contains(., 'Return') "
                                    . "or contains(., 'Andata') or contains(., 'Ritorno')]", $node);

                            if (empty($date)) {
                                $date = $this->date;
                            } else {
                                $this->date = $date;
                            }

                            if (preg_match('#(\d+)/(\d+)/(\d{4})#', $date, $m)) {
                                if (strtotime("$m[3]-$m[2]-$m[1]") !== false) {
                                    $date = "$m[3]-$m[2]-$m[1]";
                                } else {
                                    $date = "$m[3]-$m[1]-$m[2]";
                                }
                            }

                            foreach (['Dep' => 2, 'Arr' => 5] as $key => $value) {
                                $subj = node("./descendant::td[$value]");
                                $time = re('#\d+:\d+(?:\s+(?:am|pm))?#i', $subj);
                                $datetimeStr = $date . ', ' . $time;

                                if (preg_match('#([\+\-])\s+(\d+)#', $subj, $m)) {
                                    $datetimeStr .= ' ' . $m[1] . $m[2] . ' day';
                                }
                                $res[$key . 'Date'] = strtotime($datetimeStr);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("./descendant::td[6]");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return (float) re('#[0-9]+#', node("./ancestor::tr[1]/following-sibling::tr[1]//text()[contains(., 'How many miles') "
                                            . "or contains(., 'Quante miglia ho')]/ancestor::td[1]/following-sibling::td[1]", $node));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node("./ancestor::tr[1]/following-sibling::tr[1]//text()[contains(., 'How long') "
                                    . "or contains(., 'Quanto dura')]/ancestor::td[1]/following-sibling::td[1]", $node);
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'confirmation@alitalia.com') !== false
                && isset($headers['subject']) && (
                    stripos($headers['subject'], 'Riepilogo dell’acquisto') !== false
                    || stripos($headers['subject'], 'Electronic ticket receipt') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'PAGAMENTO È STATO EFFETTUATO CON SUCCESSO!') !== false
                || stripos($parser->getHTMLBody(), 'Summary timetables, fares and fare rules') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["it", "en"];
    }
}
