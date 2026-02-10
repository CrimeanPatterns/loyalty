<?php

namespace AwardWallet\Engine\expedia\Email;

class FinalDetailsForYourTrip extends \TAccountCheckerExtended
{
    public $rePlain = "#(?:Your upcoming trip to|For more specific details, see the My Itineraries in your Expedia Account|Here\s+are\s+itinerary\s+and\s+confirmation\s+numbers\s+for\s+your\s+trip.*all\s+the\s+details\s+are\s+confirmed)(?:(?s).*?)expedia#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Expedia@au\.expediamail\.com#i";
    public $reProvider = "#au\.expediamail\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "expedia/it-1539842.eml, expedia/it-1539852.eml, expedia/it-1539862.eml, expedia/it-1557161.eml, expedia/it-2075765.eml, expedia/it-2190546.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $subj = $this->parser->getHtmlBody();
                    $subj = preg_replace('#<META[^>]+>#', '', $subj);
                    $this->http->FilterHTML = true;
                    $this->http->setBody($subj);
                    $x = '//tr[normalize-space(.) = "Itinerary"]/following-sibling::tr[contains(normalize-space(.), "Check In") or contains(normalize-space(.), "Depart")]';

                    return xpath($x);
                },

                ".//text()[contains(., 'Depart')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Airline\s+confirmation\s+code:\s+([\w\-]+)#i'),
                            re('#Expedia\s+itinerary\s+number\(s\):\s+([\w\-]+)#i', $this->text())
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Travell?er(?:\(s\))?\s*:\s+([^\s\d].*)#i', $this->text(), $m)) {
                            return nice($m[1]);
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#Flight\s+(\d+)#');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#\w+\s+\d+,\s+\d{4}#i', node('./preceding-sibling::tr[3]'));

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . cell($value, +1));

                                if (preg_match('#(.*)\s+\((\w{3})\)#i', cell($value, +2), $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                }
                            }

                            return $res;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return nice(preg_replace('#\s*with\s+baggage\s*#i', '', re('#(.*)\s+Flight\s+\d+#')));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $subj = node('.//tr[contains(., "Arrive") and not(.//tr)]/following-sibling::tr[1][not(contains(., "unassigned"))]');

                            if (preg_match('#(.*),\s+(.*)\s+Class,\s+(.*)#', $subj, $m)) {
                                return [
                                    'Seats'    => $m[1],
                                    'Cabin'    => $m[2],
                                    'Aircraft' => $m[3],
                                ];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#\d+\s*Hr\s+\d+\s*Min#i');
                        },
                    ],
                ],

                ".//text()[contains(., 'Check In')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            clear("#_#", re('#Hotel\s+confirmation\s+code:\s+([\w\-]+)#i'), '-'),
                            re('#Expedia\s+itinerary\s+number\(s\):\s+([\w\-]+)#i', $this->text())
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#(.*)\s+Hotel\s+rules\s+and\s+restrictions#i');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check In', 'CheckOut' => 'Check Out'] as $key => $value) {
                            $res[$key . 'Date'] = strtotime(str_replace('/', '.', re('#' . $value . ':\s+(\d+/\d+/\d+)#i')));
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $x = './/td[contains(., "Check In:")]/following-sibling::td[last()]//text()';

                        return nice(implode("\n", nodes($x)), ',');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotel\s+rules\s+and\s+restrictions\s+(.*)#i');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Main\s+contact:\s+(.*)#i', $this->text())];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Travel+er\(s\):\s+(\d+)#', $this->text());
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Rooms?\(s\)\s+booked:\s+(\d+)#i');
                    },
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
