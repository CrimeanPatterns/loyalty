<?php

namespace AwardWallet\Engine\amextravel\Email;

class DiningReservations extends \TAccountCheckerExtended
{
    public $rePlain = "#American\s+Express\s+Centurion\s+Concierge#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Amex\s+Centurion\s+Concierge#i";
    public $reProvider = "#amex#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2143470.eml";
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
                        return "E";
                    },

                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        $c = re('#Confirmation\s*\#\s*:\s+([\w\-]+)#i');

                        return $c ? $c : CONFNO_UNKNOWN;
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#----+\s+(.*)\s+((?s).*?)\s+([\+\(][\-\d\(\)\s/]+)#i', $text, $m)) {
                            return [
                                'Name'    => $m[1],
                                'Address' => nice($m[2], ','),
                                'Phone'   => nice($m[3]),
                            ];
                        }
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $dateStr = re('#Day/Date:\s*(.*)#i');
                        $timeStr = re('#Time:\s+(\d+:\d+\s*(?:am|pm))#i');

                        if ($dateStr and $timeStr) {
                            return strtotime($dateStr . ', ' . $timeStr);
                        }
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return re('#Reserved\s+Under:\s+(.*)#i');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Party\s+Of:\s*(\d+)#i');
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
