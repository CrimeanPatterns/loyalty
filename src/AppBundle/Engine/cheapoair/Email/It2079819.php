<?php

namespace AwardWallet\Engine\cheapoair\Email;

class It2079819 extends \TAccountCheckerExtended
{
    public $rePlain = "#@cheapoair[.]com#i";
    public $rePlainRange = "-1000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@cheapoair[.]com#i";
    public $reProvider = "#[@.]cheapoair[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cheapoair/it-1596501.eml, cheapoair/it-2079819.eml, cheapoair/it-2079821.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->confirmed = re_white('here is your confirmed itinerary') ? true : false;
                    $date = re_white('Departing Flight - \w+, (\w+ \d+, \d+)');
                    $this->departing = strtotime($date);

                    return xpath("//*[contains(normalize-space(text()), 'Airline confirmation:')]/ancestor::tr[1]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('Airline confirmation: (\w+)');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if ($this->confirmed) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re_white('Flight (\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('From .+? \( (\w+) \)');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//b[normalize-space(text()) = 'From']/following::span[1]");

                            $year = date('Y', $this->departing);
                            $dt = \DateTime::createFromFormat('h:ia - M d, D; Y', "$info; $year");
                            $dt = $dt ? $dt->getTimestamp() : null;

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('\bTo .+? \( (\w+) \)');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//b[normalize-space(text()) = 'To']/following::span[1]");

                            $year = date('Y', $this->departing);
                            $dt = \DateTime::createFromFormat('h:ia - M d, D; Y', "$info; $year");
                            $dt = $dt ? $dt->getTimestamp() : null;

                            return $dt;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('^ (.+?) Flight');

                            return nice($x);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('
								Flight (?:\d+)
								(.+?)
								Seat\(s\):
							');

                            return nice($x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return between('stop', 'Baggage');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $info = between('Seat(s):', 'Airline confirmation:');
                            $q = white('(\d+[A-Z]+)');

                            if (preg_match_all("/$q/isu", $info, $ms)) {
                                return $ms[1];
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (re_white('Nonstop')) {
                                return 0;
                            }

                            return re_white('(\d+) Stop');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    // return $it;
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
