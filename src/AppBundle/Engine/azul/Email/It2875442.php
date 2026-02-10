<?php

namespace AwardWallet\Engine\azul\Email;

class It2875442 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#da Azul.+?VOO\b#si', 'blank', '3000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Itinerario Azul', 'blank', ''],
    ];
    public $reFrom = [
        ['#voeazul\.com\.br#', 'blank', ''],
    ];
    public $reProvider = [
        ['#voeazul\.com#', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "11.10.2015, 18:38";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "azul/it-2875442.eml, azul/it-3137578.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $subj = $this->parser->getHtmlBody();
                    $subj = preg_replace('#<META[^>]+>#i', '', $subj);
                    $this->http->setBody($subj);
                    $this->seatsByFlight = [];

                    if (preg_match_all("#\s*(\w+)\s+Passageiros\s(.+?)(?=VOLTA|Total)#su", $this->text(), $m1, PREG_SET_ORDER)) {
                        foreach ($m1 as $m1row) {
                            if (preg_match_all("#\s\s*([A-Z]+)\s*\-\s*([A-Z]+)\s+(?:[^\n]+\s+)??(\d+[A-Z]+)#s", $m1row[2], $m2, PREG_SET_ORDER)) {
                                foreach ($m2 as $m2row) {
                                    $key = $m1row[1] . $m2row[1] . $m2row[2];

                                    if (!isset($this->seatsByFlight[$key])) {
                                        $this->seatsByFlight[$key] = [];
                                    }
                                    $this->seatsByFlight[$key][] = $m2row[3];
                                }
                            }
                        }
                    }
                    //print_r(nodes('//*'));
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#C\s*ó\s*digo\s+localizador\s+([A-Z0-9]+)#u');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $data = nodes("//*[contains(text(), 'Passageiros')]/ancestor::table[3]/ancestor::tr[1]/following-sibling::tr[1]/descendant::table[1]/tbody/tr[not(./td[1][contains(@bgcolor,'FFFFFF')])]/td[2]");
                        $passengers = [];

                        foreach ($data as $d) {
                            if (re('#Conexão#i', $d)) {
                                continue;
                            } else {
                                $passengers[] = $d;
                            }
                        }

                        return array_merge(array_unique($passengers));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//*[contains(text(), 'Total')]/ancestor::td[1]/following-sibling::td[1]"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[contains(., "Forma de Pagamento") and not(.//tr)]/following-sibling::tr[1]/td[4]');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsXRaw = xpath("//*[contains(text(), 'VOO')]/ancestor::table[1]//tr[not(.//tr) and contains(normalize-space(.), 'VOO - ')]");
                        $its = [];

                        for ($i = 0; $i < $itsXRaw->length; $i++) {
                            $its[] = text(xpath("./preceding-sibling::tr[starts-with(normalize-space(.),'Ida') or starts-with(normalize-space(.),'Volta')]", $itsXRaw->item($i)))
                                . "\n\n" . text(xpath("./preceding-sibling::tr[1]/following-sibling::tr", $itsXRaw->item($i)));
                        }

                        return $its;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['AirlineName'] = re("#VOO\s+-\s+([A-Z]+)\s*(\d+)#ms");
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]+)\)\s+\d+:\d+#ms");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $date = en(str_replace("/", "-", re("#(?>Ida|Volta)\s+(\d+\D\w+\D\d+)#i")), 'pt');
                            $data['DepDate'] = totime($date . ' ' . re("#\s+(\d+:\d+)#ms"));
                            $data['ArrDate'] = totime($date . ' ' . re("#\d+:\d+.+?\s+(\d+:\d+)#ms"));
                            correctDates($data['DepDate'], $data['ArrDate']);

                            return $data;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\([A-Z]+\)\s+\d+:\d+.+?\(([A-Z]+)\)\s+\d+:\d+#ms");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $direction = strtoupper(re("#(Ida|Volta)#i"));
                            $key = $direction . $it['DepCode'] . $it['ArrCode'];

                            if (isset($this->seatsByFlight[$key])) {
                                return implode(', ', $this->seatsByFlight[$key]);
                            }
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
}
