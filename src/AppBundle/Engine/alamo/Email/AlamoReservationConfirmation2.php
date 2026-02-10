<?php

namespace AwardWallet\Engine\alamo\Email;

class AlamoReservationConfirmation2 extends \TAccountCheckerExtended
{
    public $rePlain = "#reservations@goalamo\.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#reservations@goalamo\.com#i";
    public $reProvider = "";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "alamo/it-1620264.eml, alamo/it-1975938.eml, alamo/it-1980293.eml, alamo/it-2162885.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [text($this->http->Response['body'])];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Your\s+confirmation\s+number|Your\s+On\s+Request\s+itinerary\s+number|The\s+confirmation\s+number)\s+(?:is|was):\s*([\w\-]+)#i');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#Pickup Information.*?Location:\s*([^\n]+)#ims");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(str_replace("@ ", "", re("#Pickup Information.*?Date & Time:\s*([^\n]+)#ims")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#Dropoff Information.*?Location:\s*([^\n]+)#ims");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(str_replace("@ ", "", re("#Dropoff Information.*?Date & Time:\s*([^\n]+)#ims")));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Phone:\s*([^\n]+)#ims");
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Fax:\s*([^\n]+)#ims");
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#Pickup Information.*?Hours:\s*([^\n]+)#ims");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return "Alamo";
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#Vehicle Type:\s*([^\n]+)-#ims");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re("#Vehicle Type:\s*[^\n]+-\s*([^\n]*)#ims");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#Name:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $total = re("#Estimated Total\.+([^\n]+)#ims");

                        if (preg_match("#(.*?)([\d,.]+)#ims", $total, $m)) {
                            return [
                                "TotalCharge" => $m[2],
                                "Currency"    => nice($m[1]) ? nice($m[1]) : null,
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s*([^\n]+)#is', implode("\n", nodes('//span[contains(., "Status")]//text()')));
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (!empty(re('#(reservation has been cancelled)#is'))) {
                            return true;
                        }

                        return false;
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
