<?php

namespace AwardWallet\Engine\expedia\Email;

class ReservationBESTPlain extends \TAccountCheckerExtended
{
    public $rePlain = "#The\s+booking\s+you\s+recently\s+made\s+on\s+the\s+(?:Traxo|BEST)\s+website\s+is\s+confirmed|Your\s+cancellation\s+request\s+has\s+been\s+received\s+and\s+forwarded\s+to\s+the\s+hotel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#expedia#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia|reply@ian.com#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "expedia/it-66.eml, expedia/it-67.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');

                    if (!preg_match($this->rePlain, $text)) {
                        // Ignore emails of other types
                        return null;
                    }
                    $this->parsedValue("userEmail", nice(re('#Customer\s+email:\s+(.*)#i')));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Itinerary\s+Number:\s*([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $subj = nice(orval(
                            re('#\n\s*Hotel\s+(?:\[.*\]\s+)?(.*?)\s*[<\n]#i'),
                            re('#Cancellation\s+Details\s+(.*)\s*<#i')
                        ));
                        $this->hotelName = $subj;

                        return $subj;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#Check-in: *([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#Check-out: *([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $subj = nice(re("#Address:\s*(.+)#"));

                        return $subj;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Phone:\s*?([^\n]*)#i'));
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Fax:\s*?([^\n]*)#i'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#Customer\s+name:\s*([^\n]+)#i"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $subj = re('#\n\s*(?:Number\s+of\s+guests|Guests):.*#i');

                        if (preg_match('#(\d+)\s+Adult#i', $subj, $m) or preg_match('#Adults:\s+(\d+)#i', $subj, $m)) {
                            $res['Guests'] = $m[1];
                            $res['Guests'] = $m[1];
                        }

                        return $res;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#\n\s*Rooms:\s+(\d+)#i');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Total\s+per\s+night\s*\n\s*.*\s+(\S+)\s*\n#i');

                        if ($subj) {
                            return $subj . ' per night';
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Cancellation\s+policy.*#is');

                        if (preg_match_all('#Room:\s+\d+:\s+(.*)#i', $subj, $m)) {
                            return implode('|', nice($m[1]));
                        } else {
                            return nice(re('#Cancellation\s+Policy\s+(.*)#i'));
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#\#\s+Room\s+Type[^\n]+(.*?)\n\s*Charges#is');

                        if (preg_match_all('#\n\s*\d+\s*\n\s*(.*)#i', $subj, $m)) {
                            return implode('|', nice($m[1]));
                        } else {
                            return nice(re('#Room\s+type:\s+(.*)#i'));
                        }
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Total\s+per\s+room.*\s+(\S+)\s*\n#i'));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#\n\s*Tax\s+Recovery\s+Charges\s+and\s+Service\s+Fees\s+(.*)#i'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#\n\s*Paid\s+(.*)#i'));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re('#Total\s+cost\s+for\s+entire\s+stay\s+in\s+(.*)#i'));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Your\s+reservation\s+is\s+(\w+)\s+#i'),
                            re('#Your\s+(reservation\s+cancellation\s+is\s+pending)#i')
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
