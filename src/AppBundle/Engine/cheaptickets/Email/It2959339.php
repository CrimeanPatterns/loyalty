<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class It2959339 extends \TAccountCheckerExtended
{
    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->locators = [];
                    $locsRaw = re("#Your\s+Airline\s+Confirmation\s+Codes\s*:(\s*.+?\n)\s*Dear\s[^\n]+?,#si");

                    if (preg_match_all("#\n\s*([^\n]+?)\s*\n\s*([\w-]+)#", $locsRaw, $m, PREG_SET_ORDER)) {
                        foreach ($m as $lr) {
                            $this->locators[$lr[1]] = $lr[2];
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Your\s+CheapTickets\s+Trip\s+Id\s*:\s*([\w-]+)#i"),
                            TRIP_CODE_UNKNOWN
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $pRaw = re("#Travellers\s*(.+?)\s*Your\s+Itinerary#si");

                        if (preg_match_all('#\d+\s*\.\s*(.*)#', $pRaw, $m)) {
                            return $m[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Price\s.+?\n\s*Total\s+([^\n]+)#si"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Price\s.+?\n\s*\d+\s+Adult\(s\)\s+([^\n]+)#si"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Price\s.+?\n\s*Taxes and Fees\s+([^\n]+)#si"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsRaw = re("#Your\s+Itinerary\s*(.+?)\s*(?:Price\s+Ticket\s+Type|Support\s+and\s+Changes|Airline Confirmation Codes)#si");

                        return splitter("#(Flight\s*[A-Z]{2}\s*\d{3,4})#", $itsRaw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['AirlineName'] = re("#Flight\s*([A-Z]{2})\s*(\d{3,4})#");
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Depart\s+[^\n]+?\(([A-Z]+)\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#\n\s*Depart\s+.+?(\d+\-\w+\-\d+).+?(\d+:\d+)\s+Arrive#s") . " " . re(2));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Arrive\s+[^\n]+?\(([A-Z]+)\)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#\n\s*Arrive\s+.+?(\d+\-\w+\-\d+).+?(\d+:\d+)\s*\n#s") . " " . re(2));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Aircraft\s+([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s+Time\s+[^\n]+?\s*\|\s*([^\n]+)#i");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s+Time\s+([^\n]+?)\s*\|#i");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Stops\s+(\d+)#");
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            $an = re("#^\s*([^\n]+?)\s*\n\s*Flight#");

                            if (isset($this->locators[$an])) {
                                return $this->locators[$an];
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@cheaptickets.co.th') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Minor schedule change notification') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (
                strpos($parser->getHTMLBody(), 'Minor schedule change notification') !== false
                || strpos($parser->getHTMLBody(), 'Ticketing Confirmation') !== false
                )
                && strpos($parser->getHTMLBody(), 'Your CheapTickets Trip Id') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cheaptickets.com') !== false;
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
