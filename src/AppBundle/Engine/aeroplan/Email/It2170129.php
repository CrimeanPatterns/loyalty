<?php

namespace AwardWallet\Engine\aeroplan\Email;

class It2170129 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?aeroplan#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#aeroplan#i";
    public $reProvider = "#aeroplan#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "aeroplan/it-2170129.eml";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*CONFIRMATION NUMBER\s+([A-Z\d-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//text()[contains(normalize-space(.), 'PICK-UP')]/ancestor-or-self::td[1]"));
                        detach("#^PICK-UP\s+LOCATION\s+#i", $text);

                        return [
                            'PickupDatetime' => totime(detach("#(\w{3}\s+\d+,\s*\d+\s+\d+:\d+(?:\s*[APM]+)?)#", $text)),
                            'PickupLocation' => nice($text),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//text()[contains(normalize-space(.), 'DROP-OFF')]/ancestor-or-self::td[1]"));
                        detach("#^DROP-OFF\s+LOCATION\s+#i", $text);

                        return [
                            'DropoffDatetime' => totime(detach("#(\w{3}\s+\d+,\s*\d+\s+\d+:\d+(?:\s*[APM]+)?)#", $text)),
                            'DropoffLocation' => nice($text),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*COMPANY\s+([^\n]+)#");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//text()[contains(normalize-space(.), 'VEHICLE')]/ancestor-or-self::td[1]"));
                        detach("#VEHICLE\s+MODEL\s+#i", $text);

                        return [
                            'CarType'  => re("#(.*?)\s+\-\s+(.+)#s", $text),
                            'CarModel' => re(2),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hi\s+([^\n,]+),#");
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*MILES REDEEMED\s+([^\n]+)#x");
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
