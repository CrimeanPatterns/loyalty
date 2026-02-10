<?php

namespace AwardWallet\Engine\amadeus\Email;

class It2893892 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?amadeus#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Booking Confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]amadeus#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]amadeus\.#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "16.07.2015, 18:19";
    public $crDate = "16.07.2015, 13:15";
    public $xPath = "";
    public $mailFiles = "amadeus/it-2893892.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $this->baseDate = 0;
                    $this->keyOfSeatsArray = ['', '', ''];
                    $this->seatsByFlights = [];
                    $str = re("#\n\s*Seats\s+and\s+Meals\s+((?:[^\n]+\s+)*)(?!\n\s*Flight\s+\d+\s*:)#i");
                    $regexp = "#\bFrom\s.+?\((\w{3})\)\s+to\s+.+?\((\w{3})\)\s+-\s+.+?(\d+\s+\w+\s+\d{4}).+?"
                             . "\sSeats?:\s*(?P<Seats>.+?)\s+Meal:\s*(?P<Meal>.+)#";

                    if (preg_match_all($regexp, $str, $m, PREG_SET_ORDER)) {
                        foreach ($m as $rowData) {
                            $arKey = $rowData[1] . $rowData[2] . preg_replace("#\s+#", "-", $rowData[3]);
                            $this->seatsByFlights[$arKey] = ['Seats' => null, 'Meal' => null];

                            if (isset($rowData['Seats']) && preg_match_all("#\b(\d+[A-Z])\b#", $rowData['Seats'], $sm)) {
                                $this->seatsByFlights[$arKey]['Seats'] = implode(", ", $sm[1]);
                            }

                            if (isset($rowData['Meal'])) {
                                $this->seatsByFlights[$arKey]['Meal'] = $rowData['Meal'];
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
                        return re("#Booking\s+reference\s*:\s*([\w-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all("#\n*\s*([^\n]+?)\s{2,}Phone\s*:#", re("#\n\s*Passenger\s+\d*\s*(.+?)Seats\s+and\s+Meals#s"), $m)) {
                            return $m[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Total\s+Price\s*.+?\n\s*Total\s*:\s*([^\n]+)#s"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsRaw = re("#\n\s*Your\s+Itinerar(?:y|ies)\s*\n(.+?)(?>Total\s+duration|Passenger\s+\d)#si");

                        return splitter("#(\s*[^\n]+\n\s*\w+ +\d+ +\w{3}\s+\d+:\d+)#", $itsRaw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['AirlineName'] = re("#\s*([A-Z]{2,4})(\d+)\s#");
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['DepName'] = re("#\d+:\d+\s+(?>\|\s*\d+\s+\w+\s+)?(.+?)\s*(?>,\s*\w{3}\s*){2},.+?\((\w{3})\)#");
                            $data['DepCode'] = re(2);
                            $this->keyOfSeatsArray[0] = $data['DepCode'];

                            return $data;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['DepDate'] = timestamp_from_format(re("#\n\s*(\w+\s+\d+\s+\w+)\s+(\d+:\d+)\s*(\|\s*\d+\s+\w+\s+)?.+?\n\s*(\d+:\d+)\s*(\|\s*\d+\s+\w+\s+)?#s") . ", " . re(2) . ', ' . date("Y", $this->date), "D d M, H:i, Y");
                            $data['ArrDate'] = timestamp_from_format(re(1) . ", " . re(4) . ', ' . date("Y", $this->date), "D d M, H:i, Y");

                            if ($data['ArrDate'] < $data['DepDate']) {
                                $data['ArrDate'] = strtotime("+1 day", $data['ArrDate']);
                            }

                            return $data;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['ArrName'] = re("#\n\s*\d+:\d+\s.+?\n\s*\d+:\d+\s+(?>\|\s*\d+\s+\w+\s+)?([^\n]+?)\s*(?>,\s*\w{3}\s*){2},[^\n]+?\((\w{3})\)#s");
                            $data['ArrCode'] = re(2);
                            $this->keyOfSeatsArray[1] = $data['ArrCode'];

                            return $data;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\sAircraft\s*:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['Cabin'] = re("#\sCabin\s*:\s*([^\n]+?)\s+\((\w{1,3})\)#");
                            $data['BookingClass'] = re(2);

                            return $data;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->seatsByFlights[implode("", $this->keyOfSeatsArray)])) {
                                return $this->seatsByFlights[implode("", $this->keyOfSeatsArray)]['Seats'];
                            }
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->seatsByFlights[implode("", $this->keyOfSeatsArray)])) {
                                return $this->seatsByFlights[implode("", $this->keyOfSeatsArray)]['Meal'];
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
