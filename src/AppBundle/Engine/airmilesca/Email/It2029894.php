<?php

namespace AwardWallet\Engine\airmilesca\Email;

class It2029894 extends \TAccountCheckerExtended
{
    public $reBody = "AIR MILES";
    public $reBody2 = "Flight";

    public $mailFiles = "airmilesca/it-1704914.eml, airmilesca/it-2029894.eml";

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
                        $node = node("(//*[contains(text(), 'Airline Confirmation #')]/ancestor-or-self::td[1])[1]");

                        if ($node != null) {
                            $node = str_replace('Airline Confirmation #:', '', $node);
                        } else {
                            $node = re("#confirmation number is:\s*([^\n]+)\n#");
                        }

                        return trim($node);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Seating:')]/ancestor-or-self::tr[1]/following-sibling::tr[1])[1]");
                        $seat = node("(//*[contains(text(), 'Seating:')]/ancestor-or-self::tr[1]/following-sibling::tr[1]//font)[1]");
                        $node = str_replace($seat, '', $node);

                        return trim($node);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Amount')]/following-sibling::td[1]");
                        $node = re("#[0-9.]+#", $node);

                        return $node;
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $node = re("#([^\n]+)\s*Taxes & Fees#");
                        $node = total($node, 'Tax');

                        return $node;
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        $node = re("#([0-9,]+)\s*reward miles#");

                        return $node;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $node = re("#We're pleased to email your complete flight confirmation#");

                        if ($node != null) {
                            return "confirmed";
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Seating:')]/ancestor-or-self::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[5]/td[1]");

                            $node = uberAir($node);

                            return $node;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[4]/td[2]");
                            $aircode = uberAircode($node);

                            return $aircode;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[3]/td[2]");

                            return $node;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("./preceding-sibling::tr[last()-1]");
                            $date = uberDate($date);
                            $deptime = node("./preceding-sibling::tr[5]/td[2]");
                            $deptime = uberTime($deptime);
                            $depDatetime = $date . " " . $deptime;

                            return totime($depDatetime);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[4]/td[3]");
                            $aircode = uberAircode($node);

                            return $aircode;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[3]/td[3]");

                            return $node;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("./preceding-sibling::tr[last()-1]");
                            $date = uberDate($date);
                            $arrtime = node("./preceding-sibling::tr[5]/td[3]");
                            $arrtime = uberTime($arrtime);
                            $arrDatetime = $date . " " . $arrtime;

                            return totime($arrDatetime);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[4]/td[4]");

                            return $node;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $node = node("./preceding-sibling::tr[4]/td[5]");

                            return $node;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = node("./following-sibling::tr[1]//font");

                            return $seat;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
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
