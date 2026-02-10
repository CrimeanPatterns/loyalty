<?php

namespace AwardWallet\Engine\budgetair\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#E-mail: mailto:info@budgetair.nl#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]budgetair#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]budgetair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "nl";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.06.2016, 08:25";
    public $crDate = "28.06.2016, 06:45";
    public $xPath = "";
    public $mailFiles = "budgetair/it-3968122.eml";
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
                        return re('#Airline referentie:\s+(\w+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $pi = nodes('//tr[contains(., "Passagier ") and not(.//tr)]/following-sibling::tr[1]');
                        $pi = preg_replace('#\s*\(.*\)#i', '', $pi);

                        return $pi;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Totaal', +1));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//img[contains(@src, "all_icons_airplane1.png")]/ancestor::tr[2]/following-sibling::tr[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $dateStr = en(re('#\d+\s+\w+\s+\d+#', node('./preceding-sibling::tr[1]')));
                            $s = node('.');
                            $r = '#';
                            $r .= 'Vluchtnummer:\s+(?P<AirlineName>\w{2})(?P<FlightNumber>\d+)\s+';
                            $r .= '(?P<DepTime>\d+:\d+)\s+(?P<DepName>.*)\s+';
                            $r .= '(?P<Duration>\d+:\d+:\d+)\s+';
                            $r .= '(?P<ArrTime>\d+:\d+)\s+(?P<ArrName>.*)';
                            $r .= '#i';
                            $res = [];

                            if (preg_match($r, $s, $m)) {
                                foreach (['AirlineName', 'FlightNumber', 'DepName', 'ArrName', 'Duration'] as $k) {
                                    $res[$k] = $m[$k];
                                }
                                $res['DepDate'] = strtotime($dateStr . ', ' . $m['DepTime']);
                                $res['ArrDate'] = strtotime($dateStr . ', ' . $m['ArrTime']);

                                if ($res['DepDate'] and $res['ArrDate'] and $res['ArrDate'] < $res['DepDate']) {
                                    $res['ArrDate'] = strtotime('+1 day', $res['ArrDate']);
                                }
                            }

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
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
        return ["nl"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
