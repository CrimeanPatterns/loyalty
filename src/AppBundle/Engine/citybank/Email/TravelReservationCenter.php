<?php

namespace AwardWallet\Engine\citybank\Email;

class TravelReservationCenter extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?travelcenter#i', 'blank', ''],
    ];
    public $reHtml = [
        ['#USAA\s+Rewards#i', 'blank', '-2000'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@travelcenter#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#@travelcenter#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.05.2015, 11:35";
    public $crDate = "22.01.2015, 09:46";
    public $xPath = "";
    public $mailFiles = "citybank/it-2389521.eml, citybank/it-2397057.eml, citybank/it-2604110.eml, citybank/it-2753262.eml, citybank/it-3021624.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//img[contains(@src, 'flightM') or contains(@src, 'hotelM')]/ancestor::table[1]/following::table[1]");
                },

                "#Check-In:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Booking\s+Confirmation\s+Number\s*:\s+([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#(.*)\s+\(\w+\s+\d+,\s+\d{4}#', node('./preceding-sibling::table[1]'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-In:\s+(\w+,\s+\w+\s+\d+,\s+\d+)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-Out:\s+(\w+,\s+\w+\s+\d+,\s+\d+)#'));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node('.//tr[contains(., "Check-In:") and not(.//tr)]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s+Adult\(s\)#');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s+Child\(ren\)#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Type:\s+(.*)#');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $s = cell('Hotel Reward', +2);

                        if (preg_match('#([\d,]+\s+Points)\s+\+\s+(.*)#i', $s, $m)) {
                            return [
                                'Total'       => cost($m[2]),
                                'Currency'    => currency($m[2]),
                                'SpentAwards' => $m[1],
                            ];
                        } elseif (re('#^[\d,.]+\s+Points$#i', $s)) {
                            return ['SpentAwards' => $s];
                        } else {
                            return total($s, 'Total');
                        }
                    },
                ],

                "#Airline\s+Reference\s+Number#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Airline Reference Number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Passenger ')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//*[contains(text(), 'Total Charges')]/ancestor-or-self::td[1]/following-sibling::td[last()]"));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Air\s+Reward\s+([A-Z\d,.]+\s*Points?)#i"),
                            re("#\n\s*Payment from Rewards Program\s+([A-Z\d,.]+\s*Point)#ix", $this->text())
                        );
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Flight:[^\n]+\s+(Confirmed|Cancel+ed)#", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//img[contains(@src, 'Images/Airlines')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^([A-Z\d]{2})[\#\s]*(\d+)\s+([^\n]+)\s+(.+)$#s", text(xpath("td[2]"))),
                                'FlightNumber' => re(2),
                                'Cabin'        => re(3),
                                'Aircraft'     => re(4),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node("td[3]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node("td[3]")));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node("td[4]"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node("td[4]")));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#^([\dhrmin\s]+)$#", node("td[6]"));
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non\-stop#i") ? 0 : null;
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
