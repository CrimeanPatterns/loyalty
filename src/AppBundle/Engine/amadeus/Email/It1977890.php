<?php

namespace AwardWallet\Engine\amadeus\Email;

// parsers with similar formats: amadeus/It1824289(array), amadeus/MyTripItinerary(array), hoggrob/It6083284(array)

class It1977890 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?amadeus#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $caseReference = "6834";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1977890.eml, amadeus/it-1983092.eml, amadeus/it-1983225.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return xpath("//*[contains(text(), 'Confirmation Number:')]/ancestor-or-self::td[1]");
                },

                "#Pick\-up#i" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Confirmation\s+Number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Pick-Up')])[1]/ancestor::tr[1]/following-sibling::tr[contains(., 'Address:')]/td[2]");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDate(cell("Pick-up")) . ',' . cell("Pick-Up time:", +1), $this->date);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Drop-off time')])[1]/ancestor::tr[1]/following-sibling::tr[contains(., 'Address:')]/td[2]");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDate(cell("Drop-off")) . ',' . cell("Drop-off time:", +1), $this->date);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Pick-Up')])[1]/ancestor::tr[1]/following-sibling::tr[contains(., 'Telephone number:')]/td[2]");
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Pick-Up')])[1]/ancestor::tr[1]/following-sibling::tr[contains(., 'Fax number:')]/td[2]");
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Pick-Up')])[1]/ancestor::tr[1]/following-sibling::tr[contains(., 'Opening hours:')]/td[2]");
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Drop-off time')])[1]/ancestor::tr[1]/following-sibling::tr[contains(., 'Fax number:')]/td[2]");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Drop-off')])[1]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Drop-off')])[1]/ancestor-or-self::td[1]/following-sibling::td[2]");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return node("(.//*[contains(text(), 'Car type :')])[1]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(@class, 'summaryPnrHeaderNames')]/ancestor-or-self::td[1]//b");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#([\d.]+\s*[A-Z]{3})#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#(\w+)\s+Confirmation Number:#");
                    },
                ],

                "#(?!Car)\s+#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Confirmation Number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(@class, 'summaryPnrHeaderNames')]/ancestor-or-self::td[1]//b");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(text(), 'Confirmation Number:')]/ancestor::tr[1]", null, true, "#[\d.,]+\s*[A-Z]{3}#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(node("//*[contains(text(), 'Confirmation Number:')]/ancestor::tr[1]", null, true, "#[\d.,]+\s*[A-Z]{3}#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Trip status\s*:\s*([^\n]+)#", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//tr[not(.//tr)][contains(.,':')][td[contains(., 'Terminal ')]]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^(.*?)\s+(\d+)#", node('td[3]')),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node('td[1]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(uberDateTime(node('td[1]')), $this->date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node('td[2]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(uberDateTime(node('td[2]')), $this->date);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $value = trim(implode(" ", nodes("td[4]//text()[normalize-space()][not(ancestor::script)]")), "\n/ ");

                            return [
                                'Cabin' => re("#\w+#", $value),
                                'Seats' => re("#Seat\s+selection\s*:\s*(\d+[A-Z]+)#", $value),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return trim(clear("#direct#i", node("td[5]")));
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
