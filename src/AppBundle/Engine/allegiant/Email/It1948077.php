<?php

namespace AwardWallet\Engine\allegiant\Email;

class It1948077 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?allegiant#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reBody = ["Your booking is confirmed"];
    public $reSubject = ["AllegiantAir.com - Itinerary"];
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#allegiant#i";
    public $reProvider = "#allegiant#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "allegiant/it-13038356.eml, allegiant/it-1948077.eml, allegiant/it-2027299.eml";
    public $pdfRequired = "0";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Allegiant - Travel is our deal')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'confirmation number')]/ancestor-or-self::td[1]");
                        $node = str_replace("Your confirmation number is:", "", $node);

                        return trim($node);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//text()[contains(., 'Passenger Names')]/ancestor::tr[1]/following-sibling::tr/td[1]");

                        return array_unique(array_filter($nodes));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total Paid", +1));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Airfare", +1));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Tax", +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#confirmed#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = xpath("//*[contains(text(), 'Flight Information')]/ancestor-or-self::td[1]");

                        return $xpath;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $node = node("ancestor::tr[1]/following-sibling::tr[3]/td[2]");

                            return $node;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if (strpos($this->http->Response['body'], 'Thank you for booking your travel with Allegiant') !== false) {
                                return "G4";
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $node = node("ancestor::tr[1]/following-sibling::tr[3]/td[3]");

                            $node = re("#\(([^\n]+)\)#", $node);

                            return $node;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node = node("ancestor::tr[1]/following-sibling::tr[3]/td[3]");

                            $node = re("#([^\n]+)\s*\(#", $node);

                            return $node;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("ancestor::tr[1]/following-sibling::tr[3]/td[1]");
                            $time = node("ancestor::tr[1]/following-sibling::tr[3]/td[4]");
                            $date = $date . " " . $time;
                            $date = uberDatetime($date);

                            return totime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $node = node("ancestor::tr[1]/following-sibling::tr[3]/td[5]");

                            $node = re("#\(([^\n]+)\)#", $node);

                            return $node;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node = node("ancestor::tr[1]/following-sibling::tr[3]/td[5]");

                            $node = re("#([^\n]+)\s*\(#", $node);

                            return $node;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("ancestor::tr[1]/following-sibling::tr[3]/td[1]");
                            $time = node("ancestor::tr[1]/following-sibling::tr[3]/td[6]");
                            $date = $date . " " . $time;
                            $date = uberDatetime($date);

                            return totime($date);
                        },
                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return array_filter(nodes("ancestor::tr[1]/following-sibling::tr[contains(., 'Seat Assignment')][1]//text()[normalize-space(.)='Seat Assignment']/ancestor::tr[1]/following-sibling::tr/td[2]", $node, "#^\d{1,2}[A-Z]$#"));
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
}
