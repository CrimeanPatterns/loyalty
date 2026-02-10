<?php

namespace AwardWallet\Engine\aplus\Email;

class It2451928 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*Von\s*:[^\n]*?accor#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]accor#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]accor#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "15.02.2015, 17:56";
    public $crDate = "09.02.2015, 21:23";
    public $xPath = "";
    public $mailFiles = "aplus/it-2451928.eml, aplus/it-2455136.eml";
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
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Buchungsnummer|Neue\s*Buchungsnr)\s*[.:\s]+([A-Z\d\-]+)#i");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(uberDate(re("#\n\s*Aufenthaltsdaten\s*:\s*([^\n]+)#"))));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(uberDate(re("#\n\s*Aufenthaltsdaten\s*:\s*([^\n]+)#"), 2)));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $info = re("#Änderung\s*Ihrer\s*Reservierung\s*:\s*(.*?)\s+Kontakt\s*:#is");

                        if (!$info) {
                            $info = re("#\n\s*Buchungsnummer[:\s]+[^\n]+\s+(.*?)\s+Kontakt\s*:#is");
                        }

                        return [
                            "HotelName" => nice(detach("#^[^\(]+#", $info)),
                            "Phone"     => nice(detach("#Telefon\s*Nr\s*:\s*([\(\)\d\s/.+\-]+)#i", $info)),
                            "Fax"       => nice(detach("#Fax\s*Nr\s*:\s*([\(\)\d\s/.+\-]+)#i", $info)),
                            "Address"   => nice(clear("#^s*\([^\)]+\)|Email\s*.+#", $info)),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Für\s*:\s*([^\n]*?)\s{2,}#"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s*Erwachsene#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#Anzahl\s*der\s*Zimmer\s*:\s*(\d+)#i");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#Stornierungskonditionen\s+([^\n]*?)\s+Anreisezeit\s+#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Zimmertyp\s*:\s*([^\n]*?)\s{2,}#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(
                            orval(
                                re("#Verbleibender, im Hotel zu begleichender Betrag[:\s]+([\d.,]+\s*[A-Z]+)#"),
                                re("#Gesamtsumme der Buchung\s*:\s*([\d.,]+\s*[A-Z]+)#")
                            ), "Total");
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#Anzahl der verbrauchten Punkte\s*:\s*([^\n]*?)\s{2,}#ix");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#(\w+) wir Ihnen hiermit#uix"),
                            re("#nun (bestätigt)#i")
                        );
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
