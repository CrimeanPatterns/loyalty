<?php

namespace AwardWallet\Engine\amextravel\Email;

class ETicket extends \TAccountCheckerExtended
{
    public $rePlain = "#AMEX RAANANA,IL#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amex-travel\.co\.il#i";
    public $reProvider = "#amex-travel\.co\.il#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2110631.eml, amextravel/it-2110666.eml, amextravel/it-2110683.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#Sent:.*\s+(\d{4})\s+#i');

                    if (!$this->year) {
                        return;
                    }

                    return xpath('//tr[normalize-space(.) = "AIR"]/ancestor::table[1]');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Airline\s+REF:\s+(\S+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengerInfoNodes = xpath('.//tr[contains(., "NAME") and contains(., "SEAT") and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 1]');
                        $passengers = null;
                        $accountNumbers = null;
                        $this->currentSeats = null;
                        $this->currentMeal = null;

                        foreach ($passengerInfoNodes as $n) {
                            if ($p = node('./td[1]', $n)) {
                                $passengers[] = $p;
                            }

                            if ($s = node('./td[2]', $n)) {
                                $this->currentSeats[] = $s;
                            }

                            if ($m = node('./td[3]', $n)) {
                                $this->currentMeal[] = $m;
                            }

                            if ($an = node('./td[last()]', $n)) {
                                $accountNumbers[] = $an;
                            }
                        }

                        return [
                            'Passengers'     => $passengers,
                            'AccountNumbers' => $accountNumbers ? implode(', ', $accountNumbers) : null,
                        ];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(CONFIRMED)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (re('#\s+(\w{2})\s+(\d+)\s+(?:Status|Operating)#i')) {
                                return [
                                    'AirlineName'  => re(1),
                                    'FlightNumber' => re(2),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'From:', 'Arr' => 'To:'] as $key => $value) {
                                if (preg_match('#(\d+)(\w+)\s+(\d{1,2})(\d{2})#i', cell($value, +1) . ' ' . cell($value, +3), $m)) {
                                    $res[$key . 'Date'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $this->year . ', ' . $m[3] . ':' . $m[4]);
                                }
                                $point = cell($value, +2);

                                if (re('#(.*)\s+(\w{3})$#i', $point)) {
                                    $res[$key . 'Code'] = re(2);
                                    $res[$key . 'Name'] = re(1);
                                } else {
                                    $res[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                                    $res[$key . 'Name'] = $point;
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#Airplane:\s+(\w.*)\s+Stop#');
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re('#Miles:\s+([\d,]+)#');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re('#Class:\s+(\w)\s+#');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Duration:\s+(\d+\.\d+)#');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#Stop:\s+(\d+)#');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
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
