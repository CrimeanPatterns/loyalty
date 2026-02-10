<?php

namespace AwardWallet\Engine\easyjet\Email;

class NectarEasyJetBookingConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+booking\s+your\s+easyJet\s+flight\s+with\s+Nectar#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Nectar-easyJet\s+booking\s+confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@nectar-easyjet\.com#i";
    public $reProvider = "#nectar-easyjet\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "easyjet/it-2213315.eml, easyjet/it-2215508.eml";
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
                        return re('#easyJet\s+booking\s+reference\s+([\w\-]+)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Balance\s+paid\s+(.*)#'));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Nectar\s+points\s+spent\s+([\d,.]+)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[normalize-space(.) = "Outbound" or normalize-space(.) = "Inbound"]/following-sibling::tr[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./following-sibling::tr[1]//td[contains(., "Flight")]/following-sibling::td[1]');

                            if (re('#^([A-Z]{2,3})(\d+)$#i', $s)) {
                                return [
                                    'AirlineName'  => re(1),
                                    'FlightNumber' => re(2),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if (re('#(.*)\s+to\s+(.*)#i')) {
                                return [
                                    'DepName' => re(1),
                                    'ArrName' => re(2),
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep', 'Arr'] as $key) {
                                $s = node('./following-sibling::tr[1]//td[contains(., "' . $key . '")]/following-sibling::td[1]');
                                $res[$key . 'Date'] = strtotime($s);
                            }

                            return $res;
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
