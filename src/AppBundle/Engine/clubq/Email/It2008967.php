<?php

namespace AwardWallet\Engine\clubq\Email;

class It2008967 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?clubq#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#clubq#i";
    public $reProvider = "#clubq#i";
    public $caseReference = "9022";
    public $xPath = "";
    public $mailFiles = "clubq/it-2008967.eml";
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
                        return re("#Confirmation \#:\s*([^\n]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//table[1]//font)[1]");

                        if ($node == null) {
                            $node = node("(//table[1]//tr[1]//span)[1]");
                        }

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Check In:", +1);

                        $node = uberDate($node);

                        return totime($node);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Check Out:", +1);
                        $node = uberDate($node);

                        return totime($node);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//table[1]//font)[2]");

                        if ($node == null) {
                            $node = node("(//table[1]//tr[2]/td[2])[1]");
                        }

                        return $node;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node("//table[1]//font//a");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Guest Name:\s*([^\n]+)#");

                        return $node;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell("# of Guests:", +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell("# of Rooms:", +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell("Nightly Rate:", +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell("Policies:", +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = re("#Room Type & Description:\s*([^\n]+)#");

                        $desc = cell("Room Type & Description:", +1);
                        $desc = str_replace($type, '', $desc);

                        return [
                            'RoomType'            => $type,
                            'RoomTypeDescription' => $desc,
                        ];
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
