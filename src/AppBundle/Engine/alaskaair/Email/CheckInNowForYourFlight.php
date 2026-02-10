<?php

namespace AwardWallet\Engine\alaskaair\Email;

class CheckInNowForYourFlight extends \TAccountCheckerExtended
{
    public $rePlain = "#From:\s+alaskaair@response.myalaskaair.com.*Subject:\s+(Check in Now for Your Flight|Need Anything for Your Trip)#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#alaskaair#i";
    public $reProvider = "#alaskaair#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "alaskaair/it-1693629.eml, alaskaair/it-1698265.eml, alaskaair/it-1706875.eml, alaskaair/it-1707125.eml, alaskaair/it-1707827.eml, alaskaair/it-18.eml, alaskaair/it-19.eml, alaskaair/it-2075708.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $nodes = xpath('//text()[contains(., "Traveler Name")]/ancestor::table[3]/../table[following-sibling::table[contains(., "Departs")]]//tr[count(./td) = 7]');
                    $this->passengers = null;

                    foreach ($nodes as $n) {
                        $this->passengers[] = preg_replace('#\s*Traveler\s+Name\s*#i', '', node('./td[1]', $n));
                        $regex = '#(\d+)\s+\|\s+(\d+\w)#i';
                        $subj = node('./td[contains(., "|")]', $n);

                        if (preg_match_all($regex, $subj, $matches, PREG_SET_ORDER)) {
                            foreach ($matches as $m) {
                                $this->seats[$m[1]][] = $m[2];
                            }
                        }
                    }

                    return xpath("//*[contains(text(), 'Departs')]/ancestor-or-self::td[2]/table[1]/following-sibling::table//text()[contains(.,'Detail') or contains(., 'Total:')]/ancestor::tr[2]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Confirmation Code:\s*([A-Z\d\-]+)#", $text),
                            re("#\n\s*Your confirmation code is\s*([\dA-Z\-]+)#", $this->text())
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_values(array_filter(explode("\n", trim(cell("Mileage Program", 0, +2, "", null)))));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#^(.*?)\s*(\d+)#", text(xpath('td[1]')), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Seats'        => isset($this->seats[$m[2]]) ? implode(', ', $this->seats[$m[2]]) : null,
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node('td[3]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node('td[3]')));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node('td[5]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node('td[5]')));
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\s+Total\s*:\s*([^\n\|]+)#", node('td[1]')));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\|\s*((?:\d+|h|m| )+)#", node('td[1]'));
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
