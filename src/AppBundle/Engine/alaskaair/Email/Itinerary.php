<?php

namespace AwardWallet\Engine\alaskaair\Email;

class Itinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#This\s+alaskaair\.com\s+itinerary\s+has\s+been\s+sent#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#alaska\.it@alaskaair\.com#i";
    public $reProvider = "#alaskaair\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "alaskaair/it-2220208.eml, alaskaair/it-2238044.eml, alaskaair/it-2238079.eml";
    public $pdfRequired = "0";

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
                        return orval(
                            re('#Confirmation\s+Code:\s+([\w\-]+)#i'),
                            CONFNO_UNKNOWN
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= 'Name:\s+(?P<Name>.*)\s*';
                        $r .= '(?:Ticket\s+Number:\s+.*\s+)?';
                        $r .= '(?:Base\s+Fare:\s+(?P<BaseFare>.*)\s+)?';
                        $r .= '(?:Tax:\s+(?P<Tax>.*)\s+)?';
                        $r .= '(?:Total:\s+(?P<TotalCharge>.*)\s+)?';
                        $r .= '(?:Seats:\s+(?P<Seats>.*)\s+)?';
                        $r .= '#';

                        if (preg_match_all($r, $text, $m)) {
                            $res['Passengers'] = $m['Name'];

                            foreach ($res['Passengers'] as &$p) {
                                $p = beautifulName($p);
                            }
                            $keys = ['BaseFare', 'Tax', 'TotalCharge'];

                            foreach ($keys as $k) {
                                if ($m[$k]) {
                                    foreach ($m[$k] as $v) {
                                        if (!isset($res[$k])) {
                                            $res[$k] = null;
                                        }

                                        if ($v) {
                                            $res[$k] += cost($v);
                                        }
                                    }
                                }
                            }

                            if (isset($res['TotalCharge'])) {
                                $res['Currency'] = currency($m['TotalCharge'][0]);
                            }

                            if ($m['Seats']) {
                                $this->seats = null;

                                foreach ($m['Seats'] as $s) {
                                    if (re('#(?:\d+[A-Z],?\s*)+#i', $s)) {
                                        $this->seats[] = nice(explode(',', $s));
                                    }
                                }
                            }

                            return $res;
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= '(?P<Date>\w+\s+\d+\s+\d{4})\s+';
                        $r .= '(?P<AirlineName>.*)\s+Flight\s+(?P<FlightNumber>\d+)\s+';
                        $r .= 'Depart:\s+(?P<DepName>.*)\s+at\s+(?P<DepTime>\d+:\d+\s*(?:am|pm)|noon)\s+';
                        $r .= 'Arrive:\s+(?P<ArrName>.*)\s+at\s+(?P<ArrTime>\d+:\d+\s*(?:am|pm)|noon)';
                        $r .= '#i';
                        $this->flightSegmentRegex = $r;

                        if (preg_match_all($this->flightSegmentRegex, $text, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match($this->flightSegmentRegex, $text, $m)) {
                                $res = null;

                                foreach (['DepName', 'ArrName', 'AirlineName', 'FlightNumber'] as $k) {
                                    $res[$k] = $m[$k];
                                }

                                foreach (['Dep', 'Arr'] as $k) {
                                    $res[$k . 'Date'] = strtotime($m['Date'] . ', ' . $m[$k . 'Time']);
                                }
                                //	if (isset($res['DepDate']) and isset($res['ArrDate'])) correctDates($res['DepDate'], $res['ArrDate']);
                                return $res;
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            static $flightNumberIndex = 0;
                            $seats = null;

                            if (isset($this->seats)) {
                                foreach ($this->seats as $s) {
                                    if ($s and isset($s[$flightNumberIndex])) {
                                        $seats[] = $s[$flightNumberIndex];
                                    }
                                }
                            }
                            $flightNumberIndex++;

                            return $seats;
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
