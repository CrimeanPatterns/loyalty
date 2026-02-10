<?php

namespace AwardWallet\Engine\bcd\Email;

class ElectronicTicketReceipt extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*(Van|From|Von)\s*:[^\n]*?bcdtravel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#bcdtravel#i";
    public $reProvider = "";
    public $caseReference = "6734";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "bcd/it-2114132.eml, bcd/it-2130796.eml";
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
                        return re("#\n\s*(?:Booking Reference|Buchungsnummer)\s*:\s*([^\n]+)#ix");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return clear("#\s*ADT$#", cell(["Traveller:", "Reisender/Reisende:"], +1, 0));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell(["Total:", "Gesamt:"], +1, 0));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(["Fare:", "Flug Total:"], +1, 0));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $costs = cell(["Taxes, fees, charges:", "Steuern & Gebühren:"], +1, 0);
                        $tax = 0;

                        re("#([\d.,]+)#", function ($m) use (&$tax) {
                            $tax += cost($m[1]);
                        }, $costs);

                        return $tax;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell(["Date of issue:", "Ausstellungsdatum:"], +1, 0));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Airline Confirmation')]/ancestor::tr[1]/preceding-sibling::tr[contains(., 'OK')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node('td[1]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node_ = node('td[5]');

                            if (preg_match("/(.+),?\s*Terminal\s+(.+)$/iu", $node_, $m)) {
                                return [
                                    "DepName"           => $m[1],
                                    "DepartureTerminal" => $m[2],
                                ];
                            } else {
                                return $node_;
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $year = date('Y', totime(re("#(?:Date of issue:|Ausstellungsdatum:)\s*([^\n]+)#", $this->text())));

                            return totime(node('td[3]') . $year . ',' . node('td[4]'));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node_ = node('following-sibling::tr[string-length(normalize-space(./td[5])) > 1][1]/td[5]');

                            if (preg_match("/(.+?),?\s*Terminal\s+(.+)$/iu", $node_, $m)) {
                                return [
                                    "ArrName"         => $m[1],
                                    "ArrivalTerminal" => $m[2],
                                ];
                            } else {
                                return $node_;
                            }
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $year = date('Y', totime(re("#(?:Date of issue:|Ausstellungsdatum:)\s*([^\n]+)#", $this->text())));

                            return totime(node('following-sibling::tr[string-length(normalize-space(./td[5])) > 1][1]/td[3]') . $year . ',' . node('following-sibling::tr[string-length(normalize-space(./td[5])) > 1][1]/td[4]'));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node("td[2]");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[1]/td[7]", $node, true, "#(?:Seat|Sitzplatz)\s*:\s*([^\n]*?)$#");
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[contains(., 'Airline Confirmation')][1]/td[last()]", $node, true, "#\s+([A-Z\d-]+)$#");
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
