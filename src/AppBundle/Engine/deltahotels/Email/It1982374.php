<?php

namespace AwardWallet\Engine\deltahotels\Email;

class It1982374 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?deltahotels#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#deltahotels#i";
    public $reProvider = "#deltahotels#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "deltahotels/it-1982374.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'confirmation')]/ancestor-or-self::span[1])[1]");
                        $node = str_replace("confirmation # ", "", $node);

                        return $node;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'hotel information')]/ancestor-or-self::td[1]//font[2]");

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $arrivalDate = node("//*[contains(text(), 'arrival date:')]/ancestor-or-self::td[1]");
                        $arrivalDate = str_replace("arrival date: ", "", $arrivalDate);
                        $checkinTime = re("#\n\s*check\s*in\s*time\s*:\s*([^\n]+)#");
                        $date = $arrivalDate . " " . $checkinTime;
                        $date = uberDatetime($date);

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $depDate = node("//*[contains(text(), 'departure date:')]/ancestor-or-self::td[1]");
                        $depDate = str_replace("departure date: ", "", $depDate);
                        $checkinTime = re("#\n\s*check\s*out\s*time\s*:\s*([^\n]+)#");
                        $date = $depDate . " " . $checkinTime;
                        $date = uberDatetime($date);

                        return totime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'hotel information')]/ancestor-or-self::td[1]//font[3]");

                        $node = explode(" check", $node);

                        $node = $node[0];

                        return $node;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'guest details')]/ancestor-or-self::tr[1]/following-sibling::tr[1]");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'number of guests')]/ancestor-or-self::tr[1]/following-sibling::tr[1]");
                        $adult = (int) re("#([0-9])\s*adult#", $node);
                        $children = (int) re("#([0-9])\s*children#");

                        return [
                            'Guests' => $adult + $children,
                            'Kids'   => $children,
                        ];
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'room and rate')]/ancestor-or-self::tr[1]/following-sibling::tr[1]");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'room total')]/ancestor-or-self::tr[1]/td[2]");

                        return cost($node);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'taxes')]/ancestor-or-self::tr[1]/td[2]");

                        return cost($node);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $node = total(node("(//*[contains(text(), 'total')]/ancestor-or-self::tr[1]/td[2])[2]"), 'Total');

                        return $node;
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
