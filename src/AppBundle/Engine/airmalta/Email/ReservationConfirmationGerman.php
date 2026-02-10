<?php

namespace AwardWallet\Engine\airmalta\Email;

class ReservationConfirmationGerman extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Vielen Dank, dass Sie sich bei Ihrer Reiseplanung für Air Malta entschieden haben#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#ibe-enquiries@airmalta.com.mt#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]airmalta#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "20.06.2016, 10:41";
    public $crDate = "20.06.2016, 10:03";
    public $xPath = "";
    public $mailFiles = "airmalta/it-3947335.eml";
    public $re_catcher = "#.*?#";
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
                        return re('#Referenz:\s+(\w+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = node('//div[normalize-space(.) = "Passagiere"]/following-sibling::table[1]//td[normalize-space(.) = "Flug"]/preceding-sibling::td[1]');
                        $ticketNumbers = cell('Flugticketnummer', +1);
                        $this->seats = re('#^\d+\w$#i', trim(cell('Sitzplatz', +1)));

                        return [
                            'Passengers'    => $passengers,
                            'TicketNumbers' => $ticketNumbers,
                        ];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#GESAMT:\s*(\d+.*)#'));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re('#Ausstellungsdatum:\s+\w+,\s+(\d+\s+\w+\s+\d+)#')));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//div[normalize-space(.) = "Hinflug"]/following-sibling::table[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('.//tr[1]/td[3]');

                            if (preg_match('#^\s*(\w{2})\s+(\d+)#i', $s, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 1, 'Arr' => 2] as $key => $value) {
                                $s = node('.//tr[1]/td[' . $value . ']');

                                if (preg_match('#(.*)\s+\w{2},\s+(.*)#', $s, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Date'] = strtotime(en($m[2]));
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('.//tr[2]/td[1]');
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re('#Flugmeilen:\s*(.*)#', node('.//tr[2]/td[2]'));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return $this->seats;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Dauer:\s*(.*)#', node('.//tr[2]/td[3]'));
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#Zwischenlandung:\s*(.*)#', node('.//tr[1]/td[4]'));
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
        return false;
    }
}
