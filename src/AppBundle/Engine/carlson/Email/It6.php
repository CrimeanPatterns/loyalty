<?php

namespace AwardWallet\Engine\carlson\Email;

class It6 extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@radisson.com#i";
    public $reProvider = "#radisson.com#i";
    public $rePlain = "#Your Radisson Reservation Confirmation#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Your Country Inns & Suites By Carlson Reservation Confirmation#i";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null) {
                    // Parser toggled off as it is covered by emailReservationConfirmationChecker.php
                    return null;
                //					return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null) {
                        return re("#Reservation Summary for Confirmation Number:\s+(\S+)#");
                    },

                    "HotelName" => function ($text = '', $node = null) {
                        $infoNode = $this->http->XPath->query("//node()[contains(text(), 'This is a post only e-mail. Please do not reply')]/ancestor::table[1]/following-sibling::table[1]//tr/td[2]")->item(0);
                        $name = node("./p/b[1]", $infoNode);
                        $addressNodes = nodes("./p/b[1]/following-sibling::node()/text()", $infoNode);

                        if (count($addressNodes) >= 3) {
                            $address = trim(join(' ', $addressNodes));
                            $phone = $addressNodes[2];
                            $detailedAddress['AddressLine'] = $addressNodes[0];

                            if (preg_match('#(.*),\s+([a-z]+)\s+([0-9]+)#i', $addressNodes[1], $m)) {
                                $detailedAddress['CityName'] = $m[1];
                                $detailedAddress['PostalCode'] = $m[3];
                                $detailedAddress['StateProv'] = $m[2];
                            }

                            return [
                                'HotelName'       => $name,
                                'Address'         => $address,
                                'DetailedAddress' => $detailedAddress,
                                'Phone'           => $phone,
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null) {
                        return strtotime(node("//td[contains(., 'Arrival Date:')]/following-sibling::td[1]"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null) {
                        return strtotime(node("//td[contains(., 'Departure Date:')]/following-sibling::td[1]"));
                    },

                    "Guests" => function ($text = '', $node = null) {
                        $guestsInfo = node("//td[contains(., 'Number of people:')]/following-sibling::td[1]");

                        if (preg_match('#([0-9]+)\s+Adults?\s+([0-9]+)\s+Children#', $guestsInfo, $m)) {
                            return ['Guests' => (int) $m[1], 'Kids' => (int) $m[2]];
                        } else {
                            return null;
                        }
                    },

                    "Total" => function ($text = '', $node = null) {
                        $s = node("//td[contains(., 'Estimated Total Price:')]/following-sibling::td[1]");

                        return [
                            'Total'    => cost($s),
                            'Currency' => currency($s),
                        ];
                    },

                    "Taxes" => function ($text = '', $node = null) {
                        return cost(node("//td[contains(., 'Estimated Taxes:')]/following-sibling::td[1]"));
                    },

                    "RoomType" => function ($text = '', $node = null) {
                        $roomInfoNodes = nodes("//tr[contains(., 'Rate Type')]/following-sibling::tr[2]/td/p/span/text()");

                        if (count($roomInfoNodes) >= 3) {
                            return [
                                'RoomType'            => $roomInfoNodes[0],
                                'RoomTypeDescription' => "$roomInfoNodes[1] $roomInfoNodes[2]",
                            ];
                        }
                    },

                    "Cost" => function ($text = '', $node = null) {
                        return cost(node("//span[contains(., 'Subtotal:')]"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null) {
                        return str_replace("\n", ' ', node("//tr[contains(., 'Cancellation Policy:')]/following-sibling::tr[1]"));
                    },

                    "GuestNames" => function ($text = '', $node = null) {
                        return [node("//*[contains(text(), 'Reservation for:')]/ancestor::td[1]/following-sibling::td[1]")];
                    },
                ],
            ],
        ];
    }
}
