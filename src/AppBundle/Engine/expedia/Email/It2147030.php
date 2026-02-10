<?php

namespace AwardWallet\Engine\expedia\Email;

class It2147030 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?expedia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "30.12.2014, 09:52";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "expedia/it-2147030.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Dear\s+([^,\n]+)#", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//text()[contains(normalize-space(.), 'Arrive')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return node("td[string-length(normalize-space(.))>1][3]", $node, true, "#Flight\s*:\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(node("preceding-sibling::*[string-length(normalize-space(.))>1][1]", $node, true, "#\s+date[:\s]([^\n]+)#i") . ',' . uberTime(1));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(node("preceding-sibling::*[string-length(normalize-space(.))>1][1]", $node, true, "#\s+date[:\s]([^\n]+)#i") . ',' . uberTime(2));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return node("td[string-length(normalize-space(.))>1][2]");
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
