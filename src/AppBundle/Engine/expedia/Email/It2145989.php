<?php

namespace AwardWallet\Engine\expedia\Email;

class It2145989 extends \TAccountCheckerExtended
{
    public $rePlain = "#your\s+account\s+will\s+be\s+refunded\s+by\s+the\s+airline.*?expedia#ims";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "6735";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "expedia/it-2144792.eml, expedia/it-2144928.eml, expedia/it-2145085.eml, expedia/it-2145989.eml, expedia/it-2189417.eml";
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
                        return orval(
                            re("#Airline confirmation code\s*:\s*([A-Z\d-]+)#ix"),
                            re("#\n\s*Expedia\s+Iti\w+\s+No[.:\s]+([A-Z\d-]+)#ix")
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Traveler\s*:\s*([^\n]+)#"),
                            re("#Traveler Information\s*:\s*([^\n]+)#ix")
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return
                        re("# your flight is cancel+ed#ix")
                        || re("#cancel+ed your flight#ix")
                        || re("#will be refunded by the airline#ix")
                        || re("#Your itinerary is cancel+ed#ix", $this->parser->getSubject()) ? true : false;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(orval(
                            trim(re("#You\s+will\s+receive\s+a\s+total\s+refund\s+of\s+([^\n]+)#i"), '.'),
                            re("#Total Airline Credit\s*:\s*([^\n]+)#ix", $this->text())
                        ));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#your flight is ([^\n.,;]+)#ix"),
                            re("#(will be refunded) by the airline#ix")
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Canceled Trip Details')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/table[1]/tr/td[1]//table");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^(.*?)\s+(\d+)$#", node(".//tr[2]")),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+\-\s+([A-Z]{3})\s*\n#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(node(".//tr[1]") . ',' . uberTime(1));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\s+\-\s+([A-Z]{3})\s*\n#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(node(".//tr[1]") . ',' . uberTime(2));
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
