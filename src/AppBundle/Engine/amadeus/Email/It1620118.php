<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1620118 extends \TAccountCheckerExtended
{
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $rePlain = "#\n[>\s*]*(From|Von)\s*:[^\n]*?amadeus#i";
    public $typesCount = "1";
    public $langSupported = "de";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1620118.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";
    public $allText;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->allText = $text;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Reservierungsnummer|Booking\s+reservation\s+number)\s*:\s*([^\n]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#(?:ANGABEN ZUM REISENDEN|TRAVELLER INFORMATION)[*\s]+\s+([^\n]+)#")];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*(?:Vielflieger|Frequent flyer):\s*([^\n]+)#")];
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*(?:Ticket)\s*([\d\-]+)#")];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*((?:Flug|Flight)\s+\d+\s*\-\s*\w+,\s*(?:\d+[.\s]+\w+|\w+\s+\d+,?)\s+\d{4})#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*(?:Fluggesellschaft|Airline)\s*:\s*[^\n]*? ([A-Z\d]{2})(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate());
                            re("#\n\s*(?:Abreise|Departure)\s*:\s*(\d+:\d+)\s*\-\s*([^\n]+)#");
                            $dep = strtotime($date . ', ' . re(1));
                            $depName = re(2);

                            if (preg_match("#(.+),([^,]*terminal[^,]*)#i", $depName, $m)) {
                                $depName = trim($m[1]);
                                $depTerm = trim($m[2]);
                            } else {
                                $depTerm = null;
                            }

                            re("#\n\s*(?:Ankunft|Arrival)\s*:\s*(\d+:\d+)(?:\s+\+(\d+)\s+day\(s\))?\s*\-\s*([^\n]+)#");
                            $arr = strtotime($date . ', ' . re(1));

                            if (!empty(re(2))) {
                                $arr = strtotime("+ " . re(2) . "day", $arr);
                            }
                            $arrName = re(3);

                            if (preg_match("#(.+),([^,]*terminal[^,]*)#i", $arrName, $m)) {
                                $arrName = trim($m[1]);
                                $arrTerm = trim($m[2]);
                            } else {
                                $arrTerm = null;
                            }

                            correctDates($dep, $arr);

                            return [
                                'DepDate'           => $dep,
                                'DepName'           => $depName,
                                'DepartureTerminal' => $depTerm,
                                'ArrDate'           => $arr,
                                'ArrName'           => $arrName,
                                'ArrivalTerminal'   => $arrTerm,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(?:Flugzeug|Aircraft)\s*:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(?:Tariftyp|Fare type)\s*:\s*([^\n]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Dauer\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(?:Mahlzeit|Meal)\s*:\s*([^\n]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $pos = stripos($this->allText, 'SONDERANFRAGEN FÜR FLUG');

                            if (empty($pos)) {
                                $pos = stripos($this->allText, 'FLIGHT SPECIAL REQUESTS');
                            }

                            if (empty($pos)) {
                                $pos = 0;
                            }
                            $info = substr($this->allText, $pos);

                            if (preg_match("#\n\s*(?:Flug|Flight)\s+\d+\s*:\s*" . explode(",", $it['DepName'])[0] . "\s*-\s*" . explode(",", $it['ArrName'])[0] . "\s*:\s*(\d{1,3}[A-Z])#", $info, $m)) {
                                return [$m[1]];
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
