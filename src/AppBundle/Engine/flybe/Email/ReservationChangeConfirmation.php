<?php

namespace AwardWallet\Engine\flybe\Email;

class ReservationChangeConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Ihre Flybe-Flugbuchungsnummer#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Bestätigung der Änderungen an Ihrer Flybe-Buchung', 'blank', ''],
    ];
    public $reFrom = [
        ['NO_REPLY@flybe.com', 'blank', ''],
    ];
    public $reProvider = [
        ['flybe.com', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "01.07.2016, 05:31";
    public $crDate = "01.07.2016, 05:11";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re('#Ihre Flybe-Flugbuchungsnummer lautet\s+(\w+)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Flugdatum") and contains(., "Flugnummer") and not(.//tr)]/following-sibling::tr');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[1]');

                            if (preg_match('#(\w{2})\s+(\d+)#i', $s, $m)) {
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
                            return node('./td[3]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $d = en(node('./td[2]'));

                            foreach (['Dep' => 5, 'Arr' => 6] as $key => $value) {
                                $t = node('./td[' . $value . ']');
                                $res[$key . 'Date'] = strtotime($d . ', ' . $t);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[4]');
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
