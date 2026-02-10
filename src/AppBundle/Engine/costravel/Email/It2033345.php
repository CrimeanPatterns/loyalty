<?php

namespace AwardWallet\Engine\costravel\Email;

class It2033345 extends \TAccountCheckerExtended
{
    public $rePlain = "#customercare@costcotravel.com#";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#costravel#i";
    public $reProvider = "#costravel#i";
    public $caseReference = "9062";
    public $xPath = "";
    public $mailFiles = "costravel/it-2033345.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'simpletable');

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Booking Number:\s*([^\n]+)#");

                        return $node;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Hotel Name')]/ancestor-or-self::tr/following-sibling::tr[1]/td[2]");

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Check In Date')]/ancestor-or-self::tr/following-sibling::tr[1]/td[39]");
                        $node = uberDatetime($node);

                        return totime($node);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Check In Date')]/ancestor-or-self::tr/following-sibling::tr[1]/td[47]");
                        $node = uberDatetime($node);

                        return totime($node);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Location')]/ancestor-or-self::tr/following-sibling::tr[1]/td[24]");

                        return $node;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $i = 2;
                        $passengers = [];
                        $node = node("//*[contains(text(), 'Passenger(s)')]/ancestor-or-self::tr/following-sibling::tr[" . $i . "]/td[3]");
                        $node2 = node("//*[contains(text(), 'Passenger(s)')]/ancestor-or-self::tr/following-sibling::tr[" . $i . "]/td[19]");
                        $tnode = node("//*[contains(text(), 'Passenger(s)')]/ancestor-or-self::tr/following-sibling::tr[" . $i . "]/td[4]");

                        while ($tnode !== null) {
                            $i++;
                            $passengers[] = trim($node) . " " . trim($node2);

                            $node = node("//*[contains(text(), 'Passenger(s)')]/ancestor-or-self::tr/following-sibling::tr[" . $i . "]/td[3]");
                            $node2 = node("//*[contains(text(), 'Passenger(s)')]/ancestor-or-self::tr/following-sibling::tr[" . $i . "]/td[19]");
                            $tnode = node("//*[contains(text(), 'Passenger(s)')]/ancestor-or-self::tr/following-sibling::tr[" . $i . "]/td[4]");
                        }

                        return [
                            'GuestNames' => $passengers,
                            'Guests'     => count($passengers),
                        ];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Room #')]/ancestor-or-self::tr/following-sibling::tr[1]/td[5]");

                        return $node;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Room #')]/ancestor-or-self::tr/following-sibling::tr[1]/td[8]");

                        if ($node != null) {
                            $node = explode("-", $node);

                            if (strpos($node[1], "Bed")) {
                                $node[1] = re("#([^\n]+ Bed)#", $node[1]);
                            }
                        }

                        return [
                            'RoomType'            => $node[0],
                            'RoomTypeDescription' => $node[1],
                        ];
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $node = re("#TOTAL PRICE:\s*([^\n]+)#");

                        return total($node, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Status:\s*([^\n]+)#");
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
