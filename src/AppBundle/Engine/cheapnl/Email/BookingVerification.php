<?php

namespace AwardWallet\Engine\cheapnl\Email;

class BookingVerification extends \TAccountCheckerExtended
{
    public $mailFiles = "cheapnl/it-2119212.eml, cheapnl/it-2959339.eml, cheapnl/it-4424303.eml, cheapnl/it-5940038.eml";

    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?cheaptickets#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#CheapTickets\s+Booking\s+Verification#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@cheaptickets\.co\.th#i";
    public $reProvider = "#cheaptickets\.co\.th#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->passengers = nodes('//td[normalize-space(.) = "Travellers"]/ancestor::tr[1]/following-sibling::tr[1]/td[contains(., "1.")][1]//tr');

                    foreach ($this->passengers as &$p) {
                        $p = re('#\d+\.\s+(.*)\s+\(.*#i', $p);
                    }
                    $recordLocatorNodes = xpath('//tr[contains(., "Your Airline Confirmation Codes:") and not(.//tr)]/following-sibling::tr');
                    $this->recordLocators = [];

                    foreach ($recordLocatorNodes as $n) {
                        $this->recordLocators[node('./td[1]', $n)] = node('./td[2]', $n);
                    }
                    $priceInfoNodes = nodes('//td[normalize-space(.) = "Price"]/ancestor::tr[1]/following-sibling::tr[1]/td[contains(., "Total")][1]//tr[string-length(normalize-space(.)) > 1]');
                    $this->prices = [];

                    foreach ($priceInfoNodes as $n) {
                        if ($x = re('#Total\s+(.*)#i', $n)) {
                            $this->prices['TotalCharge'] = cost($x);
                            $this->prices['Currency'] = currency($x);
                        } elseif ($x = re('#Taxes\s+and\s+Fees\s+(.*)#i', $n)) {
                            $this->prices['Tax'] = cost($x);
                        } elseif ($x = re('#.*\)\s+(.*)#i', $n)) {
                            $this->prices['BaseFare'] = cost($x);
                        }
                    }

                    return xpath('//tr[normalize-space(.) = "Your Itinerary"]/following-sibling::tr//tr[not(.//tr) and contains(., "Depart")]');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $an = node("./preceding-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)][1]");

                        if (isset($this->recordLocators[$an])) {
                            return $this->recordLocators[$an];
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("./preceding-sibling::tr[1]/td[3]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName'=> re("#(.+)\s+\(([A-Z]{3})\)#", node("./td[2]")),
                                'DepCode'=> re(2),
                            ];
                        },
                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(preg_replace('#\(.*\)#i', ',', node("./following-sibling::tr[1]/td[2]")));
                        },
                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrName'=> re("#(.+)\s+\(([A-Z]{3})\)#", node("./following-sibling::tr[2]/td[2]")),
                                'ArrCode'=> re(2),
                            ];
                        },
                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(preg_replace('#\(.*\)#i', ',', node("./following-sibling::tr[3]/td[2]")));
                        },
                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[position()<7][contains(., 'Aircraft')][1]/td[2]");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $result = [];

                            $duration = node("./following-sibling::tr[position()<7][contains(.,'Flight Tim')][1]/td[2]");
                            $durationParts = explode('|', $duration);
                            array_map(function ($s) { return trim($s, ' |'); }, $durationParts);

                            if (preg_match('/^[\d hrmin]{3,}$/i', $durationParts[0])) {
                                $result['Duration'] = $durationParts[0];
                            }

                            if (!empty($durationParts[1])) {
                                $result['Cabin'] = $durationParts[1];
                            }

                            return $result;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = node("./following-sibling::tr[position()<7][contains(.,'Stops')][1]/td[2]");

                            if (preg_match('/non[- ]*stop/i', $stops)) {
                                return 0;
                            }

                            return $stops;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        foreach ($this->prices as $key => $value) {
                            $itNew[0][$key] = $value;
                        }
                    }

                    return $itNew;
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
        return ['en'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
