<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class PrepareForYourTrip extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?cheaptickets#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#travelercare@cheaptickets\.com#i";
    public $reProvider = "#cheaptickets\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cheaptickets/it-2157973.eml, cheaptickets/it-2192122.eml, cheaptickets/it-2252397.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->recordLocators = [];

                    if (preg_match_all('#\s*(.*)\s+record\s+locator\s*:\s+([\w\-]+)#i', $text, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            if ($m[1] != 'CheapTickets') {
                                $this->recordLocators[$m[1]] = $m[2];
                            }
                        }
                    }
                    $s = re('#Passenger\(s\)\s*:\s+((?s).*?)\s+(?:we\s+will\s+continue|.*record\s+locator)#i');
                    $this->passengers = nice(explode(stripos($s, ',') !== false ? ',' : "\n", $s));
                    $this->totalStr = re('#Total\s+airfare:\s+(.*)#i');

                    return splitter('#(\w+,\s+\w+\s+\d+,\s+\d+\s+.*\#\s+\d+)#i');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $an = re('#\s*(.*)\s+\#\s+\d+#i');

                        if (isset($this->recordLocators[$an])) {
                            return $this->recordLocators[$an];
                        } else {
                            return CONFNO_UNKNOWN;
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#\s*(.*)\s+\#\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $year = re('#\w+,\s+\w+\s+\d+,\s+(\d{4})#i');

                            if (!$year) {
                                return null;
                            }

                            foreach (['Dep' => 'Departure', 'Arr' => 'Arrival'] as $key => $value) {
                                $r = '#' . $value . ':?\s+\((\w{3})\):\s+(\w+\s+\d+),\s+(\d+:\d+\s*(?:am|pm))#i';

                                if (preg_match($r, $text, $m)) {
                                    $res[$key . 'Code'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($m[2] . ', ' . $year . ', ' . $m[3]);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class:\s+(.*)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Seat:\s+(\d+\w)#');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $uniqueFlightSegmentIds = []; // FlightNumber + DepCode
                    $itNew = [];

                    foreach ($it as $i) {
                        if (isset($i['TripSegments'][0])) {
                            $fs = $i['TripSegments'][0];

                            if (isset($fs['FlightNumber']) and isset($fs['DepCode'])) {
                                $id = $fs['FlightNumber'] . '-' . $fs['DepCode'];

                                if (array_search($id, $uniqueFlightSegmentIds) === false) {
                                    $uniqueFlightSegmentIds[] = $id;
                                    $itNew[] = $i;
                                }
                            }
                        }
                    }
                    $itNew = uniteAirSegments($itNew);

                    if (count($itNew) == 1) {
                        $itNew[0]['TotalCharge'] = cost($this->totalStr);
                        $itNew[0]['Currency'] = currency($this->totalStr);
                    }

                    return $itNew;
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
