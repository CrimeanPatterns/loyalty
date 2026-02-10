<?php

namespace AwardWallet\Engine\chase\Email;

class TripSubmitted extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+using\s+Ultimate\s+Rewards\s+Travel#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#DoNotReplyultimaterewards@reardencommerce\.com#i";
    public $reProvider = "#[.@]chase\.com#i";
    public $xPath = "";
    public $mailFiles = "chase/it-1.eml, chase/it-1898082.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->travelers = explode('; ', re('#Travelers?:\s+(.*)#i'));
                    $this->fullText = $text;

                    if (preg_match_all('#Flight\s+from:(?:(?s).*?)Status:\s*.*#i', $text, $m)) {
                        return $m[0];
                    }
                },

                "#Flight#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+number:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Membership:\s+(.*)#i');

                        if (preg_match_all('#-\s+([\w\-]+)#i', $subj, $m)) {
                            return implode(', ', $m[1]);
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight\s+from.*\s*\n\s*(.*)\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#\w+,\s+(\w+\s+\d+,\s+\d{4})\s+Depart#i');

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $regex = '#';
                                $regex .= $value . ':\s+';
                                $regex .= '(\d+:\d+\s*(?:am|pm))\s+'; // Time
                                $regex .= '(?:\(\s*([\+-]\d+\s+\w+)\s*\))?\s*'; // Day shift
                                $regex .= '(.*)\s+'; // Name
                                $regex .= '\((\w{3})\)'; // Code
                                $regex .= '#i';

                                if (preg_match($regex, $text, $m)) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);

                                    if ($m[2]) {
                                        $res[$key . 'Date'] = strtotime(nice($m[2]), $res[$key . 'Date']);
                                    }
                                    $res[$key . 'Name'] = $m[3];
                                    $res[$key . 'Code'] = $m[4];
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#Plane\s+type:\s+(.*)#');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class:\s+(.*)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $subj = re('#Seats:\s+(.*?)\s+Meal#s') . "\n";

                            if (preg_match_all('#:\s+(\d+-\w),?#i', $subj, $m)) {
                                return str_replace('-', '', implode(', ', $m[1]));
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight\s+time:\s+(\d+h\s+\d+m)\s+\((.*)\)\s+(Non-stop|\d+stop)#i', $text, $m)) {
                                return [
                                    'Duration'      => $m[1],
                                    'TraveledMiles' => $m[2],
                                    'Stops'         => (strtolower($m[3]) == 'non-stop') ? 0 : re('#\d+#i', $m[3]),
                                ];
                            } else {
                            }
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re('#Meal\s+Service:\s+(.*)#');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        $subj = re('#Total\s+Transportation\s+(.*)#i', $this->fullText);
                        $itNew[0]['TotalCharge'] = cost($subj);
                        $itNew[0]['Currency'] = currency($subj);
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
}
