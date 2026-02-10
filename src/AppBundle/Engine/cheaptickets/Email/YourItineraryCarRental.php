<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class YourItineraryCarRental extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?cheaptickets#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#cheaptickets#i";
    public $reProvider = "#cheaptickets#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "cheaptickets/it-2122505.eml";
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
                        return re('#Confirmation\s+number\s*:\s+([\w\-]+)#i');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick-up', 'Dropoff' => 'Drop-off'] as $key => $value) {
                            $res[$key . 'Datetime'] = strtotime(cell($value, +1));
                            $rentalInfo = cell($value, +2);

                            if (preg_match('#(.*)\s*Phone\s*:\s+(.*)\s*Hours:\s+(.*)#is', $rentalInfo, $m)) {
                                $res[$key . 'Location'] = nice($m[1]);
                                $res[$key . 'Phone'] = nice($m[2]);
                                $res[$key . 'Hours'] = nice($m[3]);
                            } elseif (preg_match('#drop-off\s+location\s+same\s+as\s+pick-up\s+location\s+listed\s+above#', $rentalInfo)) {
                                foreach (['Location', 'Phone', 'Hours'] as $key2) {
                                    $res['Dropoff' . $key2] = $res['Pickup' . $key2] ?? null;
                                }
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (re('#Car\s+details\s*:\s+(.*similar\)),\s+(.*)#i')) {
                            return [
                                'CarModel' => nice(re(1)),
                                'CarType'  => nice(trim(re(2), ',')),
                            ];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Primary\s+driver\s*:\s+(.*)#i');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+car\s+rental\s+estimate\s+(.*)#i'));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Reservation\s+Made\s*:\s+(.*)#'));
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
