<?php

namespace AwardWallet\Engine\azul\Email;

class It2742828 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\bAzul\b.+?Outbound[\s\*]+\d+\/\w+\/\d+#si', 'blank', '3000'],
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
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "30.07.2015, 21:56";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "azul/it-2742828.eml, azul/it-2861679.eml, azul/it-2934040.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->seatsByFlight = [];

                    if (preg_match_all("#\s*(\w+)\s+Passengers\s(.+?)(?=RETURN|Total)#s", $this->text(), $m1, PREG_SET_ORDER)) {
                        foreach ($m1 as $m1row) {
                            if (preg_match_all("#\n\s*([A-Z]+)\s*\-\s*([A-Z]+)\s+(?:[^\n]+\s+)??(\d+[A-Z]+)#s", $m1row[2], $m2, PREG_SET_ORDER)) {
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

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Locator code')]/following-sibling::*[1]");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $data = nodes("//*[contains(text(), 'Passengers')]/ancestor::table[3]/ancestor::tr[1]/following-sibling::tr[1]/descendant::table[1]/tbody/tr[not(./td[1][contains(@bgcolor,'FFFFFF')])]/td[2]");

                        return array_merge(array_unique($data));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//*[contains(text(), 'Total')]/ancestor::td[1]/following-sibling::td[1]"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsXRaw = xpath("//*[contains(text(), 'FLIGHT')]/ancestor::table[1]//tr[not(.//tr) and contains(normalize-space(.), 'FLIGHT - ')]");
                        $its = [];

                        for ($i = 0; $i < $itsXRaw->length; $i++) {
                            $its[] = text(xpath("./preceding-sibling::tr[starts-with(normalize-space(.),'Outbound') or starts-with(normalize-space(.),'Return')]", $itsXRaw->item($i)))
                                . "\n\n" . text(xpath("./preceding-sibling::tr[1]/following-sibling::tr", $itsXRaw->item($i)));
                        }

                        return $its;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['AirlineName'] = re("#FLIGHT\s+-\s+([A-Z]+)\s*(\d+)#ms");
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]+)\)\s+\d+:\d+#ms");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $date = str_replace("/", ".", re("#(?>Outbound|Return)\s+(\d+\D\w+\D\d+)#i"));
                            $data['DepDate'] = $date . ' ' . re("#\s+(\d+:\d+)#ms");
                            $data['ArrDate'] = $date . ' ' . re("#\d+:\d+.+?\s+(\d+:\d+)#ms");
                            correctDates($data['DepDate'], $data['ArrDate']);

                            return $data;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\([A-Z]+\)\s+\d+:\d+.+?\(([A-Z]+)\)\s+\d+:\d+#ms");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $direction = strtoupper(re("#(Outbound|Return)#i"));
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
