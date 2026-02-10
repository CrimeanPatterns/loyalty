<?php

namespace AwardWallet\Engine\amextravel\Email;

class ETicketConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+using\s+Platinum\s+Travel\s+Services#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Platinum@americanexpress\.com\.bh#i";
    public $reProvider = "#americanexpress\.com\.bh#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2120476.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->passengers = nice(explode("\n", re('#Itinerary\s+for:\s+((?s).*?)\s+Flight\s+Information#i')));

                    if ($this->passengers) {
                        $this->passengers = array_values(array_filter($this->passengers));
                    }

                    return splitter('#(Flight\s+Information:.*)#i');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Airline\s+Reference\s+No:\s+([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(\w.*)\s+Flight\s+Duration#i');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\s+\d{4}\s+#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $res = null;

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $regex = '#' . $value . ':\s+(.*)\s+\((\w{3})\)\s+-\s+(.*)\s+Date:\s+\w+\s+(\d+\w+\s+\w+)\s+Time:\s+(\d{1,2})(\d{2})#i';

                                if (preg_match($regex, $text, $m)) {
                                    $res[$key . 'Name'] = $m[1] . ' (' . $m[3] . ')';
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($m[4] . ' ' . $year . ', ' . $m[5] . ':' . $m[6]);
                                }
                            }

                            return $res;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Flight\s+Duration:\s+(\d+\s+hours\s+\d+\s+minutes)#i');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re('#Meals:\s+(\w.*)\s+Airline\s+Reference#i');
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Airline/Flight:\s+(\w{2})(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
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
