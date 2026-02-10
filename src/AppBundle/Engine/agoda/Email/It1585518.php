<?php

namespace AwardWallet\Engine\agoda\Email;

class It1585518 extends \TAccountCheckerExtended
{
    public $reFrom = "#[@.]agoda#i";
    public $reProvider = "#[@.]agoda#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]agoda#i";
    public $typesCount = "1";
    public $langSupported = "ro";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "agoda/it-1585518.eml";
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
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*numărul rezervării[\s:]+([\d\w-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell('Nume hotel', +1);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell('Data sosirii', +1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell('Data plecării', +1));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return cell('Adresă:', +1) . ', ' . cell('Zonă/Oraş/Ţară', +1);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell("Oaspete principal:", +1, 0);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell("Nr. de adulţi", +1, 0);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return glue(nodes("//*[contains(text(), 'Politica de anulare şi modificare')]/ancestor-or-self::tr[1]/following-sibling::tr"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell("Tip cameră:", +1, 0);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return cell("Precizări speciale", +1, 0);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#[.,\d]+#", cell("Suma totală", +1, 0)));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell("Suma totală", +1, 0));
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
        return ["ro"];
    }
}
