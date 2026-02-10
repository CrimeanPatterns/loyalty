<?php

namespace AwardWallet\Engine\alitalia\Email;

class BookingSummaryHtml2014En extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?alitalia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#alitalia#i";
    public $reProvider = "#alitalia#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "alitalia/it-2266852.eml";
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
                        return cell("BOOKING CODE", +1, 0);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Ticket number')]/ancestor::tr[1]/td[1]");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Terminal ')]/ancestor::tr[1]/preceding-sibling::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[string-length(normalize-space(.))>1][1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return node("td[3]", $node, true, "#^.*?\s*,\s*([A-Z]{3})#");
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("td[3]", $node, true, "#^(.*?)\s*,\s*[A-Z]{3}#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("preceding::tr[contains(.,'-')][1]/td[last()]");

                            return totime($date . ',' . node("td[2]"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return node("td[5]", $node, true, "#^.*?\s*,\s*([A-Z]{3})#");
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("td[5]", $node, true, "#^(.*?)\s*,\s*[A-Z]{3}#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("preceding::tr[contains(.,'-')][1]/td[last()]");

                            return totime($date . ',' . node("td[4]"));
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
