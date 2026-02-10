<?php

namespace AwardWallet\Engine\alamo\Email;

class AlamoCar extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?alamo#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Alamo\s+Car#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#alamo#i";
    public $reProvider = "#alamo#i";
    public $xPath = "";
    public $mailFiles = "alamo/it-1887418.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick-up', 'Dropoff' => 'Drop-off'] as $key => $value) {
                            $subj = cell("$value:", +1, 0, '//text()');

                            if (preg_match('#(\w+\.?\s+\d+,\s+\d+\s+\d+:\d+.*)\s+((?s).*)#i', $subj, $m)) {
                                $res[$key . 'Datetime'] = strtotime($m[1]);
                                $res[$key . 'Location'] = nice($m[2], ',');
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $x = '(//*[normalize-space(.) = "Rental Car Details"]/following-sibling::table[1]//td[2])[1]//text()';
                        $subj = implode("\n", nodes($x));

                        if (preg_match('#(.*)\s+-\s+(.*)\s+(.*)#i', $subj, $m)) {
                            return [
                                'RentalCompany' => $m[1],
                                'CarType'       => $m[2],
                                'CarModel'      => $m[3],
                            ];
                        }
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node('//img[contains(@src, "images/rentalCars")]/@src');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Renter\'s\s+Name:\s+(.*)#i');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+Rental\s+Price\s+(.*)#'));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Taxes\s+&\s+Fees\s+(.*)#'));
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
