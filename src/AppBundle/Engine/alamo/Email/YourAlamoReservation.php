<?php

namespace AwardWallet\Engine\alamo\Email;

class YourAlamoReservation extends \TAccountCheckerExtended
{
    public $reFrom = "#info@alamo.nl#i";
    public $reProvider = "#alamo.nl#i";
    public $rePlain = "#Alamo\s+Autohuur\s+Voucher#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "nl";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "alamo/it-1807171.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return node('//p[contains(., "Confirmationnumber")]/following-sibling::p[11]');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick-up', 'Dropoff' => 'Drop-off'] as $key => $value) {
                            $subj = node('//p[contains(., "' . $value . ' date")]/following-sibling::p[11]');
                            $res[$key . 'Datetime'] = strtotime($subj);
                            $res[$key . 'Location'] = node('//p[contains(., "' . $value . ' location")]/following-sibling::p[11]');
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return node('//p[contains(., "Car type")]/following-sibling::p[11]');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return node('//p[contains(., "Driver")]/following-sibling::p[11]');
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
        return ["nl"];
    }
}
