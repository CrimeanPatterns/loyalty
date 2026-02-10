<?php

namespace AwardWallet\Engine\frontierairlines\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "frontierairlines/it-2539783.eml, frontierairlines/it-2682222.eml, frontierairlines/it-2759678.eml, frontierairlines/it-2783169.eml, frontierairlines/it-3074647.eml";

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?flyfrontier\.com#i', 'blank', ''],
        ['#@flyfrontier.com[>\s]+wrote#', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]frontierairlines#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]frontierairlines#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "29.09.2015, 13:38";
    public $crDate = "10.03.2015, 14:33";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->passengers = [];

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Trip Confirmation Number\s*:\s*([A-Z\d-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*TOTAL\s*([^\n]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Airfare\s*([^\n]+)#"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re('/\n\s*(?:Taxes\s+&\s+Fees|Taxes\s+and\s+carrier-imposed\s+fees)\s*([^\n]+)/i'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//text()[normalize-space(.)='Departure']/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/m', $text, 1),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re("#\n\s*([A-Z]{3}),\s+#"),
                                re("#\(([A-Z]{3})\)#")
                            );
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(re('/\s*Depart\s*:\s*\w+,\s*(\S+\s+\d+,\s*\d+)/i', node("./../tr[1]")));
                            $result['DepDate'] = totime($date . ', ' . uberTime(1));
                            $result['ArrDate'] = totime($date . ', ' . uberTime(2));

                            return $result;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                ure("#\n\s*([A-Z]{3}),\s+#", 2),
                                ure("#\(([A-Z]{3})\)#", 2)
                            );
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $results = [];
                            $passengersRows = $this->http->XPath->query('.//tr[starts-with(normalize-space(.),"Passenger Name") and contains(normalize-space(.),"Seats")]/following-sibling::tr[count(./td)=4 and string-length(normalize-space(.))>1]', $node);

                            foreach ($passengersRows as $passengersRow) {
                                $this->passengers[] = $this->http->FindSingleNode('./td[1]', $passengersRow);
                                $results[] = $this->http->FindSingleNode('./td[2]', $passengersRow, true, '/^([,A-Z\d\s]+)$/');
                            }

                            return array_values(array_filter($results));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+:\d+.+?\d+:\d+.+?(\d+h[^\n]*)#s");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (re('/(\d+)\s*stops*\b/i')) {
                                return re(1);
                            } else {
                                return re('/non[\s\-]*stop/i') ? 0 : null;
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    if ($it[0]['Kind'] === 'T') {
                        if (isset($this->passengers) && !empty($this->passengers)) {
                            $it[0]['Passengers'] = array_values(array_unique($this->passengers));
                        }
                    }

                    return $it;
                },
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Frontier Airlines') !== false
            || stripos($from, '@flyfrontier.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Reservation Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//www.flyfrontier.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"THANK YOU FOR FLYING FRONTIER AIRLINES") or contains(.,"FlyFrontier.com") or contains(.,"@flyfrontier.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(.),"Trip Confirmation Number")]')->length > 0;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
