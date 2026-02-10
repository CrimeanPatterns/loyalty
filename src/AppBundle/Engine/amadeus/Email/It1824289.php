<?php

namespace AwardWallet\Engine\amadeus\Email;

// parsers with similar formats: amadeus/It1977890(array), amadeus/MyTripItinerary(array), hoggrob/It6083284(array)

class It1824289 extends \TAccountCheckerExtended
{
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amadeus#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1749027.eml, amadeus/it-1824289.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Testernulldreiat Tester\s*\(([A-Z\d\-]+)\)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Testernulldreiat Tester\s*\([A-Z\d\-]+\)\s+(.*?)\s+Confirmed#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Trip status\s*:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("(//*[contains(text(), 'Terminal ')])[1]/ancestor::table[1]/tbody/tr[contains(.,':') and not(contains(., 'Stop(s)'))]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#(.*?)\s+(\d+)#", text(xpath('td[3]'))),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", node('td[1]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node('td[1]')));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", node('td[2]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node('td[2]')));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node("(//*[contains(text(), 'Terminal ')])[1]/ancestor::table[1]/tbody/tr[last()]//*[contains(text(), '{$it['FlightNumber']}') and contains(text(), '{$it['AirlineName']}')]/ancestor::tr[1]/following-sibling::tr[last()-1]");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('td[4]');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+#", node("(//*[contains(text(), 'Terminal ')])[1]/ancestor::table[1]/tbody/tr[last()]//*[contains(text(), '{$it['FlightNumber']}') and contains(text(), '{$it['AirlineName']}')]/ancestor::tr[1]/following-sibling::tr[last()]"));
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
        return true;
    }
}
