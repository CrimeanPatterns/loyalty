<?php

namespace AwardWallet\Engine\budgetair\Email;

class It2586738 extends \TAccountCheckerExtended
{
    public $mailFiles = "budgetair/it-2586738.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@budgetair.co.uk') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'BudgetAir.co.uk - order confirmation nr.') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Product details') !== false
                && strpos($parser->getHTMLBody(), 'BudgetAir') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@budgetair.co.uk') !== false;
    }

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
                        return re("#Booking\s+code\s*\:\s+([^\s]+)\s#uism");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = [];
                        $psngrNodes = xpath("//table[./thead/tr[1]/td/*[contains(text(), 'Passenger')]]/tbody/tr[./th]");

                        for ($i = 0; $i < $psngrNodes->length; $i++) {
                            $passengers[] = re("/[mrs]+\s([^.]+)$/xui", node("./th/text()", $psngrNodes->item($i)));
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("//*[contains(text(), 'Total costs')][1]/*/text()"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//table[./*/tr/*[contains(text(), 'Flight no.')]]/tbody/tr[position() mod 2 = 1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("/(\d+)/ui", node("./*[5]/*/text()"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("./*[3]/*/text()") . " " . node("./*[3]/*/*[2]/text()");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $sDate = re("/.+?\,\s*(.+)/ui", node("./*[2]/*/text()"));
                            $sTime = node("./*[4]/*/text()");
                            $depDate = $sDate . " " . $sTime;

                            if ($depDT = \DateTime::createFromFormat("d-M-Y H:i", $depDate)) {
                                return $depDT->getTimestamp();
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[1]/*[3]/*/text()") . " " . node("./following-sibling::tr[1]/*[3]/*/*[2]/text()");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            if (!($sDate = re("/.+?\,\s*(.+)/ui", node("./following-sibling::tr[1]/*[2]/*/text()")))) {
                                $sDate = re("/.+?\,\s*(.+)/ui", node("./*[2]/*/text()"));
                            }
                            $sTime = node("./following-sibling::tr[1]/*[4]/*/text()");
                            $arrDate = $sDate . " " . $sTime;

                            if ($arrDT = \DateTime::createFromFormat("d-M-Y H:i", $arrDate)) {
                                return $arrDT->getTimestamp();
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("/(\w+?)\d+/ui", node("./*[5]/*/text()"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node("./*[7]/*/text()");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[1]/*[7]/*/text()");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node("./following-sibling::tr[1]/*[5]/*/text()");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return intval(node("./*[6]/*/text()"));
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
