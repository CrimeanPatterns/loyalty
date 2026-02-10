<?php

namespace AwardWallet\Engine\expedia\Email;

class It2146606 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?expedia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "expedia/it-2146606.eml, expedia/it-2146911.eml, expedia/it-24.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text = $this->setDocument("plain")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Itinerary number\s*:\s*([A-Z\d-]+)#x");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#Hotel\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        re("#\n\s*Check[\s-]+in\s*:\s*\w+\s+(\d+)/(\d+)/(\d+)\s*Check#i");

                        return totime(re(3) . '-' . re(1) . '-' . re(2));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        re("#Check[\s-]+out\s*:\s*\w+\s+(\d+)/(\d+)/(\d+)#i");

                        return totime(re(3) . '-' . re(1) . '-' . re(2));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#Hotel\s*:\s*([^\n]+)#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone\s*:\s*([\d \(\)\-+]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n\s*Room reservation\s*:\s*([^\n-]+)#"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(\d+)\s+adult#i");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room type\s*:\s*([^\n<]+)#ix");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return clear("#<[^>]+>#", re("#\n\s*([^\n]+)\n\s*Room type\s*:#ix"));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total room cost\s*:\s*([^\n]+)#x"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes & fees\s*:\s*([^\n]+)#x"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Lodging total\s*:\s*([^\n]+)#x"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#item\(s\) you just ([^\n,.;]+)#x");
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
