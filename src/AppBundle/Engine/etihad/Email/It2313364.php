<?php

namespace AwardWallet\Engine\etihad\Email;

class It2313364 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?etihad#i', 'us', ''],
    ];
    public $reHtml = [
        ['#visit the website[^w]+www.etihad.com#i', 'blank', '-500'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]etihad#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]etihad#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "09.03.2015, 05:41";
    public $crDate = "09.03.2015, 05:24";
    public $xPath = "";
    public $mailFiles = "etihad/it-2313364.eml";
    public $re_catcher = "#.*?#";
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
                        return cell("Reservation Code", +1, 0);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Prepared\s+For\s+([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total/Transaction Currency\s+([^\n]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Fare\s+(\d[\d.,]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Booking\s+Status\s+([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Issue Date", +1, 0));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//tr[contains(., 'Travel Date') and contains(., 'Departure')]/ancestor::thead[1]/following-sibling::tbody/tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[2]", null, true, "#([A-Z\d]{2}\s*\d+)$#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#^([^\n]+)#", xpath('td[3]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $anchor = re("#Issue\s+Date\s+([^\n]+)#i", $this->text());
                            $date = re("#^(\d+\w{3})#", xpath('td[1]')) . ',' . uberTime(node('td[3]'));

                            return correctDate($date, $anchor);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#^([^\n]+)#", xpath('td[4]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $anchor = re("#Issue\s+Date\s+([^\n]+)#i", $this->text());
                            $date = re("#^\d+\w{3}\s*\-\s*(\d+\w{3})#", xpath('td[1]'));

                            if (!$date) {
                                $date = date('Y-m-d', $it["DepDate"]);
                            }

                            return correctDate($date, $anchor);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:\n|^)Class\s+(\w+)#", xpath("td[5]"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#Seat\s+Number\s+(\d+[A-Z]+)#", xpath("td[5]"));
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
