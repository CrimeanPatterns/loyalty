<?php

namespace AwardWallet\Engine\amextravel\Email;

class DiningReservations2 extends \TAccountCheckerExtended
{
    public $rePlain = "#American\s+Express\s+Platinum\s+Concierge#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Amex\s+Centurion\s+Concierge|centurionrequests@concierge\.americanexpress\.com|Platinumrequests@concierge\.americanexpress\.com|@concierge\.americanexpress\.com#i";
    public $reProvider = "#amex#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2143439.eml, amextravel/it-2143442.eml, amextravel/it-2143464.eml, amextravel/it-2143465.eml, amextravel/it-2143467.eml, amextravel/it-2143472.eml, amextravel/it-2143476.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (count(nodes('//img[contains(@src, "Platinum-card-concierge.jpg")]')) > 0) {
                        return null;
                    }

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
                        $toRemove = orval(
                            re('#Dear\s+.*,\s+(?:(?s).*?)\n\s*\n((?s)Date:.*?)\n\s*\n#i', $text),
                            re('#Dear\s+.*?,\s*\n\s*\n\s*.*\s*\n\s*\n\s*(.*)\s*\n\s*\n#i', $text)
                        );

                        if ($toRemove) {
                            $fixedText = str_replace($toRemove, '', $text);
                        } else {
                            $fixedText = $text;
                        }

                        if (preg_match('#(?:Dear\s+.*?,\s+(?:(?s).*?)|(?:(?s)Thank\s+you\s+for\s+calling.*?))\n\s*\n\s*(.*?)\s*\n\s*((?s).*?)\s+([\+\(\d][\-\d\(\)\s/]{5,14}(?:\w{2})?)\s*\n#i', $fixedText, $m)) {
                            return [
                                'Name'    => $m[1],
                                'Address' => nice(preg_replace('#http://.*#', '', $m[2]), ','),
                                'Phone'   => nice($m[3]),
                            ];
                        }
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $dateStr = nice(re('#(?:Day/Date|Day|Date|Reservation\s+Date):\s*(\w+,?\s+\w+\s+\d+,\s+\d+)#i'));
                        $timeStr = nice(re('#(?:Time|Reservation\s+Time):\s+(\d+:\d+\s*(?:am|pm)?)#i'));

                        if ($dateStr and $timeStr) {
                            return strtotime($dateStr . ', ' . $timeStr);
                        }
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Reserved\s+Under:\s+(.*)#i'));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Party\s+Of|Party\s+Size):\s*(\d+)#i');
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
