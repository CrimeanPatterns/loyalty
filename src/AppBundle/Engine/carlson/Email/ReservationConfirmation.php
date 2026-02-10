<?php

namespace AwardWallet\Engine\carlson\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#(?:Your Country Inns & Suites By Carlson|Your Radisson Hotels & Resorts|Your Radisson) Reservation Confirmation#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#(?:Your Country Inns & Suites By Carlson|Your Radisson Hotels & Resorts|Your Radisson) Reservation Confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#reservations@countryinns.com#i";
    public $reProvider = "#countryinns.com#i";
    public $xPath = "";
    public $mailFiles = "carlson/it-1.eml, carlson/it-1877734.eml, carlson/it-1877845.eml, carlson/it-2.eml, carlson/it-3.eml, carlson/it-4.eml, carlson/it-6.eml, carlson/it.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return str_replace('Room', '', re("#Reservation Summary for Confirmation Number:\s+(\S+)#"));
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'Please\s+do\s+not\s+reply.\s+';
                        $regex .= '(.*)\s+';
                        $regex .= '((?s).*?)\s+';
                        $regex .= '(.*)\s+';
                        $regex .= 'Reservation Summary';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = [
                            'CheckIn' => [
                                'Arrival',
                                'Check-In',
                            ],
                            'CheckOut' => [
                                'Departure',
                                'Check-Out',
                            ],
                        ];

                        foreach ($arr as $key => $value) {
                            $dateStr = node("//td[contains(., '$value[0] Date:')]/following-sibling::td[1]");
                            $timeStr = re('#' . $value[1] . '\s+time:\s+(\d+:\d+\s*(?:am|pm)|noon)#i');

                            if (strtolower($timeStr) == 'noon') {
                                $timeStr = '12:00';
                            }
                            $res[$key . 'Date'] = strtotime("$dateStr, $timeStr");
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Reservation\s+for:\s+(.*)#i')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Number\s+of\s+people:\s+(\d+)\s+Adults?\s+(\d+)\s+Child#';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Guests' => (int) $m[1],
                                'Kids'   => (int) $m[2],
                            ];
                        } else {
                            return null;
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return str_replace("\n", ' ', node("//tr[contains(., 'Cancellation Policy:')]/following-sibling::tr[1]"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Rate Type")]/following-sibling::tr[2]/td//text()';
                        $roomInfoNodes = array_values(array_filter(nodes($xpath)));

                        if (count($roomInfoNodes) >= 3) {
                            return [
                                'RoomType'            => $roomInfoNodes[0],
                                'RoomTypeDescription' => "$roomInfoNodes[1] $roomInfoNodes[2]",
                            ];
                        }
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Subtotal:\s+([\d\.\s,]+\s+\w+)(?:\s+Plus\s+([\d\.,]+\s+Gold\s+Points\s+Plus))?#';

                        if (preg_match($regex, $text, $m)) {
                            $currency = currency($m[1]);
                            $cost = cost(($currency == 'JPY') ? str_replace(',', '', $m[1]) : $m[1]);

                            return [
                                'Cost'        => $cost,
                                'Currency'    => $currency,
                                'SpentAwards' => (isset($m[2])) ? $m[2] : null,
                            ];
                        }
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//td[contains(., 'Estimated Taxes:')]/following-sibling::td[1]"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = node("//td[contains(., 'Estimated Total Price:')]/following-sibling::td[1]");

                        if ($subj) {
                            $currency = currency($subj);
                            $total = cost(($currency == 'JPY') ? str_replace(',', '', $subj) : $subj);

                            return [
                                'Total'    => $total,
                                'Currency' => $currency,
                            ];
                        }
                    },
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
