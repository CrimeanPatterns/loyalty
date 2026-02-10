<?php

namespace AwardWallet\Engine\amextravel\Email;

class It2253030 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?(amextravel|AMERICAN\s*EXPRESS)#i";
    public $rePlainRange = "2000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#AMERICAN\s*EXPRESS#i";
    public $rePDFRange = "3000";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amextravel#i";
    public $reProvider = "#amextravel#i";
    public $caseReference = "9499";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2253030.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = text($this->setDocument("application/pdf", "simpletable"));

                    return splitter("#\n\s*(CHECK\s*IN\s*:\s*)#", $text);
                },

                "#CHECK\s*IN#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'CHECK IN')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1][1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("//tr[1]/td[string-length(normalize-space(.))>1][1]");
                        $year = re("#\s+(\d{4})$#", $date);

                        $in = node("//*[contains(text(), 'CHECK IN')]/ancestor-or-self::td[1]/following-sibling::td[string-length(normalize-space(.))>1][1]", null, true, "#^\w+\s*(.+)#") . ' ' . $year;
                        $out = node("//*[contains(text(), 'CHECK OUT')]/ancestor-or-self::td[1]/following-sibling::td[string-length(normalize-space(.))>1][1]", null, true, "#^\w+\s*(.+)#") . ' ' . $year;

                        correctDates($in, $out, $date);

                        return [
                            'CheckInDate'  => $in,
                            'CheckOutDate' => $out,
                        ];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Fax')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1][1]");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Phone')]/following::td[string-length(normalize-space(.))>1][1]", null, true, "#^([\d\-+\(\)\s]{4,})$#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Fax')]/following::td[string-length(normalize-space(.))>1][1]", null, true, "#^([\d\-+\(\)\s]{4,})$#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'PREPARED FOR')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1][1]");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Guest\(s\)\s*:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Room\(s\)\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Rate\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return clear("#Cancellation Information\s*:\s*#", cell("Cancellation Information:"));
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return clear("#[\d.,]+\s*[A-Z]{3}\s+APPROX[\s.]+TTL.+#s",
             clear("#Room Details\s*:\s*#",
                           cell("Room Details:")));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#([\d.,]+\s*[A-Z]{3})\s+APPROX[\s.]+TTL#"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
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
