<?php

namespace AwardWallet\Engine\dividendmiles\Email;

class ScheduleChange extends \TAccountCheckerExtended
{
    public $rePlain = "#dividend\s+miles#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@usairways\.com#i";
    public $reProvider = "#\busairways\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "dividendmiles/it-2035991.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $recordLocatorInfoNodes = xpath('//text()[normalize-space(.) = "Confirmation code"]/ancestor::table[1]/following-sibling::table[1]//tr[string-length(normalize-space(.)) > 1]');
                    $this->recordLocators = [];

                    foreach ($recordLocatorInfoNodes as $n) {
                        $this->recordLocators[node('./td[3]', $n)] = node('./td[2]', $n);
                    }

                    return xpath('//text()[contains(., "Departure time")]/ancestor::table[1]/preceding-sibling::table[2]');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $key = node('./following-sibling::table[1]');

                        if (isset($this->recordLocators[$key])) {
                            return $this->recordLocators[$key];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re('#\w+\s+\d+,\s+\d+#i');

                            if (!$dateStr) {
                                return;
                            }
                            $flightInfoRow = xpath('./following-sibling::table[2]//tr[contains(., ":")]');

                            if ($flightInfoRow->length > 0) {
                                $flightInfoRow = $flightInfoRow->item(0);
                                $res['FlightNumber'] = node('./td[2]', $flightInfoRow);

                                foreach (['Dep' => 3, 'Arr' => 4] as $key => $value) {
                                    $regex = '#(\d+:\d+\s*(?:am|pm)?)\s+(\w{3})#i';
                                    $subj = node('./td[' . $value . ']', $flightInfoRow);

                                    if (preg_match($regex, $subj, $m)) {
                                        $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);
                                        $res[$key . 'Code'] = $m[2];
                                    }
                                }

                                return $res;
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $key = node('./following-sibling::table[1]');

                            if (isset($this->recordLocators[$key])) {
                                return $key;
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
}
