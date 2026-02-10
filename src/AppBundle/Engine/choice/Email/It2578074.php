<?php

namespace AwardWallet\Engine\choice\Email;

class It2578074 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]choice#i', 'blank', ''],
        ['#Learn\s+more\s+about\s+the\s+Choice#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]choicehotels.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]choicehotels.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.03.2015, 19:19";
    public $crDate = "20.03.2015, 15:41";
    public $xPath = "";
    public $mailFiles = "choice/it-2578074.eml";
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
                        return re("#\n\s*Your confirmation number is[:\s]+([A-Z\d\-]+)#ix");
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Member Number[:\s]+([A-Z\d\-]+)#ix");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Phone:')]/preceding::a[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-in Date[:\s]+\w+,\s*([^\n]+)#ix"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-out Date[:\s]+\w+,\s*([^\n]+)#ix"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $address = implode("\n", $this->http->FindNodes("//*[contains(text(), 'Phone:')]/ancestor::div[1]//text()[normalize-space()]"));

                        return [
                            'Address' => nice(re("#^(.*?)\n\s*Phone\s*:\s*([+\d\(\)\s -]{4,})\s+Fax\s*:\s*([+\d\(\)\s -]{4,})#s", $address)),
                            'Phone'   => nice(re(2)),
                            'Fax'     => nice(re(3)),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Name[:\s]+([^\n]+)#ix");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Room Description')]/following::tr[1]/td[2]", null, true, "#^(\d+)$#");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Room Description')]/following::tr[1]/td[3]", null, true, "#^(\d+)$#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(normalize-space(text()), 'change or cancel')])[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = implode("\n", $this->http->FindNodes("//*[contains(text(), 'Room Description')]/following::tr[1]/td[1]//text()[normalize-space()]"));

                        return re("#^([^\n]+)\s+(.+)$#s", $type);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return nice(re(2));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $total = clear("#\([^\)]+\)#", cell("Estimated Total", +1, 0, "//text()"));

                        return cost(re("#([^\d]+[\d.,]+)\s+[^\d]+[\d.,]+\s+[^\d]+[\d.,]+\s*$#", $total));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $total = clear("#\([^\)]+\)#", cell("Estimated Total", +1, 0, "//text()"));

                        return cost(re("#([^\d]+[\d.,]+)\s+[^\d]+[\d.,]+\s*$#", $total));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $total = clear("#\([^\)]+\)#", cell("Estimated Total", +1, 0, "//text()"));

                        return cost(re("#[^\d]+[\d.,]+\s*$#", $total));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $cur = re("#\(([^\)]+)\)#", cell("Estimated Total", +1, 0));

                        if (stripos($cur, 'US Dollar') !== false) {
                            return 'USD';
                        }

                        if (stripos($cur, 'Peso mexicano') !== false) {
                            return 'MXN';
                        }

                        if (stripos($cur, 'New Zealand Dollar') !== false) {
                            return 'NZD';
                        }

                        if (stripos($cur, 'Canadian Dollar') !== false) {
                            return 'CAD';
                        }

                        return $cur;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Status[:\s]+([A-Z\d\-]+)#ix");
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
