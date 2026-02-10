<?php

namespace AwardWallet\Engine\carlson\Email;

class It1632366 extends \TAccountCheckerExtended
{
    public $reFrom = "#@parkinn\.co\.uk#i";
    public $reProvider = "#\bcarlsonhotel\b#i";
    public $rePlain = "#@parkinn\.co\.uk|From:\s*Radisson#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "carlson/it-1673160.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return node("//font[contains(text(), 'Confirmation number')]/ancestor::tr[1]/following-sibling::tr[4]/td[4]");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("(//font[contains(text(), 'Your booking')])[3]/ancestor::tr[1]/following-sibling::tr[2]/td[4]/descendant::td[1]/following-sibling::td[2]/font[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#Arrival:\s*([^\n]+)#ims") . ' ' . re("#Check in time\s*([^\n*]+)#ims"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#Departure:\s*([^\n]+)#ims") . ' ' . re("#Check out time\s*([^\n*]+)#ims"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = nodes("(//font[contains(text(), 'Your booking')])[3]/ancestor::tr[1]/following-sibling::tr[2]/td[4]/descendant::td[1]/following-sibling::td[2]/font[1]/following-sibling::font");

                        foreach ($addr as $key => $str) {
                            if (preg_match("#^T:\s+[\+\-\(\) \d]+$#m", $str)) {
                                $phone = $str;
                                $phonekey = $key;
                                unset($addr[$key]);

                                break;
                            }
                        }
                        array_splice($addr, $phonekey);

                        return ["Address" => implode(', ', $addr), "Phone" => str_replace('T: ', '', $phone)];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#First Name\s*([^\n]+)#ims") . ' ' . re("#Last Name\s*([^\n]+)#ims");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#for (\d+) room#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('(//font[contains(text(), "Cancellation Policy:")])[1]/ancestor::tr[1]/following-sibling::tr[1]');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        preg_match_all('/Room \d:(.*?)\n/ims', $this->text(), $m);

                        return implode($m[1], '|');
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Estimated taxes", +1, 0));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#for\s+\d+\s+rooms*\s*([\d.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell("Estimated taxes", +1, 0));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your booking has been\s+(\w+)#");
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
