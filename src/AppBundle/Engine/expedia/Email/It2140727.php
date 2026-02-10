<?php

namespace AwardWallet\Engine\expedia\Email;

class It2140727 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "#Thank you for booking your trip with Expedia#ix";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "6735";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "expedia/it-2140727.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // this format looks like usual emailTravelConfirmationChaecker but it consists of divs, not tr tags
                    return xpath("//img[contains(@src, 'hot_t')]/ancestor::tr[1]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Itinerary number\s*:\s*([A-Z\d\-]+)#ix", $this->text());
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]+)\n\s*Check in#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#Check in\s*:\s*([^\n]*?)\s+(?:Check out:|\n)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#Check out\s*:\s*([^\n]*?)\s+(?:Nights:|\n)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return $it['HotelName'];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return niceName(re("#Room reservation\s*:\s*([^\n\-]+)#"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+adult#i");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total room cost\s*:\s*([^\n]+)#ix"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes & fees\s*:\s*([^\n]+)#ix"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Lodging total\s*:\s*([^\n]+)#"), 'Total');
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
